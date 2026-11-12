<?php

/**
 * VISTA: acceso denegado (403). Paso 5 del recorrido; la dibuja FiltroRol o FiltroCsrf.
 * Recibe: $mensaje (opcional): texto que explica por qué.
 */

$mensaje = $mensaje ?? 'Tu cuenta no tiene permiso para ver esta página.';
?>
<div class="br-error">
    <span class="br-error__codigo">Error 403</span>
    <h1>No tienes acceso</h1>
    <p><?= e($mensaje) ?></p>
    <a class="btn btn-primary" href="/">Volver al inicio</a>
</div>
