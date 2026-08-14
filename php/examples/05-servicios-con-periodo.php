<?php

declare(strict_types=1);

/**
 * 05 · Servicios con período
 *
 * Cuando el concepto es SERVICIOS o PRODUCTOS_Y_SERVICIOS, ARCA exige LAS TRES fechas del período:
 * inicio, fin y vencimiento del pago. Si falta alguna, la API responde 400 antes de llamar a ARCA
 * y te dice cuál.
 *
 * Es el caso típico de un abono mensual o de una consultoría facturada por período.
 *
 *   php examples/05-servicios-con-periodo.php
 */

require __DIR__ . '/bootstrap.php';

use NanoFactura\Client;
use NanoFactura\Constants\Concepto;
use NanoFactura\Constants\CondicionIva;
use NanoFactura\Constants\Provincia;
use NanoFactura\Constants\TipoComprobante;
use NanoFactura\Constants\TipoDocumento;

$nanofactura = new Client();

// Todo el mes en curso, con vencimiento a diez días del cierre.
$desde = date('Ym01');
$hasta = date('Ymt');
$vencimiento = date('Ymd', strtotime('first day of next month +9 days'));

$comprobante = $nanofactura->emitirComprobante([
    'posNumber' => puntoDeVenta(),
    'voucherType' => TipoComprobante::FACTURA_C,
    'concept' => Concepto::SERVICIOS,

    'voucherDate' => hoy(),
    'serviceFrom' => $desde,          // FchServDesde
    'serviceTo' => $hasta,            // FchServHasta
    'serviceExpiration' => $vencimiento, // FchVtoPago

    'customer' => [
        'docType' => TipoDocumento::CUIT,
        'docNumber' => '30123456789',
        'name' => 'ESTUDIO CONTABLE NORTE S.R.L.',
        'address' => 'Av. Colón 780, Córdoba',
        'stateId' => Provincia::CORDOBA,
        'taxType' => CondicionIva::RESPONSABLE_INSCRIPTO,
    ],

    'items' => [
        [
            'code' => 'ABONO-SOP',
            'description' => 'Abono de soporte técnico — período ' . substr($desde, 0, 6),
            'quantity' => 1,
            'price' => 250000,
        ],
    ],

    // El envío sale en segundo plano, sin bloquear la respuesta. No hace falta que bajes el PDF
    // para reenviarlo vos. Descomentalo poniendo una dirección tuya — el correo se manda de verdad,
    // y la API rechaza los dominios de ejemplo como el de acá abajo.
    // 'emails' => [
    //     [
    //         'email' => 'vos@example.com',
    //         'name' => 'Administración',
    //         'comments' => 'Adjuntamos la factura del período. Ante cualquier duda, quedamos a disposición.',
    //         'pdfVersions' => [1],
    //     ],
    // ],

    'externalReference' => 'ABONO-' . substr($desde, 0, 6),
]);

echo "✅ Factura de servicios emitida\n";
echo "   CAE:      {$comprobante['cae']}\n";
echo "   Período:  {$desde} → {$hasta}\n";
echo "   Vence:    {$vencimiento}\n";
echo "   Total:    $ {$comprobante['totalAmount']}\n";
echo "\nDescomentá `emails` con una dirección tuya y el envío sale con la emisión, en segundo plano.\n";
