/**
 * Moneda del comprobante. Campo `currency.id` · en ARCA, `MonId`.
 *
 * Si no es PESOS, tenés que mandar `currency.quotation` con la cotización del día contra el peso:
 * la API no la busca por vos, porque qué tipo de cambio aplicar es una decisión tuya.
 *
 * Los `price` de los ítems van EN LA MONEDA DEL COMPROBANTE, no convertidos a pesos.
 *
 * https://asistentefacturaelectronica.com/ayuda/api/tablas/monedas
 */
export const MONEDA = Object.freeze({
	PESOS: 'PES', // por defecto
	DOLAR: 'DOL',
	EURO: '060',
	REAL: '012',
	PESO_URUGUAYO: '011',
	LIBRA_ESTERLINA: '021',
	YUAN: '064',
})
