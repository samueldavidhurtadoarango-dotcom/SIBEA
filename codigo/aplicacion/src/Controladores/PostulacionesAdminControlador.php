<?php

/**
 * CONTROLADOR DE POSTULACIONES DEL ADMINISTRADOR
 *
 * Paso 4 del recorrido: atiende /admin/postulaciones (la pantalla de inicio del administrador), /admin/postulaciones/excel
 * y el detalle de cada postulación con sus decisiones (/admin/postulaciones/{id} y sus cuatro POST: revisar documentos,
 * confirmar, aprobar y rechazar). Lee los filtros de la URL con FiltroPostulaciones, pide las filas al repositorio y
 * elige la pantalla o la descarga; las reglas de la revisión están en ServicioRevision. No tiene SQL ni HTML.
 *
 * Toda decisión sigue el patrón Post/Redirect/Get: se hace el cambio, se deja un aviso de una sola vez y se vuelve al
 * detalle. Así, recargar la página no repite la decisión, y un error de negocio («Ya no hay cupos en esta
 * convocatoria») se ve como una alerta, nunca como una pantalla de error técnico.
 */

declare(strict_types=1);

namespace SIBEA\Controladores;

use SIBEA\Nucleo\ErrorDeNegocio;
use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Respuesta;
use SIBEA\Nucleo\Sesion;
use SIBEA\Nucleo\Vista;
use SIBEA\Repositorios\RepositorioCatalogos;
use SIBEA\Repositorios\RepositorioPostulaciones;
use SIBEA\Servicios\ExportadorExcel;
use SIBEA\Servicios\FiltroPostulaciones;
use SIBEA\Servicios\ServicioRevision;

final class PostulacionesAdminControlador
{
    private const RUTA_LISTA = '/admin/postulaciones';
    private const TIPO_EXCEL = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private readonly ExportadorExcel $exportador;

    /**
     * @param Sesion                   $sesion        Para dejar avisos de una sola vez.
     * @param Vista                    $vista         Dibuja las pantallas.
     * @param RepositorioPostulaciones $postulaciones Busca las filas con los filtros.
     * @param RepositorioCatalogos     $catalogos     Beneficios y programas para los desplegables de filtros.
     * @param ServicioRevision         $revision      El detalle de una postulación, la revisión de sus documentos y las decisiones.
     * @param ExportadorExcel|null     $exportador    Arma el Excel (por defecto, el real).
     */
    public function __construct(
        private readonly Sesion $sesion,
        private readonly Vista $vista,
        private readonly RepositorioPostulaciones $postulaciones,
        private readonly RepositorioCatalogos $catalogos,
        private readonly ServicioRevision $revision,
        ?ExportadorExcel $exportador = null,
    ) {
        $this->exportador = $exportador ?? new ExportadorExcel();
    }

    /**
     * GET /admin/postulaciones : la lista con buscador, filtros y páginas de 20.
     *
     * @param Peticion $peticion Puede traer ?q, ?beneficio, ?programa, ?estado, ?desde, ?hasta, ?convocatoria y ?pagina.
     * @return Respuesta La pantalla «Postulaciones».
     */
    public function lista(Peticion $peticion): Respuesta
    {
        $filtro = FiltroPostulaciones::desdePeticion($peticion);
        $pagina = $this->postulaciones->buscar($filtro, (int) $peticion->consulta('pagina', '1'));

        return Respuesta::html($this->vista->mostrar('postulaciones/lista', [
            'titulo' => 'Postulaciones',
            'filtro' => $filtro,
            'pagina' => $pagina,
            'beneficios' => $this->catalogos->beneficios(),
            'programas' => $this->catalogos->programas(),
            'estados' => FiltroPostulaciones::ESTADOS,
            'grupos' => ExportadorExcel::GRUPOS_COLUMNAS,
            'columnas' => ExportadorExcel::COLUMNAS,
        ], 'admin'));
    }

    /**
     * GET /admin/postulaciones/excel : descarga las postulaciones que cumplen los filtros activos, solo con las
     * columnas elegidas (?columnas[]=documento&columnas[]=nombre...). Es GET porque solo lee datos: no cambia nada.
     *
     * @param Peticion $peticion Trae los mismos filtros de la lista y las columnas.
     * @return Respuesta El archivo .xlsx, o la lista con un aviso si no quedó ninguna columna válida.
     */
    public function excel(Peticion $peticion): Respuesta
    {
        $filtro = FiltroPostulaciones::desdePeticion($peticion);
        // SEGURIDAD: solo se aceptan las columnas de la lista blanca; lo demás que venga en la URL se descarta.
        $columnas = ExportadorExcel::columnasPedidas($peticion->consultaLista('columnas'));
        if ($columnas === []) {
            $this->sesion->mensaje('aviso', 'Elige al menos una columna para descargar el Excel.');

            return Respuesta::redirigir(self::RUTA_LISTA . ($filtro->comoConsulta() === [] ? '' : '?' . http_build_query($filtro->comoConsulta())));
        }

        // Mismo patrón que el Excel de entrega: se arma en una carpeta temporal, se lee a memoria y el archivo se
        // borra SIEMPRE (finally), pase lo que pase. El exportador escribe fila por fila lo que el repositorio entrega.
        $ruta = $this->exportador->postulaciones($this->postulaciones->todasParaExportar($filtro), $columnas);
        try {
            $contenido = (string) file_get_contents($ruta);
        } finally {
            @unlink($ruta);
        }

        // Lleva datos personales: el navegador no debe guardarlo en caché.
        return Respuesta::descarga($contenido, 'postulaciones-' . date('Y-m-d') . '.xlsx', self::TIPO_EXCEL)->sinCache();
    }

