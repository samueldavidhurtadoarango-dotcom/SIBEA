<?php

/**
 * ARRANQUE
 *
 * Paso 0 del recorrido: public/index.php le entrega la carpeta de la aplicación y esta clase arma todo
 * (configuración, zona horaria, sesión, rutas) y atiende la petición. Si algo falla ANTES de que
 * Aplicacion pueda proteger la petición (falta config.local.php, un error en la tabla de rutas...),
 * lo registra con error_log y devuelve una página 500 sencilla: nunca se muestran rutas, claves ni
 * mensajes técnicos. (Aplicacion ya se encarga de los fallos que ocurren dentro de una petición.)
 */

declare(strict_types=1);

namespace BenefitRack\Nucleo;

use Throwable;

final class Arranque
{
    /**
     * Atiende la petición actual de principio a fin.
     *
     * @param string $raiz Carpeta de la aplicación (la que contiene config/, vistas/ y rutas.php).
     * @return Respuesta La respuesta para el navegador; si el arranque falla, una página 500 sin detalles.
     */
    public static function atender(string $raiz): Respuesta
    {
        try {
            $configuracion = Configuracion::desdeArchivo($raiz . '/config/config.local.php');
            // La hora de PHP debe ser la de Colombia (XAMPP trae la de Berlín).
            ZonaHoraria::aplicar($configuracion);
            $peticion = Peticion::desdeGlobales();

            // La fábrica reparte la sesión, la vista, la conexión y los controladores.
            $fabrica = new Fabrica($configuracion, $raiz . '/vistas', $peticion->ip());
            $fabrica->sesion()->iniciar();

            // La tabla de rutas usa la fábrica para pedir sus filtros (sesión, rol, CSRF...).
            $enrutador = new Enrutador();
            (require $raiz . '/rutas.php')($enrutador, $fabrica);

            return (new Aplicacion($enrutador, $fabrica->vista(), $configuracion, $fabrica))->ejecutar($peticion);
        } catch (Throwable $error) {
            // El detalle va solo al registro del servidor; la persona no ve nada técnico.
            error_log(sprintf('[BenefitRack] Falló el arranque: %s: %s en %s:%d', $error::class, $error->getMessage(), $error->getFile(), $error->getLine()));

            return self::paginaDeError();
        }
    }

    /** Página 500 mínima, escrita a mano: no usa vistas ni base de datos porque justo pudieron ser lo que falló. */
    private static function paginaDeError(): Respuesta
    {
        $html = '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Error · BenefitRack</title></head>'
            . '<body style="font-family: sans-serif; max-width: 32rem; margin: 4rem auto; padding: 0 1rem;">'
            . '<h1>Algo salió mal</h1>'
            . '<p>Tuvimos un problema de nuestro lado. Intenta de nuevo en unos minutos.</p>'
            . '</body></html>';

        return Respuesta::html($html, 500)->sinCache();
    }
}
