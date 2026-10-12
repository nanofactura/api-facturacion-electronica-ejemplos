/**
 * 09 · Listar comprobantes
 *
 * El listado devuelve sólo los comprobantes del entorno de tu API key: una clave TEST_ nunca ve los
 * de producción, ni al revés.
 *
 * Los filtros son estilo Django: `campo=valor` o `campo__operador=valor`, y todos se combinan con
 * AND. ⚠️ Los campos desconocidos se ignoran EN SILENCIO: un filtro mal escrito no da error, sólo
 * devuelve más resultados de los que esperabas.
 *
 *   node --env-file=../.env examples/09-listar-comprobantes.js
 */
import { NanoFactura } from '../src/client.js'
import { TIPO_COMPROBANTE } from '../src/constants/index.js'

const nanofactura = new NanoFactura()

// ── Los últimos 10 ───────────────────────────────────────────────────────────

const { comprobantes, total } = await nanofactura.listarComprobantes({ limit: 10, ordering: '-created' })

console.log(`Últimos ${comprobantes.length} de ${total} comprobantes:\n`)
for (const c of comprobantes) {
	const numero = `${String(c.posNumber).padStart(4, '0')}-${String(c.voucherNumber).padStart(8, '0')}`
	console.log(
		`  ${numero}  ${c.voucherDate}  $ ${String(c.totalAmount).padStart(12)}  ${c.docNumber.padEnd(11)}  ${c.source}`
	)
}

// ── Uno por id ───────────────────────────────────────────────────────────────
//
// Con el `id` que devolvió la emisión —el que guardaste junto a tu venta— lo pedís directo, sin
// filtrar el listado. Trae el mismo objeto que una fila del listado; si no existe, o es del otro
// entorno, la API responde 404 NOT_FOUND.

if (comprobantes.length) {
	const comprobante = await nanofactura.obtenerComprobante(comprobantes[0].id)
	const pdf = comprobante.hasPdf ? 'listo' : 'generándose'
	console.log(`\nEl último, por id: ${comprobante.id} · CAE ${comprobante.cae} · PDF ${pdf}`)
}

// ── Facturación de un mes ────────────────────────────────────────────────────
//
// `date` es la fecha FISCAL (la que declaraste al emitir). `created` es cuándo se emitió realmente.
// Para reportes fiscales usá `date`; para conciliar con tu sistema, `created`.

const mes = await nanofactura.listarComprobantes({
	date__gte: '20260801',
	date__lte: '20260831',
	ordering: 'date',
	limit: 200, // el máximo por página
})

// En el listado los importes viajan como string, para no perder precisión al serializarlos.
// Convertilos antes de operar: `suma + c.totalAmount` concatena en vez de sumar.
const facturado = mes.comprobantes.reduce((suma, c) => suma + Number(c.totalAmount), 0)
console.log(`\nAgosto 2026: ${mes.total} comprobantes · $ ${facturado.toLocaleString('es-AR')}`)

// ── Buscar por tu propio identificador ───────────────────────────────────────
//
// Para esto sirve `externalReference`: encontrar el comprobante desde tu sistema sin llevar una
// tabla de equivalencias.

const { comprobantes: delPedido } = await nanofactura.listarComprobantes({
	externalReference: 'PEDIDO-2026-00042',
})
console.log(`\nComprobantes del pedido PEDIDO-2026-00042: ${delPedido.length}`)

// ── Sólo notas de crédito ────────────────────────────────────────────────────

const { total: notasCredito } = await nanofactura.listarComprobantes({
	type__in: [TIPO_COMPROBANTE.NOTA_CREDITO_A, TIPO_COMPROBANTE.NOTA_CREDITO_B, TIPO_COMPROBANTE.NOTA_CREDITO_C].join(','),
	limit: 1,
})
console.log(`Notas de crédito emitidas: ${notasCredito}`)

// ── Paginar todo ─────────────────────────────────────────────────────────────
//
// `total` es cuántos matchean el filtro, no cuántos vinieron en la página: es lo que permite
// paginar sin depender de que la página vuelva llena.

async function* todosLosComprobantes(filtros = {}) {
	const limit = 200
	let offset = 0
	let total = Infinity

	while (offset < total) {
		const pagina = await nanofactura.listarComprobantes({ ...filtros, limit, offset, ordering: 'id' })
		total = pagina.total
		yield* pagina.comprobantes
		offset += limit
		if (pagina.comprobantes.length === 0) break
	}
}

let contados = 0
for await (const _comprobante of todosLosComprobantes({ date__gte: '20260101' })) {
	contados++
}
console.log(`\nRecorridos ${contados} comprobantes de 2026 paginando de a 200.`)
