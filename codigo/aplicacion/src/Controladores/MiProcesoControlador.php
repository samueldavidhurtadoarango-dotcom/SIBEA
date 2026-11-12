<?php

/**
 * CONTROLADOR DE «MI PROCESO» (la pantalla de inicio del aprendiz)
 *
 * Paso 4 del recorrido: atiende GET /mi-proceso. Pide el resumen a ServicioMiProceso y dibuja la pantalla dentro
 * del marco del aprendiz. No tiene SQL ni reglas. Los filtros de sesión, rol Aprendiz y primer ingreso ya
 * pasaron antes de llegar aquí (ver rutas.php); por eso el id del aprendiz sale de la sesión, nunca de la URL.
 */

declare(strict_types=1);

namespace BenefitRack\Controladores;

use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Nucleo\Sesion;
use BenefitRack\Nucleo\Vista;
use BenefitRack\Servicios\ServicioMiProceso;

final class MiProcesoControlador
{
    /**
     * @param Sesion             $sesion   Dice quién es el aprendiz.
     * @param Vista              $vista    Dibuja la pantalla.
     * @param ServicioMiProceso  $servicio Arma el resumen.
     */
    public function __construct(
        private readonly Sesion $sesion,
        private readonly Vista $vista,
        private readonly ServicioMiProceso $servicio,
    ) {
    }

    /**
     * GET /mi-proceso : las convocatorias abiertas y las postulaciones del aprendiz.
     *
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta La pantalla «Mi proceso» (sin caché: cambia cuando Bienestar decide).
     */
    public function ver(Peticion $peticion): Respuesta
    {
        // Seguridad: el id viene de la sesión, no de lo que mande el navegador.
        $resumen = $this->servicio->resumen((int) $this->sesion->idUsuario());

        return Respuesta::html($this->vista->mostrar('mi_proceso/ver', $resumen + ['titulo' => 'Mi proceso'], 'aprendiz'))->sinCache();
    }
}
