<?php

/**
 * VISTA "postulacion/enviada": «Listo, tu postulación fue enviada».
 * Paso 5 del recorrido (la dibuja PostulacionAprendizControlador dentro del marco "aprendiz", después de enviar).
 * Reproduce la maqueta vistas/09-postulacion/html/enviada: confirma el envío con la fecha, avisa que ya no se
 * puede editar y explica qué sigue.
 * Recibe: $convocatoria (beneficio y nombre) y $fechaEnvio («AAAA-MM-DD»).
 */
?>
<a class="br-volver" href="/mi-proceso">← Volver a Mi proceso</a>
<section class="br-tarjeta" data-prueba="resultado" aria-labelledby="titulo-enviada">
    <span class="br-etiqueta br-etiqueta--ok br-etiqueta--grande"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>Postulación enviada</span>
    <h1 id="titulo-enviada">Listo, tu postulación fue enviada</h1>
    <p class="br-suave"><?= e((string) $convocatoria['beneficio']) ?> · <span class="br-sin-corte"><?= e((string) $convocatoria['nombre']) ?></span> · Enviada el <?= e(formato_fecha($fechaEnvio)) ?></p>
    <p class="br-texto">Ya no puedes editarla. Si algo cambia, habla con Bienestar.</p>
    <a class="br-boton br-boton--primario" href="/mi-proceso">Ver mi proceso</a>
</section>
<section class="br-tarjeta" aria-labelledby="titulo-sigue">
    <h2 id="titulo-sigue">¿Qué sigue?</h2>
    <ol class="br-lista">
        <li>Bienestar revisa tus datos y documentos y te avisará aquí, en la campanita.</li>
        <li>Si falta algo, te pedirán el documento y lo subes desde Mi proceso.</li>
        <li>Cuando se publique quiénes fueron seleccionados, verás tu resultado en Mi proceso.</li>
    </ol>
</section>
