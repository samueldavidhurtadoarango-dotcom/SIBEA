<?php

/**
 * FILTRO DE SESIÓN
 *
 * Paso 3 del recorrido. Se pone en toda ruta privada. En cada petición:
 *   1. Si la persona no ha ingresado, la manda a /ingresar con un aviso.
 *   2. Si su sesión caducó (30 minutos sin actividad u 8 horas desde que ingresó), la cierra.
 *   3. Revalida contra la base de datos (M1): si la cuenta ya no existe, no está Activa o cambió de rol,
 *      cierra la sesión; si la base dice que debe cambiar la contraseña, actualiza la sesión.
 * Así, desactivar a alguien surte efecto en su siguiente petición y no cuando "se le acabe" la sesión.
 */

declare(strict_types=1);

namespace SIBEA\Filtros;

use Closure;
use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Respuesta;
use SIBEA\Nucleo\Sesion;
use SIBEA\Repositorios\RepositorioUsuarios;

final class FiltroSesion implements Filtro
{
    private const AVISO_EXPIRO = 'Tu sesión expiró por seguridad. Vuelve a ingresar.';
    private const AVISO_CUENTA = 'Tu cuenta no está activa. Habla con el administrador.';

    /**
     * @param Sesion                        $sesion             La sesión de esta petición.
     * @param Closure():RepositorioUsuarios $repositorio        Entrega el repositorio (así la conexión a la base solo se abre si hace falta).
     * @param int                           $minutosInactividad Minutos sin actividad que cierran la sesión.
     * @param int                           $horasMaximas       Horas desde el ingreso tras las cuales la sesión se cierra siempre.
     */
    public function __construct(
        private readonly Sesion $sesion,
        private readonly Closure $repositorio,
        private readonly int $minutosInactividad = 30,
        private readonly int $horasMaximas = 8,
    ) {
    }

    /**
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta|null null si la sesión es válida; si no, una redirección a /ingresar con el aviso.
     */
    public function aplicar(Peticion $peticion): ?Respuesta
    {
        $id = $this->sesion->idUsuario();
        if ($id === null) {
            $this->sesion->mensaje('aviso', 'Tu sesión terminó. Ingresa de nuevo para continuar.');

            return Respuesta::redirigir('/ingresar');
        }

        // B1: la caducidad se revisa primero y sin tocar la base de datos.
        if ($this->sesion->haExpirado($this->minutosInactividad * 60, $this->horasMaximas * 3600)) {
            return $this->cerrar(self::AVISO_EXPIRO);
        }

        // M1: la base de datos manda. Se compara lo que dice la sesión con lo que hay hoy en la cuenta.
        $cuenta = ($this->repositorio)()->estadoParaSesion($id);
        if ($cuenta === null || $cuenta['estado'] !== 'Activo' || $cuenta['rol'] !== $this->sesion->rol()) {
            return $this->cerrar(self::AVISO_CUENTA);
        }

        $this->sesion->fijarDebeCambiar((int) $cuenta['debe_cambiar_contrasena'] === 1);
        $this->sesion->registrarActividad();

        return null;
    }

    /** Cierra la sesión dejando el aviso y manda a /ingresar. */
    private function cerrar(string $aviso): Respuesta
    {
        $this->sesion->cerrarConAviso($aviso);

        return Respuesta::redirigir('/ingresar');
    }
}
