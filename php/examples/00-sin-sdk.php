<?php

declare(strict_types=1);

/**
 * 00 · La API cruda — sin cliente y sin dependencias
 *
 * Esto es TODO lo que hace falta para emitir una factura electrónica: un POST con un JSON y la API
 * key en el header. Las clases de `src/` son comodidad —constantes con nombre, reintentos, errores
 * tipados—, no un requisito. Si vas a escribir tu propia integración, empezá por acá.
 *
 * Sólo cURL, que viene con PHP. Sin Composer, sin autoloader, sin bootstrap.
 *
 * Están activos sólo los campos OBLIGATORIOS. Todo lo demás va comentado, con un valor de ejemplo y
 * los códigos de ARCA al lado, para que veas el pedido completo sin ir a la documentación.
 *
 *   php examples/00-sin-sdk.php
 */

// Lectura mínima del .env de la raíz, sólo para que el ejemplo corra sin instalar nada. En tu
// sistema esto son variables de entorno, o vlucas/phpdotenv.
$rutaEnv = __DIR__ . '/../../.env';
if (file_exists($rutaEnv)) {
    foreach (file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea !== '' && !str_starts_with($linea, '#') && str_contains($linea, '=')) {
            [$clave, $valor] = explode('=', $linea, 2);
            if (getenv(trim($clave)) === false) {
                putenv(trim($clave) . '=' . trim($valor));
            }
        }
    }
}

$apiKey = getenv('NANOFACTURA_API_KEY');
$idEmpresa = getenv('NANOFACTURA_ID_EMPRESA');
$baseUrl = getenv('NANOFACTURA_BASE_URL') ?: 'https://nanofactura.com/api';
$posNumber = (int) (getenv('NANOFACTURA_POS_NUMBER') ?: 1);

if (!$apiKey || !$idEmpresa) {
    fwrite(STDERR, "Faltan NANOFACTURA_API_KEY y/o NANOFACTURA_ID_EMPRESA. Copiá .env.example a .env y completalo.\n");
    exit(1);
}

$comprobante = [
    // ── Obligatorios ─────────────────────────────────────────────────────────
    //
    // Tu clave única de este comprobante: si el pedido se corta y lo reintentás con la misma clave,
    // la API devuelve el comprobante que ya emitió en vez de duplicarlo. En tu sistema sale de la
    // venta y se guarda ANTES de emitir. Máx. 128 caracteres: letras, números, - y _.
    'idempotency' => 'sin-sdk-' . date('Ymd') . '-' . getmypid(),

    'posNumber' => $posNumber,
    'voucherType' => 11,  // 11 Factura C · 1 Factura A · 6 Factura B · 13/3/8 notas de crédito
    'concept' => 1,       // 1 productos · 2 servicios · 3 ambos
    'voucherDate' => date('Ymd'),

    'customer' => [
        'docType' => 96,           // 80 CUIT · 86 CUIL · 96 DNI · 99 sin identificar
        'docNumber' => '35888999', // sin puntos ni guiones
        'name' => 'Pedro López',
        // Condición del RECEPTOR frente al IVA: 1 RI · 4 exento · 5 consumidor final · 6 monotributo
        'taxType' => 5,

        // 'address' => 'San Martín 1200, Rosario',  // se imprime en el comprobante
        // 'stateId' => 12,                          // provincia (12 = Santa Fe). Mandala siempre que
        //                                           // la sepas: es el único dato agrupable para
        //                                           // reportes de Ingresos Brutos
    ],

    'items' => [
        [
            'description' => 'Teclado mecánico inalámbrico',
            'quantity' => 2,
            'price' => 15000, // unitario, sin descuento

            // La alícuota de IVA de la línea es obligatoria en A y B, y NO va en los comprobantes C,
            // que no discriminan IVA:
            // 'taxType' => 5,                  // 5 = 21% · 4 = 10,5% · 3 = 0% · 2 exento · 1 no gravado
            // 'code' => 'PROD-001',            // tu código interno
            // 'unitType' => 7,                 // unidad de medida; 7 = unidades (por defecto)
            // 'discount' => 10,                // descuento de la línea
            // 'discountType' => 'percentage',  // 'percentage' (defecto) o 'amount'
        ],
    ],

    // ── Opcionales ───────────────────────────────────────────────────────────
    //
    // Referencia completa: https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante

    // 'externalReference' => 'PEDIDO-2026-00042', // tu identificador. Es el único campo por el que
    //                                            // después podés BUSCAR el comprobante en el listado
    // 'legend' => '¡Gracias por su compra!',      // leyenda impresa. Máx. 200
    // 'comments' => 'Retira por sucursal Centro', // nota interna: se guarda, NO se imprime. Máx. 512
    // 'additionalData' => ['vendedor' => 'ML-22', 'canal' => 'web'], // objeto libre que viaja con el comprobante

    // Con concept 2 o 3, las tres fechas del período son OBLIGATORIAS:
    // 'serviceFrom' => '20260801',
    // 'serviceTo' => '20260831',
    // 'serviceExpiration' => '20260910',

    // Sólo en notas de crédito y débito, apuntando al comprobante que modifican:
    // 'associatedVouchers' => [
    //     ['voucherType' => 11, 'posNumber' => 1, 'voucherNumber' => 45], // 'cuit' sólo si es de otro emisor
    // ],

    // Por defecto pesos a 1. Con moneda extranjera, la cotización del día la ponés vos:
    // 'currency' => ['id' => 'DOL', 'quotation' => 1150],
    // 'cancelsInSameForeignCurrency' => 'S',   // 'S' o 'N', sólo con moneda extranjera

    // Impuestos que NO son IVA (IIBB, tasas, percepciones). Acá el importe lo calculás vos:
    // 'tributes' => [
    //     ['type' => 7, 'description' => 'Percepción IIBB Santa Fe', 'baseAmount' => 30000,
    //      'aliquot' => 3.5, 'subtotal' => 1050],
    // ],

    // Envío del PDF por correo, en segundo plano. Poné una dirección tuya: se manda de verdad.
    // 'emails' => [
    //     ['email' => 'vos@example.com', 'name' => 'Pedro López', 'comments' => '¡Gracias!', 'pdfVersions' => [1]],
    // ],

    // Decimales de cantidad y precio unitario: 2 (defecto), 4 o 6. Los importes se redondean siempre
    // a 2, que es lo que exige ARCA:
    // 'settings' => ['quantityPrecision' => 4, 'pricePrecision' => 4],

    // 'receiverTaxConditionId' => 5,  // pisa customer.taxType. Normalmente no hace falta
];

