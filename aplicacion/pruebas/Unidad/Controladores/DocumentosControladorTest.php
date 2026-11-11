<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Controladores;

use BenefitRack\Controladores\DocumentosControlador;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Sesion;
use BenefitRack\Nucleo\Vista;
use BenefitRack\Repositorios\RepositorioDocumentos;
use BenefitRack\Servicios\AlmacenDocumentos;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Descargar un documento: solo su dueño o un administrador (sin base de datos: el repositorio es un doble). */
final class DocumentosControladorTest extends TestCase
{
    private const DUENO = 7;

    private string $base;
    private string $relativa;
    private RepositorioDocumentos&MockObject $documentos;
    private Sesion $sesion;
    private DocumentosControlador $controlador;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->sesion = new Sesion();
        $this->base = sys_get_temp_dir() . '/br-desc-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/temporal', 0700, true);
        mkdir($this->base . '/documentos', 0700, true);

        $nombre = bin2hex(random_bytes(16));
        $this->relativa = "documentos/$nombre.png";
        file_put_contents($this->base . '/' . $this->relativa, 'bytes-de-la-imagen');

        $this->documentos = $this->createMock(RepositorioDocumentos::class);
        $this->documentos->method('buscarConDueno')->willReturnCallback(fn (int $id): ?array => $id === 15 ? [
            'id_documento' => 15, 'id_postulacion' => 3, 'nombre_archivo' => 'cedula.png',
            'ruta_archivo' => $this->relativa, 'extension' => 'png', 'id_usuario' => self::DUENO,
        ] : null);

        $this->controlador = new DocumentosControlador(
            $this->sesion,
            new Vista(dirname(__DIR__, 3) . '/vistas', $this->sesion),
            $this->documentos,
            new AlmacenDocumentos($this->base),
        );
    }

    protected function tearDown(): void
    {
        foreach (['/documentos/*', '/temporal/*', '/*'] as $patron) {
            foreach (glob($this->base . $patron) ?: [] as $ruta) {
                is_dir($ruta) ? rmdir($ruta) : unlink($ruta);
            }
        }
        rmdir($this->base);
    }

    private function pedir(string $id = '15'): Peticion
    {
        return new Peticion('GET', "/documentos/$id", [], [], '127.0.0.1', ['id' => $id]);
    }

    public function test_el_dueno_recibe_su_documento_en_linea_y_sin_cache(): void
    {
        $this->sesion->iniciarPara(self::DUENO, 'Aprendiz', false, 'Luisa');

        $respuesta = $this->controlador->descargar($this->pedir());

        $this->assertSame(200, $respuesta->codigo());
        $this->assertSame('image/png', $respuesta->cabecera('Content-Type'));
        $this->assertSame('inline; filename="cedula.png"', $respuesta->cabecera('Content-Disposition'));
        $this->assertSame('nosniff', $respuesta->cabecera('X-Content-Type-Options'));
        $this->assertSame('no-store', $respuesta->cabecera('Cache-Control'));
    }

    public function test_otro_aprendiz_recibe_403_y_no_el_archivo(): void
    {
        $this->sesion->iniciarPara(8, 'Aprendiz', false, 'Otro');

        $respuesta = $this->controlador->descargar($this->pedir());

        $this->assertSame(403, $respuesta->codigo());
        $this->assertNull($respuesta->cabecera('Content-Disposition'));
        $this->assertStringNotContainsString('bytes-de-la-imagen', $respuesta->cuerpo());
    }

    public function test_un_administrador_recibe_cualquier_documento(): void
    {
        $this->sesion->iniciarPara(1, 'Administrador', false, 'Admin');

        $this->assertSame(200, $this->controlador->descargar($this->pedir())->codigo());
    }

    public function test_sin_sesion_recibe_403(): void
    {
        $this->assertSame(403, $this->controlador->descargar($this->pedir())->codigo());
    }

    public function test_un_documento_que_no_existe_da_404(): void
    {
        $this->sesion->iniciarPara(1, 'Administrador', false, 'Admin');

        $this->assertSame(404, $this->controlador->descargar($this->pedir('999'))->codigo());
    }

    public function test_si_el_archivo_ya_no_esta_en_disco_da_404(): void
    {
        $this->sesion->iniciarPara(self::DUENO, 'Aprendiz', false, 'Luisa');
        unlink($this->base . '/' . $this->relativa);

        $this->assertSame(404, $this->controlador->descargar($this->pedir())->codigo());
    }

    public function test_una_ruta_guardada_fuera_de_la_carpeta_de_documentos_da_404(): void
    {
        $this->sesion->iniciarPara(1, 'Administrador', false, 'Admin');
        $documentos = $this->createMock(RepositorioDocumentos::class);
        $documentos->method('buscarConDueno')->willReturn([
            'id_documento' => 15, 'id_postulacion' => 3, 'nombre_archivo' => 'x.png',
            'ruta_archivo' => '../../etc/passwd', 'extension' => 'png', 'id_usuario' => self::DUENO,
        ]);
        $controlador = new DocumentosControlador($this->sesion, new Vista(dirname(__DIR__, 3) . '/vistas', $this->sesion), $documentos, new AlmacenDocumentos($this->base));

        $this->assertSame(404, $controlador->descargar($this->pedir())->codigo());
    }
}
