/**
 * Tablas de códigos de ARCA.
 *
 * Usalas siempre en lugar de los números: `taxType` significa cosas distintas en un ítem (alícuota
 * de IVA) y en el cliente (condición frente al IVA), y los códigos se solapan. `5` es 21% en un
 * ítem y Consumidor Final en el cliente. Escribir el nombre hace imposible confundirlos.
 *
 * Referencia: https://asistentefacturaelectronica.com/ayuda/api/campos-arca
 */

export { TIPO_COMPROBANTE, LETRA_COMPROBANTE } from './tipos-comprobante.js'
export { ALICUOTA_IVA, PORCENTAJE_IVA } from './alicuotas-iva.js'
export { CONDICION_IVA } from './condiciones-iva.js'
export { TIPO_DOCUMENTO } from './tipos-documento.js'
export { UNIDAD } from './unidades.js'
export { MONEDA } from './monedas.js'
export { TRIBUTO } from './tributos.js'
export { PROVINCIAS } from './provincias.js'
export { CONCEPTO } from './conceptos.js'
