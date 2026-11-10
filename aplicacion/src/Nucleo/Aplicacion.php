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
    public function __construct(
        private readonly Enrutador $enrutador,
        private readonly Vista $vista,
        private readonly Configuracion $configuracion,
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
     * Llama al controlador. Si el destino es [Clase::class, 'metodo'], crea el
     * controlador y ejecuta el método; si es una función, la ejecuta.
     *
     * @param callable|array<int, string> $destino
     */
    private function llamar(callable|array $destino, Peticion $peticion): Respuesta
    {
        if (is_array($destino) && is_string($destino[0]) && !is_callable($destino)) {
            [$clase, $metodo] = $destino;
            $destino = [new $clase(), $metodo];
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

        $detalle = $this->configuracion->obtener('app.depuracion') === true ? $error->getMessage() : null;

        return Respuesta::html($this->vista->mostrar('errores/500', ['detalle' => $detalle]), 500);
    }
}
