"""08 · Descargar el PDF

El PDF se genera de forma ASINCRÓNICA: la emisión responde con el CAE de inmediato y el archivo
queda listo unos segundos después.

⚠️ No lo guardes en tu sistema luego de generar. Pedilo en el momento en que alguien lo abre, lo imprime o lo
adjunta, y descartalo. Guardar el PDF te obliga a resolver almacenamiento, backups, permisos y
retención para un archivo que la API te devuelve siempre, tantas veces como haga falta.

Lo que sí guardás de la emisión son los identificadores: id, cae, voucherNumber, posNumber y
voucherType. Con el `id` traés el PDF cuando lo necesites.

    python examples/08_descargar_pdf.py <invoice_id>
"""

import sys
from pathlib import Path

from _bootstrap import *  # noqa: F401,F403  (ruta del paquete y .env)

from nanofactura import NanoFactura

nanofactura = NanoFactura()

if len(sys.argv) < 2:
    print("Uso: python examples/08_descargar_pdf.py <invoice_id>", file=sys.stderr)
    print("El invoice_id es el campo `id` que devuelve la emisión.", file=sys.stderr)
    sys.exit(1)

invoice_id = sys.argv[1]

# ── Caso normal: el PDF ya está listo ────────────────────────────────────────
#
# Cuando el usuario abre el comprobante, pasaron minutos u horas desde la emisión. Alcanza con
# pedirlo: descargar_pdf() sin más vueltas.

pdf = nanofactura.descargar_pdf(invoice_id, [1])

Path(f"comprobante-{invoice_id}.pdf").write_bytes(pdf)
print(f"✅ PDF descargado · comprobante-{invoice_id}.pdf ({len(pdf) / 1024:.1f} KB)")

# ── Caso "recién emitido": esperar a que se genere ───────────────────────────
#
# Sólo hace falta si pedís el PDF inmediatamente después de emitir. Si todavía no está, la API
# responde 409 PDF_NOT_READY y esperar_pdf() reintenta hasta que aparezca.

# pdf_con_espera = nanofactura.esperar_pdf(invoice_id, [1, 2], intentos=10)

# ── Original, duplicado y triplicado ─────────────────────────────────────────
#
# `versions` estampa las copias pedidas: 1 original, 2 duplicado, 3 triplicado.

pdf_triplicado = nanofactura.descargar_pdf(invoice_id, [1, 2, 3])
Path(f"comprobante-{invoice_id}-triplicado.pdf").write_bytes(pdf_triplicado)
print(f"✅ Con las tres copias · comprobante-{invoice_id}-triplicado.pdf")

# ── Si lo que querés es que le llegue al cliente ─────────────────────────────
#
# No bajes el PDF para reenviarlo vos: agregá el destinatario y la API lo despacha en segundo
# plano, con la plantilla y las copias que corresponden. Máximo 3 por comprobante.

# nanofactura.agregar_destinatario(invoice_id, {
#     "email": "cliente@example.com",
#     "name": "Pedro López",
#     "comments": "Adjuntamos tu comprobante.",
#     "pdfVersions": [1],
# })

print("\nPara que el comprobante le llegue al cliente, usá agregar_destinatario()")
print("o mandá `emails` al emitir: el envío corre en segundo plano.")
