<?php

/**
 * VISTA
 *
 * Paso 5 del recorrido: el controlador ya tiene los datos y le pide a la Vista
 * que los dibuje. La Vista carga la plantilla pedida (vistas/<plantilla>.php),
 * y el HTML resultante lo mete dentro de un marco (vistas/plantillas/<marco>.php)
 * que trae la barra, el menú y el pie de página.
 * Las plantillas son las únicas que tienen HTML y todo dato pasa por e().
 */

declare(strict_types=1);

namespace BenefitRack\Nucleo;

final class Vista
{
    /** @var array<string, mixed> Datos que ven TODAS las páginas (por ejemplo la ruta actual, para marcar el menú). */
    private array $compartidos = [];

    /**
     * @param string      $carpeta Carpeta donde están las vistas (aplicacion/vistas).
     * @param Sesion|null $sesion  Si se entrega, las páginas reciben los mensajes de una sola vez,
     *                             el nombre de quien ingresó y su rol.
     */
    public function __construct(private readonly string $carpeta, private readonly ?Sesion $sesion = null)
    {
    }

    /**
     * Agrega un dato que verán todas las páginas, sin que cada controlador lo pase.
     *
     * @param string $nombre Nombre de la variable en la plantilla.
     * @param mixed  $valor  Su valor.
     * @return void
     */
    public function compartir(string $nombre, mixed $valor): void
    {
        $this->compartidos[$nombre] = $valor;
    }

    /**
     * Dibuja una página.
     *
     * @param string               $plantilla Nombre sin .php, por ejemplo "errores/404".
     * @param array<string, mixed> $datos     Variables que verá la plantilla ($datos['nombre'] -> $nombre).
     * @param string               $marco     Marco que envuelve la página: publica, admin o aprendiz.
     * @return string El HTML completo.
     */
    public function mostrar(string $plantilla, array $datos = [], string $marco = 'publica'): string
    {
        // Los formularios de la página necesitan el token CSRF: se entrega a todas las vistas.
        $datos += $this->compartidos + ['tokenCsrf' => Csrf::token()];
        $contenido = $this->renderizar($plantilla, $datos);

        // Los mensajes se toman DESPUÉS de dibujar la página y solo los muestra el marco.
        return $this->renderizar('plantillas/' . $marco, $datos + $this->datosDeSesion() + ['contenido' => $contenido]);
    }

    /**
     * Datos de la sesión para el marco: mensajes de una sola vez, nombre y rol.
     *
     * @return array<string, mixed>
     */
    private function datosDeSesion(): array
    {
        return [
            'mensajes' => $this->sesion?->tomarMensajes() ?? [],
            'usuarioNombre' => $this->sesion?->nombre() ?? '',
            'usuarioRol' => $this->sesion?->rol() ?? '',
        ];
    }

    /**
     * Ejecuta un archivo de vistas y devuelve lo que imprimió.
     *
     * @param array<string, mixed> $datos
     */
    private function renderizar(string $nombre, array $datos): string
    {
        // Seguridad: el nombre lo escribe el programador, nunca el usuario; aun así
        // se rechazan ".." para que jamás pueda salirse de la carpeta de vistas.
        if (str_contains($nombre, '..')) {
            throw new \InvalidArgumentException('Nombre de vista no permitido.');
        }
        $archivo = $this->carpeta . '/' . $nombre . '.php';

        if (!is_file($archivo)) {
            throw new \RuntimeException("No existe la vista: $nombre");
        }

        // Las variables solo existen dentro de esta función anónima.
        return (static function (string $__archivo, array $__datos): string {
            extract($__datos, EXTR_SKIP);
            ob_start();
            try {
                require $__archivo;

                return (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
        })($archivo, $datos);
    }
}
