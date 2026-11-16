<?php

/**
 * REPOSITORIO DE CONVOCATORIAS
 *
 * Paso 4 del recorrido: aquí vive el SQL de la tabla "convocatorias" (listar, buscar, crear,
 * editar y cambiar de estado) y la consulta de las convocatorias abiertas para un aprendiz.
 *
 * Las reglas de fondo las impone la BASE DE DATOS con sus triggers: solo un administrador
 * crea o modifica (usa @app_id_usuario de la conexión), los cupos no bajan de las aprobadas
 * y quién cerró y cuándo lo anota ella misma. Esta clase solo guarda y lee.
 *
 * No es "final" a propósito: las pruebas de otras clases pueden reemplazarla por una falsa.
 */

declare(strict_types=1);

namespace SIBEA\Repositorios;

use SIBEA\Nucleo\BaseDatos;

class RepositorioConvocatorias
{
    /** Columnas que ve la pantalla, con el beneficio y los cupos ya usados (postulaciones Aprobadas). */
    private const SELECCION = "SELECT c.id_convocatoria, c.nombre, c.id_beneficio, b.nombre AS beneficio,
               c.fecha_inicio, c.fecha_cierre, c.cupos, c.requisitos, c.valor_pago, c.numero_pagos,
               c.duracion_meses, c.estado, c.motivo_cierre, c.fecha_cierre_real,
               (SELECT COUNT(*) FROM postulaciones p
                 WHERE p.id_convocatoria = c.id_convocatoria AND p.estado = 'Aprobada') AS cupos_usados
          FROM convocatorias c
          JOIN beneficios b ON b.id_beneficio = c.id_beneficio";

    /**
     * @param BaseDatos $baseDatos La conexión de esta petición (la base sabe qué administrador actúa).
     */
    public function __construct(private readonly BaseDatos $baseDatos)
    {
    }

    /**
     * Todas las convocatorias: las Activas primero, luego las Inactivas y al final las cerradas;
     * dentro de cada grupo, las de inicio más reciente primero.
     *
     * @return list<array<string, mixed>> Columnas de SELECCION; cupos_usados = postulaciones Aprobadas.
     */
    public function listar(): array
    {
        $consulta = $this->baseDatos->pdo()->query(
            self::SELECCION . " ORDER BY FIELD(c.estado, 'Activa', 'Inactiva', 'Cierre anticipado', 'Cerrada'),
                                         c.fecha_inicio DESC, c.id_convocatoria DESC"
        );

        return $consulta === false ? [] : $consulta->fetchAll();
    }

    /**
     * @param int $id Id de la convocatoria.
     * @return array<string, mixed>|null Las mismas columnas que listar(); null si no existe.
     */
    public function buscar(int $id): ?array
    {
        $consulta = $this->baseDatos->pdo()->prepare(self::SELECCION . ' WHERE c.id_convocatoria = ?');
        $consulta->execute([$id]);
        $fila = $consulta->fetch();

        return is_array($fila) ? $fila : null;
    }

    /**
     * Crea una convocatoria. Si quien actúa no es un administrador, el trigger de la base lanza
     * una PDOException (SQLSTATE 45000): el servicio la traduce.
     *
     * @param array<string, mixed> $d nombre, id_beneficio, fecha_inicio, fecha_cierre, cupos, requisitos,
     *                                valor_pago, numero_pagos, duracion_meses y estado (Activa o Inactiva), ya validados.
     * @return int El id de la convocatoria nueva.
     */
    public function crear(array $d): int
    {
        $this->baseDatos->pdo()->prepare(
            'INSERT INTO convocatorias (nombre, id_beneficio, fecha_inicio, fecha_cierre, cupos, requisitos,
                                        valor_pago, numero_pagos, duracion_meses, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $d['nombre'], $d['id_beneficio'], $d['fecha_inicio'], $d['fecha_cierre'], $d['cupos'], $d['requisitos'],
            $d['valor_pago'], $d['numero_pagos'], $d['duracion_meses'], $d['estado'],
        ]);

