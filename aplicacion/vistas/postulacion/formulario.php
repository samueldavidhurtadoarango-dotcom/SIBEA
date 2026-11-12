<?php

/**
 * VISTA "postulacion/formulario": «Postúlate a …» y su versión con errores (es la misma pantalla).
 * Paso 5 del recorrido (la dibuja PostulacionAprendizControlador dentro del marco "aprendiz").
 * Reproduce la maqueta 09-postulacion/formulario y con-errores. El JavaScript (benefitrack.js,
 * formulario con data-prevalidar) solo avisa antes de enviar y muestra el nombre del archivo elegido; el
 * servidor siempre vuelve a validar todo.
 * Recibe: $convocatoria, $perfil («Tus datos», solo lectura), $listas (eps, bancos, tiposCuenta), $documentos
 * (campo => nombre legible), $valores (lo ya escrito), $errores (mensaje por campo), $accion y $tokenCsrf.
 */

$icono = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
$mensajeDeError = static function (string $campo) use ($errores, $icono): string {
    if (!isset($errores[$campo])) {
        return '';
    }

    return '<p class="br-error-campo" id="' . e($campo) . '-error">' . $icono . '><circle cx="12" cy="12" r="10"></circle><path d="M12 8v5M12 16h.01"></path></svg><span>' . e($errores[$campo]) . '</span></p>';
};
$atributosDeError = static fn (string $campo): string => isset($errores[$campo]) ? ' aria-invalid="true" aria-describedby="' . e($campo) . '-error"' : '';
$cantidadErrores = count($errores);
$pagos = (int) $convocatoria['numero_pagos'];
$nombreCompleto = trim(($perfil['nombre'] ?? '') . ' ' . ($perfil['apellido'] ?? ''));
?>
<a class="br-volver" href="/mi-proceso">← Volver a Mi proceso</a>

<?php if ($cantidadErrores > 0) : ?>
    <div class="br-resumen-errores" role="alert" data-prueba="resumen-errores">
        <h2>Revisa <?= $cantidadErrores === 1 ? '1 campo' : $cantidadErrores . ' campos' ?></h2>
        <p>No enviamos nada todavía. Corrige lo siguiente y vuelve a pulsar «Revisar y enviar». Los documentos hay que elegirlos de nuevo.</p>
        <ul>
            <?php foreach ($errores as $campo => $mensaje) : ?>
                <li><a href="#<?= e($campo) ?>"><?= e($mensaje) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="br-tarjeta" aria-labelledby="titulo-post">
    <span class="br-etiqueta br-etiqueta--ok br-etiqueta--grande">Convocatoria abierta</span>
    <h1 id="titulo-post">Postúlate a <?= e((string) $convocatoria['nombre']) ?></h1>
    <div class="br-datos">
        <div><p class="br-datos__clave">Beneficio</p><p class="br-datos__valor"><?= e((string) $convocatoria['beneficio']) ?></p></div>
        <div><p class="br-datos__clave">Valor de cada pago</p><p class="br-datos__valor"><?= e(formato_pesos((string) $convocatoria['valor_pago'])) ?></p></div>
        <div><p class="br-datos__clave">Número de pagos</p><p class="br-datos__valor"><?= $pagos ?> <?= $pagos === 1 ? 'pago' : 'pagos' ?></p></div>
    </div>
    <p class="br-suave">Cierra el <?= e(formato_fecha((string) $convocatoria['fecha_cierre'])) ?></p>
    <details class="br-plegable">
        <summary>Requisitos de la convocatoria</summary>
        <div>
            <ul class="br-lista">
                <?php foreach (preg_split('/\R/', trim((string) $convocatoria['requisitos'])) ?: [] as $requisito) : ?>
                    <?php if (trim($requisito) !== '') : ?><li><?= e(trim($requisito)) ?></li><?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </details>
</section>

