<?php

/**
 * CONTROLADOR DE LA CARGA DE APRENDICES DESDE EXCEL (solo administradores)
 *
 * Paso 4 del recorrido: atiende /admin/usuarios/cargar*, /admin/usuarios/plantilla.
 * El recorrido es: subir el Excel -> vista previa (no se crea nada) -> confirmar -> resultado.
 * El archivo subido solo se lee durante la petición (PHP borra su temporal al terminar): lo que
 * espera la confirmación son las filas válidas, guardadas en la sesión.
 * Las contraseñas de las cuentas creadas se suman a la entrega pendiente de la sesión: la descarga
 * «Excel de entrega» es la MISMA de /admin/usuarios/entrega, no hay una segunda.
 * No tiene SQL ni reglas de negocio: delega en ServicioCargaUsuarios y elige la pantalla.
 */

declare(strict_types=1);

namespace SIBEA\Controladores;

use SIBEA\Nucleo\ErrorDeNegocio;
use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Respuesta;
use SIBEA\Nucleo\Sesion;
use SIBEA\Nucleo\Vista;
use SIBEA\Servicios\CargaInterrumpida;
use SIBEA\Servicios\ExportadorExcel;
use SIBEA\Servicios\ServicioCargaUsuarios;

final class CargaUsuariosControlador
{
    private const RUTA_CARGAR = '/admin/usuarios/cargar';
    private const TIPO_EXCEL = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    private const BYTES_MAXIMOS = 5 * 1024 * 1024;

    private readonly ExportadorExcel $exportador;

    /**
     * @param Sesion                 $sesion     Guarda la carga en curso y las contraseñas por entregar.
     * @param Vista                  $vista      Dibuja las pantallas.
     * @param ServicioCargaUsuarios  $servicio   Lee el Excel y crea las cuentas.
     * @param ExportadorExcel|null   $exportador Arma la plantilla y el Excel de errores (por defecto, el real).
     */
    public function __construct(
        private readonly Sesion $sesion,
        private readonly Vista $vista,
        private readonly ServicioCargaUsuarios $servicio,
        ?ExportadorExcel $exportador = null,
    ) {
        $this->exportador = $exportador ?? new ExportadorExcel();
    }

    /**
     * GET /admin/usuarios/cargar : el formulario para elegir el archivo.
     *
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta La pantalla «Cargar aprendices desde Excel».
     */
    public function formulario(Peticion $peticion): Respuesta
    {
        return $this->pantallaCargar(null, null);
    }

    /**
     * GET /admin/usuarios/plantilla : descarga la plantilla .xlsx con los encabezados.
     *
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta El archivo plantilla-aprendices.xlsx.
     */
    public function plantilla(Peticion $peticion): Respuesta
    {
        return $this->descargar($this->exportador->plantillaUsuarios(), 'plantilla-aprendices.xlsx');
    }

    /**
     * POST /admin/usuarios/cargar : lee el Excel y muestra la vista previa. NO crea ninguna cuenta:
     * primero se ven los errores y después el administrador decide (por eso hay un paso de confirmar).
     *
     * @param Peticion $peticion Trae el archivo en el campo «archivo».
     * @return Respuesta La vista previa, o el formulario con el motivo si el archivo no sirve.
     */
    public function vistaPrevia(Peticion $peticion): Respuesta
    {
        $archivo = $peticion->archivo('archivo');
        $nombre = basename((string) ($archivo['name'] ?? ''));
        $problema = $this->problemaDeLaSubida($archivo);
        if ($problema !== null) {
            return $this->pantallaCargar($problema, $nombre === '' ? null : $nombre);
        }

        try {
            $lectura = $this->servicio->leer((string) $archivo['tmp_name']);
        } catch (ErrorDeNegocio $error) {
            return $this->pantallaCargar($error->getMessage(), $nombre);
        }

        // Las filas válidas esperan en la sesión (no el archivo): se confirma exactamente lo que se vio.
        $this->sesion->guardarCarga(['archivo' => $nombre, 'filas' => $lectura['filas'], 'errores' => $lectura['errores']]);

        return $this->pantalla('usuarios/vista_previa', 'Revisa el archivo', [
            'archivo' => $nombre,
            'validas' => count($lectura['filas']),
            'errores' => ServicioCargaUsuarios::aplanarErrores($lectura['errores']),
            'conErrores' => count($lectura['errores']),
        ]);
    }

