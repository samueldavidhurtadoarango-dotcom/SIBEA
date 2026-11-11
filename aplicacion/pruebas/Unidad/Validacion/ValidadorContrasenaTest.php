<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Validacion;

use BenefitRack\Validacion\ResultadoValidacion;
use BenefitRack\Validacion\ValidadorContrasena;
use PHPUnit\Framework\TestCase;

/** Reglas de la contraseña nueva (maqueta 02-contrasena/primer-ingreso). */
final class ValidadorContrasenaTest extends TestCase
{
    private function validar(string $nueva, string $confirmacion): ResultadoValidacion
    {
        return (new ValidadorContrasena())->validar($nueva, $confirmacion);
    }

    public function test_una_contrasena_buena_pasa(): void
    {
        $resultado = $this->validar('Bienestar2026', 'Bienestar2026');

        $this->assertTrue($resultado->esValido());
        $this->assertSame([], $resultado->errores());
        $this->assertSame(['nueva' => 'Bienestar2026'], $resultado->datos());
    }

    public function test_una_contrasena_corta_falla(): void
    {
        $resultado = $this->validar('abc', 'abc');

        $this->assertFalse($resultado->esValido());
        $this->assertStringContainsString('mínimo 8 caracteres', $resultado->errores()['nueva']);
    }

    public function test_sin_numero_falla(): void
    {
        $resultado = $this->validar('abcdefgh', 'abcdefgh');

        $this->assertFalse($resultado->esValido());
        $this->assertStringContainsString('un número', $resultado->errores()['nueva']);
    }

    public function test_sin_mayuscula_falla_con_el_mensaje_de_la_maqueta(): void
    {
        $resultado = $this->validar('luisa1234', 'luisa1234');

        $this->assertSame('Aún no cumple todas las reglas: le falta una letra mayúscula.', $resultado->errores()['nueva']);
    }

    public function test_sin_minuscula_falla(): void
    {
        $resultado = $this->validar('LUISA1234', 'LUISA1234');

        $this->assertStringContainsString('una letra minúscula', $resultado->errores()['nueva']);
    }

    public function test_dos_contrasenas_distintas_fallan_con_el_mensaje_de_la_maqueta(): void
    {
        $resultado = $this->validar('Bienestar2026', 'Bienestar2027');

        $this->assertFalse($resultado->esValido());
        $this->assertSame('Las contraseñas no coinciden. Escribe la misma en los dos campos.', $resultado->errores()['confirmar']);
        $this->assertArrayNotHasKey('nueva', $resultado->errores());
    }

    public function test_si_fallan_las_reglas_y_no_coinciden_hay_dos_errores(): void
    {
        $resultado = $this->validar('luisa1234', 'luisa123');

        $this->assertCount(2, $resultado->errores());
    }

    public function test_la_letra_con_tilde_cuenta_como_mayuscula_y_minuscula(): void
    {
        $this->assertTrue($this->validar('Ñandú2026', 'Ñandú2026')->esValido());
    }
}
