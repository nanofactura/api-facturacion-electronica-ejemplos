<?php

declare(strict_types=1);

namespace NanoFactura;

use RuntimeException;

/**
 * Error de la API de NanoFactura.
 *
 * Conserva `code`, `status` y `details` en vez de aplanarlos a un string: son los tres datos con
 * los que se decide qué hacer. `code` distingue el motivo, `status` si conviene reintentar y
 * `details` trae el rechazo exacto de ARCA cuando lo hay.
 */
final class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public ?string $errorCode = null,
        public ?int $status = null,
        public mixed $details = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Si el error puede ceder solo. Un reintento sobre un error permanente sólo suma latencia:
     * un payload inválido lo sigue siendo por más veces que se lo mande.
     *
     * Dos códigos entran en el rango de los transitorios pero NO lo son:
     *
     *  - SUBSCRIPTION_QUOTA_EXCEEDED (429) cede al ampliar el límite o al renovarse el período.
     *  - El certificado sin delegar al servicio (502) cede al delegarlo en el portal de ARCA.
     *    Es esCertificadoSinDelegar().
     */
    public function isRetryable(): bool
    {
        if ($this->errorCode === 'SUBSCRIPTION_QUOTA_EXCEEDED') {
            return false;
        }

        if ($this->esCertificadoSinDelegar()) {
            return false;
        }

        return in_array($this->status, [429, 500, 502, 503, 504], true);
    }

    /**
     * ARCA rechazó la autenticación porque al certificado le falta la delegación al servicio que se
     * está usando (`wsfe` para emitir, los de padrón para consultarlo). Se autoriza cada servicio por
     * separado, en el Administrador de Relaciones de Clave Fiscal:
     * https://asistentefacturaelectronica.com/ayuda/api/certificado-arca
     */
    public function esCertificadoSinDelegar(): bool
    {
        return stripos($this->getMessage(), 'Computador no autorizado') !== false;
    }

    /** Rechazo de negocio de ARCA: `details` trae los códigos exactos que devolvió. */
    public function isArcaRejection(): bool
    {
        return $this->errorCode === 'ARCA_ERROR';
    }
}
