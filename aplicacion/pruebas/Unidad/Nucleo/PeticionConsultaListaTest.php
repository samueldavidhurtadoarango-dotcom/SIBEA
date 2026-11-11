<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Nucleo;

use BenefitRack\Nucleo\Peticion;
use PHPUnit\Framework\TestCase;

/** Leer de la URL una lista (?columnas[]=a&columnas[]=b), como la mandan las casillas de un formulario. */
final class PeticionConsultaListaTest extends TestCase
{
    /** @param array<string, mixed> $consulta */
    private function peticion(array $consulta): Peticion
    {
        return new Peticion('GET', '/x', [], [], '127.0.0.1', [], $consulta);
    }

    public function test_devuelve_los_textos_de_la_lista(): void
    {
        $this->assertSame(['a', 'b'], $this->peticion(['columnas' => ['a', 'b']])->consultaLista('columnas'));
    }

    public function test_si_no_es_una_lista_devuelve_vacio(): void
    {
        $this->assertSame([], $this->peticion([])->consultaLista('columnas'));
        $this->assertSame([], $this->peticion(['columnas' => 'a'])->consultaLista('columnas'));
    }

    public function test_descarta_lo_que_no_es_texto(): void
    {
        $this->assertSame(['a'], $this->peticion(['columnas' => ['a', ['x'], 5]])->consultaLista('columnas'));
    }
}
