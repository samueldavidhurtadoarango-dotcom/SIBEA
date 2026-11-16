<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Integracion;

use PDO;
use SIBEA\Nucleo\Aplicacion;
use SIBEA\Nucleo\Csrf;
use SIBEA\Nucleo\Enrutador;
use SIBEA\Nucleo\Fabrica;
use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Respuesta;
use SIBEA\Repositorios\RepositorioUsuarios;
use SIBEA\Servicios\ServicioAcceso;

/** CA-1: bloqueo a los 3 intentos y desbloqueo a los 15 minutos, más el recorrido completo por HTTP. */
final class AccesoTest extends BaseDePrueba
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    private function servicio(): ServicioAcceso
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario(null, '127.0.0.1'); // como lo hace Fabrica::baseDatosParaIngreso()

        return new ServicioAcceso(new RepositorioUsuarios($bd));
    }

    private function correoDe(int $id): string
    {
        return (string) $this->baseDatosDeLaApp()->pdo()
            ->query("SELECT correo FROM usuarios WHERE id_usuario = $id")->fetchColumn();
    }

    private function estadoDe(int $id): string
    {
        return (string) $this->baseDatosDeLaApp()->pdo()
            ->query("SELECT estado FROM usuarios WHERE id_usuario = $id")->fetchColumn();
    }

    private function pdoRaiz(): PDO
    {
        return new PDO('mysql:host=127.0.0.1;port=3398;dbname=sibea;charset=utf8mb4', 'root', (string) getenv('BR_PRUEBA_BD_ROOT_CLAVE'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    public function test_tres_claves_erradas_bloquean_y_la_cuarta_peticion_con_clave_correcta_sigue_bloqueada(): void
    {
        $id = $this->crearAprendiz();
        $correo = $this->correoDe($id);
        $servicio = $this->servicio();

        $this->assertSame('datos-incorrectos', $servicio->ingresar($correo, 'mala-1')->estadoVista);
        $this->assertSame('datos-incorrectos', $servicio->ingresar($correo, 'mala-2')->estadoVista);
        $this->assertSame('cuenta-bloqueada', $servicio->ingresar($correo, 'mala-3')->estadoVista);

        $this->assertSame('Bloqueado', $this->estadoDe($id));
        $this->assertSame('cuenta-bloqueada', $servicio->ingresar($correo, self::CLAVE_PRUEBA)->estadoVista);
    }

    public function test_pasados_16_minutos_la_clave_correcta_vuelve_a_entrar(): void
    {
        $id = $this->crearAprendiz();
        $correo = $this->correoDe($id);
        $servicio = $this->servicio();
        foreach (['a', 'b', 'c'] as $mala) {
            $servicio->ingresar($correo, $mala);
        }
        $this->assertSame('Bloqueado', $this->estadoDe($id));

        // Se "envejece" el bloqueo como root (mantenimiento), igual que haría el paso del tiempo.
        $raiz = $this->pdoRaiz();
        $raiz->exec('SET @app_id_usuario = NULL');
        $raiz->exec("UPDATE usuarios SET fecha_bloqueo = SYSDATE() - INTERVAL 16 MINUTE WHERE id_usuario = $id");

        $resultado = $servicio->ingresar($correo, self::CLAVE_PRUEBA);

        $this->assertTrue($resultado->exito);
        $this->assertSame('Activo', $this->estadoDe($id));
    }

    public function test_antes_de_los_15_minutos_sigue_bloqueada(): void
    {
        $id = $this->crearAprendiz();
        $correo = $this->correoDe($id);
        $servicio = $this->servicio();
        foreach (['a', 'b', 'c'] as $mala) {
            $servicio->ingresar($correo, $mala);
        }

        $this->assertSame('cuenta-bloqueada', $servicio->ingresar($correo, self::CLAVE_PRUEBA)->estadoVista);
    }

    public function test_un_ingreso_correcto_reinicia_los_intentos_fallidos(): void
    {
        $id = $this->crearAprendiz();
        $correo = $this->correoDe($id);
        $servicio = $this->servicio();
        $servicio->ingresar($correo, 'mala');

        $this->assertTrue($servicio->ingresar($correo, self::CLAVE_PRUEBA)->exito);
        $intentos = $this->baseDatosDeLaApp()->pdo()->query("SELECT intentos_fallidos FROM usuarios WHERE id_usuario = $id")->fetchColumn();
        $this->assertSame(0, (int) $intentos);
    }

    public function test_buscar_por_correo_trae_el_rol_y_la_marca_de_primer_ingreso(): void
    {
        $id = $this->crearAdmin();
        $usuario = (new RepositorioUsuarios($this->baseDatosDeLaApp()))->buscarPorCorreo(strtoupper($this->correoDe($id)));

        $this->assertNotNull($usuario);
        $this->assertSame('Administrador', $usuario['rol']);
        $this->assertSame($id, (int) $usuario['id_usuario']);
        $this->assertSame(0, (int) $usuario['debe_cambiar_contrasena']);
    }

    // ---------- Recorrido completo por Aplicacion (rutas, filtros, controlador, sesión) ----------

    private function fabrica(): Fabrica
    {
        return new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
    }

    /** @param array<string, string> $campos */
    private function enviar(Fabrica $fabrica, string $metodo, string $ruta, array $campos = []): Respuesta
    {
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 2) . '/rutas.php')($enrutador, $fabrica);
        $aplicacion = new Aplicacion($enrutador, $fabrica->vista(), $this->configuracionDePrueba(), $fabrica);

        return $aplicacion->ejecutar(new Peticion($metodo, $ruta, $campos, [], '127.0.0.1'));
    }

    public function test_un_administrador_entra_y_va_a_postulaciones(): void
    {
        $id = $this->crearAdmin();
        $fabrica = $this->fabrica();

        $respuesta = $this->enviar($fabrica, 'POST', '/ingresar', ['correo' => $this->correoDe($id), 'clave' => self::CLAVE_PRUEBA, '_csrf' => Csrf::token()]);

        $this->assertSame('/admin/postulaciones', $respuesta->destino());
        $this->assertSame($id, $fabrica->sesion()->idUsuario());
        $this->assertSame('Administrador', $fabrica->sesion()->rol());
    }

    public function test_un_aprendiz_entra_y_va_a_mi_proceso(): void
    {
        $id = $this->crearAprendiz();

        $respuesta = $this->enviar($this->fabrica(), 'POST', '/ingresar', ['correo' => $this->correoDe($id), 'clave' => self::CLAVE_PRUEBA, '_csrf' => Csrf::token()]);

        $this->assertSame('/mi-proceso', $respuesta->destino());
    }

    public function test_con_contrasena_temporal_va_a_cambiarla(): void
    {
        $id = $this->crearAprendiz(['debe_cambiar_contrasena' => 1]);
        $fabrica = $this->fabrica();

        $respuesta = $this->enviar($fabrica, 'POST', '/ingresar', ['correo' => $this->correoDe($id), 'clave' => self::CLAVE_PRUEBA, '_csrf' => Csrf::token()]);

        $this->assertSame('/contrasena/nueva', $respuesta->destino());
        $this->assertTrue($fabrica->sesion()->debeCambiarContrasena());
    }

    public function test_una_clave_errada_muestra_el_formulario_con_el_estado_y_conserva_el_correo(): void
    {
        $id = $this->crearAprendiz();
        $correo = $this->correoDe($id);

        $respuesta = $this->enviar($this->fabrica(), 'POST', '/ingresar', ['correo' => $correo, 'clave' => 'mala', '_csrf' => Csrf::token()]);

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('Correo o contraseña incorrectos', $respuesta->cuerpo());
        $this->assertStringContainsString('value="' . $correo . '"', $respuesta->cuerpo());
    }

    public function test_el_ingreso_con_otra_sesion_abierta_igual_bloquea_a_los_tres_intentos(): void
    {
        // Dos pestañas: ya hay una sesión de otra persona y alguien falla 3 veces en esta.
        $otro = $this->crearAdmin();
        $id = $this->crearAprendiz();
        $correo = $this->correoDe($id);
        $fabrica = $this->fabrica();
        $fabrica->sesion()->iniciarPara($otro, 'Administrador', false);

        foreach (['a', 'b', 'c'] as $mala) {
            $this->enviar($fabrica, 'POST', '/ingresar', ['correo' => $correo, 'clave' => $mala, '_csrf' => Csrf::token()]);
        }

        $this->assertSame('Bloqueado', $this->estadoDe($id));
    }

    public function test_salir_cierra_la_sesion_y_vuelve_a_ingresar(): void
    {
        $fabrica = $this->fabrica();
        $id = $this->crearAprendiz(); // la sesión se revalida contra la base: el usuario debe existir
        $fabrica->sesion()->iniciarPara($id, 'Aprendiz', false);

        $respuesta = $this->enviar($fabrica, 'POST', '/salir', ['_csrf' => Csrf::token()]);

        $this->assertSame('/ingresar', $respuesta->destino());
        $this->assertNull($fabrica->sesion()->idUsuario());
    }

    public function test_salir_sin_token_es_403_y_no_cierra_la_sesion(): void
    {
        $fabrica = $this->fabrica();
        $id = $this->crearAprendiz(); // la sesión se revalida contra la base: el usuario debe existir
        $fabrica->sesion()->iniciarPara($id, 'Aprendiz', false);

        $respuesta = $this->enviar($fabrica, 'POST', '/salir');

        $this->assertSame(403, $respuesta->codigo());
        $this->assertSame($id, $fabrica->sesion()->idUsuario());
    }

    public function test_un_post_a_ingresar_con_la_sesion_vencida_vuelve_al_formulario_con_aviso(): void
    {
        $fabrica = $this->fabrica();

        $respuesta = $this->enviar($fabrica, 'POST', '/ingresar', ['correo' => 'a@b.co', 'clave' => 'x', '_csrf' => 'token-viejo']);

        $this->assertSame('/ingresar', $respuesta->destino());
        $this->assertSame('Tu sesión terminó. Vuelve a intentarlo.', $fabrica->sesion()->tomarMensajes()[0]['texto']);
    }
}
