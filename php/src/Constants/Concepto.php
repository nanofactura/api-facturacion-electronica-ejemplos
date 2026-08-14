<?php

declare(strict_types=1);

namespace NanoFactura\Constants;

/**
 * Concepto del comprobante. Campo `concept` · en ARCA, `Concepto`.
 *
 * ⚠️ Con SERVICIOS o PRODUCTOS_Y_SERVICIOS son obligatorias LAS TRES fechas de servicio:
 * `serviceFrom`, `serviceTo` y `serviceExpiration`, todas en formato YYYYMMDD.
 *
 * https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante
 */
final class Concepto
{
    public const PRODUCTOS = 1;
    public const SERVICIOS = 2;
    public const PRODUCTOS_Y_SERVICIOS = 3;
}
