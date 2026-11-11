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
use BenefitRack\Controladores\ContrasenaControlador;
use BenefitRack\Controladores\UsuariosControlador;
use BenefitRack\Nucleo\Enrutador;
use BenefitRack\Nucleo\Fabrica;

return static function (Enrutador $enrutador, Fabrica $fabrica): void {
    // Acceso (M1). Ni /ingresar ni /salir llevan el filtro de primer ingreso: son la salida del bucle.
    $enrutador->agregar('GET', '/', [AccesoControlador::class, 'inicio']);
    $enrutador->agregar('GET', '/ingresar', [AccesoControlador::class, 'formulario']);
    // Ruling R2: el POST de ingreso también exige token CSRF (si venció, el filtro vuelve al formulario con un aviso).
    $enrutador->agregar('POST', '/ingresar', [AccesoControlador::class, 'ingresar'], [$fabrica->filtroCsrf()]);
    $enrutador->agregar('POST', '/salir', [AccesoControlador::class, 'salir'], [$fabrica->filtroSesion(), $fabrica->filtroCsrf()]);

    // Cambio de contraseña en el primer ingreso (M2). Sin filtro de primer ingreso: es justo a donde ese filtro manda (si no, bucle).
    $enrutador->agregar('GET', '/contrasena/nueva', [ContrasenaControlador::class, 'formulario'], [$fabrica->filtroSesion()]);
    $enrutador->agregar('POST', '/contrasena/nueva', [ContrasenaControlador::class, 'guardar'], [$fabrica->filtroSesion(), $fabrica->filtroCsrf()]);

    // Usuarios (M3a): solo el administrador, ya con contraseña propia. Lo que cambia datos lleva además el token CSRF.
    $admin = [$fabrica->filtroSesion(), $fabrica->filtroRol('Administrador'), $fabrica->filtroPrimerIngreso()];
    $adminConCsrf = [...$admin, $fabrica->filtroCsrf()];
    $enrutador->agregar('GET', '/admin/usuarios', [UsuariosControlador::class, 'lista'], $admin);
    $enrutador->agregar('GET', '/admin/usuarios/nuevo', [UsuariosControlador::class, 'formulario'], $admin);
    $enrutador->agregar('POST', '/admin/usuarios/nuevo', [UsuariosControlador::class, 'crear'], $adminConCsrf);
    $enrutador->agregar('POST', '/admin/usuarios/{id}/estado', [UsuariosControlador::class, 'cambiarEstado'], $adminConCsrf);
    $enrutador->agregar('POST', '/admin/usuarios/{id}/restablecer', [UsuariosControlador::class, 'restablecer'], $adminConCsrf);
    $enrutador->agregar('POST', '/admin/usuarios/entrega', [UsuariosControlador::class, 'descargarEntrega'], $adminConCsrf);
};
