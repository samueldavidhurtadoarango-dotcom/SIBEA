<?php

/**
 * REPOSITORIO DE USUARIOS
 *
 * Paso 4 del recorrido: aquí vive el SQL de la tabla "usuarios": lo del inicio de sesión
 * (buscar por correo, contar intentos fallidos y desbloquear) y lo de la gestión de
 * aprendices (lista, crear, restablecer contraseña y cambiar estado).
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
use PDO;

class RepositorioUsuarios
{
    /** Condición de búsqueda: nombre completo, documento o correo (tres marcas ? con el mismo patrón). */
    private const FILTRO_BUSQUEDA = "(CONCAT(u.nombre, ' ', u.apellido) LIKE ? OR u.documento LIKE ? OR u.correo LIKE ?)";

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
     * Lo mínimo que el filtro de sesión necesita saber de una cuenta en CADA petición (M1).
     *
     * @param int $id Id del usuario de la sesión.
     * @return array{estado: string, rol: string, debe_cambiar_contrasena: int}|null Su estado, su rol y la marca de
     *         primer ingreso hoy en la base; null si la cuenta ya no existe.
     */
    public function estadoParaSesion(int $id): ?array
    {
        $consulta = $this->baseDatos->pdo()->prepare(
            'SELECT u.estado, r.nombre AS rol, u.debe_cambiar_contrasena
               FROM usuarios u
               JOIN roles r ON r.id_rol = u.id_rol
              WHERE u.id_usuario = ?'
        );
        $consulta->execute([$id]);
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

    /**
     * Una página de la lista de aprendices (nunca incluye administradores).
     *
     * @param string $buscar       Texto a buscar en nombre, documento o correo (vacío = todos).
     * @param int    $limite       Cuántas filas traer.
     * @param int    $desplazamiento Cuántas filas saltar (para las páginas siguientes).
     * @return list<array<string, mixed>> id_usuario, documento, nombre, apellido, correo, estado, ficha y programa.
     */
    public function listarAprendices(string $buscar, int $limite, int $desplazamiento): array
    {
        $consulta = $this->baseDatos->pdo()->prepare(
            'SELECT u.id_usuario, u.documento, u.nombre, u.apellido, u.correo, u.estado, u.ficha, p.nombre AS programa
               FROM usuarios u
               JOIN roles r ON r.id_rol = u.id_rol
               LEFT JOIN programas p ON p.id_programa = u.id_programa
              WHERE r.nombre = \'Aprendiz\' AND ' . self::FILTRO_BUSQUEDA . '
              ORDER BY u.nombre, u.apellido, u.id_usuario
              LIMIT ? OFFSET ?'
        );
        // Los números se enlazan como enteros: así llegan al LIMIT sin comillas.
        $patron = $this->patronDeBusqueda($buscar);
        $consulta->bindValue(1, $patron);
        $consulta->bindValue(2, $patron);
        $consulta->bindValue(3, $patron);
        $consulta->bindValue(4, $limite, PDO::PARAM_INT);
        $consulta->bindValue(5, $desplazamiento, PDO::PARAM_INT);
        $consulta->execute();

        return $consulta->fetchAll();
    }

    /**
     * @param string $buscar El mismo texto de listarAprendices().
     * @return int Cuántos aprendices coinciden (para calcular las páginas).
     */
    public function contarAprendices(string $buscar): int
    {
        $consulta = $this->baseDatos->pdo()->prepare(
            'SELECT COUNT(*)
               FROM usuarios u
               JOIN roles r ON r.id_rol = u.id_rol
              WHERE r.nombre = \'Aprendiz\' AND ' . self::FILTRO_BUSQUEDA
        );
        $patron = $this->patronDeBusqueda($buscar);
        $consulta->execute([$patron, $patron, $patron]);

        return (int) $consulta->fetchColumn();
    }

    /**
     * @param int $id Id del usuario.
     * @return array<string, mixed>|null id_usuario, nombre, apellido, documento y estado si es un aprendiz; null si no existe o es administrador.
     */
    public function buscarAprendiz(int $id): ?array
    {
        $consulta = $this->baseDatos->pdo()->prepare(
            'SELECT u.id_usuario, u.nombre, u.apellido, u.documento, u.correo, u.estado
               FROM usuarios u
               JOIN roles r ON r.id_rol = u.id_rol
              WHERE u.id_usuario = ? AND r.nombre = \'Aprendiz\''
        );
        $consulta->execute([$id]);
        $fila = $consulta->fetch();

        return is_array($fila) ? $fila : null;
    }

    /**
     * Los datos de la cuenta de un aprendiz para el formulario de postulación («Tus datos» y el contacto ya guardado).
     *
     * @param int $id Id del aprendiz.
     * @return array<string, mixed>|null nombre, apellido, documento, ficha, correo, programa (su nombre), telefono, direccion,
     *                                   fecha_nacimiento e id_eps; null si no existe o es administrador.
     */
    public function perfilAprendiz(int $id): ?array
    {
        $consulta = $this->baseDatos->pdo()->prepare(
            'SELECT u.nombre, u.apellido, u.documento, u.ficha, u.correo, p.nombre AS programa,
                    u.telefono, u.direccion, u.fecha_nacimiento, u.id_eps
               FROM usuarios u
               JOIN roles r ON r.id_rol = u.id_rol
               LEFT JOIN programas p ON p.id_programa = u.id_programa
              WHERE u.id_usuario = ? AND r.nombre = \'Aprendiz\''
        );
        $consulta->execute([$id]);
        $fila = $consulta->fetch();

        return is_array($fila) ? $fila : null;
    }

    /**
     * Guarda el contacto y la EPS que el aprendiz escribió al postularse. Lo hace él sobre su propia cuenta:
     * el trigger tr_usuario_validar solo deja cambiar estos datos a su dueño (@app_id_usuario) o a un administrador.
     *
     * @param int    $id              Id del aprendiz.
     * @param string $telefono        Solo dígitos.
     * @param string $direccion       Dirección de residencia.
     * @param string $fechaNacimiento «AAAA-MM-DD».
     * @param int    $idEps           Id de su EPS.
     * @return void
     */
    public function actualizarContacto(int $id, string $telefono, string $direccion, string $fechaNacimiento, int $idEps): void
    {
        $this->baseDatos->pdo()->prepare(
            'UPDATE usuarios SET telefono = ?, direccion = ?, fecha_nacimiento = ?, id_eps = ? WHERE id_usuario = ?'
        )->execute([$telefono, $direccion, $fechaNacimiento, $idEps, $id]);
    }

    /**
     * Guarda la cuenta donde se le pagan los beneficios: la crea si no tenía una o cambia la que ya tenía
     * (hay una sola por aprendiz: clave única uk_bancaria_usuario). Los triggers tr_bancaria_* exigen que
     * la conexión sea la del titular o la de un administrador.
     *
     * @param int    $id            Id del aprendiz.
     * @param int    $idBanco       Entidad bancaria.
     * @param int    $idTipoCuenta  Ahorros, corriente...
     * @param string $numeroCuenta  Solo dígitos.
     * @param string $titular       Nombre del titular.
     * @return void
     */
    public function guardarCuentaBancaria(int $id, int $idBanco, int $idTipoCuenta, string $numeroCuenta, string $titular): void
    {
        $this->baseDatos->pdo()->prepare(
            'INSERT INTO informacion_bancaria (id_usuario, id_banco, id_tipo_cuenta, numero_cuenta, titular)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE id_banco = ?, id_tipo_cuenta = ?, numero_cuenta = ?, titular = ?'
        )->execute([$id, $idBanco, $idTipoCuenta, $numeroCuenta, $titular, $idBanco, $idTipoCuenta, $numeroCuenta, $titular]);
    }

    /**
     * Crea la cuenta de un aprendiz. Si el documento o el correo ya existen, la base
     * lanza una PDOException (clave única, código 1062): el servicio la traduce.
     *
     * @param array<string, mixed> $datos documento, nombre, apellido, correo, id_programa y ficha (ya validados).
     * @param string               $hash  El hash de la contraseña temporal (nunca la contraseña).
     * @return int El id de la cuenta nueva.
     */
    public function crear(array $datos, string $hash): int
    {
        // debe_cambiar_contrasena = 1: la persona tiene que elegir su propia clave al primer ingreso.
        $this->baseDatos->pdo()->prepare(
            'INSERT INTO usuarios (documento, nombre, apellido, correo, password_hash, id_rol, id_programa, ficha, debe_cambiar_contrasena)
             VALUES (?, ?, ?, ?, ?, (SELECT id_rol FROM roles WHERE nombre = \'Aprendiz\'), ?, ?, 1)'
        )->execute([
            $datos['documento'], $datos['nombre'], $datos['apellido'], $datos['correo'], $hash, $datos['id_programa'], $datos['ficha'],
        ]);

        return (int) $this->baseDatos->pdo()->lastInsertId();
    }

    /**
     * Cambia la contraseña de un APRENDIZ por la que genera el sistema.
     *
     * La base sube debe_cambiar_contrasena a 1 sola, porque quien actúa (el administrador de la
     * conexión) no es el dueño de la cuenta. Con otro administrador como destino no se hace nada.
     *
     * @param int    $id   Id del aprendiz.
     * @param string $hash El hash de la contraseña temporal.
     * @return bool true si se cambió; false si no existe o no es aprendiz.
     */
    public function restablecerContrasena(int $id, string $hash): bool
    {
        $consulta = $this->baseDatos->pdo()->prepare(
            'UPDATE usuarios SET password_hash = ?
              WHERE id_usuario = ? AND id_rol = (SELECT id_rol FROM roles WHERE nombre = \'Aprendiz\')'
        );
        $consulta->execute([$hash, $id]);

        return $consulta->rowCount() === 1;
    }

    /**
     * Cambia el estado de una cuenta con el procedimiento de la base (README, sección 3):
     * la justificación es obligatoria y queda en la auditoría junto con quién lo hizo.
     *
     * @param int    $id            Id del usuario.
     * @param string $estado        Activo o Inactivo.
     * @param string $justificacion El motivo escrito por el administrador.
     * @return void
     */
    public function cambiarEstado(int $id, string $estado, string $justificacion): void
    {
        // conJustificacion pone @app_justificacion y la borra al terminar, pase lo que pase.
        $this->baseDatos->conJustificacion($justificacion, function () use ($id, $estado, $justificacion): void {
            $this->baseDatos->pdo()->prepare('CALL pr_cambiar_estado_usuario(?, ?, ?)')->execute([$id, $estado, $justificacion]);
        });
    }

    /**
     * Convierte lo que escribió la persona en un patrón LIKE seguro: los comodines % y _
     * se escapan para que se busquen como letras normales.
     */
    private function patronDeBusqueda(string $buscar): string
    {
        return '%' . addcslashes($buscar, '%_\\') . '%';
    }
}
