# Facturación electrónica ARCA en JavaScript

Cliente y ejemplos para emitir **facturas electrónicas de ARCA (ex AFIP)** desde Node.js con la API de NanoFactura.

**Sin dependencias**: usa el `fetch` nativo de Node 18+.

📖 [Documentación completa](https://asistentefacturaelectronica.com/ayuda/api)

## Requisitos

- **Node.js 18 o superior** (por `fetch` y `--env-file`)
- Una API key de NanoFactura → [cómo obtenerla](https://asistentefacturaelectronica.com/ayuda/api/api-keys)

## Instalación

No hay nada que instalar. Alcanza con configurar el `.env` en la raíz del repositorio:

```bash
cd ..                 # a la raíz del repo
cp .env.example .env  # y completá tu API key
cd javascript
```

## Ejecutar los ejemplos

```bash
npm run sin-sdk       # 00 · la API cruda, sin el cliente de src/
npm run verificar     # 01 · verificar la conexión ← empezá por acá
npm run factura-c     # 02 · Factura C (monotributo)
npm run factura-a     # 03 · Factura A (responsable inscripto)
npm run factura-b     # 04 · Factura B (consumidor final)
npm run servicios     # 05 · servicios con período
npm run nota-credito  # 06 · crea factura y nota de crédito
npm run padron        # 07 · consultar el padrón
npm run pdf           # 08 · descargar el PDF
npm run listar        # 09 · listar comprobantes
npm run lote          # 10 · emisión en lote
```

O directamente, que es lo mismo:

```bash
node --env-file=../.env examples/01-verificar-conexion.js
```

> Si usás Node 18, `--env-file` no existe todavía (llegó en la 20.6). Cargá el `.env` con
> `npx dotenv-cli -e ../.env -- node examples/01-verificar-conexion.js`, o exportá las variables a mano.

## Uso en tu proyecto

Copiá la carpeta `src/` a tu proyecto — son cuatro archivos sin dependencias — o usá el código como referencia para tu propio cliente.

```js
import { NanoFactura } from './src/client.js'
import { TIPO_COMPROBANTE, CONCEPTO, TIPO_DOCUMENTO, CONDICION_IVA, PROVINCIAS } from './src/constants/index.js'

// Lee NANOFACTURA_API_KEY, NANOFACTURA_ID_EMPRESA y NANOFACTURA_BASE_URL del entorno.
const nanofactura = new NanoFactura()

const comprobante = await nanofactura.emitirComprobante({
  posNumber: 1,
  voucherType: TIPO_COMPROBANTE.FACTURA_C,
  concept: CONCEPTO.PRODUCTOS,
  voucherDate: '20260811',
  customer: {
    docType: TIPO_DOCUMENTO.DNI,
    docNumber: '35888999',
    name: 'Pedro López',
    stateId: PROVINCIAS.SANTA_FE,
    taxType: CONDICION_IVA.CONSUMIDOR_FINAL,
  },
  items: [
    { description: 'Servicio de consultoría', quantity: 1, price: 50000 },
  ],
  externalReference: 'PEDIDO-42',
})

console.log(comprobante.cae)          // '76251489623547'
console.log(comprobante.voucherNumber) // 45
console.log(comprobante.quota?.remaining)
```

## Qué hay en `src/`

| Archivo | |
| :--- | :--- |
| `client.js` | El cliente: autenticación, envelope `{ ok, result }`, reintentos e idempotencia |
| `errors.js` | `NanoFacturaError` con `code`, `status`, `details`, `isRetryable`, `tieneCodigoArca()` y `camposInvalidos` |
| `constants/` | Las ocho tablas de códigos de ARCA |

### Métodos

```js
await nanofactura.verificarConexion()                        // valida clave, certificado y puntos de venta (puesta en marcha)
await nanofactura.emitirComprobante(payload)                 // POST /invoices → CAE
await nanofactura.listarComprobantes(filtros)                // → { comprobantes, total }
await nanofactura.descargarPdf(invoiceId, [1, 2])            // → Buffer
await nanofactura.esperarPdf(invoiceId)                      // igual, esperando a que se genere
await nanofactura.agregarDestinatario(invoiceId, { email })  // programa el envío del PDF
await nanofactura.regenerarPdf(invoiceId)                    // reparación: hasta 3 veces, dentro de 90 días
await nanofactura.consultarPadron('30703088534')             // datos del contribuyente en ARCA
await nanofactura.listarPuntosDeVenta()                      // los configurados
await nanofactura.listarPuntosDeVentaArca()                  // los habilitados en ARCA
await nanofactura.estadoArca()                               // diagnóstico de conexión
await nanofactura.datosEmpresa()                             // datos fiscales
```

> `verificarConexion()` es para la **puesta en marcha** y el diagnóstico, no para llamarla antes de cada emisión: lo que chequea cambia una vez cada dos años —cuando vence el certificado—, así que sólo agrega latencia. Si falta configuración, la emisión lo dice igual.

### Errores

Todos los métodos lanzan `NanoFacturaError`:

```js
import { NanoFacturaError } from './src/errors.js'

try {
  await nanofactura.emitirComprobante(payload)
} catch (error) {
  if (error.isArcaRejection) {
    // ARCA rechazó el comprobante. `details` trae los códigos exactos.
    for (const { code, msg } of error.details) console.error(`(${code}) ${msg}`)
  } else if (error.code === 'VALIDATION_ERROR') {
    // El pedido está mal armado. `field` es la ruta del dato dentro del cuerpo que mandaste.
    for (const { field, message } of error.camposInvalidos) console.error(`${field ?? '(cuerpo)'}: ${message}`)
  } else if (error.isRetryable) {
    // El cliente ya reintentó 3 veces con backoff antes de llegar acá.
  } else {
    console.error(error.code, error.message)
  }
}
```

El cliente **ya reintenta solo** los errores transitorios (`429`, `500`, `502`, `503`, `504`), con backoff exponencial y respetando `Retry-After`. Los permanentes (`400`, `401`, `402`, `403`, `404`, `409`, `422`) los lanza de inmediato: reintentarlos no cambiaría nada.

Dos que caen en ese rango pero **no ceden reintentando** quedan afuera: la cuota mensual agotada (`SUBSCRIPTION_QUOTA_EXCEEDED`) y el certificado sin delegar al servicio de ARCA (`Computador no autorizado a acceder al servicio`, `502`). Se lanzan de inmediato.

La clave de idempotencia se genera una vez por llamada y se conserva a través de los reintentos, que es lo que hace seguro reintentar tras un timeout.

## Constantes

```js
TIPO_COMPROBANTE.FACTURA_C        // 11
CONDICION_IVA.MONOTRIBUTO         // 6    ← condición del CLIENTE
ALICUOTA_IVA.VEINTIUNO            // 5    ← alícuota del ÍTEM
TIPO_DOCUMENTO.DNI                // 96
PROVINCIAS.SANTA_FE               // 12
UNIDAD.KILOGRAMOS                 // 1
MONEDA.DOLAR                      // 'DOL'
TRIBUTO.PERCEPCION_IIBB           // 7
CONCEPTO.SERVICIOS                // 2
```

> **Usalas siempre.** `taxType: 5` en un ítem es 21% de IVA; `taxType: 5` en el cliente es Consumidor Final. Son campos distintos con códigos que se solapan, y es el error más común al integrar.

## Tres cosas para tener en cuenta

**Guardá los identificadores, no el PDF.** De cada emisión guardá `id`, `cae`, `voucherNumber`, `posNumber` y `voucherType`. El PDF se genera en segundo plano y se pide a demanda con el `id`.

**Mandá `customer.stateId`.** Es opcional, pero es el único dato estructurado de ubicación y es lo que permite después armar reportes por jurisdicción para Ingresos Brutos. No se puede reconstruir hacia atrás.

**Emití secuencialmente por punto de venta.** Ver [`10-emision-en-lote.js`](examples/10-emision-en-lote.js).

## Enlaces

- [Documentación de la API](https://asistentefacturaelectronica.com/ayuda/api)
- [Campos y equivalencia con ARCA](https://asistentefacturaelectronica.com/ayuda/api/campos-arca)
- [Errores](https://asistentefacturaelectronica.com/ayuda/api/errores)
- [contacto@nanofactura.com](mailto:contacto@nanofactura.com)
