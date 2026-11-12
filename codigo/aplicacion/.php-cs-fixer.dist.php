<?php

declare(strict_types=1);

// Reglas de formato: PSR-12 más algunas básicas de orden y limpieza.
$buscador = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/pruebas', __DIR__ . '/public'])
    ->append([__DIR__ . '/rutas.php']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'ordered_imports' => true,
        'no_unused_imports' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($buscador);
