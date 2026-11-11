<?php

/**
 * FILTRO DE SESIÓN
 *
 * Paso 3 del recorrido. Se pone en toda ruta privada: si la persona no ha
 * ingresado (o su sesión terminó), la manda a /ingresar con un aviso.
 */

declare(strict_types=1);

namespace BenefitRack\Filtros;

use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Nucleo\Sesion;

final class FiltroSesion implements Filtro
{
    /**
     * @param Sesion $sesion La sesión de esta petición.
     */
    public function __construct(private readonly Sesion $sesion)
    {
    }

    /**
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta|null null si hay sesión; si no, una redirección a /ingresar.
     */
    public function aplicar(Peticion $peticion): ?Respuesta
    {
        if ($this->sesion->idUsuario() !== null) {
            return null;
        }

        $this->sesion->mensaje('aviso', 'Tu sesión terminó. Ingresa de nuevo para continuar.');

        return Respuesta::redirigir('/ingresar');
    }
}
