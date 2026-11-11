<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Servicios;

use BenefitRack\Servicios\ExportadorExcel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\TestCase;

/** El Excel de entrega de contraseñas temporales. */
final class ExportadorExcelTest extends TestCase
{
    /** @var list<string> */
    private array $archivos = [];

    protected function tearDown(): void
    {
        foreach ($this->archivos as $archivo) {
            @unlink($archivo);
        }
    }

    /**
     * Genera el Excel con las filas dadas y lo vuelve a leer con PhpSpreadsheet.
     *
     * @param list<array{documento: string, nombre: string, correo: string, contrasena: string}> $filas
     */
    private function leer(array $filas): Worksheet
    {
        $ruta = (new ExportadorExcel())->entregaContrasenas($filas);
        $this->archivos[] = $ruta;

        return IOFactory::load($ruta)->getActiveSheet();
    }

    public function test_tiene_las_cuatro_columnas_y_una_fila_por_aprendiz(): void
    {
        $hoja = $this->leer([['documento' => '1020345678', 'nombre' => 'Karen Moreno', 'correo' => 'k@x.co', 'contrasena' => 'Abc23xyz45']]);

        $this->assertSame(['Documento', 'Nombre completo', 'Correo', 'Contraseña temporal'], [
            $hoja->getCell('A1')->getValue(), $hoja->getCell('B1')->getValue(), $hoja->getCell('C1')->getValue(), $hoja->getCell('D1')->getValue(),
        ]);
        $this->assertSame('Karen Moreno', $hoja->getCell('B2')->getValue());
        $this->assertSame('Abc23xyz45', $hoja->getCell('D2')->getValue());
    }

    public function test_el_documento_queda_como_texto(): void
    {
        $hoja = $this->leer([['documento' => '1020345678', 'nombre' => 'A', 'correo' => 'a@b.co', 'contrasena' => 'x']]);

        $this->assertSame(DataType::TYPE_STRING, $hoja->getCell('A2')->getDataType());
        $this->assertSame('1020345678', $hoja->getCell('A2')->getValue());
    }

    public function test_texto_que_empieza_por_igual_queda_como_texto(): void
    {
        $hoja = $this->leer([['documento' => '1020345678', 'nombre' => '=1+1', 'correo' => '@x', 'contrasena' => '-2+3']]);

        $this->assertSame('s', $hoja->getCell('B2')->getDataType());
        $this->assertSame('=1+1', $hoja->getCell('B2')->getValue());
        $this->assertSame('s', $hoja->getCell('C2')->getDataType());
        $this->assertSame('-2+3', $hoja->getCell('D2')->getValue());
    }

    public function test_texto_seguro_quita_tabuladores_y_retornos_del_inicio(): void
    {
        $this->assertSame('=1+1', ExportadorExcel::textoSeguro("\t\r=1+1"));
        $this->assertSame('Karen', ExportadorExcel::textoSeguro('Karen'));
    }

    // ---------- Excel de postulaciones (M6a) ----------

    /**
     * @param array<string, mixed> $cambios
     * @return array<string, mixed> Una fila como la entrega el repositorio.
     */
    private function filaPostulacion(array $cambios = []): array
    {
        return $cambios + [
            'documento' => '1020345678', 'nombre' => 'Ana Peña', 'correo' => 'ana@soy.sena.edu.co', 'telefono' => '3001112233',
            'programa' => 'Contabilidad', 'ficha' => '2761190', 'eps' => 'Sanitas', 'convocatoria' => 'Apoyo 2026-2',
            'beneficio' => 'Alimentación', 'estado' => 'Aprobada', 'fecha' => '2026-11-15 09:30:00', 'banco' => 'Bancolombia',
            'tipo_cuenta' => 'Ahorros', 'numero_cuenta' => '0012345678', 'titular' => 'Ana Peña', 'motivo' => 'Lo necesito.',
            'docs_total' => 3, 'docs_pendientes' => 0, 'docs_no_validos' => 0, 'documento_pedido' => 0,
        ];
    }

    /**
     * @param iterable<array<string, mixed>> $filas
     * @param list<string>                   $columnas
     */
    private function leerPostulaciones(iterable $filas, array $columnas): Worksheet
    {
        $ruta = (new ExportadorExcel())->postulaciones($filas, $columnas);
        $this->archivos[] = $ruta;

        return IOFactory::load($ruta)->getActiveSheet();
    }

