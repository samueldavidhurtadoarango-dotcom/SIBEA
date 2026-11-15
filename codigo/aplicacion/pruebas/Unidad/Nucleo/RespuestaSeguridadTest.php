<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Nucleo;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SIBEA\Nucleo\Respuesta;

/** Cabeceras de seguridad (B3) en toda respuesta y «sandbox» extra para los documentos subidos (B6). */
final class RespuestaSeguridadTest extends TestCase
{
    /** @return array<string, array{0: Respuesta}> */
    public static function respuestas(): array
    {
        $ruta = (string) tempnam(sys_get_temp_dir(), 'brs');
        file_put_contents($ruta, 'abc');
        register_shutdown_function(static fn () => @unlink($ruta));

        return [
            'html' => [Respuesta::html('<p>hola</p>')],
            'error' => [Respuesta::html('x', 404)],
            'redirección' => [Respuesta::redirigir('/ingresar')],
            'descarga' => [Respuesta::descarga('abc', 'a.xlsx', 'application/octet-stream')],
            'archivo' => [Respuesta::archivo($ruta, 'a.pdf', 'application/pdf', true)],
            'sin caché' => [Respuesta::html('x')->sinCache()],
        ];
    }

    #[DataProvider('respuestas')]
    public function test_toda_respuesta_lleva_las_cabeceras_de_seguridad(Respuesta $respuesta): void
    {
        $this->assertSame('DENY', $respuesta->cabecera('X-Frame-Options'));
        $this->assertSame('same-origin', $respuesta->cabecera('Referrer-Policy'));
        $this->assertSame('nosniff', $respuesta->cabecera('X-Content-Type-Options'));
        $this->assertNotNull($respuesta->cabecera('Content-Security-Policy'));
    }

    public function test_la_politica_de_contenido_de_una_pagina_impide_marcos_objetos_y_base_ajena(): void
    {
        $politica = (string) Respuesta::html('x')->cabecera('Content-Security-Policy');

        $this->assertSame("frame-ancestors 'none'; object-src 'none'; base-uri 'self'", $politica);
        // No se pone default-src: bloquearía Google Fonts y los scripts de /js.
        $this->assertStringNotContainsString('default-src', $politica);
    }

    public function test_un_documento_subido_se_entrega_en_sandbox(): void
    {
        $ruta = (string) tempnam(sys_get_temp_dir(), 'brs');
        file_put_contents($ruta, 'abc');

        $respuesta = Respuesta::archivo($ruta, 'a.pdf', 'application/pdf', true);
        unlink($ruta);

        $this->assertSame("sandbox; frame-ancestors 'none'", $respuesta->cabecera('Content-Security-Policy'));
        $this->assertSame('nosniff', $respuesta->cabecera('X-Content-Type-Options'));
    }

    public function test_sin_cache_conserva_las_cabeceras_de_seguridad_y_la_sandbox(): void
    {
        $ruta = (string) tempnam(sys_get_temp_dir(), 'brs');
        file_put_contents($ruta, 'abc');

        $respuesta = Respuesta::archivo($ruta, 'a.pdf', 'application/pdf', true)->sinCache();
        unlink($ruta);

        $this->assertSame("sandbox; frame-ancestors 'none'", $respuesta->cabecera('Content-Security-Policy'));
        $this->assertSame('DENY', $respuesta->cabecera('X-Frame-Options'));
        $this->assertSame('no-store', $respuesta->cabecera('Cache-Control'));
    }
}
