<?php

/**
 * FILTRO DE PRIMER INGRESO
 *
 * Paso 3 del recorrido. Una cuenta nueva (o restablecida por un administrador)
 * trae una contraseña temporal: mientras la persona no la cambie, solo puede
 * ver la pantalla de cambio de contraseña. Este filtro NO se pone en la ruta
 * /contrasena/nueva (ni en /salir), o la persona quedaría atrapada en un ciclo.
 */

declare(strict_types=1);

namespace BenefitRack\Filtros;

use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Nucleo\Sesion;

final class FiltroPrimerIngreso implements Filtro
{
    /**
     * @param Sesion $sesion La sesión de esta petición.
     */
    public function __construct(private readonly Sesion $sesion)
    {
    }

    /**
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta|null null si no debe cambiar la contraseña; si debe, redirección a /contrasena/nueva.
     */
    public function aplicar(Peticion $peticion): ?Respuesta
    {
        if (!$this->sesion->debeCambiarContrasena()) {
            return null;
        }

        return Respuesta::redirigir('/contrasena/nueva');
    }
}
