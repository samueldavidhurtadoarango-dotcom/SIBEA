<?php

/**
 * EXPORTADOR DE EXCEL
 *
 * Paso 4 del recorrido: arma los archivos .xlsx que el administrador descarga.
 * Por ahora, el Excel de entrega de contraseñas temporales (las tareas siguientes
 * agregan los reportes). El archivo se escribe en la carpeta temporal del sistema
 * y quien lo pidió (el controlador) lo envía y lo borra: NUNCA queda guardado en el servidor.
 *
 * Seguridad (inyección de fórmulas): si una celda de texto empieza por = + - o @,
 * Excel podría ejecutarla como fórmula. Por eso TODO texto se escribe como texto explícito.
 */

declare(strict_types=1);

namespace BenefitRack\Servicios;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

final class ExportadorExcel
{
    private const ENCABEZADOS = ['Documento', 'Nombre completo', 'Correo', 'Contraseña temporal'];

    /**
     * Crea el Excel que Bienestar le entrega a cada aprendiz (documento, nombre, correo y contraseña).
     *
     * @param list<array{documento: string, nombre: string, correo: string, contrasena: string}> $filas Una por aprendiz.
     * @return string Ruta de un archivo temporal .xlsx (quien lo pidió lo envía y lo borra).
     */
    public function entregaContrasenas(array $filas): string
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Entrega');

        $this->escribirFila($hoja, 1, self::ENCABEZADOS);
        $hoja->getStyle('A1:D1')->getFont()->setBold(true);
        foreach ($filas as $indice => $fila) {
            $this->escribirFila($hoja, $indice + 2, [$fila['documento'], $fila['nombre'], $fila['correo'], $fila['contrasena']]);
        }
        foreach (['A', 'B', 'C', 'D'] as $columna) {
            $hoja->getColumnDimension($columna)->setAutoSize(true);
        }

        return $this->guardarTemporal($libro);
    }

    /**
     * Limpia un texto antes de escribirlo: quita del inicio los tabuladores, retornos de
     * carro y caracteres de control, que también sirven para colar fórmulas (guía OWASP).
     * La otra mitad de la defensa está en escribirFila(): todo se escribe como texto.
     *
     * @param string $v El texto original.
     * @return string El texto sin caracteres de control al inicio.
     */
    public static function textoSeguro(string $v): string
    {
        return ltrim($v, "\x00..\x1F");
    }

    /**
     * @param list<string> $valores Un valor por columna (A, B, C...).
     */
    private function escribirFila(Worksheet $hoja, int $numeroFila, array $valores): void
    {
        foreach ($valores as $posicion => $valor) {
            // setValueExplicit(..., TYPE_STRING): la celda es texto aunque empiece por "=", "+", "-" o "@",
            // y los documentos no pierden ceros ni se convierten en números.
            $hoja->getCell([$posicion + 1, $numeroFila])->setValueExplicit(self::textoSeguro($valor), DataType::TYPE_STRING);
        }
    }

    /** Guarda el libro en un archivo temporal del sistema y devuelve su ruta. */
    private function guardarTemporal(Spreadsheet $libro): string
    {
        // tempnam reserva un nombre único (que nadie más puede adivinar ni pisar); se le agrega la extensión.
        $reservado = tempnam(sys_get_temp_dir(), 'br_entrega_');
        if ($reservado === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal.');
        }
        $ruta = $reservado . '.xlsx';
        try {
            if (!rename($reservado, $ruta)) {
                throw new RuntimeException('No se pudo preparar el archivo temporal.');
            }
            (new Xlsx($libro))->save($ruta);
        } catch (Throwable $error) {
            // Si algo falla, no se deja ningún archivo (vacío o a medias) en la carpeta temporal.
            @unlink($reservado);
            @unlink($ruta);
            throw $error;
        }
        $libro->disconnectWorksheets();

        return $ruta;
    }
}
