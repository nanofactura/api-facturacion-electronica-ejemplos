/**
 * 07 · Consultar el padrón de ARCA
 *
 * Si tenés el CUIT del cliente, ésta es la forma correcta de armar el objeto `customer`: te asegura
 * que la condición frente al IVA sea la real —que es lo que determina qué comprobante corresponde
 * emitir— y te trae la provincia ya codificada, sin que nadie la tipee mal.
 *
 * Guardá el resultado en tu base: no hace falta consultarlo en cada venta al mismo cliente.
 *
 * ⚠️ El padrón se autoriza APARTE de la facturación electrónica: son servicios distintos de ARCA y
 * cada uno se delega por separado. Si emitís sin problemas pero esto falla con "Computador no
 * autorizado", es eso lo que falta.
 *
 *   node --env-file=../.env examples/07-consultar-padron.js [documento]
 */
import { NanoFactura } from '../src/client.js'
import { TIPO_COMPROBANTE, CONDICION_IVA } from '../src/constants/index.js'

const nanofactura = new NanoFactura()

const documento = process.argv[2] ?? '30703088534'

const contribuyente = await nanofactura.consultarPadron(documento).catch((error) => {
	// Los dos motivos por los que esto falla en la práctica. Ninguno cede reintentando.
	if (error.isCertificadoSinDelegar) {
		console.error('⚠️ Al certificado le falta la delegación a los servicios de padrón de ARCA.')
		console.error('  Se autorizan aparte de la facturación electrónica, uno por uno:')
		console.error('  https://asistentefacturaelectronica.com/ayuda/api/certificado-arca')
		return null
	}
	if (error.status === 404) {
		console.error(`⚠️ ARCA no tiene ningún contribuyente con el documento ${documento}.`)
		return null
	}
	throw error
})

// Se marca el código de salida y se deja que Node termine solo, en vez de `process.exit()`: cortar
// el proceso de golpe con las conexiones del fetch todavía abiertas lo aborta en Windows.
if (!contribuyente) {
	process.exitCode = 1
} else {
	// Qué padrón contestó. A las personas jurídicas les corresponde la Constancia de Inscripción y al
	// resto el Padrón A13; se puede pedir el otro con `consultarPadron(documento, { service: 'a13' })`.
	console.log(`Padrón de ${documento} · consultado en ${contribuyente.source}`)
	console.log(`   Nombre:    ${contribuyente.name}`)
	console.log(`   Domicilio: ${contribuyente.address || '(sin domicilio cargado)'}`)
	console.log(`   Provincia: ${contribuyente.stateId ?? 'no informada'}`)
	console.log(`   Condición: ${contribuyente.taxConditionName} (${contribuyente.taxCondition})`)
	console.log(`   Documento: tipo ${contribuyente.docType} · ${contribuyente.docNumber}`)

	// Los campos del padrón se corresponden uno a uno con los de `customer`. `taxCondition` se usa
	// tal cual como `taxType`, sin traducir.
	const customer = {
		docType: contribuyente.docType,
		// El padrón devuelve el documento como número y la emisión lo espera como string: convertilo.
		docNumber: String(contribuyente.docNumber),
		name: contribuyente.name,
		address: contribuyente.address,
		stateId: contribuyente.stateId, // puede venir null: es un valor válido
		taxType: contribuyente.taxCondition,
	}

	console.log('\nObjeto `customer` listo para emitir:')
	console.log(JSON.stringify(customer, null, 2))

	// Siendo responsable inscripto, la condición del receptor decide la letra del comprobante.
	//
	// Ojo con el monotributista: le corresponde Factura A, no B. Durante años fue al revés, y es el
	// error más frecuente al integrar.
	const RECIBEN_FACTURA_A = [CONDICION_IVA.RESPONSABLE_INSCRIPTO, CONDICION_IVA.MONOTRIBUTO]

	const tipoSugerido = RECIBEN_FACTURA_A.includes(customer.taxType)
		? TIPO_COMPROBANTE.FACTURA_A
		: TIPO_COMPROBANTE.FACTURA_B

	console.log(`\nSi sos responsable inscripto, a este cliente le corresponde voucherType ${tipoSugerido}.`)
	console.log('Si sos monotributista o exento, siempre Factura C (11).')
}
