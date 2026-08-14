/**
 * 05 · Servicios con período
 *
 * Cuando el concepto es SERVICIOS o PRODUCTOS_Y_SERVICIOS, ARCA exige LAS TRES fechas del período:
 * inicio, fin y vencimiento del pago. Si falta alguna, la API responde 400 antes de llamar a ARCA
 * y te dice cuál.
 *
 * Es el caso típico de un abono mensual o de una consultoría facturada por período.
 *
 *   node --env-file=../.env examples/05-servicios-con-periodo.js
 */
import { NanoFactura } from '../src/client.js'
import {
	TIPO_COMPROBANTE,
	CONCEPTO,
	TIPO_DOCUMENTO,
	CONDICION_IVA,
	PROVINCIAS,
} from '../src/constants/index.js'

const nanofactura = new NanoFactura()

// Todo el mes en curso, con vencimiento a diez días del cierre.
const hoy = new Date()
const desde = new Date(hoy.getFullYear(), hoy.getMonth(), 1)
const hasta = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0)
const vencimiento = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 10)

const comprobante = await nanofactura.emitirComprobante({
	posNumber: Number(process.env.NANOFACTURA_POS_NUMBER ?? 1),
	voucherType: TIPO_COMPROBANTE.FACTURA_C,
	concept: CONCEPTO.SERVICIOS,

	voucherDate: aaaammdd(hoy),
	serviceFrom: aaaammdd(desde), // FchServDesde
	serviceTo: aaaammdd(hasta), // FchServHasta
	serviceExpiration: aaaammdd(vencimiento), // FchVtoPago

	customer: {
		docType: TIPO_DOCUMENTO.CUIT,
		docNumber: '30123456789',
		name: 'ESTUDIO CONTABLE NORTE S.R.L.',
		address: 'Av. Colón 780, Córdoba',
		stateId: PROVINCIAS.CORDOBA,
		taxType: CONDICION_IVA.RESPONSABLE_INSCRIPTO,
	},

	items: [
		{
			code: 'ABONO-SOP',
			description: `Abono de soporte técnico — ${desde.toLocaleDateString('es-AR', { month: 'long', year: 'numeric' })}`,
			quantity: 1,
			price: 250000,
		},
	],

	// El envío sale en segundo plano, sin bloquear la respuesta. No hace falta que bajes el PDF
	// para reenviarlo vos. Descomentalo poniendo una dirección tuya — el correo se manda de verdad,
	// y la API rechaza los dominios de ejemplo como el de acá abajo.
	// emails: [
	// 	{
	// 		email: 'vos@example.com',
	// 		name: 'Administración',
	// 		comments: 'Adjuntamos la factura del período. Ante cualquier duda, quedamos a disposición.',
	// 		pdfVersions: [1],
	// 	},
	// ],

	externalReference: `ABONO-${aaaammdd(desde).slice(0, 6)}`,
})

console.log('✅ Factura de servicios emitida')
console.log(`   CAE:      ${comprobante.cae}`)
console.log(`   Período:  ${aaaammdd(desde)} → ${aaaammdd(hasta)}`)
console.log(`   Vence:    ${aaaammdd(vencimiento)}`)
console.log(`   Total:    $ ${comprobante.totalAmount}`)
console.log('\nDescomentá `emails` con una dirección tuya y el envío sale con la emisión, en segundo plano.')

function aaaammdd(fecha) {
	const mes = String(fecha.getMonth() + 1).padStart(2, '0')
	const dia = String(fecha.getDate()).padStart(2, '0')
	return `${fecha.getFullYear()}${mes}${dia}`
}
