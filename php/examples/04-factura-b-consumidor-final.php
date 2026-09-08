<?php

declare(strict_types=1);

/**
 * 04 · Factura B — consumidor final
 *
 * Un responsable inscripto le factura a un consumidor final. Se calcula el IVA pero no se imprime
 * discriminado.
 *
 * Lo que muestra este ejemplo es el manejo del umbral de identificación: por debajo de cierto
 * importe podés facturar sin identificar al comprador; por encima, ARCA lo exige y rechaza con el
 * código 10015. Ese umbral lo actualiza ARCA por resolución, así que NO se hardcodea: se maneja el
 * rechazo.
 *
 *   php examples/04-factura-b-consumidor-final.php
 */

require __DIR__ . '/bootstrap.php';

use NanoFactura\ApiException;
use NanoFactura\Client;
use NanoFactura\Constants\AlicuotaIva;
use NanoFactura\Constants\Concepto;
use NanoFactura\Constants\CondicionIva;
use NanoFactura\Constants\Provincia;
use NanoFactura\Constants\TipoComprobante;
use NanoFactura\Constants\TipoDocumento;

$nanofactura = new Client();

// Un consumidor final del que no tenemos los datos.
$clienteAnonimo = [
    'docType' => TipoDocumento::SIN_IDENTIFICAR,
    'docNumber' => '0',
    'name' => 'Consumidor Final',
    'taxType' => CondicionIva::CONSUMIDOR_FINAL,
    'stateId' => Provincia::NO_INFORMADA,
];

// El mismo cliente, ya identificado.
$clienteIdentificado = [
    'docType' => TipoDocumento::DNI,
    'docNumber' => '28444555',
    'name' => 'Ana Gómez',
    'address' => 'Belgrano 450, Córdoba',
    'taxType' => CondicionIva::CONSUMIDOR_FINAL,
    'stateId' => Provincia::CORDOBA,
];

$venta = [
    'posNumber' => puntoDeVenta(),
    'voucherType' => TipoComprobante::FACTURA_B,
    'concept' => Concepto::PRODUCTOS,
    'voucherDate' => hoy(),

    // El precio del ítem es el que ve el cliente: $1.350.000 finales, IVA adentro. Es el valor por
    // defecto de la API y en una Factura B es casi siempre el que corresponde, porque el comprobante
    // no discrimina IVA y el receptor paga ese número. Con false, la API lo tomaría como neto y la
    // factura saldría $1.633.500.
    'priceIncludesVat' => true,

    'items' => [
        [
            'description' => 'Notebook 14" 16GB',
            'quantity' => 1,
            'price' => 1350000,
            'taxType' => AlicuotaIva::VEINTIUNO,

            // Lo que le falta a un ítem. Ninguno es obligatorio:
            // 'code' => 'NB-14-16',           // tu código interno del producto
            // 'unitType' => Unidad::UNIDADES, // unidad de medida; 7 (unidades) por defecto
            // 'discount' => 10,               // 10% con discountType 'percentage', o $10 con 'amount'
            // 'discountType' => 'percentage', // 'percentage' (defecto) o 'amount'
        ],
    ],
    // Envío por email al emitir: sale en segundo plano, sin bloquear la respuesta. Descomentalo
    // poniendo una dirección tuya — el correo se manda de verdad.
    // 'emails' => [
    //     [
    //         'email' => 'vos@example.com',
    //         'name' => 'Ana Gómez',
    //         'comments' => 'Adjuntamos tu comprobante. ¡Gracias por tu compra!',
    //         'pdfVersions' => [1], // 1 original · 2 duplicado · 3 triplicado
    //     ],
    // ],

    'externalReference' => 'TICKET-88213',

    // ── Todo lo demás que acepta la API ──────────────────────────────────────
    //
    // Ninguno hace falta para emitir. Los que usan constantes que este ejemplo todavía no importa
    // (Moneda, Tributo, Unidad) piden agregarlas a los `use` de arriba.
    // Referencia: https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante
    //
    // 'idempotency' => 'TICKET-88213',           // tu clave única. Si no la mandás, el cliente genera un UUID
    // 'legend' => '¡Gracias por su compra!',     // leyenda impresa en el comprobante. Máx. 200
    // 'comments' => 'Vendido en caja 3',         // nota interna: se guarda, NO se imprime. Máx. 512
    // 'additionalData' => ['cajero' => 'ML-22', 'sucursal' => 'centro'], // objeto libre que viaja con el comprobante
    // 'receiverTaxConditionId' => CondicionIva::CONSUMIDOR_FINAL,        // pisa customer.taxType; casi nunca hace falta

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
    //     ['type' => Tributo::PERCEPCION_IIBB, 'description' => 'Percepción IIBB Córdoba',
    //      'baseAmount' => 1115702.48, 'aliquot' => 3.5, 'subtotal' => 39049.59],
    // ],

    // Sólo en notas de crédito y débito, apuntando al comprobante que modifican (ejemplo 06):
    // 'associatedVouchers' => [
    //     ['voucherType' => TipoComprobante::FACTURA_B, 'posNumber' => 1, 'voucherNumber' => 45],
    // ],
];

try {
    $comprobante = $nanofactura->emitirComprobante(array_merge($venta, ['customer' => $clienteAnonimo]));
    echo "✅ Emitida sin identificar al comprador · CAE {$comprobante['cae']}\n";
} catch (ApiException $e) {
    // 10015: el importe supera el máximo que ARCA permite sin identificar al comprador.
    $requiereIdentificacion = $e->tieneCodigoArca(10015);

    if (!$requiereIdentificacion) {
        throw $e;
    }

    echo "⚠️ ARCA pide identificar al comprador para este importe.\n";
    echo "  {$e->getMessage()}\n";
    echo "\n  Reintentando con los datos del cliente…\n\n";

    // Importante: es una emisión NUEVA, con una clave de idempotencia nueva. La anterior fue
    // rechazada por ARCA, así que no existe ningún comprobante que recuperar.
    $comprobante = $nanofactura->emitirComprobante(array_merge($venta, ['customer' => $clienteIdentificado]));
    echo "✅ Emitida con el comprador identificado · CAE {$comprobante['cae']}\n";
}

echo "\nEn producción, la forma correcta es al revés: pedile el documento al cliente\n";
echo "siempre que puedas, y tratá el 10015 como \"faltan datos\", no como un error.\n";
