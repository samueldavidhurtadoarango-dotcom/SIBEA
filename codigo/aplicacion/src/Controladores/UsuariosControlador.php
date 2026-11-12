<?php

/**
 * CONTROLADOR DE USUARIOS (solo administradores)
 *
 * Paso 4 del recorrido: atiende /admin/usuarios*. El administrador ve la lista de
 * aprendices, crea una cuenta, activa o desactiva cuentas y restablece contraseñas.
 * Las contraseñas temporales se muestran UNA sola vez y quedan en la sesión (no en
 * un archivo) para armar el «Excel de entrega», que se genera al momento de descargarlo.
 * No tiene SQL ni reglas de negocio: valida con ValidadorUsuario, delega en
 * ServicioUsuarios y elige la pantalla.
 */

declare(strict_types=1);

namespace BenefitRack\Controladores;

use BenefitRack\Nucleo\ErrorDeNegocio;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Nucleo\Sesion;
use BenefitRack\Nucleo\Vista;
use BenefitRack\Repositorios\RepositorioCatalogos;
use BenefitRack\Servicios\ExportadorExcel;
use BenefitRack\Servicios\ServicioUsuarios;
use BenefitRack\Validacion\ValidadorUsuario;

final class UsuariosControlador
{
    private const RUTA_LISTA = '/admin/usuarios';
    private const TIPO_EXCEL = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private readonly ValidadorUsuario $validador;
    private readonly ExportadorExcel $exportador;

    /**
     * @param Sesion                  $sesion    Quién está dentro y las contraseñas pendientes de entregar.
     * @param Vista                   $vista     Dibuja las pantallas.
     * @param ServicioUsuarios        $servicio  Reglas de las cuentas de aprendices.
     * @param RepositorioCatalogos    $catalogos Los programas para el desplegable.
     * @param ValidadorUsuario|null   $validador Revisa el formulario (por defecto, el real).
     * @param ExportadorExcel|null    $exportador Arma el Excel de entrega (por defecto, el real).
     */
    public function __construct(
        private readonly Sesion $sesion,
        private readonly Vista $vista,
        private readonly ServicioUsuarios $servicio,
        private readonly RepositorioCatalogos $catalogos,
        ?ValidadorUsuario $validador = null,
        ?ExportadorExcel $exportador = null,
    ) {
        $this->validador = $validador ?? new ValidadorUsuario();
        $this->exportador = $exportador ?? new ExportadorExcel();
    }

    /**
     * GET /admin/usuarios : la lista de aprendices (con buscador y páginas de 20).
     *
     * @param Peticion $peticion Puede traer ?q=texto y ?pagina=N.
     * @return Respuesta La pantalla «Usuarios».
     */
    public function lista(Peticion $peticion): Respuesta
    {
        $buscar = $peticion->consulta('q');
        $pagina = $this->servicio->listar($buscar, (int) $peticion->consulta('pagina', '1'));

        return $this->pantalla('usuarios/lista', 'Usuarios', ['buscar' => $buscar, 'pagina' => $pagina, 'pendientes' => count($this->sesion->entregaPendiente())]);
    }

    /**
     * GET /admin/usuarios/nuevo : el formulario vacío.
     *
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta La pantalla «Crear una cuenta de aprendiz».
     */
    public function formulario(Peticion $peticion): Respuesta
    {
        return $this->pantallaCrear([], [], null);
    }

    /**
     * POST /admin/usuarios/nuevo : crea la cuenta y muestra la contraseña temporal una sola vez.
     * (Los filtros de sesión, rol y CSRF ya pasaron.)
     *
     * @param Peticion $peticion Trae documento, nombre, apellido, correo, programa y ficha.
     * @return Respuesta La pantalla «Cuenta creada» o el formulario con los errores.
     */
    public function crear(Peticion $peticion): Respuesta
    {
        $valores = [];
        foreach (['documento', 'nombre', 'apellido', 'correo', 'programa', 'ficha'] as $campo) {
            $valores[$campo] = $peticion->campo($campo);
        }

        $resultado = $this->validador->validar($valores, $this->programasPorNombre());
        if (!$resultado->esValido()) {
            return $this->pantallaCrear($resultado->errores(), $valores, null);
        }

        try {
            $creada = $this->servicio->crearAprendiz($resultado->datos());
        } catch (ErrorDeNegocio $error) {
            return $this->pantallaCrear([], $valores, $error->getMessage());
        }

        $datos = $resultado->datos();
        $entrega = [
            'documento' => (string) $datos['documento'],
            'nombre' => $datos['nombre'] . ' ' . $datos['apellido'],
            'correo' => (string) $datos['correo'],
            'contrasena' => $creada['contrasena'],
        ];

        return $this->mostrarContrasena('Cuenta creada', 'La cuenta quedó creada', $entrega);
    }

