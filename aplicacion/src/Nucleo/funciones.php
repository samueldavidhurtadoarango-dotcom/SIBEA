<?php

/**
 * FUNCIONES AUXILIARES GLOBALES
 *
 * Composer carga este archivo en cada petición (ver "files" en composer.json).
 * Por ahora solo tiene e(), la función que usan TODAS las vistas para
 * mostrar un dato de forma segura.
 */

declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * Escapa un texto para mostrarlo dentro de HTML (defensa contra XSS).
     * Convierte < > & " ' en entidades, así un dato como <script> se ve
     * como texto y no se ejecuta.
     *
     * @param string|null $texto El dato a mostrar (null se trata como vacío).
     * @return string El texto seguro para poner en el HTML.
     */
    function e(?string $texto): string
    {
        return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
