<?php

declare(strict_types=1);

namespace NanoFactura\Constants;

/**
 * Tipos de comprobante. Campo `voucherType` · en ARCA, `CbteTipo`.
 *
 * La letra la determina tu condición fiscal como emisor y la de tu cliente:
 *   Monotributista o exento                             → siempre C
 *   Responsable inscripto → a un RI o monotributo       → A
 *   Responsable inscripto → a consumidor final o exento → B
 *
 * ⚠️ Al monotributista le corresponde Factura A, no B. Durante años fue al revés, y es el error
 * más frecuente al integrar.
 *
 * Los comprobantes M (51, 52, 53) no se emiten por esta API.
 *
 * Son constantes de clase y no un enum a propósito: se serializan a JSON como el entero que ARCA
 * espera, sin tener que acordarse de ->value en cada uso.
 *
 * https://asistentefacturaelectronica.com/ayuda/api/tipos-de-comprobante
 */
final class TipoComprobante
{
    public const FACTURA_A = 1;
    public const NOTA_DEBITO_A = 2;
    public const NOTA_CREDITO_A = 3;

    public const FACTURA_B = 6;
    public const NOTA_DEBITO_B = 7;
    public const NOTA_CREDITO_B = 8;

    public const FACTURA_C = 11;
    public const NOTA_DEBITO_C = 12;
    public const NOTA_CREDITO_C = 13;

    /**
     * La letra de un tipo de comprobante.
     *
     * Sirve para validar antes de emitir que una nota de crédito sea de la misma letra que el
     * comprobante que anula — ARCA lo exige y es un error fácil de cometer al parametrizar.
     */
    public static function letra(int $voucherType): string
    {
        return match ($voucherType) {
            self::FACTURA_A, self::NOTA_DEBITO_A, self::NOTA_CREDITO_A => 'A',
            self::FACTURA_B, self::NOTA_DEBITO_B, self::NOTA_CREDITO_B => 'B',
            self::FACTURA_C, self::NOTA_DEBITO_C, self::NOTA_CREDITO_C => 'C',
            default => throw new \InvalidArgumentException("Tipo de comprobante desconocido: {$voucherType}"),
        };
    }
}
