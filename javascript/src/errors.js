/**
 * Error de la API de NanoFactura.
 *
 * Conserva `code`, `status` y `details` en vez de aplanarlos a un string: son los tres datos con
 * los que se decide qué hacer. `code` distingue el motivo, `status` si conviene reintentar y
 * `details` trae el rechazo exacto de ARCA cuando lo hay.
 */
export class NanoFacturaError extends Error {
	constructor(message, { code = null, status = null, details = null } = {}) {
		super(message)
		this.name = 'NanoFacturaError'
		this.code = code
		this.status = status
		this.details = details
	}

	/**
	 * Si el error puede ceder solo. Un reintento sobre un error permanente sólo suma latencia:
	 * un payload inválido lo sigue siendo por más veces que se lo mande.
	 *
	 * Dos códigos entran en el rango de los transitorios pero NO lo son:
	 *
	 * - `SUBSCRIPTION_QUOTA_EXCEEDED` (429) cede al ampliar el límite o al renovarse el período.
	 * - El certificado sin delegar al servicio (502) cede al delegarlo en el portal de ARCA. Es
	 *   `isCertificadoSinDelegar`.
	 */
	get isRetryable() {
		if (this.code === 'SUBSCRIPTION_QUOTA_EXCEEDED') return false
		if (this.isCertificadoSinDelegar) return false
		return [429, 500, 502, 503, 504].includes(this.status)
	}

	/**
	 * ARCA rechazó la autenticación porque al certificado le falta la delegación al servicio que se
	 * está usando (`wsfe` para emitir, los de padrón para consultarlo). Se autoriza cada servicio por
	 * separado, en el Administrador de Relaciones de Clave Fiscal:
	 * https://asistentefacturaelectronica.com/ayuda/api/certificado-arca
	 */
	get isCertificadoSinDelegar() {
		return /Computador no autorizado/i.test(this.message)
	}

	/** Rechazo de negocio de ARCA: `details` trae los códigos exactos que devolvió. */
	get isArcaRejection() {
		return this.code === 'ARCA_ERROR'
	}

	/**
	 * Los códigos de rechazo que devolvió ARCA, o un array vacío si el error no viene de ellos.
	 *
	 * `details` no tiene una sola forma: en un rechazo de ARCA es la lista de observaciones, pero en
	 * un error de validación o de cupo es un objeto. Por eso la pregunta "¿ARCA devolvió el código
	 * N?" se hace acá y no recorriendo `details` a mano, que rompe apenas el error es de otro tipo.
	 */
	get arcaCodes() {
		if (!Array.isArray(this.details)) return []
		return this.details.map((detalle) => Number(detalle?.code)).filter(Number.isFinite)
	}

	/** Si ARCA rechazó el comprobante con ese código. */
	tieneCodigoArca(code) {
		return this.arcaCodes.includes(Number(code))
	}

	/**
	 * Los campos que la API rechazó, o un array vacío si el error no es de validación.
	 *
	 * Cada elemento trae `field` —la ruta del dato dentro del cuerpo que mandaste, con los índices de
	 * los arreglos: `items.0.price`— y `message`. `field` viene en null cuando el problema es del
	 * cuerpo entero y no de un campo puntual.
	 *
	 * Sirve para señalar el campo en un formulario sin interpretar el texto del mensaje. Un
	 * `VALIDATION_ERROR` que sale de una regla de negocio —una clave de idempotencia reusada, por
	 * ejemplo— no trae campos: ahí el array vuelve vacío y lo que explica el error es `message`.
	 */
	get camposInvalidos() {
		const errors = this.details?.errors
		if (!Array.isArray(errors)) return []
		return errors.map((error) => ({
			field: error?.field ?? null,
			message: String(error?.message ?? ''),
		}))
	}
}
