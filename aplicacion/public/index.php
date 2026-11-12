<?php

/**
 * PUNTO DE ENTRADA: toda petición del navegador llega primero aquí.
 * Arma la aplicación, la ejecuta y envía la respuesta.
 */

declare(strict_types=1);

use BenefitRack\Nucleo\{Aplicacion, Configuracion, Enrutador, Fabrica, Peticion, ZonaHoraria};

$raiz = dirname(__DIR__);
require $raiz . '/vendor/autoload.php';

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

$aplicacion = new Aplicacion($enrutador, $fabrica->vista(), $configuracion, $fabrica);
$aplicacion->ejecutar($peticion)->enviar();
