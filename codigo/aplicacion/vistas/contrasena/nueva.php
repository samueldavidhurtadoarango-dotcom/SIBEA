<?php

/**
 * VISTA "contrasena/nueva": «Crea tu contraseña» (cambio obligatorio del primer ingreso).
 * Paso 5 del recorrido (la dibuja ContrasenaControlador dentro del marco "publica").
 * Reproduce la maqueta 02-contrasena/primer-ingreso y error-validacion.
 * Recibe: $errores (mensaje por campo: 'nueva', 'confirmar'), $nombre y $tokenCsrf.
 * Las contraseñas NUNCA se vuelven a dibujar: si hay errores, se escribe de nuevo.
 * Las reglas se marcan en vivo con sibea.js (atributos data-reglas y data-regla).
 */

$errores = $errores ?? [];
$nombre = $nombre ?? '';

// Los dos campos del formulario: id, texto de la etiqueta.
$campos = ['nueva' => 'Contraseña nueva', 'confirmar' => 'Confirma tu contraseña nueva'];
// Las reglas de la maqueta; el texto de cada una y su nombre para el JavaScript.
$reglas = [
    'longitud' => 'Mínimo 8 caracteres',
    'mayuscula' => 'Una letra mayúscula',
    'minuscula' => 'Una letra minúscula',
    'numero' => 'Un número',
];
?>
<div class="br-acceso__encabezado">
    <h1 id="titulo">Crea tu contraseña</h1>
    <p>Entraste con la contraseña que te dio Bienestar al Aprendiz. Por seguridad, debes cambiarla antes de continuar.</p>
    <?php if ($nombre !== '') : ?>
        <p class="br-acceso__cuenta">Hola, <?= e($nombre) ?></p>
    <?php endif; ?>
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

<form method="post" action="/contrasena/nueva" class="br-acceso__formulario" novalidate data-reglas>
    <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">

    <?php foreach ($campos as $id => $etiqueta) : ?>
        <div class="br-campo">
            <label for="<?= e($id) ?>"><?= e($etiqueta) ?></label>
            <div class="br-campo__clave">
                <input id="<?= e($id) ?>" name="<?= e($id) ?>" type="password" autocomplete="new-password"
                       <?= $id === 'nueva' ? 'data-campo-nueva' : '' ?>
                       <?= isset($errores[$id]) ? 'aria-invalid="true" aria-describedby="' . e($id) . '-error"' : '' ?>>
                <button type="button" class="br-campo__ojo" data-mostrar-clave aria-label="Mostrar contraseña" aria-pressed="false">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </button>
            </div>
            <?php if (isset($errores[$id])) : ?>
                <p class="br-error-campo" id="<?= e($id) ?>-error">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v5M12 16h.01"></path></svg>
                    <span><?= e($errores[$id]) ?></span>
                </p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="br-reglas-caja">
        <p id="reglas-titulo">Tu contraseña debe tener:</p>
        <ul class="br-reglas" aria-labelledby="reglas-titulo">
            <?php foreach ($reglas as $regla => $texto) : ?>
                <li data-regla="<?= e($regla) ?>" data-estado="pendiente">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path data-marca d=""></path></svg>
                    <span><span class="br-solo-lectores" data-aviso>Pendiente: </span><?= e($texto) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <button type="submit" class="br-boton br-boton--primario br-boton--ancho">Guardar contraseña</button>
</form>

<p class="br-acceso__ayuda">
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 8h.01"></path></svg>
    <span>¿Problemas para cambiarla? Comunícate con Bienestar al Aprendiz de tu centro de formación.</span>
</p>
