<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Nucleo;

use BenefitRack\Nucleo\ErrorDeNegocio;
use BenefitRack\Nucleo\TraductorErroresBd;
use PDOException;
use PHPUnit\Framework\TestCase;

final class TraductorErroresBdTest extends TestCase
{
    /** Arma una PDOException como la que lanza PDO: errorInfo = [SQLSTATE, código del motor, mensaje]. */
    private function excepcion(string $estado, int $codigo, string $mensaje): PDOException
    {
        $excepcion = new PDOException("SQLSTATE[$estado]: $mensaje");
        $excepcion->errorInfo = [$estado, $codigo, $mensaje];

        return $excepcion;
    }

    public function test_el_45000_conserva_el_texto_de_la_base(): void
    {
        $texto = 'Solo un administrador activo puede aprobar una postulación';

        $traducido = TraductorErroresBd::traducir($this->excepcion('45000', 1644, $texto));

        $this->assertInstanceOf(ErrorDeNegocio::class, $traducido);
        $this->assertSame($texto, $traducido->getMessage());
    }

    public function test_el_1062_da_ese_registro_ya_existe(): void
    {
        $traducido = TraductorErroresBd::traducir($this->excepcion('23000', 1062, "Duplicate entry 'x' for key 'uk_usuario_correo'"));

        $this->assertSame('Ese registro ya existe', $traducido->getMessage());
    }

    public function test_cualquier_otro_error_da_un_mensaje_generico_sin_detalles_tecnicos(): void
    {
        $traducido = TraductorErroresBd::traducir($this->excepcion('42S02', 1146, "Table 'benefitrack_v2.secreta' doesn't exist"));

        $this->assertSame('No pudimos guardar los cambios. Intenta de nuevo.', $traducido->getMessage());
        $this->assertStringNotContainsString('secreta', $traducido->getMessage());
    }

    public function test_una_excepcion_sin_errorinfo_da_el_mensaje_generico(): void
    {
        $traducido = TraductorErroresBd::traducir(new PDOException('algo raro'));

        $this->assertSame('No pudimos guardar los cambios. Intenta de nuevo.', $traducido->getMessage());
    }
}
