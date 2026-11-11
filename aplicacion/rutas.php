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

use BenefitRack\Controladores\AccesoControlador;
use BenefitRack\Nucleo\Enrutador;
use BenefitRack\Nucleo\Fabrica;

return static function (Enrutador $enrutador, Fabrica $fabrica): void {
    // Acceso (M1). Ni /ingresar ni /salir llevan el filtro de primer ingreso: son la salida del bucle.
    $enrutador->agregar('GET', '/', [AccesoControlador::class, 'inicio']);
    $enrutador->agregar('GET', '/ingresar', [AccesoControlador::class, 'formulario']);
    // Ruling R2: el POST de ingreso también exige token CSRF (si venció, el filtro vuelve al formulario con un aviso).
    $enrutador->agregar('POST', '/ingresar', [AccesoControlador::class, 'ingresar'], [$fabrica->filtroCsrf()]);
    $enrutador->agregar('POST', '/salir', [AccesoControlador::class, 'salir'], [$fabrica->filtroSesion(), $fabrica->filtroCsrf()]);
};
