<?php

/**
 * FILTRO DE POSTULACIONES (objeto de valor)
 *
 * Paso 4 del recorrido: reúne los filtros de la lista de postulaciones del administrador (texto, beneficio,
 * programa, estado, fechas y convocatoria) ya revisados. Es la ÚNICA puerta por la que lo que viene de la URL
 * llega al repositorio: todo valor que no sirve (un estado inventado, un id que no es número, una fecha imposible)
 * se IGNORA en silencio, nunca falla. Así el repositorio solo recibe estados de una lista fija, números enteros
 * y fechas reales. El texto se entrega tal cual: el repositorio lo escapa para el LIKE.
 */

declare(strict_types=1);

namespace SIBEA\Servicios;

use DateTimeImmutable;
use SIBEA\Nucleo\Peticion;

final class FiltroPostulaciones
{
    /** Lista blanca: los estados que existen en la base (ENUM de postulaciones.estado). */
    public const ESTADOS = ['Pendiente', 'Validada', 'Aprobada', 'Rechazada'];

    /**
     * @param string      $texto          Nombre o documento a buscar (vacío = sin búsqueda).
     * @param int|null    $idBeneficio    Solo las de convocatorias de este beneficio.
     * @param int|null    $idPrograma     Solo aprendices de este programa de formación.
     * @param string|null $estado         Uno de ESTADOS.
     * @param string|null $desde          Fecha de envío mínima (AAAA-MM-DD, incluida).
     * @param string|null $hasta          Fecha de envío máxima (AAAA-MM-DD, incluida).
     * @param int|null    $idConvocatoria Solo las de esta convocatoria (viene del menú de la lista de convocatorias).
     */
    public function __construct(
        public readonly string $texto = '',
        public readonly ?int $idBeneficio = null,
        public readonly ?int $idPrograma = null,
        public readonly ?string $estado = null,
        public readonly ?string $desde = null,
        public readonly ?string $hasta = null,
        public readonly ?int $idConvocatoria = null,
    ) {
    }

    /**
     * Lee los filtros de la URL (?q=&beneficio=&programa=&estado=&desde=&hasta=&convocatoria=).
     *
     * @param Peticion $peticion La petición en curso.
     * @return FiltroPostulaciones Los filtros válidos; lo demás queda sin filtrar.
     */
    public static function desdePeticion(Peticion $peticion): FiltroPostulaciones
    {
        $estado = $peticion->consulta('estado');

        return new self(
            $peticion->consulta('q'),
            self::idPositivo($peticion->consulta('beneficio')),
            self::idPositivo($peticion->consulta('programa')),
            in_array($estado, self::ESTADOS, true) ? $estado : null,
            self::fechaValida($peticion->consulta('desde')),
            self::fechaValida($peticion->consulta('hasta')),
            self::idPositivo($peticion->consulta('convocatoria')),
        );
    }

    /**
     * Los filtros activos como los lleva la URL: sirve para conservarlos al pasar de página o al pedir el Excel.
     *
     * @return array<string, string> Solo los filtros que tienen valor.
     */
    public function comoConsulta(): array
    {
        return array_filter([
            'q' => $this->texto,
            'beneficio' => (string) $this->idBeneficio,
            'programa' => (string) $this->idPrograma,
            'estado' => (string) $this->estado,
            'desde' => (string) $this->desde,
            'hasta' => (string) $this->hasta,
            'convocatoria' => (string) $this->idConvocatoria,
        ], static fn (string $valor): bool => $valor !== '');
    }

    /** Un id es válido solo si son puros dígitos y es mayor que cero («abc», «-4», «1.5» o «2 OR 1=1» se ignoran). */
    private static function idPositivo(string $valor): ?int
    {
        if (preg_match('/^[0-9]{1,9}$/', $valor) !== 1 || (int) $valor < 1) {
            return null;
        }

        return (int) $valor;
    }

    /** Una fecha es válida solo si tiene la forma AAAA-MM-DD y el día existe (el 31 de febrero no). */
    private static function fechaValida(string $valor): ?string
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        $esReal = $fecha !== false && $fecha->format('Y-m-d') === $valor;

        return $esReal ? $valor : null;
    }
}
