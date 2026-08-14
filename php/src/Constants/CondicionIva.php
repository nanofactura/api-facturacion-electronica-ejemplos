<?php

declare(strict_types=1);

namespace NanoFactura\Constants;

/**
 * Condición del cliente frente al IVA.
 * Campos `customer.taxType` y `receiverTaxConditionId` · en ARCA, `CondicionIVAReceptorId`.
 *
 * ⚠️ No confundir con AlicuotaIva, que es la alícuota de la LÍNEA. `5` acá es Consumidor Final;
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
final class CondicionIva
{
    public const RESPONSABLE_INSCRIPTO = 1;
    public const NO_RESPONSABLE = 3;
    public const EXENTO = 4;
    public const CONSUMIDOR_FINAL = 5;
    public const MONOTRIBUTO = 6;
    public const NO_CATEGORIZADO = 7;
    public const PROVEEDOR_DEL_EXTERIOR = 8;
    public const CLIENTE_DEL_EXTERIOR = 9;
    public const LIBERADO_LEY_19640 = 10;
    public const RESPONSABLE_INSCRIPTO_AGENTE_PERCEPCION = 11;
    public const PEQUENO_CONTRIBUYENTE_EVENTUAL = 12;
    public const MONOTRIBUTISTA_SOCIAL = 13;
    public const PEQUENO_CONTRIBUYENTE_EVENTUAL_SOCIAL = 14;
    public const NO_ALCANZADO = 15;
}
