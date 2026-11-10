<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Nucleo;

use BenefitRack\Nucleo\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function test_un_token_valido_pasa(): void
    {
        $token = Csrf::token();

        $this->assertTrue(Csrf::esValido($token));
    }

    public function test_el_token_es_estable_dentro_de_la_misma_sesion(): void
    {
        $this->assertSame(Csrf::token(), Csrf::token());
    }

    public function test_un_token_alterado_falla(): void
    {
        $alterado = strrev(Csrf::token());

        $this->assertFalse(Csrf::esValido($alterado));
    }

    public function test_un_token_vacio_falla(): void
    {
        Csrf::token();

        $this->assertFalse(Csrf::esValido(''));
    }

    public function test_sin_token_en_la_sesion_nada_es_valido(): void
    {
        // Sesión nueva y vacía: ni siquiera el texto vacío debe pasar.
        $this->assertFalse(Csrf::esValido(''));
        $this->assertFalse(Csrf::esValido('cualquier-cosa'));
    }

    public function test_el_token_tiene_64_caracteres_hexadecimales(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', Csrf::token());
    }
}
