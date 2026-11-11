<?php

/**
 * CONTROLADOR DE LA POSTULACIÓN DEL APRENDIZ (primera parte: mostrar y validar)
 *
 * Paso 4 del recorrido: atiende /convocatorias/{id}/postulacion. El aprendiz ve el formulario, lo envía y, si todo
 * está bien, ve la pantalla «Revisa tu postulación» (el envío definitivo es la tarea siguiente). No tiene SQL ni
 * reglas de negocio: valida con ValidadorPostulacion, guarda los documentos con AlmacenTemporal y elige la pantalla.
 * Los filtros de sesión, rol Aprendiz, primer ingreso y CSRF ya pasaron antes de llegar aquí (ver rutas.php).
 */

declare(strict_types=1);

namespace BenefitRack\Controladores;

use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Nucleo\Sesion;
use BenefitRack\Nucleo\Vista;
use BenefitRack\Repositorios\RepositorioCatalogos;
use BenefitRack\Repositorios\RepositorioConvocatorias;
use BenefitRack\Repositorios\RepositorioUsuarios;
use BenefitRack\Servicios\AlmacenTemporal;
use BenefitRack\Validacion\ValidadorPostulacion;

final class PostulacionAprendizControlador
{
    /** Los campos de texto del formulario, tal como llegan por POST. */
    private const CAMPOS = [
        'telefono', 'direccion', 'fecha_nacimiento', 'id_eps', 'id_entidad_bancaria', 'id_tipo_cuenta',
        'numero_cuenta', 'titular_cuenta', 'motivo', 'acepta_datos',
    ];

    /**
     * @param Sesion                   $sesion        Guarda lo pendiente de enviar.
     * @param Vista                    $vista         Dibuja las pantallas.
     * @param RepositorioConvocatorias $convocatorias Busca la convocatoria y dice si está abierta.
     * @param RepositorioCatalogos     $catalogos     Las listas de EPS, bancos y tipos de cuenta.
     * @param RepositorioUsuarios      $usuarios      Los datos de la cuenta del aprendiz.
     * @param ValidadorPostulacion     $validador     Revisa el formulario y los documentos.
     * @param AlmacenTemporal          $almacen       Guarda los documentos revisados mientras se confirma el envío.
     */
    public function __construct(
        private readonly Sesion $sesion,
        private readonly Vista $vista,
        private readonly RepositorioConvocatorias $convocatorias,
        private readonly RepositorioCatalogos $catalogos,
        private readonly RepositorioUsuarios $usuarios,
        private readonly ValidadorPostulacion $validador,
        private readonly AlmacenTemporal $almacen,
    ) {
    }

    /**
     * GET /convocatorias/{id}/postulacion : el formulario (o «cerrada» si ya no se puede postular).
     *
     * @param Peticion $peticion Trae {id} en la URL.
     * @return Respuesta La pantalla del formulario, «cerrada» o 404.
     */
    public function formulario(Peticion $peticion): Respuesta
    {
        $id = (int) $peticion->parametro('id');
        $convocatoria = $this->convocatorias->buscar($id);
        if ($convocatoria === null) {
            return $this->noEncontrada();
        }

        return $this->cerrada($convocatoria)
            ?? $this->pantallaFormulario($convocatoria, $this->valoresIniciales($id), []);
    }

    /**
     * POST /convocatorias/{id}/postulacion : valida todo. Con errores vuelve al formulario; si está bien guarda
     * los documentos en la carpeta temporal, deja lo pendiente en la sesión y muestra «Revisa tu postulación».
     *
     * @param Peticion $peticion Trae {id}, los campos del formulario y los tres archivos.
     * @return Respuesta El formulario con errores, la pantalla de revisión, «cerrada» o 404.
     */
    public function revisar(Peticion $peticion): Respuesta
    {
        $id = (int) $peticion->parametro('id');
        $convocatoria = $this->convocatorias->buscar($id);
        if ($convocatoria === null) {
            return $this->noEncontrada();
        }
        // Seguridad: aunque el formulario no se pudo abrir, alguien puede mandar el POST a mano. Se revisa otra vez.
        if (($cerrada = $this->cerrada($convocatoria)) !== null) {
            return $cerrada;
        }

        $valores = $this->valoresDe($peticion);
        $resultado = $this->validador->validar($valores, $this->archivosDe($peticion), $this->listas());
        if (!$resultado->esValido()) {
            return $this->pantallaFormulario($convocatoria, $valores, $resultado->errores());
        }

        $pendiente = $this->guardarPendiente($id, $resultado->datos());

        return $this->pantallaRevisar($convocatoria, $pendiente);
    }

