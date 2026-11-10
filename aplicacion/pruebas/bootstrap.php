<?php

/**
 * ARRANQUE DE LAS PRUEBAS
 *
 * PHPUnit lo ejecuta una vez antes de todo (ver phpunit.xml). Carga el
 * autocargador de Composer y hace que cualquier aviso de PHP se reporte,
 * para que ningún error pase desapercibido.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

error_reporting(E_ALL);
