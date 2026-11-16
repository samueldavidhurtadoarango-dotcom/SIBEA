<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Controladores;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SIBEA\Controladores\PostulacionAprendizControlador;
use SIBEA\Nucleo\ErrorDeNegocio;
use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Sesion;
use SIBEA\Nucleo\Vista;
use SIBEA\Pruebas\Unidad\Servicios\AlmacenTemporalDePrueba;
use SIBEA\Pruebas\Unidad\Validacion\ArchivosDePrueba;
use SIBEA\Repositorios\RepositorioCatalogos;
use SIBEA\Repositorios\RepositorioConvocatorias;
use SIBEA\Repositorios\RepositorioUsuarios;
use SIBEA\Servicios\ServicioPostulacion;
use SIBEA\Validacion\ValidadorPostulacion;

/** Mostrar y validar la postulación (sin base de datos: los repositorios son dobles de prueba). */
final class PostulacionAprendizControladorTest extends TestCase
{
    use ArchivosDePrueba;

    private const ID_CONVOCATORIA = 5;

    private Sesion $sesion;
    private string $carpetaTemporal;
    private RepositorioConvocatorias&MockObject $convocatorias;
    private ServicioPostulacion&MockObject $servicio;
    private PostulacionAprendizControlador $controlador;

    protected function setUp(): void
    {
        $_SESSION = [];
        // Por consola no hay sesión real: se le da un id para que la carpeta temporal tenga nombre.
        session_id(bin2hex(random_bytes(8)));
        $this->sesion = new Sesion();
        $this->sesion->iniciarPara(7, 'Aprendiz', false, 'Luisa');
        $this->carpetaTemporal = sys_get_temp_dir() . '/br-ctrl-' . bin2hex(random_bytes(4));

        $this->servicio = $this->createMock(ServicioPostulacion::class);
        $this->convocatorias = $this->createMock(RepositorioConvocatorias::class);
        $this->convocatorias->method('buscar')->willReturn($this->convocatoria());
        $this->convocatorias->method('abierta')->willReturn(true);
        $this->convocatorias->method('idPostulacionDe')->willReturn(null);

        $usuarios = $this->createMock(RepositorioUsuarios::class);
        $usuarios->method('perfilAprendiz')->willReturn([
            'nombre' => 'Luisa Fernanda', 'apellido' => 'Ríos', 'documento' => '1032118904', 'ficha' => '2761190',
            'correo' => 'luisa@soy.sena.edu.co', 'programa' => 'Contabilidad', 'telefono' => '3001112233',
            'direccion' => 'Calle 1', 'fecha_nacimiento' => '2002-03-15', 'id_eps' => 2,
        ]);

        $catalogos = $this->createMock(RepositorioCatalogos::class);
        $catalogos->method('eps')->willReturn([['id' => 2, 'nombre' => 'Sanitas']]);
        $catalogos->method('bancos')->willReturn([['id' => 1, 'nombre' => 'Bancolombia']]);
        $catalogos->method('tiposCuenta')->willReturn([['id' => 1, 'nombre' => 'Ahorros']]);

        $this->controlador = new PostulacionAprendizControlador(
            $this->sesion,
            new Vista(dirname(__DIR__, 3) . '/vistas', $this->sesion),
            $this->convocatorias,
            $catalogos,
            $usuarios,
            new ValidadorPostulacion(),
            new AlmacenTemporalDePrueba($this->carpetaTemporal),
            $this->servicio,
        );
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        foreach (glob($this->carpetaTemporal . '/*/*') ?: [] as $archivo) {
            unlink($archivo);
        }
        foreach (glob($this->carpetaTemporal . '/*') ?: [] as $carpeta) {
            rmdir($carpeta);
        }
        if (is_dir($this->carpetaTemporal)) {
            rmdir($this->carpetaTemporal);
        }
    }

