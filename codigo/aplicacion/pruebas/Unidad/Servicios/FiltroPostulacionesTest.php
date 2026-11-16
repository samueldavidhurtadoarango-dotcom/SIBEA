<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Servicios;

use PHPUnit\Framework\TestCase;
use SIBEA\Nucleo\Peticion;
use SIBEA\Servicios\FiltroPostulaciones;

/** Los filtros de la lista de postulaciones: lo que no sirve se ignora, nunca falla. */
final class FiltroPostulacionesTest extends TestCase
{
    /** @param array<string, string> $consulta */
    private function filtro(array $consulta): FiltroPostulaciones
    {
        return FiltroPostulaciones::desdePeticion(new Peticion('GET', '/admin/postulaciones', [], [], '127.0.0.1', [], $consulta));
    }

    public function test_sin_nada_en_la_url_no_filtra(): void
    {
        $filtro = $this->filtro([]);

        $this->assertSame('', $filtro->texto);
        $this->assertNull($filtro->idBeneficio);
        $this->assertNull($filtro->idPrograma);
        $this->assertNull($filtro->estado);
        $this->assertNull($filtro->desde);
        $this->assertNull($filtro->hasta);
        $this->assertNull($filtro->idConvocatoria);
        $this->assertSame([], $filtro->comoConsulta());
    }

    public function test_lee_todos_los_filtros_validos(): void
    {
        $filtro = $this->filtro([
            'q' => '  peña ', 'beneficio' => '2', 'programa' => '3', 'estado' => 'Aprobada',
            'desde' => '2026-11-01', 'hasta' => '2026-11-30', 'convocatoria' => '7',
        ]);

        $this->assertSame('peña', $filtro->texto);
        $this->assertSame(2, $filtro->idBeneficio);
        $this->assertSame(3, $filtro->idPrograma);
        $this->assertSame('Aprobada', $filtro->estado);
        $this->assertSame('2026-11-01', $filtro->desde);
        $this->assertSame('2026-11-30', $filtro->hasta);
        $this->assertSame(7, $filtro->idConvocatoria);
    }

    public function test_un_estado_que_no_existe_se_ignora(): void
    {
        $this->assertNull($this->filtro(['estado' => 'Borrada'])->estado);
        // El estado es sensible a mayúsculas: así lo guarda la base.
        $this->assertNull($this->filtro(['estado' => 'aprobada'])->estado);
    }

    public function test_los_cuatro_estados_son_validos(): void
    {
        foreach (['Pendiente', 'Validada', 'Aprobada', 'Rechazada'] as $estado) {
            $this->assertSame($estado, $this->filtro(['estado' => $estado])->estado);
        }
    }

    public function test_ids_que_no_son_numeros_positivos_se_ignoran(): void
    {
        foreach (['abc', '0', '-4', '1.5', '', '2 OR 1=1'] as $malo) {
            $filtro = $this->filtro(['beneficio' => $malo, 'programa' => $malo, 'convocatoria' => $malo]);
            $this->assertNull($filtro->idBeneficio, "beneficio=$malo");
            $this->assertNull($filtro->idPrograma, "programa=$malo");
            $this->assertNull($filtro->idConvocatoria, "convocatoria=$malo");
        }
    }

    public function test_fechas_con_formato_o_dia_imposible_se_ignoran(): void
    {
        foreach (['15/11/2026', '2026-13-01', '2026-02-31', 'ayer', '2026-1-5'] as $mala) {
            $filtro = $this->filtro(['desde' => $mala, 'hasta' => $mala]);
            $this->assertNull($filtro->desde, $mala);
            $this->assertNull($filtro->hasta, $mala);
        }
    }

    public function test_como_consulta_devuelve_solo_los_filtros_activos(): void
    {
        $filtro = $this->filtro(['q' => 'ana', 'estado' => 'Borrada', 'convocatoria' => '4']);

        $this->assertSame(['q' => 'ana', 'convocatoria' => '4'], $filtro->comoConsulta());
    }
}
