"""Tipos de comprobante. Campo ``voucherType`` · en ARCA, ``CbteTipo``."""

from enum import IntEnum


class TipoComprobante(IntEnum):
    """Tipos de comprobante que la API admite emitir.

    La letra la determina tu condición fiscal como emisor y la de tu cliente:

    * Monotributista o exento                             → siempre C
    * Responsable inscripto → a un RI o monotributo       → A
    * Responsable inscripto → a consumidor final o exento → B

    ⚠️ Al monotributista le corresponde Factura A, no B. Durante años fue al revés, y es el error
    más frecuente al integrar.

    Los comprobantes M (51, 52, 53) no se emiten por esta API.

    https://asistentefacturaelectronica.com/ayuda/api/tipos-de-comprobante
    """

    FACTURA_A = 1
    NOTA_DEBITO_A = 2
    NOTA_CREDITO_A = 3

    FACTURA_B = 6
    NOTA_DEBITO_B = 7
    NOTA_CREDITO_B = 8

    FACTURA_C = 11
    NOTA_DEBITO_C = 12
    NOTA_CREDITO_C = 13

    @property
    def letra(self) -> str:
        """La letra del comprobante.

        Sirve para validar antes de emitir que una nota de crédito sea de la misma letra que el
        comprobante que anula — ARCA lo exige y es un error fácil de cometer al parametrizar.
        """
        return {
            1: "A", 2: "A", 3: "A",
            6: "B", 7: "B", 8: "B",
            11: "C", 12: "C", 13: "C",
        }[self.value]
