<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Validacion;

use BenefitRack\Validacion\ValidadorConvocatoria;
use PHPUnit\Framework\TestCase;

/** Reglas del formulario de convocatorias (la base de datos repite las de fondo). */
final class ValidadorConvocatoriaTest extends TestCase
{
    /**
     * @param array<string, string> $cambios Campos que se quieren cambiar respecto a un formulario correcto.
     * @return array<string, string>
     */
    private function formulario(array $cambios = []): array
    {
        return $cambios + [
            'nombre' => 'Alimentación 2027-1',
            'id_beneficio' => '1',
            'fecha_inicio' => '2027-02-01',
            'fecha_cierre' => '2027-03-31',
            'cupos' => '25',
            'requisitos' => "Matrícula activa.\nCertificado de EPS.",
            'valor_pago' => '180000',
            'numero_pagos' => '6',
            'duracion_meses' => '6',
            'estado' => 'Activa',
        ];
    }

    public function test_un_formulario_correcto_devuelve_los_datos_limpios(): void
    {
        $resultado = (new ValidadorConvocatoria())->validar($this->formulario());

        $this->assertTrue($resultado->esValido());
        $this->assertSame([
            'nombre' => 'Alimentación 2027-1',
            'id_beneficio' => 1,
            'fecha_inicio' => '2027-02-01',
            'fecha_cierre' => '2027-03-31',
            'cupos' => 25,
            'requisitos' => "Matrícula activa.\nCertificado de EPS.",
            'valor_pago' => '180000.00',
            'numero_pagos' => 6,
            'duracion_meses' => 6,
            'estado' => 'Activa',
        ], $resultado->datos());
    }

    public function test_el_valor_con_puntos_y_signo_queda_como_decimal(): void
    {
        foreach (['180.000', '$ 180.000', ' 180000 '] as $escrito) {
            $resultado = (new ValidadorConvocatoria())->validar($this->formulario(['valor_pago' => $escrito]));

            $this->assertTrue($resultado->esValido(), "«{$escrito}» debería valer");
            $this->assertSame('180000.00', $resultado->datos()['valor_pago']);
        }
    }

    public function test_el_valor_en_cero_o_con_letras_da_error(): void
    {
        foreach (['0', '0.000', 'abc', '18,5', '-5', ''] as $escrito) {
            $resultado = (new ValidadorConvocatoria())->validar($this->formulario(['valor_pago' => $escrito]));

            $this->assertArrayHasKey('valor_pago', $resultado->errores(), "«{$escrito}» no debería valer");
        }
    }

    public function test_cualquier_beneficio_del_catalogo_es_valido(): void
    {
        foreach (['2', '3', '15'] as $id) {
            $resultado = (new ValidadorConvocatoria())->validar($this->formulario(['id_beneficio' => $id]));

            $this->assertTrue($resultado->esValido(), "El beneficio {$id} debería valer");
            $this->assertSame((int) $id, $resultado->datos()['id_beneficio']);
        }
    }

    public function test_un_signo_de_pesos_en_medio_del_valor_da_error(): void
    {
        $resultado = (new ValidadorConvocatoria())->validar($this->formulario(['valor_pago' => '18$0.000']));

        $this->assertArrayHasKey('valor_pago', $resultado->errores());
    }

    public function test_el_cierre_igual_al_inicio_da_error(): void
    {
        $resultado = (new ValidadorConvocatoria())->validar($this->formulario(['fecha_cierre' => '2027-02-01']));

        $this->assertFalse($resultado->esValido());
        $this->assertArrayHasKey('fecha_cierre', $resultado->errores());
    }

    public function test_el_cierre_antes_del_inicio_da_error(): void
    {
        $resultado = (new ValidadorConvocatoria())->validar($this->formulario(['fecha_cierre' => '2027-01-31']));

        $this->assertArrayHasKey('fecha_cierre', $resultado->errores());
    }

    public function test_una_fecha_que_no_existe_da_error(): void
    {
        $resultado = (new ValidadorConvocatoria())->validar($this->formulario(['fecha_inicio' => '2027-02-30', 'fecha_cierre' => 'mañana']));

        $this->assertArrayHasKey('fecha_inicio', $resultado->errores());
        $this->assertArrayHasKey('fecha_cierre', $resultado->errores());
    }

    public function test_cupos_pagos_y_meses_en_cero_dan_error(): void
    {
        $resultado = (new ValidadorConvocatoria())->validar($this->formulario(['cupos' => '0', 'numero_pagos' => '0', 'duracion_meses' => '0']));

        $this->assertSame(['cupos', 'numero_pagos', 'duracion_meses'], array_keys($resultado->errores()));
    }

    public function test_cupos_con_letras_o_decimales_dan_error(): void
    {
        foreach (['abc', '2.5', '-3', ''] as $escrito) {
            $resultado = (new ValidadorConvocatoria())->validar($this->formulario(['cupos' => $escrito]));

            $this->assertArrayHasKey('cupos', $resultado->errores(), "«{$escrito}» no debería valer");
        }
    }

    public function test_nombre_requisitos_y_beneficio_son_obligatorios(): void
    {
        $resultado = (new ValidadorConvocatoria())->validar($this->formulario(['nombre' => '  ', 'requisitos' => '', 'id_beneficio' => '']));

        $this->assertSame(['nombre', 'id_beneficio', 'requisitos'], array_keys($resultado->errores()));
    }

    public function test_el_nombre_no_puede_pasar_de_150_letras(): void
    {
        $resultado = (new ValidadorConvocatoria())->validar($this->formulario(['nombre' => str_repeat('a', 151)]));

        $this->assertArrayHasKey('nombre', $resultado->errores());
    }

    public function test_sin_estado_la_convocatoria_nace_inactiva(): void
    {
        $formulario = $this->formulario();
        unset($formulario['estado']);

        $this->assertSame('Inactiva', (new ValidadorConvocatoria())->validar($formulario)->datos()['estado']);
    }

    public function test_un_estado_distinto_de_activa_o_inactiva_da_error(): void
    {
        $resultado = (new ValidadorConvocatoria())->validar($this->formulario(['estado' => 'Cerrada']));

        $this->assertArrayHasKey('estado', $resultado->errores());
    }

    public function test_con_errores_no_se_entregan_datos(): void
    {
        $resultado = (new ValidadorConvocatoria())->validar($this->formulario(['cupos' => '0']));

        $this->assertSame([], $resultado->datos());
    }
}
