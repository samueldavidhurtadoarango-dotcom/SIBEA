<?php

/**
 * REPOSITORIO DE MIGRACIONES
 *
 * Paso 4 del recorrido: aquí vive el SQL de la tabla "migraciones", donde queda el rastro de cada
 * carga de datos desde Excel (qué archivo, cuántas filas salieron bien y cuáles fallaron).
 *
 * Reglas que pone la BASE (triggers), no esta clase: solo un administrador registra o modifica una
 * migración; quien la hizo sale de @app_id_usuario; las fechas las pone la base; y una migración
 * terminada ya no se puede modificar. Por eso se crea «En proceso» y se cierra UNA sola vez.
 */

declare(strict_types=1);

namespace SIBEA\Repositorios;

use SIBEA\Nucleo\BaseDatos;

final class RepositorioMigraciones
{
    /**
     * @param BaseDatos $baseDatos La conexión de esta petición (con @app_id_usuario = el administrador).
     */
    public function __construct(private readonly BaseDatos $baseDatos)
    {
    }

    /**
     * Abre el registro de una carga, «En proceso».
     *
     * @param string $entidadDestino La tabla a la que se cargan los datos (por ejemplo «usuarios»).
     * @param string $nombreArchivo  El nombre del Excel subido.
     * @param int    $total          Cuántas filas se van a intentar crear.
     * @return int El id de la migración.
     */
    public function iniciar(string $entidadDestino, string $nombreArchivo, int $total): int
    {
        $this->baseDatos->pdo()->prepare(
            'INSERT INTO migraciones (nombre_archivo, entidad_destino, total_registros, estado) VALUES (?, ?, ?, \'En proceso\')'
        )->execute([$nombreArchivo, $entidadDestino, $total]);

        return (int) $this->baseDatos->pdo()->lastInsertId();
    }

    /**
     * Cierra la carga con sus resultados (después ya no se puede modificar).
     *
     * @param int                                                       $id        La migración.
     * @param int                                                       $exitosos  Filas creadas.
     * @param int                                                       $fallidos  Filas que no se pudieron crear.
     * @param string                                                    $estado    Completada, Completada con errores o Fallida.
     * @param list<array{fila: int, campo: string, motivo: string}>     $detalle   Los errores (se guardan como JSON).
     * @return void
     */
    public function finalizar(int $id, int $exitosos, int $fallidos, string $estado, array $detalle): void
    {
        $json = $detalle === [] ? null : json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->baseDatos->pdo()->prepare(
            'UPDATE migraciones SET registros_exitosos = ?, registros_fallidos = ?, estado = ?, detalle_errores = ? WHERE id_migracion = ?'
        )->execute([$exitosos, $fallidos, $estado, $json, $id]);
    }
}
