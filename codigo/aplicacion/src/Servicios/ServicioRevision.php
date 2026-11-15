<?php

/**
 * SERVICIO DE REVISIÓN (revisar los documentos y decidir una postulación)
 *
 * Paso 4 del recorrido: es lo que hay detrás de la pantalla de detalle de una postulación. Hace tres cosas:
 *  1. Decide qué botones se pueden usar (acciones): las reglas están en métodos estáticos que no tocan la base,
 *     así se prueban sin ella.
 *  2. Guarda la revisión de cada documento («Válido» / «No válido» con su observación).
 *  3. Cambia el estado de la postulación: Confirmar datos (Validada), Aprobar (Aprobada) y Rechazar (Rechazada).
 *
 * Quién manda en cada regla:
 *  - Las TRANSICIONES de estado, los CUPOS, el motivo obligatorio al rechazar y «solo un administrador decide» los
 *    impone la BASE DE DATOS (trigger tr_postulacion_validar). Aquí no se copian: se confía en ella y se traduce su
 *    mensaje. Por eso, al aprobar NO se mira el cupo antes: si dos administradores aprueban a la vez la última plaza,
 *    la base deja pasar a uno y rechaza al otro, y mirar antes solo daría una falsa seguridad.
 *  - Lo que la base NO sabe (que la persona vio los tres documentos y los marcó válidos antes de confirmar, o que
 *    «No válido» lleve su observación) sí se comprueba aquí, antes de escribir.
 * No tiene SQL (está en los repositorios) ni HTML.
 *
 * No es "final" a propósito: las pruebas del controlador lo reemplazan por uno falso.
 */

declare(strict_types=1);

namespace SIBEA\Servicios;

use PDOException;
use SIBEA\Nucleo\ErrorDeNegocio;
use SIBEA\Nucleo\TraductorErroresBd;
use SIBEA\Repositorios\RepositorioDocumentos;
use SIBEA\Repositorios\RepositorioPostulaciones;

class ServicioRevision
{
    /** Cuántos documentos tiene toda postulación (identidad, certificado bancario y certificado de EPS). */
    public const TOTAL_DOCUMENTOS = 3;

    /** Las dos marcas que el administrador puede poner a un documento. «Rechazado» se muestra como «No válido». */
    private const MARCAS = ['Validado', 'Rechazado'];

    /** Largo máximo del motivo de un rechazo y de la observación de un documento. */
    private const LARGO_MAXIMO = 1000;

    private const MOTIVO_PRIMERO_CONFIRMA = 'Primero confirma los datos';
    private const MOTIVO_SIN_CUPOS = 'Ya no hay cupos en esta convocatoria';

    /** Con estas palabras empieza el mensaje que lanza la base cuando ya no quedan cupos (tr_postulacion_validar). */
    private const MENSAJE_BD_SIN_CUPOS = 'No hay cupos disponibles';

    /**
     * @param RepositorioPostulaciones $postulaciones Lee la postulación y guarda su estado.
     * @param RepositorioDocumentos    $documentos    Lee y marca los documentos.
     */
    public function __construct(
        private readonly RepositorioPostulaciones $postulaciones,
        private readonly RepositorioDocumentos $documentos,
    ) {
    }

    /**
     * Qué botones del panel de decisión están habilitados.
     *
     * @param array<string, mixed> $detalle Lo que entrega detalle(): estado, documentos (con estado_validacion), cupos y aprobadas.
     * @return array{confirmar: bool, aprobar: bool, motivoAprobarDeshabilitado: ?string}
     *         confirmar: solo si está Pendiente y sus 3 documentos están Validados. aprobar: solo si está Validada y
     *         quedan cupos. motivoAprobarDeshabilitado: por qué «Aprobar» no se puede (null si se puede o si la
     *         postulación ya está decidida).
     */
    public static function acciones(array $detalle): array
    {
        $estado = (string) ($detalle['estado'] ?? '');
        $hayCupos = (int) ($detalle['cupos'] ?? 0) > (int) ($detalle['aprobadas'] ?? 0);

        $motivo = match (true) {
            $estado === 'Pendiente' => self::MOTIVO_PRIMERO_CONFIRMA,
            $estado === 'Validada' && !$hayCupos => self::MOTIVO_SIN_CUPOS,
            default => null,
        };

        return [
            'confirmar' => $estado === 'Pendiente' && self::contarDocumentos($detalle, 'Validado') === self::TOTAL_DOCUMENTOS,
            'aprobar' => $estado === 'Validada' && $hayCupos,
            'motivoAprobarDeshabilitado' => $motivo,
        ];
    }

