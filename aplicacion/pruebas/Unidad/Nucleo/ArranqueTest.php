<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Nucleo;

use BenefitRack\Nucleo\Arranque;
use PHPUnit\Framework\TestCase;

/** B7: si el arranque falla (por ejemplo, falta la configuración) la persona ve una página 500 sencilla, sin detalles. */
final class ArranqueTest extends TestCase
{
    private string $registro;
    private string|false $registroAnterior;

    protected function setUp(): void
    {
        $this->registro = (string) tempnam(sys_get_temp_dir(), 'brlog');
        $this->registroAnterior = ini_set('error_log', $this->registro);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->registroAnterior === false ? '' : $this->registroAnterior);
        @unlink($this->registro);
    }

    public function test_un_fallo_de_arranque_responde_500_sin_detalles_tecnicos(): void
    {
        $raizSinConfiguracion = sys_get_temp_dir() . '/br-sin-config-' . bin2hex(random_bytes(4));

        $respuesta = Arranque::atender($raizSinConfiguracion);

        $this->assertSame(500, $respuesta->codigo());
        $this->assertStringContainsString('Algo salió mal', $respuesta->cuerpo());
        $this->assertStringNotContainsString('config.local.php', $respuesta->cuerpo());
        $this->assertStringNotContainsString($raizSinConfiguracion, $respuesta->cuerpo());
        $this->assertStringNotContainsString('InvalidArgumentException', $respuesta->cuerpo());
    }

    public function test_el_fallo_queda_registrado_con_error_log(): void
    {
        Arranque::atender(sys_get_temp_dir() . '/br-sin-config-' . bin2hex(random_bytes(4)));

        $texto = (string) file_get_contents($this->registro);
        $this->assertStringContainsString('[BenefitRack] Falló el arranque', $texto);
        $this->assertStringContainsString('config.local.php', $texto);
    }

    public function test_la_pagina_de_error_lleva_las_cabeceras_de_seguridad(): void
    {
        $respuesta = Arranque::atender(sys_get_temp_dir() . '/br-sin-config-' . bin2hex(random_bytes(4)));

        $this->assertSame('DENY', $respuesta->cabecera('X-Frame-Options'));
    }
}
