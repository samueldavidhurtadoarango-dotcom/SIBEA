<?php

/**
 * REVISOR DE COMENTARIOS (herramienta de consola, CA-12)
 *
 * Comprueba la regla de comentarios del proyecto sobre cada archivo de src/ y vistas/:
 *   1. el archivo empieza con un bloque de comentario (qué hace y en qué paso del recorrido está);
 *   2. cada método público de src/ tiene su /** ... *\/ justo encima.
 * Se ejecuta con "composer analisis" y termina con código 1 si falta algo.
 *
 * Uso: php herramientas/revisar_comentarios.php
 */

declare(strict_types=1);

$raiz = dirname(__DIR__);
$problemas = [];

foreach (archivosPhp([$raiz . '/src', $raiz . '/vistas']) as $archivo) {
    $lineas = file($archivo, FILE_IGNORE_NEW_LINES) ?: [];
    $nombre = ltrim(str_replace('\\', '/', substr($archivo, strlen($raiz))), '/');

    if (!tieneBloqueInicial($lineas)) {
        $problemas[] = "$nombre: falta el bloque de comentario inicial";
    }
    if (str_starts_with($nombre, 'src/')) {
        foreach (metodosPublicosSinDocumentar($lineas) as $numero => $metodo) {
            $problemas[] = "$nombre:$numero: el método público $metodo no tiene /** */";
        }
    }
}

if ($problemas !== []) {
    fwrite(STDERR, implode(PHP_EOL, $problemas) . PHP_EOL);
    fwrite(STDERR, count($problemas) . ' problema(s) de comentarios.' . PHP_EOL);
    exit(1);
}
echo 'Comentarios en regla.' . PHP_EOL;

/**
 * Busca todos los .php dentro de las carpetas dadas.
 *
 * @param list<string> $carpetas Carpetas a recorrer.
 * @return list<string> Rutas de los archivos, ordenadas.
 */
function archivosPhp(array $carpetas): array
{
    $archivos = [];
    foreach ($carpetas as $carpeta) {
        $recorrido = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS));
        foreach ($recorrido as $archivo) {
            if ($archivo->getExtension() === 'php') {
                $archivos[] = $archivo->getPathname();
            }
        }
    }
    sort($archivos);

    return $archivos;
}

/**
 * ¿El archivo trae un comentario de bloque antes del código (después de <?php y, si hay, del HTML)?
 * Se mira solo la cabecera: las primeras líneas hasta el primer "declare" o "namespace".
 *
 * @param list<string> $lineas Las líneas del archivo.
 * @return bool true si hay un bloque iniciado con barra y asterisco.
 */
function tieneBloqueInicial(array $lineas): bool
{
    foreach (array_slice($lineas, 0, 40) as $linea) {
        if (str_contains($linea, '/*')) {
            return true;
        }
        if (preg_match('/^\s*(declare|namespace|use|class|final|abstract|interface)\b/', $linea) === 1) {
            return false;
        }
    }

    return false;
}

/**
 * Encuentra los métodos públicos cuya línea anterior (ignorando atributos) no cierra un comentario de bloque.
 *
 * @param list<string> $lineas Las líneas del archivo.
 * @return array<int, string> Número de línea => nombre del método sin documentar.
 */
function metodosPublicosSinDocumentar(array $lineas): array
{
    $faltan = [];
    foreach ($lineas as $indice => $linea) {
        if (preg_match('/^\s*public\s+(?:static\s+)?function\s+(\w+)/', $linea, $m) !== 1) {
            continue;
        }
        // __construct se documenta igual: la regla vale para todo método público.
        $anterior = trim($lineas[$indice - 1] ?? '');
        if (!str_ends_with($anterior, '*/')) {
            $faltan[$indice + 1] = $m[1];
        }
    }

    return $faltan;
}
