<?php

/**
 * SESIÓN
 *
 * Paso 0 del recorrido (index.php la inicia antes de enrutar) y la consultan
 * los filtros (paso 3) y los controladores (paso 4). Guarda en $_SESSION quién
 * ingresó (id, rol, nombre), si debe cambiar su contraseña y los mensajes de
 * una sola vez ("Guardado", "Tu sesión terminó"...). El resto de la aplicación
 * nunca toca $_SESSION directamente: pasa por esta clase.
 */

declare(strict_types=1);

namespace BenefitRack\Nucleo;

final class Sesion
{
    private const NOMBRE_COOKIE = 'BR_SESION';

    /**
     * Arranca la sesión con la cookie configurada de forma segura.
     *
     * @return void
     */
    public function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Estricto: PHP rechaza ids de sesión que él no creó (evita que alguien
        // "regale" un id inventado a la víctima: fijación de sesión).
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name(self::NOMBRE_COOKIE);
        session_set_cookie_params([
            'lifetime' => 0,         // la cookie muere al cerrar el navegador
            'path' => '/',
            'httponly' => true,      // JavaScript no puede leerla (defensa si hubiera XSS)
            'samesite' => 'Lax',     // no viaja en formularios enviados desde otros sitios (defensa extra contra CSRF)
            'secure' => $this->esHttps(), // en HTTPS solo viaja cifrada
        ]);
        session_start();
    }

    /**
     * Guarda en la sesión a quien acaba de ingresar.
     *
     * @param int    $idUsuario     Id del usuario en la base de datos.
     * @param string $rol           'Administrador' o 'Aprendiz'.
     * @param bool   $debeCambiar   true si debe cambiar su contraseña antes de seguir.
     * @param string $nombre        Nombre que se muestra en la barra (opcional).
     * @return void
     */
    public function iniciarPara(int $idUsuario, string $rol, bool $debeCambiar, string $nombre = ''): void
    {
        // Seguridad: al subir de "anónimo" a "usuario" se cambia el id de la sesión.
        // Así, si alguien conocía el id anterior (fijación de sesión), ya no sirve.
        // El "true" borra la sesión vieja del servidor. (Solo aplica si la sesión
        // está activa; en las pruebas unitarias no lo está.)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        // El token CSRF anónimo también se renueva: pertenece a otra "identidad".
        unset($_SESSION['_csrf']);

        $_SESSION['id_usuario'] = $idUsuario;
        $_SESSION['rol'] = $rol;
        $_SESSION['nombre'] = $nombre;
        $_SESSION['debe_cambiar_contrasena'] = $debeCambiar;
    }

    /**
     * @return int|null El id del usuario que ingresó, o null si nadie lo ha hecho.
     */
    public function idUsuario(): ?int
    {
        $id = $_SESSION['id_usuario'] ?? null;

        return is_int($id) ? $id : null;
    }

    /**
     * @return string|null 'Administrador' o 'Aprendiz'; null si no hay sesión.
     */
    public function rol(): ?string
    {
        $rol = $_SESSION['rol'] ?? null;

        return is_string($rol) ? $rol : null;
    }

    /**
     * @return string La pantalla de inicio de cada rol: el administrador a las postulaciones,
     *                el aprendiz a su proceso y quien no ha ingresado, al formulario de ingreso.
     */
    public function rutaDeInicio(): string
    {
        return match ($this->rol()) {
            'Administrador' => '/admin/postulaciones',
            'Aprendiz' => '/mi-proceso',
            default => '/ingresar',
        };
    }

    /**
     * @return string El nombre para la barra superior (vacío si no hay).
     */
    public function nombre(): string
    {
        $nombre = $_SESSION['nombre'] ?? '';

        return is_string($nombre) ? $nombre : '';
    }

    /**
     * @return bool true si la persona debe cambiar su contraseña antes de usar la aplicación.
     */
    public function debeCambiarContrasena(): bool
    {
        return ($_SESSION['debe_cambiar_contrasena'] ?? false) === true;
    }

