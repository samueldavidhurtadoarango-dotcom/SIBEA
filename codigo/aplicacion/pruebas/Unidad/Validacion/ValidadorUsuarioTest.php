<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Validacion;

use BenefitRack\Validacion\ValidadorUsuario;
use PHPUnit\Framework\TestCase;

/** Las reglas de los datos de un aprendiz nuevo (formulario y, después, Excel). */
final class ValidadorUsuarioTest extends TestCase
{
    private const PROGRAMAS = ['Análisis y Desarrollo de Software' => 7, 'Cocina' => 9];

    /**
     * @param array<string, string> $cambios
     * @return array<string, string>
     */
    private function fila(array $cambios = []): array
    {
        return $cambios + [
            'documento' => '1020345678',
            'nombre' => 'Karen Dayana',
            'apellido' => 'Moreno Lozano',
            'correo' => 'Karen.Moreno@Soy.Sena.edu.co',
            'programa' => 'Cocina',
            'ficha' => '2758341',
        ];
    }

    public function test_una_fila_correcta_queda_limpia(): void
    {
        $resultado = (new ValidadorUsuario())->validar($this->fila(), self::PROGRAMAS);

        $this->assertTrue($resultado->esValido());
        $this->assertSame('karen.moreno@soy.sena.edu.co', $resultado->datos()['correo']);
        $this->assertSame(9, $resultado->datos()['id_programa']);
    }

    public function test_el_documento_pierde_puntos_y_espacios(): void
    {
        $resultado = (new ValidadorUsuario())->validar($this->fila(['documento' => '1.020.345.678']), self::PROGRAMAS);

        $this->assertTrue($resultado->esValido());
        $this->assertSame('1020345678', $resultado->datos()['documento']);
    }

    public function test_un_documento_con_letras_o_de_largo_incorrecto_da_error(): void
    {
        foreach (['10A345678', '12345', '1234567890123'] as $malo) {
            $resultado = (new ValidadorUsuario())->validar($this->fila(['documento' => $malo]), self::PROGRAMAS);
            $this->assertArrayHasKey('documento', $resultado->errores(), $malo);
        }
    }

    public function test_un_correo_invalido_da_error_en_correo(): void
    {
        $resultado = (new ValidadorUsuario())->validar($this->fila(['correo' => 'no-es-correo']), self::PROGRAMAS);

        $this->assertFalse($resultado->esValido());
        $this->assertArrayHasKey('correo', $resultado->errores());
    }

    public function test_un_programa_que_no_existe_da_error_en_programa(): void
    {
        $resultado = (new ValidadorUsuario())->validar($this->fila(['programa' => 'Astronomía']), self::PROGRAMAS);

        $this->assertArrayHasKey('programa', $resultado->errores());
    }

    public function test_nombre_apellido_y_ficha_son_obligatorios_y_la_ficha_solo_digitos(): void
    {
        $resultado = (new ValidadorUsuario())->validar($this->fila(['nombre' => '', 'apellido' => ' ', 'ficha' => '27A']), self::PROGRAMAS);

        $this->assertSame(['nombre', 'apellido', 'ficha'], array_keys($resultado->errores()));
    }

    public function test_con_errores_los_datos_salen_vacios(): void
    {
        $resultado = (new ValidadorUsuario())->validar($this->fila(['correo' => 'x']), self::PROGRAMAS);

        $this->assertSame([], $resultado->datos());
    }
}
