<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Nucleo;

use PHPUnit\Framework\TestCase;

/**
 * Cierre del MVP (revisión visual): dos defectos que se vieron al capturar las pantallas.
 * Una prueba de estilos no puede «mirar» la página, así que comprueba que la regla exista
 * en la hoja de estilos; la comprobación visual real fue la captura con el navegador.
 */
final class HojaDeEstilosTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3) . '/public/css/benefitrack.css');
    }

    /** Devuelve el cuerpo de la regla de un selector exacto, o '' si no existe. */
    private function regla(string $selector): string
    {
        return preg_match('/(?:^|\})\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $this->css(), $m) === 1 ? $m[1] : '';
    }

    public function test_la_cabecera_pone_el_usuario_y_salir_en_la_misma_fila(): void
    {
        $regla = $this->regla('.br-cabecera__derecha');

        $this->assertStringContainsString('display: flex', $regla);
        $this->assertStringContainsString('align-items: center', $regla);
    }

    public function test_los_filtros_se_alinean_por_abajo_para_que_la_lupa_quede_dentro_del_buscador(): void
    {
        $this->assertStringContainsString('align-items: end', $this->regla('.br-filtros'));
    }

    /**
     * Defecto visto en «Mi proceso»: la línea de pasos (br-pasos) no tenía estilos y sus íconos salían enormes.
     * Regla general: toda clase br-* que usan las vistas tiene que existir en la hoja de estilos.
     */
    public function test_toda_clase_br_que_usan_las_vistas_existe_en_la_hoja_de_estilos(): void
    {
        $usadas = [];
        $vistas = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/vistas', \FilesystemIterator::SKIP_DOTS));
        foreach ($vistas as $archivo) {
            preg_match_all('/\bbr-[a-z0-9_]+(?:-[a-z0-9_]+)*/', (string) file_get_contents($archivo->getPathname()), $coincidencias);
            $usadas = array_merge($usadas, $coincidencias[0]);
        }
        preg_match_all('/\.(br-[a-z0-9_-]+)/', $this->css(), $definidas);

        $faltan = array_diff(array_unique($usadas), $definidas[1]);

        $this->assertSame([], array_values($faltan), 'Clases usadas en las vistas y sin estilos');
    }
}
