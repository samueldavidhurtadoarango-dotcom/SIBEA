<?php

/**
 * VISTA "usuarios/lista": la lista de aprendices con buscador y páginas de 20.
 * Paso 5 del recorrido (la dibuja UsuariosControlador dentro del marco "admin").
 * Reproduce la maqueta vistas/06-usuarios/html/lista (sin la columna «Último ingreso»: la base no la guarda).
 * Recibe: $pendientes (contraseñas por entregar), $buscar (texto buscado), $pagina (filas, total, pagina, paginas, porPagina) y $tokenCsrf.
 * Las acciones de cambiar estado y restablecer son formularios POST dentro de dos ventanas (modales de Bootstrap).
 */

$buscar = $buscar ?? '';
$pendientes = $pendientes ?? 0;
$filas = $pagina['filas'];
$actual = $pagina['pagina'];
$paginas = $pagina['paginas'];

// Qué etiqueta lleva cada estado (clases de la maqueta).
$etiquetas = ['Activo' => 'br-etiqueta--ok', 'Inactivo' => 'br-etiqueta--neutra', 'Bloqueado' => 'br-etiqueta--peligro'];
// Enlace a otra página conservando lo buscado.
$enlace = static fn (int $numero): string => '/admin/usuarios?' . http_build_query(array_filter(['q' => $buscar, 'pagina' => $numero > 1 ? $numero : null]));
$primera = $pagina['total'] === 0 ? 0 : ($actual - 1) * $pagina['porPagina'] + 1;
$icono = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">';
?>
<div class="br-encabezado">
    <div class="br-encabezado__titulo">
        <h1>Usuarios</h1>
        <p class="br-encabezado__sub">Crea las cuentas de los aprendices y activa, desactiva o desbloquea las que ya existen.</p>
    </div>
    <div class="br-encabezado__acciones">
        <a href="/admin/usuarios/nuevo" class="br-boton br-boton--primario"><?= $icono ?><path d="M12 5v14M5 12h14"></path></svg>Crear una cuenta</a>
    </div>
</div>

