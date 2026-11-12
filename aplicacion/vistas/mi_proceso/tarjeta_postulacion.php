<?php

/**
 * PARCIAL "mi_proceso/tarjeta_postulacion": una postulación del aprendiz, según su estado de pantalla.
 * Paso 5 del recorrido. Reproduce vistas/01-mi-proceso/html/{en-revision,seleccionado,no-seleccionado}.html
 * (los pagos y novedades son de la fase 2 y no se muestran). Si es «no-seleccionado» NO se muestra ningún motivo:
 * el servicio ya lo quitó y además esta vista no lo imprime nunca.
 * Recibe: $postulacion (una fila ya traducida por ServicioMiProceso::resumen(), con estadoVista y pasos).
 */

$idTitulo = 'titulo-postulacion-' . (int) $postulacion['id_postulacion'];
$icono = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>';
?>
<?php if ($postulacion['estadoVista'] === 'no-seleccionado') : ?>
<section class="br-tarjeta" data-prueba="resultado" aria-labelledby="<?= e($idTitulo) ?>">
    <h2 id="<?= e($idTitulo) ?>">Esta vez no fuiste seleccionado/a para <?= e((string) $postulacion['beneficio']) ?></h2>
    <p class="br-suave">Convocatoria <span class="br-sin-corte"><?= e((string) $postulacion['convocatoria']) ?></span> · Resultado del <?= e(formato_fecha((string) $postulacion['fecha_resultado'])) ?></p>
    <p class="br-texto">Sabemos que no es la noticia que esperabas. Que no hayas quedado esta vez no te impide participar de nuevo.</p>
    <p class="br-texto">Te invitamos a estar pendiente de las próximas convocatorias de Bienestar al Aprendiz. Cuando se abra una nueva, te avisaremos aquí, en la campanita.</p>
    <p class="br-suave">¿Tienes preguntas? Acércate a la oficina de Bienestar al Aprendiz de tu centro de formación.</p>
</section>
<?php else : ?>
<?php $seleccionado = $postulacion['estadoVista'] === 'seleccionado'; ?>
<section class="br-tarjeta" data-prueba="resultado" aria-labelledby="<?= e($idTitulo) ?>">
    <?php if ($seleccionado) : ?>
    <span class="br-etiqueta br-etiqueta--ok br-etiqueta--grande"><?= $icono ?>Fuiste seleccionado/a</span>
    <h2 id="<?= e($idTitulo) ?>"><?= e((string) $postulacion['beneficio']) ?></h2>
    <p class="br-suave">Convocatoria <span class="br-sin-corte"><?= e((string) $postulacion['convocatoria']) ?></span> · Aprobada el <?= e(formato_fecha((string) $postulacion['fecha_resultado'])) ?></p>
    <?php else : ?>
    <span class="br-etiqueta br-etiqueta--info br-etiqueta--grande"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>En revisión</span>
    <h2 id="<?= e($idTitulo) ?>">Tu postulación está en revisión</h2>
    <p class="br-suave"><?= e((string) $postulacion['beneficio']) ?> · Convocatoria <span class="br-sin-corte"><?= e((string) $postulacion['convocatoria']) ?></span> · Enviada el <?= e(formato_fecha((string) $postulacion['fecha_envio'])) ?></p>
    <?php endif; ?>
    <ol class="br-pasos" aria-label="Avance de tu postulación">
        <?php foreach ($postulacion['pasos'] as $numero => $paso) : ?>
        <li class="br-pasos__paso br-pasos__paso--<?= e($paso['avance']) ?>">
            <span class="br-pasos__punto"><?php if ($paso['avance'] === 'hecho') : ?><?= $icono ?><?php else : ?><span aria-hidden="true"><?= $numero + 1 ?></span><?php endif; ?></span>
            <span class="br-pasos__nombre"><?= e($paso['nombre']) ?> <span class="br-solo-lectores">(<?= $paso['avance'] === 'actual' ? 'en curso' : e($paso['avance']) ?>)</span></span>
            <span class="br-pasos__fecha"><?= $paso['fecha'] !== null ? e(formato_fecha($paso['fecha'])) : ($paso['avance'] === 'actual' ? 'En curso' : 'Pendiente') ?></span>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php if (!$seleccionado) : ?>
    <p class="br-texto">Todavía no hay una respuesta. Bienestar está revisando tu postulación y te avisaremos aquí, en la campanita, apenas cambie. Por ahora no tienes que hacer nada.</p>
    <?php endif; ?>
</section>
<?php if ($seleccionado) : ?>
<section class="br-tarjeta" data-prueba="resumen-beneficio" aria-labelledby="<?= e($idTitulo) ?>-beneficio">
    <h2 id="<?= e($idTitulo) ?>-beneficio">Tu beneficio</h2>
    <div class="br-datos">
        <div><div class="br-datos__clave">Cada pago</div><div class="br-datos__valor br-cifra"><?= e(formato_pesos($postulacion['valor_pago'])) ?></div></div>
        <div><div class="br-datos__clave">Duración</div><div class="br-datos__valor"><?= e((string) $postulacion['duracion_meses']) ?> meses</div></div>
    </div>
    <p class="br-suave">Aquí verás las fechas y el estado de tus pagos cuando Bienestar los registre.</p>
</section>
<?php else : ?>
<section class="br-tarjeta" aria-labelledby="<?= e($idTitulo) ?>-sigue">
    <h2 id="<?= e($idTitulo) ?>-sigue">¿Qué sigue?</h2>
    <ol class="br-lista">
        <li>Bienestar revisa tus datos y tus documentos.</li>
        <li>Se publica quiénes fueron seleccionados en la convocatoria.</li>
        <li>Si te escogen, aquí verás tu beneficio.</li>
    </ol>
</section>
<?php endif; ?>
<?php endif; ?>
