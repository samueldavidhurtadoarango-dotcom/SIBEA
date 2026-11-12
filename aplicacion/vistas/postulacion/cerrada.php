<?php

/**
 * VISTA "postulacion/cerrada": la convocatoria ya no recibe postulaciones, o el aprendiz ya se postuló.
 * Paso 5 del recorrido (la dibuja PostulacionAprendizControlador dentro del marco "aprendiz").
 * Reproduce la maqueta vistas/09-postulacion/html/convocatoria-cerrada; con $yaPostulada cambia el título
 * a «Ya te postulaste a esta convocatoria» (la base no permite dos postulaciones a la misma convocatoria).
 * Recibe: $convocatoria y $yaPostulada (true si el aprendiz ya se postuló).
 */
?>
<a class="br-volver" href="/mi-proceso">← Volver a Mi proceso</a>
<section class="br-vacio" data-prueba="resultado" aria-labelledby="titulo-cerrada">
    <span class="br-vacio__icono"><svg width="26" height="26" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></span>
    <?php if ($yaPostulada) : ?>
        <h1 id="titulo-cerrada">Ya te postulaste a esta convocatoria</h1>
        <p>Solo se puede enviar una postulación por convocatoria. Puedes ver cómo va en Mi proceso.</p>
    <?php else : ?>
        <h1 id="titulo-cerrada">Esta convocatoria ya cerró</h1>
        <p>La convocatoria «<?= e((string) $convocatoria['nombre']) ?>» ya no recibe postulaciones.</p>
        <p>Cuando se abra otra convocatoria, aparecerá en Mi proceso.</p>
    <?php endif; ?>
    <a class="br-boton br-boton--primario" href="/mi-proceso">Ir a Mi proceso</a>
</section>
