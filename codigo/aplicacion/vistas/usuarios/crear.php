<?php

/**
 * VISTA "usuarios/crear": formulario «Crear una cuenta de aprendiz».
 * Paso 5 del recorrido (la dibuja UsuariosControlador dentro del marco "admin").
 * Reproduce la maqueta 06-usuarios/crear-uno. Cambia el aviso azul: la contraseña
 * temporal no se envía por correo, se muestra una vez y se entrega con el Excel de entrega.
 * Recibe: $errores (mensaje por campo), $valores (lo ya escrito), $errorGeneral, $programas
 * (lista de id y nombre) y $tokenCsrf.
 */

$errores = $errores ?? [];
$valores = $valores ?? [];
$errorGeneral = $errorGeneral ?? null;

// Campos de texto: nombre del campo => [etiqueta, ayuda, clase extra, atributos].
$campos = [
    'nombre' => ['Nombres', '', '', 'autocomplete="off"'],
    'apellido' => ['Apellidos', '', '', 'autocomplete="off"'],
    'documento' => ['Número de documento', 'Solo números; si lo escribes con puntos, se quitan.', '', 'inputmode="numeric" autocomplete="off"'],
    'ficha' => ['Ficha', 'El número de la ficha de formación.', '', 'inputmode="numeric" autocomplete="off"'],
    'correo' => ['Correo', 'Es el usuario con el que el aprendiz ingresa.', 'br-formulario__ancho', 'type="email" autocomplete="off"'],
];
$icono = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
?>
<div class="br-encabezado">
    <div class="br-encabezado__titulo">
        <a href="/admin/usuarios" class="br-volver">Volver a usuarios</a>
        <h1>Crear una cuenta de aprendiz</h1>
        <p class="br-encabezado__sub">Para un aprendiz que llegó tarde o que no estaba en el Excel.</p>
    </div>
</div>

<?php if ($errores !== []) : ?>
    <div class="br-resumen-errores" role="alert">
        <h2>Revisa <?= count($errores) === 1 ? '1 cosa' : count($errores) . ' cosas' ?> para continuar</h2>
        <ul>
            <?php foreach ($errores as $campo => $mensaje) : ?>
                <li><a href="#<?= e($campo) ?>"><?= e($mensaje) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php if ($errorGeneral !== null) : ?>
    <p class="br-alerta br-alerta--error" role="alert"><?= e($errorGeneral) ?></p>
<?php endif; ?>

<form class="br-tarjeta" method="post" action="/admin/usuarios/nuevo" aria-labelledby="titulo-datos" novalidate>
    <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
    <h2 id="titulo-datos">Datos del aprendiz</h2>
    <div class="br-formulario">
        <?php foreach ($campos as $nombre => [$etiqueta, $ayuda, $clase, $atributos]) : ?>
            <div class="br-campo <?= e($clase) ?>">
                <label for="<?= e($nombre) ?>"><?= e($etiqueta) ?></label>
                <input id="<?= e($nombre) ?>" name="<?= e($nombre) ?>" value="<?= e($valores[$nombre] ?? '') ?>" required <?= $atributos ?>
                       <?= isset($errores[$nombre]) ? 'aria-invalid="true" aria-describedby="' . e($nombre) . '-error"' : '' ?>>
                <?php if ($ayuda !== '' && !isset($errores[$nombre])) : ?><p class="br-ayuda"><?= e($ayuda) ?></p><?php endif; ?>
                <?php if (isset($errores[$nombre])) : ?>
                    <p class="br-error-campo" id="<?= e($nombre) ?>-error"><?= $icono ?>><circle cx="12" cy="12" r="10"></circle><path d="M12 8v5M12 16h.01"></path></svg><span><?= e($errores[$nombre]) ?></span></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="br-campo br-formulario__ancho">
            <label for="programa">Programa</label>
            <select id="programa" name="programa" required <?= isset($errores['programa']) ? 'aria-invalid="true" aria-describedby="programa-error"' : '' ?>>
                <option value="">Elige un programa</option>
                <?php foreach ($programas as $programa) : ?>
                    <option value="<?= e($programa['nombre']) ?>"<?= ($valores['programa'] ?? '') === $programa['nombre'] ? ' selected' : '' ?>><?= e($programa['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errores['programa'])) : ?>
                <p class="br-error-campo" id="programa-error"><?= $icono ?>><circle cx="12" cy="12" r="10"></circle><path d="M12 8v5M12 16h.01"></path></svg><span><?= e($errores['programa']) ?></span></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="br-aviso br-aviso--azul br-aviso--grande">
        <?= $icono ?> class="br-aviso__icono"><rect x="4" y="11" width="16" height="10" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg>
        <div class="br-aviso__cuerpo">
            <p class="br-aviso__titulo">Se genera una contraseña temporal</p>
            <p class="br-aviso__detalle">Al crear la cuenta la verás una sola vez y descarga el Excel con las contraseñas temporales para entregárselas. El aprendiz debe cambiarla en su primer ingreso. Si el documento o el correo ya tienen cuenta, te avisamos y no se crea otra.</p>
        </div>
    </div>
    <div class="br-acciones-pie">
        <a href="/admin/usuarios" class="br-boton br-boton--secundario">Cancelar</a>
        <button type="submit" class="br-boton br-boton--primario">Crear cuenta</button>
    </div>
</form>
