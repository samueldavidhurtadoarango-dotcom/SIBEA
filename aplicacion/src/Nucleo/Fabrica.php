<?php

/**
 * FÁBRICA (inyección de dependencias a mano, sin magia)
 *
 * Paso 3 y 4 del recorrido: Aplicacion le pide aquí cada controlador. La
 * fábrica sabe armar cada uno con lo que necesita (conexión a la base,
 * sesión, vista, repositorios, servicios). Todo está escrito a mano en un
 * "match": se lee de arriba abajo y se ve exactamente qué recibe cada clase.
 *
 * Los objetos compartidos (la conexión, la sesión, la vista) se crean la
 * primera vez que alguien los pide y se reutilizan durante esa petición:
 * una sola conexión PDO por petición.
 *
 * Para agregar un controlador nuevo se añade UNA línea al match de crear().
 */

declare(strict_types=1);

namespace BenefitRack\Nucleo;

use BenefitRack\Controladores\AccesoControlador;
use BenefitRack\Controladores\ContrasenaControlador;
use BenefitRack\Filtros\FiltroCsrf;
use BenefitRack\Filtros\FiltroPrimerIngreso;
use BenefitRack\Filtros\FiltroRol;
use BenefitRack\Filtros\FiltroSesion;
use BenefitRack\Repositorios\RepositorioUsuarios;
use BenefitRack\Servicios\ServicioAcceso;
use BenefitRack\Servicios\ServicioContrasena;
use InvalidArgumentException;

final class Fabrica
{
    private ?BaseDatos $baseDatos = null;
    private ?Sesion $sesion = null;
    private ?Vista $vista = null;

    /**
     * @param Configuracion $configuracion Los ajustes de la aplicación.
     * @param string        $carpetaVistas Carpeta donde están las vistas (aplicacion/vistas).
     * @param string        $ip            IP de quien hace la petición (para la auditoría).
     */
    public function __construct(
        private readonly Configuracion $configuracion,
        private readonly string $carpetaVistas,
        private readonly string $ip,
    ) {
    }

    /**
     * Crea un controlador con todo lo que necesita.
     *
     * Ejemplo de cómo se agrega uno (las tareas siguientes añaden su línea):
     *   ClaseControlador::class => new ClaseControlador($this->sesion(), $this->vista(), new Servicio(new Repositorio($this->baseDatos()))),
     *
     * @param string $claseControlador Nombre completo de la clase (Clase::class).
     * @return object El controlador listo para usar.
     * @throws InvalidArgumentException Si la clase no está en la lista.
     */
    public function crear(string $claseControlador): object
    {
        return match ($claseControlador) {
            // El ingreso usa baseDatosParaIngreso(): @app_id_usuario SIEMPRE en NULL (bloqueo y desbloqueo automáticos de la base).
            AccesoControlador::class => new AccesoControlador(
                $this->sesion(),
                $this->vista(),
                new ServicioAcceso(new RepositorioUsuarios($this->baseDatosParaIngreso()))
            ),
            // El cambio de contraseña usa baseDatos(): la sesión fija @app_id_usuario = la propia persona,
            // y solo así la base baja debe_cambiar_contrasena a 0.
            ContrasenaControlador::class => new ContrasenaControlador(
                $this->sesion(),
                $this->vista(),
                new ServicioContrasena(new RepositorioUsuarios($this->baseDatos()))
            ),
            default => throw new InvalidArgumentException("No sé crear el controlador: $claseControlador"),
        };
    }

    /**
     * @return Sesion La sesión de esta petición (siempre la misma).
     */
    public function sesion(): Sesion
    {
        return $this->sesion ??= new Sesion();
    }

    /**
     * @return Vista La vista de esta petición (siempre la misma).
     */
    public function vista(): Vista
    {
        return $this->vista ??= new Vista($this->carpetaVistas, $this->sesion());
    }

    /**
     * Conexión para el trabajo normal: la base queda sabiendo que actúa la
     * persona de la sesión (o NULL si es un visitante). Se abre solo cuando
     * alguien la necesita y es una sola por petición.
     *
     * @return BaseDatos La conexión de esta petición (siempre la misma).
     */
    public function baseDatos(): BaseDatos
    {
        $conexion = $this->conexion();
        $conexion->fijarUsuario($this->sesion()->idUsuario(), $this->ip);

        return $conexion;
    }

    /**
     * Conexión para el flujo de inicio de sesión: SIEMPRE con @app_id_usuario = NULL,
     * aunque el navegador ya tenga una sesión abierta (dos pestañas, botón «Atrás»).
     * Si no, el contador de intentos fallidos y el bloqueo (README de la base, sección 3)
     * correrían a nombre de OTRA persona y la base los rechazaría.
     * El arm de AccesoControlador en esta clase DEBE usar este método, no baseDatos().
     *
     * @return BaseDatos La misma conexión de la petición, ya con el usuario en NULL.
     */
    public function baseDatosParaIngreso(): BaseDatos
    {
        $conexion = $this->conexion();
        $conexion->fijarUsuario(null, $this->ip);

        return $conexion;
    }

    /** Abre la conexión la primera vez y después reutiliza la misma. */
    private function conexion(): BaseDatos
    {
        return $this->baseDatos ??= BaseDatos::conectar($this->configuracion);
    }

    /**
     * @return FiltroSesion Filtro "debe haber iniciado sesión".
     */
    public function filtroSesion(): FiltroSesion
    {
        return new FiltroSesion($this->sesion());
    }

    /**
     * @param string $rol 'Administrador' o 'Aprendiz'.
     * @return FiltroRol Filtro "debe tener este rol".
     */
    public function filtroRol(string $rol): FiltroRol
    {
        return new FiltroRol($this->sesion(), $this->vista(), $rol);
    }

    /**
     * @return FiltroCsrf Filtro "todo envío debe traer el token".
     */
    public function filtroCsrf(): FiltroCsrf
    {
        return new FiltroCsrf($this->vista(), $this->sesion());
    }

    /**
     * @return FiltroPrimerIngreso Filtro "primero cambia tu contraseña temporal".
     */
    public function filtroPrimerIngreso(): FiltroPrimerIngreso
    {
        return new FiltroPrimerIngreso($this->sesion());
    }
}
