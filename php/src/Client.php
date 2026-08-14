<?php

declare(strict_types=1);

namespace NanoFactura;

/**
 * Cliente de la API de facturación electrónica de NanoFactura.
 *
 * Documentación: https://asistentefacturaelectronica.com/ayuda/api
 *
 * No tiene dependencias: usa cURL, que viene con PHP.
 */
final class Client
{
    private string $apiKey;
    private string $idEmpresa;
    private string $baseUrl;

    /**
     * @param int $timeout  Segundos. Por encima del techo de la API (25s) a propósito: un timeout
     *                      más corto del lado del cliente corta emisiones que iban a responder
     *                      bien y te deja sin saber si el comprobante existe.
     * @param int $retries  Intentos ante errores transitorios.
     */
    public function __construct(
        ?string $apiKey = null,
        ?string $idEmpresa = null,
        ?string $baseUrl = null,
        private int $timeout = 30,
        private int $retries = 3,
    ) {
        $this->apiKey = $apiKey ?? (string) getenv('NANOFACTURA_API_KEY');
        $this->idEmpresa = $idEmpresa ?? (string) getenv('NANOFACTURA_ID_EMPRESA');
        $this->baseUrl = rtrim(
            $baseUrl ?? (getenv('NANOFACTURA_BASE_URL') ?: 'https://nanofactura.com/api'),
            '/'
        );

        if ($this->apiKey === '') {
            throw new \InvalidArgumentException('Falta NANOFACTURA_API_KEY. Copiá .env.example a .env y completalo.');
        }
        if ($this->idEmpresa === '') {
            throw new \InvalidArgumentException('Falta NANOFACTURA_ID_EMPRESA. Copiá .env.example a .env y completalo.');
        }
    }

    /** `true` si la clave opera contra homologación (comprobantes sin validez fiscal). */
    public function isTestEnvironment(): bool
    {
        return str_starts_with($this->apiKey, 'TEST_');
    }

    // ── Comprobantes ─────────────────────────────────────────────────────────

    /**
     * Emite un comprobante y devuelve el CAE.
     *
     * La clave de idempotencia se genera acá si no viene en el payload, y se conserva a través de
     * los reintentos: es lo que hace que un timeout se pueda reintentar sin duplicar el
     * comprobante. Si la generás vos, guardala junto a tu operación ANTES de emitir.
     *
     * Devuelve el comprobante con `quota` agregado: el estado del cupo mensual viaja al lado de
     * `result` y no adentro, porque es de la empresa y no del comprobante. Puede ser null.
     *
     * @see https://asistentefacturaelectronica.com/ayuda/api/emitir-comprobante
     */
    public function emitirComprobante(array $payload): array
    {
        $payload['idempotency'] ??= self::uuid4();

        $respuesta = $this->request('POST', '/invoices', body: $payload, raw: true);

        return array_merge($respuesta['result'], ['quota' => $respuesta['quota'] ?? null]);
    }

    /**
     * Lista comprobantes del entorno de la clave.
     *
     * @return array{comprobantes: array, total: int} `total` es cuántos matchean el filtro, no los de la página.
     * @see https://asistentefacturaelectronica.com/ayuda/api/consultar-comprobantes
     */
    public function listarComprobantes(array $filtros = []): array
    {
        $respuesta = $this->request('GET', '/invoices', query: $filtros, raw: true);

        return ['comprobantes' => $respuesta['result'], 'total' => $respuesta['total'] ?? 0];
    }

