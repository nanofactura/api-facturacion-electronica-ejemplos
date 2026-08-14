"""Arranque común de los ejemplos: ruta del paquete, .env y utilidades de formato.

Los ejemplos hacen ``from _bootstrap import ...`` para poder correrse con
``python examples/01_verificar_conexion.py`` desde la carpeta ``python/``, sin instalar el paquete.
"""

from __future__ import annotations

import os
import sys
from datetime import date
from pathlib import Path

# El paquete `nanofactura` está un nivel arriba de examples/.
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

# En Windows la salida usa una code page heredada (cp1252) y cualquier `print` con ✅, ⚠️ o ·
# terminaba el ejemplo con UnicodeEncodeError antes de llegar a la API. La salida es texto para
# leer, así que se fuerza UTF-8.
for _flujo in (sys.stdout, sys.stderr):
    if hasattr(_flujo, "reconfigure"):
        _flujo.reconfigure(encoding="utf-8")

try:
    from dotenv import load_dotenv

    load_dotenv(Path(__file__).resolve().parent.parent.parent / ".env")
except ImportError:  # pragma: no cover
    print("⚠️ python-dotenv no está instalado: se usan las variables del entorno.")
    print("  pip install -r requirements.txt\n")


def hoy() -> str:
    """Fecha de hoy en el formato YYYYMMDD que espera ARCA."""
    return date.today().strftime("%Y%m%d")


def punto_de_venta() -> int:
    """El punto de venta configurado en el .env."""
    return int(os.environ.get("NANOFACTURA_POS_NUMBER", "1"))


def formatear_numero(pos: int, numero: int) -> str:
    """Formatea un comprobante como 0001-00000045."""
    return f"{pos:04d}-{numero:08d}"