    /**
     * Por qué «Confirmar datos» está deshabilitado (para escribirlo debajo del botón).
     *
     * @param array<string, mixed> $detalle Lo mismo que acciones().
     * @return string|null El motivo; null si el botón se puede usar o si la postulación ya no está Pendiente.
     */
    public static function motivoConfirmarDeshabilitado(array $detalle): ?string
    {
        if (($detalle['estado'] ?? '') !== 'Pendiente' || self::acciones($detalle)['confirmar']) {
            return null;
        }
        if (self::contarDocumentos($detalle, 'Rechazado') > 0) {
            return 'Para confirmar, los 3 documentos deben estar en «Válido». Hay un documento no válido.';
        }

        return 'Para confirmar, los 3 documentos deben estar en «Válido». Aún hay documentos sin revisar.';
    }

    /**
     * ¿Esta postulación solo puede terminar en rechazo? Sí cuando sigue Pendiente y algún documento es no válido:
     * «pedir un documento nuevo» es de la fase 2, así que en el MVP no hay otra salida (spec 003, §9.2).
     *
     * @param array<string, mixed> $detalle Lo mismo que acciones().
     */
    public static function soloQuedaRechazar(array $detalle): bool
    {
        return ($detalle['estado'] ?? '') === 'Pendiente' && self::contarDocumentos($detalle, 'Rechazado') > 0;
    }

    /**
     * La postulación completa para la pantalla: sus datos, sus documentos, su historial y, si ya fue aprobada, su asignación.
     *
     * @param int $idPostulacion La postulación.
     * @return array<string, mixed>|null Null si no existe.
     */
    public function detalle(int $idPostulacion): ?array
    {
        $detalle = $this->postulaciones->detalle($idPostulacion);
        if ($detalle === null) {
            return null;
        }

        return $detalle + [
            'documentos' => $this->documentos->deLaPostulacion($idPostulacion),
            'historial' => $this->postulaciones->historial($idPostulacion),
            'asignacion' => ($detalle['estado'] ?? '') === 'Aprobada' ? $this->postulaciones->asignacion($idPostulacion) : null,
        ];
    }

    /**
     * Guarda la revisión de los documentos: todo junto o nada (una transacción).
     *
     * @param int $idPostulacion La postulación.
     * @param array<int, array{estado: string, observacion: string}> $revisiones Por id de documento: «Validado» o
     *        «Rechazado» y la observación (obligatoria si es «Rechazado»; al validar se descarta).
     * @return void
     * @throws ErrorDeNegocio Si la postulación no está Pendiente, un documento no es suyo, falta una observación
     *                        o la base rechaza el cambio.
     */
    public function marcarDocumentos(int $idPostulacion, array $revisiones): void
    {
        $detalle = $this->cargar($idPostulacion);
        // Regla de la aplicación: después de confirmar, los documentos ya no se vuelven a juzgar.
        if (($detalle['estado'] ?? '') !== 'Pendiente') {
            throw new ErrorDeNegocio('Los documentos solo se revisan mientras la postulación está Pendiente.');
        }
        $marcas = $this->prepararMarcas($detalle, $revisiones);

        try {
            $this->postulaciones->iniciarTransaccion();
            foreach ($marcas as $idDocumento => [$estado, $observacion]) {
                $this->documentos->marcarDocumento($idDocumento, $idPostulacion, $estado, $observacion);
            }
            $this->postulaciones->confirmarTransaccion();
        } catch (PDOException $error) {
            $this->postulaciones->revertirTransaccion();

            throw $this->traducir($error);
        }
    }

    /**
     * «Confirmar datos»: la postulación pasa de Pendiente a Validada.
     *
     * @param int $idPostulacion La postulación.
     * @return void
     * @throws ErrorDeNegocio Si no existe, ya no está Pendiente o algún documento no está en «Válido».
     */
    public function confirmar(int $idPostulacion): void
    {
        $detalle = $this->cargar($idPostulacion);
        // Regla de la aplicación (la base no revisa los documentos): los 3 deben estar Validados.
        if (!self::acciones($detalle)['confirmar']) {
            throw new ErrorDeNegocio(self::motivoConfirmarDeshabilitado($detalle) ?? 'Esta postulación ya no está Pendiente.');
        }

        $this->decidir($idPostulacion, 'Validada', null);
    }

    /**
     * «Aprobar»: de Validada a Aprobada. La base crea la asignación y avisa al aprendiz.
     *
     * @param int $idPostulacion La postulación.
     * @return void
     * @throws ErrorDeNegocio Si no existe, no está Validada o la base dice que ya no hay cupos
     *                        («Ya no hay cupos en esta convocatoria»).
     */
    public function aprobar(int $idPostulacion): void
    {
        $estado = (string) ($this->cargar($idPostulacion)['estado'] ?? '');
        if ($estado !== 'Validada') {
            throw new ErrorDeNegocio($estado === 'Pendiente' ? self::MOTIVO_PRIMERO_CONFIRMA : 'Esta postulación ya no está Validada.');
        }

        // Los cupos NO se miran aquí: los cuenta la base dentro del mismo UPDATE, con la convocatoria bloqueada.
        $this->decidir($idPostulacion, 'Aprobada', null);
    }

