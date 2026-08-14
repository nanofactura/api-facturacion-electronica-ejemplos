"""05 · Servicios con período

Cuando el concepto es SERVICIOS o PRODUCTOS_Y_SERVICIOS, ARCA exige LAS TRES fechas del período:
inicio, fin y vencimiento del pago. Si falta alguna, la API responde 400 antes de llamar a ARCA y
te dice cuál.

Es el caso típico de un abono mensual o de una consultoría facturada por período.

    python examples/05_servicios_con_periodo.py
"""

import calendar
from datetime import date, timedelta

from _bootstrap import hoy, punto_de_venta

from nanofactura import NanoFactura
from nanofactura.constants import (
    Concepto,
    CondicionIva,
    Provincia,
    TipoComprobante,
    TipoDocumento,
)

nanofactura = NanoFactura()

# Todo el mes en curso, con vencimiento a diez días del cierre.
hoy_fecha = date.today()
ultimo_dia = calendar.monthrange(hoy_fecha.year, hoy_fecha.month)[1]

desde = hoy_fecha.replace(day=1)
hasta = hoy_fecha.replace(day=ultimo_dia)
vencimiento = hasta + timedelta(days=10)

comprobante = nanofactura.emitir_comprobante(
    {
        "posNumber": punto_de_venta(),
        "voucherType": TipoComprobante.FACTURA_C,
        "concept": Concepto.SERVICIOS,
        "voucherDate": hoy(),
        "serviceFrom": desde.strftime("%Y%m%d"),  # FchServDesde
        "serviceTo": hasta.strftime("%Y%m%d"),  # FchServHasta
        "serviceExpiration": vencimiento.strftime("%Y%m%d"),  # FchVtoPago
        "customer": {
            "docType": TipoDocumento.CUIT,
            "docNumber": "30123456789",
            "name": "ESTUDIO CONTABLE NORTE S.R.L.",
            "address": "Av. Colón 780, Córdoba",
            "stateId": Provincia.CORDOBA,
            "taxType": CondicionIva.RESPONSABLE_INSCRIPTO,
        },
        "items": [
            {
                "code": "ABONO-SOP",
                "description": f"Abono de soporte técnico — período {desde:%m/%Y}",
                "quantity": 1,
                "price": 250000,
            }
        ],
        # El envío sale en segundo plano, sin bloquear la respuesta. No hace falta que bajes el
        # PDF para reenviarlo vos. Descomentalo poniendo una dirección tuya — el correo se manda
        # de verdad, y la API rechaza los dominios de ejemplo como el de acá abajo.
        # "emails": [
        #     {
        #         "email": "vos@example.com",
        #         "name": "Administración",
        #         "comments": "Adjuntamos la factura del período. Ante cualquier duda, quedamos a disposición.",
        #         "pdfVersions": [1],
        #     }
        # ],
        "externalReference": f"ABONO-{desde:%Y%m}",
    }
)

print("✅ Factura de servicios emitida")
print(f"   CAE:      {comprobante['cae']}")
print(f"   Período:  {desde:%Y%m%d} → {hasta:%Y%m%d}")
print(f"   Vence:    {vencimiento:%Y%m%d}")
print(f"   Total:    $ {comprobante['totalAmount']}")
print("\nDescomentá `emails` con una dirección tuya y el envío sale con la emisión, en segundo plano.")
