<?php

declare(strict_types=1);

/**
 * 06 · Nota de crédito
 *
 * Un comprobante autorizado no se puede borrar ni modificar: para anularlo o corregirlo se emite
 * una nota de crédito que lo compensa.
 *
 * Dos reglas que no se negocian:
 *   1. Misma letra que el comprobante original (una NC "C" anula una Factura C).
 *   2. Siempre con `associatedVouchers` apuntando al comprobante que modifica.
 *
 *   php examples/06-nota-de-credito.php
 */

require __DIR__ . '/bootstrap.php';

use NanoFactura\Client;
use NanoFactura\Constants\Concepto;
use NanoFactura\Constants\CondicionIva;
use NanoFactura\Constants\Provincia;
use NanoFactura\Constants\TipoComprobante;
use NanoFactura\Constants\TipoDocumento;

$nanofactura = new Client();
$posNumber = puntoDeVenta();

$cliente = [
    'docType' => TipoDocumento::DNI,
    'docNumber' => '35888999',
    'name' => 'Pedro López',
    'stateId' => Provincia::SANTA_FE,
    'taxType' => CondicionIva::CONSUMIDOR_FINAL,
];

// ── 1. Emitimos una factura para después anularla ────────────────────────────

$factura = $nanofactura->emitirComprobante([
    'posNumber' => $posNumber,
    'voucherType' => TipoComprobante::FACTURA_C,
    'concept' => Concepto::PRODUCTOS,
    'voucherDate' => hoy(),
    'customer' => $cliente,
    'items' => [
        ['description' => 'Auriculares bluetooth', 'quantity' => 1, 'price' => 45000],
    ],
    'externalReference' => 'PEDIDO-2026-00099',
]);

echo "Factura emitida  · Nº {$factura['voucherNumber']} · $ {$factura['totalAmount']}\n";

// ── 2. El cliente devuelve el producto: emitimos la nota de crédito ──────────

$tipoNotaCredito = TipoComprobante::NOTA_CREDITO_C;

// La letra tiene que coincidir. Vale la pena verificarlo en código: es un error fácil de cometer
// al parametrizar el tipo de comprobante, y ARCA lo rechaza recién al emitir.
if (TipoComprobante::letra($tipoNotaCredito) !== TipoComprobante::letra(TipoComprobante::FACTURA_C)) {
    throw new RuntimeException('La nota de crédito tiene que ser de la misma letra que el comprobante que anula.');
}

$notaCredito = $nanofactura->emitirComprobante([
    'posNumber' => $posNumber,
    'voucherType' => $tipoNotaCredito,
    'concept' => Concepto::PRODUCTOS,
    'voucherDate' => hoy(),

    // El vínculo con el comprobante original. Sin esto, ARCA rechaza.
    'associatedVouchers' => [
        [
            'voucherType' => TipoComprobante::FACTURA_C,
            'posNumber' => $posNumber,
            'voucherNumber' => $factura['voucherNumber'],
        ],
    ],

    // Mismo receptor que la factura original.
    'customer' => $cliente,

    // Por el total, porque anulamos toda la factura. Para una devolución parcial irían sólo las
    // líneas devueltas, con sus cantidades.
    'items' => [
        [
            'description' => 'Anulación de Factura C ' . formatearNumero($posNumber, $factura['voucherNumber']) . ' — devolución',
            'quantity' => 1,
            'price' => 45000,
        ],
    ],

    // La nota de crédito también le llega al cliente: es el comprobante que respalda la devolución.
    // Descomentalo poniendo una dirección tuya — el correo se manda de verdad.
    // 'emails' => [
    //     [
    //         'email' => 'vos@example.com',
    //         'name' => 'Pedro López',
    //         'comments' => 'Adjuntamos la nota de crédito por la devolución.',
    //         'pdfVersions' => [1], // 1 original · 2 duplicado · 3 triplicado
    //     ],
    // ],

    'externalReference' => 'PEDIDO-2026-00099-NC',
]);

echo "Nota de crédito  · Nº {$notaCredito['voucherNumber']} · $ {$notaCredito['totalAmount']}\n";
echo "\n✅ Factura " . formatearNumero($posNumber, $factura['voucherNumber']) . " anulada.\n";
echo "   Los dos comprobantes quedan en tu historial: así lo entiende ARCA.\n";
