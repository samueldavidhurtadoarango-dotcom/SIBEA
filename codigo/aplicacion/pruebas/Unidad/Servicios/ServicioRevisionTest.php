<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Servicios;

use PDOException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SIBEA\Nucleo\ErrorDeNegocio;
use SIBEA\Repositorios\RepositorioDocumentos;
use SIBEA\Repositorios\RepositorioPostulaciones;
use SIBEA\Servicios\ServicioRevision;

/** Las reglas de la revisión y la decisión de una postulación (sin base de datos: los repositorios son dobles). */
final class ServicioRevisionTest extends TestCase
{
    private RepositorioPostulaciones&MockObject $postulaciones;
    private RepositorioDocumentos&MockObject $documentos;
    private ServicioRevision $servicio;

    protected function setUp(): void
    {
        $this->postulaciones = $this->createMock(RepositorioPostulaciones::class);
        $this->documentos = $this->createMock(RepositorioDocumentos::class);
        $this->servicio = new ServicioRevision($this->postulaciones, $this->documentos);
    }

    /**
     * Lo que devuelve el servicio para una postulación (los documentos ya vienen unidos).
     *
     * @param list<string> $estadosDeDocumentos Estado de cada documento: Pendiente, Validado o Rechazado.
     * @return array<string, mixed>
     */
    private function detalle(string $estado, array $estadosDeDocumentos = [], int $cupos = 30, int $aprobadas = 0): array
    {
        $documentos = [];
        foreach ($estadosDeDocumentos as $indice => $estadoDocumento) {
            $documentos[] = ['id_documento' => 100 + $indice, 'estado_validacion' => $estadoDocumento];
        }

        return ['id_postulacion' => 7, 'estado' => $estado, 'cupos' => $cupos, 'aprobadas' => $aprobadas, 'documentos' => $documentos];
    }

    /**
     * Deja a los repositorios falsos devolviendo esa postulación.
     *
     * @param list<string> $estadosDeDocumentos
     */
    private function dadaUnaPostulacion(string $estado, array $estadosDeDocumentos = ['Validado', 'Validado', 'Validado'], int $cupos = 30, int $aprobadas = 0): void
    {
        $detalle = $this->detalle($estado, $estadosDeDocumentos, $cupos, $aprobadas);
        $documentos = $detalle['documentos'];
        unset($detalle['documentos']);
        $this->postulaciones->method('detalle')->willReturn($detalle);
        $this->documentos->method('deLaPostulacion')->willReturn($documentos);
    }

    // ---------- acciones(): qué botones se pueden usar ----------

    public function test_pendiente_con_un_documento_pendiente_no_confirma_ni_aprueba(): void
    {
        $acciones = ServicioRevision::acciones($this->detalle('Pendiente', ['Validado', 'Validado', 'Pendiente']));

        $this->assertFalse($acciones['confirmar']);
        $this->assertFalse($acciones['aprobar']);
        $this->assertSame('Primero confirma los datos', $acciones['motivoAprobarDeshabilitado']);
    }

    public function test_pendiente_con_los_tres_documentos_validos_puede_confirmar(): void
    {
        $acciones = ServicioRevision::acciones($this->detalle('Pendiente', ['Validado', 'Validado', 'Validado']));

        $this->assertTrue($acciones['confirmar']);
        $this->assertFalse($acciones['aprobar']);
    }

    public function test_si_faltan_documentos_no_se_puede_confirmar(): void
    {
        $this->assertFalse(ServicioRevision::acciones($this->detalle('Pendiente', ['Validado', 'Validado']))['confirmar']);
        $this->assertFalse(ServicioRevision::acciones($this->detalle('Pendiente', []))['confirmar']);
    }

    public function test_un_documento_no_valido_impide_confirmar(): void
    {
        $this->assertFalse(ServicioRevision::acciones($this->detalle('Pendiente', ['Validado', 'Validado', 'Rechazado']))['confirmar']);
    }

