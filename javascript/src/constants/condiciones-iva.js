/**
 * Condición del cliente frente al IVA.
 * Campos `customer.taxType` y `receiverTaxConditionId` · en ARCA, `CondicionIVAReceptorId`.
 *
 * ⚠️ No confundir con ALICUOTA_IVA, que es la alícuota de la LÍNEA. `5` acá es Consumidor Final;
 * `5` en un ítem es 21%.
 *
 * Si sos responsable inscripto, esto decide la letra del comprobante:
 *   RESPONSABLE_INSCRIPTO y MONOTRIBUTO → Factura A
 *   CONSUMIDOR_FINAL y EXENTO           → Factura B
 *
 * ⚠️ Al monotributista le corresponde Factura A, no B: es el error más frecuente al integrar.
 *
 * El código 2 (Responsable no Inscripto) está derogado y no debe usarse.
 *
 * https://asistentefacturaelectronica.com/ayuda/api/tablas/condiciones-iva
 */
export const CONDICION_IVA = Object.freeze({
	RESPONSABLE_INSCRIPTO: 1,
	NO_RESPONSABLE: 3,
	EXENTO: 4,
	CONSUMIDOR_FINAL: 5,
	MONOTRIBUTO: 6,
	NO_CATEGORIZADO: 7,
	PROVEEDOR_DEL_EXTERIOR: 8,
	CLIENTE_DEL_EXTERIOR: 9,
	LIBERADO_LEY_19640: 10,
	RESPONSABLE_INSCRIPTO_AGENTE_PERCEPCION: 11,
	PEQUENO_CONTRIBUYENTE_EVENTUAL: 12,
	MONOTRIBUTISTA_SOCIAL: 13,
	PEQUENO_CONTRIBUYENTE_EVENTUAL_SOCIAL: 14,
	NO_ALCANZADO: 15,
})