$ch = curl_init("{$baseUrl}/{$idEmpresa}/invoices");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$apiKey}",
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($comprobante, JSON_UNESCAPED_UNICODE),
]);

$respuesta = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errorRed = curl_error($ch);
curl_close($ch);

if ($respuesta === false) {
    fwrite(STDERR, "No se pudo conectar con la API: {$errorRed}\n");
    exit(1);
}

// La API contesta siempre el mismo sobre: {"ok": true, "result": {...}} o {"ok": false, "error", "code"}.
$cuerpo = json_decode($respuesta, true);

if (empty($cuerpo['ok'])) {
    fwrite(STDERR, "ERROR {$status} · " . ($cuerpo['code'] ?? 'sin código') . "\n");
    fwrite(STDERR, ($cuerpo['error'] ?? $respuesta) . "\n");
    // `details` es opcional y SU FORMA LA DETERMINA `code`: una lista de rechazos en un ARCA_ERROR,
    // un objeto con los campos que fallaron en un VALIDATION_ERROR. Por eso se mira el código
    // primero. Deducirla de la forma —"¿es una lista?"— confunde un pedido mal armado con un
    // rechazo fiscal, que se corrigen de maneras muy distintas.
    $detalles = $cuerpo['details'] ?? null;

    if (($cuerpo['code'] ?? null) === 'ARCA_ERROR' && $detalles) {
        foreach ($detalles as $detalle) {
            fwrite(STDERR, "  ({$detalle['code']}) {$detalle['msg']}\n");
        }
    } elseif (($cuerpo['code'] ?? null) === 'VALIDATION_ERROR' && $detalles) {
        // `field` es la ruta del dato dentro del JSON que mandaste: `items.0.price`.
        foreach ($detalles['errors'] as $campo) {
            fwrite(STDERR, '  ' . ($campo['field'] ?? '(cuerpo)') . ": {$campo['message']}\n");
        }
    } elseif ($detalles) {
        fwrite(STDERR, json_encode($detalles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }
    exit(1);
}

$factura = $cuerpo['result'];
echo "Comprobante emitido\n";
echo "   CAE:     {$factura['cae']}\n";
echo "   Número:  {$factura['voucherNumber']}\n";
echo "   Total:   $ {$factura['totalAmount']}\n";
echo "   ID:      {$factura['id']}\n";
// `quota` viaja al lado de `result`, no adentro: es el cupo de la empresa, no del comprobante.
if (!empty($cuerpo['quota'])) {
    echo "   Cupo:    {$cuerpo['quota']['remaining']} restantes del período\n";
}

echo "\nEso es la API entera: un POST con este JSON. El resto de los ejemplos usa las clases de\n";
echo "src/, que agregan reintentos, errores tipados y las tablas de códigos.\n";