    /**
     * «Rechazar»: de Pendiente o Validada a Rechazada, con un motivo obligatorio.
     *
     * @param int    $idPostulacion La postulación.
     * @param string $motivo        Por qué se rechaza (de 1 a 1.000 caracteres; el aprendiz lo recibe en su aviso).
     * @return void
     * @throws ErrorDeNegocio Si falta el motivo, es muy largo, no existe o ya estaba aprobada o rechazada.
     */
    public function rechazar(int $idPostulacion, string $motivo): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new ErrorDeNegocio('Escribe el motivo del rechazo.');
        }
        if (mb_strlen($motivo) > self::LARGO_MAXIMO) {
            throw new ErrorDeNegocio('El motivo no puede pasar de ' . self::LARGO_MAXIMO . ' caracteres.');
        }
        if (!in_array($this->cargar($idPostulacion)['estado'] ?? '', ['Pendiente', 'Validada'], true)) {
            throw new ErrorDeNegocio('Esta postulación ya no se puede rechazar.');
        }

        $this->decidir($idPostulacion, 'Rechazada', $motivo);
    }

    /**
     * @return array<string, mixed> La postulación completa.
     * @throws ErrorDeNegocio Si no existe.
     */
    private function cargar(int $idPostulacion): array
    {
        return $this->detalle($idPostulacion) ?? throw new ErrorDeNegocio('No encontramos esa postulación.');
    }

    /**
     * Cuántos documentos de la postulación tienen ese estado de validación.
     *
     * @param array<string, mixed> $detalle
     */
    private static function contarDocumentos(array $detalle, string $estadoValidacion): int
    {
        $documentos = is_array($detalle['documentos'] ?? null) ? $detalle['documentos'] : [];

        return count(array_filter($documentos, static fn (mixed $documento): bool => is_array($documento) && ($documento['estado_validacion'] ?? '') === $estadoValidacion));
    }

    /**
     * Revisa lo que llegó del formulario y lo deja listo para guardar. Se revisa TODO antes de escribir algo.
     *
     * @param array<string, mixed> $detalle
     * @param array<int, array{estado: string, observacion: string}> $revisiones
     * @return array<int, array{0: string, 1: ?string}> Por id de documento: estado y observación (null si se valida).
     * @throws ErrorDeNegocio Si algo no cumple.
     */
    private function prepararMarcas(array $detalle, array $revisiones): array
    {
        $propios = array_map('intval', array_column(is_array($detalle['documentos'] ?? null) ? $detalle['documentos'] : [], 'id_documento'));
        $marcas = [];
        foreach ($revisiones as $idDocumento => $revision) {
            // SEGURIDAD: el id viene del navegador; solo se aceptan documentos de ESTA postulación.
            if (!in_array((int) $idDocumento, $propios, true)) {
                throw new ErrorDeNegocio('Uno de los documentos no pertenece a esta postulación.');
            }
            if (!in_array($revision['estado'], self::MARCAS, true)) {
                throw new ErrorDeNegocio('Marca cada documento como «Válido» o «No válido».');
            }
            $marcas[(int) $idDocumento] = [$revision['estado'], $this->observacionDe($revision)];
        }

        return $marcas;
    }

    /**
     * La observación que se guarda: obligatoria si el documento es no válido; si es válido, ninguna.
     *
     * @param array{estado: string, observacion: string} $revision
     * @throws ErrorDeNegocio Si falta o es muy larga.
     */
    private function observacionDe(array $revision): ?string
    {
        if ($revision['estado'] === 'Validado') {
            return null;
        }
        $observacion = trim($revision['observacion']);
        if ($observacion === '') {
            throw new ErrorDeNegocio('Escribe la observación de cada documento no válido.');
        }
        if (mb_strlen($observacion) > self::LARGO_MAXIMO) {
            throw new ErrorDeNegocio('La observación no puede pasar de ' . self::LARGO_MAXIMO . ' caracteres.');
        }

        return $observacion;
    }

    /** Guarda el cambio de estado; si la base lo rechaza, se muestra su motivo en lenguaje sencillo. */
    private function decidir(int $idPostulacion, string $estado, ?string $justificacion): void
    {
        try {
            $this->postulaciones->cambiarEstado($idPostulacion, $estado, $justificacion);
        } catch (PDOException $error) {
            throw $this->traducir($error);
        }
    }

    /**
     * El error de la base como un ErrorDeNegocio que se puede mostrar. El mensaje de «sin cupos» de la base
     * incluye números técnicos («1 aprobadas de 1 cupos»); a la persona se le dice solo lo esencial.
     */
    private function traducir(PDOException $error): ErrorDeNegocio
    {
        $errorDeNegocio = TraductorErroresBd::traducir($error);
        if (str_starts_with($errorDeNegocio->getMessage(), self::MENSAJE_BD_SIN_CUPOS)) {
            return new ErrorDeNegocio(self::MOTIVO_SIN_CUPOS, 0, $error);
        }

        return $errorDeNegocio;
    }
}
