<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Controladores;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SIBEA\Controladores\MiProcesoControlador;
use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Sesion;
use SIBEA\Nucleo\Vista;
use SIBEA\Servicios\ServicioMiProceso;

/** Las pantallas de «Mi proceso» (sin base de datos: el servicio es un doble de prueba). */
final class MiProcesoControladorTest extends TestCase
{
    private ServicioMiProceso&MockObject $servicio;
    private MiProcesoControlador $controlador;

    protected function setUp(): void
    {
        $_SESSION = [];
        session_id(bin2hex(random_bytes(8)));
        $sesion = new Sesion();
        $sesion->iniciarPara(7, 'Aprendiz', false, 'Luisa');
        $this->servicio = $this->createMock(ServicioMiProceso::class);
        $this->controlador = new MiProcesoControlador($sesion, new Vista(dirname(__DIR__, 3) . '/vistas', $sesion), $this->servicio);
    }

    /**
     * @param list<array<string, mixed>> $abiertas
     * @param list<array<string, mixed>> $postulaciones
     */
    private function cuerpoCon(array $abiertas, array $postulaciones): string
    {
        $estado = $abiertas === [] && $postulaciones === [] ? 'sin-postulacion' : 'con-actividad';
        $this->servicio->method('resumen')->with(7)->willReturn(['estado' => $estado, 'abiertas' => $abiertas, 'postulaciones' => $postulaciones]);
        $respuesta = $this->controlador->ver(new Peticion('GET', '/mi-proceso', [], [], '127.0.0.1'));
        $this->assertSame(200, $respuesta->codigo());

        return $respuesta->cuerpo();
    }

    /** @return array<string, mixed> */
    private function abierta(int $id, string $nombre): array
    {
        return [
            'id_convocatoria' => $id, 'nombre' => $nombre, 'beneficio' => 'Plan de datos', 'fecha_cierre' => '2026-12-15',
            'valor_pago' => '50000.00', 'numero_pagos' => 5, 'id_postulacion' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function postulacion(string $estado, string $vista): array
    {
        $pasos = [
            ['nombre' => 'Enviada', 'avance' => 'hecho', 'fecha' => '2026-08-04 09:00:00'],
            ['nombre' => 'Validada', 'avance' => 'actual', 'fecha' => null],
            ['nombre' => 'Aprobada', 'avance' => 'pendiente', 'fecha' => null],
        ];

        return [
            'id_postulacion' => 11, 'estado' => $estado, 'estadoVista' => $vista, 'convocatoria' => '2026-2', 'beneficio' => 'Apoyo de alimentación',
            'fecha_envio' => '2026-08-04 09:00:00', 'fecha_resultado' => '2026-08-22 10:00:00', 'valor_pago' => '180000.00',
            'duracion_meses' => 6, 'pasos' => $pasos,
        ];
    }

    public function test_sin_nada_muestra_el_texto_de_no_hay_convocatorias(): void
    {
        $cuerpo = $this->cuerpoCon([], []);

        $this->assertStringContainsString('En el momento no hay convocatorias disponibles', $cuerpo);
        $this->assertStringContainsString('Hola, Luisa', $cuerpo); // marco del aprendiz
    }

    public function test_cada_convocatoria_abierta_tiene_su_tarjeta_y_su_boton_postularme(): void
    {
        $cuerpo = $this->cuerpoCon([$this->abierta(1, 'Plan de datos 2026-2'), $this->abierta(2, 'Plan de datos 2027-1')], []);

        $this->assertSame(2, substr_count($cuerpo, 'data-prueba="convocatoria"'));
        $this->assertStringContainsString('href="/convocatorias/1/postulacion"', $cuerpo);
        $this->assertStringContainsString('href="/convocatorias/2/postulacion"', $cuerpo);
        $this->assertStringContainsString('$ 50.000', $cuerpo);
        $this->assertStringContainsString('15 dic 2026', $cuerpo);
        $this->assertStringNotContainsString('En el momento no hay convocatorias', $cuerpo);
    }

    public function test_en_revision_muestra_los_pasos_y_la_fecha_de_envio(): void
    {
        $cuerpo = $this->cuerpoCon([], [$this->postulacion('Pendiente', 'en-revision')]);

        $this->assertStringContainsString('Tu postulación está en revisión', $cuerpo);
        $this->assertStringContainsString('Enviada el 4 ago 2026', $cuerpo);
        $this->assertStringContainsString('br-pasos__paso--actual', $cuerpo);
    }

    public function test_seleccionado_muestra_el_beneficio_sin_pagos(): void
    {
        $cuerpo = $this->cuerpoCon([], [$this->postulacion('Aprobada', 'seleccionado')]);

        $this->assertStringContainsString('Fuiste seleccionado', $cuerpo);
        $this->assertStringContainsString('$ 180.000', $cuerpo);
        $this->assertStringContainsString('6 meses', $cuerpo);
        $this->assertStringNotContainsString('Mis pagos', $cuerpo); // los pagos son de la fase 2
    }

    public function test_no_seleccionado_es_amable_y_no_muestra_motivos(): void
    {
        $postulacion = $this->postulacion('Rechazada', 'no-seleccionado') + ['justificacion_rechazo' => 'Documento falso'];
        $cuerpo = $this->cuerpoCon([], [$postulacion]);

        $this->assertStringContainsString('Esta vez no fuiste seleccionado', $cuerpo);
        $this->assertStringContainsString('Resultado del 22 ago 2026', $cuerpo);
        $this->assertStringNotContainsString('Documento falso', $cuerpo);
    }

    public function test_una_convocatoria_abierta_y_una_postulacion_se_ven_juntas(): void
    {
        $cuerpo = $this->cuerpoCon([$this->abierta(2, 'Otra')], [$this->postulacion('Pendiente', 'en-revision')]);

        $this->assertStringContainsString('data-prueba="convocatoria"', $cuerpo);
        $this->assertStringContainsString('Tu postulación está en revisión', $cuerpo);
    }

    public function test_los_datos_se_escapan(): void
    {
        $cuerpo = $this->cuerpoCon([$this->abierta(1, '<script>alert(1)</script>')], []);

        $this->assertStringNotContainsString('<script>alert(1)', $cuerpo);
        $this->assertStringContainsString('&lt;script&gt;', $cuerpo);
    }
}
