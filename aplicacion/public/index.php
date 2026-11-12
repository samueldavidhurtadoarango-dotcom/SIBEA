<?php

/**
 * PUNTO DE ENTRADA: toda petición del navegador llega primero aquí.
 * Carga las librerías y le pasa la carpeta de la aplicación a Arranque, que arma todo, atiende la petición
 * y, si el arranque falla, responde una página 500 sencilla (el detalle va solo al error_log).
 * En producción, php.ini debe tener display_errors=Off (ver LEEME.md).
 */

declare(strict_types=1);

use BenefitRack\Nucleo\Arranque;

$raiz = dirname(__DIR__);
require $raiz . '/vendor/autoload.php';

Arranque::atender($raiz)->enviar();
