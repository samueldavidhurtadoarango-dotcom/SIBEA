<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Controladores;

use BenefitRack\Controladores\AccesoControlador;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Sesion;
use BenefitRack\Nucleo\Vista;
use BenefitRack\Repositorios\RepositorioUsuarios;
use BenefitRack\Servicios\ServicioAcceso;
use PHPUnit\Framework\TestCase;

/** Pantalla de ingreso: lo que se decide sin tocar la base de datos. */
final class AccesoControladorTest extends TestCase
{
    private Sesion $sesion;
    private AccesoControlador $controlador;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->sesion = new Sesion();
        $repositorio = $this->createMock(RepositorioUsuarios::class);
        $repositorio->expects($this->never())->method('buscarPorCorreo'); // la validación corta antes de la base
        $this->controlador = new AccesoControlador(
            $this->sesion,
            new Vista(dirname(__DIR__, 3) . '/vistas', $this->sesion),
            new ServicioAcceso($repositorio)
        );
    }

    /** @param array<string, string> $campos */
    private function post(array $campos): Peticion
    {
        return new Peticion('POST', '/ingresar', $campos, [], '127.0.0.1');
    }

    public function test_sin_sesion_el_inicio_manda_a_ingresar(): void
    {
        $respuesta = $this->controlador->inicio(new Peticion('GET', '/', [], [], '127.0.0.1'));

        $this->assertSame('/ingresar', $respuesta->destino());
    }

    public function test_el_inicio_manda_a_cada_rol_a_su_pantalla(): void
    {
        $peticion = new Peticion('GET', '/', [], [], '127.0.0.1');
        $this->sesion->iniciarPara(1, 'Administrador', false);
        $this->assertSame('/admin/postulaciones', $this->controlador->inicio($peticion)->destino());

        $this->sesion->iniciarPara(2, 'Aprendiz', false);
        $this->assertSame('/mi-proceso', $this->controlador->inicio($peticion)->destino());
    }

    public function test_el_formulario_se_dibuja_con_el_token_csrf(): void
    {
        $respuesta = $this->controlador->formulario(new Peticion('GET', '/ingresar', [], [], '127.0.0.1'));

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('name="_csrf"', $respuesta->cuerpo());
        $this->assertStringContainsString('Inicia sesión', $respuesta->cuerpo());
    }

    public function test_un_correo_mal_escrito_muestra_la_validacion_y_conserva_lo_escrito(): void
    {
        $respuesta = $this->controlador->ingresar($this->post(['correo' => 'valentina@soy', 'clave' => 'x']));

        $this->assertStringContainsString('Revisa tu correo', $respuesta->cuerpo());
        $this->assertStringContainsString('value="valentina@soy"', $respuesta->cuerpo());
    }

    public function test_la_clave_vacia_muestra_la_validacion(): void
    {
        $respuesta = $this->controlador->ingresar($this->post(['correo' => 'ana@soy.sena.edu.co', 'clave' => '']));

        $this->assertStringContainsString('Escribe tu contraseña para continuar.', $respuesta->cuerpo());
    }

    public function test_un_correo_con_comillas_se_escapa_en_la_pantalla(): void
    {
        $respuesta = $this->controlador->ingresar($this->post(['correo' => '"><script>alert(1)</script>', 'clave' => '']));

        $this->assertStringNotContainsString('<script>alert(1)', $respuesta->cuerpo());
    }
}
