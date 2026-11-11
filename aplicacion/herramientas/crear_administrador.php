<?php

/**
 * HERRAMIENTA DE CONSOLA: crear el primer administrador
 *
 * Uso (desde la carpeta aplicacion/):   php herramientas/crear_administrador.php
 *
 * La base de datos no trae ningún usuario y crear un Administrador exige que ya exista otro
 * administrador activo. Por eso el PRIMERO se crea aquí, conectado como "root" y con
 * @app_id_usuario = NULL: es el único caso en que la base lo permite, y el único en que se
 * acepta debe_cambiar_contrasena = 0 (este administrador ya elige su propia contraseña).
 * (Ver base-de-datos/README.md, sección 1 «Crear el primer administrador».)
 *
 * Los datos de conexión (host, puerto y nombre de la base) salen de config/config.local.php;
 * el usuario es siempre root y su clave se pregunta (en XAMPP suele estar vacía: Enter).
 * Solo corre en consola, nunca desde el navegador.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Esta herramienta solo se ejecuta desde la consola.\n");
}

require dirname(__DIR__) . '/vendor/autoload.php';

use BenefitRack\Nucleo\Configuracion;

/** Pregunta un dato por consola y devuelve lo que se escribió (sin espacios sobrantes). */
function preguntar(string $pregunta): string
{
    echo $pregunta . ': ';

    return trim((string) fgets(STDIN));
}

/**
 * Pide los datos del administrador y los valida.
 *
 * @return array{documento: string, nombre: string, apellido: string, correo: string, clave: string}
 */
function pedirDatos(): array
{
    $datos = [
        'documento' => preguntar('Documento (solo dígitos)'),
        'nombre' => preguntar('Nombre'),
        'apellido' => preguntar('Apellido'),
        'correo' => strtolower(preguntar('Correo')),
        'clave' => preguntar('Contraseña (mínimo 8 caracteres; se verá al escribirla)'),
    ];

    if (!ctype_digit($datos['documento']) || $datos['nombre'] === '' || $datos['apellido'] === '') {
        exit("Documento (solo dígitos), nombre y apellido son obligatorios.\n");
    }
    if (filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) === false) {
        exit("El correo no tiene forma de correo.\n");
    }
    if (strlen($datos['clave']) < 8) {
        exit("La contraseña debe tener al menos 8 caracteres.\n");
    }

    return $datos;
}

/** Abre la conexión como root a la base indicada en config.local.php. */
function conectarComoRoot(Configuracion $configuracion): PDO
{
    $clave = preguntar('Clave de root de MariaDB (Enter si está vacía)');
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $configuracion->obtener('bd.host'),
        $configuracion->obtener('bd.puerto'),
        $configuracion->obtener('bd.nombre')
    );

    return new PDO($dsn, 'root', $clave, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
}

/**
 * Inserta al administrador. Solo se guarda el hash de la contraseña, nunca la contraseña.
 *
 * @param array{documento: string, nombre: string, apellido: string, correo: string, clave: string} $datos
 */
function insertarAdministrador(PDO $pdo, array $datos): void
{
    // La base solo acepta crear un Administrador sin sesión si la conexión es root y el usuario de sesión es NULL.
    $pdo->exec('SET @app_id_usuario = NULL');
    $pdo->prepare(
        "INSERT INTO usuarios (documento, nombre, apellido, correo, password_hash, id_rol, debe_cambiar_contrasena)
         VALUES (?, ?, ?, ?, ?, (SELECT id_rol FROM roles WHERE nombre = 'Administrador'), 0)"
    )->execute([
        $datos['documento'],
        $datos['nombre'],
        $datos['apellido'],
        $datos['correo'],
        password_hash($datos['clave'], PASSWORD_DEFAULT),
    ]);
}

try {
    $configuracion = Configuracion::desdeArchivo(dirname(__DIR__) . '/config/config.local.php');
    $datos = pedirDatos();
    insertarAdministrador(conectarComoRoot($configuracion), $datos);
    echo "Listo: administrador {$datos['correo']} creado. Ya puede iniciar sesión.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'No se pudo crear el administrador: ' . $error->getMessage() . "\n");
    exit(1);
}
