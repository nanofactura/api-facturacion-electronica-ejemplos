"""Errores de la API de NanoFactura."""

from __future__ import annotations

from typing import Any


class NanoFacturaError(Exception):
    """Error de la API de NanoFactura.

    Conserva ``code``, ``status`` y ``details`` en vez de aplanarlos a un string: son los tres
    datos con los que se decide qué hacer. ``code`` distingue el motivo, ``status`` si conviene
    reintentar y ``details`` trae el rechazo exacto de ARCA cuando lo hay.
    """

    def __init__(
        self,
        message: str,
        *,
        code: str | None = None,
        status: int | None = None,
        details: Any = None,
    ) -> None:
        super().__init__(message)
        self.message = message
        self.code = code
        self.status = status
        self.details = details

    @property
    def is_retryable(self) -> bool:
        """Si el error puede ceder solo.

        Un reintento sobre un error permanente sólo suma latencia: un payload inválido lo sigue
        siendo por más veces que se lo mande.

        Dos códigos entran en el rango de los transitorios pero NO lo son:

        * ``SUBSCRIPTION_QUOTA_EXCEEDED`` (429) cede al ampliar el límite o al renovarse el período.
        * El certificado sin delegar al servicio (502) cede al delegarlo en el portal de ARCA. Es
          :attr:`es_certificado_sin_delegar`.
        """
        if self.code == "SUBSCRIPTION_QUOTA_EXCEEDED":
            return False
        if self.es_certificado_sin_delegar:
            return False
        return self.status in (429, 500, 502, 503, 504)

    @property
    def es_certificado_sin_delegar(self) -> bool:
        """ARCA rechazó la autenticación por falta de delegación del certificado al servicio.

        Cada servicio se autoriza por separado (``wsfe`` para emitir, los de padrón para
        consultarlo), en el Administrador de Relaciones de Clave Fiscal:
        https://asistentefacturaelectronica.com/ayuda/api/certificado-arca
        """
        return "computador no autorizado" in self.message.lower()

    @property
    def is_arca_rejection(self) -> bool:
        """Rechazo de negocio de ARCA: ``details`` trae los códigos exactos que devolvió."""
        return self.code == "ARCA_ERROR"

    def __str__(self) -> str:
        return f"{self.code}: {self.message}" if self.code else self.message
