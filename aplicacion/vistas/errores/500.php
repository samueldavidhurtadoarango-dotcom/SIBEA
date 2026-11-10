<?php

/**
 * VISTA PROVISIONAL: error del servidor (500). La tarea 3 le da su diseño final.
 * Recibe: $detalle (texto del error, solo con app.depuracion activa; si no, null).
 */
?>
<h1>Algo salió mal</h1>
<p>Tuvimos un problema. Intenta de nuevo en unos minutos.</p>
<?php if (isset($detalle) && $detalle !== null) : ?>
    <pre><?= e($detalle) ?></pre>
<?php endif; ?>
