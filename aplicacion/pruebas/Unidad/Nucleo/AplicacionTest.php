<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Nucleo;

use BenefitRack\Filtros\Filtro;
use BenefitRack\Nucleo\Aplicacion;
use BenefitRack\Nucleo\Configuracion;
use BenefitRack\Nucleo\Enrutador;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Nucleo\Vista;
use PHPUnit\Framework\TestCase;

final class AplicacionTest extends TestCase
{
    private Enrutador $enrutador;

    private function aplicacion(bool $depuracion = false): Aplicacion
    {
        $configuracion = new Configuracion(['app' => ['depuracion' => $depuracion]]);

        return new Aplicacion($this->enrutador, new Vista(dirname(__DIR__, 3) . '/vistas'), $configuracion);
    }

    private function peticion(string $metodo, string $ruta): Peticion
    {
        return new Peticion($metodo, $ruta, [], [], '127.0.0.1');
    }

    protected function setUp(): void
    {
        $this->enrutador = new Enrutador();
    }

    public function test_ruta_inexistente_da_404(): void
    {
        $respuesta = $this->aplicacion()->ejecutar($this->peticion('GET', '/no-existe'));

        $this->assertSame(404, $respuesta->codigo());
    }

    public function test_llama_al_controlador_con_el_parametro(): void
    {
        $this->enrutador->agregar('GET', '/cosas/{id}', static fn (Peticion $p): Respuesta => Respuesta::html('id=' . $p->parametro('id')));

        $respuesta = $this->aplicacion()->ejecutar($this->peticion('GET', '/cosas/7'));

        $this->assertSame('id=7', $respuesta->cuerpo());
    }

    public function test_un_filtro_que_responde_corta_el_recorrido(): void
    {
        $filtro = new class () implements Filtro {
            public function aplicar(Peticion $peticion): ?Respuesta
            {
                return Respuesta::redirigir('/ingresar');
            }
        };
        $this->enrutador->agregar('GET', '/privada', static fn (): Respuesta => Respuesta::html('secreto'), [$filtro]);

        $respuesta = $this->aplicacion()->ejecutar($this->peticion('GET', '/privada'));

        $this->assertSame('/ingresar', $respuesta->destino());
    }

    public function test_configuracion_incompleta_no_impide_la_pagina_500(): void
    {
        $this->enrutador->agregar('GET', '/falla', static function (): Respuesta {
            throw new \RuntimeException('x');
        });
        $aplicacion = new Aplicacion($this->enrutador, new Vista(dirname(__DIR__, 3) . '/vistas'), new Configuracion([]));
        $anterior = ini_set('error_log', sys_get_temp_dir() . '/br_prueba_error.log');

        $respuesta = $aplicacion->ejecutar($this->peticion('GET', '/falla'));

        ini_set('error_log', (string) $anterior);
        $this->assertSame(500, $respuesta->codigo());
    }

    public function test_si_falla_la_vista_500_responde_texto_simple(): void
    {
        $this->enrutador->agregar('GET', '/falla', static function (): Respuesta {
            throw new \RuntimeException('x');
        });
        $vistaRota = new Vista(sys_get_temp_dir() . '/br_sin_vistas_' . bin2hex(random_bytes(4)));
        $aplicacion = new Aplicacion($this->enrutador, $vistaRota, new Configuracion([]));
        $anterior = ini_set('error_log', sys_get_temp_dir() . '/br_prueba_error.log');

        $respuesta = $aplicacion->ejecutar($this->peticion('GET', '/falla'));

        ini_set('error_log', (string) $anterior);
        $this->assertSame(500, $respuesta->codigo());
        $this->assertSame('Error del servidor', $respuesta->cuerpo());
    }

    public function test_excepcion_da_500_sin_detalle_si_no_hay_depuracion(): void
    {
        $this->enrutador->agregar('GET', '/falla', static function (): Respuesta {
            throw new \RuntimeException('detalle-secreto');
        });
        $anterior = ini_set('error_log', sys_get_temp_dir() . '/br_prueba_error.log');

        $respuesta = $this->aplicacion()->ejecutar($this->peticion('GET', '/falla'));

        ini_set('error_log', (string) $anterior);
        $this->assertSame(500, $respuesta->codigo());
        $this->assertStringNotContainsString('detalle-secreto', $respuesta->cuerpo());
    }
}
