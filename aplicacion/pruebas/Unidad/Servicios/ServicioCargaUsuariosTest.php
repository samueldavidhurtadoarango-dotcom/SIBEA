<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Servicios;

use BenefitRack\Nucleo\ErrorDeNegocio;
use BenefitRack\Pruebas\Integracion\BaseDePrueba;
use BenefitRack\Repositorios\RepositorioCatalogos;
use BenefitRack\Repositorios\RepositorioMigraciones;
use BenefitRack\Repositorios\RepositorioUsuarios;
use BenefitRack\Servicios\ServicioCargaUsuarios;
use BenefitRack\Servicios\ServicioUsuarios;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Lectura del Excel de aprendices (CA-5). Usa la base temporal solo para conocer los programas;
 * los archivos .xlsx se crean aquí mismo y se borran al terminar cada prueba.
 */
final class ServicioCargaUsuariosTest extends BaseDePrueba
{
    /** @var list<string> Archivos temporales creados por la prueba (se borran en tearDown). */
    private array $archivos = [];

    protected function tearDown(): void
    {
        foreach ($this->archivos as $ruta) {
            @unlink($ruta);
        }
    }

    private function servicio(): ServicioCargaUsuarios
    {
        $bd = $this->baseDatosDeLaApp();

        return new ServicioCargaUsuarios(
            new ServicioUsuarios(new RepositorioUsuarios($bd)),
            new RepositorioMigraciones($bd),
            new RepositorioCatalogos($bd)
        );
    }

    private function programa(): string
    {
        return (string) (new RepositorioCatalogos($this->baseDatosDeLaApp()))->programas()[0]['nombre'];
    }

    /**
     * @param list<list<string>> $filas Primera fila = encabezados; todo se escribe como texto.
     */
    private function excel(array $filas): string
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        foreach ($filas as $i => $fila) {
            foreach ($fila as $j => $valor) {
                $hoja->getCell([$j + 1, $i + 1])->setValueExplicit($valor, DataType::TYPE_STRING);
            }
        }
        $ruta = tempnam(sys_get_temp_dir(), 'br_prueba_') . '.xlsx';
        (new Xlsx($libro))->save($ruta);
        $this->archivos[] = $ruta;

        return $ruta;
    }

    /** @return list<string> */
    private function encabezados(): array
    {
        return ['Documento', 'Nombre', 'Apellido', 'Correo', 'Programa', 'Ficha'];
    }

    public function test_lee_cuatro_filas_y_separa_validas_de_errores_contando_desde_la_fila_2(): void
    {
        $p = $this->programa();
        $ruta = $this->excel([
            $this->encabezados(),
            ['1020345600', '  Ana  ', 'Peña', 'ana@soy.sena.edu.co', $p, '2758341'],
            ['1.020.345.678', 'Luis', 'Rojas', 'luis@soy.sena.edu.co', $p, '2758341'],
            ['1020345678', 'Otro', 'Repetido', 'otro@soy.sena.edu.co', $p, '2758341'],
            ['1020345999', 'Sin', 'Correo', '', $p, '2758341'],
        ]);

        $lectura = $this->servicio()->leer($ruta);

        $this->assertCount(2, $lectura['filas']);
        $this->assertSame('Ana', $lectura['filas'][0]['nombre']);
        $this->assertSame('Peña', $lectura['filas'][0]['apellido']);
        $this->assertSame('1020345678', $lectura['filas'][1]['documento']);
        $this->assertSame([4 => ['documento' => 'Documento repetido en el archivo'], 5 => ['correo' => 'Falta el correo']], $lectura['errores']);
    }

    public function test_un_correo_repetido_en_el_archivo_es_error(): void
    {
        $p = $this->programa();
        $ruta = $this->excel([
            $this->encabezados(),
            ['1020345601', 'A', 'B', 'mismo@soy.sena.edu.co', $p, '1'],
            ['1020345602', 'C', 'D', 'MISMO@soy.sena.edu.co', $p, '1'],
        ]);

        $lectura = $this->servicio()->leer($ruta);

        $this->assertCount(1, $lectura['filas']);
        $this->assertSame([3 => ['correo' => 'Correo repetido en el archivo']], $lectura['errores']);
    }

    public function test_las_filas_en_blanco_se_ignoran(): void
    {
        $p = $this->programa();
        $ruta = $this->excel([
            $this->encabezados(),
            ['', '', '', '', '', ''],
            ['1020345603', 'A', 'B', 'a3@soy.sena.edu.co', $p, '1'],
        ]);

        $lectura = $this->servicio()->leer($ruta);

        $this->assertCount(1, $lectura['filas']);
        $this->assertSame([], $lectura['errores']);
        $this->assertSame(3, $lectura['filas'][0]['fila']);
    }

    public function test_un_txt_renombrado_xlsx_lanza_error_de_negocio(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'br_prueba_') . '.xlsx';
        file_put_contents($ruta, 'esto no es un excel');
        $this->archivos[] = $ruta;

        $this->expectException(ErrorDeNegocio::class);
        $this->servicio()->leer($ruta);
    }

    public function test_si_falta_la_columna_correo_el_error_la_nombra(): void
    {
        $ruta = $this->excel([['Documento', 'Nombre', 'Apellido', 'Programa', 'Ficha'], ['1', 'a', 'b', 'c', '1']]);

        try {
            $this->servicio()->leer($ruta);
            $this->fail('Debía lanzar ErrorDeNegocio.');
        } catch (ErrorDeNegocio $error) {
            $this->assertStringContainsString('Correo', $error->getMessage());
        }
    }

    public function test_mas_de_2000_filas_se_rechaza(): void
    {
        $filas = [$this->encabezados()];
        for ($i = 0; $i < 2001; $i++) {
            $filas[] = ['1', 'a', 'b', 'c', 'd', '1'];
        }

        $this->expectException(ErrorDeNegocio::class);
        $this->servicio()->leer($this->excel($filas));
    }
}
