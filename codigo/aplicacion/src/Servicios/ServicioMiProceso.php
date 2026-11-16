<?php

/**
 * SERVICIO «MI PROCESO» (lo que ve el aprendiz al entrar)
 *
 * Paso 4 del recorrido: junta lo que el aprendiz puede hacer (convocatorias abiertas a las que aún no se ha
 * postulado) con lo que ya hizo (sus postulaciones) y traduce el estado interno de cada postulación al estado
 * que muestra la pantalla. No toca la base: pide los datos a los repositorios y solo los ordena, así se puede
 * probar con repositorios falsos (no es "final" a propósito: las pruebas del controlador la reemplazan por una falsa).
 *
 * Reglas de la traducción (estado interno -> estado de la pantalla):
 *   Pendiente o Validada -> en-revision      (Bienestar todavía está trabajando en ella)
 *   Aprobada             -> seleccionado
 *   Rechazada            -> no-seleccionado
 * Una postulación rechazada NUNCA lleva el motivo interno (CA-2 de la spec 002): la justificación y las
 * observaciones son notas de Bienestar; al aprendiz solo se le dice, con amabilidad, que esta vez no quedó.
 */

declare(strict_types=1);

namespace SIBEA\Servicios;

use SIBEA\Repositorios\RepositorioConvocatorias;
use SIBEA\Repositorios\RepositorioPostulaciones;

class ServicioMiProceso
{
    /** Estado interno de la postulación -> estado que dibuja la pantalla. */
    private const ESTADO_VISTA = [
        'Pendiente' => 'en-revision',
        'Validada' => 'en-revision',
        'Aprobada' => 'seleccionado',
        'Rechazada' => 'no-seleccionado',
    ];

    /** Notas internas de Bienestar: se quitan siempre antes de entregar la postulación a la pantalla. */
    private const CAMPOS_INTERNOS = ['justificacion_rechazo', 'observaciones'];

    /**
     * @param RepositorioConvocatorias $convocatorias Las convocatorias abiertas para el aprendiz.
     * @param RepositorioPostulaciones $postulaciones Las postulaciones del aprendiz.
     */
    public function __construct(
        private readonly RepositorioConvocatorias $convocatorias,
        private readonly RepositorioPostulaciones $postulaciones,
    ) {
    }

    /**
     * Arma todo lo que necesita la pantalla «Mi proceso».
     *
     * @param int $idAprendiz Id del aprendiz de la sesión.
     * @return array{estado: string, abiertas: list<array<string, mixed>>, postulaciones: list<array<string, mixed>>}
     *         abiertas: convocatorias abiertas SIN postulación del aprendiz. postulaciones: las suyas, cada una con
     *         estadoVista y pasos. estado: «sin-postulacion» si no hay nada que mostrar; si no, «con-actividad».
     */
    public function resumen(int $idAprendiz): array
    {
        // Si ya se postuló a una convocatoria, esa convocatoria sale como postulación y no como oferta.
        $abiertas = array_values(array_filter(
            $this->convocatorias->abiertasPara($idAprendiz),
            static fn (array $convocatoria): bool => $convocatoria['id_postulacion'] === null
        ));
        $postulaciones = array_map(
            fn (array $postulacion): array => $this->paraLaPantalla($postulacion),
            $this->postulaciones->delAprendiz($idAprendiz)
        );

        return [
            'estado' => $abiertas === [] && $postulaciones === [] ? 'sin-postulacion' : 'con-actividad',
            'abiertas' => $abiertas,
            'postulaciones' => $postulaciones,
        ];
    }

    /**
     * Quita las notas internas y agrega el estado de la pantalla y los pasos de avance.
     *
     * @param array<string, mixed> $postulacion Una fila de RepositorioPostulaciones::delAprendiz().
     * @return array<string, mixed>
     */
    private function paraLaPantalla(array $postulacion): array
    {
        $estado = (string) $postulacion['estado'];
        $postulacion = array_diff_key($postulacion, array_flip(self::CAMPOS_INTERNOS));

        return $postulacion + ['estadoVista' => self::ESTADO_VISTA[$estado], 'pasos' => $this->pasos($estado, $postulacion)];
    }

    /**
     * Los tres pasos de la línea de avance: Enviada, Validada y Aprobada. Cada uno es «hecho», «actual» (el que
     * está en curso) o «pendiente». Una postulación rechazada no usa los pasos (su pantalla es otra), pero
     * se calculan igual para no tener un caso especial.
     *
     * @param array<string, mixed> $postulacion Con fecha_envio, fecha_validacion y fecha_resultado.
     * @return list<array{nombre: string, avance: string, fecha: string|null}>
     */
    private function pasos(string $estado, array $postulacion): array
    {
        // Cuántos pasos ya están hechos según el estado interno.
        $hechos = match ($estado) {
            'Pendiente', 'Rechazada' => 1,
            'Validada' => 2,
            default => 3,
        };
        $fechas = [$postulacion['fecha_envio'], $postulacion['fecha_validacion'], $postulacion['fecha_resultado']];
        $pasos = [];
        foreach (['Enviada', 'Validada', 'Aprobada'] as $indice => $nombre) {
            $pasos[] = [
                'nombre' => $nombre,
                'avance' => $indice < $hechos ? 'hecho' : ($indice === $hechos ? 'actual' : 'pendiente'),
                'fecha' => $indice < $hechos ? $fechas[$indice] : null,
            ];
        }

        return $pasos;
    }
}
