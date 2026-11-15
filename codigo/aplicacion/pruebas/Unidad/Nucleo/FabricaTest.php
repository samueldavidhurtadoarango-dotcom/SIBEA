<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Nucleo;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SIBEA\Nucleo\Configuracion;
use SIBEA\Nucleo\Fabrica;
use SIBEA\Nucleo\Sesion;
use SIBEA\Nucleo\Vista;

final class FabricaTest extends TestCase
{
    private function fabrica(): Fabrica
    {
        return new Fabrica(new Configuracion([]), dirname(__DIR__, 3) . '/vistas', '127.0.0.1');
    }

    public function test_una_clase_desconocida_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No sé crear');

        $this->fabrica()->crear('SIBEA\\Controladores\\NoExiste');
    }

    public function test_la_sesion_y_la_vista_son_compartidas(): void
    {
        $fabrica = $this->fabrica();

        $this->assertInstanceOf(Sesion::class, $fabrica->sesion());
        $this->assertSame($fabrica->sesion(), $fabrica->sesion());
        $this->assertInstanceOf(Vista::class, $fabrica->vista());
        $this->assertSame($fabrica->vista(), $fabrica->vista());
    }
}
