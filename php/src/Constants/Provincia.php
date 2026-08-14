<?php

declare(strict_types=1);

namespace NanoFactura\Constants;

/**
 * Provincia del cliente. Campo `customer.stateId`.
 *
 * No se le envía a ARCA: se guarda con el comprobante. Es el único dato ESTRUCTURADO de ubicación
 * —`address` es texto libre y no se puede agrupar—, y es lo que permite después repartir la
 * facturación por jurisdicción para Ingresos Brutos y Convenio Multilateral.
 *
 * Mandala siempre que la conozcas: no se puede reconstruir hacia atrás. Si genuinamente no la
 * sabés, mandá null ("no informada"). No pongas 0 por defecto: 0 es CABA, y un dato inventado
 * ensucia los reportes sin que se note.
 *
 * Son 24 jurisdicciones: las 23 provincias más CABA, que no es una provincia sino un distrito
 * autónomo pero cuenta como jurisdicción propia para Ingresos Brutos. Los códigos son de ARCA y no
 * son correlativos: el 15 no se usa.
 *
 * https://asistentefacturaelectronica.com/ayuda/api/tablas/provincias
 */
final class Provincia
{
    public const CABA = 0;
    public const BUENOS_AIRES = 1;
    public const CATAMARCA = 2;
    public const CORDOBA = 3;
    public const CORRIENTES = 4;
    public const ENTRE_RIOS = 5;
    public const JUJUY = 6;
    public const MENDOZA = 7;
    public const LA_RIOJA = 8;
    public const SALTA = 9;
    public const SAN_JUAN = 10;
    public const SAN_LUIS = 11;
    public const SANTA_FE = 12;
    public const SANTIAGO_DEL_ESTERO = 13;
    public const TUCUMAN = 14;
    public const CHACO = 16;
    public const CHUBUT = 17;
    public const FORMOSA = 18;
    public const MISIONES = 19;
    public const NEUQUEN = 20;
    public const LA_PAMPA = 21;
    public const RIO_NEGRO = 22;
    public const SANTA_CRUZ = 23;
    public const TIERRA_DEL_FUEGO = 24;

    public const NO_INFORMADA = null;
}
