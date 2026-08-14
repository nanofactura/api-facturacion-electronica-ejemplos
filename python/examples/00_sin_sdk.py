"""00 · La API cruda — sin cliente y sin dependencias

Esto es TODO lo que hace falta para emitir una factura electrónica: un POST con un JSON y la API key
en el header. El paquete `nanofactura/` es comodidad —constantes con nombre, reintentos, errores
tipados—, no un requisito. Si vas a escribir tu propia integración, empezá por acá.

Sólo biblioteca estándar: ni `requests` ni `python-dotenv`.

Están activos sólo los campos OBLIGATORIOS. Todo lo demás va comentado, con un valor de ejemplo y
los códigos de ARCA al lado, para que veas el pedido completo sin ir a la documentación.

    python examples/00_sin_sdk.py
"""

import json
import os
import sys
import urllib.error
import urllib.request
from datetime import date
from pathlib import Path

# En Windows la consola usa una code page heredada (cp1252) y los acentos salen ilegibles. La
# salida es texto para leer, así que se fuerza UTF-8.
for _flujo in (sys.stdout, sys.stderr):
    if hasattr(_flujo, "reconfigure"):
        _flujo.reconfigure(encoding="utf-8")

# Lectura mínima del .env de la raíz, sólo para que el ejemplo corra sin instalar nada. En tu
# sistema esto son variables de entorno, o python-dotenv.
_env = Path(__file__).resolve().parent.parent.parent / ".env"
if _env.exists():
    for _linea in _env.read_text(encoding="utf-8").splitlines():
        _linea = _linea.strip()
        if _linea and not _linea.startswith("#") and "=" in _linea:
            _clave, _valor = _linea.split("=", 1)
            os.environ.setdefault(_clave.strip(), _valor.strip())

API_KEY = os.environ.get("NANOFACTURA_API_KEY")
ID_EMPRESA = os.environ.get("NANOFACTURA_ID_EMPRESA")
BASE_URL = os.environ.get("NANOFACTURA_BASE_URL", "https://nanofactura.com/api")
POS_NUMBER = int(os.environ.get("NANOFACTURA_POS_NUMBER", "1"))

if not API_KEY or not ID_EMPRESA:
    print("Faltan NANOFACTURA_API_KEY y/o NANOFACTURA_ID_EMPRESA. Copiá .env.example a .env y completalo.")
    sys.exit(1)

