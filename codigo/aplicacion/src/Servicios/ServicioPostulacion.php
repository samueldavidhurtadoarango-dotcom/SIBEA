<?php

/**
 * SERVICIO DE POSTULACIÓN (enviar todo junto o nada)
 *
 * Paso 4 del recorrido. Cuando el aprendiz pulsa «Enviar postulación», este servicio guarda en UNA transacción:
 * 1) su contacto y EPS en «usuarios», 2) su cuenta en «informacion_bancaria», 3) la postulación con todo lo
 * enviado en «datos_formulario» y 4) los tres documentos (y sus archivos pasan a la carpeta definitiva).
 * Si cualquier paso falla se deshace la transacción y se borran los archivos que ya se habían movido: no queda
 * una postulación a medias ni archivos sueltos. Los errores técnicos se traducen a mensajes que se pueden mostrar.
 * No tiene SQL (está en los repositorios) ni HTML.
 *
 * No es "final" a propósito: las pruebas del controlador lo reemplazan por uno falso.
 */

declare(strict_types=1);

namespace SIBEA\Servicios;

use DateTimeImmutable;
use PDOException;
use SIBEA\Nucleo\ErrorDeNegocio;
use SIBEA\Nucleo\TraductorErroresBd;
use SIBEA\Repositorios\RepositorioDocumentos;
use SIBEA\Repositorios\RepositorioPostulaciones;
use SIBEA\Repositorios\RepositorioUsuarios;
use Throwable;

class ServicioPostulacion
{
    /** Campo del formulario => id del tipo de documento en la base (1 identidad, 4 certificado de EPS, 2 certificado bancario). */
    public const TIPOS_DOCUMENTO = ['doc_identidad' => 1, 'cert_eps' => 4, 'cert_bancario' => 2];

    /** Código de MariaDB para «clave única repetida»: aquí, (aprendiz, convocatoria) ya existe. */
    private const CODIGO_DUPLICADO = 1062;

    private const BYTES_POR_MB = 1048576;

    private readonly DateTimeImmutable $ahora;

    /**
     * @param RepositorioPostulaciones $postulaciones Crea la postulación y maneja la transacción.
     * @param RepositorioDocumentos    $documentos    Guarda las filas de los documentos.
     * @param RepositorioUsuarios      $usuarios      Guarda el contacto, la EPS y la cuenta bancaria.
     * @param AlmacenDocumentos        $almacen       Pasa los archivos de la carpeta temporal a la definitiva.
     * @param DateTimeImmutable|null   $ahora         La fecha de envío (por defecto, ahora; se cambia solo en las pruebas).
     * @param array<string, int>       $tipos         Campo => id del tipo de documento (se cambia solo en las pruebas).
     */
    public function __construct(
        private readonly RepositorioPostulaciones $postulaciones,
        private readonly RepositorioDocumentos $documentos,
        private readonly RepositorioUsuarios $usuarios,
        private readonly AlmacenDocumentos $almacen,
        ?DateTimeImmutable $ahora = null,
        private readonly array $tipos = self::TIPOS_DOCUMENTO,
    ) {
        $this->ahora = $ahora ?? new DateTimeImmutable();
    }

    /**
     * Envía la postulación: todo se guarda o no se guarda nada.
     *
     * @param int                  $idAprendiz          El aprendiz que se postula (el de la sesión).
     * @param int                  $idConvocatoria      La convocatoria.
     * @param array<string, mixed> $datos               Los campos ya validados (sin los archivos).
     * @param array<string, array{ruta: string, extension: string, nombre_original: string, bytes: int}> $archivosTemporales
     *                                                  Los tres documentos, por campo, en la carpeta temporal.
     * @return int El id de la postulación creada.
     * @throws ErrorDeNegocio Si algo falla (ya se deshizo todo): con un mensaje que se puede mostrar.
     */
    public function enviar(int $idAprendiz, int $idConvocatoria, array $datos, array $archivosTemporales): int
    {
        /** @var list<string> $movidos Rutas relativas de los archivos que ya pasaron a la carpeta definitiva. */
        $movidos = [];

        try {
            $this->postulaciones->iniciarTransaccion();
            $idPostulacion = $this->guardarTodo($idAprendiz, $idConvocatoria, $datos, $archivosTemporales, $movidos);
            $this->postulaciones->confirmarTransaccion();

            return $idPostulacion;
        } catch (Throwable $error) {
            // Todo o nada: se deshace la base y se borran los archivos que ya se habían movido.
            $this->postulaciones->revertirTransaccion();
            foreach ($movidos as $ruta) {
                $this->almacen->borrar($ruta);
            }

            throw $this->comoErrorDeNegocio($error);
        }
    }

