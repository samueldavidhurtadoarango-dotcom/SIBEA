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
use BenefitRack\Servicios\ServicioRevision;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** La pantalla de detalle de una postulación y sus cuatro decisiones (sin base de datos: el servicio es un doble). */
final class DetallePostulacionControladorTest extends TestCase
{
    private Sesion $sesion;
    private ServicioRevision&MockObject $revision;
    private PostulacionesAdminControlador $controlador;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->sesion = new Sesion();
        $this->sesion->iniciarPara(1, 'Administrador', false, 'Admin Prueba');
        $this->revision = $this->createMock(ServicioRevision::class);

        $this->controlador = new PostulacionesAdminControlador(
            $this->sesion,
            new Vista(dirname(__DIR__, 3) . '/vistas', $this->sesion),
            $this->createMock(RepositorioPostulaciones::class),
            $this->createMock(RepositorioCatalogos::class),
            $this->revision,
        );
    }

    /** @param array<string, mixed> $campos */
    private function post(string $ruta, array $campos = []): Peticion
    {
        return (new Peticion('POST', $ruta, $campos, [], '127.0.0.1'))->conParametros(['id' => '15']);
    }

    private function ver(): Peticion
    {
        return (new Peticion('GET', '/admin/postulaciones/15', [], [], '127.0.0.1'))->conParametros(['id' => '15']);
    }

    /**
     * Una postulación completa, como la entrega ServicioRevision::detalle().
     *
     * @param list<string>         $estadosDeDocumentos
     * @param array<string, mixed> $cambios            Lo que se quiere distinto en la fila.
     * @return array<string, mixed>
     */
    private function detalle(string $estado = 'Pendiente', array $estadosDeDocumentos = ['Pendiente', 'Pendiente', 'Pendiente'], array $cambios = []): array
    {
        $tipos = ['Documento de identidad', 'Certificado bancario', 'Certificado de EPS'];
        $documentos = [];
        foreach ($estadosDeDocumentos as $indice => $estadoDocumento) {
            $documentos[] = [
                'id_documento' => 100 + $indice, 'tipo' => $tipos[$indice], 'nombre_archivo' => "archivo$indice.pdf", 'extension' => 'pdf',
                'tamano_mb' => '0.41', 'estado_validacion' => $estadoDocumento,
                'observaciones' => $estadoDocumento === 'Rechazado' ? 'Foto borrosa' : null,
            ];
        }

        return $cambios + [
            'id_postulacion' => 15, 'documento' => '1034567218', 'nombre' => 'Daniela Mosquera Palacios', 'correo' => 'dmosquera41@soy.sena.edu.co',
            'telefono' => '3124567801', 'programa' => 'Gestión Administrativa', 'ficha' => '2879415', 'eps' => 'Nueva EPS',
            'convocatoria' => 'Tarjeta cívica 2026-2', 'beneficio' => 'Cívica', 'estado' => $estado, 'fecha' => '2026-10-27 16:12:00',
            'banco' => 'Bancolombia', 'tipo_cuenta' => 'Ahorros', 'numero_cuenta' => '12345678901', 'titular' => 'Daniela Mosquera',
            'motivo' => 'Necesito apoyo', 'fecha_nacimiento' => '2009-03-14', 'direccion' => 'Carrera 52 # 48-17',
            'id_convocatoria' => 7, 'cupos' => 30, 'aprobadas' => 0, 'justificacion_rechazo' => null,
            'documentos' => $documentos,
            'historial' => [['estado_anterior' => null, 'estado_nuevo' => 'Pendiente', 'justificacion' => null, 'quien' => 'Daniela Mosquera Palacios', 'fecha' => '2026-10-27 16:12:00']],
            'asignacion' => null,
        ];
    }

    /**
     * @param list<string>         $estadosDeDocumentos
     * @param array<string, mixed> $cambios
     */
    private function mostrar(string $estado = 'Pendiente', array $estadosDeDocumentos = ['Pendiente', 'Pendiente', 'Pendiente'], array $cambios = []): string
    {
        $this->revision->method('detalle')->willReturn($this->detalle($estado, $estadosDeDocumentos, $cambios));
        $respuesta = $this->controlador->detalle($this->ver());
        $this->assertSame(200, $respuesta->codigo());

        return $respuesta->cuerpo();
    }

    // ---------- GET /admin/postulaciones/{id} ----------

    public function test_el_detalle_muestra_los_datos_los_cupos_y_el_historial(): void
    {
        $cuerpo = $this->mostrar();

        $this->assertStringContainsString('Daniela Mosquera Palacios', $cuerpo);
        $this->assertStringContainsString('1034567218', $cuerpo);
        $this->assertStringContainsString('Nueva EPS', $cuerpo);
        $this->assertStringContainsString('Bancolombia', $cuerpo);
        $this->assertStringContainsString('14 mar 2009', $cuerpo);
        $this->assertStringContainsString('Cupos: 0 de 30 usados', $cuerpo);
        $this->assertStringContainsString('Quedan 30', $cuerpo);
        $this->assertStringContainsString('Enviada · queda Pendiente', $cuerpo);
        $this->assertStringContainsString('/documentos/100', $cuerpo); // enlace «Ver documento»
    }

    public function test_el_detalle_de_una_postulacion_que_no_existe_es_404(): void
    {
        $this->revision->method('detalle')->willReturn(null);

        $this->assertSame(404, $this->controlador->detalle($this->ver())->codigo());
    }

    public function test_el_detalle_escapa_lo_que_escribio_el_aprendiz(): void
    {
        $cuerpo = $this->mostrar('Pendiente', ['Pendiente', 'Pendiente', 'Pendiente'], ['motivo' => '<script>alert(1)</script>', 'direccion' => '<img src=x onerror=alert(1)>']);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $cuerpo);
        $this->assertStringNotContainsString('<img src=x', $cuerpo);
    }

    public function test_pendiente_con_documentos_sin_revisar_deshabilita_confirmar_y_aprobar_con_su_motivo(): void
    {
        $cuerpo = $this->mostrar();

        $this->assertMatchesRegularExpression('/data-prueba="confirmar-datos"[^>]*disabled/', $cuerpo);
        $this->assertMatchesRegularExpression('/data-prueba="aprobar"[^>]*disabled/', $cuerpo);
        $this->assertStringContainsString('Primero confirma los datos', $cuerpo);
        $this->assertStringContainsString('name="estado[100]"', $cuerpo); // se puede revisar cada documento
        $this->assertStringNotContainsString('Pedir documento', $cuerpo); // es de la fase 2
    }

    public function test_con_un_documento_no_valido_la_vista_explica_que_solo_queda_rechazar(): void
    {
        $cuerpo = $this->mostrar('Pendiente', ['Validado', 'Validado', 'Rechazado']);

        $this->assertStringContainsString('solo se puede rechazar', $cuerpo);
        // R17: la marca «No válido» se puede corregir mientras la postulación siga Pendiente.
        $this->assertStringContainsString('corregir esa marca a «Válido»', $cuerpo);
        $this->assertStringContainsString('Foto borrosa', $cuerpo);
        $this->assertMatchesRegularExpression('/data-prueba="confirmar-datos"[^>]*disabled/', $cuerpo);
        $this->assertStringNotContainsString('Pedir documento', $cuerpo);
    }

    public function test_con_los_tres_documentos_validos_se_puede_confirmar(): void
    {
        $cuerpo = $this->mostrar('Pendiente', ['Validado', 'Validado', 'Validado']);

        $this->assertDoesNotMatchRegularExpression('/data-prueba="confirmar-datos"[^>]*disabled/', $cuerpo);
        $this->assertStringContainsString('action="/admin/postulaciones/15/confirmar"', $cuerpo);
    }

    public function test_validada_con_cupos_se_puede_aprobar_y_rechazar(): void
    {
        $cuerpo = $this->mostrar('Validada', ['Validado', 'Validado', 'Validado']);

        $this->assertDoesNotMatchRegularExpression('/data-prueba="aprobar"[^>]*disabled/', $cuerpo);
        $this->assertStringContainsString('action="/admin/postulaciones/15/aprobar"', $cuerpo);
        $this->assertStringContainsString('action="/admin/postulaciones/15/rechazar"', $cuerpo);
        $this->assertStringContainsString('name="motivo"', $cuerpo);
        $this->assertStringContainsString('name="_csrf"', $cuerpo);
    }

    public function test_validada_sin_cupos_deshabilita_aprobar_y_lo_explica(): void
    {
        $cuerpo = $this->mostrar('Validada', ['Validado', 'Validado', 'Validado'], ['cupos' => 25, 'aprobadas' => 25]);

        $this->assertMatchesRegularExpression('/data-prueba="aprobar"[^>]*disabled/', $cuerpo);
        $this->assertStringContainsString('Ya no hay cupos en esta convocatoria', $cuerpo);
        $this->assertStringContainsString('No quedan cupos', $cuerpo);
    }

    public function test_aprobada_muestra_la_asignacion_y_ya_no_ofrece_decisiones(): void
    {
        $cuerpo = $this->mostrar('Aprobada', ['Validado', 'Validado', 'Validado'], [
            'aprobadas' => 1,
            'asignacion' => ['valor_pago' => '90000.00', 'numero_pagos' => 4, 'duracion_meses' => 4, 'fecha_inicio' => '2026-11-15', 'estado' => 'En curso'],
        ]);

        $this->assertStringContainsString('Asignación creada', $cuerpo);
        $this->assertStringContainsString('$ 90.000', $cuerpo);
        $this->assertStringNotContainsString('/aprobar"', $cuerpo);
        $this->assertStringNotContainsString('/rechazar"', $cuerpo);
        $this->assertStringNotContainsString('name="estado[100]"', $cuerpo);
    }

    public function test_rechazada_muestra_el_motivo_y_quien_la_rechazo(): void
    {
        $cuerpo = $this->mostrar('Rechazada', ['Validado', 'Validado', 'Validado'], [
            'justificacion_rechazo' => 'Ya recibe otro apoyo',
            'historial' => [['estado_anterior' => 'Validada', 'estado_nuevo' => 'Rechazada', 'justificacion' => 'Ya recibe otro apoyo', 'quien' => 'Laura Gómez', 'fecha' => '2026-11-05 11:15:00']],
        ]);

        $this->assertStringContainsString('Ya recibe otro apoyo', $cuerpo);
        $this->assertStringContainsString('Laura Gómez', $cuerpo);
        $this->assertStringContainsString('Validada → Rechazada', $cuerpo);
        $this->assertStringNotContainsString('/aprobar"', $cuerpo);
    }

    // ---------- Decisiones: siempre redirigen al detalle (POST/Redirect/GET) con un aviso ----------

    public function test_confirmar_redirige_al_detalle_con_un_aviso_de_exito(): void
    {
        $this->revision->expects($this->once())->method('confirmar')->with(15);

        $respuesta = $this->controlador->confirmar($this->post('/admin/postulaciones/15/confirmar'));

        $this->assertSame(302, $respuesta->codigo());
        $this->assertSame('/admin/postulaciones/15', $respuesta->destino());
        $this->assertSame('exito', $this->sesion->tomarMensajes()[0]['tipo']);
    }

    public function test_aprobar_con_el_error_de_cupos_vuelve_al_detalle_con_el_mensaje_sencillo(): void
    {
        $this->revision->method('aprobar')->willThrowException(new ErrorDeNegocio('Ya no hay cupos en esta convocatoria'));

        $respuesta = $this->controlador->aprobar($this->post('/admin/postulaciones/15/aprobar'));

        $this->assertSame(302, $respuesta->codigo());
        $this->assertSame('/admin/postulaciones/15', $respuesta->destino());
        $this->assertSame([['tipo' => 'error', 'texto' => 'Ya no hay cupos en esta convocatoria']], $this->sesion->tomarMensajes());
    }

    public function test_aprobar_bien_deja_un_aviso_de_exito(): void
    {
        $this->revision->expects($this->once())->method('aprobar')->with(15);

        $this->controlador->aprobar($this->post('/admin/postulaciones/15/aprobar'));

        $this->assertSame('exito', $this->sesion->tomarMensajes()[0]['tipo']);
    }

    public function test_rechazar_pasa_el_motivo_al_servicio(): void
    {
        $this->revision->expects($this->once())->method('rechazar')->with(15, 'Ya recibe otro apoyo');

        $respuesta = $this->controlador->rechazar($this->post('/admin/postulaciones/15/rechazar', ['motivo' => ' Ya recibe otro apoyo ']));

        $this->assertSame('/admin/postulaciones/15', $respuesta->destino());
        $this->assertSame('exito', $this->sesion->tomarMensajes()[0]['tipo']);
    }

    public function test_rechazar_sin_motivo_muestra_el_error_del_servicio(): void
    {
        $this->revision->method('rechazar')->willThrowException(new ErrorDeNegocio('Escribe el motivo del rechazo.'));

        $this->controlador->rechazar($this->post('/admin/postulaciones/15/rechazar'));

        $this->assertSame([['tipo' => 'error', 'texto' => 'Escribe el motivo del rechazo.']], $this->sesion->tomarMensajes());
    }

    public function test_guardar_la_revision_arma_el_mapa_de_documentos_para_el_servicio(): void
    {
        $this->revision->expects($this->once())->method('marcarDocumentos')->with(15, [
            100 => ['estado' => 'Validado', 'observacion' => ''],
            101 => ['estado' => 'Rechazado', 'observacion' => 'Foto borrosa'],
        ]);

        $respuesta = $this->controlador->documentos($this->post('/admin/postulaciones/15/documentos', [
            'estado' => ['100' => 'Validado', '101' => 'Rechazado'],
            'observacion' => ['101' => 'Foto borrosa'],
        ]));

        $this->assertSame('/admin/postulaciones/15', $respuesta->destino());
        $this->assertSame('exito', $this->sesion->tomarMensajes()[0]['tipo']);
    }

    public function test_guardar_la_revision_sin_marcar_nada_avisa_y_no_llama_al_servicio(): void
    {
        $this->revision->expects($this->never())->method('marcarDocumentos');

        $this->controlador->documentos($this->post('/admin/postulaciones/15/documentos'));

        $this->assertSame('aviso', $this->sesion->tomarMensajes()[0]['tipo']);
    }

    public function test_guardar_la_revision_con_error_de_negocio_muestra_el_mensaje(): void
    {
        $this->revision->method('marcarDocumentos')->willThrowException(new ErrorDeNegocio('Escribe la observación de cada documento no válido.'));

        $this->controlador->documentos($this->post('/admin/postulaciones/15/documentos', ['estado' => ['101' => 'Rechazado']]));

        $this->assertSame('error', $this->sesion->tomarMensajes()[0]['tipo']);
    }
}
