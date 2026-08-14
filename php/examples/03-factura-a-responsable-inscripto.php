<?php

declare(strict_types=1);

/**
 * 03 · Factura A — responsable inscripto
 *
 * Un responsable inscripto le factura a otro responsable inscripto. Acá sí se discrimina IVA: cada
 * ítem lleva su alícuota real y la API arma el desglose que ARCA espera.
 *
 * Factura A exige que el receptor esté identificado con CUIT y sea responsable inscripto. Si tu
 * cliente es consumidor final, el comprobante que corresponde es B (ver ejemplo 04).
 *
 *   php examples/03-factura-a-responsable-inscripto.php
 */

require __DIR__ . '/bootstrap.php';

use NanoFactura\Client;
use NanoFactura\Constants\AlicuotaIva;
use NanoFactura\Constants\Concepto;
use NanoFactura\Constants\CondicionIva;
use NanoFactura\Constants\Provincia;
use NanoFactura\Constants\TipoComprobante;
use NanoFactura\Constants\TipoDocumento;
use NanoFactura\Constants\Unidad;

$nanofactura = new Client();

$comprobante = $nanofactura->emitirComprobante([
    'posNumber' => puntoDeVenta(),
    'voucherType' => TipoComprobante::FACTURA_A,
    'concept' => Concepto::PRODUCTOS,
    'voucherDate' => hoy(),

    'customer' => [
        // En Factura A el documento tiene que ser CUIT y la condición Responsable Inscripto.
        'docType' => TipoDocumento::CUIT,
        'docNumber' => '30123456789',
        'name' => 'DISTRIBUIDORA DEL LITORAL S.A.',
        'address' => 'Av. Pellegrini 2500, Rosario',
        'stateId' => Provincia::SANTA_FE,
        'taxType' => CondicionIva::RESPONSABLE_INSCRIPTO,
    ],

    'items' => [
        [
            'code' => 'INS-100',
            'description' => 'Insumos de oficina — resma A4',
            'quantity' => 50,
            'unitType' => Unidad::UNIDADES,
            'price' => 4500,
            'taxType' => AlicuotaIva::VEINTIUNO, // 21%
        ],
        [
            'code' => 'INS-220',
            'description' => 'Servicio de mantenimiento mensual',
            'quantity' => 1,
            'unitType' => Unidad::UNIDADES,
            'price' => 120000,
            'taxType' => AlicuotaIva::DIEZ_CINCO, // 10,5%

            // Lo que le falta a un ítem: un descuento de línea.
            // 'discount' => 10,               // 10% con discountType 'percentage', o $10 con 'amount'
            // 'discountType' => 'percentage', // 'percentage' (defecto) o 'amount'
        ],
    ],

    // Envío por email al emitir: sale en segundo plano, sin bloquear la respuesta. Descomentalo
    // poniendo una dirección tuya — el correo se manda de verdad.
    // 'emails' => [
    //     [
    //         'email' => 'vos@example.com',
    //         'name' => 'Administración',
    //         'comments' => 'Adjuntamos la factura de la orden de compra OC-2026-1188.',
    //         'pdfVersions' => [1], // 1 original · 2 duplicado · 3 triplicado
    //     ],
    // ],

    'externalReference' => 'OC-2026-1188',

    // ── Todo lo demás que acepta la API ──────────────────────────────────────
    //
    // Ninguno hace falta para emitir. Los que usan constantes que este ejemplo todavía no importa
    // (Moneda, Tributo) piden agregarlas a los `use` de arriba.
    // Referencia: https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante
    //
    // 'idempotency' => 'OC-2026-1188',           // tu clave única. Si no la mandás, el cliente genera un UUID
    // 'legend' => 'Gracias por su compra.',      // leyenda impresa en el comprobante. Máx. 200
    // 'comments' => 'Entrega en depósito 2',     // nota interna: se guarda, NO se imprime. Máx. 512
    // 'additionalData' => ['vendedor' => 'ML-22', 'canal' => 'web'], // objeto libre que viaja con el comprobante
    // 'receiverTaxConditionId' => CondicionIva::RESPONSABLE_INSCRIPTO, // pisa customer.taxType; casi nunca hace falta

    // Decimales de cantidad y precio unitario: 2 (defecto), 4 o 6. Los importes se redondean
    // siempre a 2, que es lo que exige ARCA:
    // 'settings' => ['quantityPrecision' => 4, 'pricePrecision' => 4],

    // Por defecto pesos a 1. Con moneda extranjera, la cotización del día la ponés vos:
    // 'currency' => ['id' => Moneda::DOLAR, 'quotation' => 1150],
    // 'cancelsInSameForeignCurrency' => 'S',     // 'S' o 'N', sólo con moneda extranjera

    // Con concept SERVICIOS o PRODUCTOS_Y_SERVICIOS, las tres fechas son OBLIGATORIAS (ejemplo 05):
    // 'serviceFrom' => '20260801',
    // 'serviceTo' => '20260831',
    // 'serviceExpiration' => '20260910',

    // Impuestos que NO son IVA: IIBB, tasas municipales, percepciones. Acá el importe lo calculás vos:
    // 'tributes' => [
    //     ['type' => Tributo::PERCEPCION_IIBB, 'description' => 'Percepción IIBB Santa Fe',
    //      'baseAmount' => 345000, 'aliquot' => 3.5, 'subtotal' => 12075],
    // ],

    // Sólo en notas de crédito y débito, apuntando al comprobante que modifican (ejemplo 06):
    // 'associatedVouchers' => [
    //     ['voucherType' => TipoComprobante::FACTURA_A, 'posNumber' => 1, 'voucherNumber' => 45],
    // ],
]);

echo "✅ Factura A emitida\n";
echo "   CAE:    {$comprobante['cae']}\n";
echo "   Número: {$comprobante['voucherNumber']}\n";
echo "   Total:  $ {$comprobante['totalAmount']}\n";

echo "\nEl total incluye el IVA de cada línea: la API calcula ImpNeto, ImpIVA y el desglose\n";
echo "por alícuota. Vos sólo mandaste cantidad, precio y alícuota.\n";
