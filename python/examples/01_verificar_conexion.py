"""01 · Verificar la conexión

La primera llamada que conviene hacer al integrar: valida la API key y confirma que la empresa
tenga certificado y puntos de venta para el entorno de esa clave.

⚠️ Es una verificación de PUESTA EN MARCHA y de diagnóstico, no un paso previo a facturar. Lo que
chequea cambia una vez cada dos años —cuando vence el certificado—, así que llamarla antes de cada
emisión duplica la latencia y no evita ningún error: si falta algo, la emisión lo dice.

    python examples/01_verificar_conexion.py
"""

import sys

from _bootstrap import *  # noqa: F401,F403  (ruta del paquete y .env)

from nanofactura import NanoFactura

nanofactura = NanoFactura()

estado = nanofactura.verificar_conexion()

print("✅ Conexión OK")
print(f"   Entorno:      {estado['environment']}")
print(f"   Certificados: {'sí' if estado['certificates'] else 'NO'}")
print(f"   Puntos venta: {'sí' if estado['pos'] else 'NO'}")

if not estado["certificates"] or not estado["pos"]:
    print("\n⚠️ Falta configuración para poder emitir.")
    print("  https://asistentefacturaelectronica.com/ayuda/api/certificado-arca")
    sys.exit(1)

if nanofactura.is_test_environment:
    print("\nℹ Estás en HOMOLOGACIÓN: los comprobantes no tienen validez fiscal.")

# Los puntos de venta con `webOnly: True` son para Comprobantes en Línea y no sirven para la API:
# tienen `number: None` y no se pueden usar como `posNumber`.
puntos_de_venta = nanofactura.listar_puntos_de_venta()
utilizables = [pos for pos in puntos_de_venta if not pos.get("webOnly")]

print(f"\nPuntos de venta disponibles para facturar ({len(utilizables)}):")
for pos in utilizables:
    print(f"   {pos['number']:04d} · {pos.get('name') or '(sin nombre)'}")

print("\nCon esto ya podés emitir: seguí con 02_factura_c_monotributo.py.")
print("No llames a verificar_conexion() antes de cada comprobante: es para configurar y diagnosticar.")
