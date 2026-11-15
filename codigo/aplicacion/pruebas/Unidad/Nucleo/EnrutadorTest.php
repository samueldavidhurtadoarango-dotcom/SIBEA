<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Nucleo;

use PHPUnit\Framework\TestCase;
use SIBEA\Nucleo\Enrutador;
use SIBEA\Nucleo\Peticion;

final class EnrutadorTest extends TestCase
{
    private function peticion(string $metodo, string $ruta): Peticion
    {
        return new Peticion($metodo, $ruta, [], [], '127.0.0.1');
    }

    public function test_resuelve_ruta_con_parametro(): void
    {
        $enrutador = new Enrutador();
        $enrutador->agregar('GET', '/admin/postulaciones/{id}', ['Controlador', 'ver']);

        $resultado = $enrutador->resolver($this->peticion('GET', '/admin/postulaciones/15'));

        $this->assertNotNull($resultado);
        $this->assertSame('15', $resultado['parametros']['id']);
        $this->assertSame(['Controlador', 'ver'], $resultado['destino']);
    }

    public function test_no_resuelve_id_no_numerico(): void
    {
        $enrutador = new Enrutador();
        $enrutador->agregar('GET', '/admin/postulaciones/{id}', ['Controlador', 'ver']);

        $this->assertNull($enrutador->resolver($this->peticion('GET', '/admin/postulaciones/abc')));
    }

    public function test_distingue_metodo(): void
    {
        $enrutador = new Enrutador();
        $enrutador->agregar('GET', '/ingresar', ['Controlador', 'formulario']);

        $this->assertNull($enrutador->resolver($this->peticion('POST', '/ingresar')));
    }

    public function test_lista_las_rutas_registradas_con_metodo_y_patron(): void
    {
        $enrutador = new Enrutador();
        $enrutador->agregar('get', '/ingresar', ['Controlador', 'formulario']);
        $enrutador->agregar('POST', '/admin/usuarios/{id}/estado', ['Controlador', 'estado']);

        $this->assertSame(
            [['metodo' => 'GET', 'patron' => '/ingresar'], ['metodo' => 'POST', 'patron' => '/admin/usuarios/{id}/estado']],
            $enrutador->rutas()
        );
    }
}
