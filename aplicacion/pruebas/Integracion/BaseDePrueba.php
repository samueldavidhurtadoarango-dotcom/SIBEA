<?php

/**
 * BASE DE PRUEBA (clase de apoyo para las pruebas de integración)
 *
 * Las pruebas de integración hablan con una MariaDB de verdad, pero SOLO con
 * una instancia temporal en el puerto de prueba (variable BR_PRUEBA_BD_PUERTO,
 * normalmente 3398). Nunca con la base real de XAMPP.
 * Si la variable no existe, la prueba se salta diciendo por qué.
 *
 * Antes de cada clase de pruebas se recrea benefitrack_v2 con los tres scripts
 * (esquema, datos iniciales y usuario de la aplicación), así cada clase parte
 * de una base limpia. Los ayudantes crearAdmin(), crearAprendiz() y
 * crearConvocatoriaActiva() insertan datos de ejemplo como root, igual que el
 * README de la base explica para el primer administrador.
 */

declare(strict_types=1);

namespace BenefitRack\Pruebas\Integracion;

use BenefitRack\Nucleo\BaseDatos;
use BenefitRack\Nucleo\Configuracion;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class BaseDePrueba extends TestCase
{
    /** Contraseña en claro de todos los usuarios que crean los ayudantes. */
    public const CLAVE_PRUEBA = 'Clave-de-prueba-1';

    /** Único puerto permitido: la instancia temporal. La recarga BORRA la base, así que nunca otro (3306 es la real de XAMPP). */
    private const PUERTO_PERMITIDO = '3398';

    private static ?PDO $pdoRoot = null;

    /**
     * Prepara la base temporal: si no hay puerto de prueba salta la clase;
     * si lo hay, recrea benefitrack_v2 desde los scripts SQL.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        if (self::puerto() === null || self::clienteMysql() === null) {
            self::markTestSkipped('Falta BR_PRUEBA_BD_PUERTO o BR_PRUEBA_MYSQL: no hay base temporal para las pruebas de integración.');
        }

        if (self::puerto() !== self::PUERTO_PERMITIDO) {
            // Falla fuerte (no se salta): un puerto equivocado podría borrar la base real.
            throw new RuntimeException('BR_PRUEBA_BD_PUERTO debe ser ' . self::PUERTO_PERMITIDO . ' (la base temporal de pruebas). Se rechaza el puerto ' . self::puerto() . ' para no borrar la base real.');
        }

        $carpeta = dirname(__DIR__, 3) . '/base-de-datos/';
        foreach (['benefitrack_v2.sql', 'datos_iniciales.sql', 'usuario_aplicacion.sql'] as $script) {
            self::cargarScript($carpeta . $script);
        }
        self::$pdoRoot = null; // la base se recreó: la conexión vieja ya no sirve
    }

    /**
     * @return Configuracion La configuración de la aplicación apuntando a la base temporal.
     */
    protected function configuracionDePrueba(): Configuracion
    {
        return new Configuracion(['bd' => [
            'host' => '127.0.0.1',
            'puerto' => (int) self::puerto(),
            'nombre' => 'benefitrack_v2',
            'usuario' => 'benefitrack_app',
            'clave' => 'CAMBIE_ESTA_CLAVE', // la que trae usuario_aplicacion.sql
        ]]);
    }

    /**
     * @return BaseDatos Una conexión como la usa la aplicación (usuario benefitrack_app).
     */
    protected function baseDatosDeLaApp(): BaseDatos
    {
        return BaseDatos::conectar($this->configuracionDePrueba());
    }

    /**
     * Crea un administrador activo (que ya eligió su contraseña).
     *
     * @return int El id del nuevo usuario.
     */
    protected function crearAdmin(): int
    {
        return $this->insertarUsuario(1, ['debe_cambiar_contrasena' => 0]);
    }

    /**
     * Crea un aprendiz activo.
     *
     * @param array<string, string|int|null> $datos Columnas de usuarios que se quieren cambiar (correo, estado...).
     * @return int El id del nuevo usuario.
     */
    protected function crearAprendiz(array $datos = []): int
    {
        return $this->insertarUsuario(2, ['debe_cambiar_contrasena' => 0] + $datos);
    }

    /**
     * Crea una convocatoria Activa del beneficio 1 (Alimentación), abierta desde ayer por 30 días.
     *
     * @param int $cupos Cupos disponibles.
     * @return int El id de la convocatoria.
     */
    protected function crearConvocatoriaActiva(int $cupos = 10): int
    {
        $pdo = $this->pdoRoot();
        $pdo->exec('SET @app_id_usuario = NULL');
        $pdo->prepare(
            'INSERT INTO convocatorias (id_beneficio, nombre, fecha_inicio, fecha_cierre, cupos, requisitos, valor_pago, numero_pagos, duracion_meses, estado)
             VALUES (1, ?, CURDATE() - INTERVAL 1 DAY, CURDATE() + INTERVAL 30 DAY, ?, ?, 180000, 4, 4, ?)'
        )->execute(['Convocatoria de prueba ' . bin2hex(random_bytes(3)), $cupos, 'Requisitos de prueba', 'Activa']);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Inserta un usuario como root (con @app_id_usuario en NULL, como pide el README de la base).
     *
     * @param array<string, string|int|null> $cambios
     */
    private function insertarUsuario(int $idRol, array $cambios): int
    {
        $sufijo = bin2hex(random_bytes(4));
        $columnas = $cambios + [
            'documento' => (string) random_int(1000000000, 1999999999),
            'nombre' => 'Prueba',
            'apellido' => 'Usuario',
            'correo' => "prueba$sufijo@ejemplo.test",
            'password_hash' => password_hash(self::CLAVE_PRUEBA, PASSWORD_DEFAULT),
            'id_rol' => $idRol,
        ];

        $pdo = $this->pdoRoot();
        $pdo->exec('SET @app_id_usuario = NULL');
        $nombres = implode(', ', array_keys($columnas));
        $marcas = implode(', ', array_fill(0, count($columnas), '?'));
        $pdo->prepare("INSERT INTO usuarios ($nombres) VALUES ($marcas)")->execute(array_values($columnas));

        return (int) $pdo->lastInsertId();
    }

    /** Conexión como root a la base temporal (solo para preparar datos de prueba). */
    private function pdoRoot(): PDO
    {
        if (self::$pdoRoot === null) {
            self::$pdoRoot = new PDO(
                'mysql:host=127.0.0.1;port=' . self::puerto() . ';dbname=benefitrack_v2;charset=utf8mb4',
                'root',
                (string) getenv('BR_PRUEBA_BD_ROOT_CLAVE'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }

        return self::$pdoRoot;
    }

    /** Ejecuta un archivo .sql con el cliente mysql contra la instancia temporal. */
    private static function cargarScript(string $archivo): void
    {
        $orden = [
            self::clienteMysql(), '-u', 'root', '-h', '127.0.0.1', '-P', (string) self::puerto(),
            '--default-character-set=utf8mb4',
        ];
        $entrada = fopen($archivo, 'rb');
        if ($entrada === false) {
            throw new RuntimeException("No se pudo abrir el script SQL: $archivo");
        }

        // La clave de root viaja en MYSQL_PWD (variable de entorno del proceso hijo),
        // no en la línea de comandos, donde cualquiera podría verla.
        $entorno = array_merge(getenv(), ['MYSQL_PWD' => (string) getenv('BR_PRUEBA_BD_ROOT_CLAVE')]);
        $proceso = proc_open($orden, [0 => $entrada, 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, null, $entorno);
        if (!is_resource($proceso)) {
            throw new RuntimeException("No se pudo ejecutar el cliente mysql con $archivo");
        }
        stream_get_contents($tuberias[1]);
        $errores = (string) stream_get_contents($tuberias[2]);
        if (proc_close($proceso) !== 0) {
            throw new RuntimeException("Falló la carga de $archivo: $errores");
        }
    }

    private static function puerto(): ?string
    {
        $puerto = getenv('BR_PRUEBA_BD_PUERTO');

        return is_string($puerto) && $puerto !== '' ? $puerto : null;
    }

    private static function clienteMysql(): ?string
    {
        $ruta = getenv('BR_PRUEBA_MYSQL');

        return is_string($ruta) && $ruta !== '' ? $ruta : null;
    }
}
