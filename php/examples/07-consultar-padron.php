<?php

declare(strict_types=1);

/**
 * 07 · Consultar el padrón de ARCA
 *
 * Si tenés el CUIT del cliente, ésta es la forma correcta de armar el array `customer`: te asegura
 * que la condición frente al IVA sea la real —que es lo que determina qué comprobante corresponde
 * emitir— y te trae la provincia ya codificada, sin que nadie la tipee mal.
 *
 * Guardá el resultado en tu base: no hace falta consultarlo en cada venta al mismo cliente.
 *
 * Si estás integrando en homologación, consultá el padrón con una API key de producción: la base
 * de homologación tiene datos de prueba y responde distinto. Esta consulta sólo lee, no emite nada.
 *
 *   php examples/07-consultar-padron.php [documento]
 */

require __DIR__ . '/bootstrap.php';

use NanoFactura\ApiException;
use NanoFactura\Client;
use NanoFactura\Constants\CondicionIva;
use NanoFactura\Constants\TipoComprobante;

$nanofactura = new Client();

$documento = $argv[1] ?? '30703088534';

try {
    $contribuyente = $nanofactura->consultarPadron($documento);
} catch (ApiException $e) {
    if ($e->status === 404) {
        fwrite(STDERR, "⚠️ ARCA no tiene ningún contribuyente con el documento {$documento}.
");
        exit(1);
    }
    // ARCA puede negarse a informar una clave concreta por el estado de ESE contribuyente —cancelada,
    // inactiva, con actividades fuera del nomenclador vigente—. No es un problema de configuración ni
    // cede reintentando: es lo que ARCA sabe de ese CUIT.
    if ($e->code === 'ARCA_ERROR') {
        fwrite(STDERR, "⚠️ ARCA no informa datos de {$documento}: {$e->getMessage()}
");
        exit(1);
    }
    throw $e;
}

// `cache.hit` dice si la respuesta salió de un dato ya guardado en vez de consultarse en el momento,
// y `cache.stale` si ese dato estaba vencido y se usó igual porque ARCA no respondió.
$cache = $contribuyente['cache'] ?? [];
$origen = ($cache['hit'] ?? false) ? (($cache['stale'] ?? false) ? ' · dato guardado (vencido)' : ' · dato guardado') : '';
echo "Padrón de {$documento}{$origen}
";
echo "   Nombre:    {$contribuyente['name']}\n";
echo '   Domicilio: ' . (($contribuyente['address'] ?? '') ?: '(sin domicilio cargado)') . "\n";
echo '   Provincia: ' . ($contribuyente['stateId'] ?? 'no informada') . "\n";
echo "   Condición: {$contribuyente['taxConditionName']} ({$contribuyente['taxCondition']})\n";
echo "   Documento: tipo {$contribuyente['docType']} · {$contribuyente['docNumber']}\n";

// Los campos del padrón se corresponden uno a uno con los de `customer`. `taxCondition` se usa tal
// cual como `taxType`, sin traducir.
$customer = [
    'docType' => $contribuyente['docType'],
    // El padrón devuelve el documento como número y la emisión lo espera como string: convertilo.
    'docNumber' => (string) $contribuyente['docNumber'],
    'name' => $contribuyente['name'],
    'address' => $contribuyente['address'] ?? null,
    'stateId' => $contribuyente['stateId'] ?? null, // puede venir null: es un valor válido
    'taxType' => $contribuyente['taxCondition'],
];

echo "\nArray `customer` listo para emitir:\n";
echo json_encode($customer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Siendo responsable inscripto, la condición del receptor decide la letra del comprobante.
//
// Ojo con el monotributista: le corresponde Factura A, no B. Durante años fue al revés, y es el
// error más frecuente al integrar.
$recibenFacturaA = [CondicionIva::RESPONSABLE_INSCRIPTO, CondicionIva::MONOTRIBUTO];

$tipoSugerido = in_array($customer['taxType'], $recibenFacturaA, true)
    ? TipoComprobante::FACTURA_A
    : TipoComprobante::FACTURA_B;

echo "\nSi sos responsable inscripto, a este cliente le corresponde voucherType {$tipoSugerido}.\n";
echo "Si sos monotributista o exento, siempre Factura C (11).\n";
