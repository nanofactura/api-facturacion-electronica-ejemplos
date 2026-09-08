"""04 · Factura B — consumidor final

Un responsable inscripto le factura a un consumidor final. Se calcula el IVA pero no se imprime
discriminado.

Lo que muestra este ejemplo es el manejo del umbral de identificación: por debajo de cierto importe
podés facturar sin identificar al comprador; por encima, ARCA lo exige y rechaza con el código
10015. Ese umbral lo actualiza ARCA por resolución, así que NO se hardcodea: se maneja el rechazo.

    python examples/04_factura_b_consumidor_final.py
"""

from _bootstrap import hoy, punto_de_venta

from nanofactura import NanoFactura, NanoFacturaError
from nanofactura.constants import (
    PROVINCIA_NO_INFORMADA,
    AlicuotaIva,
    Concepto,
    CondicionIva,
    Provincia,
    TipoComprobante,
    TipoDocumento,
)

nanofactura = NanoFactura()

# Un consumidor final del que no tenemos los datos.
cliente_anonimo = {
    "docType": TipoDocumento.SIN_IDENTIFICAR,
    "docNumber": "0",
    "name": "Consumidor Final",
    "taxType": CondicionIva.CONSUMIDOR_FINAL,
    "stateId": PROVINCIA_NO_INFORMADA,
}

# El mismo cliente, ya identificado.
cliente_identificado = {
    "docType": TipoDocumento.DNI,
    "docNumber": "28444555",
    "name": "Ana Gómez",
    "address": "Belgrano 450, Córdoba",
    "taxType": CondicionIva.CONSUMIDOR_FINAL,
    "stateId": Provincia.CORDOBA,
}

venta = {
    "posNumber": punto_de_venta(),
    "voucherType": TipoComprobante.FACTURA_B,
    "concept": Concepto.PRODUCTOS,
    "voucherDate": hoy(),
    # El precio del ítem es el que ve el cliente: $1.350.000 finales, IVA adentro. Es el valor por
    # defecto de la API y en una Factura B es casi siempre el que corresponde, porque el comprobante
    # no discrimina IVA y el receptor paga ese número. Con False, la API lo tomaría como neto y la
    # factura saldría $1.633.500.
    "priceIncludesVat": True,
    "items": [
        {
            "description": 'Notebook 14" 16GB',
            "quantity": 1,
            "price": 1350000,
            "taxType": AlicuotaIva.VEINTIUNO,
            # Lo que le falta a un ítem. Ninguno es obligatorio:
            # "code": "NB-14-16",            # tu código interno del producto
            # "unitType": Unidad.UNIDADES,   # unidad de medida; 7 (unidades) por defecto
            # "discount": 10,                # 10% con discountType "percentage", o $10 con "amount"
            # "discountType": "percentage",  # "percentage" (defecto) o "amount"
        }
    ],
    # Envío por email al emitir: sale en segundo plano, sin bloquear la respuesta. Descomentalo
    # poniendo una dirección tuya — el correo se manda de verdad.
    # "emails": [
    #     {
    #         "email": "vos@example.com",
    #         "name": "Ana Gómez",
    #         "comments": "Adjuntamos tu comprobante. ¡Gracias por tu compra!",
    #         "pdfVersions": [1],  # 1 original · 2 duplicado · 3 triplicado
    #     }
    # ],
    "externalReference": "TICKET-88213",
    # ── Todo lo demás que acepta la API ──────────────────────────────────────
    #
    # Ninguno hace falta para emitir. Los que usan constantes que este ejemplo todavía no importa
    # (Moneda, Tributo, Unidad) piden agregarlas al `from nanofactura.constants import` de arriba.
    # Referencia: https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante
    #
    # "idempotency": "TICKET-88213",         # tu clave única. Si no la mandás, el cliente genera un UUID
    # "legend": "¡Gracias por su compra!",   # leyenda impresa en el comprobante. Máx. 200
    # "comments": "Vendido en caja 3",       # nota interna: se guarda, NO se imprime. Máx. 512
    # "additionalData": {"cajero": "ML-22", "sucursal": "centro"},  # objeto libre que viaja con el comprobante
    # "receiverTaxConditionId": CondicionIva.CONSUMIDOR_FINAL,      # pisa customer.taxType; casi nunca hace falta
    #
    # Decimales de cantidad y precio unitario: 2 (defecto), 4 o 6. Los importes se redondean
    # siempre a 2, que es lo que exige ARCA:
    # "settings": {"quantityPrecision": 4, "pricePrecision": 4},
    #
    # Por defecto pesos a 1. Con moneda extranjera, la cotización del día la ponés vos:
    # "currency": {"id": Moneda.DOLAR, "quotation": 1150},
    # "cancelsInSameForeignCurrency": "S",   # "S" o "N", sólo con moneda extranjera
    #
    # Con concept SERVICIOS o PRODUCTOS_Y_SERVICIOS, las tres fechas son OBLIGATORIAS (ejemplo 05):
    # "serviceFrom": "20260801",
    # "serviceTo": "20260831",
    # "serviceExpiration": "20260910",
    #
    # Impuestos que NO son IVA: IIBB, tasas municipales, percepciones. Acá el importe lo calculás vos:
    # "tributes": [
    #     {"type": Tributo.PERCEPCION_IIBB, "description": "Percepción IIBB Córdoba",
    #      "baseAmount": 1115702.48, "aliquot": 3.5, "subtotal": 39049.59},
    # ],
    #
    # Sólo en notas de crédito y débito, apuntando al comprobante que modifican (ejemplo 06):
    # "associatedVouchers": [
    #     {"voucherType": TipoComprobante.FACTURA_B, "posNumber": 1, "voucherNumber": 45},
    # ],
}

try:
    comprobante = nanofactura.emitir_comprobante({**venta, "customer": cliente_anonimo})
    print(f"✅ Emitida sin identificar al comprador · CAE {comprobante['cae']}")
except NanoFacturaError as error:
    # 10015: el importe supera el máximo que ARCA permite sin identificar al comprador.
    requiere_identificacion = error.tiene_codigo_arca(10015)

    if not requiere_identificacion:
        raise

    print("⚠️ ARCA pide identificar al comprador para este importe.")
    print(f"  {error.message}")
    print("\n  Reintentando con los datos del cliente…\n")

    # Importante: es una emisión NUEVA, con una clave de idempotencia nueva. La anterior fue
    # rechazada por ARCA, así que no existe ningún comprobante que recuperar.
    comprobante = nanofactura.emitir_comprobante({**venta, "customer": cliente_identificado})
    print(f"✅ Emitida con el comprador identificado · CAE {comprobante['cae']}")

print("\nEn producción, la forma correcta es al revés: pedile el documento al cliente")
print('siempre que puedas, y tratá el 10015 como "faltan datos", no como un error.')
