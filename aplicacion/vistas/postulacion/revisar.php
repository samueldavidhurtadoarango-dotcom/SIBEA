<?php

/**
 * VISTA "postulacion/revisar": «Revisa tu postulación antes de enviarla».
 * Paso 5 del recorrido (la dibuja PostulacionAprendizControlador dentro del marco "aprendiz").
 * Reproduce la maqueta 09-postulacion/revisar-y-enviar. Solo muestra lo ya validado: «Corregir» vuelve
 * al formulario y «Enviar postulación» (POST con token CSRF) crea la postulación (tarea siguiente).
 * Recibe: $convocatoria, $perfil, $datos (ya limpios), $archivos (campo => nombre_original, bytes), $documentos
 * (campo => nombre legible), $eps, $banco, $tipoCuenta (los nombres elegidos), $volver, $accionEnviar y $tokenCsrf.
 */

$pagos = (int) $convocatoria['numero_pagos'];
$nombreCompleto = trim(($perfil['nombre'] ?? '') . ' ' . ($perfil['apellido'] ?? ''));
// «1,2 MB» (con coma decimal, como se escribe en Colombia).
$tamano = static fn (int $bytes): string => number_format($bytes / 1048576, 1, ',', '.') . ' MB';
?>
<a class="br-volver" href="/mi-proceso">← Volver a Mi proceso</a>
<section class="br-tarjeta" aria-labelledby="titulo-revisar">
    <h1 id="titulo-revisar">Revisa tu postulación antes de enviarla</h1>
    <p class="br-suave"><?= e((string) $convocatoria['beneficio']) ?> · <?= e((string) $convocatoria['nombre']) ?> · <?= $pagos ?> <?= $pagos === 1 ? 'pago' : 'pagos' ?> de <?= e(formato_pesos((string) $convocatoria['valor_pago'])) ?></p>
    <div class="br-aviso br-aviso--naranja br-aviso--grande" data-prueba="aviso-no-editar">
        <svg class="br-aviso__icono" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg>
        <div class="br-aviso__cuerpo">
            <p class="br-aviso__titulo">Después de enviar no podrás editar tu postulación.</p>
            <p class="br-aviso__detalle">Si algo cambia, habla con Bienestar. Si ves un error ahora, pulsa «Corregir».</p>
        </div>
    </div>

    <div class="br-revision-bloque">
        <h2>Tus datos</h2>
        <dl class="br-ficha-datos">
            <div class="br-ficha-datos__ancho"><dt>Nombre</dt><dd><?= e($nombreCompleto) ?></dd></div>
            <div><dt>Documento</dt><dd><?= e((string) ($perfil['documento'] ?? '')) ?></dd></div>
            <div><dt>Ficha</dt><dd><?= e((string) ($perfil['ficha'] ?? '')) ?></dd></div>
            <div class="br-ficha-datos__ancho"><dt>Programa</dt><dd><?= e((string) ($perfil['programa'] ?? '')) ?></dd></div>
        </dl>
    </div>

    <div class="br-revision-bloque">
        <h2>Contacto y salud</h2>
        <dl class="br-ficha-datos">
            <div><dt>Teléfono celular</dt><dd><?= e((string) $datos['telefono']) ?></dd></div>
            <div><dt>Fecha de nacimiento</dt><dd><?= e(formato_fecha((string) $datos['fecha_nacimiento'])) ?></dd></div>
            <div class="br-ficha-datos__ancho"><dt>Dirección</dt><dd><?= e((string) $datos['direccion']) ?></dd></div>
            <div><dt>EPS</dt><dd><?= e($eps) ?></dd></div>
        </dl>
    </div>

    <div class="br-revision-bloque">
        <h2>Cuenta para recibir los pagos</h2>
        <dl class="br-ficha-datos">
            <div><dt>Entidad</dt><dd><?= e($banco) ?></dd></div>
            <div><dt>Tipo de cuenta</dt><dd><?= e($tipoCuenta) ?></dd></div>
            <div class="br-ficha-datos__ancho"><dt>Número de cuenta</dt><dd><?= e((string) $datos['numero_cuenta']) ?></dd></div>
            <div class="br-ficha-datos__ancho"><dt>Titular</dt><dd><?= e((string) $datos['titular_cuenta']) ?></dd></div>
        </dl>
    </div>

    <div class="br-revision-bloque">
        <h2>Documentos</h2>
        <div class="br-pila">
            <?php foreach ($documentos as $campo => $nombre) : ?>
                <div class="br-archivo-fila">
                    <span class="br-suave"><?= e($nombre) ?></span>
                    <div class="br-archivo br-archivo--ok">
                        <svg aria-hidden="true" class="br-archivo__icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                        <div class="br-archivo__texto">
                            <span class="br-archivo__nombre"><?= e($archivos[$campo]['nombre_original']) ?></span>
                            <span class="br-suave"><?= e($tamano($archivos[$campo]['bytes'])) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="br-revision-bloque">
        <h2>Tu motivo</h2>
        <?php if ($datos['motivo'] !== '') : ?>
            <p class="br-texto"><?= nl2br(e((string) $datos['motivo'])) ?></p>
        <?php else : ?>
            <p class="br-suave">No escribiste un motivo (es opcional).</p>
        <?php endif; ?>
        <p class="br-suave">Declaraste que la información es verdadera y autorizaste el tratamiento de tus datos (Ley 1581 de 2012).</p>
    </div>

    <!-- Enviar crea la postulación: es POST con token CSRF, no un enlace. -->
    <form method="post" action="<?= e($accionEnviar) ?>" class="br-acciones-formulario">
        <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
        <a class="br-boton br-boton--secundario" href="<?= e($volver) ?>">Corregir</a>
        <button type="submit" class="br-boton br-boton--primario">Enviar postulación</button>
    </form>
</section>
