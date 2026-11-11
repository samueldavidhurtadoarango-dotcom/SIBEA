<?php

/**
 * REVISOR DE COMENTARIOS (herramienta de consola, CA-12)
 *
 * Comprueba la regla de comentarios del proyecto sobre cada archivo de src/ y vistas/:
 *   1. el archivo empieza con un bloque de documentación (abierto con barra y dos asteriscos)
 *      que tenga texto de verdad: qué hace y en qué paso del recorrido está;
 *   2. cada método público de src/ tiene encima su bloque de documentación con texto
 *      (se aceptan líneas de atributos #[...] entre el bloque y el método).
 * Un bloque vacío o un comentario común (barra y un solo asterisco) no cuentan.
 * Se ejecuta con "composer analisis" y termina con código 1 si falta algo.
 *
 * Uso: php herramientas/revisar_comentarios.php [carpeta-raiz]   (por defecto, la carpeta aplicacion/)
 */

declare(strict_types=1);

$raiz = $argv[1] ?? dirname(__DIR__);
$problemas = [];

foreach (archivosPhp([$raiz . '/src', $raiz . '/vistas']) as $archivo) {
    $lineas = file($archivo, FILE_IGNORE_NEW_LINES) ?: [];
    $nombre = ltrim(str_replace('\\', '/', substr($archivo, strlen($raiz))), '/');

    if (!tieneBloqueInicial($lineas)) {
        $problemas[] = "$nombre: falta el bloque de comentario inicial (con texto)";
    }
    if (str_starts_with($nombre, 'src/')) {
        foreach (metodosPublicosSinDocumentar($lineas) as $numero => $metodo) {
            $problemas[] = "$nombre:$numero: el método público $metodo no tiene /** */ con texto";
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
 * @param list<string> $carpetas Carpetas a recorrer (las que no existan se saltan).
 * @return list<string> Rutas de los archivos, ordenadas.
 */
function archivosPhp(array $carpetas): array
{
    $archivos = [];
    foreach ($carpetas as $carpeta) {
        if (!is_dir($carpeta)) {
            continue;
        }
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
 * ¿El texto de un bloque de documentación tiene palabras de verdad (al menos 3 letras seguidas)?
 *
 * @param list<string> $bloque Las líneas del bloque, de la apertura al cierre.
 * @return bool false si está vacío o solo trae asteriscos y símbolos.
 */
function tieneTexto(array $bloque): bool
{
    $contenido = implode(' ', $bloque);
    $contenido = str_replace(['/**', '*/'], ' ', $contenido);

    return preg_match('/\p{L}{3,}/u', $contenido) === 1;
}

/**
 * Sube desde la línea que cierra un bloque (termina en la marca de cierre) hasta su apertura.
 *
 * @param list<string> $lineas Las líneas del archivo.
 * @param int          $cierre Índice de la línea donde termina el bloque.
 * @return list<string>|null El bloque completo si abre con la marca de documentación; null si es un comentario común.
 */
function bloqueDeDocumentacion(array $lineas, int $cierre): ?array
{
    for ($i = $cierre; $i >= 0 && $cierre - $i < 200; $i--) {
        $linea = ltrim($lineas[$i]);
        if (str_contains($linea, '/*')) {
            return str_contains($linea, '/**') ? array_slice($lineas, $i, $cierre - $i + 1) : null;
        }
    }

    return null;
}

/**
 * ¿El archivo trae, antes del código, un bloque de documentación con texto?
 * Se mira solo la cabecera: las primeras líneas hasta el primer "declare", "namespace" o "use".
 *
 * @param list<string> $lineas Las líneas del archivo.
 * @return bool true si hay un bloque con texto.
 */
function tieneBloqueInicial(array $lineas): bool
{
    foreach (array_slice($lineas, 0, 40, true) as $indice => $linea) {
        if (str_contains($linea, '*/')) {
            $bloque = bloqueDeDocumentacion($lineas, $indice);

            return $bloque !== null && tieneTexto($bloque);
        }
        if (preg_match('/^\s*(declare|namespace|use|class|final|abstract|interface)\b/', $linea) === 1) {
            return false;
        }
    }

    return false;
}

/**
 * Encuentra los métodos públicos sin bloque de documentación con texto encima
 * (se saltan las líneas de atributos #[...] entre el bloque y el método).
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
        $anterior = $indice - 1;
        while ($anterior >= 0 && str_starts_with(trim($lineas[$anterior]), '#[')) {
            $anterior--;
        }
        $bloque = $anterior >= 0 && str_ends_with(trim($lineas[$anterior]), '*/') ? bloqueDeDocumentacion($lineas, $anterior) : null;
        if ($bloque === null || !tieneTexto($bloque)) {
            $faltan[$indice + 1] = $m[1];
        }
    }

    return $faltan;
}
