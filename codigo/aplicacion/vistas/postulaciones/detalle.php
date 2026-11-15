<?php

/**
 * VISTA "postulaciones/detalle": el detalle de una postulación para el administrador.
 * Paso 5 del recorrido (la dibuja PostulacionesAdminControlador::detalle dentro del marco "admin").
 * Reproduce la maqueta 03-detalle-postulacion (pendiente, validada, aprobada, rechazada y sin cupos), sin el
 * botón «Pedir documento» (es de la fase 2):
 *   - a la izquierda: los datos del formulario, los documentos (con su revisión «Válido» / «No válido» mientras la
 *     postulación está Pendiente) y el historial; si ya fue aprobada, también la asignación creada;
 *   - a la derecha: el panel de decisión en dos pasos (1. Confirmar datos, 2. Aprobar o rechazar).
 * Rechazar y aprobar abren una ventana de confirmación; rechazar pide el motivo.
 * Recibe: $detalle (la postulación completa, ver ServicioRevision::detalle), $acciones (qué botones se pueden usar),
 * $motivoConfirmar (por qué «Confirmar» no se puede), $soloRechazar (¿solo queda rechazar?) y $tokenCsrf.
 * Todo dato pasa por e(): lo escribieron los aprendices. Los formularios son POST con token CSRF.
 */

$id = (int) $detalle['id_postulacion'];
$estado = (string) $detalle['estado'];
$nombre = (string) $detalle['nombre'];
$pendiente = $estado === 'Pendiente';
$base = '/admin/postulaciones/' . $id;

// Cupos de la convocatoria: cuántos están aprobados y cuántos quedan.
$cupos = (int) $detalle['cupos'];
$usados = (int) $detalle['aprobadas'];
$quedan = max(0, $cupos - $usados);

// Qué clase de etiqueta lleva cada estado (hoja de estilos).
$etiquetas = ['Pendiente' => 'br-etiqueta--pendiente', 'Validada' => 'br-etiqueta--validada', 'Aprobada' => 'br-etiqueta--ok', 'Rechazada' => 'br-etiqueta--peligro'];
// Cómo se llama cada estado de un documento ante la persona («Rechazado» en la base es «No válido» en pantalla).
$nombreDeMarca = ['Pendiente' => 'Sin revisar', 'Validado' => 'Válido', 'Rechazado' => 'No válido'];

// El cambio más reciente a un estado (quién y cuándo): el historial viene con lo más reciente arriba.
$cambioA = static function (string $nuevo) use ($detalle): ?array {
    foreach ($detalle['historial'] as $cambio) {
        if ($cambio['estado_nuevo'] === $nuevo) {
            return $cambio;
        }
    }

    return null;
};
$quienYCuando = static fn (?array $cambio): string => $cambio === null ? '' : e((string) $cambio['quien']) . ' · ' . e(formato_fecha_hora((string) $cambio['fecha']));
// Un dato del formulario o un guion largo si la persona no lo llenó.
$dato = static fn (mixed $valor): string => ($valor === null || $valor === '') ? '—' : e((string) $valor);

$icono = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">';
$iconoArchivo = $icono . '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"></path><path d="M14 3v5h5"></path></svg>';
$iconoAviso = $icono . '<circle cx="12" cy="12" r="9"></circle><path d="M12 8v5M12 16h.01"></path></svg>';
$iconoVisto = $icono . '<path d="m5 12 5 5 9-10"></path></svg>';
$iconoCerrar = $icono . '<path d="M6 6l12 12M18 6 6 18"></path></svg>';
?>
<div class="br-encabezado">
    <div class="br-encabezado__titulo">
        <a href="/admin/postulaciones" class="br-volver"><?= $icono ?><path d="m15 18-6-6 6-6"></path></svg>Volver a postulaciones</a>
        <h1><?= e($nombre) ?> <span class="br-etiqueta br-etiqueta--grande <?= e($etiquetas[$estado] ?? '') ?>"><?= e($estado) ?></span></h1>
        <p class="br-encabezado__meta">
            <span class="br-cifra"><?= e((string) $detalle['documento']) ?></span>
            <span><?= e((string) $detalle['convocatoria']) ?></span>
            <span>Enviada el <?= e(formato_fecha((string) $detalle['fecha'])) ?></span>
        </p>
    </div>
    <div class="br-cupos<?= $quedan === 0 ? ' br-cupos--lleno' : '' ?>" data-prueba="cupos">
        <div class="br-cupos__fila">
            <span class="br-cupos__cifra">Cupos: <?= $usados ?> de <?= $cupos ?> usados</span>
            <span class="br-cupos__resto"><?= $quedan === 0 ? 'No quedan cupos' : 'Quedan ' . $quedan ?></span>
        </div>
        <progress class="br-progreso br-progreso--fina" value="<?= $usados ?>" max="<?= max(1, $cupos) ?>" aria-label="Cupos usados de <?= e((string) $detalle['convocatoria']) ?>"></progress>
    </div>
