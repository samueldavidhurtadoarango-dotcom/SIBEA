<?php

/**
 * VISTA "mi_proceso/ver": la pantalla de inicio del aprendiz.
 * Paso 5 del recorrido (la dibuja MiProcesoControlador dentro del marco "aprendiz"). Reproduce las maquetas
 * pantalla 01-mi-proceso del catálogo: una tarjeta por cada convocatoria abierta sin postulación y una por cada postulación;
 * si no hay nada, el aviso «En el momento no hay convocatorias disponibles».
 * Recibe: $estado ('sin-postulacion' o 'con-actividad'), $abiertas y $postulaciones (ver ServicioMiProceso::resumen()).
 */
?>
<?php if ($estado === 'sin-postulacion') : ?>
<section class="br-vacio" data-prueba="resultado" aria-labelledby="titulo-vacio">
    <span class="br-vacio__icono"><svg aria-hidden="true" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path><path d="M14 2v4a2 2 0 0 0 2 2h4"></path><path d="M9 13h6"></path><path d="M9 17h4"></path></svg></span>
    <h1 id="titulo-vacio">En el momento no hay convocatorias disponibles</h1>
    <p>Cuando se abra una convocatoria, aparecerá aquí y te llegará un aviso a la campanita.</p>
    <p class="br-suave">Aún no tienes postulaciones.</p>
    <p>Si crees que esto es un error, acércate a la oficina de Bienestar al Aprendiz de tu centro de formación.</p>
</section>
<?php else : ?>
<h1 class="br-solo-lectores">Mi proceso</h1>
    <?php foreach ($abiertas as $convocatoria) : ?>
        <?php include __DIR__ . '/tarjeta_convocatoria.php'; ?>
    <?php endforeach; ?>
    <?php foreach ($postulaciones as $postulacion) : ?>
        <?php include __DIR__ . '/tarjeta_postulacion.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
