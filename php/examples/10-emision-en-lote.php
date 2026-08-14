<?php

declare(strict_types=1);

/**
 * 10 · Emisión en lote
 *
 * ARCA exige numeración correlativa por punto de venta y tipo de comprobante, así que la API emite
 * de a un comprobante por vez para cada combinación.
 *
 * ⚠️ SECUENCIÁ, NO PARALELICES. Lanzar en paralelo comprobantes del mismo punto de venta y tipo no
 * acelera nada —igual se serializan— y sólo produce errores 429. Si tenés que emitir mucho y
 * rápido, la forma de paralelizar es POR PUNTO DE VENTA: cada uno lleva su propia numeración.
 *
 * Lo otro que muestra este ejemplo es la persistencia de la clave de idempotencia: se genera y se
 * guarda ANTES de emitir. Si el proceso se cae a la mitad, al reanudarlo se reusa la misma clave y
 * los comprobantes ya emitidos vuelven con `idempotencyHit: true` en vez de duplicarse.
 *
 *   php examples/10-emision-en-lote.php
 */

require __DIR__ . '/bootstrap.php';

use NanoFactura\ApiException;
use NanoFactura\Client;
use NanoFactura\Constants\Concepto;
use NanoFactura\Constants\CondicionIva;
use NanoFactura\Constants\Provincia;
use NanoFactura\Constants\TipoComprobante;
use NanoFactura\Constants\TipoDocumento;

$nanofactura = new Client();
$posNumber = puntoDeVenta();

// En tu sistema esto sale de la base de datos. `idempotency` es una columna de la venta, no algo
// que se calcule al vuelo: se escribe cuando la venta se marca para facturar y no cambia nunca más.
$ventas = [
    ['id' => 'V-1001', 'cliente' => 'Pedro López', 'doc' => '35888999', 'provincia' => Provincia::SANTA_FE, 'total' => 30000, 'idempotency' => null],
    ['id' => 'V-1002', 'cliente' => 'Ana Gómez', 'doc' => '28444555', 'provincia' => Provincia::CORDOBA, 'total' => 47500, 'idempotency' => null],
    ['id' => 'V-1003', 'cliente' => 'Luis Ruiz', 'doc' => '31222888', 'provincia' => Provincia::BUENOS_AIRES, 'total' => 12300, 'idempotency' => null],
];

$emitidos = [];
$fallidos = [];

foreach ($ventas as $venta) {
    // 1. La clave se genera y se GUARDA antes de emitir. En tu sistema, este paso es un UPDATE.
    $venta['idempotency'] ??= bin2hex(random_bytes(16));
    // $db->ventas->update($venta['id'], ['idempotency' => $venta['idempotency']]);

    try {
        $comprobante = $nanofactura->emitirComprobante([
            'idempotency' => $venta['idempotency'],
            'posNumber' => $posNumber,
            'voucherType' => TipoComprobante::FACTURA_C,
            'concept' => Concepto::PRODUCTOS,
            'voucherDate' => hoy(),
            'customer' => [
                'docType' => TipoDocumento::DNI,
                'docNumber' => $venta['doc'],
                'name' => $venta['cliente'],
                'stateId' => $venta['provincia'],
                'taxType' => CondicionIva::CONSUMIDOR_FINAL,
            ],
            'items' => [
                [
                    'description' => "Venta {$venta['id']}",
                    'quantity' => 1,
                    'price' => $venta['total'],
                ],
            ],
            'externalReference' => $venta['id'],
        ]);

        // 2. Guardá el resultado INMEDIATAMENTE, no al final del lote. Si el proceso muere en la
        //    venta 40 de 100, las 39 anteriores tienen que estar registradas.
        // $db->ventas->update($venta['id'], ['cae' => $comprobante['cae'], ...]);

        $emitidos[] = $comprobante;

        $reusado = ($comprobante['idempotencyHit'] ?? false) ? ' (ya existía)' : '';
        echo "✅ {$venta['id']} → CAE {$comprobante['cae']}{$reusado}\n";
    } catch (ApiException $e) {
        $fallidos[] = $venta['id'];
        echo "❌ {$venta['id']} → " . ($e->errorCode ?? 'ERROR') . ": {$e->getMessage()}\n";

        // 3. Cortá al primer error PERMANENTE: si el primero está mal armado, probablemente lo
        //    estén todos, y seguir sólo suma rechazos. Los transitorios ya los reintentó el cliente.
        if (!$e->isRetryable() && !$e->isArcaRejection()) {
            echo "\n⛔ Error de configuración: se corta el lote.\n";
            break;
        }
    }
}

echo "\n─────────────────────────────────\n";
echo 'Emitidos: ' . count($emitidos) . ' · Fallidos: ' . count($fallidos) . "\n";

// La cuota del período viene en cada emisión: sirve para frenar antes de chocar.
$ultimo = end($emitidos);
if ($ultimo !== false && !empty($ultimo['quota'])) {
    echo "Cupo restante del período: {$ultimo['quota']['remaining']} (hasta {$ultimo['quota']['periodEnd']})\n";
}

// ── Paralelizar por punto de venta ───────────────────────────────────────────
//
// Distintos puntos de venta SÍ pueden emitir a la vez, porque cada uno lleva su propia numeración.
// En PHP, con procesos separados: uno por punto de venta, y dentro de cada uno el bucle secuencial
// de arriba. Nunca dos procesos sobre el MISMO punto de venta y tipo.
