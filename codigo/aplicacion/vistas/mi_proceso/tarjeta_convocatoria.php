<?php

/**
 * PARCIAL "mi_proceso/tarjeta_convocatoria": una convocatoria abierta a la que el aprendiz aún no se postula.
 * Paso 5 del recorrido. Reproduce 01-mi-proceso/convocatoria-abierta. El botón «Postularme»
 * lleva al formulario de esa convocatoria (/convocatorias/{id}/postulacion).
 * Recibe: $convocatoria (una fila de RepositorioConvocatorias::abiertasPara()).
 */

$idConvocatoria = (int) $convocatoria['id_convocatoria'];
$idTitulo = 'titulo-convocatoria-' . $idConvocatoria;
?>
<section class="br-tarjeta" data-prueba="convocatoria" aria-labelledby="<?= e($idTitulo) ?>">
    <span class="br-etiqueta br-etiqueta--ok br-etiqueta--grande">Convocatoria abierta</span>
    <p class="br-texto">Hay una convocatoria abierta para ti</p>
    <h2 id="<?= e($idTitulo) ?>"><?= e((string) $convocatoria['nombre']) ?></h2>
    <div class="br-datos">
        <div><p class="br-datos__clave">Beneficio</p><p class="br-datos__valor"><?= e((string) $convocatoria['beneficio']) ?></p></div>
        <div><p class="br-datos__clave">Valor de cada pago</p><p class="br-datos__valor"><?= e(formato_pesos($convocatoria['valor_pago'])) ?></p></div>
        <div><p class="br-datos__clave">Número de pagos</p><p class="br-datos__valor"><?= e((string) $convocatoria['numero_pagos']) ?> pagos</p></div>
    </div>
    <p class="br-suave">Cierra el <?= e(formato_fecha((string) $convocatoria['fecha_cierre'])) ?></p>
    <a class="br-boton br-boton--primario" href="/convocatorias/<?= $idConvocatoria ?>/postulacion">Postularme<span class="br-solo-lectores"> a <?= e((string) $convocatoria['nombre']) ?></span></a>
</section>
