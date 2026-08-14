/**
 * Tributos y percepciones que NO son IVA. Campo `tributes[].type` · en ARCA, `Tributos[].Id`.
 *
 * A diferencia de los ítems, acá el importe lo calculás vos: la API manda el `subtotal` tal como
 * lo enviás.
 *
 * La mayoría de las integraciones no usa este campo: sólo corresponde si sos agente de percepción
 * o retención, o si tenés que informar impuestos internos o tasas municipales.
 *
 * https://asistentefacturaelectronica.com/ayuda/api/tablas/tributos
 */
export const TRIBUTO = Object.freeze({
	IMPUESTOS_NACIONALES: 1,
	IMPUESTOS_PROVINCIALES: 2,
	IMPUESTOS_MUNICIPALES: 3,
	IMPUESTOS_INTERNOS: 4,
	IIBB: 5,
	PERCEPCION_IVA: 6,
	PERCEPCION_IIBB: 7,
	PERCEPCION_MUNICIPAL: 8,
	OTRAS_PERCEPCIONES: 9,
	IMPUESTO_INTERNO_ITEM: 10,
	PERCEPCION_IVA_NO_CATEGORIZADO: 13,
	RETENCION_IIGG_RG830: 14,
	RETENCION_IVA_RG3873: 15,
	PAGO_A_CUENTA_IVA_RG3873: 16,
	PERCEPCION_IVA_RG3873: 17,
	RETENCION_IVA_RG2616: 18,
	RETENCION_GANANCIAS_RG2616: 19,
	OTROS: 99,
})
