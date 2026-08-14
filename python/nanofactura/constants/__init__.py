"""Tablas de códigos de ARCA.

Usalas siempre en lugar de los números: ``taxType`` significa cosas distintas en un ítem (alícuota
de IVA) y en el cliente (condición frente al IVA), y los códigos se solapan. ``5`` es 21% en un
ítem y Consumidor Final en el cliente. Escribir el nombre hace imposible confundirlos.

Son ``IntEnum`` (y ``str`` + ``Enum`` para las monedas), así que ``json.dumps`` las serializa como
el entero o el string que ARCA espera, sin tener que acordarse de ``.value`` en cada uso.

Referencia: https://asistentefacturaelectronica.com/ayuda/api/campos-arca
"""

from .alicuotas_iva import AlicuotaIva
from .conceptos import Concepto
from .condiciones_iva import CondicionIva
from .monedas import Moneda
from .provincias import PROVINCIA_NO_INFORMADA, Provincia
from .tipos_comprobante import TipoComprobante
from .tipos_documento import TipoDocumento
from .tributos import Tributo
from .unidades import Unidad

__all__ = [
    "PROVINCIA_NO_INFORMADA",
    "AlicuotaIva",
    "Concepto",
    "CondicionIva",
    "Moneda",
    "Provincia",
    "TipoComprobante",
    "TipoDocumento",
    "Tributo",
    "Unidad",
]
