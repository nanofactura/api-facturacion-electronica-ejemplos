"""Cliente de la API de facturación electrónica de NanoFactura.

Documentación: https://asistentefacturaelectronica.com/ayuda/api
"""

from __future__ import annotations

import os
import random
import time
import uuid
from typing import Any

import requests

from .errors import NanoFacturaError


class NanoFactura:
    """Cliente de la API de facturación electrónica de ARCA.

    Args:
        api_key: Por defecto, ``NANOFACTURA_API_KEY``.
        id_empresa: Por defecto, ``NANOFACTURA_ID_EMPRESA``.
        base_url: Por defecto, ``NANOFACTURA_BASE_URL``.
        timeout: Segundos. Por encima del techo de la API (25s) a propósito: un timeout más corto
            del lado del cliente corta emisiones que iban a responder bien y te deja sin saber si
            el comprobante existe.
        retries: Intentos ante errores transitorios.
    """

    def __init__(
        self,
        api_key: str | None = None,
        id_empresa: str | None = None,
        base_url: str | None = None,
        timeout: int = 30,
        retries: int = 3,
    ) -> None:
        self.api_key = api_key or os.environ.get("NANOFACTURA_API_KEY", "")
        self.id_empresa = id_empresa or os.environ.get("NANOFACTURA_ID_EMPRESA", "")
        self.base_url = (
            base_url or os.environ.get("NANOFACTURA_BASE_URL") or "https://nanofactura.com/api"
        ).rstrip("/")
        self.timeout = timeout
        self.retries = retries

        if not self.api_key:
            raise ValueError("Falta NANOFACTURA_API_KEY. Copiá .env.example a .env y completalo.")
        if not self.id_empresa:
            raise ValueError("Falta NANOFACTURA_ID_EMPRESA. Copiá .env.example a .env y completalo.")

        # Una sesión reusa la conexión TCP entre llamadas: en un lote de comprobantes evita
        # rehacer el handshake TLS en cada emisión.
        self._session = requests.Session()
        self._session.headers["Authorization"] = f"Bearer {self.api_key}"

    @property
    def is_test_environment(self) -> bool:
        """``True`` si la clave opera contra homologación (comprobantes sin validez fiscal)."""
        return self.api_key.startswith("TEST_")

    # ── Comprobantes ─────────────────────────────────────────────────────────

    def emitir_comprobante(self, payload: dict[str, Any]) -> dict[str, Any]:
        """Emite un comprobante y devuelve el CAE.

        La clave de idempotencia se genera acá si no viene en el payload, y se conserva a través
        de los reintentos: es lo que hace que un timeout se pueda reintentar sin duplicar el
        comprobante. Si la generás vos, guardala junto a tu operación ANTES de emitir.

        Devuelve el comprobante con ``quota`` agregado: el estado del cupo mensual viaja al lado
        de ``result`` y no adentro, porque es de la empresa y no del comprobante. Puede ser
        ``None``.

        https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante
        """
        cuerpo = {"idempotency": str(uuid.uuid4()), **payload}
        respuesta = self._request("POST", "/invoices", body=cuerpo, raw=True)
        return {**respuesta["result"], "quota": respuesta.get("quota")}

    def listar_comprobantes(self, filtros: dict[str, Any] | None = None) -> tuple[list[dict], int]:
        """Lista comprobantes del entorno de la clave.

        Returns:
            ``(comprobantes, total)``, donde ``total`` es cuántos matchean el filtro, no los de
            la página.

        https://asistentefacturaelectronica.com/ayuda/api/consultar-comprobantes
        """
        respuesta = self._request("GET", "/invoices", query=filtros or {}, raw=True)
        return respuesta["result"], respuesta.get("total", 0)

    def obtener_comprobante(self, invoice_id: int | str) -> dict:
        """Un comprobante por su ``id``, el que devolvió la emisión.

        Devuelve el mismo diccionario que una fila del listado. Si no existe —o es del otro
        entorno— lanza ``NanoFacturaError`` con ``code`` ``NOT_FOUND`` y ``status`` 404.

        https://asistentefacturaelectronica.com/ayuda/api/consultar-comprobantes#un-comprobante-por-id
        """
        return self._request("GET", f"/invoices/{invoice_id}")

    def descargar_pdf(self, invoice_id: int | str, versiones: list[int] | None = None) -> bytes:
        """Descarga el PDF y lo devuelve como bytes.

        No lo guardes en tu sistema luego de generar: pedilo en el momento en que alguien lo abre o lo imprime. El
        PDF se genera en segundo plano, así que puede no estar listo apenas emitido — para eso
        está :meth:`esperar_pdf`.

        Args:
            versiones: Copias a estampar. 1 original, 2 duplicado, 3 triplicado.

        https://asistentefacturaelectronica.com/ayuda/api/pdf-y-email
        """
        versiones = versiones or [1]
        url = f"{self.base_url}/{self.id_empresa}/invoices/{invoice_id}/pdf"

        try:
            respuesta = self._session.get(
                url, params={"versions": ",".join(map(str, versiones))}, timeout=self.timeout
            )
        except requests.RequestException as exc:
            raise NanoFacturaError(f"No se pudo descargar el PDF: {exc}", status=504) from exc

        if respuesta.status_code >= 400:
            datos = _json_o_vacio(respuesta)
            raise NanoFacturaError(
                datos.get("error", f"No se pudo descargar el PDF (HTTP {respuesta.status_code})"),
                code=datos.get("code"),
                status=respuesta.status_code,
            )

        return respuesta.content

    def esperar_pdf(
        self,
        invoice_id: int | str,
        versiones: list[int] | None = None,
        intentos: int = 10,
        espera_segundos: float = 2,
    ) -> bytes:
        """Descarga el PDF, esperando a que termine de generarse.

        Sólo hace falta si pedís el PDF inmediatamente después de emitir. En el uso normal
        —pedirlo cuando el usuario lo abre— ya está listo hace rato y :meth:`descargar_pdf`
        alcanza.
        """
        for intento in range(1, intentos + 1):
            try:
                return self.descargar_pdf(invoice_id, versiones)
            except NanoFacturaError as exc:
                if exc.code != "PDF_NOT_READY" or intento == intentos:
                    raise
                time.sleep(espera_segundos)

        raise NanoFacturaError("El PDF no se generó a tiempo", code="PDF_NOT_READY", status=409)

    def agregar_destinatario(self, invoice_id: int | str, destinatario: dict) -> dict:
        """Agrega un destinatario y programa el envío del PDF. Máximo 3 por comprobante."""
        return self._request("POST", f"/invoices/{invoice_id}/emails", body=destinatario)

    def regenerar_pdf(self, invoice_id: int | str) -> dict:
        """Vuelve a generar el PDF. Hasta 3 veces por comprobante y dentro de los 90 días."""
        return self._request("POST", f"/invoices/{invoice_id}/pdf/regenerate")

    # ── Configuración y consultas ────────────────────────────────────────────

    def verificar_conexion(self) -> dict:
        """Valida la clave y la configuración mínima para facturar.

        Es la primera llamada que conviene hacer al integrar, y después sirve para diagnosticar.
        NO es un chequeo previo a cada emisión: la configuración cambia una vez cada dos años
        (cuando vence el certificado), así que llamarla antes de facturar duplica la latencia sin
        evitar ningún error. Si algo falta, la emisión lo dice igual.
        """
        return self._request("GET", "/api-keys/test", raw=True)

    def listar_puntos_de_venta(self) -> list[dict]:
        """Puntos de venta configurados en NanoFactura."""
        return self._request("GET", "/pos")

    def listar_puntos_de_venta_arca(self) -> list[dict]:
        """Puntos de venta efectivamente habilitados en ARCA."""
        return self._request("GET", "/pos/arca")

    def consultar_padron(self, documento: str, service: str | None = None) -> dict:
        """Consulta un contribuyente en el padrón de ARCA por CUIT o CUIL.

        ``taxCondition`` se puede usar tal cual como ``customer.taxType``. ``source`` dice qué
        padrón contestó —``'constancia'`` o ``'a13'``—, y ``service`` elige uno en vez del que
        corresponde al tipo de clave. Cada padrón se delega por separado en ARCA.
        """
        return self._request("GET", f"/arca/padron/{documento}", query={"service": service})

    def estado_arca(self) -> dict:
        """Diagnóstico: servidores de ARCA, certificado y acceso a los padrones."""
        return self._request("GET", "/arca/status", raw=True)

    def datos_empresa(self) -> dict:
        """Datos fiscales de la empresa."""
        return self._request("GET", "/details")

    # ── Interno ──────────────────────────────────────────────────────────────

    def _request(
        self,
        method: str,
        path: str,
        body: dict | None = None,
        query: dict | None = None,
        raw: bool = False,
    ) -> Any:
        url = f"{self.base_url}/{self.id_empresa}{path}"
        params = {k: v for k, v in (query or {}).items() if v is not None}
        ultimo_error: NanoFacturaError | None = None

        for intento in range(1, self.retries + 1):
            try:
                respuesta = self._session.request(
                    method, url, json=body, params=params, timeout=self.timeout
                )
            except requests.RequestException as exc:
                # Corte de red o timeout. Es el caso más delicado: el comprobante puede haberse
                # emitido igual. El reintento manda la MISMA idempotency, así que si existía
                # vuelve con `idempotencyHit: True` en vez de duplicarse.
                ultimo_error = NanoFacturaError(f"No se pudo conectar con la API: {exc}", status=504)
                if intento == self.retries:
                    raise ultimo_error from exc
                time.sleep(_backoff(intento))
                continue

            datos = _json_o_vacio(respuesta)

            if datos.get("ok"):
                return datos if raw else datos["result"]

            error = NanoFacturaError(
                datos.get("error", f"Error HTTP {respuesta.status_code}"),
                code=datos.get("code"),
                status=respuesta.status_code,
                details=datos.get("details"),
            )

            if not error.is_retryable or intento == self.retries:
                raise error

            retry_after = respuesta.headers.get("Retry-After")
            espera = float(retry_after) if retry_after and retry_after.isdigit() else _backoff(intento)
            time.sleep(espera)
            ultimo_error = error

        raise ultimo_error or NanoFacturaError("Error desconocido")


def _json_o_vacio(respuesta: requests.Response) -> dict:
    """El cuerpo como dict, o vacío si no es JSON (un 502 de la CDN, por ejemplo)."""
    try:
        datos = respuesta.json()
    except ValueError:
        return {}
    return datos if isinstance(datos, dict) else {}


def _backoff(intento: int) -> float:
    """Backoff exponencial con jitter, para que varios procesos no reintenten todos a la vez."""
    return 2**intento * 0.5 + random.uniform(0, 0.25)
