<?php

/**
 * FILTRO DE LECTURA DEL EXCEL DE CARGA
 *
 * Paso 4 del recorrido: le dice a PhpSpreadsheet qué celdas vale la pena leer ANTES de cargarlas en
 * memoria: solo las primeras filas (encabezado + el máximo de aprendices + 1 para notar que se pasó) y
 * las columnas de la plantilla. Así una celda perdida en la columna XFD o en la fila 1 000 000 no hace
 * crecer la memoria: simplemente se ignora.
 */

declare(strict_types=1);

namespace BenefitRack\Servicios;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

final class FiltroLecturaCarga implements IReadFilter
{
    private bool $huboFilasDeMas = false;

    /**
     * @param int $ultimaFila    La última fila que se lee.
     * @param int $ultimaColumna La última columna que se lee (1 = A, 26 = Z).
     */
    public function __construct(private readonly int $ultimaFila, private readonly int $ultimaColumna)
    {
    }

    /**
     * @param string $columnAddress Letra de la columna (A, B... XFD).
     * @param int    $row           Número de fila.
     * @param string $worksheetName Nombre de la hoja (no importa aquí).
     * @return bool true si la celda se lee.
     */
    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        if ($row > $this->ultimaFila) {
            // No se lee, pero se anota: el archivo trae más filas de las permitidas.
            $this->huboFilasDeMas = true;

            return false;
        }

        return Coordinate::columnIndexFromString($columnAddress) <= $this->ultimaColumna;
    }

    /**
     * @return bool true si el archivo tenía celdas más abajo de la última fila permitida.
     */
    public function huboFilasDeMas(): bool
    {
        return $this->huboFilasDeMas;
    }
}
