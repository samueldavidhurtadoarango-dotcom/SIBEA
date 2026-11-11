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

use BenefitRack\Filtros\FiltroCsrf;
use BenefitRack\Filtros\FiltroPrimerIngreso;
use BenefitRack\Filtros\FiltroRol;
use BenefitRack\Filtros\FiltroSesion;
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
     *   AccesoControlador::class => new AccesoControlador($this->sesion(), $this->vista(), new UsuarioRepositorio($this->baseDatos())),
     *
     * @param string $claseControlador Nombre completo de la clase (Clase::class).
     * @return object El controlador listo para usar.
     * @throws InvalidArgumentException Si la clase no está en la lista.
     */
    public function crear(string $claseControlador): object
    {
        return match ($claseControlador) {
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
     * La conexión se abre solo cuando alguien la necesita. En cuanto se abre,
     * se le dice a la base quién actúa: la persona de la sesión o NULL si es
     * un visitante o está iniciando sesión.
     *
     * @return BaseDatos La conexión de esta petición (siempre la misma).
     */
    public function baseDatos(): BaseDatos
    {
        if ($this->baseDatos === null) {
            $this->baseDatos = BaseDatos::conectar($this->configuracion);
            $this->baseDatos->fijarUsuario($this->sesion()->idUsuario(), $this->ip);
        }

        return $this->baseDatos;
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
        return new FiltroCsrf($this->vista());
    }

    /**
     * @return FiltroPrimerIngreso Filtro "primero cambia tu contraseña temporal".
     */
    public function filtroPrimerIngreso(): FiltroPrimerIngreso
    {
        return new FiltroPrimerIngreso($this->sesion());
    }
}
