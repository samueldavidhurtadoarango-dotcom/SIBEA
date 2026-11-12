<?php

/**
 * MARCO "aprendiz": envuelve las páginas del aprendiz.
 * Paso 5 del recorrido. Reproduce la barra de la maqueta aprobada
 * (barra del catálogo de pantallas): marca, saludo y «Salir»; debajo, una columna centrada.
 * Recibe: $contenido (HTML ya seguro), $mensajes, $usuarioNombre, $tokenCsrf y opcional $titulo.
 */

$titulo = $titulo ?? 'Mi proceso';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · BenefitRack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/benefitrack.css">
</head>
<body>
<header class="br-barra-aprendiz">
    <div class="br-barra-aprendiz__dentro">
        <span class="br-barra-aprendiz__marca">BenefitRack</span>
        <span class="br-barra-aprendiz__saludo">Hola, <?= e($usuarioNombre ?? '') ?></span>
        <!-- Salir cambia el estado de la sesión: es POST con token CSRF, no un enlace. -->
        <form method="post" action="/salir" class="br-salir-formulario">
            <input type="hidden" name="_csrf" value="<?= e($tokenCsrf) ?>">
            <button type="submit" class="br-boton-enlace br-barra-aprendiz__salir">Salir</button>
        </form>
    </div>
</header>

<main class="br-pagina">
    <?php include __DIR__ . '/../parciales/alertas.php'; ?>
    <?= $contenido ?>
</main>
<script src="/js/bootstrap.bundle.min.js"></script>
<script src="/js/benefitrack.js"></script>
</body>
</html>
