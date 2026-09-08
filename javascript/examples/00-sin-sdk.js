/**
 * 00 · La API cruda — sin cliente y sin dependencias
 *
 * Esto es TODO lo que hace falta para emitir una factura electrónica: un POST con un JSON y la API
 * key en el header. El cliente de `src/` es comodidad —constantes con nombre, reintentos, errores
 * tipados—, no un requisito. Si vas a escribir tu propia integración, empezá por acá.
 *
 * Están activos sólo los campos OBLIGATORIOS. Todo lo demás va comentado, con un valor de ejemplo y
 * los códigos de ARCA al lado, para que veas el pedido completo sin ir a la documentación.
 *
 *   node --env-file=../.env examples/00-sin-sdk.js
 */

const API_KEY = process.env.NANOFACTURA_API_KEY
const ID_EMPRESA = process.env.NANOFACTURA_ID_EMPRESA
const BASE_URL = process.env.NANOFACTURA_BASE_URL ?? 'https://nanofactura.com/api'
const POS_NUMBER = Number(process.env.NANOFACTURA_POS_NUMBER ?? 1)

if (!API_KEY || !ID_EMPRESA) {
	console.error('Faltan NANOFACTURA_API_KEY y/o NANOFACTURA_ID_EMPRESA. Copiá .env.example a .env y completalo.')
	process.exitCode = 1
} else {
	const comprobante = {
		// ── Obligatorios ─────────────────────────────────────────────────────────

		// Tu clave única de este comprobante: si el pedido se corta y lo reintentás con la misma
		// clave, la API devuelve el comprobante que ya emitió en vez de duplicarlo. En tu sistema
		// sale de la venta y se guarda ANTES de emitir. Máx. 128 caracteres: letras, números, - y _.
		idempotency: `sin-sdk-${new Date().toISOString().replace(/\D/g, '')}`,

		posNumber: POS_NUMBER,
		voucherType: 11, // 11 Factura C · 1 Factura A · 6 Factura B · 13/3/8 notas de crédito
		concept: 1, // 1 productos · 2 servicios · 3 ambos
		voucherDate: new Date().toISOString().slice(0, 10).replaceAll('-', ''), // YYYYMMDD

		customer: {
			docType: 96, // 80 CUIT · 86 CUIL · 96 DNI · 99 sin identificar
			docNumber: '35888999', // sin puntos ni guiones
			name: 'Pedro López',
			taxType: 5, // condición del RECEPTOR frente al IVA: 1 RI · 4 exento · 5 consumidor final · 6 monotributo

			// address: 'San Martín 1200, Rosario',  // se imprime en el comprobante
			// stateId: 12,                          // provincia (12 = Santa Fe). Mandala siempre que la sepas:
			//                                       // es el único dato agrupable para reportes de Ingresos Brutos
		},

		items: [
			{
				description: 'Teclado mecánico inalámbrico',
				quantity: 2,
				price: 15000, // unitario, sin descuento

				// La alícuota de IVA de la línea es obligatoria en A y B, y NO va en los comprobantes C,
				// que no discriminan IVA:
				// taxType: 5,                  // 5 = 21% · 4 = 10,5% · 3 = 0% · 2 exento · 1 no gravado
				// code: 'PROD-001',            // tu código interno
				// unitType: 7,                 // unidad de medida; 7 = unidades (por defecto)
				// discount: 10,                // descuento de la línea
				// discountType: 'percentage',  // 'percentage' (defecto) o 'amount'
			},
		],

		// ── Opcionales ───────────────────────────────────────────────────────────
		//
		// Referencia completa: https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante

		// externalReference: 'PEDIDO-2026-00042', // tu identificador. Es el único campo por el que
		//                                         // después podés BUSCAR el comprobante en el listado
		// legend: '¡Gracias por su compra!',      // leyenda impresa. Máx. 200
		// comments: 'Retira por sucursal Centro', // nota interna: se guarda, NO se imprime. Máx. 512
		// additionalData: { vendedor: 'ML-22', canal: 'web' }, // objeto libre que viaja con el comprobante

		// Con concept 2 o 3, las tres fechas del período son OBLIGATORIAS:
		// serviceFrom: '20260801',
		// serviceTo: '20260831',
		// serviceExpiration: '20260910',

		// Sólo en notas de crédito y débito, apuntando al comprobante que modifican:
		// associatedVouchers: [
		// 	{ voucherType: 11, posNumber: 1, voucherNumber: 45 /*, cuit: '30123456789' */ },
		// ],

		// Por defecto pesos a 1. Con moneda extranjera, la cotización del día la ponés vos:
		// currency: { id: 'DOL', quotation: 1150 },
		// cancelsInSameForeignCurrency: 'S',   // 'S' o 'N', sólo con moneda extranjera

		// Impuestos que NO son IVA (IIBB, tasas, percepciones). Acá el importe lo calculás vos:
		// tributes: [
		// 	{ type: 7, description: 'Percepción IIBB Santa Fe', baseAmount: 30000, aliquot: 3.5, subtotal: 1050 },
		// ],

		// Envío del PDF por correo, en segundo plano. Poné una dirección tuya: se manda de verdad.
		// emails: [
		// 	{ email: 'vos@example.com', name: 'Pedro López', comments: '¡Gracias!', pdfVersions: [1] },
		// ],

		// Decimales de cantidad y precio unitario: 2 (defecto), 4 o 6. Los importes se redondean
		// siempre a 2, que es lo que exige ARCA:
		// settings: { quantityPrecision: 4, pricePrecision: 4 },

		// receiverTaxConditionId: 5,  // pisa customer.taxType. Normalmente no hace falta
	}

	const respuesta = await fetch(`${BASE_URL}/${ID_EMPRESA}/invoices`, {
		method: 'POST',
		headers: {
			Authorization: `Bearer ${API_KEY}`,
			'Content-Type': 'application/json',
		},
		body: JSON.stringify(comprobante),
	})

	// La API contesta siempre el mismo sobre: { ok: true, result: {...} } o { ok: false, error, code }.
	const cuerpo = await respuesta.json()

	if (!cuerpo.ok) {
		console.error(`ERROR ${respuesta.status} · ${cuerpo.code ?? 'sin código'}`)
		console.error(cuerpo.error)
		// `details` es opcional y SU FORMA LA DETERMINA `code`: una lista de rechazos en un
		// ARCA_ERROR, un objeto con los campos que fallaron en un VALIDATION_ERROR. Por eso se mira
		// el código primero. Deducirla de la forma —"¿es una lista?"— confunde un pedido mal armado
		// con un rechazo fiscal, que se corrigen de maneras muy distintas.
		if (cuerpo.code === 'ARCA_ERROR' && cuerpo.details) {
			for (const { code, msg } of cuerpo.details) console.error(`  (${code}) ${msg}`)
		} else if (cuerpo.code === 'VALIDATION_ERROR' && cuerpo.details) {
			// `field` es la ruta del dato dentro del JSON que mandaste: `items.0.price`.
			for (const { field, message } of cuerpo.details.errors) {
				console.error(`  ${field ?? '(cuerpo)'}: ${message}`)
			}
		} else if (cuerpo.details) {
			console.error(JSON.stringify(cuerpo.details, null, 2))
		}
		process.exitCode = 1
	} else {
		const factura = cuerpo.result
		console.log('Comprobante emitido')
		console.log(`   CAE:     ${factura.cae}`)
		console.log(`   Número:  ${factura.voucherNumber}`)
		console.log(`   Total:   $ ${factura.totalAmount}`)
		console.log(`   ID:      ${factura.id}`)
		// `quota` viaja al lado de `result`, no adentro: es el cupo de la empresa, no del comprobante.
		if (cuerpo.quota) console.log(`   Cupo:    ${cuerpo.quota.remaining} restantes del período`)
		console.log('\nEso es la API entera: un POST con este JSON. El resto de los ejemplos usa el')
		console.log('cliente de src/, que agrega reintentos, errores tipados y las tablas de códigos.')
	}
}
