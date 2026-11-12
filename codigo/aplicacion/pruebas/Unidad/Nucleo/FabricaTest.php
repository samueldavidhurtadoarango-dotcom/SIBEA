<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Nucleo;

use BenefitRack\Nucleo\Configuracion;
use BenefitRack\Nucleo\Fabrica;
use BenefitRack\Nucleo\Sesion;
use BenefitRack\Nucleo\Vista;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

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

        $this->fabrica()->crear('BenefitRack\\Controladores\\NoExiste');
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
