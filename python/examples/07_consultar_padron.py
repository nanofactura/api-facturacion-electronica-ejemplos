"""07 · Consultar el padrón de ARCA

Si tenés el CUIT del cliente, ésta es la forma correcta de armar el dict `customer`: te asegura que
la condición frente al IVA sea la real —que es lo que determina qué comprobante corresponde
emitir— y te trae la provincia ya codificada, sin que nadie la tipee mal.

Guardá el resultado en tu base: no hace falta consultarlo en cada venta al mismo cliente.

Si estás integrando en homologación, consultá el padrón con una API key de producción: la base de
homologación tiene datos de prueba y responde distinto. Esta consulta sólo lee, no emite nada.

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
    if error.status == 404:
        print(f"⚠️ ARCA no tiene ningún contribuyente con el documento {documento}.", file=sys.stderr)
        sys.exit(1)
    # ARCA puede negarse a informar una clave concreta por el estado de ESE contribuyente —cancelada,
    # inactiva, con actividades fuera del nomenclador vigente—. No es un problema de configuración ni
    # cede reintentando: es lo que ARCA sabe de ese CUIT.
    if error.code == "ARCA_ERROR":
        print(f"⚠️ ARCA no informa datos de {documento}: {error.message}", file=sys.stderr)
        sys.exit(1)
    raise

# `cache["hit"]` dice si la respuesta salió de un dato ya guardado en vez de consultarse en el
# momento, y `cache["stale"]` si ese dato estaba vencido y se usó igual porque ARCA no respondió.
cache = contribuyente.get("cache") or {}
origen = (" · dato guardado (vencido)" if cache.get("stale") else " · dato guardado") if cache.get("hit") else ""
print(f"Padrón de {documento}{origen}")
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