</div>

<div class="br-detalle">
    <div class="br-detalle__principal">
        <?php if ($detalle['asignacion'] !== null) : ?>
            <?php $asignacion = $detalle['asignacion']; ?>
            <section class="br-tarjeta" aria-labelledby="titulo-asignacion" data-prueba="asignacion">
                <div class="br-tarjeta__cabecera">
                    <h2 id="titulo-asignacion">Asignación creada</h2>
                    <span class="br-etiqueta br-etiqueta--ok"><?= e((string) $asignacion['estado']) ?></span>
                </div>
                <dl class="br-ficha-datos">
                    <div><dt>Beneficio</dt><dd><?= e((string) $detalle['beneficio']) ?></dd></div>
                    <div><dt>Valor de cada pago</dt><dd class="br-cifra"><?= e(formato_pesos((string) $asignacion['valor_pago'])) ?></dd></div>
                    <div><dt>Pagos</dt><dd><?= (int) $asignacion['numero_pagos'] ?> en <?= (int) $asignacion['duracion_meses'] ?> meses</dd></div>
                    <div><dt>Inicio</dt><dd><?= e(formato_fecha((string) $asignacion['fecha_inicio'])) ?></dd></div>
                </dl>
            </section>
        <?php endif; ?>

        <section class="br-tarjeta" aria-labelledby="titulo-datos" data-prueba="datos">
            <div class="br-tarjeta__cabecera">
                <h2 id="titulo-datos">Datos del formulario</h2>
                <p class="br-suave">Compáralos con la base de aprendices del SENA.</p>
            </div>
            <dl class="br-ficha-datos">
                <div class="br-ficha-datos__ancho-celular"><dt>Nombre completo</dt><dd><?= e($nombre) ?></dd></div>
                <div><dt>Documento</dt><dd class="br-cifra"><?= $dato($detalle['documento']) ?></dd></div>
                <div><dt>Fecha de nacimiento</dt><dd><?= $detalle['fecha_nacimiento'] ? e(formato_fecha((string) $detalle['fecha_nacimiento'])) : '—' ?></dd></div>
                <div class="br-ficha-datos__ancho-celular"><dt>Programa de formación</dt><dd><?= $dato($detalle['programa']) ?></dd></div>
                <div><dt>Ficha</dt><dd class="br-cifra"><?= $dato($detalle['ficha']) ?></dd></div>
                <div class="br-ficha-datos__doble br-ficha-datos__ancho-celular"><dt>Correo</dt><dd><?= $dato($detalle['correo']) ?></dd></div>
                <div><dt>Celular</dt><dd class="br-cifra"><?= $dato($detalle['telefono']) ?></dd></div>
                <div><dt>EPS</dt><dd><?= $dato($detalle['eps']) ?></dd></div>
                <div class="br-ficha-datos__doble br-ficha-datos__ancho-celular"><dt>Dirección</dt><dd><?= $dato($detalle['direccion']) ?></dd></div>
                <div><dt>Banco</dt><dd><?= $dato($detalle['banco']) ?></dd></div>
                <div><dt>Tipo de cuenta</dt><dd><?= $dato($detalle['tipo_cuenta']) ?></dd></div>
                <div><dt>Número de cuenta</dt><dd class="br-cifra"><?= $dato($detalle['numero_cuenta']) ?></dd></div>
                <div class="br-ficha-datos__ancho-celular"><dt>Titular de la cuenta</dt><dd><?= $dato($detalle['titular']) ?></dd></div>
                <div class="br-ficha-datos__ancho"><dt>Por qué solicita el beneficio</dt><dd><?= $dato($detalle['motivo']) ?></dd></div>
            </dl>
        </section>

        <section class="br-tarjeta" aria-labelledby="titulo-documentos">
            <div class="br-tarjeta__cabecera">
                <h2 id="titulo-documentos">Documentos adjuntos</h2>
                <p class="br-suave"><?= $pendiente ? 'Marca cada documento como válido o no válido y guarda la revisión.' : 'Así quedó la revisión de cada documento.' ?></p>
            </div>
            <?php if ($soloRechazar) : ?>
                <p class="br-nota br-nota--peligro" data-prueba="solo-rechazar">
                    <strong>Hay un documento no válido.</strong>
                    <span>Por ahora esta postulación solo se puede rechazar: pedirle un documento nuevo al aprendiz llegará en una próxima versión. Si te equivocaste, mientras la postulación siga Pendiente puedes corregir esa marca a «Válido» y guardar la revisión. Para rechazar, escribe el motivo.</span>
                </p>
            <?php endif; ?>

            <?php if ($pendiente) : ?>
                <form method="post" action="<?= e($base) ?>/documentos">
                    <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
            <?php endif; ?>
            <ul class="br-documentos">
                <?php foreach ($detalle['documentos'] as $documento) : ?>
                    <?php
                    $marca = (string) $documento['estado_validacion'];
                    $idDocumento = (int) $documento['id_documento'];
                    $clase = ['Validado' => ' br-documento--valido', 'Rechazado' => ' br-documento--no-valido'][$marca] ?? '';
                    $tamano = number_format((float) $documento['tamano_mb'], 2, ',', '.') . ' MB';
                    ?>
                    <li class="br-documento<?= $clase ?>">
                        <span class="br-documento__icono" aria-hidden="true"><?= $iconoArchivo ?></span>
                        <div class="br-documento__texto">
                            <span class="br-documento__titulo"><?= e((string) $documento['tipo']) ?></span>
                            <span class="br-documento__archivo"><?= e((string) $documento['nombre_archivo']) ?> · <span class="br-sin-corte"><?= e(strtoupper((string) $documento['extension'])) ?> · <?= e($tamano) ?></span></span>
                            <?php if ($marca === 'Rechazado' && !$pendiente) : ?>
                                <span class="br-documento__motivo"><strong>Motivo:</strong> <?= e((string) $documento['observaciones']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="br-documento__acciones">
                            <!-- El archivo lo entrega DocumentosControlador (solo dueño o administrador). Las imágenes se ven en otra
                                 pestaña; el PDF se descarga (el visor de PDF no funciona con la protección «sandbox»). -->
                            <?php $esPdf = strtolower((string) $documento['extension']) === 'pdf'; ?>
                            <a href="/documentos/<?= $idDocumento ?>"<?= $esPdf ? '' : ' target="_blank" rel="noopener"' ?> class="br-boton br-boton--secundario br-boton--chico"><?= $esPdf ? 'Descargar PDF' : 'Ver documento' ?><span class="br-solo-lectores"> (<?= e((string) $documento['tipo']) ?>)</span></a>
                            <?php if ($pendiente) : ?>
                                <div class="br-revision" role="group" aria-label="Revisión de <?= e((string) $documento['tipo']) ?>">
                                    <label class="br-revision__opcion br-revision__opcion--valido"><input type="radio" name="estado[<?= $idDocumento ?>]" value="Validado"<?= $marca === 'Validado' ? ' checked' : '' ?>><?= $iconoVisto ?>Válido</label>
                                    <label class="br-revision__opcion br-revision__opcion--no-valido"><input type="radio" name="estado[<?= $idDocumento ?>]" value="Rechazado"<?= $marca === 'Rechazado' ? ' checked' : '' ?>><?= $iconoCerrar ?>No válido</label>
                                </div>
                            <?php else : ?>
                                <span class="br-etiqueta <?= $marca === 'Validado' ? 'br-etiqueta--ok' : ($marca === 'Rechazado' ? 'br-etiqueta--peligro' : '') ?>"><?= e($nombreDeMarca[$marca] ?? $marca) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($pendiente) : ?>
                            <div class="br-campo br-documento__observacion">
                                <label for="observacion-<?= $idDocumento ?>">Motivo por el que no es válido <span class="br-suave">(obligatorio)</span></label>
                                <textarea id="observacion-<?= $idDocumento ?>" name="observacion[<?= $idDocumento ?>]" class="br-entrada" rows="2" maxlength="1000" placeholder="Por ejemplo: la foto está borrosa y no se alcanza a leer"><?= e((string) $documento['observaciones']) ?></textarea>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($pendiente) : ?>
                    <div class="br-revision-guardar">
                        <button type="submit" class="br-boton br-boton--secundario">Guardar revisión</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="br-tarjeta" aria-labelledby="titulo-historial" data-prueba="historial">
            <div class="br-tarjeta__cabecera">
                <h2 id="titulo-historial">Historial</h2>
                <p class="br-suave">Quién cambió el estado y cuándo</p>
            </div>
            <ol class="br-historial">
                <?php foreach ($detalle['historial'] as $cambio) : ?>
                    <?php $envio = $cambio['estado_anterior'] === null; ?>
                    <li class="br-historial__item">
                        <span class="br-historial__cambio"><?= $envio ? 'Enviada · queda ' . e((string) $cambio['estado_nuevo']) : e((string) $cambio['estado_anterior']) . ' → ' . e((string) $cambio['estado_nuevo']) ?></span>
                        <span class="br-historial__quien"><?= e((string) $cambio['quien']) ?><?= $envio ? ' (aprendiz)' : '' ?> · <time datetime="<?= e(str_replace(' ', 'T', substr((string) $cambio['fecha'], 0, 16))) ?>"><?= e(formato_fecha_hora((string) $cambio['fecha'])) ?></time></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>

    <div class="br-detalle__lateral">
        <section class="br-tarjeta br-decision" aria-labelledby="titulo-decision">
            <div class="br-decision__cabecera">
                <h2 id="titulo-decision">Revisión</h2>
                <p class="br-suave">Dos pasos, en orden: primero confirmar los datos, después decidir.</p>
            </div>

            <?php if ($pendiente) : ?>
                <div class="br-decision__paso">
                    <div class="br-decision__titulo"><span class="br-decision__numero" aria-hidden="true">1</span><h3>Confirmar datos</h3></div>
                    <p class="br-decision__texto">Revisa que los datos y los documentos coincidan con la base de aprendices del SENA.</p>
                    <div class="br-decision__acciones">
                        <form method="post" action="<?= e($base) ?>/confirmar">
                            <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
                            <button type="submit" class="br-boton br-boton--primario" data-prueba="confirmar-datos"<?= $acciones['confirmar'] ? '' : ' disabled aria-describedby="motivo-confirmar"' ?>>Datos confirmados</button>
                        </form>
                        <?php if (!$acciones['confirmar'] && $motivoConfirmar !== null) : ?>
                            <p class="br-motivo" id="motivo-confirmar"><?= $iconoAviso ?><span><?= e($motivoConfirmar) ?></span></p>
                        <?php endif; ?>
                        <div class="br-decision__separador" role="presentation"></div>
                        <button type="button" class="br-boton br-boton--peligro" data-bs-toggle="modal" data-bs-target="#modal-rechazar" data-accion="<?= e($base) ?>/rechazar" aria-describedby="nota-rechazar">Rechazar</button>
                        <p class="br-suave" id="nota-rechazar">Te pediremos el motivo antes de rechazar.</p>
                    </div>
                </div>
                <div class="br-decision__paso br-decision__paso--bloqueado">
                    <div class="br-decision__titulo"><span class="br-decision__numero" aria-hidden="true">2</span><h3>Aprobar o rechazar</h3></div>
                    <div class="br-decision__acciones">
                        <button type="button" class="br-boton br-boton--primario" data-prueba="aprobar" disabled aria-describedby="motivo-aprobar">Aprobar</button>
                        <p class="br-motivo" id="motivo-aprobar" data-prueba="motivo-deshabilitado"><?= $iconoAviso ?><span><?= e((string) $acciones['motivoAprobarDeshabilitado']) ?></span></p>
                    </div>
                </div>
            <?php else : ?>
                <?php $validada = $cambioA('Validada'); ?>
                <?php if ($validada !== null) : ?>
                    <div class="br-decision__paso br-decision__paso--hecho">
                        <div class="br-decision__titulo"><span class="br-decision__numero" aria-hidden="true"><?= $iconoVisto ?></span><h3>Datos confirmados</h3></div>
                        <p class="br-decision__texto"><?= $quienYCuando($validada) ?>. Todos los documentos son válidos.</p>
                    </div>
                <?php endif; ?>

                <?php if ($estado === 'Validada') : ?>
                    <div class="br-decision__paso">
                        <div class="br-decision__titulo"><span class="br-decision__numero" aria-hidden="true">2</span><h3>Aprobar o rechazar</h3></div>
                        <?php if ($acciones['aprobar']) : ?>
                            <p class="br-decision__texto">Quedan <?= $quedan ?> cupos en <?= e((string) $detalle['convocatoria']) ?>. Al aprobar se crea la asignación y <?= e($nombre) ?> recibe un aviso.</p>
                        <?php endif; ?>
                        <div class="br-decision__acciones">
                            <?php if ($acciones['aprobar']) : ?>
                                <button type="button" class="br-boton br-boton--primario" data-prueba="aprobar" data-bs-toggle="modal" data-bs-target="#modal-aprobar" data-accion="<?= e($base) ?>/aprobar">Aprobar</button>
                            <?php else : ?>
                                <button type="button" class="br-boton br-boton--primario" data-prueba="aprobar" disabled aria-describedby="motivo-aprobar">Aprobar</button>
                                <p class="br-motivo br-motivo--alerta" id="motivo-aprobar" data-prueba="motivo-deshabilitado"><?= $iconoAviso ?><span><?= e((string) $acciones['motivoAprobarDeshabilitado']) ?>: <?= e((string) $detalle['convocatoria']) ?> tiene <?= $usados ?> de <?= $cupos ?> cupos usados.</span></p>
                                <div class="br-decision__separador" role="presentation"></div>
                            <?php endif; ?>
                            <button type="button" class="br-boton br-boton--peligro" data-bs-toggle="modal" data-bs-target="#modal-rechazar" data-accion="<?= e($base) ?>/rechazar" aria-describedby="nota-rechazar">Rechazar</button>
                            <p class="br-suave" id="nota-rechazar">Te pediremos el motivo antes de rechazar.</p>
                        </div>
                    </div>
                <?php elseif ($estado === 'Aprobada') : ?>
                    <div class="br-decision__paso br-decision__paso--hecho">
                        <div class="br-decision__titulo"><span class="br-decision__numero" aria-hidden="true"><?= $iconoVisto ?></span><h3>Aprobada</h3></div>
                        <div class="br-nota br-nota--ok"><strong>Aprobada por <?= e((string) ($cambioA('Aprobada')['quien'] ?? '')) ?></strong><span><?= e(formato_fecha_hora((string) ($cambioA('Aprobada')['fecha'] ?? ''))) ?>. Se creó la asignación y <?= e($nombre) ?> recibió un aviso.</span></div>
                    </div>
                <?php else : ?>
                    <?php $rechazo = $cambioA('Rechazada'); ?>
                    <div class="br-decision__paso br-decision__paso--rechazado">
                        <div class="br-decision__titulo"><span class="br-decision__numero" aria-hidden="true"><?= $iconoCerrar ?></span><h3>Rechazada</h3></div>
                        <div class="br-nota br-nota--peligro" data-prueba="motivo-rechazo"><strong>Rechazada por <?= $quienYCuando($rechazo) ?></strong><span>Motivo: <?= e((string) $detalle['justificacion_rechazo']) ?></span></div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php if ($estado === 'Pendiente' || $estado === 'Validada') : ?>
    <!-- Ventana de rechazo: pide el motivo (obligatorio). El botón que la abre le pone la dirección en "action". -->
    <div class="modal fade" id="modal-rechazar" tabindex="-1" aria-labelledby="modal-rechazar-titulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" action="<?= e($base) ?>/rechazar" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modal-rechazar-titulo">¿Rechazar a <?= e($nombre) ?>?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="br-campo">
                        <label for="motivo-rechazo">Motivo del rechazo <span class="br-suave">(obligatorio)</span></label>
                        <textarea id="motivo-rechazo" name="motivo" class="br-entrada" rows="4" maxlength="1000" required
                                  placeholder="Por ejemplo: ya recibe un apoyo igual de otro programa del SENA"></textarea>
                        <p class="br-ayuda">El aprendiz lo recibe en su aviso y queda en el historial.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="br-boton br-boton--secundario" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="br-boton br-boton--peligro">Sí, rechazar</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($acciones['aprobar']) : ?>
    <!-- Ventana de confirmación al aprobar: la acción crea la asignación y avisa al aprendiz, así que se pide confirmarla. -->
    <div class="modal fade" id="modal-aprobar" tabindex="-1" aria-labelledby="modal-aprobar-titulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" action="<?= e($base) ?>/aprobar" class="modal-content">
                <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modal-aprobar-titulo">¿Seguro que quieres aprobar a <?= e($nombre) ?>?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <ul class="br-lista">
                        <li>Se crea su asignación de <?= e((string) $detalle['convocatoria']) ?>.</li>
                        <li>Los cupos pasan a <strong><?= $usados + 1 ?> de <?= $cupos ?> usados</strong>.</li>
                        <li><?= e($nombre) ?> recibe un aviso en SIBEA.</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="br-boton br-boton--secundario" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="br-boton br-boton--primario">Sí, aprobar</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
