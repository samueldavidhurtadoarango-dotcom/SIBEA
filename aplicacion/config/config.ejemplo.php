<?php

/**
 * CONFIGURACIÓN DE EJEMPLO
 *
 * Copia este archivo como config/config.local.php y pon tus datos reales.
 * config.local.php NO se sube a git (tiene la clave de la base de datos).
 */

declare(strict_types=1);

return [
    'bd' => [
        'host' => '127.0.0.1',
        'puerto' => 3306,
        'nombre' => 'benefitrack_v2',
        'usuario' => 'benefitrack_app', // nunca root
        'clave' => 'cambia-esta-clave',
    ],
    'app' => [
        'url_base' => 'http://localhost:8080',
        // true solo en tu computador: muestra el detalle de los errores.
        'depuracion' => false,
        // Zona horaria de PHP (XAMPP trae Europe/Berlin; sin esta clave se usa America/Bogota).
        'zona_horaria' => 'America/Bogota',
        // La sesión se cierra tras estos minutos sin actividad...
        'sesion_inactividad_minutos' => 30,
        // ...y, aunque no pare de usarla, tras estas horas desde que ingresó.
        'sesion_duracion_maxima_horas' => 8,
    ],
];