        return (int) $this->baseDatos->pdo()->lastInsertId();
    }

    /**
     * Cambia los datos de una convocatoria (no su estado: eso es cambiarEstado()).
     *
     * @param int                  $id Id de la convocatoria.
     * @param array<string, mixed> $d  Los mismos campos de crear() (el estado se ignora).
     * @return void
     */
    public function actualizar(int $id, array $d): void
    {
        $this->baseDatos->pdo()->prepare(
            'UPDATE convocatorias
                SET nombre = ?, id_beneficio = ?, fecha_inicio = ?, fecha_cierre = ?, cupos = ?, requisitos = ?,
                    valor_pago = ?, numero_pagos = ?, duracion_meses = ?
              WHERE id_convocatoria = ?'
        )->execute([
            $d['nombre'], $d['id_beneficio'], $d['fecha_inicio'], $d['fecha_cierre'], $d['cupos'], $d['requisitos'],
            $d['valor_pago'], $d['numero_pagos'], $d['duracion_meses'], $id,
        ]);
    }

    /**
     * Pone el estado nuevo. Al cerrar, la base anota sola quién cerró y cuándo (fecha_cierre_real
     * y usuario_cierre); al reabrir, los borra. El motivo sí lo guardamos aquí.
     *
     * @param int         $id     Id de la convocatoria.
     * @param string      $estado Activa, Inactiva, Cerrada o Cierre anticipado.
     * @param string|null $motivo El motivo del cierre anticipado; null en los demás casos (así se borra el de un cierre anterior).
     * @return void
     */
    public function cambiarEstado(int $id, string $estado, ?string $motivo): void
    {
        $this->baseDatos->pdo()->prepare(
            'UPDATE convocatorias SET estado = ?, motivo_cierre = ? WHERE id_convocatoria = ?'
        )->execute([$estado, $motivo, $id]);
    }

    /**
     * Las convocatorias a las que un aprendiz puede postularse hoy: Activas y con la fecha de hoy
     * entre el inicio y el cierre. Trae el id de la postulación del aprendiz si ya se postuló.
     * La usa la pantalla «Mi proceso» del aprendiz.
     *
     * @param int $idAprendiz Id del aprendiz.
     * @return list<array<string, mixed>> id_convocatoria, nombre, beneficio, fechas, cupos, requisitos, valor_pago,
     *                                    numero_pagos, duracion_meses e id_postulacion (null si todavía no se postuló).
     */
    public function abiertasPara(int $idAprendiz): array
    {
        $consulta = $this->baseDatos->pdo()->prepare(
            "SELECT c.id_convocatoria, c.nombre, b.nombre AS beneficio, c.fecha_inicio, c.fecha_cierre, c.cupos,
                    c.requisitos, c.valor_pago, c.numero_pagos, c.duracion_meses, p.id_postulacion
               FROM convocatorias c
               JOIN beneficios b ON b.id_beneficio = c.id_beneficio
               LEFT JOIN postulaciones p ON p.id_convocatoria = c.id_convocatoria AND p.id_usuario = ?
              WHERE c.estado = 'Activa' AND CURDATE() BETWEEN c.fecha_inicio AND c.fecha_cierre
              ORDER BY c.fecha_cierre, c.id_convocatoria"
        );
        $consulta->execute([$idAprendiz]);

        return $consulta->fetchAll();
    }

    /**
     * ¿Se puede postular hoy a esta convocatoria? Misma regla que abiertasPara() y que el trigger de la base:
     * estado Activa y la fecha de hoy (la de la base) entre el inicio y el cierre.
     *
     * @param int $id Id de la convocatoria.
     * @return bool true si está abierta; false si está cerrada, inactiva, fuera de fechas o no existe.
     */
    public function abierta(int $id): bool
    {
        $consulta = $this->baseDatos->pdo()->prepare(
            "SELECT 1 FROM convocatorias
              WHERE id_convocatoria = ? AND estado = 'Activa' AND CURDATE() BETWEEN fecha_inicio AND fecha_cierre"
        );
        $consulta->execute([$id]);

        return $consulta->fetchColumn() !== false;
    }

    /**
     * @param int $idConvocatoria Id de la convocatoria.
     * @param int $idAprendiz     Id del aprendiz.
     * @return int|null El id de la postulación del aprendiz a esa convocatoria; null si todavía no se postuló.
     */
    public function idPostulacionDe(int $idConvocatoria, int $idAprendiz): ?int
    {
        $consulta = $this->baseDatos->pdo()->prepare('SELECT id_postulacion FROM postulaciones WHERE id_convocatoria = ? AND id_usuario = ?');
        $consulta->execute([$idConvocatoria, $idAprendiz]);
        $id = $consulta->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}
