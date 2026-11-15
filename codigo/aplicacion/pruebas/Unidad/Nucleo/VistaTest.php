<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Nucleo;

use PHPUnit\Framework\TestCase;
use SIBEA\Nucleo\Vista;

final class VistaTest extends TestCase
{
    private string $carpeta;

    protected function setUp(): void
    {
        // Carpeta de vistas de mentira, solo para esta prueba.
        $this->carpeta = sys_get_temp_dir() . '/br_vistas_' . bin2hex(random_bytes(4));
        mkdir($this->carpeta . '/plantillas', 0777, true);
        file_put_contents($this->carpeta . '/plantillas/publica.php', '<main><?= $contenido ?></main>');
        file_put_contents($this->carpeta . '/saludo.php', '<p>Hola <?= e($nombre) ?></p>');
    }

    protected function tearDown(): void
    {
        unlink($this->carpeta . '/plantillas/publica.php');
        unlink($this->carpeta . '/saludo.php');
        rmdir($this->carpeta . '/plantillas');
        rmdir($this->carpeta);
    }

    public function test_mostrar_inserta_datos_escapados_en_el_marco(): void
    {
        $vista = new Vista($this->carpeta);

        $html = $vista->mostrar('saludo', ['nombre' => '<b>Ana</b>']);

        $this->assertSame('<main><p>Hola &lt;b&gt;Ana&lt;/b&gt;</p></main>', $html);
    }
}
