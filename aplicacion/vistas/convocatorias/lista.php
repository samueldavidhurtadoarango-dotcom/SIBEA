<?php

/**
 * VISTA "convocatorias/lista": la lista de convocatorias con pestañas por estado.
 * Paso 5 del recorrido (la dibuja ConvocatoriasControlador dentro del marco "admin").
 * Reproduce las maquetas vistas/07-convocatorias/html/lista, cerrar y vacio. El cierre usa una ventana
 * (modal de Bootstrap, como la lista de usuarios) que pide el motivo solo cuando el cierre es anticipado.
 * Recibe: $filas (convocatorias del filtro), $contadores (cuántas hay por pestaña), $filtro (pestaña
 * actual) y $tokenCsrf. Los estados usan los colores de la maqueta (las etiquetas br-etiqueta--*).
 */

$hoy = date('Y-m-d');
// Qué etiqueta lleva cada estado y qué dice debajo de ella.
$etiquetas = ['Activa' => 'br-etiqueta--ok', 'Inactiva' => 'br-etiqueta--neutra', 'Cerrada' => 'br-etiqueta--info', 'Cierre anticipado' => 'br-etiqueta--aviso'];
$pestanas = ['todas' => 'Todas', 'activas' => 'Activas', 'inactivas' => 'Inactivas', 'cerradas' => 'Cerradas'];
$enlacePestana = static fn (string $clave): string => '/admin/convocatorias' . ($clave === 'todas' ? '' : '?estado=' . $clave);
$icono = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">';

// «1 pago» / «6 pagos» y «1 mes» / «6 meses».
$plural = static fn (int $cantidad, string $uno, string $varios): string => $cantidad . ' ' . ($cantidad === 1 ? $uno : $varios);
// Frase que va debajo de la etiqueta de estado.
$notaDeEstado = static function (array $fila): string {
    return match ($fila['estado']) {
        'Activa' => 'Cierra el ' . formato_fecha($fila['fecha_cierre']),
        'Inactiva' => 'Nadie puede postularse aún',
        'Cerrada' => 'Cerró el ' . formato_fecha($fila['fecha_cierre_real']),
        default => 'Cerró el ' . formato_fecha($fila['fecha_cierre_real']) . '. Motivo: ' . $fila['motivo_cierre'],
    };
};
?>
<div class="br-encabezado">
    <div class="br-encabezado__titulo">
        <h1>Convocatorias</h1>
        <p class="br-encabezado__sub">Abre y cierra las convocatorias de cada beneficio: fechas, cupos y valor de los pagos.</p>
    </div>
    <?php if ($contadores['todas'] > 0) : ?>
        <div class="br-encabezado__acciones">
            <a href="/admin/convocatorias/nueva" class="br-boton br-boton--primario"><?= $icono ?><path d="M12 5v14M5 12h14"></path></svg>Nueva convocatoria</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($contadores['todas'] === 0) : ?>
    <!-- Estado vacío (maqueta vacio.html): todavía no se ha creado ninguna. -->
    <div class="br-vacio">
        <span class="br-vacio__icono" aria-hidden="true"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" width="26" height="26"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 10h18M12 13v5M9.5 15.5h5"></path></svg></span>
        <h2>Aún no hay convocatorias.</h2>
        <p>Crea la primera: con ella defines el beneficio, las fechas, los cupos y cuánto recibe cada aprendiz aprobado. Las postulaciones y los pagos aparecen después.</p>
        <div class="br-fila"><a href="/admin/convocatorias/nueva" class="br-boton br-boton--primario"><?= $icono ?><path d="M12 5v14M5 12h14"></path></svg>Nueva convocatoria</a></div>
    </div>
