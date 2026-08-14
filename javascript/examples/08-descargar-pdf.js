/**
 * 08 · Descargar el PDF
 *
 * El PDF se genera de forma ASINCRÓNICA: la emisión responde con el CAE de inmediato y el archivo
 * queda listo unos segundos después.
 *
 * ⚠️ No lo guardes en tu sistema luego de generar. Pedilo en el momento en que alguien lo abre, lo imprime o lo
 * adjunta, y descartalo. Guardar el PDF te obliga a resolver almacenamiento, backups, permisos y
 * retención para un archivo que la API te devuelve siempre, tantas veces como haga falta.
 *
 * Lo que sí guardás de la emisión son los identificadores: id, cae, voucherNumber, posNumber y
 * voucherType. Con el `id` traés el PDF cuando lo necesites.
 *
 *   node --env-file=../.env examples/08-descargar-pdf.js <invoiceId>
 */
import { writeFile } from 'node:fs/promises'
import { NanoFactura } from '../src/client.js'

const nanofactura = new NanoFactura()

const invoiceId = process.argv[2]
if (!invoiceId) {
	console.error('Uso: node examples/08-descargar-pdf.js <invoiceId>')
	console.error('El invoiceId es el campo `id` que devuelve la emisión.')
	process.exit(1)
}

// ── Caso normal: el PDF ya está listo ────────────────────────────────────────
//
// Cuando el usuario abre el comprobante, pasaron minutos u horas desde la emisión. Alcanza con
// pedirlo: `descargarPdf()` sin más vueltas.

const pdf = await nanofactura.descargarPdf(invoiceId, [1])

await writeFile(`comprobante-${invoiceId}.pdf`, pdf)
console.log(`✅ PDF descargado · comprobante-${invoiceId}.pdf (${(pdf.length / 1024).toFixed(1)} KB)`)

// ── Caso "recién emitido": esperar a que se genere ───────────────────────────
//
// Sólo hace falta si pedís el PDF inmediatamente después de emitir. Si todavía no está, la API
// responde 409 PDF_NOT_READY y `esperarPdf()` reintenta hasta que aparezca.

// const pdfConEspera = await nanofactura.esperarPdf(invoiceId, { versiones: [1, 2], intentos: 10 })

// ── Original, duplicado y triplicado ─────────────────────────────────────────
//
// `versions` estampa las copias pedidas: 1 original, 2 duplicado, 3 triplicado.

const pdfTriplicado = await nanofactura.descargarPdf(invoiceId, [1, 2, 3])
await writeFile(`comprobante-${invoiceId}-triplicado.pdf`, pdfTriplicado)
console.log(`✅ Con las tres copias · comprobante-${invoiceId}-triplicado.pdf`)

// ── Si lo que querés es que le llegue al cliente ─────────────────────────────
//
// No bajes el PDF para reenviarlo vos: agregá el destinatario y la API lo despacha en segundo
// plano, con la plantilla y las copias que corresponden. Máximo 3 por comprobante.

// await nanofactura.agregarDestinatario(invoiceId, {
//   email: 'cliente@example.com',
//   name: 'Pedro López',
//   comments: 'Adjuntamos tu comprobante.',
//   pdfVersions: [1],
// })

console.log('\nPara que el comprobante le llegue al cliente, usá agregarDestinatario()')
console.log('o mandá `emails` al emitir: el envío corre en segundo plano.')
