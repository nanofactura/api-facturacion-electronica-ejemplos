<?php

declare(strict_types=1);

namespace NanoFactura\Constants;

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
final class Unidad
{
    public const UNIDADES = 7; // por defecto
    public const KILOGRAMOS = 1;
    public const METROS = 2;
    public const METROS_CUADRADOS = 3;
    public const METROS_CUBICOS = 4;
    public const LITROS = 5;
    public const PARES = 8;
    public const DOCENAS = 9;
    public const GRAMOS = 14;
    public const KILOMETROS = 17;
    public const CENTIMETROS = 20;
    public const TONELADAS = 29;
    public const MILILITROS = 47;
    public const PACKS = 96;
    public const OTRAS = 98;
}
