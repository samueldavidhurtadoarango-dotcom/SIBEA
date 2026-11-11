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
use BenefitRack\Controladores\CargaUsuariosControlador;
use BenefitRack\Controladores\ContrasenaControlador;
use BenefitRack\Controladores\ConvocatoriasControlador;
use BenefitRack\Controladores\DocumentosControlador;
use BenefitRack\Controladores\PostulacionAprendizControlador;
use BenefitRack\Controladores\PostulacionesAdminControlador;
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

    // Carga de aprendices desde Excel (M3b): subir -> vista previa -> confirmar. Subir y confirmar cambian datos: llevan CSRF.
    $enrutador->agregar('GET', '/admin/usuarios/cargar', [CargaUsuariosControlador::class, 'formulario'], $admin);
    $enrutador->agregar('GET', '/admin/usuarios/plantilla', [CargaUsuariosControlador::class, 'plantilla'], $admin);
    $enrutador->agregar('POST', '/admin/usuarios/cargar', [CargaUsuariosControlador::class, 'vistaPrevia'], $adminConCsrf);
    $enrutador->agregar('POST', '/admin/usuarios/cargar/confirmar', [CargaUsuariosControlador::class, 'confirmar'], $adminConCsrf);
    $enrutador->agregar('GET', '/admin/usuarios/cargar/errores', [CargaUsuariosControlador::class, 'errores'], $admin);

    // Convocatorias (M4): solo el administrador. Lo que cambia datos lleva además el token CSRF.
    $enrutador->agregar('GET', '/admin/convocatorias', [ConvocatoriasControlador::class, 'lista'], $admin);
    $enrutador->agregar('GET', '/admin/convocatorias/nueva', [ConvocatoriasControlador::class, 'formulario'], $admin);
    $enrutador->agregar('POST', '/admin/convocatorias/nueva', [ConvocatoriasControlador::class, 'crear'], $adminConCsrf);
    $enrutador->agregar('GET', '/admin/convocatorias/{id}/editar', [ConvocatoriasControlador::class, 'editar'], $admin);
    $enrutador->agregar('POST', '/admin/convocatorias/{id}/editar', [ConvocatoriasControlador::class, 'guardar'], $adminConCsrf);
    $enrutador->agregar('POST', '/admin/convocatorias/{id}/estado', [ConvocatoriasControlador::class, 'cambiarEstado'], $adminConCsrf);

    // Postulaciones del administrador (M6a): lista con filtros y Excel. Son GET que solo leen: no llevan CSRF.
    // (El detalle y las decisiones, /admin/postulaciones/{id}..., son de la tarea siguiente.)
    $enrutador->agregar('GET', '/admin/postulaciones', [PostulacionesAdminControlador::class, 'lista'], $admin);
    $enrutador->agregar('GET', '/admin/postulaciones/excel', [PostulacionesAdminControlador::class, 'excel'], $admin);

    // Postulación del aprendiz (M5a y M5b): formulario, revisar y enviar. Solo el aprendiz, ya con contraseña propia; los POST llevan CSRF.
    $aprendiz = [$fabrica->filtroSesion(), $fabrica->filtroRol('Aprendiz'), $fabrica->filtroPrimerIngreso()];
    $enrutador->agregar('GET', '/convocatorias/{id}/postulacion', [PostulacionAprendizControlador::class, 'formulario'], $aprendiz);
    $enrutador->agregar('POST', '/convocatorias/{id}/postulacion', [PostulacionAprendizControlador::class, 'revisar'], [...$aprendiz, $fabrica->filtroCsrf()]);
    $enrutador->agregar('POST', '/convocatorias/{id}/postulacion/enviar', [PostulacionAprendizControlador::class, 'enviar'], [...$aprendiz, $fabrica->filtroCsrf()]);

    // Documentos de las postulaciones (M5b): con sesión de cualquier rol; el controlador deja pasar solo al dueño o a un administrador.
    $enrutador->agregar('GET', '/documentos/{id}', [DocumentosControlador::class, 'descargar'], [$fabrica->filtroSesion(), $fabrica->filtroPrimerIngreso()]);
};
