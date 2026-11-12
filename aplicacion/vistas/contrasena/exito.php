<?php

/**
 * VISTA "contrasena/exito": «Todo listo» tras cambiar la contraseña.
 * Paso 5 del recorrido (la dibuja ContrasenaControlador dentro del marco "publica").
 * Reproduce la maqueta 02-contrasena/exito.
 * Recibe: $inicio, la pantalla de inicio de su rol (/admin/postulaciones o /mi-proceso).
 */

$inicio = $inicio ?? '/ingresar';
?>
<div class="br-acceso__estado">
    <span class="br-acceso__sello">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"></path></svg>
    </span>
    <div class="br-acceso__encabezado">
        <h1 id="titulo">Todo listo</h1>
        <p role="status">Contraseña cambiada. Ya puedes entrar.</p>
    </div>
</div>

<a class="br-boton br-boton--primario br-boton--ancho" href="<?= e($inicio) ?>">Continuar</a>
