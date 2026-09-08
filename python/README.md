# Facturación electrónica ARCA en Python

Cliente y ejemplos para emitir **facturas electrónicas de ARCA (ex AFIP)** desde Python con la API de NanoFactura.

Una sola dependencia: **`requests`**.

📖 [Documentación completa](https://asistentefacturaelectronica.com/ayuda/api)

## Requisitos

- **Python 3.9 o superior**
- Una API key de NanoFactura → [cómo obtenerla](https://asistentefacturaelectronica.com/ayuda/api/api-keys)

## Instalación

```bash
cd ..                 # a la raíz del repo
cp .env.example .env  # y completá tu API key
cd python

python -m venv .venv
source .venv/bin/activate      # en Windows: .venv\Scripts\activate
pip install -r requirements.txt
```

## Ejecutar los ejemplos

```bash
python examples/00_sin_sdk.py                          # la API cruda, sin el paquete
python examples/01_verificar_conexion.py               # ← empezá por acá
python examples/02_factura_c_monotributo.py
python examples/03_factura_a_responsable_inscripto.py
python examples/04_factura_b_consumidor_final.py
python examples/05_servicios_con_periodo.py
python examples/06_nota_de_credito.py
python examples/07_consultar_padron.py 30703088534
python examples/08_descargar_pdf.py 1485
python examples/09_listar_comprobantes.py
python examples/10_emision_en_lote.py
```

## Uso en tu proyecto

Copiá la carpeta `nanofactura/` a tu proyecto, o usá el código como referencia.

```python
from nanofactura import NanoFactura
from nanofactura.constants import (
    Concepto, CondicionIva, Provincia, TipoComprobante, TipoDocumento,
)

# Lee NANOFACTURA_API_KEY, NANOFACTURA_ID_EMPRESA y NANOFACTURA_BASE_URL del entorno.
nanofactura = NanoFactura()

comprobante = nanofactura.emitir_comprobante({
    "posNumber": 1,
    "voucherType": TipoComprobante.FACTURA_C,
    "concept": Concepto.PRODUCTOS,
    "voucherDate": "20260811",
    "customer": {
        "docType": TipoDocumento.DNI,
        "docNumber": "35888999",
        "name": "Pedro López",
        "stateId": Provincia.SANTA_FE,
        "taxType": CondicionIva.CONSUMIDOR_FINAL,
    },
    "items": [
        {"description": "Servicio de consultoría", "quantity": 1, "price": 50000},
    ],
    "externalReference": "PEDIDO-42",
})

print(comprobante["cae"])            # '76251489623547'
print(comprobante["voucherNumber"])  # 45
print(comprobante["quota"])          # estado del cupo mensual (o None)
```

## Qué hay en `nanofactura/`

| Archivo | |
| :--- | :--- |
| `client.py` | El cliente: autenticación, envelope `{ok, result}`, reintentos e idempotencia |
| `errors.py` | `NanoFacturaError` con `code`, `status`, `details`, `is_retryable`, `tiene_codigo_arca()` y `campos_invalidos` |
| `constants/` | Las ocho tablas de códigos de ARCA, como `IntEnum` |

### Métodos

```python
nanofactura.verificar_conexion()                          # valida clave, certificado y puntos de venta (puesta en marcha)
nanofactura.emitir_comprobante(payload)                   # POST /invoices → CAE
nanofactura.listar_comprobantes(filtros)                  # → (comprobantes, total)
nanofactura.descargar_pdf(invoice_id, [1, 2])             # → bytes
nanofactura.esperar_pdf(invoice_id)                       # igual, esperando a que se genere
nanofactura.agregar_destinatario(invoice_id, {"email": …}) # programa el envío del PDF
nanofactura.regenerar_pdf(invoice_id)                     # reparación: hasta 3 veces, dentro de 90 días
nanofactura.consultar_padron("30703088534")               # datos del contribuyente en ARCA
nanofactura.listar_puntos_de_venta()                      # los configurados
nanofactura.listar_puntos_de_venta_arca()                 # los habilitados en ARCA
nanofactura.estado_arca()                                 # diagnóstico de conexión
nanofactura.datos_empresa()                               # datos fiscales
```

> `verificar_conexion()` es para la **puesta en marcha** y el diagnóstico, no para llamarla antes de cada emisión: lo que chequea cambia una vez cada dos años —cuando vence el certificado—, así que sólo agrega latencia. Si falta configuración, la emisión lo dice igual.

### Errores

Todos los métodos lanzan `NanoFacturaError`:

```python
from nanofactura import NanoFacturaError

try:
    comprobante = nanofactura.emitir_comprobante(payload)
except NanoFacturaError as error:
    if error.is_arca_rejection:
        # ARCA rechazó el comprobante. `details` trae los códigos exactos.
        for detalle in error.details:
            print(f"({detalle['code']}) {detalle['msg']}")
    elif error.code == "VALIDATION_ERROR":
        # El pedido está mal armado. `field` es la ruta del dato dentro del cuerpo que mandaste.
        for campo in error.campos_invalidos:
            print(f"{campo['field'] or '(cuerpo)'}: {campo['message']}")
    else:
        print(error.code, error.message)
```

El cliente **ya reintenta solo** los errores transitorios (`429`, `500`, `502`, `503`, `504`), con backoff exponencial y respetando `Retry-After`. Los permanentes (`400`, `401`, `402`, `403`, `404`, `409`, `422`) los lanza de inmediato: reintentarlos no cambiaría nada.

Dos que caen en ese rango pero **no ceden reintentando** quedan afuera: la cuota mensual agotada (`SUBSCRIPTION_QUOTA_EXCEEDED`) y el certificado sin delegar al servicio de ARCA (`Computador no autorizado a acceder al servicio`, `502`). Se lanzan de inmediato.

La clave de idempotencia se genera una vez por llamada y se conserva a través de los reintentos, que es lo que hace seguro reintentar tras un timeout.

## Constantes

Son `IntEnum`, así que `json.dumps` las serializa como el entero que ARCA espera — no hace falta `.value`:

```python
TipoComprobante.FACTURA_C          # 11
CondicionIva.MONOTRIBUTO           # 6    ← condición del CLIENTE
AlicuotaIva.VEINTIUNO              # 5    ← alícuota del ÍTEM
TipoDocumento.DNI                  # 96
Provincia.SANTA_FE                 # 12
Unidad.KILOGRAMOS                  # 1
Moneda.DOLAR                       # 'DOL'
Tributo.PERCEPCION_IIBB            # 7
Concepto.SERVICIOS                 # 2

PROVINCIA_NO_INFORMADA             # None — cuando no sabés de dónde es el cliente
```

> **Usalas siempre.** `taxType: 5` en un ítem es 21% de IVA; `taxType: 5` en el cliente es Consumidor Final. Son campos distintos con códigos que se solapan, y es el error más común al integrar.

Un par de propiedades útiles:

```python
TipoComprobante.NOTA_CREDITO_C.letra   # 'C' — para validar que coincida con la factura que anula
AlicuotaIva.VEINTIUNO.porcentaje       # 21.0
```

## Tres cosas para tener en cuenta

**Guardá los identificadores, no el PDF.** De cada emisión guardá `id`, `cae`, `voucherNumber`, `posNumber` y `voucherType`. El PDF se genera en segundo plano y se pide a demanda con el `id`.

**Mandá `customer["stateId"]`.** Es opcional, pero es el único dato estructurado de ubicación y es lo que permite después armar reportes por jurisdicción para Ingresos Brutos. No se puede reconstruir hacia atrás.

**Emití secuencialmente por punto de venta.** Ver [`10_emision_en_lote.py`](examples/10_emision_en_lote.py).

## Enlaces

- [Documentación de la API](https://asistentefacturaelectronica.com/ayuda/api)
- [Campos y equivalencia con ARCA](https://asistentefacturaelectronica.com/ayuda/api/campos-arca)
- [Errores](https://asistentefacturaelectronica.com/ayuda/api/errores)
- [contacto@nanofactura.com](mailto:contacto@nanofactura.com)
