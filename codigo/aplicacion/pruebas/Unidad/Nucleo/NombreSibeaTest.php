<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Nucleo;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SIBEA\Nucleo\Arranque;
use SIBEA\Nucleo\Sesion;
use SIBEA\Nucleo\Vista;

/**
 * Spec 005: el sistema se llama SIBEA en todo lo que ve una persona.
 * «BenefitRack» solo puede quedar en nombres internos (comentarios, espacio de nombres PHP,
 * benefitrack.css/js), nunca en un texto que se dibuje en pantalla.
 */
final class NombreSibeaTest extends TestCase
{
    /** CA-4: ningún archivo de vistas/ tiene «BenefitRack» en un texto visible. */
    public function test_ninguna_vista_muestra_el_nombre_viejo(): void
    {
        $encontrados = [];
        foreach (self::archivosDeVistas() as $archivo) {
            if (self::muestraNombreViejo((string) file_get_contents($archivo))) {
                $encontrados[] = $archivo;
            }
        }

        $this->assertSame([], $encontrados, 'Estas vistas aún muestran «BenefitRack».');
    }

    /** El detector no se deja engañar por mayúsculas, y no cuenta comentarios HTML ni las rutas internas. */
    public function test_el_detector_ve_el_nombre_viejo_aunque_cambien_las_mayusculas(): void
    {
        $this->assertTrue(self::muestraNombreViejo('<h1>Bienvenido a BENEFITRACK</h1>'));
        $this->assertTrue(self::muestraNombreViejo('<img alt="Logo de Benefitrack">'));
        $this->assertFalse(self::muestraNombreViejo('<!-- lo activa benefitrack.js --><link href="/css/benefitrack.css">'));
    }

    /** CA-1: la pestaña del navegador dice «<título> · SIBEA» en los tres marcos. */
    public function test_los_tres_marcos_dicen_sibea_en_el_titulo(): void
    {
        $_SESSION = [];
        $vista = new Vista(dirname(__DIR__, 3) . '/vistas', new Sesion());

        foreach (['publica', 'admin', 'aprendiz'] as $marco) {
            $html = $vista->mostrar('errores/404', ['titulo' => 'Prueba'], $marco);

            $this->assertStringContainsString('<title>Prueba · SIBEA</title>', $html, "Marco: $marco");
            $this->assertStringContainsString('SIBEA</span>', $html, "Marca visible del marco: $marco");
        }
    }

    /** CA-2: la página de error del arranque dice «Error · SIBEA» y no el nombre viejo. */
    public function test_la_pagina_de_error_del_arranque_dice_sibea(): void
    {
        $registroAnterior = ini_set('error_log', (string) tempnam(sys_get_temp_dir(), 'brlog'));
        try {
            $cuerpo = Arranque::atender(sys_get_temp_dir() . '/br-sin-config-' . bin2hex(random_bytes(4)))->cuerpo();
        } finally {
            ini_set('error_log', $registroAnterior === false ? '' : $registroAnterior);
        }

        $this->assertStringContainsString('<title>Error · SIBEA</title>', $cuerpo);
        $this->assertStringNotContainsString('BenefitRack', $cuerpo);
    }

    /** Spec 006 CA-A4: la pantalla de acceso dice qué significa la sigla, debajo de la marca (PC y celular). */
    public function test_la_pantalla_de_acceso_muestra_el_significado_de_sibea(): void
    {
        $_SESSION = [];
        $vista = new Vista(dirname(__DIR__, 3) . '/vistas', new Sesion());

        $html = $vista->mostrar('acceso/ingresar', ['estado' => 'normal', 'tokenCsrf' => 'x'], 'publica');

        $significado = 'Sistema de Información de Beneficios y Apoyos';
        $this->assertSame(2, substr_count($html, $significado), 'Una vez en el panel de computador y otra en el encabezado del celular.');
        $this->assertMatchesRegularExpression('#br-acceso__nombre">SIBEA</span>\s*<small[^>]*>' . $significado . '</small>#u', $html);
        $this->assertMatchesRegularExpression('#br-acceso__movil-nombre">SIBEA</span>\s*<small[^>]*text-secondary[^>]*>' . $significado . '</small>#u', $html);
    }

    /** Spec 006 CA-A4: los tres textos de arranque empiezan con «SIBEA — Sistema de Información de Beneficios y Apoyos». */
    public function test_los_documentos_de_arranque_abren_con_el_significado(): void
    {
        $raiz = dirname(__DIR__, 5);
        $frase = 'SIBEA — Sistema de Información de Beneficios y Apoyos';

        $this->assertStringStartsWith('# ' . $frase, (string) file_get_contents($raiz . '/LEEME.md'));
        $this->assertStringStartsWith('# ' . $frase, (string) file_get_contents($raiz . '/codigo/aplicacion/LEEME.md'));
        $this->assertMatchesRegularExpression('#/\*\*\R \* ' . $frase . '#u', (string) file_get_contents($raiz . '/codigo/aplicacion/public/index.php'));
    }

