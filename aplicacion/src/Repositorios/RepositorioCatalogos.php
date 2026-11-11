<?php

/**
 * REPOSITORIO DE CATÁLOGOS
 *
 * Paso 4 del recorrido: aquí vive el SQL de las listas fijas que llenan los
 * desplegables (programas, beneficios, bancos, EPS y tipos de cuenta). Las
 * carga el script datos_iniciales.sql. Solo devuelve los registros activos.
 */

declare(strict_types=1);

namespace BenefitRack\Repositorios;

use BenefitRack\Nucleo\BaseDatos;

final class RepositorioCatalogos
{
    /**
     * @param BaseDatos $baseDatos La conexión de esta petición.
     */
    public function __construct(private readonly BaseDatos $baseDatos)
    {
    }

    /**
     * @return list<array{id: int, nombre: string}> Programas de formación activos, por nombre.
     */
    public function programas(): array
    {
        return $this->activos('programas', 'id_programa', true);
    }

    /**
     * @return list<array{id: int, nombre: string}> Beneficios activos, por nombre.
     */
    public function beneficios(): array
    {
        return $this->activos('beneficios', 'id_beneficio', true);
    }

    /**
     * @return list<array{id: int, nombre: string}> Bancos activos, por nombre.
     */
    public function bancos(): array
    {
        return $this->activos('entidades_bancarias', 'id_banco', true);
    }

    /**
     * @return list<array{id: int, nombre: string}> EPS activas, por nombre.
     */
    public function eps(): array
    {
        return $this->activos('eps', 'id_eps', true);
    }

    /**
     * @return list<array{id: int, nombre: string}> Tipos de cuenta (la tabla no tiene estado: todos están activos).
     */
    public function tiposCuenta(): array
    {
        return $this->activos('tipos_cuenta', 'id_tipo_cuenta', false);
    }

    /**
     * Lee una lista fija. La tabla y la columna las escribe el programador en este
     * archivo (nunca vienen de una persona), por eso se pueden poner en el SQL.
     *
     * @param string $tabla       Nombre de la tabla.
     * @param string $columnaId   Columna con el id.
     * @param bool   $tieneEstado true si la tabla tiene la columna "estado".
     * @return list<array{id: int, nombre: string}>
     */
    private function activos(string $tabla, string $columnaId, bool $tieneEstado): array
    {
        $filtro = $tieneEstado ? "WHERE estado = 'Activo'" : '';
        $filas = $this->baseDatos->pdo()
            ->query("SELECT $columnaId AS id, nombre FROM $tabla $filtro ORDER BY nombre")
            ->fetchAll();

        return array_map(
            static fn (array $fila): array => ['id' => (int) $fila['id'], 'nombre' => (string) $fila['nombre']],
            $filas
        );
    }
}
