<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Servicios;

use BenefitRack\Servicios\AlmacenDocumentos;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** La carpeta definitiva de documentos: solo recibe archivos de la carpeta temporal y nunca sale de sí misma. */
final class AlmacenDocumentosTest extends TestCase
{
    private string $base;
    private AlmacenDocumentos $almacen;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/br-docs-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/temporal/sesion1', 0700, true);
        mkdir($this->base . '/documentos', 0700, true);
        $this->almacen = new AlmacenDocumentos($this->base);
    }

    protected function tearDown(): void
    {
        foreach (['/temporal/sesion1/*', '/temporal/*', '/documentos/*', '/*'] as $patron) {
            foreach (glob($this->base . $patron) ?: [] as $ruta) {
                is_dir($ruta) ? rmdir($ruta) : unlink($ruta);
            }
        }
        rmdir($this->base);
    }

    private function temporal(string $contenido = 'contenido'): string
    {
        $ruta = $this->base . '/temporal/sesion1/' . bin2hex(random_bytes(16)) . '.pdf';
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    public function test_guardar_mueve_el_archivo_con_nombre_aleatorio_y_devuelve_la_ruta_relativa(): void
    {
        $origen = $this->temporal('hola');

        $relativa = $this->almacen->guardar($origen, 'pdf');

        $this->assertMatchesRegularExpression('#^documentos/[0-9a-f]{32}\.pdf$#', $relativa);
        $this->assertFileDoesNotExist($origen);
        $this->assertSame('hola', file_get_contents($this->almacen->rutaAbsoluta($relativa)));
    }

    public function test_dos_documentos_nunca_comparten_nombre(): void
    {
        $this->assertNotSame($this->almacen->guardar($this->temporal(), 'pdf'), $this->almacen->guardar($this->temporal(), 'pdf'));
    }

    public function test_rechaza_un_archivo_que_no_esta_en_la_carpeta_temporal(): void
    {
        $ajeno = (string) tempnam(sys_get_temp_dir(), 'brx');
        file_put_contents($ajeno, 'secreto');

        try {
            $this->almacen->guardar($ajeno, 'pdf');
            $this->fail('Debía rechazar una ruta fuera de temporal.');
        } catch (RuntimeException) {
            $this->assertFileExists($ajeno); // no se movió
        } finally {
            unlink($ajeno);
        }
    }

    public function test_rechaza_una_ruta_que_sale_de_temporal_con_dos_puntos(): void
    {
        $ajeno = $this->base . '/documentos/ajeno.pdf';
        file_put_contents($ajeno, 'x');

        $this->expectException(RuntimeException::class);
        $this->almacen->guardar($this->base . '/temporal/sesion1/../../documentos/ajeno.pdf', 'pdf');
    }

    public function test_rechaza_una_carpeta_hermana_con_el_mismo_comienzo(): void
    {
        mkdir($this->base . '/temporal-otra', 0700);
        $ruta = $this->base . '/temporal-otra/x.pdf';
        file_put_contents($ruta, 'x');

        try {
            $this->almacen->guardar($ruta, 'pdf');
            $this->fail('Debía rechazar la carpeta hermana.');
        } catch (RuntimeException) {
            $this->assertFileExists($ruta);
        } finally {
            unlink($ruta);
            rmdir($this->base . '/temporal-otra');
        }
    }

    public function test_rechaza_una_ruta_que_no_existe(): void
    {
        $this->expectException(RuntimeException::class);
        $this->almacen->guardar($this->base . '/temporal/sesion1/no-existe.pdf', 'pdf');
    }

    public function test_rechaza_una_extension_no_permitida(): void
    {
        $origen = $this->temporal();

        $this->expectException(InvalidArgumentException::class);
        try {
            $this->almacen->guardar($origen, 'php');
        } finally {
            $this->assertFileExists($origen);
        }
    }

    public function test_ruta_absoluta_solo_acepta_rutas_que_guardo_el_almacen(): void
    {
        $relativa = $this->almacen->guardar($this->temporal(), 'pdf');
        $this->assertFileExists($this->almacen->rutaAbsoluta($relativa));

        foreach (['../x.pdf', 'documentos/../../x.pdf', 'documentos/abc.pdf', 'temporal/sesion1/a.pdf', '/etc/passwd', ''] as $mala) {
            try {
                $this->almacen->rutaAbsoluta($mala);
                $this->fail("Debía rechazar «$mala».");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_borrar_elimina_el_archivo_y_no_falla_si_ya_no_existe_o_la_ruta_es_rara(): void
    {
        $relativa = $this->almacen->guardar($this->temporal(), 'pdf');
        $absoluta = $this->almacen->rutaAbsoluta($relativa);

        $this->almacen->borrar($relativa);
        $this->assertFileDoesNotExist($absoluta);

        $this->almacen->borrar($relativa);        // ya no existe
        $this->almacen->borrar('../../etc/passwd'); // ruta no válida: se ignora
        $this->addToAssertionCount(1);
    }
}
