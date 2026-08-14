"""06 · Nota de crédito

Un comprobante autorizado no se puede borrar ni modificar: para anularlo o corregirlo se emite una
nota de crédito que lo compensa.

Dos reglas que no se negocian:
  1. Misma letra que el comprobante original (una NC "C" anula una Factura C).
  2. Siempre con `associatedVouchers` apuntando al comprobante que modifica.

    python examples/06_nota_de_credito.py
"""

from _bootstrap import formatear_numero, hoy, punto_de_venta

from nanofactura import NanoFactura
from nanofactura.constants import (
    Concepto,
    CondicionIva,
    Provincia,
    TipoComprobante,
    TipoDocumento,
)

nanofactura = NanoFactura()
pos_number = punto_de_venta()

cliente = {
    "docType": TipoDocumento.DNI,
    "docNumber": "35888999",
    "name": "Pedro López",
    "stateId": Provincia.SANTA_FE,
    "taxType": CondicionIva.CONSUMIDOR_FINAL,
}

# ── 1. Emitimos una factura para después anularla ────────────────────────────

factura = nanofactura.emitir_comprobante(
    {
        "posNumber": pos_number,
        "voucherType": TipoComprobante.FACTURA_C,
        "concept": Concepto.PRODUCTOS,
        "voucherDate": hoy(),
        "customer": cliente,
        "items": [
            {
                "description": "Auriculares bluetooth",
                "quantity": 1,
                "price": 45000,
            }
        ],
        "externalReference": "PEDIDO-2026-00099",
    }
)

print(f"Factura emitida  · Nº {factura['voucherNumber']} · $ {factura['totalAmount']}")

# ── 2. El cliente devuelve el producto: emitimos la nota de crédito ──────────

tipo_nota_credito = TipoComprobante.NOTA_CREDITO_C

# La letra tiene que coincidir. Vale la pena verificarlo en código: es un error fácil de cometer al
# parametrizar el tipo de comprobante, y ARCA lo rechaza recién al emitir.
if tipo_nota_credito.letra != TipoComprobante.FACTURA_C.letra:
    raise ValueError("La nota de crédito tiene que ser de la misma letra que el comprobante que anula.")

numero_original = formatear_numero(pos_number, factura["voucherNumber"])

nota_credito = nanofactura.emitir_comprobante(
    {
        "posNumber": pos_number,
        "voucherType": tipo_nota_credito,
        "concept": Concepto.PRODUCTOS,
        "voucherDate": hoy(),
        # El vínculo con el comprobante original. Sin esto, ARCA rechaza.
        "associatedVouchers": [
            {
                "voucherType": TipoComprobante.FACTURA_C,
                "posNumber": pos_number,
                "voucherNumber": factura["voucherNumber"],
            }
        ],
        # Mismo receptor que la factura original.
        "customer": cliente,
        # Por el total, porque anulamos toda la factura. Para una devolución parcial irían sólo las
        # líneas devueltas, con sus cantidades.
        "items": [
            {
                "description": f"Anulación de Factura C {numero_original} — devolución",
                "quantity": 1,
                "price": 45000,
            }
        ],
        # La nota de crédito también le llega al cliente: es el comprobante que respalda la
        # devolución. Descomentalo poniendo una dirección tuya — el correo se manda de verdad.
        # "emails": [
        #     {
        #         "email": "vos@example.com",
        #         "name": "Pedro López",
        #         "comments": "Adjuntamos la nota de crédito por la devolución.",
        #         "pdfVersions": [1],  # 1 original · 2 duplicado · 3 triplicado
        #     }
        # ],
        "externalReference": "PEDIDO-2026-00099-NC",
    }
)

print(f"Nota de crédito  · Nº {nota_credito['voucherNumber']} · $ {nota_credito['totalAmount']}")
print(f"\n✅ Factura {numero_original} anulada.")
print("   Los dos comprobantes quedan en tu historial: así lo entiende ARCA.")
