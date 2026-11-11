<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Servicios;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** La carpeta temporal de la sesión: nombres aleatorios, dentro de la carpeta propia y fuera de public/. */
final class AlmacenTemporalTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/br-almacen-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->base . '/*/*') ?: [] as $archivo) {
            unlink($archivo);
        }
        foreach (glob($this->base . '/*') ?: [] as $carpeta) {
            rmdir($carpeta);
        }
        if (is_dir($this->base)) {
            rmdir($this->base);
        }
    }

    /** @return array{ruta: string, extension: string, nombre_original: string, bytes: int} */
    private function validado(): array
    {
        $ruta = (string) tempnam(sys_get_temp_dir(), 'brt');
        file_put_contents($ruta, 'contenido');

        return ['ruta' => $ruta, 'extension' => 'pdf', 'nombre_original' => 'cedula.pdf', 'bytes' => 9];
    }

    public function test_guarda_con_nombre_aleatorio_en_la_carpeta_de_la_sesion(): void
    {
        $guardado = (new AlmacenTemporalDePrueba($this->base))->guardar($this->validado(), 'abc123');

        $this->assertMatchesRegularExpression('#/abc123/[0-9a-f]{32}\.pdf$#', str_replace('\\', '/', $guardado['ruta']));
        $this->assertFileExists($guardado['ruta']);
        $this->assertSame('cedula.pdf', $guardado['nombre_original']);
        $this->assertSame(9, $guardado['bytes']);
        $this->assertSame('pdf', $guardado['extension']);
    }

    public function test_dos_archivos_nunca_comparten_nombre(): void
    {
        $almacen = new AlmacenTemporalDePrueba($this->base);

        $uno = $almacen->guardar($this->validado(), 'abc123');
        $dos = $almacen->guardar($this->validado(), 'abc123');

        $this->assertNotSame($uno['ruta'], $dos['ruta']);
    }

    public function test_un_id_de_sesion_con_rutas_raras_se_rechaza(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AlmacenTemporalDePrueba($this->base))->guardar($this->validado(), '../../publico');
    }

    public function test_descartar_borra_solo_lo_que_esta_en_la_carpeta_propia(): void
    {
        $almacen = new AlmacenTemporalDePrueba($this->base);
        $guardado = $almacen->guardar($this->validado(), 'abc123');
        $ajeno = $this->validado();

        $almacen->descartar([$guardado, $ajeno]);

        $this->assertFileDoesNotExist($guardado['ruta']);
        $this->assertFileExists($ajeno['ruta']); // fuera de la carpeta propia: no se toca
        unlink($ajeno['ruta']);
    }
}
