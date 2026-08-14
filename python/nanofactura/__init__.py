"""Cliente de la API de facturación electrónica ARCA de NanoFactura.

Documentación: https://asistentefacturaelectronica.com/ayuda/api
"""

from .client import NanoFactura
from .errors import NanoFacturaError

__all__ = ["NanoFactura", "NanoFacturaError"]
__version__ = "1.0.0"
