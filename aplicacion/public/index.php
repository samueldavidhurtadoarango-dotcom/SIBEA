<?php

/**
 * PUNTO DE ENTRADA: toda petición del navegador llega primero aquí.
 * Arma la aplicación, la ejecuta y envía la respuesta.
 */

declare(strict_types=1);

use BenefitRack\Nucleo\{Aplicacion, Configuracion, Enrutador, Peticion, Vista};

$raiz = dirname(__DIR__);
require $raiz . '/vendor/autoload.php';

$configuracion = Configuracion::desdeArchivo($raiz . '/config/config.local.php');

$enrutador = new Enrutador();
(require $raiz . '/rutas.php')($enrutador);

$aplicacion = new Aplicacion($enrutador, new Vista($raiz . '/vistas'), $configuracion);
$aplicacion->ejecutar(Peticion::desdeGlobales())->enviar();