    /**
     * GET /admin/postulaciones/{id} : el detalle de una postulación con sus datos, documentos, historial y el panel de decisión.
     *
     * @param Peticion $peticion Trae {id} en la URL.
     * @return Respuesta La pantalla de detalle, o la página 404 si la postulación no existe.
     */
    public function detalle(Peticion $peticion): Respuesta
    {
        $detalle = $this->revision->detalle((int) $peticion->parametro('id'));
        if ($detalle === null) {
            return Respuesta::html($this->vista->mostrar('errores/404', [], 'admin'), 404);
        }

        return Respuesta::html($this->vista->mostrar('postulaciones/detalle', [
            'titulo' => 'Postulación de ' . (string) $detalle['nombre'],
            'detalle' => $detalle,
            'acciones' => ServicioRevision::acciones($detalle),
            'motivoConfirmar' => ServicioRevision::motivoConfirmarDeshabilitado($detalle),
            'soloRechazar' => ServicioRevision::soloQuedaRechazar($detalle),
        ], 'admin'));
    }

    /**
     * POST /admin/postulaciones/{id}/documentos : guarda la revisión de los documentos («Válido» / «No válido»).
     *
     * @param Peticion $peticion Trae {id}, estado[idDocumento] y observacion[idDocumento].
     * @return Respuesta Vuelve al detalle con un aviso.
     */
    public function documentos(Peticion $peticion): Respuesta
    {
        $estados = $peticion->campoMapa('estado');
        $observaciones = $peticion->campoMapa('observacion');
        $revisiones = [];
        foreach ($estados as $idDocumento => $estado) {
            $revisiones[(int) $idDocumento] = ['estado' => $estado, 'observacion' => $observaciones[$idDocumento] ?? ''];
        }
        if ($revisiones === []) {
            $this->sesion->mensaje('aviso', 'Marca al menos un documento como «Válido» o «No válido».');

            return $this->volverAlDetalle((int) $peticion->parametro('id'));
        }

        return $this->decidir($peticion, fn (int $id) => $this->revision->marcarDocumentos($id, $revisiones), 'Revisión guardada.');
    }

    /**
     * POST /admin/postulaciones/{id}/confirmar : «Datos confirmados» (la postulación pasa a Validada).
     *
     * @param Peticion $peticion Trae {id}.
     * @return Respuesta Vuelve al detalle con un aviso.
     */
    public function confirmar(Peticion $peticion): Respuesta
    {
        return $this->decidir($peticion, fn (int $id) => $this->revision->confirmar($id), 'Datos confirmados. Ahora puedes aprobar o rechazar la postulación.');
    }

    /**
     * POST /admin/postulaciones/{id}/aprobar : aprueba la postulación (la base crea la asignación y avisa al aprendiz).
     *
     * @param Peticion $peticion Trae {id}.
     * @return Respuesta Vuelve al detalle con un aviso (de éxito o, por ejemplo, «Ya no hay cupos en esta convocatoria»).
     */
    public function aprobar(Peticion $peticion): Respuesta
    {
        return $this->decidir($peticion, fn (int $id) => $this->revision->aprobar($id), 'Postulación aprobada. Se creó la asignación y el aprendiz recibió un aviso.');
    }

    /**
     * POST /admin/postulaciones/{id}/rechazar : rechaza la postulación con un motivo obligatorio.
     *
     * @param Peticion $peticion Trae {id} y el campo «motivo» de la ventana de rechazo.
     * @return Respuesta Vuelve al detalle con un aviso.
     */
    public function rechazar(Peticion $peticion): Respuesta
    {
        $motivo = $peticion->campo('motivo');

        return $this->decidir($peticion, fn (int $id) => $this->revision->rechazar($id, $motivo), 'Postulación rechazada. El aprendiz recibió un aviso con el motivo.');
    }

    /**
     * Hace el cambio, deja el aviso (éxito o el mensaje del error de negocio) y vuelve al detalle (Post/Redirect/Get).
     *
     * @param callable(int): void $cambio       Lo que se hace con el id de la postulación.
     * @param string              $mensajeExito Lo que se avisa si todo salió bien.
     */
    private function decidir(Peticion $peticion, callable $cambio, string $mensajeExito): Respuesta
    {
        $id = (int) $peticion->parametro('id');
        try {
            $cambio($id);
            $this->sesion->mensaje('exito', $mensajeExito);
        } catch (ErrorDeNegocio $error) {
            $this->sesion->mensaje('error', $error->getMessage());
        }

        return $this->volverAlDetalle($id);
    }

    private function volverAlDetalle(int $idPostulacion): Respuesta
    {
        return Respuesta::redirigir(self::RUTA_LISTA . '/' . $idPostulacion);
    }
}
