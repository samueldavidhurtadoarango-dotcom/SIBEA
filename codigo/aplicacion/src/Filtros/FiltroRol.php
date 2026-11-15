<?php

/**
 * FILTRO DE ROL
 *
 * Paso 3 del recorrido, después de FiltroSesion. Deja pasar solo a quien tenga
 * el rol que pide la ruta ('Administrador' o 'Aprendiz'). Un aprendiz que
 * escribe a mano una dirección de administrador recibe la página 403.
 */

declare(strict_types=1);

namespace SIBEA\Filtros;

use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Respuesta;
use SIBEA\Nucleo\Sesion;
use SIBEA\Nucleo\Vista;

final class FiltroRol implements Filtro
{
    /**
     * @param Sesion $sesion       La sesión de esta petición.
     * @param Vista  $vista        Para dibujar la página 403.
     * @param string $rolRequerido 'Administrador' o 'Aprendiz'.
     */
    public function __construct(
        private readonly Sesion $sesion,
        private readonly Vista $vista,
        private readonly string $rolRequerido,
    ) {
    }

    /**
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta|null null si el rol coincide; si no, la página 403.
     */
    public function aplicar(Peticion $peticion): ?Respuesta
    {
        if ($this->sesion->rol() === $this->rolRequerido) {
            return null;
        }

        // Seguridad: la verificación se hace en el servidor en cada petición;
        // esconder el enlace en el menú no protege nada por sí solo.
        return Respuesta::html($this->vista->mostrar('errores/403'), 403);
    }
}
