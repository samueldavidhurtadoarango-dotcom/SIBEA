<?php

/**
 * CSRF (token anti-falsificación de formularios)
 *
 * Paso 3 del recorrido (lo usa FiltroCsrf) y paso 5 (las vistas lo escriben
 * en cada formulario como campo oculto "_csrf").
 *
 * El problema: otra página web podría hacer que TU navegador envíe un
 * formulario a SIBEA sin que lo notes (el navegador adjunta tu cookie
 * de sesión solo). El remedio: cada formulario lleva un código secreto que
 * solo nuestro servidor y tu sesión conocen; una página ajena no puede
 * adivinarlo y por eso su envío se rechaza.
 */

declare(strict_types=1);

namespace SIBEA\Nucleo;

final class Csrf
{
    /** Nombre de la casilla de $_SESSION donde vive el token. */
    private const CLAVE = '_csrf';

    /**
     * Devuelve el token de la sesión; si todavía no hay uno, lo crea.
     * Existe también en la sesión anónima, así el formulario de ingreso lo tiene.
     *
     * @return string 64 caracteres hexadecimales.
     */
    public static function token(): string
    {
        if (!isset($_SESSION[self::CLAVE]) || !is_string($_SESSION[self::CLAVE])) {
            // random_bytes usa el generador seguro del sistema: no se puede adivinar.
            $_SESSION[self::CLAVE] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CLAVE];
    }

    /**
     * Comprueba el token que llegó en un formulario.
     *
     * @param string $enviado Lo que trajo el campo "_csrf" (vacío si no vino).
     * @return bool true solo si coincide exactamente con el token de la sesión.
     */
    public static function esValido(string $enviado): bool
    {
        $guardado = $_SESSION[self::CLAVE] ?? null;
        if (!is_string($guardado) || $guardado === '' || $enviado === '') {
            return false;
        }

        // hash_equals compara en tiempo constante: un atacante no puede ir
        // descubriendo el token letra por letra midiendo cuánto tarda la respuesta.
        return hash_equals($guardado, $enviado);
    }
}
