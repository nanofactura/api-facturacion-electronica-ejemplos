# Instrucciones para agentes de programación

Este repositorio contiene ejemplos de integración con la **API REST de NanoFactura** para emitir facturas electrónicas de **ARCA (ex AFIP)**, en JavaScript, PHP y Python.

## Leé esto primero

**La documentación completa, en texto plano y autocontenida:**

```
https://asistentefacturaelectronica.com/ayuda/api/llms-full.txt
```

Trae todos los endpoints, los campos con su equivalencia en ARCA, la obligatoriedad según el tipo de comprobante, las tablas de códigos y el manejo de errores. Leelo antes de escribir código: no hay que adivinar nada.

Páginas sueltas, si necesitás sólo una:

| Tema | URL (agregá `.md` para el Markdown crudo) |
| :--- | :--- |
| Emitir un comprobante | `https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante.md` |
| Campos y equivalencia con ARCA | `https://asistentefacturaelectronica.com/ayuda/api/campos-arca.md` |
| Qué comprobante emitir | `https://asistentefacturaelectronica.com/ayuda/api/tipos-de-comprobante.md` |
| Errores | `https://asistentefacturaelectronica.com/ayuda/api/errores.md` |
| Consultar comprobantes | `https://asistentefacturaelectronica.com/ayuda/api/consultar-comprobantes.md` |
| PDF y email | `https://asistentefacturaelectronica.com/ayuda/api/pdf-y-email.md` |

## Estructura

```
javascript/src/client.js        cliente HTTP + errores + reintentos
javascript/src/constants/       tablas de códigos de ARCA
javascript/examples/            10 ejemplos numerados

php/src/Client.php              lo mismo, en PHP 8.1+
php/src/Constants/
php/examples/

python/nanofactura/client.py    lo mismo, en Python 3.9+
python/nanofactura/constants/
python/examples/
```

Los tres lenguajes implementan el **mismo** contrato y los **mismos** diez ejemplos. Si tenés que agregar algo en uno, agregalo en los tres.

## Reglas al escribir código con esta API

1. **Usá las constantes, nunca los números.** `TIPO_COMPROBANTE.FACTURA_C`, no `11`. Es la única defensa contra confundir `taxType` de ítem (alícuota de IVA) con `taxType` de cliente (condición frente al IVA): son campos distintos con códigos que se solapan.

2. **La `idempotency` se genera una sola vez por comprobante, fuera de cualquier bucle de reintento.** Es lo único que hace seguro reintentar tras un timeout. Si la regenerás en el reintento, podés duplicar el comprobante.

3. **No reintentes errores permanentes.** `400`, `401`, `402`, `403`, `404`, `409` y `422` no cambian reintentando. Sí reintentá `429`, `500`, `502` y `504`, respetando `Retry-After` si viene.

4. **Un `504` no significa que el comprobante no se emitió.** Reintentá con la misma `idempotency`: si ya existía, la respuesta viene con `idempotencyHit: true`.

5. **Emití secuencialmente por punto de venta y tipo de comprobante.** ARCA exige numeración correlativa. Paralelizar sobre la misma combinación no acelera nada y produce `429 VOUCHER_LOCK_TIMEOUT`.

6. **No guardes el PDF.** Se genera en segundo plano y se pide a demanda con el `id`. Guardá `id`, `cae`, `voucherNumber`, `posNumber` y `voucherType`.

7. **Mandá `customer.stateId`** (provincia) siempre que se conozca: es el único dato estructurado de ubicación y no se puede reconstruir después. `null` es válido y significa "no informada" — es preferible a inventar `0`, que es CABA.

8. **Chequeá `data.ok`, no el status HTTP.** Todas las respuestas traen `ok`; los errores de negocio traen además `code`.

9. **No hardcodees importes de umbrales de ARCA** (por ejemplo, a partir de cuánto hay que identificar al comprador). Cambian por resolución. Manejá el rechazo `10015` en su lugar.

10. **La API key nunca va en el código ni en el navegador.** Va en `NANOFACTURA_API_KEY`, y la API se llama desde el servidor.

## Entornos

El prefijo de la API key decide contra qué ARCA se opera: `TEST_` es homologación (sin validez fiscal) y `PROD_` es producción. **No hay ningún parámetro de entorno en el pedido.** Pasar a producción es cambiar la variable de entorno.

Cuando generes código de ejemplo o de prueba, asumí `TEST_`.

## Configuración

```bash
NANOFACTURA_API_KEY=TEST_...        # obligatoria
NANOFACTURA_ID_EMPRESA=mi-empresa     # obligatoria, va en la URL
NANOFACTURA_BASE_URL=https://nanofactura.com/api
```

## Lo que esta API no hace

No crea certificados de ARCA, no administra suscripciones ni pagos, no invita usuarios y no crea otras API keys. Todo eso se hace desde la aplicación web, con usuario. Si el pedido del usuario requiere alguna de esas cosas, indicale que vaya a
`https://asistentefacturaelectronica.com/ayuda/api/certificado-arca`.
