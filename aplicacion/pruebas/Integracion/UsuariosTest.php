<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Integracion;

use BenefitRack\Nucleo\Aplicacion;
use BenefitRack\Nucleo\Csrf;
use BenefitRack\Nucleo\Enrutador;
use BenefitRack\Nucleo\ErrorDeNegocio;
use BenefitRack\Nucleo\Fabrica;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Repositorios\RepositorioCatalogos;
use BenefitRack\Repositorios\RepositorioUsuarios;
use BenefitRack\Servicios\ServicioUsuarios;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** M3a: crear aprendices, cambiar estado, restablecer contraseña y entregar el Excel. */
final class UsuariosTest extends BaseDePrueba
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    /** Un servicio conectado como lo haría la Fabrica: la base sabe quién es el administrador. */
    private function servicio(int $idAdmin): ServicioUsuarios
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idAdmin, '127.0.0.1');

        return new ServicioUsuarios(new RepositorioUsuarios($bd));
    }

    private function idPrograma(): int
    {
        return (int) (new RepositorioCatalogos($this->baseDatosDeLaApp()))->programas()[0]['id'];
    }

    /** @return array<string, string|int> */
    private function datosNuevos(): array
    {
        return [
            'documento' => (string) random_int(1000000000, 1999999999),
            'nombre' => 'Karen Dayana',
            'apellido' => 'Moreno Lozano',
            'correo' => 'karen' . bin2hex(random_bytes(4)) . '@soy.sena.edu.co',
            'id_programa' => $this->idPrograma(),
            'ficha' => '2758341',
        ];
    }

    private function filaDe(int $id): array
    {
        $consulta = $this->baseDatosDeLaApp()->pdo()->query("SELECT * FROM usuarios WHERE id_usuario = $id");
        $fila = $consulta === false ? false : $consulta->fetch();

        return is_array($fila) ? $fila : [];
    }

    // ---------- Servicio ----------

    public function test_crear_un_aprendiz_devuelve_una_contrasena_que_sirve_y_debe_cambiarse(): void
    {
        $admin = $this->crearAdmin();

        $resultado = $this->servicio($admin)->crearAprendiz($this->datosNuevos());

        $fila = $this->filaDe($resultado['id']);
        $this->assertTrue(password_verify($resultado['contrasena'], (string) $fila['password_hash']));
        $this->assertSame(1, (int) $fila['debe_cambiar_contrasena']);
        $this->assertSame(2, (int) $fila['id_rol']);
        $this->assertSame(10, strlen($resultado['contrasena']));
    }

    public function test_crear_con_el_mismo_documento_lanza_error_de_negocio(): void
    {
        $admin = $this->crearAdmin();
        $datos = $this->datosNuevos();
        $this->servicio($admin)->crearAprendiz($datos);

        $this->expectException(ErrorDeNegocio::class);
        $this->servicio($admin)->crearAprendiz(['correo' => 'otro' . bin2hex(random_bytes(4)) . '@soy.sena.edu.co'] + $datos);
    }

    public function test_crear_con_el_mismo_correo_lanza_error_de_negocio(): void
    {
        $admin = $this->crearAdmin();
        $datos = $this->datosNuevos();
        $this->servicio($admin)->crearAprendiz($datos);

        $this->expectException(ErrorDeNegocio::class);
        $this->servicio($admin)->crearAprendiz(['documento' => (string) random_int(1000000000, 1999999999)] + $datos);
    }

    public function test_desactivar_sin_justificacion_lanza_error_de_negocio_con_el_texto_de_la_base(): void
    {
        $admin = $this->crearAdmin();
        $aprendiz = $this->crearAprendiz();

        try {
            $this->servicio($admin)->cambiarEstado($aprendiz, 'Inactivo', '   ');
            $this->fail('Debía lanzar ErrorDeNegocio.');
        } catch (ErrorDeNegocio $error) {
            $this->assertStringContainsString('justificaci', $error->getMessage()); // texto escrito por la base de datos
        }
        $this->assertSame('Activo', $this->filaDe($aprendiz)['estado']);
    }

    public function test_desactivar_y_activar_con_justificacion_cambia_el_estado(): void
    {
        $admin = $this->crearAdmin();
        $aprendiz = $this->crearAprendiz();

        $this->servicio($admin)->cambiarEstado($aprendiz, 'Inactivo', 'Se retiró del programa');
        $this->assertSame('Inactivo', $this->filaDe($aprendiz)['estado']);

        $this->servicio($admin)->cambiarEstado($aprendiz, 'Activo', 'Volvió a matricularse');
        $this->assertSame('Activo', $this->filaDe($aprendiz)['estado']);
    }

    public function test_desbloquear_es_pasar_a_activo(): void
    {
        $admin = $this->crearAdmin();
        $aprendiz = $this->crearAprendiz();
        // Se bloquea como lo hace el ingreso: 3 intentos fallidos con @app_id_usuario en NULL.
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario(null, '127.0.0.1');
        $repositorio = new RepositorioUsuarios($bd);
        for ($i = 0; $i < 3; $i++) {
            $repositorio->registrarIntentoFallido($aprendiz);
        }
        $this->assertSame('Bloqueado', $this->filaDe($aprendiz)['estado']);

        $this->servicio($admin)->cambiarEstado($aprendiz, 'Activo', 'El aprendiz llamó a Bienestar');

        $this->assertSame('Activo', $this->filaDe($aprendiz)['estado']);
    }

    public function test_solo_se_puede_pasar_a_activo_o_inactivo(): void
    {
        $admin = $this->crearAdmin();
        $aprendiz = $this->crearAprendiz();

        $this->expectException(ErrorDeNegocio::class);
        $this->servicio($admin)->cambiarEstado($aprendiz, 'Bloqueado', 'No se puede');
    }

    public function test_no_se_puede_desactivar_a_un_administrador_ni_a_uno_mismo(): void
    {
        $admin = $this->crearAdmin();

        try {
            $this->servicio($admin)->cambiarEstado($admin, 'Inactivo', 'Intento');
            $this->fail('Debía lanzar ErrorDeNegocio.');
        } catch (ErrorDeNegocio) {
            $this->assertSame('Activo', $this->filaDe($admin)['estado']);
        }
    }

    public function test_restablecer_devuelve_una_contrasena_nueva_y_exige_cambiarla(): void
    {
        $admin = $this->crearAdmin();
        $aprendiz = $this->crearAprendiz(); // ya había elegido su propia clave
        $antes = (string) $this->filaDe($aprendiz)['password_hash'];

        $nueva = $this->servicio($admin)->restablecerContrasena($aprendiz);

        $fila = $this->filaDe($aprendiz);
        $this->assertNotSame($antes, $fila['password_hash']);
        $this->assertTrue(password_verify($nueva, (string) $fila['password_hash']));
        $this->assertSame(1, (int) $fila['debe_cambiar_contrasena']);
    }

    public function test_restablecer_a_alguien_que_no_es_aprendiz_lanza_error_de_negocio(): void
    {
        $admin = $this->crearAdmin();
        $otroAdmin = $this->crearAdmin();

        $this->expectException(ErrorDeNegocio::class);
        $this->servicio($admin)->restablecerContrasena($otroAdmin);
    }

    // ---------- Pantallas (por el enrutador, como en el navegador) ----------

    private function fabrica(): Fabrica
    {
        return new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
    }

    /**
     * @param array<string, string> $campos   Datos del formulario (POST).
     * @param array<string, string> $consulta Datos de la URL (?q=...).
     */
    private function enviar(Fabrica $fabrica, string $metodo, string $ruta, array $campos = [], array $consulta = []): Respuesta
    {
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 2) . '/rutas.php')($enrutador, $fabrica);
        $aplicacion = new Aplicacion($enrutador, $fabrica->vista(), $this->configuracionDePrueba(), $fabrica);

        return $aplicacion->ejecutar(new Peticion($metodo, $ruta, $campos, [], '127.0.0.1', [], $consulta));
    }

    private function fabricaDeAdmin(): Fabrica
    {
        $fabrica = $this->fabrica();
        $fabrica->sesion()->iniciarPara($this->crearAdmin(), 'Administrador', false, 'Admin Prueba');

        return $fabrica;
    }

    public function test_la_lista_muestra_aprendices_y_el_buscador_filtra(): void
    {
        $this->crearAprendiz(['nombre' => 'Zulema', 'apellido' => 'Buscable', 'documento' => '5550001111']);
        $this->crearAprendiz(['nombre' => 'Otro', 'apellido' => 'Distinto', 'documento' => '5550002222']);
        $fabrica = $this->fabricaDeAdmin();

        $todos = $this->enviar($fabrica, 'GET', '/admin/usuarios');
        $this->assertSame(200, $todos->codigo());
        $this->assertStringContainsString('Zulema Buscable', $todos->cuerpo());
        $this->assertStringContainsString('Distinto', $todos->cuerpo());

        // La búsqueda viaja en la URL (?q=): el controlador la lee con Peticion::consulta().
        $this->assertStringNotContainsString('Distinto', $this->enviar($fabrica, 'GET', '/admin/usuarios', [], ['q' => 'Zulema'])->cuerpo());
        $this->assertStringContainsString('5550002222', $this->enviar($fabrica, 'GET', '/admin/usuarios', [], ['q' => '5550002222'])->cuerpo());
    }

    public function test_un_aprendiz_no_entra_a_usuarios(): void
    {
        $fabrica = $this->fabrica();
        $fabrica->sesion()->iniciarPara($this->crearAprendiz(), 'Aprendiz', false, 'Ana');

        $this->assertSame(403, $this->enviar($fabrica, 'GET', '/admin/usuarios')->codigo());
        $this->assertSame(403, $this->enviar($fabrica, 'POST', '/admin/usuarios/nuevo', ['_csrf' => Csrf::token()])->codigo());
    }

    public function test_sin_sesion_manda_a_ingresar(): void
    {
        $this->assertSame('/ingresar', $this->enviar($this->fabrica(), 'GET', '/admin/usuarios')->destino());
    }

    public function test_el_formulario_de_crear_se_muestra(): void
    {
        $respuesta = $this->enviar($this->fabricaDeAdmin(), 'GET', '/admin/usuarios/nuevo');

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('Crear una cuenta de aprendiz', $respuesta->cuerpo());
        $this->assertStringContainsString('name="_csrf"', $respuesta->cuerpo());
    }

    /** @return array<string, string> */
    private function camposDelFormulario(): array
    {
        $programas = (new RepositorioCatalogos($this->baseDatosDeLaApp()))->programas();

        return [
            'documento' => '1.0' . random_int(10000000, 99999999),
            'nombre' => 'Karen Dayana',
            'apellido' => 'Moreno Lozano',
            'correo' => 'karen' . bin2hex(random_bytes(4)) . '@Soy.Sena.edu.co',
            'programa' => (string) $programas[0]['nombre'],
            'ficha' => '2758341',
            '_csrf' => Csrf::token(),
        ];
    }

    public function test_crear_muestra_la_contrasena_una_sola_vez_y_guarda_la_entrega_en_la_sesion(): void
    {
        $fabrica = $this->fabricaDeAdmin();

        $respuesta = $this->enviar($fabrica, 'POST', '/admin/usuarios/nuevo', $this->camposDelFormulario());

        $this->assertSame(200, $respuesta->codigo());
        $pendiente = $_SESSION['entrega_pendiente'];
        $this->assertCount(1, $pendiente);
        $this->assertStringContainsString($pendiente[0]['contrasena'], $respuesta->cuerpo());
        $this->assertStringContainsString('Descargar Excel de entrega', $respuesta->cuerpo());
        $this->assertStringContainsString('lugar seguro', $respuesta->cuerpo());
        // La misma contraseña funciona contra el hash guardado.
        $hash = (string) $this->baseDatosDeLaApp()->pdo()->query("SELECT password_hash FROM usuarios WHERE correo = '" . strtolower($pendiente[0]['correo']) . "'")->fetchColumn();
        $this->assertTrue(password_verify($pendiente[0]['contrasena'], $hash));
    }

    public function test_crear_con_errores_vuelve_al_formulario_sin_crear_nada(): void
    {
        $campos = ['correo' => 'no-es-correo'] + $this->camposDelFormulario();

        $respuesta = $this->enviar($this->fabricaDeAdmin(), 'POST', '/admin/usuarios/nuevo', $campos);

        $this->assertStringContainsString('Revisa 1 cosa', $respuesta->cuerpo());
        $this->assertArrayNotHasKey('entrega_pendiente', $_SESSION);
    }

    public function test_crear_un_documento_repetido_muestra_el_aviso_en_el_formulario(): void
    {
        $fabrica = $this->fabricaDeAdmin();
        $campos = $this->camposDelFormulario();
        $this->enviar($fabrica, 'POST', '/admin/usuarios/nuevo', $campos);

        $respuesta = $this->enviar($fabrica, 'POST', '/admin/usuarios/nuevo', ['correo' => 'otro' . bin2hex(random_bytes(4)) . '@soy.sena.edu.co'] + $campos);

        $this->assertStringContainsString('Crear una cuenta de aprendiz', $respuesta->cuerpo());
        $this->assertStringContainsString('Ya existe una cuenta', $respuesta->cuerpo());
    }

    public function test_crear_sin_token_es_403(): void
    {
        $campos = $this->camposDelFormulario();
        unset($campos['_csrf']);

        $this->assertSame(403, $this->enviar($this->fabricaDeAdmin(), 'POST', '/admin/usuarios/nuevo', $campos)->codigo());
    }

    public function test_descargar_la_entrega_entrega_el_excel_y_borra_los_datos_de_la_sesion(): void
    {
        $fabrica = $this->fabricaDeAdmin();
        $this->enviar($fabrica, 'POST', '/admin/usuarios/nuevo', $this->camposDelFormulario());
        $contrasena = $_SESSION['entrega_pendiente'][0]['contrasena'];

        $respuesta = $this->enviar($fabrica, 'POST', '/admin/usuarios/entrega', ['_csrf' => Csrf::token()]);

        $ruta = (string) $respuesta->rutaArchivo();
        $this->assertSame(200, $respuesta->codigo());
        $this->assertFileExists($ruta);
        $this->assertSame($contrasena, IOFactory::load($ruta)->getActiveSheet()->getCell('D2')->getValue());
        $this->assertArrayNotHasKey('entrega_pendiente', $_SESSION);
        @unlink($ruta);
    }

    public function test_descargar_sin_nada_pendiente_vuelve_a_la_lista_con_un_aviso(): void
    {
        $respuesta = $this->enviar($this->fabricaDeAdmin(), 'POST', '/admin/usuarios/entrega', ['_csrf' => Csrf::token()]);

        $this->assertSame('/admin/usuarios', $respuesta->destino());
    }

    public function test_cambiar_estado_por_la_ruta_pide_justificacion(): void
    {
        $aprendiz = $this->crearAprendiz();
        $fabrica = $this->fabricaDeAdmin();

        $sin = $this->enviar($fabrica, 'POST', "/admin/usuarios/$aprendiz/estado", ['estado' => 'Inactivo', 'justificacion' => '', '_csrf' => Csrf::token()]);
        $this->assertSame('/admin/usuarios', $sin->destino());
        $this->assertSame('Activo', $this->filaDe($aprendiz)['estado']);

        $con = $this->enviar($fabrica, 'POST', "/admin/usuarios/$aprendiz/estado", ['estado' => 'Inactivo', 'justificacion' => 'Se retiró', '_csrf' => Csrf::token()]);
        $this->assertSame('/admin/usuarios', $con->destino());
        $this->assertSame('Inactivo', $this->filaDe($aprendiz)['estado']);
    }

    public function test_restablecer_por_la_ruta_muestra_la_contrasena_nueva(): void
    {
        $aprendiz = $this->crearAprendiz();
        $fabrica = $this->fabricaDeAdmin();

        $respuesta = $this->enviar($fabrica, 'POST', "/admin/usuarios/$aprendiz/restablecer", ['_csrf' => Csrf::token()]);

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString($_SESSION['entrega_pendiente'][0]['contrasena'], $respuesta->cuerpo());
        $this->assertSame(1, (int) $this->filaDe($aprendiz)['debe_cambiar_contrasena']);
    }
}