comprobante = {
    # ── Obligatorios ─────────────────────────────────────────────────────────
    #
    # Tu clave única de este comprobante: si el pedido se corta y lo reintentás con la misma clave,
    # la API devuelve el comprobante que ya emitió en vez de duplicarlo. En tu sistema sale de la
    # venta y se guarda ANTES de emitir. Máx. 128 caracteres: letras, números, - y _.
    "idempotency": f"sin-sdk-{date.today():%Y%m%d}-{os.getpid()}",
    "posNumber": POS_NUMBER,
    "voucherType": 11,  # 11 Factura C · 1 Factura A · 6 Factura B · 13/3/8 notas de crédito
    "concept": 1,  # 1 productos · 2 servicios · 3 ambos
    "voucherDate": date.today().strftime("%Y%m%d"),  # YYYYMMDD
    "customer": {
        "docType": 96,  # 80 CUIT · 86 CUIL · 96 DNI · 99 sin identificar
        "docNumber": "35888999",  # sin puntos ni guiones
        "name": "Pedro López",
        # Condición del RECEPTOR frente al IVA: 1 RI · 4 exento · 5 consumidor final · 6 monotributo
        "taxType": 5,
        # "address": "San Martín 1200, Rosario",  # se imprime en el comprobante
        # "stateId": 12,                          # provincia (12 = Santa Fe). Mandala siempre que la
        #                                         # sepas: es el único dato agrupable para reportes
        #                                         # de Ingresos Brutos
    },
    "items": [
        {
            "description": "Teclado mecánico inalámbrico",
            "quantity": 2,
            "price": 15000,  # unitario, sin descuento
            # La alícuota de IVA de la línea es obligatoria en A y B, y NO va en los comprobantes C,
            # que no discriminan IVA:
            # "taxType": 5,                  # 5 = 21% · 4 = 10,5% · 3 = 0% · 2 exento · 1 no gravado
            # "code": "PROD-001",            # tu código interno
            # "unitType": 7,                 # unidad de medida; 7 = unidades (por defecto)
            # "discount": 10,                # descuento de la línea
            # "discountType": "percentage",  # "percentage" (defecto) o "amount"
        }
    ],
    # ── Opcionales ───────────────────────────────────────────────────────────
    #
    # Referencia completa: https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante
    #
    # "externalReference": "PEDIDO-2026-00042",  # tu identificador. Es el único campo por el que
    #                                            # después podés BUSCAR el comprobante en el listado
    # "legend": "¡Gracias por su compra!",       # leyenda impresa. Máx. 200
    # "comments": "Retira por sucursal Centro",  # nota interna: se guarda, NO se imprime. Máx. 512
    # "additionalData": {"vendedor": "ML-22", "canal": "web"},  # objeto libre que viaja con el comprobante
    #
    # Con concept 2 o 3, las tres fechas del período son OBLIGATORIAS:
    # "serviceFrom": "20260801",
    # "serviceTo": "20260831",
    # "serviceExpiration": "20260910",
    #
    # Sólo en notas de crédito y débito, apuntando al comprobante que modifican:
    # "associatedVouchers": [
    #     {"voucherType": 11, "posNumber": 1, "voucherNumber": 45},  # "cuit" sólo si es de otro emisor
    # ],
    #
    # Por defecto pesos a 1. Con moneda extranjera, la cotización del día la ponés vos:
    # "currency": {"id": "DOL", "quotation": 1150},
    # "cancelsInSameForeignCurrency": "S",   # "S" o "N", sólo con moneda extranjera
    #
    # Impuestos que NO son IVA (IIBB, tasas, percepciones). Acá el importe lo calculás vos:
    # "tributes": [
    #     {"type": 7, "description": "Percepción IIBB Santa Fe", "baseAmount": 30000,
    #      "aliquot": 3.5, "subtotal": 1050},
    # ],
    #
    # Envío del PDF por correo, en segundo plano. Poné una dirección tuya: se manda de verdad.
    # "emails": [
    #     {"email": "vos@example.com", "name": "Pedro López", "comments": "¡Gracias!", "pdfVersions": [1]},
    # ],
    #
    # Decimales de cantidad y precio unitario: 2 (defecto), 4 o 6. Los importes se redondean siempre
    # a 2, que es lo que exige ARCA:
    # "settings": {"quantityPrecision": 4, "pricePrecision": 4},
    #
    # "receiverTaxConditionId": 5,  # pisa customer.taxType. Normalmente no hace falta
}

pedido = urllib.request.Request(
    f"{BASE_URL}/{ID_EMPRESA}/invoices",
    method="POST",
    data=json.dumps(comprobante).encode("utf-8"),
    headers={
        "Authorization": f"Bearer {API_KEY}",
        "Content-Type": "application/json",
    },
)

# La API contesta siempre el mismo sobre: {"ok": true, "result": {...}} o {"ok": false, "error", "code"}.
# urllib levanta HTTPError con los 4xx/5xx, y el cuerpo del error se lee del propio objeto.
try:
    with urllib.request.urlopen(pedido, timeout=30) as respuesta:
        cuerpo = json.load(respuesta)
except urllib.error.HTTPError as e:
    cuerpo = json.loads(e.read().decode("utf-8"))
    print(f"ERROR {e.code} · {cuerpo.get('code', 'sin código')}")
    print(cuerpo.get("error"))
    # `details` trae los rechazos de ARCA con su código, cuando el error viene de ellos.
    if cuerpo.get("details"):
        print(json.dumps(cuerpo["details"], indent=2, ensure_ascii=False))
    sys.exit(1)

factura = cuerpo["result"]
print("Comprobante emitido")
print(f"   CAE:     {factura['cae']}")
print(f"   Número:  {factura['voucherNumber']}")
print(f"   Total:   $ {factura['totalAmount']}")
print(f"   ID:      {factura['id']}")
# `quota` viaja al lado de `result`, no adentro: es el cupo de la empresa, no del comprobante.
if cuerpo.get("quota"):
    print(f"   Cupo:    {cuerpo['quota']['remaining']} restantes del período")

print("\nEso es la API entera: un POST con este JSON. El resto de los ejemplos usa el paquete")
print("nanofactura/, que agrega reintentos, errores tipados y las tablas de códigos.")
