<?php

/**
 * TABLA DE RUTAS
 *
 * Aquí se declara qué URL atiende cada controlador y qué filtros la protegen.
 * Cada tarea del proyecto agrega sus rutas debajo. Ejemplo:
 *   $enrutador->agregar('GET', '/ingresar', [AccesoControlador::class, 'formulario']);
 */

declare(strict_types=1);

use BenefitRack\Nucleo\Enrutador;
use BenefitRack\Nucleo\Respuesta;

return static function (Enrutador $enrutador): void {
    // Provisional: se reemplaza cuando exista el inicio de sesión.
    $enrutador->agregar('GET', '/', static fn (): Respuesta => Respuesta::html('BenefitRack'));
};