    /**
     * POST /admin/usuarios/{id}/estado : activa, desactiva o desbloquea una cuenta con justificación.
     *
     * @param Peticion $peticion Trae {id} en la URL y los campos "estado" y "justificacion".
     * @return Respuesta Vuelve a la lista con un mensaje de éxito o de error.
     */
    public function cambiarEstado(Peticion $peticion): Respuesta
    {
        try {
            $aprendiz = $this->servicio->aprendiz((int) $peticion->parametro('id'));
            $estado = $peticion->campo('estado');
            $this->servicio->cambiarEstado((int) $peticion->parametro('id'), $estado, $peticion->campo('justificacion'));
            $this->sesion->mensaje('exito', 'La cuenta de ' . $aprendiz['nombre'] . ' ' . $aprendiz['apellido'] . ' quedó ' . $this->estadoEnFemenino($estado) . '.');
        } catch (ErrorDeNegocio $error) {
            $this->sesion->mensaje('error', $error->getMessage());
        }

        return Respuesta::redirigir(self::RUTA_LISTA);
    }

    /**
     * POST /admin/usuarios/{id}/restablecer : pone una contraseña temporal nueva y la muestra una sola vez.
     *
     * @param Peticion $peticion Trae {id} en la URL.
     * @return Respuesta La pantalla con la contraseña nueva, o la lista con un mensaje de error.
     */
    public function restablecer(Peticion $peticion): Respuesta
    {
        try {
            $aprendiz = $this->servicio->aprendiz((int) $peticion->parametro('id'));
            $contrasena = $this->servicio->restablecerContrasena((int) $aprendiz['id_usuario']);
        } catch (ErrorDeNegocio $error) {
            $this->sesion->mensaje('error', $error->getMessage());

            return Respuesta::redirigir(self::RUTA_LISTA);
        }

        $entrega = [
            'documento' => (string) $aprendiz['documento'],
            'nombre' => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
            'correo' => (string) $aprendiz['correo'],
            'contrasena' => $contrasena,
        ];

        return $this->mostrarContrasena('Contraseña restablecida', 'La contraseña quedó restablecida', $entrega);
    }

    /**
     * POST /admin/usuarios/entrega : genera el Excel con las contraseñas pendientes, lo envía y lo borra.
     * Es POST (con token) y no un enlace porque cambia algo: borra las contraseñas de la sesión.
     *
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta El archivo .xlsx, o la lista con un aviso si no hay nada pendiente.
     */
    public function descargarEntrega(Peticion $peticion): Respuesta
    {
        $filas = $this->sesion->entregaPendiente();
        if ($filas === []) {
            $this->sesion->mensaje('aviso', 'No hay contraseñas pendientes de entregar.');

            return Respuesta::redirigir(self::RUTA_LISTA);
        }

        // El Excel se arma ahora en una carpeta temporal, se lee a memoria y el archivo se borra SIEMPRE (finally),
        // incluso si algo falla: así no queda en el servidor, ni aunque el navegador cancele la descarga.
        $ruta = $this->exportador->entregaContrasenas($filas);
        try {
            $contenido = (string) file_get_contents($ruta);
        } finally {
            @unlink($ruta);
        }
        // Ya están en el archivo: se olvidan de la sesión para que no queden más tiempo del necesario.
        $this->sesion->borrarEntrega();

        return Respuesta::descarga($contenido, 'entrega-contrasenas-' . date('Y-m-d') . '.xlsx', self::TIPO_EXCEL)->sinCache();
    }

    /**
     * @return array<string, int> Nombre del programa => id, para el validador.
     */
    private function programasPorNombre(): array
    {
        $mapa = [];
        foreach ($this->catalogos->programas() as $programa) {
            $mapa[$programa['nombre']] = $programa['id'];
        }

        return $mapa;
    }

    /**
     * Guarda la entrega en la sesión y muestra la contraseña (la única vez que se ve en pantalla).
     *
     * @param array{documento: string, nombre: string, correo: string, contrasena: string} $entrega
     */
    private function mostrarContrasena(string $titulo, string $encabezado, array $entrega): Respuesta
    {
        $this->sesion->agregarEntrega($entrega);

        // La página lleva una contraseña en claro: el navegador no debe guardarla (ni en caché ni en el historial).
        return $this->pantalla('usuarios/creada', $titulo, [
            'encabezado' => $encabezado,
            'entrega' => $entrega,
            'pendientes' => count($this->sesion->entregaPendiente()),
        ])->sinCache();
    }

    /**
     * @param array<string, string> $errores Mensaje por campo.
     * @param array<string, string> $valores Lo que la persona ya escribió (para no perderlo).
     */
    private function pantallaCrear(array $errores, array $valores, ?string $errorGeneral): Respuesta
    {
        return $this->pantalla('usuarios/crear', 'Crear una cuenta', [
            'errores' => $errores,
            'valores' => $valores,
            'errorGeneral' => $errorGeneral,
            'programas' => $this->catalogos->programas(),
        ]);
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function pantalla(string $plantilla, string $titulo, array $datos): Respuesta
    {
        return Respuesta::html($this->vista->mostrar($plantilla, ['titulo' => $titulo] + $datos, 'admin'));
    }

    /** «Activo» -> «Activa», «Inactivo» -> «Inactiva»: la frase habla de «la cuenta». */
    private function estadoEnFemenino(string $estado): string
    {
        return $estado === 'Inactivo' ? 'Inactiva' : 'Activa';
    }
}
