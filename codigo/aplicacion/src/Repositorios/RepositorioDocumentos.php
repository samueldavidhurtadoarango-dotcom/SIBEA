<?php

/**
 * REPOSITORIO DE DOCUMENTOS
 *
 * Paso 4 del recorrido: aquí vive el SQL de la tabla "documentos_postulacion": guardar un documento de la
 * postulación, buscar uno junto con el dueño de su postulación (para decidir quién puede verlo), listar los de una
 * postulación y marcar uno como Validado o Rechazado.
 *
 * La base impone: el documento nace Pendiente, solo lo sube el dueño de la postulación o un administrador
 * (tr_documento_validar_insercion) y el tamaño va de más de 0 a 5 MB (CHECK). Al marcarlo, el trigger
 * tr_validacion_documento exige un administrador y guarda él mismo quién y cuándo (la aplicación nunca escribe
 * esas columnas).
 *
 * No es "final" a propósito: las pruebas la reemplazan por una falsa.
 */

declare(strict_types=1);

namespace SIBEA\Repositorios;

use PDOException;
use SIBEA\Nucleo\BaseDatos;

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

    /**
     * Los documentos de una postulación, con el nombre de su tipo, en el orden en que se subieron.
     *
     * @param int $idPostulacion La postulación.
     * @return list<array<string, mixed>> id_documento, tipo, nombre_archivo, extension, tamano_mb, estado_validacion
     *                                    (Pendiente, Validado o Rechazado) y observaciones.
     */
    public function deLaPostulacion(int $idPostulacion): array
    {
        $consulta = $this->baseDatos->pdo()->prepare(
            'SELECT d.id_documento, t.nombre AS tipo, d.nombre_archivo, d.extension, d.tamano_mb, d.estado_validacion, d.observaciones
               FROM documentos_postulacion d
               JOIN tipos_documento t ON t.id_tipo_documento = d.id_tipo_documento
              WHERE d.id_postulacion = ?
              ORDER BY d.id_documento'
        );
        $consulta->execute([$idPostulacion]);

        return array_values($consulta->fetchAll());
    }

    /**
     * Marca un documento como Validado o Rechazado. El WHERE incluye la postulación: así un documento de otra
     * postulación nunca se toca, aunque su id llegue por error o a propósito.
     *
     * @param int         $idDocumento   El documento.
     * @param int         $idPostulacion La postulación a la que debe pertenecer.
     * @param string      $estado        Validado o Rechazado.
     * @param string|null $observacion   Por qué es no válido (null al validar: se borra la observación anterior).
     * @return void
     * @throws PDOException Si la base rechaza el cambio (por ejemplo, si quien lo hace no es administrador).
     */
    public function marcarDocumento(int $idDocumento, int $idPostulacion, string $estado, ?string $observacion): void
    {
        $this->baseDatos->pdo()->prepare(
            'UPDATE documentos_postulacion SET estado_validacion = ?, observaciones = ? WHERE id_documento = ? AND id_postulacion = ?'
        )->execute([$estado, $observacion, $idDocumento, $idPostulacion]);
    }
}
