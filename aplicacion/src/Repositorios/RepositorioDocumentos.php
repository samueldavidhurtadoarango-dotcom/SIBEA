<?php

/**
 * REPOSITORIO DE DOCUMENTOS
 *
 * Paso 4 del recorrido: aquí vive el SQL de la tabla "documentos_postulacion": guardar un documento de la
 * postulación y buscar uno junto con el dueño de su postulación (para decidir quién puede verlo).
 *
 * La base impone: el documento nace Pendiente, solo lo sube el dueño de la postulación o un administrador
 * (tr_documento_validar_insercion) y el tamaño va de más de 0 a 5 MB (CHECK).
 *
 * No es "final" a propósito: las pruebas la reemplazan por una falsa.
 */

declare(strict_types=1);

namespace BenefitRack\Repositorios;

use BenefitRack\Nucleo\BaseDatos;

class RepositorioDocumentos
{
    /**
     * @param BaseDatos $baseDatos La conexión de esta petición.
     */
    public function __construct(private readonly BaseDatos $baseDatos)
    {
    }

    /**
     * Guarda un documento de una postulación.
     *
     * @param int    $idPostulacion   La postulación.
     * @param int    $idTipoDocumento 1 identidad, 2 certificado bancario, 4 certificado de EPS.
     * @param string $nombreArchivo   Nombre original, ya limpio (solo para mostrarlo).
     * @param string $rutaArchivo     Ruta relativa en almacenamiento («documentos/<32 hex>.<ext>»).
     * @param string $extension       pdf, jpg o png.
     * @param string $tamanoMb        Tamaño en MB con dos decimales («1.44»), de 0.01 a 5.00.
     * @return int El id del documento nuevo.
     */
    public function crear(int $idPostulacion, int $idTipoDocumento, string $nombreArchivo, string $rutaArchivo, string $extension, string $tamanoMb): int
    {
        $this->baseDatos->pdo()->prepare(
            'INSERT INTO documentos_postulacion (id_postulacion, id_tipo_documento, nombre_archivo, ruta_archivo, extension, tamano_mb)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$idPostulacion, $idTipoDocumento, $nombreArchivo, $rutaArchivo, $extension, $tamanoMb]);

        return (int) $this->baseDatos->pdo()->lastInsertId();
    }

    /**
     * Un documento con el aprendiz dueño de su postulación.
     *
     * @param int $idDocumento Id del documento.
     * @return array<string, mixed>|null id_documento, id_postulacion, nombre_archivo, ruta_archivo, extension e
     *                                   id_usuario (el dueño de la postulación); null si no existe.
     */
    public function buscarConDueno(int $idDocumento): ?array
    {
        $consulta = $this->baseDatos->pdo()->prepare(
            'SELECT d.id_documento, d.id_postulacion, d.nombre_archivo, d.ruta_archivo, d.extension, p.id_usuario
               FROM documentos_postulacion d
               JOIN postulaciones p ON p.id_postulacion = d.id_postulacion
              WHERE d.id_documento = ?'
        );
        $consulta->execute([$idDocumento]);
        $fila = $consulta->fetch();

        return is_array($fila) ? $fila : null;
    }
}
