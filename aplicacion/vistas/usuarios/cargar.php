<?php

/**
 * VISTA "usuarios/cargar": elegir el Excel de aprendices (y la pantalla de «archivo inválido»).
 * Paso 5 del recorrido (la dibuja CargaUsuariosControlador dentro del marco "admin").
 * Reproduce las maquetas vistas/06-usuarios/html/cargar-excel y archivo-invalido. Cambia el aviso
 * de la contraseña: no se envía por correo, se entrega en el Excel de entrega. Las columnas son
 * las de la plantilla real (6, no 7).
 * Recibe: $motivo (por qué el archivo no sirve, o null), $nombreArchivo (el que se intentó subir, o null),
 * $columnas (nombres de la plantilla), $maximoFilas y $tokenCsrf.
 */

$motivo = $motivo ?? null;
$nombreArchivo = $nombreArchivo ?? null;
// Una ayuda corta por columna, en el mismo orden de $columnas.
$ayudas = [
    'Documento' => 'Sin puntos: 1032118904',
    'Nombre' => 'Luisa Fernanda',
    'Apellido' => 'Ríos Cárdenas',
    'Correo' => 'No se puede repetir',
    'Programa' => 'Debe estar en la lista de programas',
    'Ficha' => 'Número de la ficha: 2758341',
];
$icono = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"';
$limite = number_format($maximoFilas, 0, ',', '.');
?>
<div class="br-encabezado">
    <div class="br-encabezado__titulo">
        <a href="/admin/usuarios" class="br-volver">Volver a usuarios</a>
        <h1>Cargar aprendices desde Excel</h1>
        <p class="br-encabezado__sub">Sube la lista con la plantilla. Antes de crear cualquier cuenta te mostramos qué filas tienen errores.</p>
    </div>
</div>

<div class="br-detalle">
    <div class="br-detalle__principal">
        <form class="br-tarjeta" method="post" action="/admin/usuarios/cargar" enctype="multipart/form-data" aria-labelledby="titulo-archivo">
            <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
            <h2 id="titulo-archivo">Archivo</h2>

            <?php if ($motivo !== null) : ?>
                <?php if ($nombreArchivo !== null) : ?>
                    <div class="br-archivo br-archivo--error">
                        <?= $icono ?> class="br-archivo__icono"><path d="M12 9v4M12 17h.01"></path><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"></path></svg>
                        <div class="br-archivo__texto"><span class="br-archivo__nombre"><?= e($nombreArchivo) ?></span></div>
                    </div>
                <?php endif; ?>
                <div class="br-mensaje-error" role="alert" data-prueba="motivo-archivo">
                    <strong><?= e($motivo) ?></strong>
                    <span>Ábrelo en Excel o en Google Sheets, guárdalo como «Libro de Excel (.xlsx)» y súbelo de nuevo. Para no olvidar ninguna columna, usa la plantilla.</span>
                </div>
            <?php endif; ?>

            <div class="br-campo">
                <span class="br-solo-lectores" id="ayuda-formatos">Solo archivos de Excel (.xlsx), de hasta 5 MB y <?= e($limite) ?> filas.</span>
                <label class="br-zona" for="archivo" data-prueba="zona">
                    <?= $icono ?> class="br-zona__icono"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m17 8-5-5-5 5"></path><path d="M12 3v12"></path></svg>
                    <span class="br-zona__titulo"><?= $motivo !== null ? 'Elige otro archivo' : 'Elige el archivo' ?></span>
                    <span class="br-zona__ayuda" aria-hidden="true">Excel (.xlsx) · máximo 5 MB · hasta <?= e($limite) ?> filas</span>
                    <input class="br-solo-lectores" type="file" id="archivo" name="archivo" required accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" aria-describedby="ayuda-formatos">
                </label>
            </div>
            <p class="br-ayuda">Cada aprendiz queda con una contraseña temporal que debe cambiar en su primer ingreso. Al terminar descargas el Excel de entrega para dársela.</p>
            <div class="br-acciones-pie">
                <button type="submit" class="br-boton br-boton--primario" data-prueba="revisar">Revisar archivo</button>
            </div>
        </form>
    </div>

    <aside class="br-detalle__lateral">
        <section class="br-tarjeta" aria-labelledby="titulo-columnas" data-prueba="columnas">
            <h2 id="titulo-columnas">Columnas de la plantilla</h2>
            <p class="br-suave">El archivo debe tener estas <?= count($columnas) ?> columnas en la primera fila, con estos nombres. Una fila por aprendiz.</p>
            <ol class="br-columnas">
                <?php foreach ($columnas as $posicion => $columna) : ?>
                    <li>
                        <span class="br-columnas__letra" aria-hidden="true"><?= e(chr(65 + $posicion)) ?></span>
                        <span class="br-columnas__nombre"><?= e($columna) ?></span>
                        <span class="br-suave"><?= e($ayudas[$columna] ?? '') ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <a href="/admin/usuarios/plantilla" class="br-boton br-boton--secundario br-boton--ancho">Descargar plantilla</a>
        </section>
    </aside>
</div>
