<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Nucleo;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SIBEA\Nucleo\Configuracion;
use SIBEA\Nucleo\ZonaHoraria;

/** La zona horaria sale de la configuración (app.zona_horaria) y por defecto es America/Bogota. */
final class ZonaHorariaTest extends TestCase
{
    private string $original;

    protected function setUp(): void
    {
        $this->original = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->original);
    }

    public function test_sin_la_clave_se_usa_bogota(): void
    {
        date_default_timezone_set('Europe/Berlin'); // lo que trae XAMPP

        $zona = ZonaHoraria::aplicar(new Configuracion([]));

        $this->assertSame('America/Bogota', $zona);
        $this->assertSame('America/Bogota', date_default_timezone_get());
    }

    public function test_usa_la_zona_configurada(): void
    {
        $zona = ZonaHoraria::aplicar(new Configuracion(['app' => ['zona_horaria' => 'America/Lima']]));

        $this->assertSame('America/Lima', $zona);
        $this->assertSame('America/Lima', date_default_timezone_get());
    }

    public function test_una_zona_inventada_falla_con_un_mensaje_claro(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ZonaHoraria::aplicar(new Configuracion(['app' => ['zona_horaria' => 'Mi/Casa']]));
    }
}
