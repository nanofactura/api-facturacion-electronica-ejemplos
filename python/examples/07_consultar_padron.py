"""07 · Consultar el padrón de ARCA

Si tenés el CUIT del cliente, ésta es la forma correcta de armar el dict `customer`: te asegura que
la condición frente al IVA sea la real —que es lo que determina qué comprobante corresponde
emitir— y te trae la provincia ya codificada, sin que nadie la tipee mal.

Guardá el resultado en tu base: no hace falta consultarlo en cada venta al mismo cliente.

⚠️ El padrón se autoriza APARTE de la facturación electrónica: son servicios distintos de ARCA y cada
uno se delega por separado. Si emitís sin problemas pero esto falla con "Computador no autorizado",
es eso lo que falta.

    python examples/07_consultar_padron.py [documento]
"""

import json
import sys

from _bootstrap import *  # noqa: F401,F403  (ruta del paquete y .env)

from nanofactura import NanoFactura, NanoFacturaError
from nanofactura.constants import CondicionIva, TipoComprobante

nanofactura = NanoFactura()

documento = sys.argv[1] if len(sys.argv) > 1 else "30703088534"

try:
    contribuyente = nanofactura.consultar_padron(documento)
except NanoFacturaError as error:
    # Los dos motivos por los que esto falla en la práctica. Ninguno cede reintentando.
    if error.es_certificado_sin_delegar:
        # El mensaje de la API nombra el servicio, el alias del certificado y el entorno: son los
        # tres datos con los que se encuentra la relación que falta en el portal de ARCA.
        print(f"⚠️ {error.message}", file=sys.stderr)
        print("  Los servicios de padrón se autorizan aparte de la facturación electrónica:", file=sys.stderr)
        print(
            "  https://asistentefacturaelectronica.com/ayuda/api/certificado-arca",
            file=sys.stderr,
        )
        sys.exit(1)
    if error.status == 404:
        print(f"⚠️ ARCA no tiene ningún contribuyente con el documento {documento}.", file=sys.stderr)
        sys.exit(1)
    raise

# Qué padrón contestó. A las personas jurídicas les corresponde la Constancia de Inscripción y al
# resto el Padrón A13; se puede pedir el otro con `consultar_padron(documento, service="a13")`.
print(f"Padrón de {documento} · consultado en {contribuyente['source']}")
print(f"   Nombre:    {contribuyente['name']}")
print(f"   Domicilio: {contribuyente.get('address') or '(sin domicilio cargado)'}")
print(f"   Provincia: {contribuyente.get('stateId') if contribuyente.get('stateId') is not None else 'no informada'}")
print(f"   Condición: {contribuyente['taxConditionName']} ({contribuyente['taxCondition']})")
print(f"   Documento: tipo {contribuyente['docType']} · {contribuyente['docNumber']}")

# Los campos del padrón se corresponden uno a uno con los de `customer`. `taxCondition` se usa tal
# cual como `taxType`, sin traducir.
customer = {
    "docType": contribuyente["docType"],
    # El padrón devuelve el documento como número y la emisión lo espera como string: convertilo.
    "docNumber": str(contribuyente["docNumber"]),
    "name": contribuyente["name"],
    "address": contribuyente.get("address"),
    "stateId": contribuyente.get("stateId"),  # puede venir None: es un valor válido
    "taxType": contribuyente["taxCondition"],
}

print("\nDict `customer` listo para emitir:")
print(json.dumps(customer, indent=2, ensure_ascii=False))

# Siendo responsable inscripto, la condición del receptor decide la letra del comprobante.
#
# Ojo con el monotributista: le corresponde Factura A, no B. Durante años fue al revés, y es el
# error más frecuente al integrar.
RECIBEN_FACTURA_A = (CondicionIva.RESPONSABLE_INSCRIPTO, CondicionIva.MONOTRIBUTO)

tipo_sugerido = (
    TipoComprobante.FACTURA_A
    if customer["taxType"] in RECIBEN_FACTURA_A
    else TipoComprobante.FACTURA_B
)

print(f"\nSi sos responsable inscripto, a este cliente le corresponde voucherType {int(tipo_sugerido)}.")
print("Si sos monotributista o exento, siempre Factura C (11).")
