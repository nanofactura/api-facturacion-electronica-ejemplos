import { randomUUID } from 'node:crypto'
import { NanoFacturaError } from './errors.js'

/**
 * Cliente de la API de facturación electrónica de NanoFactura.
 *
 * Documentación: https://asistentefacturaelectronica.com/ayuda/api
 *
 * No tiene dependencias: usa el `fetch` nativo de Node 18+.
 */
export class NanoFactura {
	/**
	 * @param {object} [options]
	 * @param {string} [options.apiKey]   Por defecto, `NANOFACTURA_API_KEY`.
	 * @param {string} [options.idEmpresa]  Por defecto, `NANOFACTURA_ID_EMPRESA`.
	 * @param {string} [options.baseUrl]  Por defecto, `NANOFACTURA_BASE_URL`.
	 * @param {number} [options.timeout]  Milisegundos. Por defecto 30000.
	 * @param {number} [options.retries]  Intentos ante errores transitorios. Por defecto 3.
	 */
	constructor({ apiKey, idEmpresa, baseUrl, timeout = 30_000, retries = 3 } = {}) {
		this.apiKey = apiKey ?? process.env.NANOFACTURA_API_KEY
		this.idEmpresa = idEmpresa ?? process.env.NANOFACTURA_ID_EMPRESA
		this.baseUrl = (baseUrl ?? process.env.NANOFACTURA_BASE_URL ?? 'https://nanofactura.com/api').replace(/\/$/, '')
		this.timeout = timeout
		this.retries = retries

		if (!this.apiKey) throw new Error('Falta NANOFACTURA_API_KEY. Copiá .env.example a .env y completalo.')
		if (!this.idEmpresa) throw new Error('Falta NANOFACTURA_ID_EMPRESA. Copiá .env.example a .env y completalo.')
	}

	/** `true` si la clave opera contra homologación (comprobantes sin validez fiscal). */
	get isTestEnvironment() {
		return this.apiKey.startsWith('TEST_')
	}

	// ── Comprobantes ──────────────────────────────────────────────────────────

