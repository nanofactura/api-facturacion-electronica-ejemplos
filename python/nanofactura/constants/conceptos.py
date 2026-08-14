"""Concepto del comprobante. Campo ``concept`` · en ARCA, ``Concepto``."""

from enum import IntEnum


class Concepto(IntEnum):
    """Qué se está facturando.

    ⚠️ Con ``SERVICIOS`` o ``PRODUCTOS_Y_SERVICIOS`` son obligatorias LAS TRES fechas de servicio:
    ``serviceFrom``, ``serviceTo`` y ``serviceExpiration``, todas en formato ``YYYYMMDD``.

    https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante
    """

    PRODUCTOS = 1
    SERVICIOS = 2
    PRODUCTOS_Y_SERVICIOS = 3
