/**
 * 10 · Emisión en lote
 *
 * ARCA exige numeración correlativa por punto de venta y tipo de comprobante, así que la API emite
 * de a un comprobante por vez para cada combinación.
 *
 * ⚠️ SECUENCIÁ, NO PARALELICES. Lanzar en paralelo comprobantes del mismo punto de venta y tipo no
 * acelera nada —igual se serializan— y sólo produce errores 429. Si tenés que emitir mucho y
 * rápido, la forma de paralelizar es POR PUNTO DE VENTA: cada uno lleva su propia numeración.
 *
 * Lo otro que muestra este ejemplo es la persistencia de la clave de idempotencia: se genera y se
 * guarda ANTES de emitir. Si el proceso se cae a la mitad, al reanudarlo se reusa la misma clave y
 * los comprobantes ya emitidos vuelven con `idempotencyHit: true` en vez de duplicarse.
 *
 *   node --env-file=../.env examples/10-emision-en-lote.js
 */
import { randomUUID } from 'node:crypto'
import { NanoFactura } from '../src/client.js'
import {
	TIPO_COMPROBANTE,
	CONCEPTO,
	TIPO_DOCUMENTO,
	CONDICION_IVA,
	PROVINCIAS,
} from '../src/constants/index.js'

const nanofactura = new NanoFactura()
const posNumber = Number(process.env.NANOFACTURA_POS_NUMBER ?? 1)

// En tu sistema esto sale de la base de datos. `idempotency` es una columna de la venta, no algo
// que se calcule al vuelo: se escribe cuando la venta se marca para facturar y no cambia nunca más.
const ventas = [
	{ id: 'V-1001', cliente: 'Pedro López', doc: '35888999', provincia: PROVINCIAS.SANTA_FE, total: 30000, idempotency: null },
	{ id: 'V-1002', cliente: 'Ana Gómez', doc: '28444555', provincia: PROVINCIAS.CORDOBA, total: 47500, idempotency: null },
	{ id: 'V-1003', cliente: 'Luis Ruiz', doc: '31222888', provincia: PROVINCIAS.BUENOS_AIRES, total: 12300, idempotency: null },
]

const emitidos = []
const fallidos = []

for (const venta of ventas) {
	// 1. La clave se genera y se GUARDA antes de emitir. En tu sistema, este paso es un UPDATE.
	venta.idempotency ??= randomUUID()
	// await db.ventas.update(venta.id, { idempotency: venta.idempotency })

	try {
		const comprobante = await nanofactura.emitirComprobante({
			idempotency: venta.idempotency,
			posNumber,
			voucherType: TIPO_COMPROBANTE.FACTURA_C,
			concept: CONCEPTO.PRODUCTOS,
			voucherDate: hoy(),
			customer: {
				docType: TIPO_DOCUMENTO.DNI,
				docNumber: venta.doc,
				name: venta.cliente,
				stateId: venta.provincia,
				taxType: CONDICION_IVA.CONSUMIDOR_FINAL,
			},
			items: [{ description: `Venta ${venta.id}`, quantity: 1, price: venta.total }],
			externalReference: venta.id,
		})

		// 2. Guardá el resultado INMEDIATAMENTE, no al final del lote. Si el proceso muere en la
		//    venta 40 de 100, las 39 anteriores tienen que estar registradas.
		// await db.ventas.update(venta.id, { cae: comprobante.cae, invoiceId: comprobante.id, ... })

		emitidos.push({ venta: venta.id, ...comprobante })

		const reusado = comprobante.idempotencyHit ? ' (ya existía)' : ''
		console.log(`✅ ${venta.id} → CAE ${comprobante.cae}${reusado}`)
	} catch (error) {
		fallidos.push({ venta: venta.id, code: error.code, error: error.message })
		console.log(`❌ ${venta.id} → ${error.code ?? 'ERROR'}: ${error.message}`)

		// 3. Cortá al primer error PERMANENTE: si el primero está mal armado, probablemente lo
		//    estén todos, y seguir sólo suma rechazos. Los transitorios ya los reintentó el cliente.
		if (!error.isRetryable && error.code !== 'ARCA_ERROR') {
			console.log('\n⛔ Error de configuración: se corta el lote.')
			break
		}
	}
}

console.log(`\n─────────────────────────────────`)
console.log(`Emitidos: ${emitidos.length} · Fallidos: ${fallidos.length}`)

// La cuota del período viene en cada emisión: sirve para frenar antes de chocar.
const ultimo = emitidos.at(-1)
if (ultimo?.quota) {
	console.log(`Cupo restante del período: ${ultimo.quota.remaining} (hasta ${ultimo.quota.periodEnd})`)
}

// ── Paralelizar por punto de venta ───────────────────────────────────────────
//
// Distintos puntos de venta SÍ pueden emitir a la vez, porque cada uno lleva su propia numeración.
// El patrón correcto es agrupar y correr un flujo secuencial por grupo:
//
//   const porPuntoDeVenta = Object.groupBy(ventas, (v) => v.posNumber)
//   await Promise.all(
//     Object.values(porPuntoDeVenta).map(async (grupo) => {
//       for (const venta of grupo) await emitir(venta)   // secuencial dentro del grupo
//     })
//   )

function hoy() {
	return new Date().toISOString().slice(0, 10).replaceAll('-', '')
}
