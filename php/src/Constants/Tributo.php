<?php

declare(strict_types=1);

namespace NanoFactura\Constants;

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
final class Tributo
{
    public const IMPUESTOS_NACIONALES = 1;
    public const IMPUESTOS_PROVINCIALES = 2;
    public const IMPUESTOS_MUNICIPALES = 3;
    public const IMPUESTOS_INTERNOS = 4;
    public const IIBB = 5;
    public const PERCEPCION_IVA = 6;
    public const PERCEPCION_IIBB = 7;
    public const PERCEPCION_MUNICIPAL = 8;
    public const OTRAS_PERCEPCIONES = 9;
    public const IMPUESTO_INTERNO_ITEM = 10;
    public const PERCEPCION_IVA_NO_CATEGORIZADO = 13;
    public const RETENCION_IIGG_RG830 = 14;
    public const RETENCION_IVA_RG3873 = 15;
    public const PAGO_A_CUENTA_IVA_RG3873 = 16;
    public const PERCEPCION_IVA_RG3873 = 17;
    public const RETENCION_IVA_RG2616 = 18;
    public const RETENCION_GANANCIAS_RG2616 = 19;
    public const OTROS = 99;
}
