/**
 * Tipo de documento del cliente. Campo `customer.docType` · en ARCA, `DocTipo`.
 *
 * `docNumber` va siempre sin puntos ni guiones: '20112223334', no '20-11222333-4'.
 *
 * En Factura A y M tiene que ser CUIT. SIN_IDENTIFICAR (con docNumber '0') sólo se admite en B y C
 * por debajo del importe que fija ARCA — por encima, el rechazo llega con el código 10015.
 *
 * https://asistentefacturaelectronica.com/ayuda/api/tablas/tipos-de-documento
 */
export const TIPO_DOCUMENTO = Object.freeze({
	CUIT: 80,
	CUIL: 86,
	CDI: 87,
	DNI: 96,
	PASAPORTE: 94,
	CI_EXTRANJERA: 91,
	SIN_IDENTIFICAR: 99, // consumidor final no identificado, con docNumber '0'
})
