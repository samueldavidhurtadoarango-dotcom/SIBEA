<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Validacion;

use PHPUnit\Framework\TestCase;
use SIBEA\Validacion\ResultadoValidacion;

/** ResultadoValidacion: los datos solo salen si no hay errores. */
final class ResultadoValidacionTest extends TestCase
{
    public function test_sin_errores_entrega_los_datos(): void
    {
        $resultado = new ResultadoValidacion(['nombre' => 'Ana']);

        $this->assertSame(['nombre' => 'Ana'], $resultado->datos());
    }

    public function test_con_errores_no_entrega_datos(): void
    {
        $resultado = new ResultadoValidacion(['nombre' => 'Ana']);
        $resultado->agregar('correo', 'El correo no es válido.');

        $this->assertSame([], $resultado->datos());
    }
}
