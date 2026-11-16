<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Servicios;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SIBEA\Repositorios\RepositorioUsuarios;
use SIBEA\Servicios\ServicioAcceso;

/** Reglas de inicio de sesión con un repositorio falso (sin base de datos). */
final class ServicioAccesoTest extends TestCase
{
    private const CLAVE = 'Clave-correcta-1';

    private RepositorioUsuarios&MockObject $repositorio;
    private ServicioAcceso $servicio;

    protected function setUp(): void
    {
        $this->repositorio = $this->createMock(RepositorioUsuarios::class);
        $this->servicio = new ServicioAcceso($this->repositorio);
    }

    /**
     * @param array<string, mixed> $cambios
     * @return array<string, mixed>
     */
    private function usuario(array $cambios = []): array
    {
        return $cambios + [
            'id_usuario' => 7,
            'nombre' => 'Ana',
            'password_hash' => password_hash(self::CLAVE, PASSWORD_DEFAULT),
            'estado' => 'Activo',
            'rol' => 'Aprendiz',
            'intentos_fallidos' => 0,
            'fecha_bloqueo' => null,
            'debe_cambiar_contrasena' => 0,
        ];
    }

    public function test_con_la_clave_correcta_entra_y_reinicia_los_intentos(): void
    {
        $this->repositorio->method('buscarPorCorreo')->willReturn($this->usuario(['intentos_fallidos' => 2]));
        $this->repositorio->expects($this->once())->method('reiniciarIntentos')->with(7);

        $resultado = $this->servicio->ingresar('ana@ejemplo.test', self::CLAVE);

        $this->assertTrue($resultado->exito);
        $this->assertSame(7, $resultado->idUsuario);
        $this->assertSame('Aprendiz', $resultado->rol);
        $this->assertFalse($resultado->debeCambiar);
    }

    public function test_informa_si_debe_cambiar_la_contrasena(): void
    {
        $this->repositorio->method('buscarPorCorreo')->willReturn($this->usuario(['debe_cambiar_contrasena' => 1]));

        $this->assertTrue($this->servicio->ingresar('ana@ejemplo.test', self::CLAVE)->debeCambiar);
    }

    public function test_con_la_clave_errada_dice_datos_incorrectos_y_registra_el_intento(): void
    {
        $this->repositorio->method('buscarPorCorreo')->willReturn($this->usuario());
        $this->repositorio->expects($this->once())->method('registrarIntentoFallido')->with(7);

        $resultado = $this->servicio->ingresar('ana@ejemplo.test', 'otra-clave');

        $this->assertFalse($resultado->exito);
        $this->assertSame('datos-incorrectos', $resultado->estadoVista);
    }

    public function test_si_el_intento_fallido_la_bloquea_dice_cuenta_bloqueada(): void
    {
        $this->repositorio->method('buscarPorCorreo')->willReturnOnConsecutiveCalls(
            $this->usuario(['intentos_fallidos' => 2]),
            $this->usuario(['intentos_fallidos' => 3, 'estado' => 'Bloqueado'])
        );

        $this->assertSame('cuenta-bloqueada', $this->servicio->ingresar('ana@ejemplo.test', 'otra-clave')->estadoVista);
    }

    public function test_una_cuenta_inactiva_no_entra_aunque_la_clave_sea_correcta(): void
    {
        $this->repositorio->method('buscarPorCorreo')->willReturn($this->usuario(['estado' => 'Inactivo']));
        $this->repositorio->expects($this->never())->method('reiniciarIntentos');

        $this->assertSame('cuenta-inactiva', $this->servicio->ingresar('ana@ejemplo.test', self::CLAVE)->estadoVista);
    }

    public function test_una_cuenta_inactiva_con_clave_errada_no_delata_su_estado(): void
    {
        $this->repositorio->method('buscarPorCorreo')->willReturn($this->usuario(['estado' => 'Inactivo']));

        $this->assertSame('datos-incorrectos', $this->servicio->ingresar('ana@ejemplo.test', 'otra-clave')->estadoVista);
    }

    public function test_un_correo_inexistente_responde_igual_que_una_clave_errada(): void
    {
        $this->repositorio->method('buscarPorCorreo')->willReturn(null);
        $this->repositorio->expects($this->never())->method('registrarIntentoFallido');

        $resultado = $this->servicio->ingresar('nadie@ejemplo.test', self::CLAVE);

        $this->assertFalse($resultado->exito);
        $this->assertSame('datos-incorrectos', $resultado->estadoVista);
    }

    public function test_el_correo_se_busca_sin_mayusculas_ni_espacios(): void
    {
        $this->repositorio->expects($this->once())->method('buscarPorCorreo')->with('ana@ejemplo.test')->willReturn(null);

        $this->servicio->ingresar('  ANA@Ejemplo.TEST ', self::CLAVE);
    }

    public function test_una_cuenta_bloqueada_no_entra_aunque_la_clave_sea_correcta(): void
    {
        $this->repositorio->method('buscarPorCorreo')->willReturn($this->usuario(['estado' => 'Bloqueado']));
        $this->repositorio->method('desbloquearSiPasaronQuinceMinutos')->willReturn(false);

        $this->assertSame('cuenta-bloqueada', $this->servicio->ingresar('ana@ejemplo.test', self::CLAVE)->estadoVista);
    }
}
