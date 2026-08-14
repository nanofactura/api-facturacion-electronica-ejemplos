<?php

declare(strict_types=1);

namespace NanoFactura\Constants;

/**
 * Alícuotas de IVA de la línea. Campo `items[].taxType` · en ARCA, `Iva[].Id`.
 *
 * ⚠️ No confundir con CondicionIva, que es la condición del CLIENTE frente al IVA. Los códigos se
 * solapan: `5` acá es 21%, y `5` en el cliente es Consumidor Final.
 *
 * En los comprobantes C el campo no va: no se discrimina IVA y todas las líneas van al neto.
 *
 * https://asistentefacturaelectronica.com/ayuda/api/tablas/alicuotas-iva
 */
final class AlicuotaIva
{
    public const NO_GRAVADO = 1;   // no genera IVA, va a ImpTotConc
    public const EXENTO = 2;       // no genera IVA, va a ImpOpEx
    public const CERO = 3;         // 0%, va a ImpOpEx
    public const DIEZ_CINCO = 4;   // 10,5%
    public const VEINTIUNO = 5;    // 21%  ← la alícuota general
    public const VEINTISIETE = 6;  // 27%
    public const CINCO = 8;        // 5%
    public const DOS_CINCO = 9;    // 2,5%

    /** El porcentaje de cada código, por si necesitás mostrarlo o calcular del lado del cliente. */
    public static function porcentaje(int $alicuota): float
    {
        return match ($alicuota) {
            self::DIEZ_CINCO => 10.5,
            self::VEINTIUNO => 21.0,
            self::VEINTISIETE => 27.0,
            self::CINCO => 5.0,
            self::DOS_CINCO => 2.5,
            default => 0.0,
        };
    }
}