    /**
     * Pasa los documentos a la carpeta temporal de la sesión y deja lo pendiente en la sesión.
     *
     * @param array<string, mixed> $datosValidados Lo que devolvió el validador (campos limpios + «archivos»).
     * @return array{datos: array<string, mixed>, archivos: array<string, array{ruta: string, extension: string, nombre_original: string, bytes: int}>}
     */
    private function guardarPendiente(int $idConvocatoria, array $datosValidados): array
    {
        // Si había una revisión anterior de esta convocatoria, sus documentos temporales ya no sirven.
        $this->descartarPendiente($idConvocatoria);

        /** @var array<string, array{ruta: string, extension: string, nombre_original: string, bytes: int}> $archivos */
        $archivos = $datosValidados['archivos'];
        unset($datosValidados['archivos']);

        $guardados = [];
        foreach ($archivos as $campo => $archivo) {
            $guardados[$campo] = $this->almacen->guardar($archivo, $this->sesion->idSesion());
        }
        $pendiente = ['datos' => $datosValidados, 'archivos' => $guardados];
        $this->sesion->guardarPostulacionPendiente($idConvocatoria, $pendiente);

        return $pendiente;
    }

    private function descartarPendiente(int $idConvocatoria): void
    {
        $anterior = $this->sesion->postulacionPendiente($idConvocatoria);
        if ($anterior !== null) {
            $this->almacen->descartar(array_values($anterior['archivos']));
            $this->sesion->borrarPostulacionPendiente($idConvocatoria);
        }
    }

    /**
     * Regla de negocio: solo se postula a una convocatoria abierta y una sola vez.
     *
     * @param array<string, mixed> $convocatoria
     * @return Respuesta|null La pantalla «cerrada» si no se puede postular; null si se puede.
     */
    private function cerrada(array $convocatoria): ?Respuesta
    {
        $id = (int) $convocatoria['id_convocatoria'];
        $yaPostulada = $this->convocatorias->idPostulacionDe($id, $this->idAprendiz()) !== null;
        if (!$yaPostulada && $this->convocatorias->abierta($id)) {
            return null;
        }

        return $this->pantalla('postulacion/cerrada', 'Postulación', ['convocatoria' => $convocatoria, 'yaPostulada' => $yaPostulada]);
    }

    /**
     * Lo que se muestra escrito al abrir el formulario: lo que la persona dejó en la revisión anterior o,
     * si no hay, lo que ya está guardado en su cuenta.
     *
     * @return array<string, string>
     */
    private function valoresIniciales(int $idConvocatoria): array
    {
        $guardado = $this->sesion->postulacionPendiente($idConvocatoria)['datos'] ?? $this->usuarios->perfilAprendiz($this->idAprendiz()) ?? [];
        $valores = [];
        foreach (self::CAMPOS as $campo) {
            $valores[$campo] = (string) ($guardado[$campo] ?? '');
        }
        $valores['fecha_nacimiento'] = $this->fechaParaEscribir($valores['fecha_nacimiento']);
        // La declaración nunca viene marcada de antemano: la persona debe aceptarla cada vez.
        $valores['acepta_datos'] = '';

        return $valores;
    }

