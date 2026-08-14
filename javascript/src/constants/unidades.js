/**
 * Unidad de medida del ítem. Campo `items[].unitType`.
 *
 * Opcional: si no lo mandás se usa UNIDADES (7). Sólo importa si vendés por peso, volumen o medida.
 * Un código no reconocido se imprime como "unidades".
 *
 * No hay código de "hora": lo habitual es usar UNIDADES con la cantidad de horas y aclararlo en la
 * descripción.
 *
 * https://asistentefacturaelectronica.com/ayuda/api/tablas/unidades-de-medida
 */
export const UNIDAD = Object.freeze({
	UNIDADES: 7, // por defecto
	KILOGRAMOS: 1,
	METROS: 2,
	METROS_CUADRADOS: 3,
	METROS_CUBICOS: 4,
	LITROS: 5,
	PARES: 8,
	DOCENAS: 9,
	GRAMOS: 14,
	KILOMETROS: 17,
	CENTIMETROS: 20,
	TONELADAS: 29,
	MILILITROS: 47,
	PACKS: 96,
	OTRAS: 98,
})
