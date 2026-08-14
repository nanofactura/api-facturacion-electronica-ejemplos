/**
 * 06 · Nota de crédito
 *
 * Un comprobante autorizado no se puede borrar ni modificar: para anularlo o corregirlo se emite
 * una nota de crédito que lo compensa.
 *
 * Dos reglas que no se negocian:
 *   1. Misma letra que el comprobante original (una NC "C" anula una Factura C).
 *   2. Siempre con `associatedVouchers` apuntando al comprobante que modifica.
 *
 *   node --env-file=../.env examples/06-nota-de-credito.js
 */
import { NanoFactura } from '../src/client.js'
import {
	TIPO_COMPROBANTE,
	LETRA_COMPROBANTE,
	CONCEPTO,
	TIPO_DOCUMENTO,
	CONDICION_IVA,
	PROVINCIAS,
} from '../src/constants/index.js'

const nanofactura = new NanoFactura()
const posNumber = Number(process.env.NANOFACTURA_POS_NUMBER ?? 1)

// ── 1. Emitimos una factura para después anularla ────────────────────────────

const factura = await nanofactura.emitirComprobante({
	posNumber,
	voucherType: TIPO_COMPROBANTE.FACTURA_C,
	concept: CONCEPTO.PRODUCTOS,
	voucherDate: hoy(),
	customer: {
		docType: TIPO_DOCUMENTO.DNI,
		docNumber: '35888999',
		name: 'Pedro López',
		stateId: PROVINCIAS.SANTA_FE,
		taxType: CONDICION_IVA.CONSUMIDOR_FINAL,
	},
	items: [{ description: 'Auriculares bluetooth', quantity: 1, price: 45000 }],
	externalReference: 'PEDIDO-2026-00099',
})

console.log(`Factura emitida  · Nº ${factura.voucherNumber} · $ ${factura.totalAmount}`)

// ── 2. El cliente devuelve el producto: emitimos la nota de crédito ──────────

const tipoNotaCredito = TIPO_COMPROBANTE.NOTA_CREDITO_C

// La letra tiene que coincidir. Vale la pena verificarlo en código: es un error fácil de cometer
// al parametrizar el tipo de comprobante, y ARCA lo rechaza recién al emitir.
if (LETRA_COMPROBANTE[tipoNotaCredito] !== LETRA_COMPROBANTE[TIPO_COMPROBANTE.FACTURA_C]) {
	throw new Error('La nota de crédito tiene que ser de la misma letra que el comprobante que anula.')
}

const notaCredito = await nanofactura.emitirComprobante({
	posNumber,
	voucherType: tipoNotaCredito,
	concept: CONCEPTO.PRODUCTOS,
	voucherDate: hoy(),

	// El vínculo con el comprobante original. Sin esto, ARCA rechaza.
	associatedVouchers: [
		{
			voucherType: TIPO_COMPROBANTE.FACTURA_C,
			posNumber,
			voucherNumber: factura.voucherNumber,
		},
	],

	// Mismo receptor que la factura original.
	customer: {
		docType: TIPO_DOCUMENTO.DNI,
		docNumber: '35888999',
		name: 'Pedro López',
		stateId: PROVINCIAS.SANTA_FE,
		taxType: CONDICION_IVA.CONSUMIDOR_FINAL,
	},

	// Por el total, porque anulamos toda la factura. Para una devolución parcial irían sólo las
	// líneas devueltas, con sus cantidades.
	items: [
		{
			description: `Anulación de Factura C ${formatear(posNumber, factura.voucherNumber)} — devolución`,
			quantity: 1,
			price: 45000,
		},
	],

	// La nota de crédito también le llega al cliente: es el comprobante que respalda la devolución.
	// Descomentalo poniendo una dirección tuya — el correo se manda de verdad.
	// emails: [
	// 	{
	// 		email: 'vos@example.com',
	// 		name: 'Pedro López',
	// 		comments: 'Adjuntamos la nota de crédito por la devolución.',
	// 		pdfVersions: [1], // 1 original · 2 duplicado · 3 triplicado
	// 	},
	// ],

	externalReference: 'PEDIDO-2026-00099-NC',
})

console.log(`Nota de crédito  · Nº ${notaCredito.voucherNumber} · $ ${notaCredito.totalAmount}`)
console.log(`\n✅ Factura ${formatear(posNumber, factura.voucherNumber)} anulada.`)
console.log('   Los dos comprobantes quedan en tu historial: así lo entiende ARCA.')

function hoy() {
	return new Date().toISOString().slice(0, 10).replaceAll('-', '')
}

function formatear(pos, numero) {
	return `${String(pos).padStart(4, '0')}-${String(numero).padStart(8, '0')}`
}
