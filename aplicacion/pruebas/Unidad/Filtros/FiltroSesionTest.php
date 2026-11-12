<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Filtros;

use BenefitRack\Filtros\FiltroSesion;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Sesion;
use BenefitRack\Repositorios\RepositorioUsuarios;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** M1 y B1: el filtro de sesión revalida contra la base (repositorio falso) y aplica la expiración. */
final class FiltroSesionTest extends TestCase
{
    private const AVISO_CUENTA = 'Tu cuenta no está activa. Habla con el administrador.';
    private const AVISO_EXPIRO = 'Tu sesión expiró por seguridad. Vuelve a ingresar.';

    private int $ahora = 2_000_000;
    private Sesion $sesion;
    private RepositorioUsuarios&MockObject $repositorio;
    private FiltroSesion $filtro;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->sesion = new Sesion(fn (): int => $this->ahora);
        $this->sesion->iniciarPara(7, 'Aprendiz', false, 'Luisa');
        $this->repositorio = $this->createMock(RepositorioUsuarios::class);
        $this->filtro = new FiltroSesion($this->sesion, fn (): RepositorioUsuarios => $this->repositorio, 30, 8);
    }

    private function peticion(): Peticion
    {
        return new Peticion('GET', '/mi-proceso', [], [], '127.0.0.1');
    }

    /** @param array<string, mixed> $cambios */
    private function estado(array $cambios = []): void
    {
        $this->repositorio->method('estadoParaSesion')->with(7)->willReturn($cambios + ['estado' => 'Activo', 'rol' => 'Aprendiz', 'debe_cambiar_contrasena' => 0]);
    }

    public function test_un_usuario_activo_con_el_mismo_rol_pasa(): void
    {
        $this->estado();

        $this->assertNull($this->filtro->aplicar($this->peticion()));
        $this->assertSame(7, $this->sesion->idUsuario());
    }

    public function test_un_usuario_desactivado_pierde_la_sesion_con_aviso(): void
    {
        $this->estado(['estado' => 'Inactivo']);

        $respuesta = $this->filtro->aplicar($this->peticion());

        $this->assertNotNull($respuesta);
        $this->assertSame('/ingresar', $respuesta->destino());
        $this->assertNull($this->sesion->idUsuario());
        $this->assertSame(self::AVISO_CUENTA, $this->sesion->tomarMensajes()[0]['texto']);
    }

    public function test_un_usuario_bloqueado_pierde_la_sesion(): void
    {
        $this->estado(['estado' => 'Bloqueado']);

        $this->assertSame('/ingresar', $this->filtro->aplicar($this->peticion())?->destino());
        $this->assertNull($this->sesion->idUsuario());
    }

    public function test_un_usuario_que_ya_no_existe_pierde_la_sesion(): void
    {
        $this->repositorio->method('estadoParaSesion')->willReturn(null);

        $respuesta = $this->filtro->aplicar($this->peticion());

        $this->assertSame('/ingresar', $respuesta?->destino());
        $this->assertNull($this->sesion->idUsuario());
        $this->assertSame(self::AVISO_CUENTA, $this->sesion->tomarMensajes()[0]['texto']);
    }

    public function test_un_cambio_de_rol_cierra_la_sesion(): void
    {
        $this->estado(['rol' => 'Administrador']);

        $respuesta = $this->filtro->aplicar($this->peticion());

        $this->assertSame('/ingresar', $respuesta?->destino());
        $this->assertNull($this->sesion->rol());
        $this->assertSame(self::AVISO_CUENTA, $this->sesion->tomarMensajes()[0]['texto']);
    }

    public function test_si_la_base_dice_que_debe_cambiar_la_contrasena_se_actualiza_la_sesion(): void
    {
        $this->estado(['debe_cambiar_contrasena' => 1]);

        $this->assertNull($this->filtro->aplicar($this->peticion()));
        $this->assertTrue($this->sesion->debeCambiarContrasena());
    }

    public function test_si_la_base_dice_que_ya_no_debe_cambiar_se_actualiza_la_sesion(): void
    {
        $this->sesion->fijarDebeCambiar(true);
        $this->estado(['debe_cambiar_contrasena' => 0]);

        $this->assertNull($this->filtro->aplicar($this->peticion()));
        $this->assertFalse($this->sesion->debeCambiarContrasena());
    }

    public function test_pasados_30_minutos_sin_actividad_expira_sin_consultar_la_base(): void
    {
        $this->repositorio->expects($this->never())->method('estadoParaSesion');
        $this->ahora += 30 * 60 + 1;

        $respuesta = $this->filtro->aplicar($this->peticion());

        $this->assertSame('/ingresar', $respuesta?->destino());
        $this->assertNull($this->sesion->idUsuario());
        $this->assertSame(self::AVISO_EXPIRO, $this->sesion->tomarMensajes()[0]['texto']);
    }

    public function test_cada_peticion_valida_reinicia_la_inactividad(): void
    {
        $this->estado();
        $this->ahora += 20 * 60;
        $this->assertNull($this->filtro->aplicar($this->peticion()));
        $this->ahora += 20 * 60;

        $this->assertNull($this->filtro->aplicar($this->peticion()));
    }

    public function test_pasadas_8_horas_expira_aunque_haya_actividad(): void
    {
        $this->estado();
        for ($i = 0; $i < 49; $i++) {
            $this->ahora += 10 * 60;
            $respuesta = $this->filtro->aplicar($this->peticion());
        }

        $this->assertSame('/ingresar', $respuesta?->destino());
        $this->assertSame(self::AVISO_EXPIRO, $this->sesion->tomarMensajes()[0]['texto']);
    }

    public function test_sin_sesion_sigue_mandando_a_ingresar_sin_consultar_la_base(): void
    {
        $_SESSION = [];
        $this->repositorio->expects($this->never())->method('estadoParaSesion');

        $respuesta = $this->filtro->aplicar($this->peticion());

        $this->assertSame('/ingresar', $respuesta?->destino());
        $this->assertStringContainsString('Tu sesión terminó', $this->sesion->tomarMensajes()[0]['texto']);
    }
}
