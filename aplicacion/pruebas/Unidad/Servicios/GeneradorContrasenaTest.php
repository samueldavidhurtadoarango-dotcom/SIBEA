<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Servicios;

use BenefitRack\Servicios\GeneradorContrasena;
use PHPUnit\Framework\TestCase;

/** La contraseña temporal: 10 caracteres, sin los que se confunden, aleatoria. */
final class GeneradorContrasenaTest extends TestCase
{
    /** @return list<string> */
    private function generarMil(): array
    {
        $generador = new GeneradorContrasena();
        $claves = [];
        for ($i = 0; $i < 1000; $i++) {
            $claves[] = $generador->generar();
        }

        return $claves;
    }

    public function test_mil_contrasenas_tienen_diez_caracteres(): void
    {
        foreach ($this->generarMil() as $clave) {
            $this->assertSame(10, strlen($clave));
        }
    }

    public function test_ninguna_contiene_caracteres_que_se_confunden(): void
    {
        foreach ($this->generarMil() as $clave) {
            $this->assertSame(0, preg_match('/[0O1lI]/', $clave), "La contraseña $clave tiene un carácter ambiguo.");
        }
    }

    public function test_solo_usa_el_alfabeto_permitido(): void
    {
        foreach ($this->generarMil() as $clave) {
            $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789]{10}$/', $clave);
        }
    }

    public function test_no_hay_repetidas(): void
    {
        $claves = $this->generarMil();

        $this->assertCount(1000, array_unique($claves));
    }
}
