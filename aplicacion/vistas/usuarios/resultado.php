<?php

/**
 * VISTA "usuarios/resultado": cuántas cuentas se crearon y qué falló, con las dos descargas.
 * Paso 5 del recorrido (la dibuja CargaUsuariosControlador dentro del marco "admin").
 * Reproduce el aviso verde de la maqueta vistas/06-usuarios/html/resultado. Cambia el texto: la
 * contraseña temporal no llega por correo, se entrega con el Excel de entrega (el mismo de
 * /admin/usuarios/entrega: viven en la sesión hasta que se descarga).
 * Recibe: $creadas, $conErrores, $pendientes (contraseñas que lleva el Excel de entrega) y $tokenCsrf.
 */

$icono = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
?>
<div class="br-encabezado">
    <div class="br-encabezado__titulo">
        <a href="/admin/usuarios" class="br-volver">Volver a usuarios</a>
        <h1>Resultado de la carga</h1>
    </div>
</div>

<div class="br-aviso br-aviso--<?= $conErrores > 0 ? 'naranja' : 'verde' ?> br-aviso--grande" role="status">
    <?= $icono ?> class="br-aviso__icono"><path d="M20 6 9 17l-5-5"></path></svg>
    <div class="br-aviso__cuerpo">
        <p class="br-aviso__titulo" data-prueba="resumen-carga">
            <?= $creadas === 1 ? 'Se creó 1 cuenta' : 'Se crearon ' . $creadas . ' cuentas' ?>.
            <?php if ($conErrores > 0) : ?><?= $conErrores === 1 ? '1 fila con errores.' : $conErrores . ' filas con errores.' ?><?php endif; ?>
        </p>
        <p class="br-aviso__detalle">
            Cada cuenta nueva quedó con una contraseña temporal que se ve solo en el Excel de entrega: descárgalo, guárdalo en un lugar seguro y bórralo cuando termines de entregarlas. El sistema no lo conserva.
            <?php if ($conErrores > 0) : ?>Las filas con errores no se crearon: descarga la lista, corrígelas en el Excel y cárgalas en un archivo nuevo.<?php endif; ?>
        </p>
        <div class="br-acciones-pie">
            <?php if ($pendientes > 0) : ?>
                <!-- POST con token: es la misma descarga de /admin/usuarios/entrega y, al hacerla, borra las contraseñas de la sesión. -->
                <form method="post" action="/admin/usuarios/entrega">
                    <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
                    <button type="submit" class="br-boton br-boton--primario br-boton--chico">Descargar Excel de entrega<?= $pendientes > 1 ? ' (' . $pendientes . ' contraseñas)' : '' ?></button>
                </form>
            <?php endif; ?>
            <?php if ($conErrores > 0) : ?>
                <a href="/admin/usuarios/cargar/errores" class="br-boton br-boton--secundario br-boton--chico">Descargar errores</a>
            <?php endif; ?>
            <a href="/admin/usuarios" class="br-boton br-boton--secundario br-boton--chico">Ver usuarios</a>
        </div>
    </div>
</div>
