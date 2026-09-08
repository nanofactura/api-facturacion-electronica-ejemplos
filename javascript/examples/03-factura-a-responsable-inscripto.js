/**
 * 03 · Factura A — responsable inscripto
 *
 * Un responsable inscripto le factura a otro responsable inscripto. Acá sí se discrimina IVA: cada
 * ítem lleva su alícuota real y la API arma el desglose que ARCA espera.
 *
 * Factura A exige que el receptor esté identificado con CUIT y sea responsable inscripto. Si tu
 * cliente es consumidor final, el comprobante que corresponde es B (ver ejemplo 04).
 *
 *   node --env-file=../.env examples/03-factura-a-responsable-inscripto.js
 */
import { NanoFactura } from '../src/client.js'
import {
	TIPO_COMPROBANTE,
	CONCEPTO,
	TIPO_DOCUMENTO,
	CONDICION_IVA,
	ALICUOTA_IVA,
	UNIDAD,
	PROVINCIAS,
} from '../src/constants/index.js'

const nanofactura = new NanoFactura()

const comprobante = await nanofactura.emitirComprobante({
	posNumber: Number(process.env.NANOFACTURA_POS_NUMBER ?? 1),
	voucherType: TIPO_COMPROBANTE.FACTURA_A,
	concept: CONCEPTO.PRODUCTOS,
	voucherDate: hoy(),

	// Los precios de los ítems incluyen el IVA. Es el valor por defecto de la API, y se declara acá
	// para que se lea en el ejemplo: de él depende cuánto sale la factura. Si tu lista de precios es
	// neta, poné false y la API le suma el IVA por encima.
	priceIncludesVat: true,

	customer: {
		// En Factura A el documento tiene que ser CUIT y la condición Responsable Inscripto.
		docType: TIPO_DOCUMENTO.CUIT,
		docNumber: '30123456789',
		name: 'DISTRIBUIDORA DEL LITORAL S.A.',
		address: 'Av. Pellegrini 2500, Rosario',
		stateId: PROVINCIAS.SANTA_FE,
		taxType: CONDICION_IVA.RESPONSABLE_INSCRIPTO,
	},

	items: [
		{
			code: 'INS-100',
			description: 'Insumos de oficina — resma A4',
			quantity: 50,
			unitType: UNIDAD.UNIDADES,
			price: 6050, // final: $5.000 + 21%
			taxType: ALICUOTA_IVA.VEINTIUNO, // 21%
		},
		{
			code: 'INS-220',
			description: 'Servicio de mantenimiento mensual',
			quantity: 1,
			unitType: UNIDAD.UNIDADES,
			price: 110500, // final: $100.000 + 10,5%
			taxType: ALICUOTA_IVA.DIEZ_CINCO, // 10,5%

			// Lo que le falta a un ítem: un descuento de línea.
			// discount: 10,               // 10% con discountType 'percentage', o $10 con 'amount'
			// discountType: 'percentage', // 'percentage' (defecto) o 'amount'
		},
	],

	// Envío por email al emitir: sale en segundo plano, sin bloquear la respuesta. Descomentalo
	// poniendo una dirección tuya — el correo se manda de verdad.
	// emails: [
	// 	{
	// 		email: 'vos@example.com',
	// 		name: 'Administración',
	// 		comments: 'Adjuntamos la factura de la orden de compra OC-2026-1188.',
	// 		pdfVersions: [1], // 1 original · 2 duplicado · 3 triplicado
	// 	},
	// ],

	externalReference: 'OC-2026-1188',

	// ── Todo lo demás que acepta la API ──────────────────────────────────────
	//
	// Ninguno hace falta para emitir. Los que usan constantes que este ejemplo todavía no importa
	// (MONEDA, TRIBUTO) piden agregarlas al `import` de arriba.
	// Referencia: https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante
	//
	// idempotency: 'OC-2026-1188',            // tu clave única. Si no la mandás, el cliente genera un UUID
	// legend: 'Gracias por su compra.',       // leyenda impresa en el comprobante. Máx. 200
	// comments: 'Entrega en depósito 2',      // nota interna: se guarda, NO se imprime. Máx. 512
	// additionalData: { vendedor: 'ML-22', canal: 'web' }, // objeto libre que viaja con el comprobante
	// receiverTaxConditionId: CONDICION_IVA.RESPONSABLE_INSCRIPTO, // pisa customer.taxType; casi nunca hace falta
	//
	// Decimales de cantidad y precio unitario: 2 (defecto), 4 o 6. Los importes se redondean
	// siempre a 2, que es lo que exige ARCA:
	// settings: { quantityPrecision: 4, pricePrecision: 4 },
	//
	// Por defecto pesos a 1. Con moneda extranjera, la cotización del día la ponés vos:
	// currency: { id: MONEDA.DOLAR, quotation: 1150 },
	// cancelsInSameForeignCurrency: 'S',      // 'S' o 'N', sólo con moneda extranjera
	//
	// Con concept SERVICIOS o PRODUCTOS_Y_SERVICIOS, las tres fechas son OBLIGATORIAS (ejemplo 05):
	// serviceFrom: '20260801',
	// serviceTo: '20260831',
	// serviceExpiration: '20260910',
	//
	// Impuestos que NO son IVA: IIBB, tasas municipales, percepciones. Acá el importe lo calculás vos:
	// tributes: [
	// 	{ type: TRIBUTO.PERCEPCION_IIBB, description: 'Percepción IIBB Santa Fe', baseAmount: 350000, aliquot: 3.5, subtotal: 12250 },
	// ],
	//
	// Sólo en notas de crédito y débito, apuntando al comprobante que modifican (ejemplo 06):
	// associatedVouchers: [
	// 	{ voucherType: TIPO_COMPROBANTE.FACTURA_A, posNumber: 1, voucherNumber: 45 }, // `cuit` sólo si es de otro emisor
	// ],
})

console.log('✅ Factura A emitida')
console.log(`   CAE:    ${comprobante.cae}`)
console.log(`   Número: ${comprobante.voucherNumber}`)
console.log(`   Total:  $ ${comprobante.totalAmount}`)

console.log('\nEl total es la suma de los precios que mandaste: 50 × $6.050 + $110.500 = $413.000.')
console.log('La API lo reparte en neto ($350.000) e IVA ($63.000), arma el desglose por alícuota y')
console.log('se lo informa a ARCA. El comprobante A los imprime discriminados.')

function hoy() {
	return new Date().toISOString().slice(0, 10).replaceAll('-', '')
}
