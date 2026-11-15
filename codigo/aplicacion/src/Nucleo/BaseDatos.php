<?php

/**
 * BASE DE DATOS (conexión)
 *
 * Paso 4 del recorrido: la usan los repositorios para hablar con MariaDB.
 * Se crea UNA conexión por petición (la Fabrica la reparte). Siempre entra con
 * el usuario sibea_app (nunca root), y después de conectar le dice a la
 * base quién es la persona que actúa (@app_id_usuario) para que el historial
 * y la auditoría guarden el autor correcto.
 */

declare(strict_types=1);

namespace SIBEA\Nucleo;

use PDO;

final class BaseDatos
{
    private function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Abre la conexión con los datos de la configuración.
     *
     * @param Configuracion $configuracion Debe tener bd.host, bd.puerto, bd.nombre, bd.usuario y bd.clave.
     * @return BaseDatos La conexión lista para usar.
     */
    public static function conectar(Configuracion $configuracion): BaseDatos
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $configuracion->obtener('bd.host'),
            $configuracion->obtener('bd.puerto'),
            $configuracion->obtener('bd.nombre')
        );

        $pdo = new PDO($dsn, (string) $configuracion->obtener('bd.usuario'), (string) $configuracion->obtener('bd.clave'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,   // los errores lanzan excepción (no se ignoran)
            PDO::ATTR_EMULATE_PREPARES => false,           // consultas preparadas de verdad: el dato viaja aparte del SQL (anti inyección SQL)
            PDO::ATTR_PERSISTENT => false,                 // sin conexiones compartidas: las variables @app_* no se heredan de otra persona
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return new self($pdo);
    }

    /**
     * @return PDO La conexión, para que los repositorios preparen sus consultas.
     */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Le dice a la base quién actúa y desde dónde (para el historial y la auditoría).
     *
     * @param int|null $idUsuario Id de quien inició sesión; null en el flujo de inicio de sesión
     *                            (así la base reconoce los bloqueos como automáticos).
     * @param string   $ip        IP de quien hace la petición.
     * @return void
     */
    public function fijarUsuario(?int $idUsuario, string $ip): void
    {
        // Se manda SIEMPRE, también con null: si no, la conexión podría conservar el id anterior.
        $this->pdo->prepare('SET @app_id_usuario = ?, @app_ip = ?')->execute([$idUsuario, $ip]);
    }

    /**
     * Ejecuta una acción con una justificación escrita (la base la exige para
     * cambiar el rol o el estado de una cuenta).
     *
     * @param string   $texto  El motivo que escribió el administrador.
     * @param callable $accion Lo que se hace mientras la justificación está puesta.
     * @return mixed Lo que devuelva la acción.
     */
    public function conJustificacion(string $texto, callable $accion): mixed
    {
        $this->pdo->prepare('SET @app_justificacion = ?')->execute([$texto]);

        try {
            return $accion();
        } finally {
            // Pase lo que pase (incluso una excepción) la justificación se borra,
            // para que no se use por error en la siguiente operación.
            $this->pdo->exec('SET @app_justificacion = NULL');
        }
    }
}
