<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Servicios;

use BenefitRack\Servicios\AlmacenTemporal;

/**
 * AlmacenTemporal de prueba: con PHP por consola move_uploaded_file() siempre falla (no hubo subida real
 * por HTTP), así que aquí «mover» es un rename común. Es la única parte que cambia.
 */
final class AlmacenTemporalDePrueba extends AlmacenTemporal
{
    protected function mover(string $origen, string $destino): bool
    {
        return rename($origen, $destino);
    }
}
