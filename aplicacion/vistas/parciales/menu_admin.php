<?php

/**
 * PARCIAL "menu_admin": el menú del administrador (lateral en computador, desplegable en celular).
 * Paso 5 del recorrido: lo incluye el marco admin. Misma estructura que la maqueta
 * aprobada (vistas/_comun/menu-admin.html). En el MVP solo hay tres opciones.
 * Recibe: $rutaActual (la URL pedida), para marcar con aria-current el enlace de la página donde se está.
 */

$rutaActual = $rutaActual ?? '';
$opciones = [
    ['Convocatorias', '/admin/convocatorias', '<rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 10h18"></path>'],
    ['Postulaciones', '/admin/postulaciones', '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"></path><path d="M14 3v5h5M9 14l2 2 4-4"></path>'],
    ['Usuarios', '/admin/usuarios', '<circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20a6.5 6.5 0 0 1 13 0"></path><path d="M16 4.5a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-4-6"></path>'],
];
// La opción activa es la que contiene la URL actual (así /admin/usuarios/5 también marca «Usuarios»).
$esActiva = static fn (string $url): bool => $rutaActual === $url || str_starts_with($rutaActual, $url . '/');
?>
<nav class="br-lateral" aria-label="Menú principal">
    <div class="br-lateral__marca">
        <div class="br-logo-sena">Logo SENA</div>
        <div>
            <span class="br-lateral__nombre">BenefitRack</span>
            <span class="br-lateral__sub">Bienestar al Aprendiz</span>
        </div>
    </div>
    <div class="br-lateral__lista">
        <?php foreach ($opciones as [$texto, $url, $icono]) : ?>
            <a href="<?= e($url) ?>" class="br-lateral__enlace"<?= $esActiva($url) ? ' aria-current="page"' : '' ?>>
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><?= $icono ?></svg>
                <span><?= e($texto) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <p class="br-lateral__nota">El sistema solo registra los pagos; no mueve dinero.</p>
</nav>

<div class="br-movil">
    <div class="br-movil__marca">
        <span class="br-movil__logo">SENA</span>
        <span>BenefitRack</span>
    </div>
    <details>
        <summary aria-label="Abrir menú"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"></path></svg></summary>
        <nav class="br-movil__menu" aria-label="Menú del celular">
            <?php foreach ($opciones as [$texto, $url]) : ?>
                <a href="<?= e($url) ?>"<?= $esActiva($url) ? ' aria-current="page"' : '' ?>><?= e($texto) ?></a>
            <?php endforeach; ?>
        </nav>
    </details>
</div>
