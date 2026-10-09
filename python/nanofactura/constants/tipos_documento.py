"""Tipo de documento del cliente. Campo ``customer.docType`` · en ARCA, ``DocTipo``."""

from enum import IntEnum


class TipoDocumento(IntEnum):
    """Tipos de documento admitidos por ARCA.

    ``docNumber`` va siempre sin puntos ni guiones y SÓLO con dígitos: ``'20112223334'``, no
    ``'20-11222333-4'``. ARCA declara ese campo como numérico, así que no acepta letras con ningún
    tipo de documento —tampoco con pasaporte—.

    Con ``SIN_IDENTIFICAR`` se pueden omitir ``docNumber`` y ``name``: la API los completa con ``'0'``
    y ``"Consumidor Final"``. Con cualquier otro tipo los dos son obligatorios.

    En Factura A y M tiene que ser ``CUIT``. ``SIN_IDENTIFICAR`` (con ``docNumber='0'``) sólo se
    admite en B y C por debajo del importe que fija ARCA — por encima, el rechazo llega con el
    código 10015.

    https://asistentefacturaelectronica.com/ayuda/api/tablas/tipos-de-documento
    """

    CUIT = 80
    CUIL = 86
    CDI = 87
    DNI = 96
    PASAPORTE = 94
    CI_EXTRANJERA = 91
    SIN_IDENTIFICAR = 99  # consumidor final no identificado; docNumber '0' u omitido
