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
}
