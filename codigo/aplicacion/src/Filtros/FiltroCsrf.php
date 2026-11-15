<?php

/**
 * FILTRO CSRF
 *
 * Paso 3 del recorrido. Toda petición que cambia datos (cualquiera que no sea
 * GET) debe traer el campo "_csrf" con el token de la sesión. Si falta o no
 * coincide, se corta aquí con un 403 y el controlador ni se entera.
 */

declare(strict_types=1);

namespace SIBEA\Filtros;

use SIBEA\Nucleo\Csrf;
use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Respuesta;
use SIBEA\Nucleo\Sesion;
use SIBEA\Nucleo\Vista;

final class FiltroCsrf implements Filtro
{
    /** Única ruta donde un token vencido se perdona con un aviso: la sesión pudo caducar mientras la persona escribía. */
    private const RUTA_DE_INGRESO = '/ingresar';

    /**
     * @param Vista       $vista  Para dibujar la página 403.
     * @param Sesion|null $sesion Si se entrega, en /ingresar un token vencido vuelve al formulario con un aviso (en vez de 403).
     */
    public function __construct(private readonly Vista $vista, private readonly ?Sesion $sesion = null)
    {
    }

    /**
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta|null null si es de solo lectura o trae un token válido; si no, la página 403.
     */
    public function aplicar(Peticion $peticion): ?Respuesta
    {
        // Las lecturas (GET) no cambian nada, no necesitan token.
        if ($peticion->metodo() === 'GET') {
            return null;
        }
        if (Csrf::esValido($peticion->campo('_csrf'))) {
            return null;
        }

        // En el formulario de ingreso lo normal es que la sesión haya caducado: se explica y se vuelve a empezar.
        if ($this->sesion !== null && $peticion->ruta() === self::RUTA_DE_INGRESO) {
            $this->sesion->mensaje('aviso', 'Tu sesión terminó. Vuelve a intentarlo.');

            return Respuesta::redirigir(self::RUTA_DE_INGRESO);
        }

        $mensaje = 'No pudimos comprobar que el envío vino de esta página. Vuelve atrás, recarga e intenta de nuevo.';

        return Respuesta::html($this->vista->mostrar('errores/403', ['mensaje' => $mensaje]), 403);
    }
}
