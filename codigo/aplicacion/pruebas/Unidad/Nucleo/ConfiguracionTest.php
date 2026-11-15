<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Nucleo;

use PHPUnit\Framework\TestCase;
use SIBEA\Nucleo\Configuracion;

/** Configuracion::obtenerOpcional: el valor guardado o, si falta la clave, el de por defecto. */
final class ConfiguracionTest extends TestCase
{
    public function test_devuelve_el_valor_guardado(): void
    {
        $configuracion = new Configuracion(['app' => ['sesion_inactividad_minutos' => 10]]);

        $this->assertSame(10, $configuracion->obtenerOpcional('app.sesion_inactividad_minutos', 30));
    }

    public function test_si_falta_la_clave_devuelve_el_valor_por_defecto(): void
    {
        $this->assertSame(30, (new Configuracion([]))->obtenerOpcional('app.sesion_inactividad_minutos', 30));
    }
}
