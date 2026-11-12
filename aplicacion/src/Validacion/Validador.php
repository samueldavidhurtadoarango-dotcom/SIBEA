<?php

/**
 * VALIDADOR (base de todos los validadores)
 *
 * Paso 4 del recorrido: cada formulario tiene su validador, que revisa lo que
 * escribió la persona ANTES de llegar a la base de datos. Cada uno define su
 * propio método validar() (recibe distintos campos) y devuelve un
 * ResultadoValidacion. Esta clase solo reúne lo que comparten.
 */

declare(strict_types=1);

namespace BenefitRack\Validacion;

abstract class Validador
{
    /**
     * @param string $patron Expresión regular (con la bandera "u" para que entienda tildes y ñ).
     * @param string $texto  El texto a revisar.
     * @return bool true si el texto cumple el patrón.
     */
    protected function cumple(string $patron, string $texto): bool
    {
        return preg_match($patron, $texto) === 1;
    }
}
