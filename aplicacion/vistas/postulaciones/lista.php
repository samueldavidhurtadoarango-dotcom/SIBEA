<?php

/**
 * VISTA "postulaciones/lista": la lista de postulaciones del administrador (su pantalla de inicio).
 * Paso 5 del recorrido (la dibuja PostulacionesAdminControlador dentro del marco "admin").
 * Reproduce la maqueta vistas/00-existentes/postulaciones: buscador, filtros, tabla con páginas de 20, el estado
 * vacío «No encontramos postulaciones con estos filtros» y la ventana «Descargar en Excel» con las columnas.
 * Recibe: $filtro (FiltroPostulaciones), $pagina (filas, total, pagina, paginas, porPagina), $beneficios, $programas,
 * $estados, $grupos (grupos de columnas del Excel) y $columnas (clave => título).
 * Todo dato pasa por e(): los nombres vienen de lo que escribieron los aprendices.
 */

use BenefitRack\Servicios\ResumenDocumentos;

$filas = $pagina['filas'];
$actual = $pagina['pagina'];
$paginas = $pagina['paginas'];
$total = $pagina['total'];
$activos = $filtro->comoConsulta();
$hayFiltros = $activos !== [];

// Qué etiqueta lleva cada estado (clases de la hoja de estilos).
$etiquetas = ['Pendiente' => 'br-etiqueta--pendiente', 'Validada' => 'br-etiqueta--validada', 'Aprobada' => 'br-etiqueta--ok', 'Rechazada' => 'br-etiqueta--peligro'];
// Enlace a otra página conservando los filtros.
$enlace = static fn (int $numero): string => '/admin/postulaciones?' . http_build_query($activos + ($numero > 1 ? ['pagina' => $numero] : []));
$primera = $total === 0 ? 0 : ($actual - 1) * $pagina['porPagina'] + 1;
$icono = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">';
// Una opción de un desplegable, marcada si es la elegida.
$opcion = static fn (string $valor, string $texto, bool $elegida): string => '<option value="' . e($valor) . '"' . ($elegida ? ' selected' : '') . '>' . e($texto) . '</option>';
?>
<div class="br-encabezado">
    <div class="br-encabezado__titulo">
        <h1>Postulaciones</h1>
        <p class="br-encabezado__sub">Revisa lo que enviaron los aprendices: sus datos y sus documentos.</p>
    </div>
    <div class="br-encabezado__acciones">
        <button type="button" class="br-boton br-boton--secundario" data-bs-toggle="modal" data-bs-target="#modal-excel"><?= $icono ?><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5"></path><path d="M12 15V3"></path></svg>Descargar en Excel</button>
    </div>
</div>

