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
    /** @param string $carpeta Carpeta donde están las vistas (aplicacion/vistas). */
    public function __construct(private readonly string $carpeta)
    {
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
        $contenido = $this->renderizar($plantilla, $datos);

        return $this->renderizar('plantillas/' . $marco, $datos + ['contenido' => $contenido]);
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