    /** @return array<string, mixed> */
    private function convocatoria(): array
    {
        return [
            'id_convocatoria' => self::ID_CONVOCATORIA, 'nombre' => 'Plan de datos 2026-2', 'beneficio' => 'Plan de datos',
            'fecha_inicio' => '2026-10-01', 'fecha_cierre' => '2026-12-15', 'requisitos' => "Ser aprendiz activo.\nTener cuenta.",
            'valor_pago' => '50000.00', 'numero_pagos' => 5, 'estado' => 'Activa',
        ];
    }

    private function get(): Peticion
    {
        return new Peticion('GET', '/convocatorias/5/postulacion', [], [], '127.0.0.1', ['id' => '5']);
    }

    /**
     * @param array<string, string>               $campos
     * @param array<string, array<string, mixed>> $archivos
     */
    private function post(array $campos, array $archivos): Peticion
    {
        return new Peticion('POST', '/convocatorias/5/postulacion', $campos, $archivos, '127.0.0.1', ['id' => '5']);
    }

    /** @return array<string, string> */
    private function camposBuenos(): array
    {
        return [
            'telefono' => '3001112233', 'direccion' => 'Calle 1 # 2-3', 'fecha_nacimiento' => '2002-03-15', 'id_eps' => '2',
            'id_entidad_bancaria' => '1', 'id_tipo_cuenta' => '1', 'numero_cuenta' => '91234567890',
            'titular_cuenta' => 'Luisa Ríos', 'motivo' => 'Necesito el plan.', 'acepta_datos' => '1',
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function archivosBuenos(): array
    {
        return [
            'doc_identidad' => $this->subido($this->bytesPdf(), 'cedula.pdf'),
            'cert_eps' => $this->subido($this->bytesPng(), 'eps.png'),
            'cert_bancario' => $this->subido($this->bytesJpg(), 'banco.jpg'),
        ];
    }

    public function test_el_formulario_muestra_la_convocatoria_los_datos_de_la_cuenta_y_el_contacto_ya_guardado(): void
    {
        $respuesta = $this->controlador->formulario($this->get());

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('Postúlate a Plan de datos 2026-2', $respuesta->cuerpo());
        $this->assertStringContainsString('1032118904', $respuesta->cuerpo());       // «Tus datos», de la cuenta
        $this->assertStringContainsString('value="3001112233"', $respuesta->cuerpo()); // contacto ya guardado
        $this->assertStringContainsString('name="_csrf"', $respuesta->cuerpo());
        $this->assertStringContainsString('enctype="multipart/form-data"', $respuesta->cuerpo());
    }

    public function test_una_convocatoria_que_no_existe_da_404(): void
    {
        $convocatorias = $this->createMock(RepositorioConvocatorias::class);
        $convocatorias->method('buscar')->willReturn(null);
        $controlador = $this->controladorCon($convocatorias);

        $this->assertSame(404, $controlador->formulario($this->get())->codigo());
    }

    public function test_si_la_convocatoria_no_esta_abierta_muestra_cerrada(): void
    {
        $convocatorias = $this->createMock(RepositorioConvocatorias::class);
        $convocatorias->method('buscar')->willReturn($this->convocatoria());
        $convocatorias->method('abierta')->willReturn(false);
        $controlador = $this->controladorCon($convocatorias);

        $respuesta = $controlador->formulario($this->get());

        $this->assertStringContainsString('Esta convocatoria ya cerró', $respuesta->cuerpo());
        $this->assertStringNotContainsString('Revisar y enviar', $respuesta->cuerpo());
    }

    public function test_si_ya_se_postulo_muestra_cerrada_con_ya_te_postulaste(): void
    {
        $convocatorias = $this->createMock(RepositorioConvocatorias::class);
        $convocatorias->method('buscar')->willReturn($this->convocatoria());
        $convocatorias->method('abierta')->willReturn(true);
        $convocatorias->method('idPostulacionDe')->willReturn(99);
        $controlador = $this->controladorCon($convocatorias);

        $respuesta = $controlador->formulario($this->get());

        $this->assertStringContainsString('Ya te postulaste a esta convocatoria', $respuesta->cuerpo());
        $this->assertStringNotContainsString('Revisar y enviar', $respuesta->cuerpo());
    }

    public function test_un_post_con_errores_vuelve_al_formulario_con_el_resumen_y_no_guarda_nada(): void
    {
        $campos = ['telefono' => '12'] + $this->camposBuenos();
        $archivos = $this->archivosBuenos();
        unset($archivos['cert_eps']);

        $respuesta = $this->controlador->revisar($this->post($campos, $archivos));

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('Revisa 2 campos', $respuesta->cuerpo());
        $this->assertStringContainsString('Falta el Certificado de EPS', $respuesta->cuerpo());
        $this->assertStringContainsString('value="Calle 1 # 2-3"', $respuesta->cuerpo()); // lo escrito se conserva
        $this->assertArrayNotHasKey('postulacion_pendiente', $_SESSION);
    }

    public function test_un_post_correcto_guarda_lo_pendiente_en_la_sesion_y_muestra_revisar(): void
    {
        $respuesta = $this->controlador->revisar($this->post($this->camposBuenos(), $this->archivosBuenos()));

        $this->assertStringContainsString('Revisa tu postulación antes de enviarla', $respuesta->cuerpo());
        $this->assertStringContainsString('Sanitas', $respuesta->cuerpo());
        $pendiente = $this->sesion->postulacionPendiente(self::ID_CONVOCATORIA);
        $this->assertNotNull($pendiente);
        $this->assertSame('3001112233', $pendiente['datos']['telefono']);
        $this->assertSame(['doc_identidad', 'cert_eps', 'cert_bancario'], array_keys($pendiente['archivos']));
        $this->assertFileExists($pendiente['archivos']['cert_eps']['ruta']);
        $this->assertSame('png', $pendiente['archivos']['cert_eps']['extension']);
        $this->assertArrayNotHasKey('archivos', $pendiente['datos']);
    }

    public function test_un_segundo_post_correcto_borra_los_archivos_temporales_del_primero(): void
    {
        $this->controlador->revisar($this->post($this->camposBuenos(), $this->archivosBuenos()));
        $primeros = $this->sesion->postulacionPendiente(self::ID_CONVOCATORIA)['archivos'] ?? [];

        $this->controlador->revisar($this->post($this->camposBuenos(), $this->archivosBuenos()));

        foreach ($primeros as $archivo) {
            $this->assertFileDoesNotExist($archivo['ruta']);
        }
    }

    public function test_un_post_a_una_convocatoria_cerrada_no_guarda_nada(): void
    {
        $convocatorias = $this->createMock(RepositorioConvocatorias::class);
        $convocatorias->method('buscar')->willReturn($this->convocatoria());
        $convocatorias->method('abierta')->willReturn(false);
        $controlador = $this->controladorCon($convocatorias);

        $respuesta = $controlador->revisar($this->post($this->camposBuenos(), $this->archivosBuenos()));

        $this->assertStringContainsString('Esta convocatoria ya cerró', $respuesta->cuerpo());
        $this->assertArrayNotHasKey('postulacion_pendiente', $_SESSION);
    }

    public function test_una_convocatoria_inactiva_da_404_en_formulario_revisar_y_enviar(): void
    {
        $inactiva = ['estado' => 'Inactiva'] + $this->convocatoria();
        $convocatorias = $this->createMock(RepositorioConvocatorias::class);
        $convocatorias->method('buscar')->willReturn($inactiva);
        $convocatorias->method('abierta')->willReturn(false);
        $controlador = $this->controladorCon($convocatorias);

        $this->assertSame(404, $controlador->formulario($this->get())->codigo());
        $this->assertSame(404, $controlador->revisar($this->post($this->camposBuenos(), $this->archivosBuenos()))->codigo());
        $this->assertSame(404, $controlador->enviar($this->post([], []))->codigo());
    }

    public function test_una_convocatoria_que_pasa_a_inactiva_borra_los_archivos_pendientes_al_revisar_y_al_enviar(): void
    {
        foreach (['revisar', 'enviar'] as $accion) {
            $this->controlador->revisar($this->post($this->camposBuenos(), $this->archivosBuenos()));
            $pendientes = $this->sesion->postulacionPendiente(self::ID_CONVOCATORIA)['archivos'] ?? [];
            $this->assertNotEmpty($pendientes);

            // Mientras la persona revisaba, el administrador pasó la convocatoria a Inactiva.
            $convocatorias = $this->createMock(RepositorioConvocatorias::class);
            $convocatorias->method('buscar')->willReturn(['estado' => 'Inactiva'] + $this->convocatoria());
            $controlador = $this->controladorCon($convocatorias);
            $respuesta = $accion === 'revisar'
                ? $controlador->revisar($this->post($this->camposBuenos(), $this->archivosBuenos()))
                : $controlador->enviar($this->post([], []));

            $this->assertSame(404, $respuesta->codigo());
            $this->assertNull($this->sesion->postulacionPendiente(self::ID_CONVOCATORIA));
            foreach ($pendientes as $archivo) {
                $this->assertFileDoesNotExist($archivo['ruta']);
            }
        }
    }

    public function test_un_post_con_errores_descarta_tambien_la_revision_anterior(): void
    {
        $this->controlador->revisar($this->post($this->camposBuenos(), $this->archivosBuenos()));
        $anteriores = $this->sesion->postulacionPendiente(self::ID_CONVOCATORIA)['archivos'] ?? [];
        $this->assertNotEmpty($anteriores);

        $this->controlador->revisar($this->post(['telefono' => '12'] + $this->camposBuenos(), $this->archivosBuenos()));

        foreach ($anteriores as $archivo) {
            $this->assertFileDoesNotExist($archivo['ruta']);
        }
        $this->assertNull($this->sesion->postulacionPendiente(self::ID_CONVOCATORIA));
    }

    // ---------- Enviar la postulación ----------

    private function enviar(): Peticion
    {
        return new Peticion('POST', '/convocatorias/5/postulacion/enviar', [], [], '127.0.0.1', ['id' => '5']);
    }

    /** Deja una revisión pendiente como la deja «Revisar y enviar». */
    private function dejarPendiente(): void
    {
        $this->controlador->revisar($this->post($this->camposBuenos(), $this->archivosBuenos()));
    }

    public function test_enviar_llama_al_servicio_con_lo_pendiente_y_muestra_enviada(): void
    {
        $this->dejarPendiente();
        $pendiente = $this->sesion->postulacionPendiente(self::ID_CONVOCATORIA);
        $this->assertNotNull($pendiente);
        $this->servicio->expects($this->once())->method('enviar')
            ->with(7, self::ID_CONVOCATORIA, $pendiente['datos'], $pendiente['archivos'])
            ->willReturn(99);

        $respuesta = $this->controlador->enviar($this->enviar());

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('Listo, tu postulación fue enviada', $respuesta->cuerpo());
        $this->assertStringContainsString('Plan de datos', $respuesta->cuerpo());
        $this->assertSame('no-store', $respuesta->cabecera('Cache-Control'));
    }

    public function test_despues_de_enviar_se_borra_lo_pendiente_y_la_carpeta_temporal_de_la_sesion(): void
    {
        $this->dejarPendiente();
        $archivos = $this->sesion->postulacionPendiente(self::ID_CONVOCATORIA)['archivos'] ?? [];
        $this->servicio->method('enviar')->willReturn(99);

        $this->controlador->enviar($this->enviar());

        $this->assertNull($this->sesion->postulacionPendiente(self::ID_CONVOCATORIA));
        foreach ($archivos as $archivo) {
            $this->assertFileDoesNotExist($archivo['ruta']);
        }
        $this->assertSame([], glob($this->carpetaTemporal . '/*') ?: []);
    }

    public function test_enviar_sin_revision_pendiente_vuelve_al_formulario_con_un_aviso(): void
    {
        $this->servicio->expects($this->never())->method('enviar');

        $respuesta = $this->controlador->enviar($this->enviar());

        $this->assertSame('/convocatorias/5/postulacion', $respuesta->destino());
        $this->assertNotEmpty($this->sesion->tomarMensajes());
    }

    public function test_enviar_a_una_convocatoria_que_ya_cerro_no_llama_al_servicio_y_descarta_lo_pendiente(): void
    {
        $this->dejarPendiente();
        $archivos = $this->sesion->postulacionPendiente(self::ID_CONVOCATORIA)['archivos'] ?? [];
        $convocatorias = $this->createMock(RepositorioConvocatorias::class);
        $convocatorias->method('buscar')->willReturn($this->convocatoria());
        $convocatorias->method('abierta')->willReturn(false);
        $this->servicio->expects($this->never())->method('enviar');

        $respuesta = $this->controladorCon($convocatorias)->enviar($this->enviar());

        $this->assertStringContainsString('Esta convocatoria ya cerró', $respuesta->cuerpo());
        $this->assertNull($this->sesion->postulacionPendiente(self::ID_CONVOCATORIA));
        foreach ($archivos as $archivo) {
            $this->assertFileDoesNotExist($archivo['ruta']);
        }
    }

    public function test_enviar_si_ya_se_postulo_muestra_ya_te_postulaste_y_no_llama_al_servicio(): void
    {
        $this->dejarPendiente();
        $convocatorias = $this->createMock(RepositorioConvocatorias::class);
        $convocatorias->method('buscar')->willReturn($this->convocatoria());
        $convocatorias->method('abierta')->willReturn(true);
        $convocatorias->method('idPostulacionDe')->willReturn(99);
        $this->servicio->expects($this->never())->method('enviar');

        $respuesta = $this->controladorCon($convocatorias)->enviar($this->enviar());

        $this->assertStringContainsString('Ya te postulaste a esta convocatoria', $respuesta->cuerpo());
    }

    public function test_enviar_a_una_convocatoria_que_no_existe_da_404(): void
    {
        $convocatorias = $this->createMock(RepositorioConvocatorias::class);
        $convocatorias->method('buscar')->willReturn(null);

        $this->assertSame(404, $this->controladorCon($convocatorias)->enviar($this->enviar())->codigo());
    }

    public function test_si_el_servicio_falla_muestra_el_mensaje_y_descarta_lo_pendiente(): void
    {
        $this->dejarPendiente();
        $this->servicio->method('enviar')->willThrowException(new ErrorDeNegocio('La convocatoria no está abierta'));

        $respuesta = $this->controlador->enviar($this->enviar());

        $this->assertSame('/convocatorias/5/postulacion', $respuesta->destino());
        $mensajes = $this->sesion->tomarMensajes();
        $this->assertSame('error', $mensajes[0]['tipo']);
        $this->assertStringContainsString('La convocatoria no está abierta', $mensajes[0]['texto']);
        $this->assertNull($this->sesion->postulacionPendiente(self::ID_CONVOCATORIA));
    }

    private function controladorCon(RepositorioConvocatorias $convocatorias): PostulacionAprendizControlador
    {
        $usuarios = $this->createMock(RepositorioUsuarios::class);
        $usuarios->method('perfilAprendiz')->willReturn(['nombre' => 'Luisa', 'apellido' => 'Ríos', 'documento' => '1', 'ficha' => '2',
            'correo' => 'l@x.co', 'programa' => 'P', 'telefono' => null, 'direccion' => null, 'fecha_nacimiento' => null, 'id_eps' => null]);
        $catalogos = $this->createMock(RepositorioCatalogos::class);
        $catalogos->method('eps')->willReturn([['id' => 2, 'nombre' => 'Sanitas']]);
        $catalogos->method('bancos')->willReturn([['id' => 1, 'nombre' => 'Bancolombia']]);
        $catalogos->method('tiposCuenta')->willReturn([['id' => 1, 'nombre' => 'Ahorros']]);

        return new PostulacionAprendizControlador(
            $this->sesion,
            new Vista(dirname(__DIR__, 3) . '/vistas', $this->sesion),
            $convocatorias,
            $catalogos,
            $usuarios,
            new ValidadorPostulacion(),
            new AlmacenTemporalDePrueba($this->carpetaTemporal),
            $this->servicio,
        );
    }
}
