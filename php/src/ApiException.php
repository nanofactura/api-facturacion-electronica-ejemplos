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
     *
     * Y uno va al revés: ARCA_UNCONFIRMED llega como 409 —un status que acá es permanente— pero es
     * el que MÁS conviene reintentar. Significa que se cortó con ARCA al autorizar y no se pudo
     * confirmar si el comprobante salió; el reintento manda la misma idempotency, así que la API
     * consulta ARCA y devuelve el comprobante si ya existía en vez de emitir otro. Tratarlo como
     * permanente deja al cliente sin su comprobante y obliga a resolverlo a mano.
     */
    public function isRetryable(): bool
    {
        if ($this->errorCode === 'ARCA_UNCONFIRMED') {
            return true;
        }

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
     * separado, en el Administrador de Relaciones con Clave Fiscal:
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

    /**
     * Los códigos de rechazo que devolvió ARCA, o un array vacío si el error no viene de ellos.
     *
     * `details` no tiene una sola forma: en un rechazo de ARCA es la lista de observaciones, pero
     * en un error de validación o de cupo es un objeto. Por eso la pregunta "¿ARCA devolvió el
     * código N?" se hace acá y no recorriendo `details` a mano, que rompe apenas el error es de
     * otro tipo.
     *
     * @return list<int>
     */
    public function arcaCodes(): array
    {
        if (!is_array($this->details) || !array_is_list($this->details)) {
            return [];
        }

        $codes = [];

        foreach ($this->details as $detalle) {
            $code = is_array($detalle) ? ($detalle['code'] ?? null) : null;

            if (is_int($code) || (is_string($code) && ctype_digit($code))) {
                $codes[] = (int) $code;
            }
        }

        return $codes;
    }

    /** Si ARCA rechazó el comprobante con ese código. */
    public function tieneCodigoArca(int $code): bool
    {
        return in_array($code, $this->arcaCodes(), true);
    }

    /**
     * Los campos que la API rechazó, o un array vacío si el error no es de validación.
     *
     * Cada elemento trae `field` —la ruta del dato dentro del cuerpo que mandaste, con los índices
     * de los arreglos: `items.0.price`— y `message`. `field` viene en null cuando el problema es del
     * cuerpo entero y no de un campo puntual.
     *
     * Sirve para señalar el campo en un formulario sin interpretar el texto del mensaje. Un
     * `VALIDATION_ERROR` que sale de una regla de negocio —una clave de idempotencia reusada, por
     * ejemplo— no trae campos: ahí el array vuelve vacío y lo que explica el error es el mensaje.
     *
     * @return list<array{field: ?string, message: string}>
     */
    public function camposInvalidos(): array
    {
        $errors = is_array($this->details) ? ($this->details['errors'] ?? null) : null;

        if (!is_array($errors) || !array_is_list($errors)) {
            return [];
        }

        $campos = [];

        foreach ($errors as $error) {
            if (!is_array($error)) {
                continue;
            }

            $field = $error['field'] ?? null;
            $campos[] = [
                'field' => is_string($field) ? $field : null,
                'message' => (string) ($error['message'] ?? ''),
            ];
        }

        return $campos;
    }
}
