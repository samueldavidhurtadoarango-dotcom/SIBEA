<?php

/**
 * ZONA HORARIA
 *
 * Paso 0 del recorrido: index.php (y el arranque de las pruebas) la aplican antes de hacer nada más.
 * XAMPP trae por defecto «Europe/Berlin», y con esa hora las fechas que escribe PHP (por ejemplo la
 * expiración de la sesión) no coincidirían con las de Colombia. Por eso la fijamos nosotros.
 */

declare(strict_types=1);

namespace SIBEA\Nucleo;

use InvalidArgumentException;

final class ZonaHoraria
{
    /** Zona que se usa si la configuración no dice otra cosa. */
    public const POR_DEFECTO = 'America/Bogota';

    /**
     * Pone la zona horaria de PHP según app.zona_horaria (o America/Bogota si falta).
     *
     * @param Configuracion $configuracion Los ajustes de la aplicación.
     * @return string La zona que quedó aplicada.
     * @throws InvalidArgumentException Si el nombre no es una zona horaria real (mejor fallar que usar otra hora sin avisar).
     */
    public static function aplicar(Configuracion $configuracion): string
    {
        $zona = $configuracion->obtenerOpcional('app.zona_horaria', self::POR_DEFECTO);
        if (!is_string($zona) || !in_array($zona, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('app.zona_horaria no es una zona horaria válida (ejemplo: America/Bogota).');
        }
        date_default_timezone_set($zona);

        return $zona;
    }
}
