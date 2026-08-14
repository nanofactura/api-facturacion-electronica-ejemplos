/**
 * 01 · Verificar la conexión
 *
 * La primera llamada que conviene hacer al integrar: valida la API key y confirma que la empresa
 * tenga certificado y puntos de venta para el entorno de esa clave.
 *
 * ⚠️ Es una verificación de PUESTA EN MARCHA y de diagnóstico, no un paso previo a facturar. Lo que
 * chequea cambia una vez cada dos años —cuando vence el certificado—, así que llamarla antes de
 * cada emisión duplica la latencia y no evita ningún error: si falta algo, la emisión lo dice.
 *
 *   node --env-file=../.env examples/01-verificar-conexion.js
 */
import { NanoFactura } from '../src/client.js'

const nanofactura = new NanoFactura()

const estado = await nanofactura.verificarConexion()

console.log('✅ Conexión OK')
console.log(`   Entorno:      ${estado.environment}`)
console.log(`   Certificados: ${estado.certificates ? 'sí' : 'NO'}`)
console.log(`   Puntos venta: ${estado.pos ? 'sí' : 'NO'}`)

// Se marca el código de salida y se deja que Node termine solo, en vez de `process.exit()`: cortar
// el proceso de golpe con las conexiones del fetch todavía abiertas lo aborta en Windows.
if (!estado.certificates || !estado.pos) {
	console.log('\n⚠️ Falta configuración para poder emitir.')
	console.log('  https://asistentefacturaelectronica.com/ayuda/api/certificado-arca')
	process.exitCode = 1
} else {
	if (nanofactura.isTestEnvironment) {
		console.log('\nℹ Estás en HOMOLOGACIÓN: los comprobantes no tienen validez fiscal.')
	}

	// Los puntos de venta con `webOnly: true` son para Comprobantes en Línea y no sirven para la API:
	// tienen `number: null` y no se pueden usar como `posNumber`.
	const puntosDeVenta = await nanofactura.listarPuntosDeVenta()
	const utilizables = puntosDeVenta.filter((pos) => !pos.webOnly)

	console.log(`\nPuntos de venta disponibles para facturar (${utilizables.length}):`)
	for (const pos of utilizables) {
		console.log(`   ${String(pos.number).padStart(4, '0')} · ${pos.name ?? '(sin nombre)'}`)
	}

	console.log('\nCon esto ya podés emitir: seguí con 02-factura-c-monotributo.js.')
	console.log('No llames a verificarConexion() antes de cada comprobante: es para configurar y diagnosticar.')
}
