<?php

/**
 * MARCO "publica": envuelve las páginas sin sesión (inicio de sesión, errores).
 * Paso 5 del recorrido. Reproduce la maqueta aprobada 00-existentes/login:
 * panel azul de marca (solo en computador) y tarjeta blanca centrada.
 * Recibe: $contenido (HTML de la página, ya seguro), $mensajes, y opcional $titulo.
 */

$titulo = $titulo ?? 'BenefitRack';
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
<div class="br-acceso">
    <aside class="br-acceso__marca">
        <div class="br-acceso__marca-centro">
            <div>
                <div class="br-acceso__logo">Logo SENA</div>
                <span class="br-acceso__nombre">BenefitRack</span>
                <p class="br-acceso__lema">Tus beneficios de alimentación, datos y transporte, en un solo lugar.</p>
            </div>
            <ul class="br-acceso__beneficios">
                <li>
                    <span class="br-acceso__icono" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7"></path></svg></span>
                    <div><strong>Alimentación</strong><span>Apoyo para tus comidas mientras estudias.</span></div>
                </li>
                <li>
                    <span class="br-acceso__icono" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"></path></svg></span>
                    <div><strong>Plan de datos</strong><span>Internet en tu celular para tus clases y tareas.</span></div>
                </li>
                <li>
                    <span class="br-acceso__icono" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="15" rx="3"></rect><path d="M4 11h16M8 18v3M16 18v3M8 14.5h.01M16 14.5h.01"></path></svg></span>
                    <div><strong>Tarjeta cívica</strong><span>Transporte público para llegar a tu centro.</span></div>
                </li>
            </ul>
        </div>
        <span class="br-acceso__pie">Servicio Nacional de Aprendizaje · Bienestar al Aprendiz</span>
    </aside>

    <main class="br-acceso__lado">
        <div class="br-acceso__movil">
            <div class="br-acceso__logo">Logo SENA</div>
            <span class="br-acceso__movil-nombre">BenefitRack</span>
        </div>
        <div class="br-acceso__tarjeta">
            <?php include __DIR__ . '/../parciales/alertas.php'; ?>
            <?= $contenido ?>
        </div>
    </main>
</div>
<script src="/js/bootstrap.bundle.min.js"></script>
<script src="/js/benefitrack.js"></script>
</body>
</html>
