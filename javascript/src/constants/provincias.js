/**
 * Provincia del cliente. Campo `customer.stateId`.
 *
 * No se le envía a ARCA: se guarda con el comprobante. Es el único dato ESTRUCTURADO de ubicación
 * —`address` es texto libre y no se puede agrupar—, y es lo que permite después repartir la
 * facturación por jurisdicción para Ingresos Brutos y Convenio Multilateral.
 *
 * Mandala siempre que la conozcas: no se puede reconstruir hacia atrás. Si genuinamente no la
 * sabés, mandá `null` ("no informada"). No pongas 0 por defecto: 0 es CABA, y un dato inventado
 * ensucia los reportes sin que se note.
 *
 * Son 24 jurisdicciones: las 23 provincias más CABA, que no es una provincia sino un distrito
 * autónomo pero cuenta como jurisdicción propia para Ingresos Brutos. Los códigos son de ARCA y no
 * son correlativos: el 15 no se usa.
 *
 * https://asistentefacturaelectronica.com/ayuda/api/tablas/provincias
 */
export const PROVINCIAS = Object.freeze({
	CABA: 0,
	BUENOS_AIRES: 1,
	CATAMARCA: 2,
	CORDOBA: 3,
	CORRIENTES: 4,
	ENTRE_RIOS: 5,
	JUJUY: 6,
	MENDOZA: 7,
	LA_RIOJA: 8,
	SALTA: 9,
	SAN_JUAN: 10,
	SAN_LUIS: 11,
	SANTA_FE: 12,
	SANTIAGO_DEL_ESTERO: 13,
	TUCUMAN: 14,
	CHACO: 16,
	CHUBUT: 17,
	FORMOSA: 18,
	MISIONES: 19,
	NEUQUEN: 20,
	LA_PAMPA: 21,
	RIO_NEGRO: 22,
	SANTA_CRUZ: 23,
	TIERRA_DEL_FUEGO: 24,

	NO_INFORMADA: null,
})