    /**
     * Anota que la persona ya cambió su contraseña.
     *
     * @return void
     */
    public function marcarContrasenaCambiada(): void
    {
        $_SESSION['debe_cambiar_contrasena'] = false;
        // OWASP: al cambiar una credencial se renueva el id de sesión (si alguien conocía el anterior, ya no sirve).
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Cierra la sesión: borra los datos, la sesión del servidor y la cookie.
     *
     * @return void
     */
    public function cerrar(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            // También se pide al navegador que olvide la cookie.
            $cookie = session_get_cookie_params();
            setcookie(self::NOMBRE_COOKIE, '', [
                'expires' => time() - 3600,
                'path' => $cookie['path'],
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => $cookie['secure'],
            ]);
            session_destroy();
        }
    }

    /**
     * Deja un mensaje para mostrarlo en la próxima página (una sola vez).
     *
     * @param string $tipo  'exito', 'error' o 'aviso'.
     * @param string $texto El mensaje, en lenguaje sencillo.
     * @return void
     */
    public function mensaje(string $tipo, string $texto): void
    {
        $_SESSION['mensajes'][] = ['tipo' => $tipo, 'texto' => $texto];
    }

    /**
     * Entrega los mensajes pendientes y los borra, para que no se repitan.
     *
     * @return list<array{tipo: string, texto: string}>
     */
    public function tomarMensajes(): array
    {
        /** @var list<array{tipo: string, texto: string}> $mensajes */
        $mensajes = $_SESSION['mensajes'] ?? [];
        unset($_SESSION['mensajes']);

        return $mensajes;
    }

    /**
     * Anota una contraseña temporal recién entregada para incluirla en el Excel de entrega.
     * Vive solo en la sesión del administrador (no se escribe en ningún archivo propio) hasta que descarga el Excel.
     *
     * @param array{documento: string, nombre: string, correo: string, contrasena: string} $fila Los datos de un aprendiz.
     * @return void
     */
    public function agregarEntrega(array $fila): void
    {
        // Se indexa por documento: si a la misma persona se le genera otra contraseña (restablecer dos veces,
        // o crear y restablecer), queda solo la última, que es la única que sirve.
        $_SESSION['entrega_pendiente'][$fila['documento']] = $fila;
    }

    /**
     * @return list<array{documento: string, nombre: string, correo: string, contrasena: string}> Lo pendiente de entregar.
     */
    public function entregaPendiente(): array
    {
        /** @var list<array{documento: string, nombre: string, correo: string, contrasena: string}> $filas */
        $filas = $_SESSION['entrega_pendiente'] ?? [];

        return array_values($filas);
    }

    /**
     * Borra las contraseñas pendientes (se llama al descargar el Excel: así no quedan en la sesión).
     *
     * @return void
     */
    public function borrarEntrega(): void
    {
        unset($_SESSION['entrega_pendiente']);
    }

    /**
     * Guarda la carga de aprendices en curso: las filas válidas esperan a que el administrador confirme
     * (así se confirma lo que se vio en la vista previa, sin conservar el archivo subido) y los errores
     * quedan para poder descargarlos.
     *
     * @param array{archivo: string, filas: list<array<string, mixed>>, errores: array<int, array<string, string>>} $carga
     * @return void
     */
    public function guardarCarga(array $carga): void
    {
        $_SESSION['carga_usuarios'] = $carga;
    }

    /**
     * @return array{archivo: string, filas: list<array<string, mixed>>, errores: array<int, array<string, string>>}|null
     *         La carga en curso, o null si no hay ninguna.
     */
    public function cargaPendiente(): ?array
    {
        /** @var array{archivo: string, filas: list<array<string, mixed>>, errores: array<int, array<string, string>>}|null $carga */
        $carga = $_SESSION['carga_usuarios'] ?? null;

        return $carga;
    }

    /** ¿La petición llegó por HTTPS? (el servidor lo avisa en $_SERVER['HTTPS']). */
    private function esHttps(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}
