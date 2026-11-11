<?php

/**
 * CONTROLADOR DE POSTULACIONES DEL ADMINISTRADOR
 *
 * Paso 4 del recorrido: atiende /admin/postulaciones (la pantalla de inicio del administrador) y
 * /admin/postulaciones/excel. Lee los filtros de la URL con FiltroPostulaciones, pide las filas al repositorio y
 * elige la pantalla o la descarga. No tiene SQL ni HTML. (El detalle y las decisiones de cada postulación son de
 * la tarea siguiente.)
 */

declare(strict_types=1);

namespace BenefitRack\Controladores;

use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Nucleo\Sesion;
use BenefitRack\Nucleo\Vista;
use BenefitRack\Repositorios\RepositorioCatalogos;
use BenefitRack\Repositorios\RepositorioPostulaciones;
use BenefitRack\Servicios\ExportadorExcel;
use BenefitRack\Servicios\FiltroPostulaciones;

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
     * @param ExportadorExcel|null     $exportador    Arma el Excel (por defecto, el real).
     */
    public function __construct(
        private readonly Sesion $sesion,
        private readonly Vista $vista,
        private readonly RepositorioPostulaciones $postulaciones,
        private readonly RepositorioCatalogos $catalogos,
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
}
