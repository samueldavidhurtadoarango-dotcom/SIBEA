<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Integracion;

use BenefitRack\Nucleo\BaseDatos;
use BenefitRack\Nucleo\Fabrica;

/** El inicio de sesión debe correr con @app_id_usuario = NULL aunque ya haya una sesión abierta. */
final class FabricaBaseDatosTest extends BaseDePrueba
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    private function fabrica(): Fabrica
    {
        return new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '10.0.0.7');
    }

    private function usuarioVisto(BaseDatos $bd): mixed
    {
        return $bd->pdo()->query('SELECT @app_id_usuario')->fetchColumn();
    }

    public function test_con_sesion_abierta_base_datos_actua_como_ese_usuario_y_para_ingreso_como_null(): void
    {
        $fabrica = $this->fabrica();
        $fabrica->sesion()->iniciarPara(5, 'Administrador', false);

        $this->assertSame('5', (string) $this->usuarioVisto($fabrica->baseDatos()));
        $this->assertNull($this->usuarioVisto($fabrica->baseDatosParaIngreso()));
        // Es la misma conexión: volver al trabajo normal vuelve a fijar a la persona de la sesión.
        $this->assertSame('5', (string) $this->usuarioVisto($fabrica->baseDatos()));
    }

    public function test_sin_sesion_base_datos_da_null(): void
    {
        $this->assertNull($this->usuarioVisto($this->fabrica()->baseDatos()));
    }

    public function test_hay_una_sola_conexion_por_peticion(): void
    {
        $fabrica = $this->fabrica();

        $this->assertSame($fabrica->baseDatos()->pdo(), $fabrica->baseDatosParaIngreso()->pdo());
    }
}
