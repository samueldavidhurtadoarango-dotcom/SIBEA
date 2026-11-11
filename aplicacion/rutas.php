<?php

/**
 * TABLA DE RUTAS
 *
 * Aquí se declara qué URL atiende cada controlador y qué filtros la protegen.
 * Los filtros se piden a la fábrica y se ejecutan en el orden escrito.
 * Cada tarea del proyecto agrega sus rutas debajo. Ejemplo:
 *   $enrutador->agregar('POST', '/admin/usuarios', [UsuariosControlador::class, 'crear'], [
 *       $fabrica->filtroSesion(), $fabrica->filtroRol('Administrador'),
 *       $fabrica->filtroPrimerIngreso(), $fabrica->filtroCsrf(),
 *   ]);
 */

declare(strict_types=1);

use BenefitRack\Nucleo\Enrutador;
use BenefitRack\Nucleo\Fabrica;
use BenefitRack\Nucleo\Respuesta;

return static function (Enrutador $enrutador, Fabrica $fabrica): void {
    // Provisional: se reemplaza cuando exista el inicio de sesión.
    $enrutador->agregar('GET', '/', static fn (): Respuesta => Respuesta::html('BenefitRack'));
};