    /**
     * POST /admin/usuarios/cargar/confirmar : crea las cuentas de las filas válidas de la vista previa.
     *
     * @param Peticion $peticion La petición en curso (los filtros de sesión, rol y CSRF ya pasaron).
     * @return Respuesta La pantalla de resultado, o el formulario si no hay nada que confirmar.
     */
    public function confirmar(Peticion $peticion): Respuesta
    {
        $carga = $this->sesion->cargaPendiente();
        if ($carga === null || $carga['filas'] === []) {
            $this->sesion->mensaje('aviso', 'No hay filas pendientes de crear. Sube el archivo de nuevo.');

            return Respuesta::redirigir(self::RUTA_CARGAR);
        }

        try {
            $resultado = $this->servicio->confirmar($carga['filas'], $carga['archivo']);
        } catch (CargaInterrumpida $interrupcion) {
            // Un fallo inesperado a la mitad: las cuentas ya creadas existen y su contraseña solo está en la entrega.
            // Se conserva lo hecho (abajo, igual que si hubiera salido bien) y se avisa del error en la pantalla.
            $resultado = $interrupcion->resultado();
            $this->sesion->mensaje('error', 'La carga se interrumpió por un error inesperado. Las cuentas ya creadas están en el Excel de entrega; revisa la lista de usuarios antes de volver a cargar el resto.');
            error_log('[SIBEA] Carga interrumpida: ' . ($interrupcion->getPrevious()?->getMessage() ?? ''));
        }
        // Las contraseñas van a la entrega pendiente de la sesión (la misma del resto de cuentas).
        foreach ($resultado['entrega'] as $fila) {
            $this->sesion->agregarEntrega($fila);
        }
        // Las filas ya se usaron (o se intentaron): se vacían para que confirmar dos veces no repita nada. Los errores (de la
        // vista previa y los que aparecieron al crear) se conservan para poder descargarlos.
        $errores = $carga['errores'] + $resultado['errores'];
        $this->sesion->guardarCarga(['archivo' => $carga['archivo'], 'filas' => [], 'errores' => $errores]);

        // El resultado no lleva contraseñas, pero la descarga de entrega está a un clic: no se guarda en caché.
        return $this->pantalla('usuarios/resultado', 'Resultado de la carga', [
            'creadas' => $resultado['creadas'],
            'conErrores' => count($errores),
            'pendientes' => count($this->sesion->entregaPendiente()),
        ])->sinCache();
    }

    /**
     * GET /admin/usuarios/cargar/errores : descarga un Excel con la fila, el campo y el motivo de cada error.
     *
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta El archivo errores-carga.xlsx, o la lista con un aviso si no hay errores.
     */
    public function errores(Peticion $peticion): Respuesta
    {
        $errores = ServicioCargaUsuarios::aplanarErrores($this->sesion->cargaPendiente()['errores'] ?? []);
        if ($errores === []) {
            $this->sesion->mensaje('aviso', 'No hay errores para descargar.');

            return Respuesta::redirigir('/admin/usuarios');
        }

        return $this->descargar($this->exportador->erroresCarga($errores), 'errores-carga-' . date('Y-m-d') . '.xlsx');
    }

    /**
     * ¿Por qué no se puede ni empezar a leer lo que llegó? (nada subido, subida cortada o archivo muy grande).
     *
     * @param array<string, mixed>|null $archivo Lo que PHP puso en $_FILES['archivo'].
     * @return string|null El motivo en lenguaje sencillo, o null si se puede leer.
     */
    private function problemaDeLaSubida(?array $archivo): ?string
    {
        $codigo = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($codigo === UPLOAD_ERR_NO_FILE) {
            return 'Elige un archivo de Excel (.xlsx) para continuar.';
        }
        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE || (int) ($archivo['size'] ?? 0) > self::BYTES_MAXIMOS) {
            return 'El archivo pesa más de 5 MB. Divídelo en varios archivos.';
        }

        return $codigo === UPLOAD_ERR_OK ? null : 'No se pudo subir el archivo. Inténtalo de nuevo.';
    }

    /**
     * Lee un Excel temporal a memoria, lo borra SIEMPRE y lo entrega como descarga (nada queda en el servidor).
     */
    private function descargar(string $ruta, string $nombre): Respuesta
    {
        try {
            $contenido = (string) file_get_contents($ruta);
        } finally {
            @unlink($ruta);
        }

        return Respuesta::descarga($contenido, $nombre, self::TIPO_EXCEL)->sinCache();
    }

    /** El formulario de subida; con $motivo es la pantalla de «archivo inválido» de la maqueta. */
    private function pantallaCargar(?string $motivo, ?string $nombreArchivo): Respuesta
    {
        return $this->pantalla('usuarios/cargar', 'Cargar aprendices desde Excel', [
            'motivo' => $motivo,
            'nombreArchivo' => $nombreArchivo,
            'columnas' => ServicioCargaUsuarios::COLUMNAS,
            'maximoFilas' => ServicioCargaUsuarios::MAXIMO_FILAS,
        ]);
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function pantalla(string $plantilla, string $titulo, array $datos): Respuesta
    {
        return Respuesta::html($this->vista->mostrar($plantilla, ['titulo' => $titulo] + $datos, 'admin'));
    }
}
