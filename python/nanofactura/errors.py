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

        Y uno va al revés: ``ARCA_UNCONFIRMED`` llega como 409 —un status que acá es permanente—
        pero es el que MÁS conviene reintentar. Significa que se cortó con ARCA al autorizar y no se
        pudo confirmar si el comprobante salió; el reintento manda la misma ``idempotency``, así que
        la API consulta ARCA y devuelve el comprobante si ya existía en vez de emitir otro. Tratarlo
        como permanente deja al cliente sin su comprobante y obliga a resolverlo a mano.
        """
        if self.code == "ARCA_UNCONFIRMED":
            return True
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

    @property
    def arca_codes(self) -> list[int]:
        """Los códigos de rechazo que devolvió ARCA, o una lista vacía si el error no es de ellos.

        ``details`` no tiene una sola forma: en un rechazo de ARCA es la lista de observaciones,
        pero en un error de validación o de cupo es un diccionario. Por eso la pregunta "¿ARCA
        devolvió el código N?" se hace acá y no recorriendo ``details`` a mano, que rompe apenas el
        error es de otro tipo.
        """
        if not isinstance(self.details, list):
            return []

        codes = []
        for detalle in self.details:
            code = detalle.get("code") if isinstance(detalle, dict) else None
            if isinstance(code, bool):
                continue
            if isinstance(code, int):
                codes.append(code)
            elif isinstance(code, str) and code.strip().lstrip("-").isdigit():
                codes.append(int(code))
        return codes

    def tiene_codigo_arca(self, code: int) -> bool:
        """Si ARCA rechazó el comprobante con ese código."""
        return code in self.arca_codes

    @property
    def campos_invalidos(self) -> list[dict[str, Any]]:
        """Los campos que la API rechazó, o una lista vacía si el error no es de validación.

        Cada elemento trae ``field`` —la ruta del dato dentro del cuerpo que mandaste, con los
        índices de las listas: ``items.0.price``— y ``message``. ``field`` viene en ``None`` cuando
        el problema es del cuerpo entero y no de un campo puntual.

        Sirve para señalar el campo en un formulario sin interpretar el texto del mensaje. Un
        ``VALIDATION_ERROR`` que sale de una regla de negocio —una clave de idempotencia reusada,
        por ejemplo— no trae campos: ahí la lista vuelve vacía y lo que explica el error es
        ``message``.
        """
        errors = self.details.get("errors") if isinstance(self.details, dict) else None
        if not isinstance(errors, list):
            return []

        campos = []
        for error in errors:
            if not isinstance(error, dict):
                continue
            field = error.get("field")
            campos.append(
                {
                    "field": field if isinstance(field, str) else None,
                    "message": str(error.get("message") or ""),
                }
            )
        return campos

    def __str__(self) -> str:
        return f"{self.code}: {self.message}" if self.code else self.message
