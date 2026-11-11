<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Nucleo;

use BenefitRack\Nucleo\Respuesta;
use PHPUnit\Framework\TestCase;

/** Respuesta::archivo: descarga (attachment) por defecto y «en línea» solo cuando se pide. */
final class RespuestaArchivoTest extends TestCase
{
    private string $ruta;

    protected function setUp(): void
    {
        $this->ruta = (string) tempnam(sys_get_temp_dir(), 'bra');
        file_put_contents($this->ruta, 'abc');
    }

    protected function tearDown(): void
    {
        unlink($this->ruta);
    }

    public function test_por_defecto_es_una_descarga(): void
    {
        $respuesta = Respuesta::archivo($this->ruta, 'a.pdf', 'application/pdf');

        $this->assertSame('attachment; filename="a.pdf"', $respuesta->cabecera('Content-Disposition'));
    }

    public function test_en_linea_se_muestra_en_el_navegador_y_el_nombre_sigue_saneado(): void
    {
        $respuesta = Respuesta::archivo($this->ruta, "a\"\r\nX-Malo: 1.pdf", 'application/pdf', true);

        $this->assertSame('inline; filename="a___X-Malo_ 1.pdf"', $respuesta->cabecera('Content-Disposition'));
        $this->assertSame('nosniff', $respuesta->cabecera('X-Content-Type-Options'));
        $this->assertSame('3', $respuesta->cabecera('Content-Length'));
    }
}
