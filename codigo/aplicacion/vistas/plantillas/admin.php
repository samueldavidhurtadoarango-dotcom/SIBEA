<?php

/**
 * MARCO "admin": envuelve las páginas del administrador.
 * Paso 5 del recorrido. Estructura de la maqueta aprobada (menú del catálogo de pantallas):
 * menú lateral (computador) o barra desplegable (celular), cabecera con la persona y el contenido.
 * Recibe: $contenido (HTML ya seguro), $mensajes, $rutaActual, $usuarioNombre, $tokenCsrf y opcional $titulo.
 */

$titulo = $titulo ?? 'Administración';
$palabras = preg_split('/\s+/', trim($usuarioNombre ?? '')) ?: [];
// Iniciales para el círculo de la cabecera: primera letra de las dos primeras palabras.
$iniciales = mb_strtoupper(mb_substr($palabras[0] ?? '', 0, 1) . mb_substr($palabras[1] ?? '', 0, 1));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · SIBEA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/benefitrack.css">
</head>
<body>
<div class="br-admin">
    <?php include __DIR__ . '/../parciales/menu_admin.php'; ?>

    <header class="br-cabecera">
        <nav class="br-migas" aria-label="Ruta"><span aria-current="page"><?= e($titulo) ?></span></nav>
        <div class="br-cabecera__derecha">
            <div class="br-usuario">
                <span class="br-usuario__inicial" aria-hidden="true"><?= e($iniciales) ?></span>
                <span class="br-usuario__texto">
                    <span class="br-usuario__nombre"><?= e($usuarioNombre ?? '') ?></span>
                    <span class="br-usuario__rol">Administración</span>
                </span>
            </div>
            <!-- Salir cambia el estado de la sesión: es POST con token CSRF, no un enlace. -->
            <form method="post" action="/salir" class="br-salir-formulario">
                <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
                <button type="submit" class="br-boton-enlace">Salir</button>
            </form>
        </div>
    </header>

    <main>
        <?php include __DIR__ . '/../parciales/alertas.php'; ?>
        <?= $contenido ?>
    </main>
</div>
<script src="/js/bootstrap.bundle.min.js"></script>
<script src="/js/benefitrack.js"></script>
</body>
</html>
