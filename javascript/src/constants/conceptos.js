/**
 * Concepto del comprobante. Campo `concept` · en ARCA, `Concepto`.
 *
 * ⚠️ Con SERVICIOS o PRODUCTOS_Y_SERVICIOS son obligatorias LAS TRES fechas de servicio:
 * `serviceFrom`, `serviceTo` y `serviceExpiration`, todas en formato YYYYMMDD.
 *
 * https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante
 */
export const CONCEPTO = Object.freeze({
	PRODUCTOS: 1,
	SERVICIOS: 2,
	PRODUCTOS_Y_SERVICIOS: 3,
})
