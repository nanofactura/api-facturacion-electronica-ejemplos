# API de Facturación Electrónica ARCA — Ejemplos de integración

Ejemplos de integración con la **API REST de [NanoFactura](https://asistentefacturaelectronica.com/ayuda/api)** para emitir **facturas electrónicas de ARCA (ex AFIP)** desde cualquier sistema, en **JavaScript**, **PHP** y **Python**.

Mandás un JSON con el cliente y los ítems, y recibís el **CAE** en la misma respuesta. Sin certificados que firmar, sin XML, sin llevar numeración, sin generar el PDF.

📖 **Documentación completa:** [asistentefacturaelectronica.com/ayuda/api](https://asistentefacturaelectronica.com/ayuda/api)

---

## Qué hay acá

```
javascript/    Node.js 18+, sin dependencias (fetch nativo)
php/           PHP 8.1+, sin dependencias (cURL)
python/        Python 3.9+, con requests
```

Los tres traen lo mismo, con la misma estructura y los mismos once ejemplos numerados:

| | |
| :--- | :--- |
| **Cliente** | Autenticación, envelope `{ ok, result }`, errores tipados, idempotencia y reintentos |
| **Constantes** | Todas las tablas de códigos de ARCA, para escribir `CONDICION_IVA.MONOTRIBUTO` en vez de `6` |
| **11 ejemplos** | De la API cruda a la emisión en lote |

Elegí el lenguaje que uses y leé su README.

- **[javascript/README.md](javascript/README.md)**
- **[php/README.md](php/README.md)**
- **[python/README.md](python/README.md)**

## Empezar

### 1. Cloná y configurá

```bash
git clone https://github.com/nanofactura/api-facturacion-electronica-ejemplos.git
cd api-facturacion-electronica-ejemplos
cp .env.example .env
```

Editá `.env` con tu API key y el código de tu empresa:

```bash
NANOFACTURA_API_KEY=TEST_00000000000000000000000000000000
NANOFACTURA_ID_EMPRESA=mi-empresa
```

> **¿No tenés todavía la API key?** Necesitás una empresa con plan Premium (o los **14 días de prueba gratuita**). El certificado de ARCA, los puntos de venta y la API key se configuran una sola vez:
> **[Guía de puesta en marcha →](https://asistentefacturaelectronica.com/ayuda/api/certificado-arca)**

### 2. Verificá la conexión

Es la primera llamada que conviene hacer siempre. Elegí tu lenguaje:

```bash
cd javascript && node examples/01-verificar-conexion.js
cd php        && php examples/01-verificar-conexion.php
cd python     && python examples/01_verificar_conexion.py
```

```
✅ Conexión OK
   Entorno:      homologation
   Certificados: sí
   Puntos venta: sí
```

### 3. Emití tu primera factura

```bash
node examples/02-factura-c-monotributo.js
```

```
✅ Comprobante emitido
   CAE:     76251489623547
   Número:  0001-00000045
   Total:   $ 30000
```

Ese CAE lo otorgó ARCA. Con una clave `TEST_` el comprobante **no tiene validez fiscal**: es el entorno de pruebas, y es donde conviene empezar.

## Empezá siempre por homologación

El prefijo de la API key es lo único que decide contra qué ARCA se opera:

| Prefijo | Entorno | Validez fiscal |
| :--- | :--- | :---: |
| `TEST_` | Homologación (pruebas) | No |
| `PROD_` | Producción | **Sí** |

**Pasar a producción es cambiar el valor de `NANOFACTURA_API_KEY`.** El código no cambia: no hay ningún parámetro de entorno en el pedido.

## Los once ejemplos

Numerados en orden de dificultad, iguales en los tres lenguajes:

| | Ejemplo | Qué muestra |
| :--- | :--- | :--- |
| `00` | La API cruda, sin cliente | El POST pelado, con todos los campos comentados |
| `01` | Verificar conexión | Valida clave, certificado y puntos de venta |
| `02` | Factura C — monotributo | El caso más común. Sin discriminar IVA |
| `03` | Factura A — responsable inscripto | Receptor con CUIT, IVA discriminado |
| `04` | Factura B — consumidor final | Cuándo hay que identificar al comprador |
| `05` | Servicios con período | `concept: 2` y las tres fechas obligatorias |
| `06` | Nota de crédito | Anular un comprobante con `associatedVouchers` |
| `07` | Consultar padrón | Armar el cliente con los datos reales de ARCA |
| `08` | Descargar el PDF | A demanda, con espera si todavía se está generando |
| `09` | Listar comprobantes | Filtros, orden y paginado |
| `10` | Emisión en lote | Secuencial por punto de venta, con reintentos |

## Constantes en vez de números

El error más común al integrar es un número mal puesto. `taxType: 5` en un ítem es **21% de IVA**; `taxType: 5` en el cliente es **Consumidor Final**. Son campos distintos con códigos que se solapan.

Las constantes lo hacen imposible de confundir:

```js
import { TIPO_COMPROBANTE, CONDICION_IVA, TIPO_DOCUMENTO, ALICUOTA_IVA, PROVINCIAS } from './src/constants/index.js'

voucherType: TIPO_COMPROBANTE.FACTURA_C   // 11
taxType:     CONDICION_IVA.MONOTRIBUTO    // 6   (cliente)
docType:     TIPO_DOCUMENTO.DNI           // 96
taxType:     ALICUOTA_IVA.VEINTIUNO       // 5   (ítem)
stateId:     PROVINCIAS.SANTA_FE          // 12
```

Están las ocho tablas: tipos de comprobante, alícuotas de IVA, condiciones de IVA, tipos de documento, unidades de medida, monedas, tributos y provincias.

## El precio del ítem incluye el IVA

`priceIncludesVat` vale `true` salvo que digas lo contrario, y decide cómo se leen todos los `price` del comprobante: **el precio que mandás es el precio final**, y la API le extrae el IVA para armar el desglose que ARCA pide.

```js
price: 121000, taxType: ALICUOTA_IVA.VEINTIUNO   // total $121.000 = neto $100.000 + IVA $21.000
```

Si tu lista de precios es neta, mandá `priceIncludesVat: false` y la API suma el IVA por encima: el mismo ítem pasa a ser una factura de $146.410.

Es un solo valor para todo el comprobante: no se puede mezclar precios netos y finales en el mismo pedido. Y en los comprobantes **C** no hace nada, porque no hay IVA que separar.

Está en los ejemplos `03` (A) y `04` (B).

## Tres cosas que conviene saber antes de integrar

**Guardá los identificadores, no los documentos.** De cada emisión guardá `id`, `cae`, `voucherNumber`, `posNumber` y `voucherType`. El PDF se genera solo, en segundo plano, y lo pedís a demanda cuando alguien lo abre o lo imprime — no hace falta almacenarlo.

**Mandá la provincia del cliente (`stateId`).** Es opcional, pero es el único dato estructurado de ubicación: con él podés armar reportes por jurisdicción para Ingresos Brutos. Sin él, esa pregunta no tiene respuesta y **no se puede reconstruir hacia atrás**.

**Emití secuencialmente por punto de venta.** ARCA exige numeración correlativa, así que los comprobantes del mismo punto de venta y tipo se serializan. Lanzarlos en paralelo no acelera nada y produce errores. Ver [`10-emision-en-lote`](javascript/examples/10-emision-en-lote.js).

## Para tu agente de IA

Este repositorio incluye un **[`AGENTS.md`](AGENTS.md)** con las instrucciones y el enlace a la documentación en texto plano. Si trabajás con Claude Code, Cursor, Antigravity o Copilot, abrir el repo alcanza.

O directamente, sin clonar nada:

```
Implementá facturación electrónica de ARCA leyendo:
https://asistentefacturaelectronica.com/ayuda/api/llms-full.txt

Seguí los patrones de:
https://github.com/nanofactura/api-facturacion-electronica-ejemplos
```

## ¿Otro lenguaje?

La API es REST sobre HTTP con JSON: se consume desde **cualquier** lenguaje. Estos tres son ejemplos, no un requisito — funciona igual desde C#, Java, Go, Ruby o Rust, desde Google Apps Script, y desde herramientas no-code como n8n, Make o Zapier.

Si querés aportar los ejemplos en tu lenguaje, los pull requests son bienvenidos.

## Enlaces

| | |
| :--- | :--- |
| 📖 Documentación | [asistentefacturaelectronica.com/ayuda/api](https://asistentefacturaelectronica.com/ayuda/api) |
| 🚀 Primeros pasos | [/ayuda/api/primeros-pasos](https://asistentefacturaelectronica.com/ayuda/api/primeros-pasos) |
| 🔑 Puesta en marcha | [/ayuda/api/certificado-arca](https://asistentefacturaelectronica.com/ayuda/api/certificado-arca) |
| 📋 Campos y equivalencia con ARCA | [/ayuda/api/campos-arca](https://asistentefacturaelectronica.com/ayuda/api/campos-arca) |
| ❓ Preguntas frecuentes | [/ayuda/api/preguntas-frecuentes](https://asistentefacturaelectronica.com/ayuda/api/preguntas-frecuentes) |
| 🤖 Documentación para LLMs | [/ayuda/api/llms-full.txt](https://asistentefacturaelectronica.com/ayuda/api/llms-full.txt) |
| ✉️ Contacto | [contacto@nanofactura.com](mailto:contacto@nanofactura.com) |

## Legales

| | |
| :--- | :--- |
| 📄 Términos y Condiciones | [asistentefacturaelectronica.com/legal/tyc](https://asistentefacturaelectronica.com/legal/tyc) |
| 🔒 Política de Privacidad | [asistentefacturaelectronica.com/legal/privacidad](https://asistentefacturaelectronica.com/legal/privacidad) |
| 🛡️ Seguridad | [asistentefacturaelectronica.com/legal/seguridad](https://asistentefacturaelectronica.com/legal/seguridad) |

Si estás evaluando meter la API en tu sistema, **[Seguridad](https://asistentefacturaelectronica.com/legal/seguridad)** es la que te interesa: cómo se resguardan las credenciales, los certificados y los comprobantes.

## Licencia

El **código de este repositorio** es MIT — ver [LICENSE](LICENSE). Usalo, copialo y adaptalo a tu proyecto, incluso comercial o de código cerrado, sin pedir permiso.

Son dos cosas distintas y conviene no confundirlas:

- **Este código** se entrega tal cual, sin garantías, bajo los términos del `LICENSE`. Lo que hagas con él es tuyo.
- **El servicio** —la API, su disponibilidad y la emisión de comprobantes ante ARCA— se rige por los [Términos y Condiciones](https://asistentefacturaelectronica.com/legal/tyc) y la [Política de Privacidad](https://asistentefacturaelectronica.com/legal/privacidad).

Que estos ejemplos sean MIT no te da derechos sobre el servicio, ni el servicio se hace responsable de cómo adaptes el código.

---

<sub>NanoFactura es el proveedor de la API. **Asistente Factura Electrónica** es su extensión de Chrome, para quienes facturan a mano en Comprobantes en Línea de ARCA. Comparten cuenta, empresa y listado de comprobantes.</sub>
