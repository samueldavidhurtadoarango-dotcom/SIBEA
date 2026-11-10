<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Filtros;

use BenefitRack\Filtros\FiltroCsrf;
use BenefitRack\Filtros\FiltroPrimerIngreso;
use BenefitRack\Filtros\FiltroRol;
use BenefitRack\Filtros\FiltroSesion;
use BenefitRack\Nucleo\Aplicacion;
use BenefitRack\Nucleo\Configuracion;
use BenefitRack\Nucleo\Csrf;
use BenefitRack\Nucleo\Enrutador;
use BenefitRack\Nucleo\Fabrica;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Nucleo\Sesion;
use BenefitRack\Nucleo\Vista;
use PHPUnit\Framework\TestCase;

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

    public function test_sin_sesion_redirige_a_ingresar_con_mensaje(): void
    {
        $respuesta = (new FiltroSesion($this->sesion))->aplicar($this->peticion('GET', '/mi-proceso'));

        $this->assertNotNull($respuesta);
        $this->assertSame('/ingresar', $respuesta->destino());
        $mensajes = $this->sesion->tomarMensajes();
        $this->assertStringContainsString('Tu sesión terminó', $mensajes[0]['texto']);
    }

    public function test_con_sesion_deja_pasar(): void
    {
        $this->sesion->iniciarPara(3, 'Aprendiz', false);

        $this->assertNull((new FiltroSesion($this->sesion))->aplicar($this->peticion('GET', '/mi-proceso')));
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
