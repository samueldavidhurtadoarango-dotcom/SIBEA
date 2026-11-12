<?php

/**
 * VISTA "usuarios/vista_previa": qué filas del Excel están bien y cuáles tienen errores, ANTES de crear nada.
 * Paso 5 del recorrido (la dibuja CargaUsuariosControlador dentro del marco "admin").
 * Reproduce la maqueta 06-usuarios/vista-previa (sin la columna «Valor en el archivo»).
 * Recibe: $archivo (nombre del Excel), $validas (filas listas), $conErrores (filas con error),
 * $errores (lista de fila, campo y motivo) y $tokenCsrf.
 */

$total = $validas + $conErrores;
?>
<div class="br-encabezado">
    <div class="br-encabezado__titulo">
        <a href="/admin/usuarios/cargar" class="br-volver">Cambiar archivo</a>
        <h1>Revisa el archivo antes de crear las cuentas</h1>
        <p class="br-encabezado__sub">Todavía no se ha creado ninguna cuenta. Mira qué filas tienen errores y decide.</p>
    </div>
</div>

<div class="br-detalle">
    <div class="br-detalle__principal">
        <section class="br-tarjeta" aria-labelledby="titulo-archivo">
            <h2 id="titulo-archivo">Archivo</h2>
            <div class="br-archivo br-archivo--ok">
                <div class="br-archivo__texto">
                    <span class="br-archivo__nombre"><?= e($archivo) ?></span>
                    <span class="br-suave"><?= $total ?> <?= $total === 1 ? 'fila de aprendiz' : 'filas de aprendices' ?></span>
                </div>
                <a href="/admin/usuarios/cargar">Cambiar archivo</a>
            </div>
        </section>

        <?php if ($errores !== []) : ?>
            <section class="br-tarjeta br-listado" aria-labelledby="titulo-errores">
                <div class="br-listado__herramientas">
                    <h2 id="titulo-errores">Filas con errores <span class="br-contador br-contador--aviso"><?= $conErrores ?></span></h2>
                    <p class="br-suave">Estas filas no se crean. Corrígelas en el Excel y cárgalas después en un archivo nuevo; las demás no tienes que volver a subirlas.</p>
                </div>
                <div class="br-tabla-envoltura">
                    <table class="br-tabla br-tabla--listado br-tabla--fichas br-tabla--sin-seleccion" data-prueba="errores" aria-label="Filas del archivo con errores">
                        <thead><tr><th scope="col">Fila</th><th scope="col">Columna</th><th scope="col">Motivo</th></tr></thead>
                        <tbody>
                            <?php foreach ($errores as $error) : ?>
                                <tr>
                                    <td class="br-tabla__titulo br-cifra" data-etiqueta="Fila"><span class="br-tabla__principal"><?= (int) $error['fila'] ?></span></td>
                                    <td data-etiqueta="Columna"><?= e(ucfirst($error['campo'])) ?></td>
                                    <td data-etiqueta="Motivo" class="br-tabla__ancha"><?= e($error['motivo']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <aside class="br-detalle__lateral">
        <section class="br-tarjeta" aria-labelledby="titulo-crear">
            <h2 id="titulo-crear">Crear las cuentas</h2>
            <div class="br-resumen" data-prueba="resumen-filas">
                <p class="br-resumen__cifra"><?= $validas ?> de <?= $total ?> filas están listas</p>
                <p class="br-resumen__partes">
                    <span><span class="br-resumen__punto" aria-hidden="true"></span><?= $validas ?> <?= $validas === 1 ? 'válida' : 'válidas' ?></span>
                    <span><span class="br-resumen__punto br-resumen__punto--aviso" aria-hidden="true"></span><?= $conErrores ?> con errores</span>
                </p>
            </div>
            <p class="br-suave">Las filas con errores no se crean. Cada aprendiz nuevo queda con una contraseña temporal que se entrega en un Excel al terminar.</p>
            <?php if ($validas > 0) : ?>
                <form method="post" action="/admin/usuarios/cargar/confirmar">
                    <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
                    <button type="submit" class="br-boton br-boton--primario br-boton--ancho" data-prueba="crear-cuentas">Crear <?= $validas ?> <?= $validas === 1 ? 'cuenta' : 'cuentas' ?></button>
                </form>
            <?php endif; ?>
            <a href="/admin/usuarios/cargar" class="br-boton br-boton--secundario br-boton--ancho">Cancelar</a>
        </section>
    </aside>
</div>
