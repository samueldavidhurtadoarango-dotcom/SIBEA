<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Servicios;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SIBEA\Repositorios\RepositorioConvocatorias;
use SIBEA\Repositorios\RepositorioPostulaciones;
use SIBEA\Servicios\ServicioMiProceso;

/** Cómo se arma «Mi proceso» del aprendiz (sin base de datos: los repositorios son dobles de prueba). */
final class ServicioMiProcesoTest extends TestCase
{
    private RepositorioConvocatorias&MockObject $convocatorias;
    private RepositorioPostulaciones&MockObject $postulaciones;
    private ServicioMiProceso $servicio;

    protected function setUp(): void
    {
        $this->convocatorias = $this->createMock(RepositorioConvocatorias::class);
        $this->postulaciones = $this->createMock(RepositorioPostulaciones::class);
        $this->servicio = new ServicioMiProceso($this->convocatorias, $this->postulaciones);
    }

    /** @return array<string, mixed> */
    private function abierta(int $id, ?int $idPostulacion): array
    {
        return ['id_convocatoria' => $id, 'nombre' => "Convocatoria $id", 'id_postulacion' => $idPostulacion];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function postulacion(string $estado, array $extra = []): array
    {
        return $extra + [
            'id_postulacion' => 11, 'estado' => $estado, 'convocatoria' => 'Convocatoria 1', 'beneficio' => 'Plan de datos',
            'fecha_envio' => '2026-08-04 09:00:00', 'fecha_validacion' => null, 'fecha_resultado' => null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $abiertas
     * @param list<array<string, mixed>> $postulaciones
     */
    private function dado(array $abiertas, array $postulaciones): void
    {
        $this->convocatorias->method('abiertasPara')->with(7)->willReturn($abiertas);
        $this->postulaciones->method('delAprendiz')->with(7)->willReturn($postulaciones);
    }

    public function test_con_dos_abiertas_y_una_postulacion_en_una_solo_queda_la_otra_como_abierta(): void
    {
        $this->dado([$this->abierta(1, 11), $this->abierta(2, null)], [$this->postulacion('Pendiente')]);

        $resumen = $this->servicio->resumen(7);

        $this->assertCount(1, $resumen['abiertas']);
        $this->assertSame(2, $resumen['abiertas'][0]['id_convocatoria']);
        $this->assertCount(1, $resumen['postulaciones']);
        $this->assertSame('en-revision', $resumen['postulaciones'][0]['estadoVista']);
        $this->assertSame('con-actividad', $resumen['estado']);
    }

    /** @return array<string, array{string, string}> */
    public static function estados(): array
    {
        return [
            'pendiente' => ['Pendiente', 'en-revision'],
            'validada' => ['Validada', 'en-revision'],
            'aprobada' => ['Aprobada', 'seleccionado'],
            'rechazada' => ['Rechazada', 'no-seleccionado'],
        ];
    }

    /** @dataProvider estados */
    public function test_el_estado_de_la_postulacion_se_traduce_al_estado_de_la_pantalla(string $estado, string $esperado): void
    {
        $this->dado([], [$this->postulacion($estado)]);

        $this->assertSame($esperado, $this->servicio->resumen(7)['postulaciones'][0]['estadoVista']);
    }

    public function test_una_postulacion_rechazada_no_deja_pasar_el_motivo_interno(): void
    {
        $this->dado([], [$this->postulacion('Rechazada', ['justificacion_rechazo' => 'Documento falso', 'observaciones' => 'nota interna'])]);

        $postulacion = $this->servicio->resumen(7)['postulaciones'][0];

        $this->assertSame('no-seleccionado', $postulacion['estadoVista']);
        $this->assertArrayNotHasKey('justificacion_rechazo', $postulacion);
        $this->assertArrayNotHasKey('observaciones', $postulacion);
    }

    public function test_sin_convocatorias_ni_postulaciones_el_estado_es_sin_postulacion(): void
    {
        $this->dado([], []);

        $resumen = $this->servicio->resumen(7);

        $this->assertSame('sin-postulacion', $resumen['estado']);
        $this->assertSame([], $resumen['abiertas']);
        $this->assertSame([], $resumen['postulaciones']);
    }

    public function test_solo_con_convocatorias_abiertas_ya_no_es_sin_postulacion(): void
    {
        $this->dado([$this->abierta(1, null)], []);

        $this->assertSame('con-actividad', $this->servicio->resumen(7)['estado']);
    }

    public function test_los_pasos_de_una_postulacion_pendiente_marcan_enviada_hecha_y_validada_en_curso(): void
    {
        $this->dado([], [$this->postulacion('Pendiente')]);

        $pasos = $this->servicio->resumen(7)['postulaciones'][0]['pasos'];

        $this->assertSame(['Enviada', 'Validada', 'Aprobada'], array_column($pasos, 'nombre'));
        $this->assertSame(['hecho', 'actual', 'pendiente'], array_column($pasos, 'avance'));
        $this->assertSame('2026-08-04 09:00:00', $pasos[0]['fecha']);
    }

    public function test_los_pasos_de_una_validada_marcan_aprobada_en_curso(): void
    {
        $this->dado([], [$this->postulacion('Validada', ['fecha_validacion' => '2026-08-12 10:00:00'])]);

        $pasos = $this->servicio->resumen(7)['postulaciones'][0]['pasos'];

        $this->assertSame(['hecho', 'hecho', 'actual'], array_column($pasos, 'avance'));
        $this->assertSame('2026-08-12 10:00:00', $pasos[1]['fecha']);
    }

    public function test_los_pasos_de_una_aprobada_estan_todos_hechos(): void
    {
        $this->dado([], [$this->postulacion('Aprobada', ['fecha_validacion' => '2026-08-12 10:00:00', 'fecha_resultado' => '2026-08-22 10:00:00'])]);

        $pasos = $this->servicio->resumen(7)['postulaciones'][0]['pasos'];

        $this->assertSame(['hecho', 'hecho', 'hecho'], array_column($pasos, 'avance'));
        $this->assertSame('2026-08-22 10:00:00', $pasos[2]['fecha']);
    }
}