    /**
     * Los cuatro pasos, dentro de la transacción.
     *
     * @param array<string, mixed>        $datos
     * @param array<string, array{ruta: string, extension: string, nombre_original: string, bytes: int}> $archivos
     * @param list<string>                $movidos Se va llenando con cada archivo movido (por referencia, para poder borrarlos si algo falla).
     */
    private function guardarTodo(int $idAprendiz, int $idConvocatoria, array $datos, array $archivos, array &$movidos): int
    {
        $this->usuarios->actualizarContacto($idAprendiz, (string) $datos['telefono'], (string) $datos['direccion'], (string) $datos['fecha_nacimiento'], (int) $datos['id_eps']);
        $this->usuarios->guardarCuentaBancaria($idAprendiz, (int) $datos['id_entidad_bancaria'], (int) $datos['id_tipo_cuenta'], (string) $datos['numero_cuenta'], (string) $datos['titular_cuenta']);
        $idPostulacion = $this->postulaciones->crear($idAprendiz, $idConvocatoria, $this->jsonDe($datos));
        $this->guardarDocumentos($idPostulacion, $archivos, $movidos);

        return $idPostulacion;
    }

    /**
     * Mueve cada archivo a la carpeta definitiva y guarda su fila. Cada archivo movido se anota de inmediato
     * en $movidos: si la fila falla, ese archivo también se borra.
     *
     * @param array<string, array{ruta: string, extension: string, nombre_original: string, bytes: int}> $archivos
     * @param list<string> $movidos
     */
    private function guardarDocumentos(int $idPostulacion, array $archivos, array &$movidos): void
    {
        foreach ($this->tipos as $campo => $idTipo) {
            $archivo = $archivos[$campo] ?? null;
            if ($archivo === null) {
                throw new ErrorDeNegocio('Falta uno de los documentos. Vuelve a adjuntarlos.');
            }
            $ruta = $this->almacen->guardar($archivo['ruta'], $archivo['extension']);
            $movidos[] = $ruta;
            $this->documentos->crear(
                $idPostulacion,
                $idTipo,
                $this->nombreLimpio($archivo['nombre_original']),
                $ruta,
                $archivo['extension'],
                $this->tamanoMb($archivo['bytes'])
            );
        }
    }

    /**
     * Todo lo enviado, más la fecha de envío, como JSON (la columna datos_formulario exige JSON válido).
     *
     * @param array<string, mixed> $datos
     */
    private function jsonDe(array $datos): string
    {
        $datos['fecha_envio'] = $this->ahora->format('Y-m-d H:i:s');

        return json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Regla de negocio: los documentos pesan de 0.01 a 5 MB (la base lo comprueba con un CHECK). Se redondea hacia
     * arriba a centésimas con enteros (sin decimales flotantes), y un archivo pequeño cuenta como 0.01.
     *
     * @return string Por ejemplo «1.44».
     */
    private function tamanoMb(int $bytes): string
    {
        $centesimas = max(1, intdiv($bytes * 100 + self::BYTES_POR_MB - 1, self::BYTES_POR_MB));

        return number_format($centesimas / 100, 2, '.', '');
    }

    /** El nombre original solo se muestra: se le quitan rutas y símbolos raros y se limita a 255 caracteres. */
    private function nombreLimpio(string $nombre): string
    {
        $nombre = basename(str_replace('\\', '/', $nombre));
        $nombre = mb_substr((string) preg_replace('/[^\p{L}\p{N}._ -]/u', '_', $nombre), 0, 255);

        return trim($nombre) === '' ? 'documento' : $nombre;
    }

    /** Convierte cualquier error en uno que se pueda mostrar; el detalle técnico queda en el log del servidor. */
    private function comoErrorDeNegocio(Throwable $error): ErrorDeNegocio
    {
        if ($error instanceof ErrorDeNegocio) {
            return $error;
        }
        if ($error instanceof PDOException) {
            // Regla de negocio: una sola postulación por aprendiz y convocatoria (clave única de la base).
            if (($error->errorInfo[1] ?? 0) === self::CODIGO_DUPLICADO) {
                return new ErrorDeNegocio('Ya te postulaste a esta convocatoria.', 0, $error);
            }

            return TraductorErroresBd::traducir($error);
        }
        error_log('[SIBEA] Error al enviar la postulación: ' . $error->getMessage());

        return new ErrorDeNegocio('No pudimos guardar tu postulación. Intenta de nuevo.', 0, $error);
    }
}
