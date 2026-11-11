<?php

/**
 * FILTRO CSRF
 *
 * Paso 3 del recorrido. Toda petición que cambia datos (cualquiera que no sea
 * GET) debe traer el campo "_csrf" con el token de la sesión. Si falta o no
 * coincide, se corta aquí con un 403 y el controlador ni se entera.
 */

declare(strict_types=1);

namespace BenefitRack\Filtros;

use BenefitRack\Nucleo\Csrf;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Nucleo\Vista;

final class FiltroCsrf implements Filtro
{
    /**
     * @param Vista $vista Para dibujar la página 403.
     */
    public function __construct(private readonly Vista $vista)
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

        $mensaje = 'No pudimos comprobar que el envío vino de esta página. Vuelve atrás, recarga e intenta de nuevo.';

        return Respuesta::html($this->vista->mostrar('errores/403', ['mensaje' => $mensaje]), 403);
    }
}
