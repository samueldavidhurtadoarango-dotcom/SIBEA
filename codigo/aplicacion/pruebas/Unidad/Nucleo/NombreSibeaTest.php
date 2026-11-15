<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Nucleo;

use BenefitRack\Nucleo\Arranque;
use BenefitRack\Nucleo\Sesion;
use BenefitRack\Nucleo\Vista;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

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
     * Deja fuera comentarios PHP y HTML (<!-- -->), nombres de código (como `use BenefitRack\...`)
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
