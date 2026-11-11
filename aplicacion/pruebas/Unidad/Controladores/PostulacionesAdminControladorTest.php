<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Controladores;

use BenefitRack\Controladores\PostulacionesAdminControlador;
use BenefitRack\Nucleo\ErrorDeNegocio;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Sesion;
use BenefitRack\Nucleo\Vista;
use BenefitRack\Repositorios\RepositorioCatalogos;
use BenefitRack\Repositorios\RepositorioPostulaciones;
use BenefitRack\Servicios\FiltroPostulaciones;
use BenefitRack\Servicios\ServicioRevision;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** La lista de postulaciones del administrador y su Excel (sin base de datos: los repositorios son dobles). */
final class PostulacionesAdminControladorTest extends TestCase
{
    private Sesion $sesion;
    private RepositorioPostulaciones&MockObject $postulaciones;
    private ServicioRevision&MockObject $revision;
    private PostulacionesAdminControlador $controlador;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->sesion = new Sesion();
        $this->sesion->iniciarPara(1, 'Administrador', false, 'Admin Prueba');

        $this->postulaciones = $this->createMock(RepositorioPostulaciones::class);
        $this->revision = $this->createMock(ServicioRevision::class);
        $catalogos = $this->createMock(RepositorioCatalogos::class);
        $catalogos->method('beneficios')->willReturn([['id' => 1, 'nombre' => 'Alimentación']]);
        $catalogos->method('programas')->willReturn([['id' => 4, 'nombre' => 'Contabilidad']]);

        $this->controlador = new PostulacionesAdminControlador(
            $this->sesion,
            new Vista(dirname(__DIR__, 3) . '/vistas', $this->sesion),
            $this->postulaciones,
            $catalogos,
            $this->revision,
        );
    }

    /** @param array<string, mixed> $consulta */
    private function pedir(string $ruta, array $consulta = []): Peticion
    {
        return new Peticion('GET', $ruta, [], [], '127.0.0.1', [], $consulta);
    }

    /** @return array<string, mixed> */
    private function fila(string $nombre = 'Ana Peña'): array
    {
        return [
            'id_postulacion' => 15, 'documento' => '1020345678', 'nombre' => $nombre, 'correo' => 'ana@soy.sena.edu.co',
            'programa' => 'Contabilidad', 'ficha' => '2761190', 'convocatoria' => 'Apoyo 2026-2', 'beneficio' => 'Alimentación',
            'estado' => 'Pendiente', 'fecha' => '2026-11-15 09:30:00', 'docs_total' => 3, 'docs_pendientes' => 3,
            'docs_no_validos' => 0, 'documento_pedido' => 0,
        ];
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @return array{filas: list<array<string, mixed>>, total: int, pagina: int, paginas: int, porPagina: int}
     */
    private function resultado(array $filas, int $total = 0): array
    {
        return ['filas' => $filas, 'total' => $total === 0 ? count($filas) : $total, 'pagina' => 1, 'paginas' => 1, 'porPagina' => 20];
    }

    public function test_la_lista_muestra_las_filas_y_el_boton_de_excel(): void
    {
        $this->postulaciones->method('buscar')->willReturn($this->resultado([$this->fila()]));

        $respuesta = $this->controlador->lista($this->pedir('/admin/postulaciones'));

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('Ana Peña', $respuesta->cuerpo());
        $this->assertStringContainsString('1020345678', $respuesta->cuerpo());
        $this->assertStringContainsString('/admin/postulaciones/15', $respuesta->cuerpo());
        $this->assertStringContainsString('Descargar en Excel', $respuesta->cuerpo());
        $this->assertStringContainsString('3 por revisar', $respuesta->cuerpo());
        $this->assertStringContainsString('15 nov 2026', $respuesta->cuerpo());
    }

    public function test_la_lista_escapa_el_texto_de_las_filas(): void
    {
        $this->postulaciones->method('buscar')->willReturn($this->resultado([$this->fila('<script>alert(1)</script>')]));

        $cuerpo = $this->controlador->lista($this->pedir('/admin/postulaciones'))->cuerpo();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $cuerpo);
        $this->assertStringContainsString('&lt;script&gt;', $cuerpo);
    }

    public function test_sin_resultados_muestra_el_estado_vacio(): void
    {
        $this->postulaciones->method('buscar')->willReturn($this->resultado([]));

        $cuerpo = $this->controlador->lista($this->pedir('/admin/postulaciones', ['estado' => 'Aprobada']))->cuerpo();

        $this->assertStringContainsString('No encontramos postulaciones con estos filtros', $cuerpo);
    }

    public function test_la_lista_pasa_los_filtros_de_la_url_al_repositorio(): void
    {
        $this->postulaciones->expects($this->once())->method('buscar')
            ->with(
                $this->callback(static fn (FiltroPostulaciones $f): bool => $f->idConvocatoria === 7 && $f->estado === 'Aprobada' && $f->texto === 'peña'),
                3
            )
            ->willReturn($this->resultado([]));

        $this->controlador->lista($this->pedir('/admin/postulaciones', ['convocatoria' => '7', 'estado' => 'Aprobada', 'q' => 'peña', 'pagina' => '3']));
    }

    public function test_el_modal_de_excel_conserva_los_filtros_activos_y_ofrece_las_columnas(): void
    {
        $this->postulaciones->method('buscar')->willReturn($this->resultado([$this->fila()]));

        $cuerpo = $this->controlador->lista($this->pedir('/admin/postulaciones', ['estado' => 'Aprobada']))->cuerpo();

        $this->assertStringContainsString('action="/admin/postulaciones/excel"', $cuerpo);
        $this->assertStringContainsString('name="estado" value="Aprobada"', $cuerpo);
        $this->assertStringContainsString('name="columnas[]" value="documento"', $cuerpo);
        $this->assertStringContainsString('name="columnas[]" value="documentos"', $cuerpo);
    }

    public function test_excel_sin_columnas_validas_vuelve_a_la_lista_con_un_aviso(): void
    {
        $this->postulaciones->expects($this->never())->method('todasParaExportar');

        $respuesta = $this->controlador->excel($this->pedir('/admin/postulaciones/excel', ['estado' => 'Aprobada', 'columnas' => ['clave_secreta']]));

        $this->assertSame(302, $respuesta->codigo());
        $this->assertSame('/admin/postulaciones?estado=Aprobada', $respuesta->destino());
        $this->assertNotSame([], $this->sesion->tomarMensajes());
    }

    public function test_excel_descarga_un_xlsx_sin_cache_con_los_filtros_activos(): void
    {
        $this->postulaciones->expects($this->once())->method('todasParaExportar')
            ->with($this->callback(static fn (FiltroPostulaciones $f): bool => $f->estado === 'Aprobada'))
            ->willReturn((static function (): \Generator {
                yield ['documento' => '1020345678', 'nombre' => 'Ana Peña', 'estado' => 'Aprobada'];
            })());

        $respuesta = $this->controlador->excel($this->pedir('/admin/postulaciones/excel', ['estado' => 'Aprobada', 'columnas' => ['documento', 'nombre', 'estado']]));

        $this->assertSame(200, $respuesta->codigo());
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $respuesta->cabecera('Content-Type'));
        $this->assertSame('attachment; filename="postulaciones-' . date('Y-m-d') . '.xlsx"', $respuesta->cabecera('Content-Disposition'));
        $this->assertSame('no-store', $respuesta->cabecera('Cache-Control'));
        // Un .xlsx es un ZIP: empieza por "PK".
        $this->assertSame('PK', substr($respuesta->cuerpo(), 0, 2));
    }
}
