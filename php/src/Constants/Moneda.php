<?php

declare(strict_types=1);

namespace NanoFactura\Constants;

/**
 * Moneda del comprobante. Campo `currency.id` · en ARCA, `MonId`.
 *
 * Si no es PESOS, tenés que mandar `currency.quotation` con la cotización del día contra el peso:
 * la API no la busca por vos, porque qué tipo de cambio aplicar es una decisión tuya.
 *
 * Los `price` de los ítems van EN LA MONEDA DEL COMPROBANTE, no convertidos a pesos.
 *
 * https://asistentefacturaelectronica.com/ayuda/api/tablas/monedas
 */
final class Moneda
{
    public const PESOS = 'PES'; // por defecto
    public const DOLAR = 'DOL';
    public const EURO = '060';
    public const REAL = '012';
    public const PESO_URUGUAYO = '011';
    public const LIBRA_ESTERLINA = '021';
    public const YUAN = '064';
}
