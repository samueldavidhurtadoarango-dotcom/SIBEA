<?php

/**
 * CONTROLADOR DE CONVOCATORIAS (solo administradores)
 *
 * Paso 4 del recorrido: atiende /admin/convocatorias*. El administrador ve la lista (con pestañas
 * por estado), crea y edita convocatorias y las activa, desactiva o cierra. No tiene SQL ni
 * reglas de negocio: valida con ValidadorConvocatoria, delega en ServicioConvocatorias y elige la pantalla.
 * Los filtros de sesión, rol, primer ingreso y CSRF ya pasaron antes de llegar aquí (ver rutas.php).
 */

declare(strict_types=1);

namespace SIBEA\Controladores;

use SIBEA\Nucleo\ErrorDeNegocio;
use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Respuesta;
use SIBEA\Nucleo\Sesion;
use SIBEA\Nucleo\Vista;
use SIBEA\Repositorios\RepositorioCatalogos;
use SIBEA\Servicios\ServicioConvocatorias;
use SIBEA\Validacion\ValidadorConvocatoria;

final class ConvocatoriasControlador
{
    private const RUTA_LISTA = '/admin/convocatorias';

    /** Los campos del formulario, tal como llegan por POST. */
    private const CAMPOS = ['nombre', 'id_beneficio', 'fecha_inicio', 'fecha_cierre', 'cupos', 'requisitos', 'valor_pago', 'numero_pagos', 'duracion_meses', 'estado'];

    /** Qué se le dice al administrador cuando cambia el estado de «la convocatoria X». */
    private const MENSAJE_POR_ACCION = [
        'activar' => 'quedó activa',
        'desactivar' => 'quedó inactiva',
        'cerrar' => 'se cerró',
        'cerrar-anticipado' => 'se cerró antes de la fecha de cierre',
    ];

    private readonly ValidadorConvocatoria $validador;

    /**
     * @param Sesion                      $sesion     Para dejar mensajes de éxito o de error.
     * @param Vista                       $vista      Dibuja las pantallas.
     * @param ServicioConvocatorias       $servicio   Reglas de las convocatorias.
     * @param RepositorioCatalogos        $catalogos  Los beneficios para el desplegable.
     * @param ValidadorConvocatoria|null  $validador  Revisa el formulario (por defecto, el real).
     */
    public function __construct(
        private readonly Sesion $sesion,
        private readonly Vista $vista,
        private readonly ServicioConvocatorias $servicio,
        private readonly RepositorioCatalogos $catalogos,
        ?ValidadorConvocatoria $validador = null,
    ) {
        $this->validador = $validador ?? new ValidadorConvocatoria();
    }

    /**
     * GET /admin/convocatorias : la lista, con la pestaña pedida en ?estado= (todas, activas, inactivas, cerradas).
     *
     * @param Peticion $peticion Puede traer ?estado=.
     * @return Respuesta La pantalla «Convocatorias».
     */
    public function lista(Peticion $peticion): Respuesta
    {
        return $this->pantalla('convocatorias/lista', 'Convocatorias', $this->servicio->listar($peticion->consulta('estado', 'todas')));
    }

    /**
     * GET /admin/convocatorias/nueva : el formulario vacío.
     *
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta La pantalla «Nueva convocatoria».
     */
    public function formulario(Peticion $peticion): Respuesta
    {
        return $this->pantallaFormulario(null, [], [], null);
    }

    /**
     * POST /admin/convocatorias/nueva : valida y crea la convocatoria.
     *
     * @param Peticion $peticion Trae los campos del formulario.
     * @return Respuesta Vuelve a la lista con un aviso, o al formulario con los errores.
     */
    public function crear(Peticion $peticion): Respuesta
    {
        $valores = $this->valoresDe($peticion);
        $resultado = $this->validador->validar($valores);
        if (!$resultado->esValido()) {
            return $this->pantallaFormulario(null, $resultado->errores(), $valores, null);
        }

        try {
            $this->servicio->crear($resultado->datos());
        } catch (ErrorDeNegocio $error) {
            return $this->pantallaFormulario(null, [], $valores, $error->getMessage());
        }

        $this->sesion->mensaje('exito', 'La convocatoria «' . $resultado->datos()['nombre'] . '» quedó guardada.');

        return Respuesta::redirigir(self::RUTA_LISTA);
    }