    public function test_validada_con_cupos_puede_aprobar_y_ya_no_confirmar(): void
    {
        $acciones = ServicioRevision::acciones($this->detalle('Validada', ['Validado', 'Validado', 'Validado'], 30, 29));

        $this->assertTrue($acciones['aprobar']);
        $this->assertFalse($acciones['confirmar']);
        $this->assertNull($acciones['motivoAprobarDeshabilitado']);
    }

    public function test_validada_sin_cupos_no_puede_aprobar_y_dice_por_que(): void
    {
        $acciones = ServicioRevision::acciones($this->detalle('Validada', ['Validado', 'Validado', 'Validado'], 25, 25));

        $this->assertFalse($acciones['aprobar']);
        $this->assertSame('Ya no hay cupos en esta convocatoria', $acciones['motivoAprobarDeshabilitado']);
    }

    public function test_aprobada_y_rechazada_ya_no_tienen_acciones(): void
    {
        foreach (['Aprobada', 'Rechazada'] as $estado) {
            $acciones = ServicioRevision::acciones($this->detalle($estado, ['Validado', 'Validado', 'Validado']));

            $this->assertSame(['confirmar' => false, 'aprobar' => false, 'motivoAprobarDeshabilitado' => null], $acciones, $estado);
        }
    }

    public function test_el_motivo_de_confirmar_nombra_lo_que_falta(): void
    {
        $this->assertStringContainsString('no válido', (string) ServicioRevision::motivoConfirmarDeshabilitado($this->detalle('Pendiente', ['Validado', 'Validado', 'Rechazado'])));
        $this->assertStringContainsString('sin revisar', (string) ServicioRevision::motivoConfirmarDeshabilitado($this->detalle('Pendiente', ['Validado', 'Pendiente', 'Validado'])));
        $this->assertNull(ServicioRevision::motivoConfirmarDeshabilitado($this->detalle('Pendiente', ['Validado', 'Validado', 'Validado'])));
        $this->assertNull(ServicioRevision::motivoConfirmarDeshabilitado($this->detalle('Validada', ['Validado', 'Validado', 'Validado'])));
    }

    public function test_con_un_documento_no_valido_solo_queda_rechazar(): void
    {
        $this->assertTrue(ServicioRevision::soloQuedaRechazar($this->detalle('Pendiente', ['Validado', 'Rechazado', 'Validado'])));
        $this->assertFalse(ServicioRevision::soloQuedaRechazar($this->detalle('Pendiente', ['Validado', 'Pendiente', 'Validado'])));
        $this->assertFalse(ServicioRevision::soloQuedaRechazar($this->detalle('Rechazada', ['Rechazado', 'Validado', 'Validado'])));
    }

    // ---------- detalle() ----------

    public function test_detalle_une_documentos_historial_y_asignacion(): void
    {
        $this->postulaciones->method('detalle')->with(7)->willReturn(['id_postulacion' => 7, 'estado' => 'Aprobada']);
        $this->postulaciones->method('historial')->with(7)->willReturn([['estado_nuevo' => 'Aprobada']]);
        $this->postulaciones->method('asignacion')->with(7)->willReturn(['numero_pagos' => 4]);
        $this->documentos->method('deLaPostulacion')->with(7)->willReturn([['id_documento' => 1]]);

        $detalle = $this->servicio->detalle(7);

        $this->assertSame([['id_documento' => 1]], $detalle['documentos'] ?? null);
        $this->assertSame([['estado_nuevo' => 'Aprobada']], $detalle['historial'] ?? null);
        $this->assertSame(['numero_pagos' => 4], $detalle['asignacion'] ?? null);
    }

    public function test_detalle_de_una_postulacion_que_no_existe_es_null(): void
    {
        $this->postulaciones->method('detalle')->willReturn(null);

        $this->assertNull($this->servicio->detalle(99));
    }

    // ---------- marcarDocumentos() ----------