    public function test_ca10_exporta_exactamente_las_columnas_pedidas(): void
    {
        $hoja = $this->leerPostulaciones([$this->filaPostulacion()], ['documento', 'nombre', 'estado']);

        $this->assertSame('C', $hoja->getHighestColumn());
        $this->assertSame(['Documento', 'Nombre', 'Estado'], [$hoja->getCell('A1')->getValue(), $hoja->getCell('B1')->getValue(), $hoja->getCell('C1')->getValue()]);
        $this->assertSame(['1020345678', 'Ana Peña', 'Aprobada'], [$hoja->getCell('A2')->getValue(), $hoja->getCell('B2')->getValue(), $hoja->getCell('C2')->getValue()]);
        $this->assertSame(2, $hoja->getHighestRow());
    }

    public function test_el_documento_exportado_es_texto(): void
    {
        $hoja = $this->leerPostulaciones([$this->filaPostulacion()], ['documento']);

        $this->assertSame(DataType::TYPE_STRING, $hoja->getCell('A2')->getDataType());
        $this->assertSame('1020345678', $hoja->getCell('A2')->getValue());
    }

    public function test_un_nombre_con_formula_queda_como_texto(): void
    {
        $formula = '=HYPERLINK("http://malo.test","clic")';
        $hoja = $this->leerPostulaciones([$this->filaPostulacion(['nombre' => $formula])], ['nombre']);

        $this->assertSame(DataType::TYPE_STRING, $hoja->getCell('A2')->getDataType());
        $this->assertSame($formula, $hoja->getCell('A2')->getValue());
    }

    public function test_las_columnas_desconocidas_se_ignoran_y_se_ordenan_como_en_la_maqueta(): void
    {
        $this->assertSame(['documento', 'estado'], ExportadorExcel::columnasPedidas(['estado', 'clave_secreta', 'documento', 'documento']));
        $this->assertSame([], ExportadorExcel::columnasPedidas(['x', 'y']));
    }

    public function test_cada_columna_pertenece_a_un_grupo(): void
    {
        $enGrupos = array_merge(...array_values(ExportadorExcel::GRUPOS_COLUMNAS));

        $this->assertEqualsCanonicalizing(array_keys(ExportadorExcel::COLUMNAS), $enGrupos);
    }

    public function test_la_fecha_sale_como_15_nov_2026(): void
    {
        $hoja = $this->leerPostulaciones([$this->filaPostulacion()], ['fecha']);

        $this->assertSame('15 nov 2026', $hoja->getCell('A2')->getValue());
    }

    public function test_cuenta_y_documento_llevan_solo_digitos_y_conservan_los_ceros(): void
    {
        $fila = $this->filaPostulacion(['documento' => '1.020-345.678', 'numero_cuenta' => '0012 3456-78']);
        $hoja = $this->leerPostulaciones([$fila], ['documento', 'numero_cuenta']);

        $this->assertSame('1020345678', $hoja->getCell('A2')->getValue());
        $this->assertSame('0012345678', $hoja->getCell('B2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $hoja->getCell('B2')->getDataType());
    }

    public function test_la_columna_documentos_usa_el_texto_del_resumen(): void
    {
        $hoja = $this->leerPostulaciones([$this->filaPostulacion(['documento_pedido' => 1])], ['documentos']);

        $this->assertSame('Documento pedido', $hoja->getCell('A2')->getValue());
    }

    public function test_un_dato_vacio_o_nulo_queda_como_celda_vacia(): void
    {
        $hoja = $this->leerPostulaciones([$this->filaPostulacion(['banco' => null, 'motivo' => ''])], ['banco', 'motivo']);

        $this->assertSame('', (string) $hoja->getCell('A2')->getValue());
        $this->assertSame('', (string) $hoja->getCell('B2')->getValue());
    }

    public function test_escribe_fila_por_fila_desde_un_generador(): void
    {
        $generador = (function (): \Generator {
            foreach (['1', '2', '3'] as $documento) {
                yield $this->filaPostulacion(['documento' => $documento]);
            }
        })();

        $hoja = $this->leerPostulaciones($generador, ['documento']);

        $this->assertSame(4, $hoja->getHighestRow());
        $this->assertSame('3', $hoja->getCell('A4')->getValue());
    }
}
