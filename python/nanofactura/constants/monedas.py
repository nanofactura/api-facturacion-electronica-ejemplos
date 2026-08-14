"""Moneda del comprobante. Campo ``currency.id`` · en ARCA, ``MonId``."""

from enum import Enum


class Moneda(str, Enum):
    """Códigos de moneda de ARCA.

    Si no es ``PESOS``, tenés que mandar ``currency.quotation`` con la cotización del día contra
    el peso: la API no la busca por vos, porque qué tipo de cambio aplicar es una decisión tuya.

    Los ``price`` de los ítems van EN LA MONEDA DEL COMPROBANTE, no convertidos a pesos.

    Hereda de ``str`` para que ``json.dumps`` la serialice como el código que ARCA espera.

    https://asistentefacturaelectronica.com/ayuda/api/tablas/monedas
    """

    PESOS = "PES"  # por defecto
    DOLAR = "DOL"
    EURO = "060"
    REAL = "012"
    PESO_URUGUAYO = "011"
    LIBRA_ESTERLINA = "021"
    YUAN = "064"

    def __str__(self) -> str:
        return self.value
