<?php

/**
 * VISTA "convocatorias/formulario": «Nueva convocatoria» y «Editar convocatoria» (es el mismo formulario).
 * Paso 5 del recorrido (la dibuja ConvocatoriasControlador dentro del marco "admin").
 * Reproduce la maqueta vistas/07-convocatorias/html/crear-editar. Al crear se elige si nace Activa o Inactiva;
 * al editar no se muestra esa opción: el estado se cambia desde la lista.
 * Recibe: $accion (a dónde se envía), $edicion (true si edita), $errores (mensaje por campo), $valores (lo ya
 * escrito), $errorGeneral, $beneficios (lista de id y nombre) y $tokenCsrf.
 */

$errores = $errores ?? [];
$valores = $valores ?? [];
$errorGeneral = $errorGeneral ?? null;
$icono = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

// Campos de una sola línea: nombre => [etiqueta, ayuda, atributos del input].
$campos = [
    'nombre' => ['Nombre', 'Beneficio y periodo, p. ej. «Plan de datos 2027-1».', 'autocomplete="off" maxlength="150"'],
    'fecha_inicio' => ['Fecha de inicio', 'Desde este día se reciben postulaciones.', 'type="date"'],
    'fecha_cierre' => ['Fecha de cierre', 'Debe ser después de la fecha de inicio.', 'type="date"'],
    'cupos' => ['Cupos', 'Cuántos aprendices se pueden aprobar.', 'type="number" min="1" step="1"'],
    'valor_pago' => ['Valor de cada pago', 'En pesos, sin signo $ (los puntos de miles sirven: 180.000).', 'inputmode="numeric" autocomplete="off"'],
    'numero_pagos' => ['Número de pagos', '', 'type="number" min="1" step="1"'],
    'duracion_meses' => ['Duración en meses', 'Cuánto dura el beneficio de cada aprendiz.', 'type="number" min="1" step="1"'],
];
$mensajeDeError = static function (string $campo) use ($errores, $icono): string {
    if (!isset($errores[$campo])) {
        return '';
    }

    return '<p class="br-error-campo" id="' . e($campo) . '-error">' . $icono . '><circle cx="12" cy="12" r="10"></circle><path d="M12 8v5M12 16h.01"></path></svg><span>' . e($errores[$campo]) . '</span></p>';
};
$atributosDeError = static fn (string $campo): string => isset($errores[$campo]) ? ' aria-invalid="true" aria-describedby="' . e($campo) . '-error"' : '';
?>
<div class="br-encabezado">
    <div class="br-encabezado__titulo">
        <a href="/admin/convocatorias" class="br-volver">Volver a convocatorias</a>
        <h1><?= $edicion ? 'Editar convocatoria' : 'Nueva convocatoria' ?></h1>
        <p class="br-encabezado__sub">Define el beneficio, las fechas, los cupos y cuánto recibe cada aprendiz aprobado.</p>
    </div>
</div>

<?php if ($errores !== []) : ?>
    <div class="br-resumen-errores" role="alert">
        <h2>Revisa <?= count($errores) === 1 ? '1 cosa' : count($errores) . ' cosas' ?> para continuar</h2>
        <ul>
            <?php foreach ($errores as $campo => $mensaje) : ?>
                <li><a href="#<?= e($campo) ?>"><?= e($mensaje) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php if ($errorGeneral !== null) : ?>
    <p class="br-alerta br-alerta--error" role="alert"><?= e($errorGeneral) ?></p>
<?php endif; ?>

<div class="br-detalle">
    <div class="br-detalle__principal">
        <form class="br-tarjeta" method="post" action="<?= e($accion) ?>" aria-labelledby="titulo-datos" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
            <h2 id="titulo-datos">Datos de la convocatoria</h2>
            <div class="br-formulario">
                <?php foreach ($campos as $nombre => [$etiqueta, $ayuda, $atributos]) : ?>
                    <div class="br-campo">
                        <label for="<?= e($nombre) ?>"><?= e($etiqueta) ?></label>
                        <input id="<?= e($nombre) ?>" name="<?= e($nombre) ?>" value="<?= e((string) ($valores[$nombre] ?? '')) ?>" required <?= $atributos ?><?= $atributosDeError($nombre) ?>>
                        <?php if ($ayuda !== '' && !isset($errores[$nombre])) : ?><p class="br-ayuda"><?= e($ayuda) ?></p><?php endif; ?>
                        <?= $mensajeDeError($nombre) ?>
                    </div>
                    <?php if ($nombre === 'nombre') : // el beneficio va justo después del nombre ?>
                        <div class="br-campo">
                            <label for="id_beneficio">Beneficio</label>
                            <select id="id_beneficio" name="id_beneficio" required<?= $atributosDeError('id_beneficio') ?>>
                                <option value="">Elige un beneficio</option>
                                <?php foreach ($beneficios as $beneficio) : ?>
                                    <option value="<?= (int) $beneficio['id'] ?>"<?= (string) ($valores['id_beneficio'] ?? '') === (string) $beneficio['id'] ? ' selected' : '' ?>><?= e($beneficio['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $mensajeDeError('id_beneficio') ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="br-campo br-formulario__ancho">
                    <label for="requisitos">Requisitos</label>
                    <textarea id="requisitos" name="requisitos" rows="5" required<?= $atributosDeError('requisitos') ?>><?= e((string) ($valores['requisitos'] ?? '')) ?></textarea>
                    <?php if (!isset($errores['requisitos'])) : ?><p class="br-ayuda">Uno por línea.</p><?php endif; ?>
                    <?= $mensajeDeError('requisitos') ?>
                </div>

                <?php if (!$edicion) : ?>
                    <fieldset class="br-opciones br-formulario__ancho">
                        <legend>¿Recibe postulaciones desde ya?</legend>
                        <div class="br-opciones__lista">
                            <?php $estadoElegido = (string) ($valores['estado'] ?? 'Inactiva'); ?>
                            <label class="br-opcion">
                                <input type="radio" name="estado" value="Activa"<?= $estadoElegido === 'Activa' ? ' checked' : '' ?>>
                                <span class="br-opcion__titulo">Activa</span>
                                <span class="br-opcion__ayuda">Recibe postulaciones entre la fecha de inicio y la de cierre.</span>
                            </label>
                            <label class="br-opcion">
                                <input type="radio" name="estado" value="Inactiva"<?= $estadoElegido !== 'Activa' ? ' checked' : '' ?>>
                                <span class="br-opcion__titulo">Inactiva</span>
                                <span class="br-opcion__ayuda">Queda guardada y nadie puede postularse. Puedes activarla cuando esté lista.</span>
                            </label>
                        </div>
                    </fieldset>
                <?php endif; ?>
            </div>
            <div class="br-acciones-pie">
                <a href="/admin/convocatorias" class="br-boton br-boton--secundario">Cancelar</a>
                <button type="submit" class="br-boton br-boton--primario">Guardar convocatoria</button>
            </div>
        </form>
    </div>
    <aside class="br-detalle__lateral">
        <section class="br-tarjeta" aria-labelledby="titulo-editar">
            <h2 id="titulo-editar">Al editar una convocatoria</h2>
            <ul class="br-lista br-texto">
                <li>Los cupos no pueden quedar por debajo de las postulaciones ya aprobadas.</li>
                <li>El valor, el número de pagos y la duración se copian a cada aprendiz al aprobarlo. Si los cambias, no afecta a los ya aprobados.</li>
                <li>Para que nadie más se postule, ciérrala desde la lista con «Cerrar convocatoria».</li>
            </ul>
        </section>
    </aside>
</div>