<!-- enctype multipart: sin él el navegador no envía los archivos. novalidate: los avisos los da nuestro JavaScript y, sobre todo, el servidor. -->
<form class="br-formulario-largo" method="post" action="<?= e($accion) ?>" enctype="multipart/form-data" novalidate data-prevalidar>
    <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">

    <section class="br-tarjeta" aria-labelledby="sec-datos">
        <h2 id="sec-datos">Tus datos</h2>
        <p class="br-nota">Salen de tu cuenta y no se pueden cambiar aquí. ¿Algo está mal? Avísale a Bienestar antes de enviar.</p>
        <dl class="br-ficha-datos">
            <div class="br-ficha-datos__ancho"><dt>Nombre</dt><dd><?= e($nombreCompleto) ?></dd></div>
            <div><dt>Documento</dt><dd><?= e((string) ($perfil['documento'] ?? '')) ?></dd></div>
            <div><dt>Ficha</dt><dd><?= e((string) ($perfil['ficha'] ?? '')) ?></dd></div>
            <div class="br-ficha-datos__ancho"><dt>Correo</dt><dd><?= e((string) ($perfil['correo'] ?? '')) ?></dd></div>
            <div class="br-ficha-datos__ancho"><dt>Programa</dt><dd><?= e((string) ($perfil['programa'] ?? '')) ?></dd></div>
        </dl>
    </section>

    <section class="br-tarjeta" aria-labelledby="sec-contacto">
        <h2 id="sec-contacto">Contacto</h2>
        <div class="br-formulario">
            <div class="br-campo">
                <label for="telefono">Teléfono celular</label>
                <input type="tel" id="telefono" name="telefono" value="<?= e($valores['telefono'] ?? '') ?>" placeholder="300 123 4567" inputmode="tel" autocomplete="tel" data-requerido<?= $atributosDeError('telefono') ?>>
                <?= $mensajeDeError('telefono') ?>
            </div>
            <div class="br-campo">
                <label for="fecha_nacimiento">Fecha de nacimiento</label>
                <input type="text" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= e($valores['fecha_nacimiento'] ?? '') ?>" placeholder="día/mes/año" data-requerido aria-describedby="nacimiento-ayuda<?= isset($errores['fecha_nacimiento']) ? ' fecha_nacimiento-error' : '' ?>"<?= isset($errores['fecha_nacimiento']) ? ' aria-invalid="true"' : '' ?>>
                <span class="br-ayuda" id="nacimiento-ayuda">Por ejemplo: 15/03/2002.</span>
                <?= $mensajeDeError('fecha_nacimiento') ?>
            </div>
            <div class="br-campo br-formulario__ancho">
                <label for="direccion">Dirección de residencia</label>
                <input type="text" id="direccion" name="direccion" value="<?= e($valores['direccion'] ?? '') ?>" placeholder="Calle, número y barrio" autocomplete="street-address" maxlength="200" data-requerido<?= $atributosDeError('direccion') ?>>
                <?= $mensajeDeError('direccion') ?>
            </div>
        </div>
    </section>

    <section class="br-tarjeta" aria-labelledby="sec-salud">
        <h2 id="sec-salud">Salud</h2>
        <div class="br-formulario">
            <div class="br-campo br-formulario__ancho">
                <label for="id_eps">EPS a la que estás afiliado</label>
                <select id="id_eps" name="id_eps" data-requerido<?= $atributosDeError('id_eps') ?>>
                    <option value="">Elige tu EPS</option>
                    <?php foreach ($listas['eps'] as $opcion) : ?>
                        <option value="<?= (int) $opcion['id'] ?>"<?= (string) ($valores['id_eps'] ?? '') === (string) $opcion['id'] ? ' selected' : '' ?>><?= e($opcion['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $mensajeDeError('id_eps') ?>
            </div>
        </div>
    </section>

    <section class="br-tarjeta" aria-labelledby="sec-cuenta">
        <h2 id="sec-cuenta">Cuenta para recibir los pagos</h2>
        <p class="br-nota">La cuenta debe estar a tu nombre. Si no, el pago no se podrá hacer.</p>
        <div class="br-formulario">
            <div class="br-campo">
                <label for="id_entidad_bancaria">Entidad bancaria</label>
                <select id="id_entidad_bancaria" name="id_entidad_bancaria" data-requerido<?= $atributosDeError('id_entidad_bancaria') ?>>
                    <option value="">Elige tu banco</option>
                    <?php foreach ($listas['bancos'] as $opcion) : ?>
                        <option value="<?= (int) $opcion['id'] ?>"<?= (string) ($valores['id_entidad_bancaria'] ?? '') === (string) $opcion['id'] ? ' selected' : '' ?>><?= e($opcion['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $mensajeDeError('id_entidad_bancaria') ?>
            </div>
            <div class="br-campo">
                <label for="id_tipo_cuenta">Tipo de cuenta</label>
                <select id="id_tipo_cuenta" name="id_tipo_cuenta" data-requerido<?= $atributosDeError('id_tipo_cuenta') ?>>
                    <option value="">Elige el tipo</option>
                    <?php foreach ($listas['tiposCuenta'] as $opcion) : ?>
                        <option value="<?= (int) $opcion['id'] ?>"<?= (string) ($valores['id_tipo_cuenta'] ?? '') === (string) $opcion['id'] ? ' selected' : '' ?>><?= e($opcion['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $mensajeDeError('id_tipo_cuenta') ?>
            </div>
            <div class="br-campo">
                <label for="numero_cuenta">Número de cuenta</label>
                <input type="text" id="numero_cuenta" name="numero_cuenta" value="<?= e($valores['numero_cuenta'] ?? '') ?>" placeholder="Solo números" inputmode="numeric" autocomplete="off" maxlength="30" data-requerido<?= $atributosDeError('numero_cuenta') ?>>
                <?= $mensajeDeError('numero_cuenta') ?>
            </div>
            <div class="br-campo">
                <label for="titular_cuenta">Titular de la cuenta</label>
                <input type="text" id="titular_cuenta" name="titular_cuenta" value="<?= e($valores['titular_cuenta'] ?? '') ?>" placeholder="Nombre completo" maxlength="200" data-requerido<?= $atributosDeError('titular_cuenta') ?>>
                <?= $mensajeDeError('titular_cuenta') ?>
            </div>
        </div>
    </section>

    <section class="br-tarjeta" aria-labelledby="sec-documentos">
        <h2 id="sec-documentos">Documentos</h2>
        <p class="br-nota">Los tres son obligatorios. Formatos: PDF, JPG o PNG. Máximo 5 MB cada uno.</p>
        <div class="br-pila">
            <?php foreach ($documentos as $campo => $nombre) : ?>
                <div class="br-campo">
                    <span class="br-solo-lectores" id="<?= e($campo) ?>-ayuda">Archivo de <?= e($nombre) ?>: formatos permitidos PDF, JPG o PNG, máximo 5 MB.</span>
                    <label class="br-zona<?= isset($errores[$campo]) ? ' br-zona--error' : '' ?>" for="<?= e($campo) ?>">
                        <svg aria-hidden="true" class="br-zona__icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m17 8-5-5-5 5"></path><path d="M12 3v12"></path></svg>
                        <span class="br-zona__titulo"><?= e($nombre) ?></span>
                        <span class="br-zona__ayuda" aria-hidden="true">PDF, JPG o PNG · máximo 5 MB</span>
                        <span class="br-zona__elegido" data-elegido="<?= e($campo) ?>"></span>
                        <span class="br-boton br-boton--secundario" aria-hidden="true">Elegir archivo</span>
                        <input class="br-solo-lectores" type="file" id="<?= e($campo) ?>" name="<?= e($campo) ?>" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-requerido data-archivo aria-describedby="<?= e($campo) ?>-ayuda<?= isset($errores[$campo]) ? ' ' . e($campo) . '-error' : '' ?>"<?= isset($errores[$campo]) ? ' aria-invalid="true"' : '' ?>>
                    </label>
                    <?= $mensajeDeError($campo) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="br-tarjeta" aria-labelledby="sec-motivo">
        <h2 id="sec-motivo">¿Por qué necesitas este beneficio?</h2>
        <div class="br-campo">
            <label for="motivo">Cuéntanos tu situación <span class="br-suave">(opcional)</span></label>
            <textarea id="motivo" name="motivo" maxlength="500" aria-describedby="motivo-ayuda<?= isset($errores['motivo']) ? ' motivo-error' : '' ?>"<?= isset($errores['motivo']) ? ' aria-invalid="true"' : '' ?>><?= e($valores['motivo'] ?? '') ?></textarea>
            <span class="br-ayuda" id="motivo-ayuda">Máximo 500 caracteres.</span>
            <?= $mensajeDeError('motivo') ?>
        </div>
    </section>

    <section class="br-tarjeta" aria-label="Declaración">
        <div class="br-casilla-texto">
            <input type="checkbox" id="acepta_datos" name="acepta_datos" value="1" data-requerido<?= $atributosDeError('acepta_datos') ?>>
            <label for="acepta_datos">Declaro que la información es verdadera y autorizo el tratamiento de mis datos personales (Ley 1581 de 2012).</label>
        </div>
        <?= $mensajeDeError('acepta_datos') ?>
    </section>

    <div class="br-acciones-formulario">
        <p class="br-suave">Después de enviar no podrás editar tu postulación.</p>
        <button type="submit" class="br-boton br-boton--primario">Revisar y enviar</button>
    </div>
</form>