	/**
	 * Emite un comprobante y devuelve el CAE.
	 *
	 * La clave de idempotencia se genera acá si no viene en el payload, y se conserva a través de
	 * los reintentos: es lo que hace que un timeout se pueda reintentar sin duplicar el
	 * comprobante. Si la generás vos, guardala junto a tu operación ANTES de emitir, para poder
	 * reusarla si el proceso se cae en el medio.
	 *
	 * Devuelve el comprobante con `quota` agregado: el estado del cupo mensual viaja al lado de
	 * `result` en la respuesta, no adentro, porque es de la empresa y no del comprobante. Se
	 * expone acá para no tener que consultarlo aparte. Puede ser `null` —en reintentos
	 * idempotentes, que no consumen—, así que tratalo como opcional.
	 *
	 * @see https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante
	 */
	async emitirComprobante(payload) {
		const respuesta = await this.#request('POST', '/invoices', {
			body: { idempotency: randomUUID(), ...payload },
			raw: true,
		})
		return { ...respuesta.result, quota: respuesta.quota ?? null }
	}

	/**
	 * Lista comprobantes del entorno de la clave.
	 * Devuelve `{ result, total }`, donde `total` es cuántos matchean el filtro (no los de la página).
	 *
	 * @see https://asistentefacturaelectronica.com/ayuda/api/consultar-comprobantes
	 */
	async listarComprobantes(filtros = {}) {
		const { result, total } = await this.#request('GET', '/invoices', { query: filtros, raw: true })
		return { comprobantes: result, total }
	}

	/**
	 * Un comprobante por su `id`, el que devolvió la emisión: el mismo objeto que una fila del
	 * listado. Si no existe —o es del otro entorno— lanza un `NanoFacturaError` con `code`
	 * `NOT_FOUND` y `status` 404.
	 *
	 * @see https://asistentefacturaelectronica.com/ayuda/api/consultar-comprobantes#un-comprobante-por-id
	 */
	async obtenerComprobante(invoiceId) {
		return this.#request('GET', `/invoices/${invoiceId}`)
	}

	/**
	 * Descarga el PDF y lo devuelve como Buffer.
	 *
	 * No lo guardes en tu sistema luego de generar: pedilo en el momento en que alguien lo abre o lo imprime. El PDF
	 * se genera en segundo plano, así que puede no estar listo apenas emitido — para eso está
	 * `esperarPdf()`.
	 *
	 * @see https://asistentefacturaelectronica.com/ayuda/api/pdf-y-email
	 */
	async descargarPdf(invoiceId, versiones = [1]) {
		const url = `${this.baseUrl}/${this.idEmpresa}/invoices/${invoiceId}/pdf?versions=${versiones.join(',')}`
		const response = await fetch(url, {
			headers: { Authorization: `Bearer ${this.apiKey}` },
			signal: AbortSignal.timeout(this.timeout),
		})

		if (!response.ok) {
			const data = await response.json().catch(() => ({}))
			throw new NanoFacturaError(data.error ?? `No se pudo descargar el PDF (HTTP ${response.status})`, {
				code: data.code,
				status: response.status,
			})
		}
		return Buffer.from(await response.arrayBuffer())
	}

	/**
	 * Descarga el PDF, esperando a que termine de generarse.
	 *
	 * Sólo hace falta si pedís el PDF inmediatamente después de emitir. En el uso normal —pedirlo
	 * cuando el usuario lo abre— ya está listo hace rato y `descargarPdf()` alcanza.
	 */
	async esperarPdf(invoiceId, { versiones = [1], intentos = 10, esperaMs = 2000 } = {}) {
		for (let intento = 1; intento <= intentos; intento++) {
			try {
				return await this.descargarPdf(invoiceId, versiones)
			} catch (error) {
				const ultimoIntento = intento === intentos
				if (error.code !== 'PDF_NOT_READY' || ultimoIntento) throw error
				await sleep(esperaMs)
			}
		}
	}

	/** Agrega un destinatario y programa el envío del PDF. Máximo 3 por comprobante. */
	async agregarDestinatario(invoiceId, destinatario) {
		return this.#request('POST', `/invoices/${invoiceId}/emails`, { body: destinatario })
	}

	/** Vuelve a generar el PDF. Hasta 3 veces por comprobante y dentro de los 90 días. */
	async regenerarPdf(invoiceId) {
		return this.#request('POST', `/invoices/${invoiceId}/pdf/regenerate`)
	}

	// ── Configuración y consultas ─────────────────────────────────────────────

	/**
	 * Valida la clave y la configuración mínima para facturar.
	 *
	 * Es la primera llamada que conviene hacer al integrar, y después sirve para diagnosticar. NO es
	 * un chequeo previo a cada emisión: la configuración cambia una vez cada dos años (cuando vence
	 * el certificado), así que llamarla antes de facturar duplica la latencia sin evitar ningún
	 * error. Si algo falta, la emisión lo dice igual.
	 */
	async verificarConexion() {
		return this.#request('GET', '/api-keys/test', { raw: true })
	}

	/** Puntos de venta configurados en NanoFactura. */
	async listarPuntosDeVenta() {
		return this.#request('GET', '/pos')
	}

	/** Puntos de venta efectivamente habilitados en ARCA. */
	async listarPuntosDeVentaArca() {
		return this.#request('GET', '/pos/arca')
	}

	/**
	 * Consulta un contribuyente en el padrón de ARCA por CUIT o CUIL.
	 *
	 * Devuelve los datos ya mapeados a los códigos de la emisión: `taxCondition` se puede usar tal
	 * cual como `customer.taxType`. `source` dice qué padrón contestó —'constancia' o 'a13'—, y con
	 * `{ service }` elegís uno en vez del que corresponde al tipo de clave. Cada padrón se delega
	 * por separado en ARCA.
	 *
	 * @param {string} documento
	 * @param {object} [options]
	 * @param {'constancia'|'a13'} [options.service]
	 */
	async consultarPadron(documento, { service } = {}) {
		return this.#request('GET', `/arca/padron/${documento}`, { query: { service } })
	}

	/** Diagnóstico: servidores de ARCA, certificado y acceso a los padrones. */
	async estadoArca() {
		return this.#request('GET', '/arca/status', { raw: true })
	}

	/** Datos fiscales de la empresa. */
	async datosEmpresa() {
		return this.#request('GET', '/details')
	}

	// ── Interno ───────────────────────────────────────────────────────────────

	/**
	 * @param {'GET'|'POST'|'PATCH'|'DELETE'} method
	 * @param {string} path      Relativo a `/:idEmpresa`.
	 * @param {object} [options]
	 * @param {object} [options.body]
	 * @param {object} [options.query]
	 * @param {boolean} [options.raw] Devolver la respuesta completa en vez de sólo `result`.
	 */
	async #request(method, path, { body, query, raw = false } = {}) {
		const url = new URL(`${this.baseUrl}/${this.idEmpresa}${path}`)
		for (const [clave, valor] of Object.entries(query ?? {})) {
			if (valor !== undefined && valor !== null) url.searchParams.set(clave, String(valor))
		}

		let ultimoError

		for (let intento = 1; intento <= this.retries; intento++) {
			let response
			try {
				response = await fetch(url, {
					method,
					headers: {
						Authorization: `Bearer ${this.apiKey}`,
						...(body ? { 'Content-Type': 'application/json' } : {}),
					},
					body: body ? JSON.stringify(body) : undefined,
					signal: AbortSignal.timeout(this.timeout),
				})
			} catch (cause) {
				// Corte de red o timeout del cliente. Es el caso más delicado: el comprobante puede
				// haberse emitido igual. El reintento manda la MISMA idempotency, así que si existía
				// vuelve con `idempotencyHit: true` en vez de duplicarse.
				ultimoError = new NanoFacturaError(`No se pudo conectar con la API: ${cause.message}`, { status: 504 })
				if (intento === this.retries) throw ultimoError
				await sleep(backoffMs(intento))
				continue
			}

			const data = await response.json().catch(() => ({}))

			if (data.ok) return raw ? data : data.result

			const error = new NanoFacturaError(data.error ?? `Error HTTP ${response.status}`, {
				code: data.code ?? null,
				status: response.status,
				details: data.details ?? null,
			})

			if (!error.isRetryable || intento === this.retries) throw error

			const retryAfter = Number(response.headers.get('Retry-After'))
			await sleep(Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter * 1000 : backoffMs(intento))
			ultimoError = error
		}

		throw ultimoError
	}
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

/** Backoff exponencial con jitter, para que varios procesos no reintenten todos a la vez. */
const backoffMs = (intento) => 2 ** intento * 500 + Math.random() * 250