<?php else : ?>
    <nav class="br-pestanas" aria-label="Filtrar por estado de la convocatoria">
        <?php foreach ($pestanas as $clave => $texto) : ?>
            <a href="<?= e($enlacePestana($clave)) ?>"<?= $filtro === $clave ? ' aria-current="page"' : '' ?>><?= e($texto) ?> <span class="br-contador"><?= (int) $contadores[$clave] ?></span></a>
        <?php endforeach; ?>
    </nav>

    <section class="br-tarjeta br-listado" aria-label="Lista de convocatorias">
        <?php if ($filas === []) : ?>
            <p class="br-vacio">No hay convocatorias <?= e(mb_strtolower($pestanas[$filtro])) ?>.</p>
        <?php else : ?>
            <div class="br-listado__herramientas">
                <div class="br-listado__resumen"><span>Mostrando <?= count($filas) ?> <?= count($filas) === 1 ? 'convocatoria' : 'convocatorias' ?>, las abiertas primero</span></div>
            </div>
            <div class="br-tabla-envoltura">
                <table class="br-tabla br-tabla--listado br-tabla--fichas br-tabla--sin-seleccion" data-prueba="tabla-convocatorias" aria-label="Convocatorias">
                    <thead><tr>
                        <th scope="col">Convocatoria</th><th scope="col">Fechas</th><th scope="col">Cupos</th><th scope="col">Pagos</th><th scope="col">Estado</th><th scope="col"><span class="br-solo-lectores">Acciones</span></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($filas as $fila) : ?>
                        <?php
                        $id = (int) $fila['id_convocatoria'];
                        $nombre = (string) $fila['nombre'];
                        $estado = (string) $fila['estado'];
                        $abierta = $estado === 'Activa' || $estado === 'Inactiva';
                        $usados = (int) $fila['cupos_usados'];
                        $cupos = (int) $fila['cupos'];
                        $lleno = $usados >= $cupos;
                        // Cerrar antes de la fecha de cierre es un cierre anticipado: pide el motivo.
                        $anticipado = (string) $fila['fecha_cierre'] > $hoy;
                        $diasFaltan = (int) (new DateTimeImmutable($hoy))->diff(new DateTimeImmutable((string) $fila['fecha_cierre']))->days;
                        $explicacion = $anticipado
                            ? 'Cierre anticipado: faltan ' . $plural($diasFaltan, 'día', 'días') . ' para la fecha de cierre, así que debes escribir el motivo.'
                            : 'Cierre normal: ya llegó la fecha de cierre. No pide motivo.';
                        $resumen = $fila['beneficio'] . ' · del ' . formato_fecha($fila['fecha_inicio'], false) . ' al ' . formato_fecha($fila['fecha_cierre']) . ' · ' . $usados . ' de ' . $cupos . ' cupos usados';
                        ?>
                        <tr>
                            <td class="br-tabla__titulo"><span class="br-tabla__principal"><?= e($nombre) ?></span><span class="br-tabla__sub"><?= e($fila['beneficio']) ?></span></td>
                            <td data-etiqueta="Fechas"><span class="br-sin-corte"><?= e(formato_fecha($fila['fecha_inicio'], false)) ?></span> – <span class="br-sin-corte"><?= e(formato_fecha($fila['fecha_cierre'])) ?></span></td>
                            <td data-etiqueta="Cupos" class="br-tabla__cupos<?= $lleno ? ' br-tabla__cupos--lleno' : '' ?>"><span class="br-cifra"><?= $usados ?> de <?= $cupos ?></span><progress class="br-progreso br-progreso--fina" value="<?= $usados ?>" max="<?= $cupos ?>" aria-label="Cupos usados de <?= e($nombre) ?>"></progress><span class="br-tabla__sub"><?= $lleno ? 'Sin cupos libres' : 'Quedan ' . ($cupos - $usados) ?></span></td>
                            <td data-etiqueta="Pagos"><span class="br-sin-corte"><?= e($plural((int) $fila['numero_pagos'], 'pago', 'pagos') . ' de ' . formato_pesos($fila['valor_pago'])) ?></span><span class="br-tabla__sub">En <?= e($plural((int) $fila['duracion_meses'], 'mes', 'meses')) ?></span></td>
                            <td class="br-tabla__estado<?= $estado === 'Cierre anticipado' ? ' br-tabla__estado--largo' : '' ?>"><span class="br-etiqueta <?= e($etiquetas[$estado] ?? 'br-etiqueta--neutra') ?>"><?= e($estado) ?></span><span class="br-tabla__sub"><?= e($notaDeEstado($fila)) ?></span></td>
                            <td class="br-tabla__acciones"><div class="br-tabla__acciones-dentro">
                                <?php if ($abierta) : ?>
                                    <a href="/admin/convocatorias/<?= $id ?>/editar" class="br-boton br-boton--secundario br-boton--chico">Editar<span class="br-solo-lectores"> <?= e($nombre) ?></span></a>
                                <?php endif; ?>
                                <details class="br-menu-acciones">
                                    <summary aria-label="Más acciones para <?= e($nombre) ?>"><?= $icono ?><circle cx="5" cy="12" r="1.25"></circle><circle cx="12" cy="12" r="1.25"></circle><circle cx="19" cy="12" r="1.25"></circle></svg></summary>
                                    <div class="br-menu-acciones__lista">
                                        <a href="/admin/postulaciones?convocatoria=<?= $id ?>" class="br-menu-acciones__item">Ver postulaciones<span class="br-suave">Las que llegaron a esta convocatoria.</span></a>
                                        <?php if ($abierta) : ?>
                                            <form method="post" action="/admin/convocatorias/<?= $id ?>/estado">
                                                <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
                                                <input type="hidden" name="accion" value="<?= $estado === 'Activa' ? 'desactivar' : 'activar' ?>">
                                                <?php if ($estado === 'Activa') : ?>
                                                    <button type="submit" class="br-menu-acciones__item">Desactivar<span class="br-suave">Nadie podrá postularse hasta que la actives otra vez.</span></button>
                                                <?php else : ?>
                                                    <button type="submit" class="br-menu-acciones__item">Activar<span class="br-suave">Recibe postulaciones entre la fecha de inicio y la de cierre.</span></button>
                                                <?php endif; ?>
                                            </form>
                                            <div class="br-menu-acciones__separador" role="presentation"></div>
                                            <button type="button" class="br-menu-acciones__item br-menu-acciones__item--peligro"
                                                    data-bs-toggle="modal" data-bs-target="#modal-cerrar"
                                                    data-accion="/admin/convocatorias/<?= $id ?>/estado" data-cambio="<?= $anticipado ? 'cerrar-anticipado' : 'cerrar' ?>"
                                                    data-nombre="<?= e($nombre) ?>" data-titulo="Cerrar <?= e($nombre) ?>"
                                                    data-resumen="<?= e($resumen) ?>" data-explicacion="<?= e($explicacion) ?>">
                                                Cerrar convocatoria<span class="br-suave">Nadie más podrá postularse. Antes de la fecha de cierre pide un motivo.</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Ventana para cerrar: la llenan los botones «Cerrar convocatoria» (benefitrack.js). El motivo solo aparece en el cierre anticipado. -->
    <div class="modal fade" id="modal-cerrar" tabindex="-1" aria-labelledby="modal-cerrar-titulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" action="" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
                <input type="hidden" name="accion" value="cerrar">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modal-cerrar-titulo" data-modal-titulo>Cerrar la convocatoria</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="br-suave" data-modal-resumen></p>
                    <p data-modal-explicacion></p>
                    <div class="br-campo" data-modal-motivo hidden>
                        <label for="motivo">Motivo del cierre anticipado <span class="br-suave">(obligatorio)</span></label>
                        <textarea id="motivo" name="motivo" class="br-entrada" rows="3" maxlength="500"
                                  placeholder="Por ejemplo: se agotó el presupuesto del semestre"></textarea>
                        <p class="br-ayuda">Queda guardado con la convocatoria, junto con quién la cerró y cuándo.</p>
                    </div>
                    <p class="br-suave">Desde ahora nadie más podrá postularse. Las postulaciones aprobadas y sus pagos siguen igual, y las que ya llegaron se pueden seguir revisando.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="br-boton br-boton--secundario" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="br-boton br-boton--peligro">Cerrar convocatoria</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
