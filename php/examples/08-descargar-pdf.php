<?php

declare(strict_types=1);

/**
 * 08 · Descargar el PDF
 *
 * El PDF se genera de forma ASINCRÓNICA: la emisión responde con el CAE de inmediato y el archivo
 * queda listo unos segundos después.
 *
 * ⚠️ No lo guardes en tu sistema luego de generar. Pedilo en el momento en que alguien lo abre, lo imprime o lo
 * adjunta, y descartalo. Guardar el PDF te obliga a resolver almacenamiento, backups, permisos y
 * retención para un archivo que la API te devuelve siempre, tantas veces como haga falta.
 *
 * Lo que sí guardás de la emisión son los identificadores: id, cae, voucherNumber, posNumber y
 * voucherType. Con el `id` traés el PDF cuando lo necesites.
 *
 *   php examples/08-descargar-pdf.php <invoiceId>
 */

require __DIR__ . '/bootstrap.php';

use NanoFactura\Client;

$nanofactura = new Client();

$invoiceId = $argv[1] ?? null;
if ($invoiceId === null) {
    fwrite(STDERR, "Uso: php examples/08-descargar-pdf.php <invoiceId>\n");
    fwrite(STDERR, "El invoiceId es el campo `id` que devuelve la emisión.\n");
    exit(1);
}

// ── Caso normal: el PDF ya está listo ────────────────────────────────────────
//
// Cuando el usuario abre el comprobante, pasaron minutos u horas desde la emisión. Alcanza con
// pedirlo: descargarPdf() sin más vueltas.

$pdf = $nanofactura->descargarPdf($invoiceId, [1]);

file_put_contents("comprobante-{$invoiceId}.pdf", $pdf);
printf("✅ PDF descargado · comprobante-%s.pdf (%.1f KB)\n", $invoiceId, strlen($pdf) / 1024);

// ── Caso "recién emitido": esperar a que se genere ───────────────────────────
//
// Sólo hace falta si pedís el PDF inmediatamente después de emitir. Si todavía no está, la API
// responde 409 PDF_NOT_READY y esperarPdf() reintenta hasta que aparezca.

// $pdfConEspera = $nanofactura->esperarPdf($invoiceId, [1, 2], intentos: 10);

// ── Original, duplicado y triplicado ─────────────────────────────────────────
//
// `versions` estampa las copias pedidas: 1 original, 2 duplicado, 3 triplicado.

$pdfTriplicado = $nanofactura->descargarPdf($invoiceId, [1, 2, 3]);
file_put_contents("comprobante-{$invoiceId}-triplicado.pdf", $pdfTriplicado);
echo "✅ Con las tres copias · comprobante-{$invoiceId}-triplicado.pdf\n";

// ── Si lo que querés es que le llegue al cliente ─────────────────────────────
//
// No bajes el PDF para reenviarlo vos: agregá el destinatario y la API lo despacha en segundo
// plano, con la plantilla y las copias que corresponden. Máximo 3 por comprobante.

// $nanofactura->agregarDestinatario($invoiceId, [
//     'email' => 'cliente@example.com',
//     'name' => 'Pedro López',
//     'comments' => 'Adjuntamos tu comprobante.',
//     'pdfVersions' => [1],
// ]);

echo "\nPara que el comprobante le llegue al cliente, usá agregarDestinatario()\n";
echo "o mandá `emails` al emitir: el envío corre en segundo plano.\n";