    /**
     * Spec 006 CA-A5 y Parte B: en codigo/aplicacion (sin vendor/) y en codigo/base-de-datos no queda la
     * palabra «benefitrack», sin importar mayúsculas, ni en el contenido ni en los nombres de archivo
     * (la base ahora es sibea, su usuario sibea_app y el script sibea.sql).
     */
    public function test_en_el_codigo_no_queda_la_palabra_benefitrack(): void
    {
        $encontrados = [];
        foreach (self::archivosDelCodigo() as $archivo) {
            $ruta = str_replace('\\', '/', $archivo);
            if (stripos(basename($ruta), 'benefitrack') !== false) {
                $encontrados[] = $ruta . ' (nombre del archivo)';
                continue;
            }
            if (stripos(self::sinExcepciones((string) file_get_contents($archivo)), 'benefitrack') !== false) {
                $encontrados[] = $ruta;
            }
        }

        $this->assertSame([], $encontrados, 'Estos archivos aún dicen «benefitrack».');
    }

    /**
     * Única excepción explícita: TU_USUARIO/BenefitRack, la carpeta del proyecto en el LEEME
     * (renombrarla está fuera de alcance de la spec 006).
     */
    private static function sinExcepciones(string $texto): string
    {
        return str_ireplace('TU_USUARIO/BenefitRack', '', $texto);
    }

    /**
     * Archivos de texto de codigo/aplicacion y codigo/base-de-datos, sin vendor/ ni cachés.
     * Se omiten esta misma prueba (nombra la palabra a propósito) y config/config.local.php
     * (la configuración propia de cada computador: está fuera de git y no es código del proyecto).
     *
     * @return list<string>
     */
    private static function archivosDelCodigo(): array
    {
        // codigo/aplicacion y codigo/base-de-datos (la base, sus pruebas SQL y su README).
        $raices = [dirname(__DIR__, 3), dirname(__DIR__, 4) . '/base-de-datos'];
        $extensiones = ['php', 'md', 'json', 'neon', 'xml', 'css', 'js', 'dist', 'sh', 'txt', 'html', 'yml', 'lock', 'sql'];
        $omitidos = [basename(__FILE__), 'config.local.php'];
        $archivos = [];
        foreach ($raices as $raiz) {
            $recorrido = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($recorrido as $archivo) {
                $ruta = str_replace('\\', '/', $archivo->getPathname());
                if (str_contains($ruta, '/vendor/') || str_contains($ruta, '/.phpunit.cache/') || in_array($archivo->getFilename(), $omitidos, true)) {
                    continue;
                }
                if ($archivo->isFile() && in_array(strtolower($archivo->getExtension()), $extensiones, true)) {
                    $archivos[] = $archivo->getPathname();
                }
            }
        }

        return $archivos;
    }

    /**
     * Todos los archivos .php dentro de vistas/.
     *
     * @return list<string>
     */
    private static function archivosDeVistas(): array
    {
        $archivos = [];
        $recorrido = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/vistas'));
        foreach ($recorrido as $archivo) {
            if ($archivo->isFile() && $archivo->getExtension() === 'php') {
                $archivos[] = $archivo->getPathname();
            }
        }

        return $archivos;
    }

    /**
     * ¿El código de una vista muestra el nombre viejo? Busca «benefitrack» sin importar
     * mayúsculas (BenefitRack, BENEFITRACK, Benefitrack...) solo en lo que llega a la pantalla.
     */
    private static function muestraNombreViejo(string $codigo): bool
    {
        return stripos(self::textoVisible($codigo), 'benefitrack') !== false;
    }

    /**
     * Se queda solo con lo que puede llegar a la pantalla: el HTML suelto y los textos entre comillas.
     * Deja fuera comentarios PHP y HTML (<!-- -->), nombres de código (como `use SIBEA\...`)
     * y las rutas internas /css/benefitrack.css y /js/benefitrack.js.
     */
    private static function textoVisible(string $codigo): string
    {
        $visible = '';
        foreach (token_get_all($codigo) as $pieza) {
            if (is_array($pieza) && in_array($pieza[0], [T_INLINE_HTML, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                $visible .= $pieza[1] . "\n";
            }
        }

        $sinComentarios = (string) preg_replace('/<!--.*?-->/s', '', $visible);

        return str_ireplace(['/css/benefitrack.css', '/js/benefitrack.js'], '', $sinComentarios);
    }
}
