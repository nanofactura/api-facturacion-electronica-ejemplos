/**
 * 07 · Consultar el padrón de ARCA
 *
 * Si tenés el CUIT del cliente, ésta es la forma correcta de armar el objeto `customer`: te asegura
 * que la condición frente al IVA sea la real —que es lo que determina qué comprobante corresponde
 * emitir— y te trae la provincia ya codificada, sin que nadie la tipee mal.
 *
 * Guardá el resultado en tu base: no hace falta consultarlo en cada venta al mismo cliente.
 *
 * Si estás integrando en homologación, consultá el padrón con una API key de producción: la base
 * de homologación tiene datos de prueba y responde distinto. Esta consulta sólo lee, no emite nada.
 *
 *   node --env-file=../.env examples/07-consultar-padron.js [documento]
 */
import { NanoFactura } from '../src/client.js'
import { TIPO_COMPROBANTE, CONDICION_IVA } from '../src/constants/index.js'

const nanofactura = new NanoFactura()

const documento = process.argv[2] ?? '30703088534'

const contribuyente = await nanofactura.consultarPadron(documento).catch((error) => {
	if (error.status === 404) {
		console.error(`⚠️ ARCA no tiene ningún contribuyente con el documento ${documento}.`)
		return null
	}
	// ARCA puede negarse a informar una clave concreta por el estado de ESE contribuyente —cancelada,
	// inactiva, con actividades fuera del nomenclador vigente—. No es un problema de configuración ni
	// cede reintentando: es lo que ARCA sabe de ese CUIT.
	if (error.code === 'ARCA_ERROR') {
		console.error(`⚠️ ARCA no informa datos de ${documento}: ${error.message}`)
		return null
	}
	throw error
})

// Se marca el código de salida y se deja que Node termine solo, en vez de `process.exit()`: cortar
// el proceso de golpe con las conexiones del fetch todavía abiertas lo aborta en Windows.
if (!contribuyente) {
	process.exitCode = 1
} else {
	// `cache.hit` dice si la respuesta salió de un dato ya guardado en vez de consultarse en el momento,
	// y `cache.stale` si ese dato estaba vencido y se usó igual porque ARCA no respondió.
	const { hit, stale } = contribuyente.cache ?? {}
	console.log(`Padrón de ${documento}${hit ? (stale ? ' · dato guardado (vencido)' : ' · dato guardado') : ''}`)
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
