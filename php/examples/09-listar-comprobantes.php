<?php

declare(strict_types=1);

/**
 * 09 · Listar comprobantes
 *
 * El listado devuelve sólo los comprobantes del entorno de tu API key: una clave TEST_ nunca ve los
 * de producción, ni al revés.
 *
 * Los filtros son estilo Django: `campo=valor` o `campo__operador=valor`, y todos se combinan con
 * AND. ⚠️ Los campos desconocidos se ignoran EN SILENCIO: un filtro mal escrito no da error, sólo
 * devuelve más resultados de los que esperabas.
 *
 *   php examples/09-listar-comprobantes.php
 */

require __DIR__ . '/bootstrap.php';

use NanoFactura\Client;
use NanoFactura\Constants\TipoComprobante;

$nanofactura = new Client();

// ── Los últimos 10 ───────────────────────────────────────────────────────────

['comprobantes' => $comprobantes, 'total' => $total] = $nanofactura->listarComprobantes([
    'limit' => 10,
    'ordering' => '-created',
]);

echo 'Últimos ' . count($comprobantes) . " de {$total} comprobantes:\n\n";
foreach ($comprobantes as $c) {
    printf(
        "  %s  %s  $ %12s  %-11s  %s\n",
        formatearNumero($c['posNumber'], $c['voucherNumber']),
        $c['voucherDate'],
        $c['totalAmount'],
        $c['docNumber'],
        $c['source']
    );
}

// ── Uno por id ───────────────────────────────────────────────────────────────
//
// Con el `id` que devolvió la emisión —el que guardaste junto a tu venta— lo pedís directo, sin
// filtrar el listado. Trae el mismo objeto que una fila del listado; si no existe, o es del otro
// entorno, la API responde 404 NOT_FOUND.

if ($comprobantes !== []) {
    $comprobante = $nanofactura->obtenerComprobante($comprobantes[0]['id']);
    printf(
        "\nEl último, por id: %d · CAE %s · PDF %s\n",
        $comprobante['id'],
        $comprobante['cae'],
        $comprobante['hasPdf'] ? 'listo' : 'generándose'
    );
}

// ── Facturación de un mes ────────────────────────────────────────────────────
//
// `date` es la fecha FISCAL (la que declaraste al emitir). `created` es cuándo se emitió realmente.
// Para reportes fiscales usá `date`; para conciliar con tu sistema, `created`.

$mes = $nanofactura->listarComprobantes([
    'date__gte' => '20260801',
    'date__lte' => '20260831',
    'ordering' => 'date',
    'limit' => 200, // el máximo por página
]);

// En el listado los importes viajan como string, para no perder precisión al serializarlos.
// PHP los castea solo al sumar, pero conviene ser explícito: en JavaScript o Python no lo hace.
$facturado = array_sum(array_map('floatval', array_column($mes['comprobantes'], 'totalAmount')));
echo "\nAgosto 2026: {$mes['total']} comprobantes · $ " . number_format($facturado, 2, ',', '.') . "\n";

// ── Buscar por tu propio identificador ───────────────────────────────────────
//
// Para esto sirve `externalReference`: encontrar el comprobante desde tu sistema sin llevar una
// tabla de equivalencias.

$delPedido = $nanofactura->listarComprobantes(['externalReference' => 'PEDIDO-2026-00042']);
echo "\nComprobantes del pedido PEDIDO-2026-00042: " . count($delPedido['comprobantes']) . "\n";

// ── Sólo notas de crédito ────────────────────────────────────────────────────

$notasCredito = $nanofactura->listarComprobantes([
    'type__in' => implode(',', [
        TipoComprobante::NOTA_CREDITO_A,
        TipoComprobante::NOTA_CREDITO_B,
        TipoComprobante::NOTA_CREDITO_C,
    ]),
    'limit' => 1,
]);
echo "Notas de crédito emitidas: {$notasCredito['total']}\n";

// ── Paginar todo ─────────────────────────────────────────────────────────────
//
// `total` es cuántos matchean el filtro, no cuántos vinieron en la página: es lo que permite
// paginar sin depender de que la página vuelva llena.

function todosLosComprobantes(Client $nanofactura, array $filtros = []): \Generator
{
    $limit = 200;
    $offset = 0;
    $total = PHP_INT_MAX;

    while ($offset < $total) {
        $pagina = $nanofactura->listarComprobantes($filtros + ['limit' => $limit, 'offset' => $offset, 'ordering' => 'id']);
        $total = $pagina['total'];

        if ($pagina['comprobantes'] === []) {
            break;
        }

        yield from $pagina['comprobantes'];
        $offset += $limit;
    }
}

$contados = 0;
foreach (todosLosComprobantes($nanofactura, ['date__gte' => '20260101']) as $comprobante) {
    $contados++;
}
echo "\nRecorridos {$contados} comprobantes de 2026 paginando de a 200.\n";
