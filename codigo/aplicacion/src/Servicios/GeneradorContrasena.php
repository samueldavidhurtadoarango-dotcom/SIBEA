<?php

/**
 * GENERADOR DE CONTRASEÑA TEMPORAL
 *
 * Paso 4 del recorrido: cuando Bienestar crea una cuenta o restablece una contraseña,
 * el sistema inventa una contraseña temporal distinta para cada aprendiz. El aprendiz
 * debe cambiarla en su primer ingreso. Aquí solo se genera el texto; guardar su hash
 * (nunca la contraseña) es trabajo del ServicioUsuarios.
 */

declare(strict_types=1);

namespace BenefitRack\Servicios;

final class GeneradorContrasena
{
    /** Largo de la contraseña temporal. */
    private const LARGO = 10;

    /** Letras y números SIN los que se confunden al leerlos: 0, O, 1, l, I. */
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    /**
     * Inventa una contraseña temporal.
     *
     * @return string 10 caracteres del alfabeto permitido.
     */
    public function generar(): string
    {
        $ultimo = strlen(self::ALFABETO) - 1;
        $contrasena = '';
        for ($i = 0; $i < self::LARGO; $i++) {
            // Seguridad: random_int usa el generador criptográfico del sistema. rand() o mt_rand()
            // son predecibles: alguien podría adivinar las contraseñas siguientes.
            $contrasena .= self::ALFABETO[random_int(0, $ultimo)];
        }

        return $contrasena;
    }
}