    /**
     * Descarga el PDF y lo devuelve como string binario.
     *
     * No lo guardes en tu sistema luego de generar: pedilo en el momento en que alguien lo abre o lo imprime. El PDF
     * se genera en segundo plano, así que puede no estar listo apenas emitido — para eso está
     * esperarPdf().
     *
     * @param int[] $versiones 1 original, 2 duplicado, 3 triplicado.
     * @see https://asistentefacturaelectronica.com/ayuda/api/pdf-y-email
     */
    public function descargarPdf(int|string $invoiceId, array $versiones = [1]): string
    {
        $url = sprintf(
            '%s/%s/invoices/%s/pdf?versions=%s',
            $this->baseUrl,
            $this->idEmpresa,
            $invoiceId,
            implode(',', $versiones)
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiKey],
        ]);

        $cuerpo = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errorCurl = curl_error($ch);
        curl_close($ch);

        if ($cuerpo === false) {
            throw new ApiException("No se pudo descargar el PDF: {$errorCurl}", status: 504);
        }

        if ($status >= 400) {
            $datos = json_decode((string) $cuerpo, true) ?: [];
            throw new ApiException(
                $datos['error'] ?? "No se pudo descargar el PDF (HTTP {$status})",
                $datos['code'] ?? null,
                $status,
            );
        }

        return (string) $cuerpo;
    }

    /**
     * Descarga el PDF, esperando a que termine de generarse.
     *
     * Sólo hace falta si pedís el PDF inmediatamente después de emitir. En el uso normal —pedirlo
     * cuando el usuario lo abre— ya está listo hace rato y descargarPdf() alcanza.
     */
    public function esperarPdf(int|string $invoiceId, array $versiones = [1], int $intentos = 10, int $esperaSegundos = 2): string
    {
        for ($intento = 1; $intento <= $intentos; $intento++) {
            try {
                return $this->descargarPdf($invoiceId, $versiones);
            } catch (ApiException $e) {
                if ($e->errorCode !== 'PDF_NOT_READY' || $intento === $intentos) {
                    throw $e;
                }
                sleep($esperaSegundos);
            }
        }

        throw new ApiException('El PDF no se generó a tiempo', 'PDF_NOT_READY', 409);
    }

    /** Agrega un destinatario y programa el envío del PDF. Máximo 3 por comprobante. */
    public function agregarDestinatario(int|string $invoiceId, array $destinatario): array
    {
        return $this->request('POST', "/invoices/{$invoiceId}/emails", body: $destinatario);
    }

    /** Vuelve a generar el PDF. Hasta 3 veces por comprobante y dentro de los 90 días. */
    public function regenerarPdf(int|string $invoiceId): array
    {
        return $this->request('POST', "/invoices/{$invoiceId}/pdf/regenerate");
    }

    // ── Configuración y consultas ────────────────────────────────────────────

    /**
     * Valida la clave y la configuración mínima para facturar.
     *
     * Es la primera llamada que conviene hacer al integrar, y después sirve para diagnosticar. NO es
     * un chequeo previo a cada emisión: la configuración cambia una vez cada dos años (cuando vence
     * el certificado), así que llamarla antes de facturar duplica la latencia sin evitar ningún
     * error. Si algo falta, la emisión lo dice igual.
     */
    public function verificarConexion(): array
    {
        return $this->request('GET', '/api-keys/test', raw: true);
    }

    /** Puntos de venta configurados en NanoFactura. */
    public function listarPuntosDeVenta(): array
    {
        return $this->request('GET', '/pos');
    }

    /** Puntos de venta efectivamente habilitados en ARCA. */
    public function listarPuntosDeVentaArca(): array
    {
        return $this->request('GET', '/pos/arca');
    }

    /**
     * Consulta un contribuyente en el padrón de ARCA por CUIT o CUIL.
     *
     * `taxCondition` se puede usar tal cual como `customer.taxType`. `source` dice qué padrón
     * contestó —'constancia' o 'a13'—, y `$service` elige uno en vez del que corresponde al tipo de
     * clave. Cada padrón se delega por separado en ARCA.
     */
    public function consultarPadron(string $documento, ?string $service = null): array
    {
        return $this->request(
            'GET',
            "/arca/padron/{$documento}",
            query: $service !== null ? ['service' => $service] : []
        );
    }

    /** Diagnóstico: servidores de ARCA, certificado y acceso a los padrones. */
    public function estadoArca(): array
    {
        return $this->request('GET', '/arca/status', raw: true);
    }

    /** Datos fiscales de la empresa. */
    public function datosEmpresa(): array
    {
        return $this->request('GET', '/details');
    }

    // ── Interno ──────────────────────────────────────────────────────────────

    /**
     * @param bool $raw Devolver la respuesta completa en vez de sólo `result`.
     */
    private function request(string $method, string $path, ?array $body = null, array $query = [], bool $raw = false): array
    {
        $url = $this->baseUrl . '/' . $this->idEmpresa . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query(array_filter($query, static fn ($v) => $v !== null));
        }

        $ultimoError = null;

        for ($intento = 1; $intento <= $this->retries; $intento++) {
            $cabeceras = ['Authorization: Bearer ' . $this->apiKey];
            if ($body !== null) {
                $cabeceras[] = 'Content-Type: application/json';
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER => $cabeceras,
                CURLOPT_HEADER => true,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
            }

            $respuestaCruda = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $tamanoCabeceras = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $errorCurl = curl_error($ch);
            curl_close($ch);

            if ($respuestaCruda === false) {
                // Corte de red o timeout. Es el caso más delicado: el comprobante puede haberse
                // emitido igual. El reintento manda la MISMA idempotency, así que si existía vuelve
                // con `idempotencyHit: true` en vez de duplicarse.
                $ultimoError = new ApiException("No se pudo conectar con la API: {$errorCurl}", status: 504);
                if ($intento === $this->retries) {
                    throw $ultimoError;
                }
                usleep(self::backoffMicrosegundos($intento));
                continue;
            }

            $cabecerasTexto = substr((string) $respuestaCruda, 0, $tamanoCabeceras);
            $datos = json_decode(substr((string) $respuestaCruda, $tamanoCabeceras), true) ?: [];

            if ($datos['ok'] ?? false) {
                return $raw ? $datos : $datos['result'];
            }

            $error = new ApiException(
                $datos['error'] ?? "Error HTTP {$status}",
                $datos['code'] ?? null,
                $status,
                $datos['details'] ?? null,
            );

            if (!$error->isRetryable() || $intento === $this->retries) {
                throw $error;
            }

            $retryAfter = self::leerRetryAfter($cabecerasTexto);
            usleep($retryAfter !== null ? $retryAfter * 1_000_000 : self::backoffMicrosegundos($intento));
            $ultimoError = $error;
        }

        throw $ultimoError ?? new ApiException('Error desconocido');
    }

    private static function leerRetryAfter(string $cabeceras): ?int
    {
        if (preg_match('/^Retry-After:\s*(\d+)/mi', $cabeceras, $coincidencias) === 1) {
            return (int) $coincidencias[1];
        }

        return null;
    }

    /** Backoff exponencial con jitter, para que varios procesos no reintenten todos a la vez. */
    private static function backoffMicrosegundos(int $intento): int
    {
        return (int) ((2 ** $intento) * 500_000 + random_int(0, 250_000));
    }

    private static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
