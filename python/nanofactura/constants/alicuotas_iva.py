"""Alícuotas de IVA de la línea. Campo ``items[].taxType`` · en ARCA, ``Iva[].Id``."""

from enum import IntEnum


class AlicuotaIva(IntEnum):
    """Alícuota de IVA de una línea de detalle.

    ⚠️ No confundir con :class:`CondicionIva`, que es la condición del CLIENTE frente al IVA. Los
    códigos se solapan: ``5`` acá es 21%, y ``5`` en el cliente es Consumidor Final.

    En los comprobantes C el campo no va: no se discrimina IVA y todas las líneas van al neto.

    https://asistentefacturaelectronica.com/ayuda/api/tablas/alicuotas-iva
    """

    NO_GRAVADO = 1    # no genera IVA, va a ImpTotConc
    EXENTO = 2        # no genera IVA, va a ImpOpEx
    CERO = 3          # 0%, va a ImpOpEx
    DIEZ_CINCO = 4    # 10,5%
    VEINTIUNO = 5     # 21%  ← la alícuota general
    VEINTISIETE = 6   # 27%
    CINCO = 8         # 5%
    DOS_CINCO = 9     # 2,5%

    @property
    def porcentaje(self) -> float:
        """El porcentaje, por si necesitás mostrarlo o calcular del lado del cliente."""
        return {4: 10.5, 5: 21.0, 6: 27.0, 8: 5.0, 9: 2.5}.get(self.value, 0.0)
