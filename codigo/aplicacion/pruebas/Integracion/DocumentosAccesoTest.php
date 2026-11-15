<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Integracion;

use SIBEA\Nucleo\Aplicacion;
use SIBEA\Nucleo\Enrutador;
use SIBEA\Nucleo\Fabrica;
use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Respuesta;

/** Review Focus 3: un documento solo lo ve su dueño o un administrador (por el enrutador, como en el navegador). */
final class DocumentosAccesoTest extends BaseDePrueba
{
    /** @var list<string> Archivos que esta prueba dejó en almacenamiento/documentos. */
    private array $creados = [];

    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->creados as $ruta) {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
    }

    /** Crea la postulación de un aprendiz con un documento (y su archivo real); devuelve el id del documento. */
    private function crearDocumentoDe(int $idAprendiz): int
    {
        $idConvocatoria = $this->crearConvocatoriaActiva();
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($this->crearAdmin(), '127.0.0.1');
        $bd->pdo()->prepare('INSERT INTO postulaciones (id_usuario, id_convocatoria) VALUES (?, ?)')->execute([$idAprendiz, $idConvocatoria]);
        $idPostulacion = (int) $bd->pdo()->lastInsertId();

        $relativa = 'documentos/' . bin2hex(random_bytes(16)) . '.pdf';
        $absoluta = dirname(__DIR__, 2) . '/almacenamiento/' . $relativa;
        file_put_contents($absoluta, "%PDF-1.4\nsecreto de A\n%%EOF");
        $this->creados[] = $absoluta;

        $bd->pdo()->prepare(
            'INSERT INTO documentos_postulacion (id_postulacion, id_tipo_documento, nombre_archivo, ruta_archivo, extension, tamano_mb)
             VALUES (?, 1, ?, ?, ?, 0.01)'
        )->execute([$idPostulacion, 'cedula.pdf', $relativa, 'pdf']);

        return (int) $bd->pdo()->lastInsertId();
    }

    private function pedir(int $idUsuario, string $rol, int $idDocumento): Respuesta
    {
        $fabrica = new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
        $fabrica->sesion()->iniciarPara($idUsuario, $rol, false, 'Prueba');
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 2) . '/rutas.php')($enrutador, $fabrica);
        $aplicacion = new Aplicacion($enrutador, $fabrica->vista(), $this->configuracionDePrueba(), $fabrica);

        return $aplicacion->ejecutar(new Peticion('GET', "/documentos/$idDocumento", [], [], '127.0.0.1'));
    }

    public function test_el_dueno_recibe_su_documento(): void
    {
        $a = $this->crearAprendiz();
        $idDocumento = $this->crearDocumentoDe($a);

        $respuesta = $this->pedir($a, 'Aprendiz', $idDocumento);

        $this->assertSame(200, $respuesta->codigo());
        $this->assertSame('application/pdf', $respuesta->cabecera('Content-Type'));
        // Los PDF se entregan como descarga (decisión R19: el sandbox impide verlos en línea).
        $this->assertStringStartsWith('attachment;', (string) $respuesta->cabecera('Content-Disposition'));
        $this->assertSame('nosniff', $respuesta->cabecera('X-Content-Type-Options'));
    }

    public function test_el_aprendiz_b_pide_el_documento_del_aprendiz_a_y_recibe_403(): void
    {
        $idDocumento = $this->crearDocumentoDe($this->crearAprendiz());

        $respuesta = $this->pedir($this->crearAprendiz(), 'Aprendiz', $idDocumento);

        $this->assertSame(403, $respuesta->codigo());
        $this->assertStringNotContainsString('secreto de A', $respuesta->cuerpo());
        $this->assertNull($respuesta->cabecera('Content-Disposition'));
    }

    public function test_el_administrador_recibe_el_documento_con_200(): void
    {
        $idDocumento = $this->crearDocumentoDe($this->crearAprendiz());

        $respuesta = $this->pedir($this->crearAdmin(), 'Administrador', $idDocumento);

        $this->assertSame(200, $respuesta->codigo());
    }

    public function test_sin_sesion_manda_a_ingresar(): void
    {
        $fabrica = new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 2) . '/rutas.php')($enrutador, $fabrica);
        $aplicacion = new Aplicacion($enrutador, $fabrica->vista(), $this->configuracionDePrueba(), $fabrica);

        $respuesta = $aplicacion->ejecutar(new Peticion('GET', '/documentos/1', [], [], '127.0.0.1'));

        $this->assertSame('/ingresar', $respuesta->destino());
    }

    public function test_un_documento_que_no_existe_da_404(): void
    {
        $this->assertSame(404, $this->pedir($this->crearAdmin(), 'Administrador', 999999)->codigo());
    }
}
