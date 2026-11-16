<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Nucleo;

use PHPUnit\Framework\TestCase;
use SIBEA\Nucleo\Sesion;

final class SesionTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function test_sin_iniciar_sesion_no_hay_usuario(): void
    {
        $sesion = new Sesion();

        $this->assertNull($sesion->idUsuario());
        $this->assertNull($sesion->rol());
        $this->assertFalse($sesion->debeCambiarContrasena());
    }

    public function test_iniciar_para_guarda_los_datos_del_usuario(): void
    {
        $sesion = new Sesion();

        $sesion->iniciarPara(7, 'Aprendiz', true, 'Luisa');

        $this->assertSame(7, $sesion->idUsuario());
        $this->assertSame('Aprendiz', $sesion->rol());
        $this->assertSame('Luisa', $sesion->nombre());
        $this->assertTrue($sesion->debeCambiarContrasena());
    }

    public function test_marcar_contrasena_cambiada_baja_la_bandera(): void
    {
        $sesion = new Sesion();
        $sesion->iniciarPara(7, 'Aprendiz', true);

        $sesion->marcarContrasenaCambiada();

        $this->assertFalse($sesion->debeCambiarContrasena());
    }

    public function test_iniciar_para_renueva_el_token_csrf(): void
    {
        $_SESSION['_csrf'] = 'token-anonimo';

        (new Sesion())->iniciarPara(7, 'Aprendiz', false);

        $this->assertArrayNotHasKey('_csrf', $_SESSION);
    }

    public function test_la_pantalla_de_inicio_depende_del_rol(): void
    {
        $sesion = new Sesion();
        $this->assertSame('/ingresar', $sesion->rutaDeInicio());

        $sesion->iniciarPara(1, 'Administrador', false);
        $this->assertSame('/admin/postulaciones', $sesion->rutaDeInicio());

        $sesion->iniciarPara(2, 'Aprendiz', false);
        $this->assertSame('/mi-proceso', $sesion->rutaDeInicio());
    }

    public function test_cerrar_borra_todo(): void
    {
        $sesion = new Sesion();
        $sesion->iniciarPara(7, 'Administrador', false);

        $sesion->cerrar();

        $this->assertNull($sesion->idUsuario());
        $this->assertSame([], $_SESSION);
    }

    public function test_los_mensajes_se_muestran_una_sola_vez(): void
    {
        $sesion = new Sesion();
        $sesion->mensaje('exito', 'Guardado');
        $sesion->mensaje('error', 'Algo falló');

        $primera = $sesion->tomarMensajes();
        $segunda = $sesion->tomarMensajes();

        $this->assertSame([['tipo' => 'exito', 'texto' => 'Guardado'], ['tipo' => 'error', 'texto' => 'Algo falló']], $primera);
        $this->assertSame([], $segunda);
    }
}
