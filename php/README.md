# Facturación electrónica ARCA en PHP

Cliente y ejemplos para emitir **facturas electrónicas de ARCA (ex AFIP)** desde PHP con la API de NanoFactura.

**Sin dependencias**: sólo cURL y JSON, que vienen con PHP.

📖 [Documentación completa](https://asistentefacturaelectronica.com/ayuda/api)

## Requisitos

- **PHP 8.1 o superior** con las extensiones `curl` y `json`
- Una API key de NanoFactura → [cómo obtenerla](https://asistentefacturaelectronica.com/ayuda/api/api-keys)

## Instalación

No hace falta Composer para correr los ejemplos: traen su propio autoloader. Alcanza con configurar el `.env` en la raíz del repositorio:

```bash
cd ..                 # a la raíz del repo
cp .env.example .env  # y completá tu API key
cd php
```

Si preferís usar Composer en tu proyecto, el `composer.json` ya declara el autoload PSR-4:

```bash
composer install
```

## Ejecutar los ejemplos

```bash
php examples/00-sin-sdk.php                          # la API cruda, sin las clases de src/
php examples/01-verificar-conexion.php               # ← empezá por acá
php examples/02-factura-c-monotributo.php
php examples/03-factura-a-responsable-inscripto.php
php examples/04-factura-b-consumidor-final.php
php examples/05-servicios-con-periodo.php
php examples/06-nota-de-credito.php
php examples/07-consultar-padron.php 30703088534
php examples/08-descargar-pdf.php 1485
php examples/09-listar-comprobantes.php
php examples/10-emision-en-lote.php
```

## Uso en tu proyecto

Copiá la carpeta `src/` — son doce archivos sin dependencias — o usá el código como referencia.

```php
<?php

require 'vendor/autoload.php';

use NanoFactura\Client;
use NanoFactura\Constants\{TipoComprobante, Concepto, TipoDocumento, CondicionIva, Provincia};

// Lee NANOFACTURA_API_KEY, NANOFACTURA_ID_EMPRESA y NANOFACTURA_BASE_URL del entorno.
$nanofactura = new Client();

$comprobante = $nanofactura->emitirComprobante([
    'posNumber' => 1,
    'voucherType' => TipoComprobante::FACTURA_C,
    'concept' => Concepto::PRODUCTOS,
    'voucherDate' => '20260811',
    'customer' => [
        'docType' => TipoDocumento::DNI,
        'docNumber' => '35888999',
        'name' => 'Pedro López',
        'stateId' => Provincia::SANTA_FE,
        'taxType' => CondicionIva::CONSUMIDOR_FINAL,
    ],
    'items' => [
        ['description' => 'Servicio de consultoría', 'quantity' => 1, 'price' => 50000],
    ],
    'externalReference' => 'PEDIDO-42',
]);

echo $comprobante['cae'];            // '76251489623547'
echo $comprobante['voucherNumber'];  // 45
```

## Qué hay en `src/`

| Archivo | |
| :--- | :--- |
| `Client.php` | El cliente: autenticación, envelope `{ ok, result }`, reintentos e idempotencia |
| `ApiException.php` | Excepción con `errorCode`, `status`, `details`, `isRetryable()`, `tieneCodigoArca()` y `camposInvalidos()` |
| `Constants/` | Las ocho tablas de códigos de ARCA |

### Métodos

```php
$nanofactura->verificarConexion();                          // valida clave, certificado y puntos de venta (puesta en marcha)
$nanofactura->emitirComprobante($payload);                  // POST /invoices → CAE
$nanofactura->listarComprobantes($filtros);                 // → ['comprobantes' => [...], 'total' => n]
$nanofactura->descargarPdf($invoiceId, [1, 2]);             // → string binario
$nanofactura->esperarPdf($invoiceId);                       // igual, esperando a que se genere
$nanofactura->agregarDestinatario($invoiceId, ['email' => …]); // programa el envío del PDF
$nanofactura->regenerarPdf($invoiceId);                     // reparación: hasta 3 veces, dentro de 90 días
$nanofactura->consultarPadron('30703088534');               // datos del contribuyente en ARCA
$nanofactura->listarPuntosDeVenta();                        // los configurados
$nanofactura->listarPuntosDeVentaArca();                    // los habilitados en ARCA
$nanofactura->estadoArca();                                 // diagnóstico de conexión
$nanofactura->datosEmpresa();                               // datos fiscales
```

> `verificarConexion()` es para la **puesta en marcha** y el diagnóstico, no para llamarla antes de cada emisión: lo que chequea cambia una vez cada dos años —cuando vence el certificado—, así que sólo agrega latencia. Si falta configuración, la emisión lo dice igual.

### Errores

Todos los métodos lanzan `NanoFactura\ApiException`:

```php
use NanoFactura\ApiException;

try {
    $comprobante = $nanofactura->emitirComprobante($payload);
} catch (ApiException $e) {
    if ($e->isArcaRejection()) {
        // ARCA rechazó el comprobante. `details` trae los códigos exactos.
        foreach ($e->details as $detalle) {
            error_log("({$detalle['code']}) {$detalle['msg']}");
        }
    } elseif ($e->errorCode === 'VALIDATION_ERROR') {
        // El pedido está mal armado. `field` es la ruta del dato dentro del cuerpo que mandaste.
        foreach ($e->camposInvalidos() as $campo) {
            error_log(($campo['field'] ?? '(cuerpo)') . ": {$campo['message']}");
        }
    } else {
        error_log("{$e->errorCode}: {$e->getMessage()}");
    }
}
```

El cliente **ya reintenta solo** los errores transitorios (`429`, `500`, `502`, `503`, `504`), con backoff exponencial y respetando `Retry-After`. Los permanentes (`400`, `401`, `402`, `403`, `404`, `409`, `422`) los lanza de inmediato: reintentarlos no cambiaría nada.

Dos que caen en ese rango pero **no ceden reintentando** quedan afuera: la cuota mensual agotada (`SUBSCRIPTION_QUOTA_EXCEEDED`) y el certificado sin delegar al servicio de ARCA (`Computador no autorizado a acceder al servicio`, `502`). Se lanzan de inmediato.

La clave de idempotencia se genera una vez por llamada y se conserva a través de los reintentos, que es lo que hace seguro reintentar tras un timeout.

## Constantes

```php
TipoComprobante::FACTURA_C          // 11
CondicionIva::MONOTRIBUTO           // 6    ← condición del CLIENTE
AlicuotaIva::VEINTIUNO              // 5    ← alícuota del ÍTEM
TipoDocumento::DNI                  // 96
Provincia::SANTA_FE                 // 12
Unidad::KILOGRAMOS                  // 1
Moneda::DOLAR                       // 'DOL'
Tributo::PERCEPCION_IIBB            // 7
Concepto::SERVICIOS                 // 2
```

> **Usalas siempre.** `taxType: 5` en un ítem es 21% de IVA; `taxType: 5` en el cliente es Consumidor Final. Son campos distintos con códigos que se solapan, y es el error más común al integrar.

Son constantes de clase y no enums a propósito: se serializan a JSON como el entero que ARCA espera, sin tener que acordarse de `->value` en cada uso.

## Tres cosas para tener en cuenta

**Guardá los identificadores, no el PDF.** De cada emisión guardá `id`, `cae`, `voucherNumber`, `posNumber` y `voucherType`. El PDF se genera en segundo plano y se pide a demanda con el `id`.

**Mandá `customer.stateId`.** Es opcional, pero es el único dato estructurado de ubicación y es lo que permite después armar reportes por jurisdicción para Ingresos Brutos. No se puede reconstruir hacia atrás.

**Emití secuencialmente por punto de venta.** Ver [`10-emision-en-lote.php`](examples/10-emision-en-lote.php).

## Enlaces

- [Documentación de la API](https://asistentefacturaelectronica.com/ayuda/api)
- [Campos y equivalencia con ARCA](https://asistentefacturaelectronica.com/ayuda/api/campos-arca)
- [Errores](https://asistentefacturaelectronica.com/ayuda/api/errores)
- [contacto@nanofactura.com](mailto:contacto@nanofactura.com)
