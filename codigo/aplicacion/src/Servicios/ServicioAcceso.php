<?php

/**
 * SERVICIO DE ACCESO (reglas del inicio de sesión)
 *
 * Paso 4 del recorrido: el controlador le entrega el correo y la clave y este servicio
 * decide si la persona entra o por qué no (datos incorrectos, cuenta bloqueada o inactiva).
 * No sabe nada de HTTP ni de HTML: devuelve un ResultadoAcceso.
 *
 * El conteo de intentos, el bloqueo a los 3 fallos y el desbloqueo a los 15 minutos
 * los hace la base de datos. Este servicio solo registra cada intento y vuelve a leer el estado.
 * La conexión del repositorio debe tener @app_id_usuario = NULL: la Fabrica se encarga
 * con baseDatosParaIngreso() (si no, la base rechazaría el bloqueo automático).
 */

declare(strict_types=1);

namespace SIBEA\Servicios;

use SIBEA\Repositorios\RepositorioUsuarios;

/** Lo que devuelve ServicioAcceso::ingresar(): entró, o falló con el nombre de la pantalla a mostrar. */
final class ResultadoAcceso
{
    private function __construct(
        public readonly bool $exito,
        public readonly int $idUsuario = 0,
        public readonly string $rol = '',
        public readonly bool $debeCambiar = false,
        public readonly string $nombre = '',
        public readonly string $estadoVista = '',
    ) {
    }

    /**
     * @param int    $id          Id del usuario que entró.
     * @param string $rol         'Administrador' o 'Aprendiz'.
     * @param bool   $debeCambiar true si todavía tiene la contraseña temporal.
     * @param string $nombre      Nombre para la barra superior.
     * @return ResultadoAcceso Ingreso correcto.
     */
    public static function exito(int $id, string $rol, bool $debeCambiar, string $nombre = ''): ResultadoAcceso
    {
        return new self(true, $id, $rol, $debeCambiar, $nombre);
    }

    /**
     * @param string $estadoVista 'datos-incorrectos', 'cuenta-bloqueada' o 'cuenta-inactiva' (estados de la maqueta).
     * @return ResultadoAcceso Ingreso rechazado.
     */
    public static function fallo(string $estadoVista): ResultadoAcceso
    {
        return new self(false, estadoVista: $estadoVista);
    }
}

final class ServicioAcceso
{
    /**
     * Hash bcrypt de una clave aleatoria que nadie conoce (costo 10, igual que los reales).
     * Se usa cuando el correo no existe, para que verificarlo tarde lo mismo que con un usuario real.
     */
    private const HASH_FALSO = '$2y$10$tuqb4rnJ5zvIQjqC4MASdOIqnYeLaDkP1PZ5oNAO59JKcY7ZPB9Mq';

    /**
     * @param RepositorioUsuarios $usuarios Acceso a la tabla usuarios.
     */
    public function __construct(private readonly RepositorioUsuarios $usuarios)
    {
    }

    /**
     * Decide si alguien puede entrar.
     *
     * @param string $correo Correo escrito (se compara sin mayúsculas ni espacios).
     * @param string $clave  Contraseña escrita.
     * @return ResultadoAcceso exito(...) o fallo('datos-incorrectos' | 'cuenta-bloqueada' | 'cuenta-inactiva').
     */
    public function ingresar(string $correo, string $clave): ResultadoAcceso
    {
        $correo = strtolower(trim($correo));
        $usuario = $this->usuarios->buscarPorCorreo($correo);

        if ($usuario === null) {
            // Seguridad: se hace el mismo trabajo de verificar un hash para que el tiempo de
            // respuesta no delate si el correo existe, y se responde IGUAL que con clave errada.
            password_verify($clave, self::HASH_FALSO);

            return ResultadoAcceso::fallo('datos-incorrectos');
        }

        $usuario = $this->desbloquearSiVencio($usuario, $correo);
        if ($usuario['estado'] === 'Bloqueado') {
            return ResultadoAcceso::fallo('cuenta-bloqueada');
        }

        if (!password_verify($clave, (string) $usuario['password_hash'])) {
            return $this->registrarFallo($usuario, $correo);
        }

        return $this->entrar($usuario);
    }

    /**
     * Si la cuenta está bloqueada y ya pasaron 15 minutos, la base la desbloquea y se vuelve a leer.
     *
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    private function desbloquearSiVencio(array $usuario, string $correo): array
    {
        if ($usuario['estado'] !== 'Bloqueado') {
            return $usuario;
        }
        if (!$this->usuarios->desbloquearSiPasaronQuinceMinutos((int) $usuario['id_usuario'])) {
            return $usuario;
        }

        return $this->usuarios->buscarPorCorreo($correo) ?? $usuario;
    }

    /**
     * Clave errada: se cuenta el intento (solo si la cuenta está activa) y se vuelve a leer
     * el estado, porque el tercer fallo bloquea la cuenta en ese mismo instante.
     *
     * @param array<string, mixed> $usuario
     */
    private function registrarFallo(array $usuario, string $correo): ResultadoAcceso
    {
        if ($usuario['estado'] !== 'Activo') {
            // Cuenta inactiva: a quien no sabe la clave no se le revela su estado.
            return ResultadoAcceso::fallo('datos-incorrectos');
        }

        $this->usuarios->registrarIntentoFallido((int) $usuario['id_usuario']);
        $despues = $this->usuarios->buscarPorCorreo($correo);
        $bloqueada = ($despues['estado'] ?? '') === 'Bloqueado';

        return ResultadoAcceso::fallo($bloqueada ? 'cuenta-bloqueada' : 'datos-incorrectos');
    }

    /**
     * Clave correcta: si la cuenta está inactiva no entra; si no, se limpian los intentos.
     *
     * @param array<string, mixed> $usuario
     */
    private function entrar(array $usuario): ResultadoAcceso
    {
        if ($usuario['estado'] === 'Inactivo') {
            return ResultadoAcceso::fallo('cuenta-inactiva');
        }

        if ((int) $usuario['intentos_fallidos'] > 0) {
            $this->usuarios->reiniciarIntentos((int) $usuario['id_usuario']);
        }

        return ResultadoAcceso::exito(
            (int) $usuario['id_usuario'],
            (string) $usuario['rol'],
            (int) $usuario['debe_cambiar_contrasena'] === 1,
            (string) $usuario['nombre']
        );
    }
}
