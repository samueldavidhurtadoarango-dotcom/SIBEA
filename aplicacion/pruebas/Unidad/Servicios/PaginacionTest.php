<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Servicios;

use BenefitRack\Servicios\Paginacion;
use PHPUnit\Framework\TestCase;

/** Cuántas páginas hay y qué página se muestra cuando piden una que no existe. */
final class PaginacionTest extends TestCase
{
    public function test_paginas_redondea_hacia_arriba_y_nunca_baja_de_una(): void
    {
        $this->assertSame(1, Paginacion::paginas(0, 20));
        $this->assertSame(1, Paginacion::paginas(20, 20));
        $this->assertSame(2, Paginacion::paginas(21, 20));
    }

    public function test_corregir_deja_la_pagina_dentro_del_rango(): void
    {
        $this->assertSame(1, Paginacion::corregir(0, 3));
        $this->assertSame(1, Paginacion::corregir(-5, 3));
        $this->assertSame(2, Paginacion::corregir(2, 3));
        $this->assertSame(3, Paginacion::corregir(99, 3));
    }
}
