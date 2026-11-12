<?php

/**
 * FUNCIONES AUXILIARES GLOBALES
 *
 * Composer carga este archivo en cada petición (ver "files" en composer.json).
 * e() escapa un dato para mostrarlo de forma segura; formato_pesos() y
 * formato_fecha() y formato_fecha_hora() lo muestran como se lee en Colombia («$ 180.000», «15 nov 2026»,
 * «5 nov 2026, 9:40 a. m.»).
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

if (!function_exists('formato_pesos')) {
    /**
     * Muestra un monto en pesos con puntos de miles: 180000 -> «$ 180.000».
     *
     * @param string|int|float $monto El monto (la base lo entrega como texto: «180000.00»).
     * @return string El monto listo para mostrar (sin centavos: los pagos son en pesos enteros).
     */
    function formato_pesos(string|int|float $monto): string
    {
        return '$ ' . number_format((float) $monto, 0, ',', '.');
    }
}

if (!function_exists('formato_fecha')) {
    /**
     * Muestra una fecha con el mes corto en español: «2026-11-15» -> «15 nov 2026».
     * Se arma a mano (y no con strftime o setlocale) para no depender del idioma del servidor.
     *
     * @param string|null $fecha   Fecha «AAAA-MM-DD» (si trae hora, se ignora); null se muestra vacío.
     * @param bool        $conAnio false para omitir el año (p. ej. el inicio de un rango: «1 ago – 30 nov 2026»).
     * @return string La fecha lista para mostrar.
     */
    function formato_fecha(?string $fecha, bool $conAnio = true): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        [$anio, $mes, $dia] = array_map('intval', explode('-', substr($fecha, 0, 10)));

        return $dia . ' ' . $meses[$mes - 1] . ($conAnio ? ' ' . $anio : '');
    }
}

if (!function_exists('formato_fecha_hora')) {
    /**
     * Muestra fecha y hora con reloj de 12 horas: «2026-11-05 16:12:00» -> «5 nov 2026, 4:12 p. m.».
     * Las 00:xx son «12:xx a. m.» y las 12:xx son «12:xx p. m.».
     *
     * @param string|null $fechaHora «AAAA-MM-DD HH:MM:SS»; null o vacío se muestra vacío.
     * @return string La fecha y la hora listas para mostrar.
     */
    function formato_fecha_hora(?string $fechaHora): string
    {
        if ($fechaHora === null || $fechaHora === '') {
            return '';
        }
        $hora24 = (int) substr($fechaHora, 11, 2);
        $hora12 = $hora24 % 12 === 0 ? 12 : $hora24 % 12;

        return formato_fecha($fechaHora) . ', ' . $hora12 . ':' . substr($fechaHora, 14, 2) . ($hora24 < 12 ? ' a. m.' : ' p. m.');
    }
}
