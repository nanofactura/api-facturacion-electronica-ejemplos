<?php

declare(strict_types=1);

/**
 * Arranque común de los ejemplos: autoloader y variables de entorno.
 *
 * Usa el autoloader de Composer si existe, y si no registra uno propio. Así los ejemplos corren con
 * un simple `php examples/01-verificar-conexion.php`, sin `composer install`: el proyecto no tiene
 * dependencias, y pedir un paso de instalación para cargar cuatro clases sería fricción sin motivo.
 */

$autoloadComposer = __DIR__ . '/../vendor/autoload.php';

if (file_exists($autoloadComposer)) {
    require $autoloadComposer;
} else {
    spl_autoload_register(static function (string $clase): void {
        $prefijo = 'NanoFactura\\';
        if (!str_starts_with($clase, $prefijo)) {
            return;
        }

        $ruta = __DIR__ . '/../src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
        if (file_exists($ruta)) {
            require $ruta;
        }
    });
}

// Carga del .env de la raíz del repositorio. Deliberadamente mínimo: no soporta comillas, valores
// multilínea ni interpolación. Para producción usá vlucas/phpdotenv o las variables del entorno de
// tu servidor — esto es sólo para que los ejemplos corran sin instalar nada.
$env = __DIR__ . '/../../.env';

if (file_exists($env)) {
    foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
            continue;
        }

        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor);

        if (getenv($clave) === false) {
            putenv("{$clave}={$valor}");
        }
    }
}

/** Fecha de hoy en el formato YYYYMMDD que espera ARCA. */
function hoy(): string
{
    return date('Ymd');
}

/** El punto de venta configurado en el .env. */
function puntoDeVenta(): int
{
    return (int) (getenv('NANOFACTURA_POS_NUMBER') ?: 1);
}

/** Formatea un comprobante como 0001-00000045. */
function formatearNumero(int $pos, int $numero): string
{
    return sprintf('%04d-%08d', $pos, $numero);
}
