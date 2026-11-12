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

    /** @var \Closure():int Devuelve la hora actual (segundos). En las pruebas se reemplaza por un reloj controlado. */
    private readonly \Closure $reloj;

    /**
     * @param \Closure():int|null $reloj              Quién dice la hora (null = la hora real). Existe para poder probar la expiración sin esperar.
     * @param int                 $segundosEnServidor Cuánto guarda PHP los datos de una sesión (debe ser al menos la duración máxima;
     *                                                por defecto PHP los borra a los 24 minutos y la sesión «moriría» antes de tiempo).
     * @param string|null         $carpetaSesiones    Carpeta propia donde PHP guarda las sesiones (null = la de php.ini; así se usa en las pruebas).
     */
    public function __construct(?\Closure $reloj = null, private readonly int $segundosEnServidor = 28800, private readonly ?string $carpetaSesiones = null)
    {
        $this->reloj = $reloj ?? static fn (): int => time();
    }

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
        ini_set('session.gc_maxlifetime', (string) $this->segundosEnServidor);
        $this->usarCarpetaPropia();
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
        // Marcas para la expiración (B1): cuándo ingresó y cuándo fue su última petición.
        $_SESSION['_inicio'] = ($this->reloj)();
        $_SESSION['_actividad'] = $_SESSION['_inicio'];
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
     * Dice si la sesión de quien ingresó ya caducó: por inactividad (mucho tiempo sin pedir nada)
     * o por duración máxima (aunque no pare de usarla, no vive para siempre).
     *
     * @param int $segundosInactividad Máximo de segundos entre dos peticiones.
     * @param int $segundosMaximos     Máximo de segundos desde que ingresó.
     * @return bool true si caducó. Si hay usuario pero faltan las marcas de tiempo, también (ante la duda, se cierra).
     */
    public function haExpirado(int $segundosInactividad, int $segundosMaximos): bool
    {
        if ($this->idUsuario() === null) {
            return false;
        }
        $inicio = $_SESSION['_inicio'] ?? null;
        $actividad = $_SESSION['_actividad'] ?? null;
        if (!is_int($inicio) || !is_int($actividad)) {
            return true;
        }
        $ahora = ($this->reloj)();

        return $ahora - $actividad > $segundosInactividad || $ahora - $inicio > $segundosMaximos;
    }

    /**
     * Anota que la persona acaba de hacer una petición (reinicia el contador de inactividad).
     *
     * @return void
     */
    public function registrarActividad(): void
    {
        $_SESSION['_actividad'] = ($this->reloj)();
    }

    /**
     * Pone la bandera «debe cambiar su contraseña» tal como la dice la base de datos.
     *
     * @param bool $debeCambiar true si la base dice que debe cambiarla.
     * @return void
     */
    public function fijarDebeCambiar(bool $debeCambiar): void
    {
        $_SESSION['debe_cambiar_contrasena'] = $debeCambiar;
    }

    /**
     * Cierra la sesión de la persona pero deja una sesión anónima nueva con un aviso para /ingresar.
     * (cerrar() destruye la sesión del servidor y el aviso se perdería.)
     *
     * @param string $texto El aviso que verá en el formulario de ingreso.
     * @return void
     */
    public function cerrarConAviso(string $texto): void
    {
        $_SESSION = [];
        // Seguridad: id nuevo (el "true" borra la sesión vieja del servidor); el token CSRF y los datos viejos desaparecen.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $this->mensaje('aviso', $texto);
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

    /**
     * @return string El id de la sesión de PHP (nombra la carpeta temporal de sus documentos).
     */
    public function idSesion(): string
    {
        return session_id() ?: '';
    }

    /**
     * Guarda la postulación que ya pasó la validación y espera a que la persona pulse «Enviar» (Ruling R3).
     * Los documentos no van en la sesión: solo su ruta en la carpeta temporal.
     *
     * @param int                                                                                                                  $idConvocatoria Convocatoria a la que se postula.
     * @param array{datos: array<string, mixed>, archivos: array<string, array{ruta: string, extension: string, nombre_original: string, bytes: int}>} $postulacion
     * @return void
     */
    public function guardarPostulacionPendiente(int $idConvocatoria, array $postulacion): void
    {
        $_SESSION['postulacion_pendiente'][$idConvocatoria] = $postulacion;
    }

    /**
     * @param int $idConvocatoria La convocatoria.
     * @return array{datos: array<string, mixed>, archivos: array<string, array{ruta: string, extension: string, nombre_original: string, bytes: int}>}|null
     *         Lo pendiente de enviar, o null si no hay nada.
     */
    public function postulacionPendiente(int $idConvocatoria): ?array
    {
        /** @var array{datos: array<string, mixed>, archivos: array<string, array{ruta: string, extension: string, nombre_original: string, bytes: int}>}|null $pendiente */
        $pendiente = $_SESSION['postulacion_pendiente'][$idConvocatoria] ?? null;

        return $pendiente;
    }

    /**
     * Olvida la postulación pendiente de una convocatoria (al enviarla o al reemplazarla).
     *
     * @param int $idConvocatoria La convocatoria.
     * @return void
     */
    public function borrarPostulacionPendiente(int $idConvocatoria): void
    {
        unset($_SESSION['postulacion_pendiente'][$idConvocatoria]);
        if (($_SESSION['postulacion_pendiente'] ?? null) === []) {
            unset($_SESSION['postulacion_pendiente']);
        }
    }
    /**
     * Seguridad: PHP guarda las sesiones en la carpeta temporal de XAMPP (C:
mpp	mp), que comparte con phpMyAdmin
     * y otras aplicaciones. Su limpieza automática borra los archivos a los 24 minutos y no respetaría nuestros
     * 30 minutos / 8 horas. Con una carpeta propia solo viven aquí nuestras sesiones.
     */
    private function usarCarpetaPropia(): void
    {
        if ($this->carpetaSesiones === null) {
            return;
        }
        if (!is_dir($this->carpetaSesiones)) {
            mkdir($this->carpetaSesiones, 0700, true);
        }
        session_save_path($this->carpetaSesiones);
    }

    /** ¿La petición llegó por HTTPS? (el servidor lo avisa en $_SERVER['HTTPS']). */
    private function esHttps(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}
