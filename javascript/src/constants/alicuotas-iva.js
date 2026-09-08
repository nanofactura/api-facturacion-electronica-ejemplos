/**
 * Alícuotas de IVA de la línea. Campo `items[].taxType` · en ARCA, `Iva[].Id`.
 *
 * ⚠️ No confundir con CONDICION_IVA, que es la condición del CLIENTE frente al IVA. Los códigos se
 * solapan: `5` acá es 21%, y `5` en el cliente es Consumidor Final.
 *
 * En los comprobantes C el campo no va: no se discrimina IVA y todas las líneas van al neto.
 *
 * https://asistentefacturaelectronica.com/ayuda/api/tablas/alicuotas-iva
 */
export const ALICUOTA_IVA = Object.freeze({
	NO_GRAVADO: 1, // suma al importe no gravado; no genera IVA
	EXENTO: 2, // suma al importe exento; no genera IVA
	CERO: 3, // 0%: gravado, suma al neto con un IVA de 0
	DIEZ_CINCO: 4, // 10,5%
	VEINTIUNO: 5, // 21%  ← la alícuota general
	VEINTISIETE: 6, // 27%
	CINCO: 8, // 5%
	DOS_CINCO: 9, // 2,5%
})

/** El porcentaje de cada código, por si necesitás mostrarlo o calcular del lado del cliente. */
export const PORCENTAJE_IVA = Object.freeze({
	[ALICUOTA_IVA.NO_GRAVADO]: 0,
	[ALICUOTA_IVA.EXENTO]: 0,
	[ALICUOTA_IVA.CERO]: 0,
	[ALICUOTA_IVA.DIEZ_CINCO]: 10.5,
	[ALICUOTA_IVA.VEINTIUNO]: 21,
	[ALICUOTA_IVA.VEINTISIETE]: 27,
	[ALICUOTA_IVA.CINCO]: 5,
	[ALICUOTA_IVA.DOS_CINCO]: 2.5,
})
