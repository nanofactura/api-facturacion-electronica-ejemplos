/**
 * Tipos de comprobante. Campo `voucherType` · en ARCA, `CbteTipo`.
 *
 * La letra la determina tu condición fiscal como emisor y la de tu cliente:
 *   Monotributista o exento                     → siempre C
 *   Responsable inscripto → a un RI o monotributo → A
 *   Responsable inscripto → a consumidor final o exento → B
 *
 * ⚠️ Al monotributista le corresponde Factura A, no B. Durante años fue al revés, y es el error
 * más frecuente al integrar.
 *
 * Los comprobantes M (51, 52, 53) no se emiten por esta API.
 *
 * https://asistentefacturaelectronica.com/ayuda/api/tipos-de-comprobante
 */
export const TIPO_COMPROBANTE = Object.freeze({
	FACTURA_A: 1,
	NOTA_DEBITO_A: 2,
	NOTA_CREDITO_A: 3,

	FACTURA_B: 6,
	NOTA_DEBITO_B: 7,
	NOTA_CREDITO_B: 8,

	FACTURA_C: 11,
	NOTA_DEBITO_C: 12,
	NOTA_CREDITO_C: 13,
})

/**
 * La letra de cada tipo. Sirve para validar antes de emitir que una nota de crédito sea de la
 * misma letra que el comprobante que anula — ARCA lo exige y es un error fácil de cometer.
 */
export const LETRA_COMPROBANTE = Object.freeze({
	[TIPO_COMPROBANTE.FACTURA_A]: 'A',
	[TIPO_COMPROBANTE.NOTA_DEBITO_A]: 'A',
	[TIPO_COMPROBANTE.NOTA_CREDITO_A]: 'A',
	[TIPO_COMPROBANTE.FACTURA_B]: 'B',
	[TIPO_COMPROBANTE.NOTA_DEBITO_B]: 'B',
	[TIPO_COMPROBANTE.NOTA_CREDITO_B]: 'B',
	[TIPO_COMPROBANTE.FACTURA_C]: 'C',
	[TIPO_COMPROBANTE.NOTA_DEBITO_C]: 'C',
	[TIPO_COMPROBANTE.NOTA_CREDITO_C]: 'C',
})
