<?php

/**
 * APLICACIÓN
 *
 * Es el director de orquesta. Recorrido de una petición:
 *   1. Peticion  (index.php la arma)
 *   2. Enrutador (¿qué ruta es?)             -> si no hay, página 404
 *   3. Filtros   (sesión, rol, CSRF...)      -> si alguno responde, se corta ahí
 *   4. Controlador (hace el trabajo)
 *   5. Vista     (dibuja el HTML)
 *   6. Respuesta (index.php la envía)
 * Si algo falla sin que lo esperáramos, se muestra la página 500.
 */

declare(strict_types=1);

namespace BenefitRack\Nucleo;

use Throwable;

final class Aplicacion
{
    /**
     * @param Enrutador     $enrutador     La tabla de rutas ya cargada.
     * @param Vista         $vista         Dibuja las páginas (incluidas las de error).
     * @param Configuracion $configuracion Ajustes de la aplicación (se usa app.depuracion).
     * @param Fabrica       $fabrica       Arma los controladores con sus dependencias.
     */
    public function __construct(
        private readonly Enrutador $enrutador,
        private readonly Vista $vista,
        private readonly Configuracion $configuracion,
        private readonly Fabrica $fabrica,
    ) {
    }

    /**
     * Atiende una petición de principio a fin.
     *
     * @param Peticion $peticion Lo que pidió el navegador.
     * @return Respuesta Lo que hay que enviarle (nunca lanza excepciones).
     */
    public function ejecutar(Peticion $peticion): Respuesta
    {
        try {
            $ruta = $this->enrutador->resolver($peticion);
            if ($ruta === null) {
                return Respuesta::html($this->vista->mostrar('errores/404'), 404);
            }

            // El menú del administrador marca el enlace de la página actual.
            $this->vista->compartir('rutaActual', $peticion->ruta());
            $peticion = $peticion->conParametros($ruta['parametros']);
            foreach ($ruta['filtros'] as $filtro) {
                $respuesta = $filtro->aplicar($peticion);
                if ($respuesta !== null) {
                    return $respuesta;
                }
            }

            return $this->llamar($ruta['destino'], $peticion);
        } catch (Throwable $error) {
            return $this->respuestaDeError($error);
        }
    }

    /**
     * Llama al controlador. Si el destino es [Clase::class, 'metodo'], le pide
     * el controlador a la Fabrica (con sus dependencias) y ejecuta el método; si es una función, la ejecuta.
     *
     * @param callable|array<int, string> $destino
     */
    private function llamar(callable|array $destino, Peticion $peticion): Respuesta
    {
        if (is_array($destino) && is_string($destino[0]) && !is_callable($destino)) {
            [$clase, $metodo] = $destino;
            $destino = [$this->fabrica->crear($clase), $metodo];
        }

        return $destino($peticion);
    }

    /**
     * Error imprevisto: queda registrado en el log del servidor y la persona
     * ve una página de error. El detalle técnico solo se muestra si la
     * depuración está activa (nunca en producción: filtraría información).
     */
    private function respuestaDeError(Throwable $error): Respuesta
    {
        error_log(sprintf('[BenefitRack] %s: %s en %s:%d', $error::class, $error->getMessage(), $error->getFile(), $error->getLine()));

        try {
            $detalle = $this->depuracionActiva() ? $error->getMessage() : null;

            return Respuesta::html($this->vista->mostrar('errores/500', ['detalle' => $detalle]), 500);
        } catch (Throwable) {
            // Último recurso: ni la página de error se pudo dibujar.
            return Respuesta::html('Error del servidor', 500);
        }
    }

    /**
     * Lee app.depuracion sin lanzar excepciones: si falta en la configuración
     * se asume false (lo más seguro: no mostrar detalles).
     */
    private function depuracionActiva(): bool
    {
        try {
            return $this->configuracion->obtener('app.depuracion') === true;
        } catch (Throwable) {
            return false;
        }
    }
}
