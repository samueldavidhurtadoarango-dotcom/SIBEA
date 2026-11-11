<?php

/**
 * REPOSITORIO DE USUARIOS
 *
 * Paso 4 del recorrido: aquí vive el SQL de la tabla "usuarios". Por ahora solo lo
 * que necesita el inicio de sesión (buscar por correo, contar intentos fallidos y
 * desbloquear). Las tareas siguientes agregan más métodos.
 *
 * IMPORTANTE: el bloqueo tras 3 intentos y el desbloqueo a los 15 minutos los hace
 * la BASE DE DATOS (README de base-de-datos, sección 3), siempre que la conexión
 * tenga @app_id_usuario = NULL. Esta clase solo "avisa" con un UPDATE.
 *
 * No es "final" a propósito: las pruebas la reemplazan por un repositorio falso.
 */

declare(strict_types=1);

namespace BenefitRack\Repositorios;

use BenefitRack\Nucleo\BaseDatos;

class RepositorioUsuarios
{
    /**
     * @param BaseDatos $baseDatos La conexión de esta petición (en el ingreso, con @app_id_usuario NULL).
     */
    public function __construct(private readonly BaseDatos $baseDatos)
    {
    }

    /**
     * Busca a una persona por su correo (la columna compara sin distinguir mayúsculas).
     *
     * @param string $correo El correo ya limpio.
     * @return array<string, mixed>|null id_usuario, nombre, password_hash, estado, rol,
     *                                   intentos_fallidos, fecha_bloqueo y debe_cambiar_contrasena; null si no existe.
     */
    public function buscarPorCorreo(string $correo): ?array
    {
        $consulta = $this->baseDatos->pdo()->prepare(
            'SELECT u.id_usuario, u.nombre, u.password_hash, u.estado, r.nombre AS rol,
                    u.intentos_fallidos, u.fecha_bloqueo, u.debe_cambiar_contrasena
               FROM usuarios u
               JOIN roles r ON r.id_rol = u.id_rol
              WHERE u.correo = ?'
        );
        $consulta->execute([$correo]);
        $fila = $consulta->fetch();

        return is_array($fila) ? $fila : null;
    }

    /**
     * Suma un intento fallido. Si llega a 3, la misma sentencia pasa la cuenta a
     * 'Bloqueado' (la base reconoce ese cambio como automático y guarda la fecha).
     *
     * @param int $id Id del usuario.
     * @return void
     */
    public function registrarIntentoFallido(int $id): void
    {
        // En un UPDATE, MariaDB aplica las asignaciones de izquierda a derecha: cuando se
        // evalúa "estado", intentos_fallidos ya tiene el valor sumado.
        $this->baseDatos->pdo()->prepare(
            "UPDATE usuarios
                SET intentos_fallidos = intentos_fallidos + 1,
                    estado = IF(intentos_fallidos >= 3, 'Bloqueado', estado)
              WHERE id_usuario = ? AND estado = 'Activo'"
        )->execute([$id]);
    }

    /**
     * Deja los intentos fallidos en cero (tras un ingreso correcto).
     *
     * @param int $id Id del usuario.
     * @return void
     */
    public function reiniciarIntentos(int $id): void
    {
        $this->baseDatos->pdo()->prepare('UPDATE usuarios SET intentos_fallidos = 0 WHERE id_usuario = ?')->execute([$id]);
    }

    /**
     * Intenta desbloquear la cuenta si ya pasaron 15 minutos desde el bloqueo.
     *
     * La regla de los 15 minutos la impone la base de datos; aquí se repite en el WHERE
     * solo para no intentar un UPDATE que la base rechazaría con un error.
     *
     * @param int $id Id del usuario.
     * @return bool true si la cuenta quedó desbloqueada.
     */
    public function desbloquearSiPasaronQuinceMinutos(int $id): bool
    {
        $consulta = $this->baseDatos->pdo()->prepare(
            "UPDATE usuarios SET estado = 'Activo'
              WHERE id_usuario = ? AND estado = 'Bloqueado'
                AND fecha_bloqueo + INTERVAL 15 MINUTE <= SYSDATE()"
        );
        $consulta->execute([$id]);

        return $consulta->rowCount() === 1;
    }

    /**
     * Guarda la contraseña nueva (solo el hash) de una persona.
     *
     * No toca debe_cambiar_contrasena: la base lo baja a 0 sola cuando la conexión tiene
     * @app_id_usuario igual a este id (la persona cambia SU contraseña) y lo sube a 1 si otro la cambia.
     *
     * @param int    $id   Id del usuario.
     * @param string $hash Resultado de password_hash().
     * @return void
     */
    public function cambiarContrasena(int $id, string $hash): void
    {
        $this->baseDatos->pdo()->prepare('UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?')->execute([$hash, $id]);
    }
}