<section class="br-tarjeta br-listado" aria-label="Lista de postulaciones">
    <div class="br-listado__herramientas">
        <form method="get" action="/admin/postulaciones" role="search" class="br-filtros">
            <div class="br-buscador">
                <label for="buscar" class="br-solo-lectores">Buscar por nombre o documento</label>
                <?= $icono ?><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                <input id="buscar" name="q" class="br-entrada" type="search" placeholder="Nombre o documento" value="<?= e($filtro->texto) ?>">
            </div>
            <div class="br-campo">
                <label for="beneficio" class="br-solo-lectores">Beneficio</label>
                <select id="beneficio" name="beneficio">
                    <?= $opcion('', 'Todos los beneficios', $filtro->idBeneficio === null) ?>
                    <?php foreach ($beneficios as $beneficio) : ?>
                        <?= $opcion((string) $beneficio['id'], $beneficio['nombre'], $filtro->idBeneficio === (int) $beneficio['id']) ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="br-campo">
                <label for="programa" class="br-solo-lectores">Programa de formación</label>
                <select id="programa" name="programa">
                    <?= $opcion('', 'Todos los programas', $filtro->idPrograma === null) ?>
                    <?php foreach ($programas as $programa) : ?>
                        <?= $opcion((string) $programa['id'], $programa['nombre'], $filtro->idPrograma === (int) $programa['id']) ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="br-campo">
                <label for="estado" class="br-solo-lectores">Estado</label>
                <select id="estado" name="estado">
                    <?= $opcion('', 'Todos los estados', $filtro->estado === null) ?>
                    <?php foreach ($estados as $estado) : ?>
                        <?= $opcion($estado, $estado, $filtro->estado === $estado) ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="br-campo">
                <label for="desde">Enviada desde</label>
                <input id="desde" name="desde" type="date" value="<?= e((string) $filtro->desde) ?>">
            </div>
            <div class="br-campo">
                <label for="hasta">Enviada hasta</label>
                <input id="hasta" name="hasta" type="date" value="<?= e((string) $filtro->hasta) ?>">
            </div>
            <?php if ($filtro->idConvocatoria !== null) : ?>
                <!-- Viene del menú de la lista de convocatorias: se conserva al volver a filtrar. -->
                <input type="hidden" name="convocatoria" value="<?= (int) $filtro->idConvocatoria ?>">
            <?php endif; ?>
            <div class="br-encabezado__acciones">
                <button type="submit" class="br-boton br-boton--primario">Filtrar</button>
                <?php if ($hayFiltros) : ?>
                    <a href="/admin/postulaciones" class="br-boton br-boton--secundario">Quitar filtros</a>
                <?php endif; ?>
            </div>
        </form>
        <div class="br-listado__resumen">
            <span><?= $total === 0 ? 'Sin resultados' : 'Mostrando ' . $primera . ' a ' . ($primera + count($filas) - 1) . ' de ' . $total . ' postulaciones' ?></span>
        </div>
    </div>

    <?php if ($filas === []) : ?>
        <div class="br-vacio">
            <h2>No encontramos postulaciones con estos filtros</h2>
            <p><?= $hayFiltros ? 'Prueba con otro nombre o documento, o quita algún filtro.' : 'Cuando un aprendiz envíe su postulación, la verás aquí.' ?></p>
        </div>
    <?php else : ?>
        <div class="br-tabla-envoltura">
            <table class="br-tabla br-tabla--listado br-tabla--fichas br-tabla--sin-seleccion" aria-label="Postulaciones">
                <thead><tr>
                    <th scope="col">Aprendiz</th><th scope="col">Convocatoria</th><th scope="col">Programa</th><th scope="col">Estado</th><th scope="col">Documentos</th><th scope="col">Enviada</th><th scope="col"><span class="br-solo-lectores">Acciones</span></th>
                </tr></thead>
                <tbody>
                <?php foreach ($filas as $fila) : ?>
                    <?php $estadoFila = (string) $fila['estado']; ?>
                    <tr>
                        <td class="br-tabla__titulo"><span class="br-tabla__principal"><?= e($fila['nombre']) ?></span><span class="br-tabla__sub"><?= e($fila['documento']) ?></span></td>
                        <td data-etiqueta="Convocatoria" class="br-tabla__ancha"><?= e($fila['convocatoria']) ?><span class="br-tabla__sub"><?= e($fila['beneficio']) ?></span></td>
                        <td data-etiqueta="Programa" class="br-tabla__ancha"><?= e($fila['programa'] ?? 'Sin programa') ?><?php if (($fila['ficha'] ?? '') !== '') : ?><span class="br-tabla__sub">Ficha <?= e($fila['ficha']) ?></span><?php endif; ?></td>
                        <td class="br-tabla__estado"><span class="br-etiqueta <?= e($etiquetas[$estadoFila] ?? 'br-etiqueta--neutra') ?>"><?= e($estadoFila) ?></span></td>
                        <td data-etiqueta="Documentos"><?= e(ResumenDocumentos::texto($fila)) ?></td>
                        <td data-etiqueta="Enviada"><?= e(formato_fecha((string) $fila['fecha'])) ?></td>
                        <td class="br-tabla__acciones"><div class="br-tabla__acciones-dentro">
                            <a href="/admin/postulaciones/<?= (int) $fila['id_postulacion'] ?>" class="br-boton br-boton--secundario br-boton--chico">Ver<span class="br-solo-lectores"> la postulación de <?= e($fila['nombre']) ?></span></a>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="br-listado__pie">
            <span>Página <?= $actual ?> de <?= $paginas ?></span>
            <nav class="br-paginacion" aria-label="Páginas">
                <?php if ($actual > 1) : ?>
                    <a href="<?= e($enlace($actual - 1)) ?>">Anterior</a>
                <?php else : ?>
                    <span aria-disabled="true">Anterior</span>
                <?php endif; ?>
                <span aria-current="page" class="br-paginacion__numero"><?= $actual ?></span>
                <?php if ($actual < $paginas) : ?>
                    <a href="<?= e($enlace($actual + 1)) ?>">Siguiente</a>
                <?php else : ?>
                    <span aria-disabled="true">Siguiente</span>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</section>

<!-- Ventana «Descargar en Excel»: es un GET (solo lee datos). Lleva los filtros activos como campos ocultos para que
     el Excel traiga lo mismo que se ve, y una casilla por columna (todas marcadas al abrirla). -->
<div class="modal fade" id="modal-excel" tabindex="-1" aria-labelledby="modal-excel-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form method="get" action="/admin/postulaciones/excel" class="modal-content" data-descarga>
            <?php foreach ($activos as $nombre => $valor) : ?>
                <input type="hidden" name="<?= e($nombre) ?>" value="<?= e($valor) ?>">
            <?php endforeach; ?>
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="modal-excel-titulo">Descargar en Excel</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>Se descargan las <strong><?= $total ?></strong> postulaciones que coinciden con los filtros actuales. Elige las columnas que quieres.</p>
                <?php foreach ($grupos as $titulo => $claves) : ?>
                    <fieldset class="br-campo">
                        <legend class="fs-6 fw-semibold"><?= e($titulo) ?></legend>
                        <?php foreach ($claves as $clave) : ?>
                            <div class="br-casilla-texto">
                                <input type="checkbox" id="col-<?= e($clave) ?>" name="columnas[]" value="<?= e($clave) ?>" checked>
                                <label for="col-<?= e($clave) ?>"><?= e($columnas[$clave]) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="br-boton br-boton--secundario" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="br-boton br-boton--primario">Descargar</button>
            </div>
        </form>
    </div>
</div>