<?php if ($pendientes > 0) : ?>
    <!-- Contraseñas temporales que aún no se han descargado en el Excel de entrega (viven en la sesión). -->
    <div class="br-aviso br-aviso--naranja br-aviso--grande" role="status">
        <div class="br-aviso__cuerpo">
            <p class="br-aviso__titulo"><?= $pendientes ?> <?= $pendientes === 1 ? 'contraseña pendiente' : 'contraseñas pendientes' ?> de entregar</p>
            <p class="br-aviso__detalle">Descarga el Excel de entrega y guárdalo en un lugar seguro: el sistema no lo conserva y, al descargarlo, las contraseñas se borran de la sesión.</p>
            <form method="post" action="/admin/usuarios/entrega">
                <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
                <button type="submit" class="br-boton br-boton--primario br-boton--chico">Descargar Excel de entrega</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<section class="br-tarjeta br-listado" aria-label="Lista de aprendices">
    <div class="br-listado__herramientas">
        <form method="get" action="/admin/usuarios" role="search" class="br-filtros">
            <div class="br-buscador">
                <label for="buscar" class="br-solo-lectores">Buscar por nombre, documento o correo</label>
                <?= $icono ?><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                <input id="buscar" name="q" class="br-entrada" type="search" placeholder="Nombre, documento o correo" value="<?= e($buscar) ?>">
            </div>
            <button type="submit" class="br-boton br-boton--secundario">Buscar</button>
        </form>
        <div class="br-listado__resumen">
            <span><?= $pagina['total'] === 0 ? 'Sin resultados' : 'Mostrando ' . $primera . ' a ' . ($primera + count($filas) - 1) . ' de ' . $pagina['total'] . ' aprendices' ?></span>
        </div>
        <p class="br-motivo"><?= $icono ?><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5"></path><path d="M12 8h.01"></path></svg>Una cuenta queda bloqueada tras 3 intentos fallidos de ingreso. Se desbloquea sola a los 15 minutos, o puedes desbloquearla ya con «Desbloquear».</p>
    </div>

    <?php if ($filas === []) : ?>
        <p class="br-vacio">No hay aprendices<?= $buscar !== '' ? ' que coincidan con «' . e($buscar) . '»' : ' todavía. Crea la primera cuenta con «Crear una cuenta»' ?>.</p>
    <?php else : ?>
        <div class="br-tabla-envoltura">
            <table class="br-tabla br-tabla--listado br-tabla--fichas br-tabla--sin-seleccion" aria-label="Cuentas de aprendices">
                <thead><tr>
                    <th scope="col">Aprendiz</th><th scope="col">Correo</th><th scope="col">Programa</th><th scope="col">Estado</th><th scope="col"><span class="br-solo-lectores">Acciones</span></th>
                </tr></thead>
                <tbody>
                <?php foreach ($filas as $fila) : ?>
                    <?php
                    $nombreCompleto = $fila['nombre'] . ' ' . $fila['apellido'];
                    $id = (int) $fila['id_usuario'];
                    $estado = (string) $fila['estado'];
                    ?>
                    <tr>
                        <td class="br-tabla__titulo"><span class="br-tabla__principal"><?= e($nombreCompleto) ?></span><span class="br-tabla__sub"><?= e($fila['documento']) ?></span></td>
                        <td data-etiqueta="Correo" class="br-tabla__ancha"><?= e($fila['correo']) ?></td>
                        <td data-etiqueta="Programa" class="br-tabla__ancha"><?= e($fila['programa'] ?? 'Sin programa') ?><?php if (($fila['ficha'] ?? '') !== '') : ?><span class="br-tabla__sub">Ficha <?= e($fila['ficha']) ?></span><?php endif; ?></td>
                        <td class="br-tabla__estado"><span class="br-etiqueta <?= e($etiquetas[$estado] ?? 'br-etiqueta--neutra') ?>"><?= e($estado) ?></span></td>
                        <td class="br-tabla__acciones"><div class="br-tabla__acciones-dentro">
                            <?php if ($estado !== 'Activo') : ?>
                                <button type="button" class="br-boton br-boton--secundario br-boton--chico"
                                        data-bs-toggle="modal" data-bs-target="#modal-estado"
                                        data-accion="/admin/usuarios/<?= $id ?>/estado" data-estado="Activo" data-nombre="<?= e($nombreCompleto) ?>"
                                        data-titulo="<?= $estado === 'Bloqueado' ? 'Desbloquear' : 'Activar' ?> la cuenta">
                                    <?= $estado === 'Bloqueado' ? 'Desbloquear' : 'Activar' ?><span class="br-solo-lectores"> la cuenta de <?= e($nombreCompleto) ?></span>
                                </button>
                            <?php endif; ?>
                            <?php if ($estado !== 'Inactivo') : ?>
                                <details class="br-menu-acciones">
                                    <summary aria-label="Más acciones para <?= e($nombreCompleto) ?>"><?= $icono ?><circle cx="5" cy="12" r="1.25"></circle><circle cx="12" cy="12" r="1.25"></circle><circle cx="19" cy="12" r="1.25"></circle></svg></summary>
                                    <div class="br-menu-acciones__lista">
                                        <button type="button" class="br-menu-acciones__item"
                                                data-bs-toggle="modal" data-bs-target="#modal-restablecer"
                                                data-accion="/admin/usuarios/<?= $id ?>/restablecer" data-nombre="<?= e($nombreCompleto) ?>">
                                            Restablecer contraseña<span class="br-suave">Genera una contraseña temporal nueva; la actual deja de servir.</span>
                                        </button>
                                        <button type="button" class="br-menu-acciones__item"
                                                data-bs-toggle="modal" data-bs-target="#modal-estado"
                                                data-accion="/admin/usuarios/<?= $id ?>/estado" data-estado="Inactivo" data-nombre="<?= e($nombreCompleto) ?>"
                                                data-titulo="Desactivar la cuenta">
                                            Desactivar cuenta<span class="br-suave">No podrá ingresar. Sus postulaciones y pagos se conservan.</span>
                                        </button>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="br-listado__pie">
            <span>Página <?= $actual ?> de <?= $paginas ?></span>
            <nav class="br-paginacion" aria-label="Páginas">
                <?php if ($actual > 1) : ?>
                    <a href="<?= e($enlace($actual - 1)) ?>">Anterior</a>
                <?php else : ?>
                    <span aria-disabled="true">Anterior</span>
                <?php endif; ?>
                <span aria-current="page" class="br-paginacion__numero"><?= $actual ?></span>
                <?php if ($actual < $paginas) : ?>
                    <a href="<?= e($enlace($actual + 1)) ?>">Siguiente</a>
                <?php else : ?>
                    <span aria-disabled="true">Siguiente</span>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</section>

<!-- Ventana para activar, desactivar o desbloquear: pide la justificación (la base de datos la exige y la guarda en la auditoría). -->
<div class="modal fade" id="modal-estado" tabindex="-1" aria-labelledby="modal-estado-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="" class="modal-content">
            <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
            <input type="hidden" name="estado" value="">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="modal-estado-titulo" data-modal-titulo>Cambiar el estado de la cuenta</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>Cuenta de <strong data-modal-nombre></strong>.</p>
                <div class="br-campo">
                    <label for="justificacion">Justificación (obligatoria)</label>
                    <textarea id="justificacion" name="justificacion" class="br-entrada" rows="3" maxlength="500" required
                              placeholder="Por ejemplo: el aprendiz se retiró del programa"></textarea>
                    <p class="br-ayuda">Queda guardada en el historial junto con tu nombre.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="br-boton br-boton--secundario" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="br-boton br-boton--primario">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<!-- Ventana para restablecer la contraseña: avisa lo que pasa antes de hacerlo. -->
<div class="modal fade" id="modal-restablecer" tabindex="-1" aria-labelledby="modal-restablecer-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="" class="modal-content">
            <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="modal-restablecer-titulo">Restablecer la contraseña</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>Vas a generar una contraseña temporal nueva para <strong data-modal-nombre></strong>. La contraseña actual dejará de servir y la persona deberá cambiarla en su próximo ingreso.</p>
                <p class="br-suave">La contraseña nueva se muestra una sola vez; después la entregas con el Excel de entrega.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="br-boton br-boton--secundario" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="br-boton br-boton--primario">Restablecer</button>
            </div>
        </form>
    </div>
</div>
