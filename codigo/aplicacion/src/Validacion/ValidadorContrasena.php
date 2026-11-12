<?php

/**
 * VALIDADOR DE LA CONTRASEÑA NUEVA
 *
 * Paso 4 del recorrido. Las cuatro reglas son las de la maqueta
 * 02-contrasena/primer-ingreso: mínimo 8 caracteres, una letra mayúscula,
 * una minúscula y un número; además, las dos casillas deben coincidir.
 * (benefitrack.js repite estas reglas en vivo, pero la verdad la decide este
 * archivo: el JavaScript del navegador se puede saltar.)
 */

declare(strict_types=1);

namespace BenefitRack\Validacion;

final class ValidadorContrasena extends Validador
{
    private const MINIMO_CARACTERES = 8;

    /**
     * @param string $nueva        La contraseña nueva, leída con Peticion::campo() (igual que el ingreso).
     * @param string $confirmacion La misma contraseña, escrita otra vez.
     * @return ResultadoValidacion Errores en "nueva" (reglas) y/o "confirmar" (no coinciden).
     */
    public function validar(string $nueva, string $confirmacion): ResultadoValidacion
    {
        $resultado = new ResultadoValidacion();

        $faltantes = $this->reglasQueFaltan($nueva);
        if ($faltantes !== []) {
            $resultado->agregar('nueva', 'Aún no cumple todas las reglas: le falta ' . implode(', ', $faltantes) . '.');
        }
        if ($nueva !== $confirmacion) {
            $resultado->agregar('confirmar', 'Las contraseñas no coinciden. Escribe la misma en los dos campos.');
        }

        // Los datos solo se entregan si todo es válido: con errores, la contraseña no sale de aquí.
        return $resultado->esValido() ? new ResultadoValidacion(['nueva' => $nueva]) : $resultado;
    }

    /**
     * @return list<string> Lo que le falta a la contraseña, con las palabras de la maqueta.
     */
    private function reglasQueFaltan(string $nueva): array
    {
        $faltantes = [];
        if (mb_strlen($nueva) < self::MINIMO_CARACTERES) {
            $faltantes[] = 'mínimo 8 caracteres';
        }
        if (!$this->cumple('/\p{Lu}/u', $nueva)) {
            $faltantes[] = 'una letra mayúscula';
        }
        if (!$this->cumple('/\p{Ll}/u', $nueva)) {
            $faltantes[] = 'una letra minúscula';
        }
        if (!$this->cumple('/[0-9]/', $nueva)) {
            $faltantes[] = 'un número';
        }

        return $faltantes;
    }
}
