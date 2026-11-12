<?php

/**
 * VISTA: error del servidor (500). Paso 5 del recorrido; la dibuja Aplicacion cuando algo falla sin esperarlo.
 * Recibe: $detalle (texto técnico del error, solo con app.depuracion activa; si no, null).
 */
?>
<div class="br-error">
    <span class="br-error__codigo">Error 500</span>
    <h1>Algo salió mal</h1>
    <p>Tuvimos un problema de nuestro lado. Intenta de nuevo en unos minutos.</p>
    <?php if (isset($detalle) && $detalle !== null) : ?>
        <pre class="br-error__detalle"><?= e($detalle) ?></pre>
    <?php endif; ?>
    <a class="btn btn-primary" href="/">Volver al inicio</a>
</div>
