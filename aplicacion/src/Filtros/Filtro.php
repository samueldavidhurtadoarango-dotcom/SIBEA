<?php

/**
 * FILTRO (interfaz)
 *
 * Paso 3 del recorrido: antes de llamar al controlador, la aplicación pasa la
 * petición por los filtros de la ruta (¿hay sesión? ¿es administrador? ¿trae
 * el token CSRF?). Cada filtro implementa esta interfaz.
 */

declare(strict_types=1);

namespace BenefitRack\Filtros;

use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;

interface Filtro
{
    /**
     * Revisa la petición.
     *
     * @param Peticion $peticion La petición que se está atendiendo.
     * @return Respuesta|null null para dejarla pasar; una Respuesta (redirección, 403...)
     *                        para cortar el recorrido ahí.
     */
    public function aplicar(Peticion $peticion): ?Respuesta;
}
