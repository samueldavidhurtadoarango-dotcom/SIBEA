<?php

/**
 * PARCIAL "alertas": dibuja los mensajes de una sola vez ("Guardado", "Tu sesión terminó"...).
 * Paso 5 del recorrido: los marcos (publica, admin, aprendiz) lo incluyen arriba del contenido.
 * Recibe: $mensajes, lista de ['tipo' => 'exito'|'error'|'aviso', 'texto' => string].
 * Los de error y aviso se anuncian a los lectores de pantalla de inmediato (role="alert").
 */

$mensajes = $mensajes ?? [];
$tiposValidos = ['exito', 'error', 'aviso'];
?>
<?php if ($mensajes !== []) : ?>
    <div class="br-alertas">
        <?php foreach ($mensajes as $mensaje) : ?>
            <?php $tipo = in_array($mensaje['tipo'], $tiposValidos, true) ? $mensaje['tipo'] : 'aviso'; ?>
            <p class="br-alerta br-alerta--<?= e($tipo) ?>" role="<?= $tipo === 'exito' ? 'status' : 'alert' ?>"><?= e($mensaje['texto']) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
