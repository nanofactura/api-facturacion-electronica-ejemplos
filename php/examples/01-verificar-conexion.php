<?php

declare(strict_types=1);

/**
 * 01 · Verificar la conexión
 *
 * La primera llamada que conviene hacer al integrar: valida la API key y confirma que la empresa
 * tenga certificado y puntos de venta para el entorno de esa clave.
 *
 * ⚠️ Es una verificación de PUESTA EN MARCHA y de diagnóstico, no un paso previo a facturar. Lo que
 * chequea cambia una vez cada dos años —cuando vence el certificado—, así que llamarla antes de
 * cada emisión duplica la latencia y no evita ningún error: si falta algo, la emisión lo dice.
 *
 *   php examples/01-verificar-conexion.php
 */

require __DIR__ . '/bootstrap.php';

use NanoFactura\Client;

$nanofactura = new Client();

$estado = $nanofactura->verificarConexion();

echo "✅ Conexión OK\n";
echo "   Entorno:      {$estado['environment']}\n";
echo '   Certificados: ' . ($estado['certificates'] ? 'sí' : 'NO') . "\n";
echo '   Puntos venta: ' . ($estado['pos'] ? 'sí' : 'NO') . "\n";

if (!$estado['certificates'] || !$estado['pos']) {
    echo "\n⚠️ Falta configuración para poder emitir.\n";
    echo "  https://asistentefacturaelectronica.com/ayuda/api/certificado-arca\n";
    exit(1);
}

if ($nanofactura->isTestEnvironment()) {
    echo "\nℹ Estás en HOMOLOGACIÓN: los comprobantes no tienen validez fiscal.\n";
}

// Los puntos de venta con `webOnly: true` son para Comprobantes en Línea y no sirven para la API:
// tienen `number: null` y no se pueden usar como `posNumber`.
$puntosDeVenta = $nanofactura->listarPuntosDeVenta();
$utilizables = array_filter($puntosDeVenta, static fn (array $pos): bool => !($pos['webOnly'] ?? false));

echo "\nPuntos de venta disponibles para facturar (" . count($utilizables) . "):\n";
foreach ($utilizables as $pos) {
    printf("   %04d · %s\n", $pos['number'], $pos['name'] ?? '(sin nombre)');
}

echo "\nCon esto ya podés emitir: seguí con 02-factura-c-monotributo.php.\n";
echo "No llames a verificarConexion() antes de cada comprobante: es para configurar y diagnosticar.\n";
