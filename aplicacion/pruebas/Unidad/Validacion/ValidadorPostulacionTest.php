<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Validacion;

use BenefitRack\Validacion\ResultadoValidacion;
use BenefitRack\Validacion\ValidadorPostulacion;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Reglas del formulario de postulación: campos, catálogos y los tres documentos. */
final class ValidadorPostulacionTest extends TestCase
{
    use ArchivosDePrueba;

    protected function tearDown(): void
    {
        $this->limpiar();
    }

    private function validador(): ValidadorPostulacion
    {
        // «Hoy» fijo para que las pruebas de edad no dependan de la fecha real.
        return new ValidadorPostulacion(null, new DateTimeImmutable('2026-11-10'));
    }

    /**
     * @param array<string, string> $cambios
     * @return array<string, string>
     */
    private function campos(array $cambios = []): array
    {
        return $cambios + [
            'telefono' => '300 123 4567',
            'direccion' => 'Carrera 45 # 12-34, Bello',
            'fecha_nacimiento' => '2002-03-15',
            'id_eps' => '2',
            'id_entidad_bancaria' => '1',
            'id_tipo_cuenta' => '1',
            'numero_cuenta' => '91234567890',
            'titular_cuenta' => 'Luisa Fernanda Ríos',
            'motivo' => 'Necesito conectarme a clases.',
            'acepta_datos' => '1',
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function archivos(): array
    {
        return [
            'doc_identidad' => $this->subido($this->bytesPdf(), 'cedula.pdf'),
            'cert_eps' => $this->subido($this->bytesPng(), 'eps.png'),
            'cert_bancario' => $this->subido($this->bytesJpg(), 'banco.jpg'),
        ];
    }

    /** @return array<string, list<array{id: int, nombre: string}>> */
    private function catalogos(): array
    {
        return [
            'eps' => [['id' => 1, 'nombre' => 'Sura'], ['id' => 2, 'nombre' => 'Sanitas']],
            'bancos' => [['id' => 1, 'nombre' => 'Bancolombia']],
            'tiposCuenta' => [['id' => 1, 'nombre' => 'Ahorros']],
        ];
    }

    /**
     * @param array<string, string>                    $cambios
     * @param array<string, array<string, mixed>>|null $archivos
     */
    private function validar(array $cambios = [], ?array $archivos = null): ResultadoValidacion
    {
        return $this->validador()->validar($this->campos($cambios), $archivos ?? $this->archivos(), $this->catalogos());
    }

    public function test_un_formulario_completo_y_correcto_es_valido(): void
    {
        $resultado = $this->validar();

        $this->assertTrue($resultado->esValido(), json_encode($resultado->errores(), JSON_UNESCAPED_UNICODE) ?: '');
        $datos = $resultado->datos();
        $this->assertSame('3001234567', $datos['telefono']);
        $this->assertSame(2, $datos['id_eps']);
        $this->assertSame('91234567890', $datos['numero_cuenta']);
        $this->assertSame(['doc_identidad', 'cert_eps', 'cert_bancario'], array_keys($datos['archivos']));
        $this->assertSame('png', $datos['archivos']['cert_eps']['extension']);
    }

    public function test_si_falta_cert_eps_el_error_dice_falta_el_certificado_de_eps(): void
    {
        $archivos = $this->archivos();
        unset($archivos['cert_eps']);

        $resultado = $this->validar([], $archivos);

        $this->assertFalse($resultado->esValido());
        $this->assertSame('Falta el Certificado de EPS', $resultado->errores()['cert_eps']);
        $this->assertSame([], $resultado->datos());
    }

    public function test_un_motivo_de_501_caracteres_da_error_y_uno_de_500_no(): void
    {
        $this->assertArrayHasKey('motivo', $this->validar(['motivo' => str_repeat('á', 501)])->errores());
        $this->assertTrue($this->validar(['motivo' => str_repeat('á', 500)])->esValido());
    }

    public function test_el_motivo_es_opcional(): void
    {
        $resultado = $this->validar(['motivo' => '']);

        $this->assertTrue($resultado->esValido());
        $this->assertSame('', $resultado->datos()['motivo']);
    }

    public function test_el_telefono_lleva_de_7_a_10_digitos(): void
    {
        foreach (['123456', '12345678901', 'abc1234567', ''] as $malo) {
            $this->assertArrayHasKey('telefono', $this->validar(['telefono' => $malo])->errores(), $malo);
        }
        $this->assertTrue($this->validar(['telefono' => '1234567'])->esValido());
    }

    public function test_la_edad_debe_estar_entre_14_y_60_anios(): void
    {
        $this->assertArrayHasKey('fecha_nacimiento', $this->validar(['fecha_nacimiento' => '2012-11-11'])->errores()); // 13 años
        $this->assertTrue($this->validar(['fecha_nacimiento' => '2012-11-10'])->esValido());                           // 14 justos
        $this->assertTrue($this->validar(['fecha_nacimiento' => '1966-11-10'])->esValido());                           // 60 justos
        $this->assertArrayHasKey('fecha_nacimiento', $this->validar(['fecha_nacimiento' => '1965-11-09'])->errores()); // 61
    }

    public function test_la_fecha_acepta_dia_mes_anio_y_rechaza_fechas_imposibles(): void
    {
        $this->assertSame('2002-03-15', $this->validar(['fecha_nacimiento' => '15/03/2002'])->datos()['fecha_nacimiento']);
        foreach (['31/02/2002', 'ayer', '2002-13-01', ''] as $mala) {
            $this->assertArrayHasKey('fecha_nacimiento', $this->validar(['fecha_nacimiento' => $mala])->errores(), $mala);
        }
    }

    public function test_el_numero_de_cuenta_lleva_de_6_a_20_digitos_sin_puntos(): void
    {
        foreach (['12345', '123456789012345678901', '1234.56789', '12-345678', 'ABC12345'] as $malo) {
            $this->assertArrayHasKey('numero_cuenta', $this->validar(['numero_cuenta' => $malo])->errores(), $malo);
        }
        $this->assertTrue($this->validar(['numero_cuenta' => '123456'])->esValido());
        $this->assertTrue($this->validar(['numero_cuenta' => str_repeat('9', 20)])->esValido());
    }

    public function test_eps_banco_y_tipo_de_cuenta_deben_ser_de_la_lista(): void
    {
        $errores = $this->validar(['id_eps' => '99', 'id_entidad_bancaria' => 'x', 'id_tipo_cuenta' => ''])->errores();

        $this->assertArrayHasKey('id_eps', $errores);
        $this->assertArrayHasKey('id_entidad_bancaria', $errores);
        $this->assertArrayHasKey('id_tipo_cuenta', $errores);
    }

    public function test_direccion_y_titular_son_obligatorios(): void
    {
        $errores = $this->validar(['direccion' => '  ', 'titular_cuenta' => ''])->errores();

        $this->assertArrayHasKey('direccion', $errores);
        $this->assertArrayHasKey('titular_cuenta', $errores);
    }

    public function test_sin_aceptar_el_tratamiento_de_datos_no_es_valido(): void
    {
        $campos = $this->campos();
        unset($campos['acepta_datos']);

        $resultado = $this->validador()->validar($campos, $this->archivos(), $this->catalogos());

        $this->assertArrayHasKey('acepta_datos', $resultado->errores());
    }

    public function test_con_varios_problemas_se_cuentan_todos(): void
    {
        $resultado = $this->validar(['telefono' => '', 'numero_cuenta' => 'x'], []);

        $this->assertCount(5, $resultado->errores()); // teléfono, cuenta y los 3 documentos
    }
}