    /** «2002-03-15» se muestra como «15/03/2002», que es como se escribe a mano. */
    private function fechaParaEscribir(string $fecha): string
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m) === 1 ? "$m[3]/$m[2]/$m[1]" : $fecha;
    }

    /** @return array<string, string> Los campos de texto del POST. */
    private function valoresDe(Peticion $peticion): array
    {
        $valores = [];
        foreach (self::CAMPOS as $campo) {
            $valores[$campo] = $peticion->campo($campo);
        }

        return $valores;
    }

    /** @return array<string, array<string, mixed>|null> Los tres documentos de la petición (null si no vino). */
    private function archivosDe(Peticion $peticion): array
    {
        $archivos = [];
        foreach (array_keys(ValidadorPostulacion::DOCUMENTOS) as $campo) {
            $archivos[$campo] = $peticion->archivo($campo);
        }

        return $archivos;
    }

    /** @return array{eps: list<array{id: int, nombre: string}>, bancos: list<array{id: int, nombre: string}>, tiposCuenta: list<array{id: int, nombre: string}>} */
    private function listas(): array
    {
        return [
            'eps' => $this->catalogos->eps(),
            'bancos' => $this->catalogos->bancos(),
            'tiposCuenta' => $this->catalogos->tiposCuenta(),
        ];
    }

    /**
     * @param array<string, mixed>  $convocatoria
     * @param array<string, string> $valores
     * @param array<string, string> $errores
     */
    private function pantallaFormulario(array $convocatoria, array $valores, array $errores): Respuesta
    {
        $id = (int) $convocatoria['id_convocatoria'];

        return $this->pantalla('postulacion/formulario', 'Postúlate a ' . $convocatoria['nombre'], [
            'convocatoria' => $convocatoria,
            'perfil' => $this->usuarios->perfilAprendiz($this->idAprendiz()) ?? [],
            'listas' => $this->listas(),
            'documentos' => ValidadorPostulacion::DOCUMENTOS,
            'valores' => $valores,
            'errores' => $errores,
            'accion' => "/convocatorias/$id/postulacion",
        ]);
    }

    /**
     * @param array<string, mixed> $convocatoria
     * @param array{datos: array<string, mixed>, archivos: array<string, array{ruta: string, extension: string, nombre_original: string, bytes: int}>} $pendiente
     */
    private function pantallaRevisar(array $convocatoria, array $pendiente): Respuesta
    {
        $id = (int) $convocatoria['id_convocatoria'];
        $listas = $this->listas();
        $datos = $pendiente['datos'];

        return $this->pantalla('postulacion/revisar', 'Revisa tu postulación', [
            'convocatoria' => $convocatoria,
            'perfil' => $this->usuarios->perfilAprendiz($this->idAprendiz()) ?? [],
            'datos' => $datos,
            'archivos' => $pendiente['archivos'],
            'documentos' => ValidadorPostulacion::DOCUMENTOS,
            'eps' => $this->nombreDe($listas['eps'], (int) $datos['id_eps']),
            'banco' => $this->nombreDe($listas['bancos'], (int) $datos['id_entidad_bancaria']),
            'tipoCuenta' => $this->nombreDe($listas['tiposCuenta'], (int) $datos['id_tipo_cuenta']),
            'volver' => "/convocatorias/$id/postulacion",
            'accionEnviar' => "/convocatorias/$id/postulacion/enviar",
        ]);
    }

    /**
     * @param list<array{id: int, nombre: string}> $lista
     * @return string El nombre de la opción con ese id ('' si no está).
     */
    private function nombreDe(array $lista, int $id): string
    {
        foreach ($lista as $opcion) {
            if ($opcion['id'] === $id) {
                return $opcion['nombre'];
            }
        }

        return '';
    }

    private function idAprendiz(): int
    {
        return $this->sesion->idUsuario() ?? 0;
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function pantalla(string $plantilla, string $titulo, array $datos): Respuesta
    {
        return Respuesta::html($this->vista->mostrar($plantilla, $datos + ['titulo' => $titulo], 'aprendiz'))->sinCache();
    }

    private function noEncontrada(): Respuesta
    {
        return Respuesta::html($this->vista->mostrar('errores/404', [], 'aprendiz'), 404);
    }
}
