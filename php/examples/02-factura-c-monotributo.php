<?php

declare(strict_types=1);

/**
 * 02 · Factura C — monotributo
 *
 * El caso más común en Argentina: un monotributista le factura a un consumidor final.
 *
 * En los comprobantes C no se discrimina IVA, así que los ítems NO llevan `taxType`: todas las
 * líneas van al neto. En las facturas A y B, en cambio, la alícuota de cada ítem es obligatoria.
 *
 *   php examples/02-factura-c-monotributo.php
 */

require __DIR__ . '/bootstrap.php';

use NanoFactura\Client;
use NanoFactura\Constants\Concepto;
use NanoFactura\Constants\CondicionIva;
use NanoFactura\Constants\Provincia;
use NanoFactura\Constants\TipoComprobante;
use NanoFactura\Constants\TipoDocumento;
use NanoFactura\Constants\Unidad;

$nanofactura = new Client();

$comprobante = $nanofactura->emitirComprobante([
    'posNumber' => puntoDeVenta(),
    'voucherType' => TipoComprobante::FACTURA_C,
    'concept' => Concepto::PRODUCTOS,
    'voucherDate' => hoy(),

    'customer' => [
        'docType' => TipoDocumento::DNI,
        'docNumber' => '35888999',
        'name' => 'Pedro López',
        'address' => 'San Martín 1200, Rosario',
        // Mandala siempre que la conozcas: es lo que después permite armar reportes por
        // jurisdicción para Ingresos Brutos, y no se puede reconstruir hacia atrás.
        'stateId' => Provincia::SANTA_FE,
        'taxType' => CondicionIva::CONSUMIDOR_FINAL,
    ],

    'items' => [
        [
            'code' => 'PROD-001',
            'description' => 'Teclado mecánico inalámbrico',
            'quantity' => 2,
            'unitType' => Unidad::UNIDADES,
            'price' => 15000,
        ],
        [
            'code' => 'PROD-002',
            'description' => 'Mouse ergonómico',
            'quantity' => 1,
            'unitType' => Unidad::UNIDADES,
            'price' => 8000,
            'discount' => 10,
            'discountType' => 'percentage',
        ],
    ],

    // Envío por email al emitir: sale en segundo plano, sin bloquear la respuesta. Descomentalo
    // poniendo una dirección tuya — el correo se manda de verdad.
    // 'emails' => [
    //     [
    //         'email' => 'vos@example.com',
    //         'name' => 'Pedro López',
    //         'comments' => 'Adjuntamos tu comprobante. ¡Gracias por tu compra!',
    //         'pdfVersions' => [1], // 1 original · 2 duplicado · 3 triplicado
    //     ],
    // ],

    // Tu identificador. Es lo que después te permite encontrar el comprobante desde tu sistema
    // sin llevar una tabla de equivalencias.
    'externalReference' => 'PEDIDO-2026-00042',
    'legend' => '¡Gracias por su compra!',

    // ── Todo lo demás que acepta la API ──────────────────────────────────────
    //
    // Ninguno hace falta para emitir. Los que usan constantes que este ejemplo todavía no importa
    // (Moneda, Tributo) piden agregarlas a los `use` de arriba.
    // Referencia: https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante
    //
    // 'idempotency' => 'PEDIDO-2026-00042',       // tu clave única. Si no la mandás, el cliente genera un UUID
    // 'comments' => 'Retira por sucursal Centro', // nota interna: se guarda, NO se imprime. Máx. 512
    // 'additionalData' => ['vendedor' => 'ML-22', 'canal' => 'web'], // objeto libre que viaja con el comprobante
    // 'receiverTaxConditionId' => CondicionIva::CONSUMIDOR_FINAL,    // pisa customer.taxType; casi nunca hace falta

    // Decimales de cantidad y precio unitario: 2 (defecto), 4 o 6. Los importes se redondean
    // siempre a 2, que es lo que exige ARCA:
    // 'settings' => ['quantityPrecision' => 4, 'pricePrecision' => 4],

    // Por defecto pesos a 1. Con moneda extranjera, la cotización del día la ponés vos:
    // 'currency' => ['id' => Moneda::DOLAR, 'quotation' => 1150],
    // 'cancelsInSameForeignCurrency' => 'S',      // 'S' o 'N', sólo con moneda extranjera

    // Con concept SERVICIOS o PRODUCTOS_Y_SERVICIOS, las tres fechas son OBLIGATORIAS (ejemplo 05):
    // 'serviceFrom' => '20260801',
    // 'serviceTo' => '20260831',
    // 'serviceExpiration' => '20260910',

    // Impuestos que NO son IVA: IIBB, tasas municipales, percepciones. Acá el importe lo calculás vos:
    // 'tributes' => [
    //     ['type' => Tributo::PERCEPCION_IIBB, 'description' => 'Percepción IIBB Santa Fe',
    //      'baseAmount' => 30000, 'aliquot' => 3.5, 'subtotal' => 1050],
    // ],

    // Sólo en notas de crédito y débito, apuntando al comprobante que modifican (ejemplo 06):
    // 'associatedVouchers' => [
    //     ['voucherType' => TipoComprobante::FACTURA_C, 'posNumber' => 1, 'voucherNumber' => 45],
    // ],
]);

echo "✅ Comprobante emitido\n";
echo "   CAE:     {$comprobante['cae']}\n";
echo '   Número:  ' . formatearNumero(puntoDeVenta(), $comprobante['voucherNumber']) . "\n";
echo "   Total:   $ {$comprobante['totalAmount']}\n";
echo "   ID:      {$comprobante['id']}\n";

// ARCA a veces aprueba el comprobante señalando algo. Vale la pena registrarlo.
if (!empty($comprobante['arcaObservations'])) {
    echo "\n⚠️ ARCA aprobó con observaciones:\n";
    foreach ($comprobante['arcaObservations'] as $obs) {
        echo "   ({$obs['code']}) {$obs['msg']}\n";
    }
}

echo "\nGuardá contra tu operación: id, cae, voucherNumber, posNumber y voucherType.\n";
echo "El PDF se genera solo, en segundo plano: pedilo cuando alguien lo necesite.\n";