    /**
     * GET /admin/convocatorias/{id}/editar : el formulario con los datos actuales.
     *
     * @param Peticion $peticion Trae {id} en la URL.
     * @return Respuesta La pantalla «Editar convocatoria» (404 si no existe).
     */
    public function editar(Peticion $peticion): Respuesta
    {
        try {
            $convocatoria = $this->servicio->convocatoria((int) $peticion->parametro('id'));
        } catch (ErrorDeNegocio) {
            return $this->noEncontrada();
        }

        return $this->pantallaFormulario((int) $convocatoria['id_convocatoria'], [], $this->valoresGuardados($convocatoria), null);
    }

    /**
     * POST /admin/convocatorias/{id}/editar : valida y guarda los cambios.
     *
     * @param Peticion $peticion Trae {id} en la URL y los campos del formulario.
     * @return Respuesta Vuelve a la lista con un aviso, o al formulario con los errores.
     */
    public function guardar(Peticion $peticion): Respuesta
    {
        $id = (int) $peticion->parametro('id');
        $valores = $this->valoresDe($peticion);
        $resultado = $this->validador->validar($valores);
        if (!$resultado->esValido()) {
            return $this->pantallaFormulario($id, $resultado->errores(), $valores, null);
        }

        try {
            $this->servicio->actualizar($id, $resultado->datos());
        } catch (ErrorDeNegocio $error) {
            return $this->pantallaFormulario($id, [], $valores, $error->getMessage());
        }

        $this->sesion->mensaje('exito', 'Los cambios de «' . $resultado->datos()['nombre'] . '» quedaron guardados.');

        return Respuesta::redirigir(self::RUTA_LISTA);
    }

    /**
     * POST /admin/convocatorias/{id}/estado : activa, desactiva o cierra (con motivo si es anticipado).
     *
     * @param Peticion $peticion Trae {id} en la URL y los campos "accion" y "motivo".
     * @return Respuesta Vuelve a la lista con un mensaje de éxito o de error.
     */
    public function cambiarEstado(Peticion $peticion): Respuesta
    {
        $accion = $peticion->campo('accion');

        try {
            $id = (int) $peticion->parametro('id');
            $this->servicio->cambiarEstado($id, $accion, $peticion->campo('motivo'));
            $nombre = (string) $this->servicio->convocatoria($id)['nombre'];
            $this->sesion->mensaje('exito', 'La convocatoria «' . $nombre . '» ' . self::MENSAJE_POR_ACCION[$accion] . '.');
        } catch (ErrorDeNegocio $error) {
            $this->sesion->mensaje('error', $error->getMessage());
        }

        return Respuesta::redirigir(self::RUTA_LISTA);
    }

    /**
     * @return array<string, string> Lo que llegó en el formulario (para validarlo y, si hay errores, no perderlo).
     */
    private function valoresDe(Peticion $peticion): array
    {
        $valores = [];
        foreach (self::CAMPOS as $campo) {
            $valores[$campo] = $peticion->campo($campo);
        }

        return $valores;
    }

    /**
     * @param array<string, mixed> $convocatoria La fila guardada.
     * @return array<string, string> Los valores para llenar el formulario (el valor en pesos enteros, sin «.00»).
     */
    private function valoresGuardados(array $convocatoria): array
    {
        $valores = [];
        foreach (self::CAMPOS as $campo) {
            $valores[$campo] = (string) ($convocatoria[$campo] ?? '');
        }
        $valores['valor_pago'] = (string) (int) $convocatoria['valor_pago'];

        return $valores;
    }

    /**
     * @param int|null              $id          Id de la convocatoria que se edita; null si es nueva.
     * @param array<string, string> $errores     Mensaje por campo.
     * @param array<string, string> $valores     Lo que la persona ya escribió.
     */
    private function pantallaFormulario(?int $id, array $errores, array $valores, ?string $errorGeneral): Respuesta
    {
        $edicion = $id !== null;

        return $this->pantalla('convocatorias/formulario', $edicion ? 'Editar convocatoria' : 'Nueva convocatoria', [
            'accion' => $edicion ? self::RUTA_LISTA . "/$id/editar" : self::RUTA_LISTA . '/nueva',
            'edicion' => $edicion,
            'errores' => $errores,
            'valores' => $valores,
            'errorGeneral' => $errorGeneral,
            'beneficios' => $this->catalogos->beneficios(),
        ]);
    }

    private function noEncontrada(): Respuesta
    {
        return Respuesta::html($this->vista->mostrar('errores/404'), 404);
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function pantalla(string $plantilla, string $titulo, array $datos): Respuesta
    {
        return Respuesta::html($this->vista->mostrar($plantilla, ['titulo' => $titulo] + $datos, 'admin'));
    }
}
