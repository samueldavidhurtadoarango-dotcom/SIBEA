<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Filtros;

use PHPUnit\Framework\TestCase;
use SIBEA\Filtros\FiltroCsrf;
use SIBEA\Filtros\FiltroPrimerIngreso;
use SIBEA\Filtros\FiltroRol;
use SIBEA\Filtros\FiltroSesion;
use SIBEA\Nucleo\Aplicacion;
use SIBEA\Nucleo\Configuracion;
use SIBEA\Nucleo\Csrf;
use SIBEA\Nucleo\Enrutador;
use SIBEA\Nucleo\Fabrica;
use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Respuesta;
use SIBEA\Nucleo\Sesion;
use SIBEA\Nucleo\Vista;
use SIBEA\Repositorios\RepositorioUsuarios;

final class FiltrosTest extends TestCase
{
    private Sesion $sesion;
    private Vista $vista;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->sesion = new Sesion();
        $this->vista = new Vista(dirname(__DIR__, 3) . '/vistas', $this->sesion);
    }

    /** @param array<string, mixed> $campos */
    private function peticion(string $metodo, string $ruta, array $campos = []): Peticion
    {
        return new Peticion($metodo, $ruta, $campos, [], '127.0.0.1');
    }

    /** El filtro de sesión con un repositorio falso que dice que la persona sigue activa y con su rol. */
    private function filtroSesion(): FiltroSesion
    {
        $repositorio = $this->createMock(RepositorioUsuarios::class);
        $repositorio->method('estadoParaSesion')->willReturn(['estado' => 'Activo', 'rol' => 'Aprendiz', 'debe_cambiar_contrasena' => 0]);

        return new FiltroSesion($this->sesion, fn (): RepositorioUsuarios => $repositorio);
    }

    public function test_sin_sesion_redirige_a_ingresar_con_mensaje(): void
    {
        $respuesta = $this->filtroSesion()->aplicar($this->peticion('GET', '/mi-proceso'));

        $this->assertNotNull($respuesta);
        $this->assertSame('/ingresar', $respuesta->destino());
        $mensajes = $this->sesion->tomarMensajes();
        $this->assertStringContainsString('Tu sesión terminó', $mensajes[0]['texto']);
    }

    public function test_con_sesion_deja_pasar(): void
    {
        $this->sesion->iniciarPara(3, 'Aprendiz', false);

        $this->assertNull($this->filtroSesion()->aplicar($this->peticion('GET', '/mi-proceso')));
    }

    public function test_un_aprendiz_en_ruta_de_admin_recibe_403(): void
    {
        $this->sesion->iniciarPara(3, 'Aprendiz', false);

        $respuesta = (new FiltroRol($this->sesion, $this->vista, 'Administrador'))->aplicar($this->peticion('GET', '/admin/usuarios'));

        $this->assertNotNull($respuesta);
        $this->assertSame(403, $respuesta->codigo());
    }

    public function test_el_rol_correcto_deja_pasar(): void
    {
        $this->sesion->iniciarPara(1, 'Administrador', false);

        $this->assertNull((new FiltroRol($this->sesion, $this->vista, 'Administrador'))->aplicar($this->peticion('GET', '/admin/usuarios')));
    }

    public function test_un_post_sin_token_recibe_403_y_no_llega_al_controlador(): void
    {
        $llamado = false;
        $enrutador = new Enrutador();
        $enrutador->agregar('POST', '/cambia', static function () use (&$llamado): Respuesta {
            $llamado = true;

            return Respuesta::html('hecho');
        }, [new FiltroCsrf($this->vista)]);
        $fabrica = new Fabrica(new Configuracion([]), dirname(__DIR__, 3) . '/vistas', '127.0.0.1');
        $aplicacion = new Aplicacion($enrutador, $this->vista, new Configuracion([]), $fabrica);

        $respuesta = $aplicacion->ejecutar($this->peticion('POST', '/cambia'));

        $this->assertSame(403, $respuesta->codigo());
        $this->assertFalse($llamado);
    }

    public function test_un_post_a_ingresar_sin_token_vuelve_al_formulario_con_aviso_en_vez_de_403(): void
    {
        $filtro = new FiltroCsrf($this->vista, $this->sesion);

        $respuesta = $filtro->aplicar($this->peticion('POST', '/ingresar', ['_csrf' => 'viejo']));

        $this->assertNotNull($respuesta);
        $this->assertSame('/ingresar', $respuesta->destino());
        $this->assertSame('Tu sesión terminó. Vuelve a intentarlo.', $this->sesion->tomarMensajes()[0]['texto']);
    }

    public function test_en_otra_ruta_el_token_invalido_sigue_siendo_403_aunque_haya_sesion(): void
    {
        $filtro = new FiltroCsrf($this->vista, $this->sesion);

        $respuesta = $filtro->aplicar($this->peticion('POST', '/salir', ['_csrf' => 'viejo']));

        $this->assertNotNull($respuesta);
        $this->assertSame(403, $respuesta->codigo());
    }

    public function test_un_post_con_token_falso_recibe_403(): void
    {
        Csrf::token();

        $respuesta = (new FiltroCsrf($this->vista))->aplicar($this->peticion('POST', '/cambia', ['_csrf' => 'falso']));

        $this->assertNotNull($respuesta);
        $this->assertSame(403, $respuesta->codigo());
    }

    public function test_un_post_con_token_correcto_pasa(): void
    {
        $token = Csrf::token();

        $this->assertNull((new FiltroCsrf($this->vista))->aplicar($this->peticion('POST', '/cambia', ['_csrf' => $token])));
    }

    public function test_un_get_no_necesita_token(): void
    {
        $this->assertNull((new FiltroCsrf($this->vista))->aplicar($this->peticion('GET', '/lista')));
    }

    public function test_con_debe_cambiar_redirige_a_contrasena_nueva(): void
    {
        $this->sesion->iniciarPara(3, 'Aprendiz', true);

        $respuesta = (new FiltroPrimerIngreso($this->sesion))->aplicar($this->peticion('GET', '/mi-proceso'));

        $this->assertNotNull($respuesta);
        $this->assertSame('/contrasena/nueva', $respuesta->destino());
    }

    public function test_sin_debe_cambiar_deja_pasar(): void
    {
        $this->sesion->iniciarPara(3, 'Aprendiz', false);

        $this->assertNull((new FiltroPrimerIngreso($this->sesion))->aplicar($this->peticion('GET', '/mi-proceso')));
    }
}
