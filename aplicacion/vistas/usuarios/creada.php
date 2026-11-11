<?php

/**
 * VISTA "usuarios/creada": muestra la contraseña temporal UNA sola vez (cuenta creada o contraseña restablecida).
 * Paso 5 del recorrido (la dibuja UsuariosControlador dentro del marco "admin").
 * No hay maqueta de esta pantalla: sigue el estilo de las tarjetas y avisos de 06-usuarios.
 * Recibe: $encabezado, $entrega (documento, nombre, correo, contrasena), $pendientes (cuántas contraseñas
 * lleva el Excel de entrega) y $tokenCsrf.
 * Si la persona recarga o vuelve a esta página, la contraseña ya no aparece: solo queda en el Excel de entrega.
 */

$icono = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
?>
<div class="br-encabezado">
    <div class="br-encabezado__titulo">
        <a href="/admin/usuarios" class="br-volver">Volver a usuarios</a>
        <h1><?= e($encabezado) ?></h1>
        <p class="br-encabezado__sub"><?= e($entrega['nombre']) ?> · documento <?= e($entrega['documento']) ?></p>
    </div>
</div>

<section class="br-tarjeta" aria-labelledby="titulo-clave">
    <h2 id="titulo-clave">Contraseña temporal</h2>
    <p class="br-clave-temporal" data-prueba="contrasena-temporal"><?= e($entrega['contrasena']) ?></p>
    <p class="br-suave">Correo para ingresar: <?= e($entrega['correo']) ?>. El aprendiz debe cambiar esta contraseña en su primer ingreso.</p>

    <div class="br-aviso br-aviso--naranja br-aviso--grande">
        <?= $icono ?> class="br-aviso__icono"><path d="M12 9v4M12 17h.01"></path><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"></path></svg>
        <div class="br-aviso__cuerpo">
            <p class="br-aviso__titulo">Esta contraseña se muestra solo ahora</p>
            <p class="br-aviso__detalle">Descarga el Excel con las contraseñas temporales para entregárselas a cada aprendiz y guárdalo en un lugar seguro: el sistema no lo conserva. Cuando termines de entregarlas, borra el archivo.</p>
        </div>
    </div>

    <div class="br-acciones-pie">
        <a href="/admin/usuarios" class="br-boton br-boton--secundario">Volver a usuarios</a>
        <a href="/admin/usuarios/nuevo" class="br-boton br-boton--secundario">Crear otra cuenta</a>
        <!-- POST con token: descargar borra las contraseñas de la sesión, así que no puede ser un simple enlace. -->
        <form method="post" action="/admin/usuarios/entrega">
            <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
            <button type="submit" class="br-boton br-boton--primario">Descargar Excel de entrega<?= $pendientes > 1 ? ' (' . $pendientes . ' contraseñas)' : '' ?></button>
        </form>
    </div>
</section>