    public function test_marcar_valido_guarda_sin_observacion_y_rechazado_con_ella(): void
    {
        $this->dadaUnaPostulacion('Pendiente', ['Pendiente', 'Pendiente', 'Pendiente']);
        $this->postulaciones->expects($this->once())->method('iniciarTransaccion');
        $this->postulaciones->expects($this->once())->method('confirmarTransaccion');
        $llamadas = [];
        $this->documentos->method('marcarDocumento')->willReturnCallback(static function (int $idDocumento, int $idPostulacion, string $estado, ?string $observacion) use (&$llamadas): void {
            $llamadas[] = [$idDocumento, $idPostulacion, $estado, $observacion];
        });

        $this->servicio->marcarDocumentos(7, [
            100 => ['estado' => 'Validado', 'observacion' => 'texto que se descarta'],
            101 => ['estado' => 'Rechazado', 'observacion' => '  Foto borrosa  '],
        ]);

        $this->assertSame([[100, 7, 'Validado', null], [101, 7, 'Rechazado', 'Foto borrosa']], $llamadas);
    }

    public function test_rechazar_un_documento_exige_observacion(): void
    {
        $this->dadaUnaPostulacion('Pendiente', ['Pendiente', 'Pendiente', 'Pendiente']);
        $this->documentos->expects($this->never())->method('marcarDocumento');

        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('observación');

        $this->servicio->marcarDocumentos(7, [100 => ['estado' => 'Validado', 'observacion' => ''], 101 => ['estado' => 'Rechazado', 'observacion' => '   ']]);
    }

    public function test_un_estado_de_documento_inventado_se_rechaza(): void
    {
        $this->dadaUnaPostulacion('Pendiente', ['Pendiente', 'Pendiente', 'Pendiente']);
        $this->documentos->expects($this->never())->method('marcarDocumento');

        $this->expectException(ErrorDeNegocio::class);

        $this->servicio->marcarDocumentos(7, [100 => ['estado' => 'Pendiente', 'observacion' => '']]);
    }

    public function test_no_se_marca_un_documento_de_otra_postulacion(): void
    {
        $this->dadaUnaPostulacion('Pendiente', ['Pendiente', 'Pendiente', 'Pendiente']);
        $this->documentos->expects($this->never())->method('marcarDocumento');

        $this->expectException(ErrorDeNegocio::class);

        $this->servicio->marcarDocumentos(7, [999 => ['estado' => 'Validado', 'observacion' => '']]);
    }

    public function test_solo_se_revisan_documentos_de_postulaciones_pendientes(): void
    {
        $this->dadaUnaPostulacion('Aprobada');
        $this->documentos->expects($this->never())->method('marcarDocumento');

        $this->expectException(ErrorDeNegocio::class);

        $this->servicio->marcarDocumentos(7, [100 => ['estado' => 'Rechazado', 'observacion' => 'x']]);
    }

    public function test_si_la_base_falla_al_marcar_se_deshace_todo(): void
    {
        $this->dadaUnaPostulacion('Pendiente', ['Pendiente', 'Pendiente', 'Pendiente']);
        $error = new PDOException('x');
        $error->errorInfo = ['45000', 1644, 'La fecha y el usuario de validación los registra la base de datos'];
        $this->documentos->method('marcarDocumento')->willThrowException($error);
        $this->postulaciones->expects($this->once())->method('revertirTransaccion');
        $this->postulaciones->expects($this->never())->method('confirmarTransaccion');

        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('La fecha y el usuario de validación');

        $this->servicio->marcarDocumentos(7, [100 => ['estado' => 'Validado', 'observacion' => '']]);
    }

    // ---------- confirmar() ----------

    public function test_confirmar_pasa_a_validada_cuando_los_tres_documentos_son_validos(): void
    {
        $this->dadaUnaPostulacion('Pendiente');
        $this->postulaciones->expects($this->once())->method('cambiarEstado')->with(7, 'Validada', null);

        $this->servicio->confirmar(7);
    }

