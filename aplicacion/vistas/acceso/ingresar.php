<?php

/**
 * VISTA "acceso/ingresar": la pantalla de inicio de sesión.
 * Paso 5 del recorrido (la dibuja AccesoControlador dentro del marco "publica").
 * Reproduce la maqueta vistas/00-existentes/login/html (6 estados):
 *   normal, validacion, datos-incorrectos, cuenta-bloqueada, cuenta-inactiva y
 *   cargando (este último lo activa benefitrack.js al enviar el formulario).
 * Recibe: $estado, $correo (lo que escribió), $errores (mensajes por campo), $tokenCsrf.
 * La contraseña nunca se vuelve a dibujar. Todo dato pasa por e().
 */

$estado = $estado ?? 'normal';
$correo = $correo ?? '';
$errores = $errores ?? [];
?>
<div class="br-ingreso__titulo">
    <h1>Inicia sesión</h1>
    <p>Qué bueno verte. Ingresa con tu correo para continuar.</p>
</div>

<form method="post" action="/ingresar" class="br-formulario" novalidate data-cargando>
    <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">

    <?php if ($estado === 'datos-incorrectos') : ?>
        <div class="br-aviso-ingreso br-aviso-ingreso--error" role="alert">
            <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5M12 16h.01"></path></svg>
            <div>
                <p><strong>Correo o contraseña incorrectos.</strong></p>
                <p>Revisa que estén bien escritos e inténtalo de nuevo. Después de 3 intentos, tu cuenta se bloquea por 15 minutos.</p>
            </div>
        </div>
    <?php elseif ($estado === 'cuenta-bloqueada') : ?>
        <div class="br-aviso-ingreso br-aviso-ingreso--bloqueada" role="alert">
            <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg>
            <div>
                <p><strong>Tu cuenta está protegida</strong></p>
                <p>Tu cuenta se bloqueó por 3 intentos fallidos. Puedes intentarlo de nuevo en 15 minutos.</p>
                <p>Si no recuerdas tu contraseña, pide a Bienestar al Aprendiz que te la restablezca.</p>
            </div>
        </div>
    <?php elseif ($estado === 'cuenta-inactiva') : ?>
        <div class="br-aviso-ingreso br-aviso-ingreso--inactiva" role="alert">
            <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20a6.5 6.5 0 0 1 13 0"></path><path d="m16.5 8 4.5 4.5M21 8l-4.5 4.5"></path></svg>
            <div>
                <p>Tu cuenta está inactiva. Comunícate con Bienestar al Aprendiz para activarla.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="br-campo">
        <label for="correo">Correo electrónico</label>
        <input id="correo" name="correo" type="email" autocomplete="email" inputmode="email"
               placeholder="nombre@soy.sena.edu.co" value="<?= e($correo) ?>"
               <?= isset($errores['correo']) ? 'aria-invalid="true" aria-describedby="correo-error"' : '' ?>>
        <?php if (isset($errores['correo'])) : ?>
            <p id="correo-error" class="br-campo__error"><?= e($errores['correo']) ?></p>
        <?php endif; ?>
    </div>

    <div class="br-campo">
        <label for="clave">Contraseña</label>
        <div class="br-campo__clave">
            <input id="clave" name="clave" type="password" autocomplete="current-password"
                   <?= isset($errores['clave']) ? 'aria-invalid="true" aria-describedby="clave-error"' : '' ?>>
            <button type="button" class="br-campo__ojo" data-mostrar-clave aria-label="Mostrar contraseña" aria-pressed="false">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </button>
        </div>
        <?php if (isset($errores['clave'])) : ?>
            <p id="clave-error" class="br-campo__error"><?= e($errores['clave']) ?></p>
        <?php endif; ?>
    </div>

    <p class="br-ingreso__olvido">Si olvidaste tu contraseña, pide a Bienestar al Aprendiz que te la restablezca.</p>

    <button type="submit" class="br-boton-ingresar" data-texto-cargando="Ingresando…">Ingresar</button>
</form>

<p class="br-ingreso__nota">Tu cuenta la crea Bienestar al Aprendiz. Si no puedes entrar, comunícate con tu centro de formación.</p>
