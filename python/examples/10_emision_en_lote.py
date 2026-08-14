"""10 · Emisión en lote

ARCA exige numeración correlativa por punto de venta y tipo de comprobante, así que la API emite de
a un comprobante por vez para cada combinación.

⚠️ SECUENCIÁ, NO PARALELICES. Lanzar en paralelo comprobantes del mismo punto de venta y tipo no
acelera nada —igual se serializan— y sólo produce errores 429. Si tenés que emitir mucho y rápido,
la forma de paralelizar es POR PUNTO DE VENTA: cada uno lleva su propia numeración.

Lo otro que muestra este ejemplo es la persistencia de la clave de idempotencia: se genera y se
guarda ANTES de emitir. Si el proceso se cae a la mitad, al reanudarlo se reusa la misma clave y
los comprobantes ya emitidos vuelven con `idempotencyHit: True` en vez de duplicarse.

    python examples/10_emision_en_lote.py
"""

import uuid

from _bootstrap import hoy, punto_de_venta

from nanofactura import NanoFactura, NanoFacturaError
from nanofactura.constants import (
    Concepto,
    CondicionIva,
    Provincia,
    TipoComprobante,
    TipoDocumento,
)

nanofactura = NanoFactura()
pos_number = punto_de_venta()

# En tu sistema esto sale de la base de datos. `idempotency` es una columna de la venta, no algo que
# se calcule al vuelo: se escribe cuando la venta se marca para facturar y no cambia nunca más.
ventas = [
    {"id": "V-1001", "cliente": "Pedro López", "doc": "35888999", "provincia": Provincia.SANTA_FE, "total": 30000, "idempotency": None},
    {"id": "V-1002", "cliente": "Ana Gómez", "doc": "28444555", "provincia": Provincia.CORDOBA, "total": 47500, "idempotency": None},
    {"id": "V-1003", "cliente": "Luis Ruiz", "doc": "31222888", "provincia": Provincia.BUENOS_AIRES, "total": 12300, "idempotency": None},
]

emitidos: list[dict] = []
fallidos: list[str] = []

for venta in ventas:
    # 1. La clave se genera y se GUARDA antes de emitir. En tu sistema, este paso es un UPDATE.
    if venta["idempotency"] is None:
        venta["idempotency"] = str(uuid.uuid4())
        # db.ventas.update(venta["id"], idempotency=venta["idempotency"])

    try:
        comprobante = nanofactura.emitir_comprobante(
            {
                "idempotency": venta["idempotency"],
                "posNumber": pos_number,
                "voucherType": TipoComprobante.FACTURA_C,
                "concept": Concepto.PRODUCTOS,
                "voucherDate": hoy(),
                "customer": {
                    "docType": TipoDocumento.DNI,
                    "docNumber": venta["doc"],
                    "name": venta["cliente"],
                    "stateId": venta["provincia"],
                    "taxType": CondicionIva.CONSUMIDOR_FINAL,
                },
                "items": [
                    {
                        "description": f"Venta {venta['id']}",
                        "quantity": 1,
                        "price": venta["total"],
                    }
                ],
                "externalReference": venta["id"],
            }
        )

        # 2. Guardá el resultado INMEDIATAMENTE, no al final del lote. Si el proceso muere en la
        #    venta 40 de 100, las 39 anteriores tienen que estar registradas.
        # db.ventas.update(venta["id"], cae=comprobante["cae"], invoice_id=comprobante["id"])

        emitidos.append(comprobante)

        reusado = " (ya existía)" if comprobante.get("idempotencyHit") else ""
        print(f"✅ {venta['id']} → CAE {comprobante['cae']}{reusado}")

    except NanoFacturaError as error:
        fallidos.append(venta["id"])
        print(f"❌ {venta['id']} → {error.code or 'ERROR'}: {error.message}")

        # 3. Cortá al primer error PERMANENTE: si el primero está mal armado, probablemente lo
        #    estén todos, y seguir sólo suma rechazos. Los transitorios ya los reintentó el cliente.
        if not error.is_retryable and not error.is_arca_rejection:
            print("\n⛔ Error de configuración: se corta el lote.")
            break

print("\n─────────────────────────────────")
print(f"Emitidos: {len(emitidos)} · Fallidos: {len(fallidos)}")

# La cuota del período viene en cada emisión: sirve para frenar antes de chocar.
if emitidos and emitidos[-1].get("quota"):
    quota = emitidos[-1]["quota"]
    print(f"Cupo restante del período: {quota['remaining']} (hasta {quota['periodEnd']})")

# ── Paralelizar por punto de venta ───────────────────────────────────────────
#
# Distintos puntos de venta SÍ pueden emitir a la vez, porque cada uno lleva su propia numeración.
# El patrón correcto es agrupar y correr un flujo secuencial por grupo:
#
#   from concurrent.futures import ThreadPoolExecutor
#   from itertools import groupby
#
#   ventas.sort(key=lambda v: v["posNumber"])
#   grupos = [list(g) for _, g in groupby(ventas, key=lambda v: v["posNumber"])]
#
#   with ThreadPoolExecutor() as pool:
#       pool.map(emitir_grupo_secuencialmente, grupos)   # secuencial DENTRO de cada grupo