    public function test_confirmar_con_un_documento_sin_revisar_se_rechaza_y_no_toca_la_base(): void
    {
        $this->dadaUnaPostulacion('Pendiente', ['Validado', 'Pendiente', 'Validado']);
        $this->postulaciones->expects($this->never())->method('cambiarEstado');

        $this->expectException(ErrorDeNegocio::class);

        $this->servicio->confirmar(7);
    }

    public function test_confirmar_una_postulacion_que_no_existe_da_error_de_negocio(): void
    {
        $this->postulaciones->method('detalle')->willReturn(null);

        $this->expectException(ErrorDeNegocio::class);

        $this->servicio->confirmar(99);
    }

    // ---------- aprobar() ----------

    public function test_aprobar_pasa_a_aprobada(): void
    {
        $this->dadaUnaPostulacion('Validada');
        $this->postulaciones->expects($this->once())->method('cambiarEstado')->with(7, 'Aprobada', null);

        $this->servicio->aprobar(7);
    }

    public function test_aprobar_una_pendiente_se_rechaza_antes_de_llegar_a_la_base(): void
    {
        $this->dadaUnaPostulacion('Pendiente');
        $this->postulaciones->expects($this->never())->method('cambiarEstado');

        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('Primero confirma los datos');

        $this->servicio->aprobar(7);
    }

    public function test_si_la_base_dice_que_no_hay_cupos_se_muestra_ya_no_hay_cupos(): void
    {
        $this->dadaUnaPostulacion('Validada');
        $error = new PDOException('x');
        $error->errorInfo = ['45000', 1644, 'No hay cupos disponibles en la convocatoria: 1 aprobadas de 1 cupos'];
        $this->postulaciones->method('cambiarEstado')->willThrowException($error);

        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('Ya no hay cupos en esta convocatoria');

        $this->servicio->aprobar(7);
    }

    public function test_otros_mensajes_de_la_base_se_muestran_tal_cual(): void
    {
        $this->dadaUnaPostulacion('Validada');
        $error = new PDOException('x');
        $error->errorInfo = ['45000', 1644, 'Solo un administrador activo puede aprobar una postulación'];
        $this->postulaciones->method('cambiarEstado')->willThrowException($error);

        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('Solo un administrador activo puede aprobar una postulación');

        $this->servicio->aprobar(7);
    }

    // ---------- rechazar() ----------

    public function test_rechazar_sin_motivo_lanza_error_de_negocio(): void
    {
        $this->dadaUnaPostulacion('Pendiente');
        $this->postulaciones->expects($this->never())->method('cambiarEstado');

        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('motivo');

        $this->servicio->rechazar(7, '   ');
    }

    public function test_rechazar_con_un_motivo_de_mas_de_mil_caracteres_se_rechaza(): void
    {
        $this->dadaUnaPostulacion('Pendiente');
        $this->postulaciones->expects($this->never())->method('cambiarEstado');

        $this->expectException(ErrorDeNegocio::class);

        $this->servicio->rechazar(7, str_repeat('a', 1001));
    }

    public function test_rechazar_guarda_el_motivo_sin_espacios_sobrantes(): void
    {
        $this->dadaUnaPostulacion('Validada');
        $this->postulaciones->expects($this->once())->method('cambiarEstado')->with(7, 'Rechazada', 'Ya recibe otro apoyo');

        $this->servicio->rechazar(7, '  Ya recibe otro apoyo ');
    }

    public function test_acepta_un_motivo_de_exactamente_mil_caracteres_con_tildes(): void
    {
        $this->dadaUnaPostulacion('Pendiente');
        $motivo = str_repeat('ñ', 1000);
        $this->postulaciones->expects($this->once())->method('cambiarEstado')->with(7, 'Rechazada', $motivo);

        $this->servicio->rechazar(7, $motivo);
    }

    public function test_una_postulacion_aprobada_ya_no_se_puede_rechazar(): void
    {
        $this->dadaUnaPostulacion('Aprobada');
        $this->postulaciones->expects($this->never())->method('cambiarEstado');

        $this->expectException(ErrorDeNegocio::class);

        $this->servicio->rechazar(7, 'Cambié de opinión');
    }
}
