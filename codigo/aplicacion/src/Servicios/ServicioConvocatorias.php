<?php

/**
 * SERVICIO DE CONVOCATORIAS (gestión por el administrador)
 *
 * Paso 4 del recorrido: las reglas de negocio de crear, editar, filtrar y cambiar de estado
 * las convocatorias. El controlador le entrega datos ya validados. Las reglas de fondo (solo un
 * administrador, cupos no menores a las aprobadas, quién cerró y cuándo) las impone la base de
 * datos: aquí solo se traducen sus errores a mensajes que se pueden mostrar. No tiene SQL.
 *
 * Varias convocatorias pueden estar Activas a la vez: no hay ninguna regla que las limite.
 */

declare(strict_types=1);

namespace SIBEA\Servicios;

use PDOException;
use SIBEA\Nucleo\ErrorDeNegocio;
use SIBEA\Nucleo\TraductorErroresBd;
use SIBEA\Repositorios\RepositorioConvocatorias;

final class ServicioConvocatorias
{
    /** Pestaña de la lista => estados que muestra (null = todos). «Cerradas» reúne las dos formas de cierre. */
    private const FILTROS = [
        'todas' => null,
        'activas' => ['Activa'],
        'inactivas' => ['Inactiva'],
        'cerradas' => ['Cerrada', 'Cierre anticipado'],
    ];

    /** Acción de la pantalla => estado que queda. */
    private const ESTADO_POR_ACCION = [
        'activar' => 'Activa',
        'desactivar' => 'Inactiva',
        'cerrar' => 'Cerrada',
        'cerrar-anticipado' => 'Cierre anticipado',
    ];

    private const ESTADOS_CERRADOS = ['Cerrada', 'Cierre anticipado'];

    /**
     * @param RepositorioConvocatorias $convocatorias Donde se leen y guardan las convocatorias.
     */
    public function __construct(private readonly RepositorioConvocatorias $convocatorias)
    {
    }

    /**
     * La lista para una pestaña, con el contador de cada pestaña.
     *
     * @param string $filtro todas, activas, inactivas o cerradas (cualquier otra cosa se trata como «todas»).
     * @return array{filas: list<array<string, mixed>>, contadores: array<string, int>, filtro: string}
     *         Las filas del filtro (las abiertas primero), cuántas hay en cada pestaña y el filtro realmente usado.
     */
    public function listar(string $filtro): array
    {
        $filtro = array_key_exists($filtro, self::FILTROS) ? $filtro : 'todas';
        $todas = $this->convocatorias->listar();

        $contadores = [];
        foreach (array_keys(self::FILTROS) as $nombre) {
            $contadores[$nombre] = count($this->filtrar($todas, $nombre));
        }

        return ['filas' => $this->filtrar($todas, $filtro), 'contadores' => $contadores, 'filtro' => $filtro];
    }

    /**
     * @param int $id Id de la convocatoria.
     * @return array<string, mixed> Sus datos (con beneficio y cupos_usados).
     * @throws ErrorDeNegocio Si no existe.
     */
    public function convocatoria(int $id): array
    {
        return $this->convocatorias->buscar($id) ?? throw new ErrorDeNegocio('No encontramos esa convocatoria.');
    }

    /**
     * Crea una convocatoria Activa o Inactiva.
     *
     * @param array<string, mixed> $datos Los campos validados por ValidadorConvocatoria.
     * @return int El id de la convocatoria nueva.
     * @throws ErrorDeNegocio Si la base lo rechaza (por ejemplo, si quien actúa no es administrador).
     */
    public function crear(array $datos): int
    {
        try {
            return $this->convocatorias->crear($datos);
        } catch (PDOException $error) {
            throw TraductorErroresBd::traducir($error);
        }
    }

    /**
     * Cambia los datos de una convocatoria que todavía no se cerró (el estado no se toca aquí).
     *
     * @param int                  $id    Id de la convocatoria.
     * @param array<string, mixed> $datos Los campos validados por ValidadorConvocatoria.
     * @return void
     * @throws ErrorDeNegocio Si no existe, ya está cerrada o la base rechaza el cambio (p. ej. cupos por debajo de las aprobadas).
     */
    public function actualizar(int $id, array $datos): void
    {
        // Regla de negocio: una convocatoria cerrada ya no se edita (para eso se reabre desde la lista).
        if (in_array($this->convocatoria($id)['estado'], self::ESTADOS_CERRADOS, true)) {
            throw new ErrorDeNegocio('Una convocatoria cerrada ya no se puede editar.');
        }

        try {
            $this->convocatorias->actualizar($id, $datos);
        } catch (PDOException $error) {
            throw TraductorErroresBd::traducir($error);
        }
    }

    /**
     * Activa, desactiva o cierra una convocatoria.
     *
     * @param int         $id     Id de la convocatoria.
     * @param string      $accion activar, desactivar, cerrar o cerrar-anticipado.
     * @param string|null $motivo Obligatorio solo con cerrar-anticipado (en las demás acciones se ignora).
     * @return void
     * @throws ErrorDeNegocio Si la acción no existe, falta el motivo, la convocatoria no existe o la base rechaza el cambio.
     */
    public function cambiarEstado(int $id, string $accion, ?string $motivo): void
    {
        $estado = self::ESTADO_POR_ACCION[$accion] ?? throw new ErrorDeNegocio('Esa acción no existe.');
        $actual = $this->convocatoria($id);

        // Regla de negocio: una convocatoria ya cerrada no se vuelve a cerrar (quedaría con el cierre original y otro estado o motivo).
        // Para tocarla se reabre primero con activar o desactivar.
        if (in_array($actual['estado'], self::ESTADOS_CERRADOS, true) && in_array($estado, self::ESTADOS_CERRADOS, true)) {
            throw new ErrorDeNegocio('La convocatoria ya está cerrada. Reábrela antes de volver a cerrarla.');
        }

        // Regla de negocio: cerrar antes de la fecha de cierre deja escrito el motivo; en las demás acciones no se guarda ninguno.
        $motivo = $accion === 'cerrar-anticipado' ? trim((string) $motivo) : null;
        if ($motivo === '') {
            throw new ErrorDeNegocio('Escribe el motivo del cierre anticipado.');
        }

        try {
            $this->convocatorias->cambiarEstado($id, $estado, $motivo);
        } catch (PDOException $error) {
            throw TraductorErroresBd::traducir($error);
        }
    }

    /**
     * @param list<array<string, mixed>> $filas   Todas las convocatorias.
     * @param string                     $filtro  Una clave de FILTROS.
     * @return list<array<string, mixed>> Las que tienen alguno de los estados del filtro.
     */
    private function filtrar(array $filas, string $filtro): array
    {
        $estados = self::FILTROS[$filtro];
        if ($estados === null) {
            return $filas;
        }

        return array_values(array_filter($filas, static fn (array $fila): bool => in_array($fila['estado'], $estados, true)));
    }
}
