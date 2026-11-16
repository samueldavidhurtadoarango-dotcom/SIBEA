<?php

/**
 * EXPORTADOR DE EXCEL
 *
 * Paso 4 del recorrido: arma los archivos .xlsx que el administrador descarga: el Excel de entrega de
 * contraseñas temporales, la plantilla de carga, el Excel de errores y el Excel de postulaciones (con las
 * columnas que él elija; las tareas siguientes agregan los demás reportes). El archivo se escribe en la carpeta
 * temporal del sistema y quien lo pidió (el controlador) lo envía y lo borra: NUNCA queda guardado en el servidor.
 *
 * Seguridad (inyección de fórmulas): si una celda de texto empieza por = + - o @,
 * Excel podría ejecutarla como fórmula. Por eso TODO texto se escribe como texto explícito.
 */

declare(strict_types=1);

namespace SIBEA\Servicios;

use Generator;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

final class ExportadorExcel
{
    private const ENCABEZADOS = ['Documento', 'Nombre completo', 'Correo', 'Contraseña temporal'];
    private const ENCABEZADOS_PLANTILLA = ['Documento', 'Nombre', 'Apellido', 'Correo', 'Programa', 'Ficha'];
    private const ENCABEZADOS_ERRORES = ['Fila', 'Campo', 'Motivo'];

    /**
     * Las columnas que se pueden pedir del Excel de postulaciones: clave => título. Es la LISTA BLANCA: solo estas
     * claves se exportan, así que lo que llegue de la URL jamás decide qué dato sale (ver columnasPedidas()).
     * El orden es el de la maqueta: datos del aprendiz, datos de la postulación y estado de los documentos.
     */
    public const COLUMNAS = [
        'documento' => 'Documento', 'nombre' => 'Nombre', 'correo' => 'Correo', 'telefono' => 'Teléfono',
        'programa' => 'Programa', 'ficha' => 'Ficha', 'eps' => 'EPS',
        'convocatoria' => 'Convocatoria', 'beneficio' => 'Beneficio', 'estado' => 'Estado', 'fecha' => 'Fecha de envío',
        'banco' => 'Banco', 'tipo_cuenta' => 'Tipo de cuenta', 'numero_cuenta' => 'Número de cuenta',
        'titular' => 'Titular', 'motivo' => 'Motivo',
        'documentos' => 'Estado de los documentos',
    ];

    /** Cómo se agrupan las columnas en la ventana «Descargar en Excel»: título del grupo => claves de COLUMNAS. */
    public const GRUPOS_COLUMNAS = [
        'Datos del aprendiz' => ['documento', 'nombre', 'correo', 'telefono', 'programa', 'ficha', 'eps'],
        'Datos de la postulación' => ['convocatoria', 'beneficio', 'estado', 'fecha', 'banco', 'tipo_cuenta', 'numero_cuenta', 'titular', 'motivo'],
        'Estado de los documentos' => ['documentos'],
    ];

    /**
     * Crea el Excel que Bienestar le entrega a cada aprendiz (documento, nombre, correo y contraseña).
     *
     * @param list<array{documento: string, nombre: string, correo: string, contrasena: string}> $filas Una por aprendiz.
     * @return string Ruta de un archivo temporal .xlsx (quien lo pidió lo envía y lo borra).
     */
    public function entregaContrasenas(array $filas): string
    {
        $tabla = [];
        foreach ($filas as $fila) {
            $tabla[] = [$fila['documento'], $fila['nombre'], $fila['correo'], $fila['contrasena']];
        }

        return $this->tabla('Entrega', self::ENCABEZADOS, $tabla);
    }

    /**
     * Crea la plantilla vacía para cargar aprendices: solo la fila de encabezados.
     *
     * @return string Ruta de un archivo temporal .xlsx (quien lo pidió lo envía y lo borra).
     */
    public function plantillaUsuarios(): string
    {
        return $this->tabla('Aprendices', self::ENCABEZADOS_PLANTILLA, []);
    }

    /**
     * Crea el Excel con los errores de una carga: en qué fila, en qué campo y por qué.
     *
     * @param list<array{fila: int, campo: string, motivo: string}> $errores Una fila de Excel por error.
     * @return string Ruta de un archivo temporal .xlsx (quien lo pidió lo envía y lo borra).
     */
    public function erroresCarga(array $errores): string
    {
        $tabla = [];
        foreach ($errores as $error) {
            $tabla[] = [(string) $error['fila'], $error['campo'], $error['motivo']];
        }

        return $this->tabla('Errores', self::ENCABEZADOS_ERRORES, $tabla);
    }

    /**
     * Quita de lo pedido toda clave que no esté en COLUMNAS (seguridad: lista blanca) y las deja en el orden de la maqueta.
     *
     * @param list<string> $pedidas Las claves que llegaron de la URL (pueden traer repetidas o inventadas).
     * @return list<string> Solo las claves conocidas, sin repetir.
     */
    public static function columnasPedidas(array $pedidas): array
    {
        return array_values(array_filter(array_keys(self::COLUMNAS), static fn (string $clave): bool => in_array($clave, $pedidas, true)));
    }

    /**
     * Crea el Excel de postulaciones con SOLO las columnas pedidas. Escribe fila por fila: recibe las filas
     * una a una (un generador) y cada una se vuelca a la hoja y se olvida, sin armar antes una lista gigante.
     *
     * @param iterable<array<string, mixed>> $filas    Las postulaciones, como las entrega el repositorio.
     * @param list<string>                   $columnas Claves de COLUMNAS (las desconocidas se ignoran).
     * @return string Ruta de un archivo temporal .xlsx (quien lo pidió lo envía y lo borra).
     * @throws InvalidArgumentException Si no queda ninguna columna válida.
     */
    public function postulaciones(iterable $filas, array $columnas): string
    {
        $columnas = self::columnasPedidas($columnas);
        if ($columnas === []) {
            throw new InvalidArgumentException('Hay que pedir al menos una columna.');
        }
        $titulos = array_map(static fn (string $clave): string => self::COLUMNAS[$clave], $columnas);

        return $this->tabla('Postulaciones', $titulos, $this->valoresDePostulaciones($filas, $columnas));
    }

    /**
     * Convierte cada postulación en la lista de textos de sus columnas, de a una (generador).
     *
     * @param iterable<array<string, mixed>> $filas
     * @param list<string>                   $columnas
     * @return Generator<int, list<string>>
     */
    private function valoresDePostulaciones(iterable $filas, array $columnas): Generator
    {
        foreach ($filas as $fila) {
            yield array_map(static fn (string $columna): string => self::valorDeColumna($fila, $columna), $columnas);
        }
    }

    /**
     * El texto de una celda. Regla: los documentos y las cuentas llevan SOLO dígitos (como texto, así no pierden
     * los ceros del inicio); la fecha va como «15 nov 2026»; un dato que no existe queda como celda vacía.
     *
     * @param array<string, mixed> $fila
     */
    private static function valorDeColumna(array $fila, string $columna): string
    {
        $valor = (string) ($fila[$columna] ?? '');

        return match ($columna) {
            'documentos' => ResumenDocumentos::texto($fila),
            'fecha' => formato_fecha(substr($valor, 0, 10)),
            'documento', 'numero_cuenta' => (string) preg_replace('/\D/', '', $valor),
            default => $valor,
        };
    }

    /**
     * Arma un libro de una hoja: encabezados en negrita y las filas debajo, todo como texto seguro.
     *
     * @param list<string>                $encabezados Títulos de las columnas.
     * @param iterable<list<string>>      $filas       Los datos, una lista por fila (se escriben de a una).
     * @return string Ruta del archivo temporal.
     */
    private function tabla(string $titulo, array $encabezados, iterable $filas): string
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle($titulo);

        $this->escribirFila($hoja, 1, $encabezados);
        $ultima = Coordinate::stringFromColumnIndex(count($encabezados));
        $hoja->getStyle("A1:{$ultima}1")->getFont()->setBold(true);
        $numeroFila = 1;
        foreach ($filas as $fila) {
            $this->escribirFila($hoja, ++$numeroFila, $fila);
        }
        foreach (range(1, count($encabezados)) as $columna) {
            $hoja->getColumnDimensionByColumn($columna)->setAutoSize(true);
        }

        return $this->guardarTemporal($libro);
    }

    /**
     * Limpia un texto antes de escribirlo: quita del inicio los tabuladores, retornos de
     * carro y caracteres de control, que también sirven para colar fórmulas (guía OWASP).
     * La otra mitad de la defensa está en escribirFila(): todo se escribe como texto.
     *
     * @param string $v El texto original.
     * @return string El texto sin caracteres de control al inicio.
     */
    public static function textoSeguro(string $v): string
    {
        return ltrim($v, "\x00..\x1F");
    }

    /**
     * @param list<string> $valores Un valor por columna (A, B, C...).
     */
    private function escribirFila(Worksheet $hoja, int $numeroFila, array $valores): void
    {
        foreach ($valores as $posicion => $valor) {
            // setValueExplicit(..., TYPE_STRING): la celda es texto aunque empiece por "=", "+", "-" o "@",
            // y los documentos no pierden ceros ni se convierten en números.
            $hoja->getCell([$posicion + 1, $numeroFila])->setValueExplicit(self::textoSeguro($valor), DataType::TYPE_STRING);
        }
    }

    /** Guarda el libro en un archivo temporal del sistema y devuelve su ruta. */
    private function guardarTemporal(Spreadsheet $libro): string
    {
        // tempnam reserva un nombre único (que nadie más puede adivinar ni pisar); se le agrega la extensión.
        $reservado = tempnam(sys_get_temp_dir(), 'br_entrega_');
        if ($reservado === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal.');
        }
        $ruta = $reservado . '.xlsx';
        try {
            if (!rename($reservado, $ruta)) {
                throw new RuntimeException('No se pudo preparar el archivo temporal.');
            }
            (new Xlsx($libro))->save($ruta);
        } catch (Throwable $error) {
            // Si algo falla, no se deja ningún archivo (vacío o a medias) en la carpeta temporal.
            @unlink($reservado);
            @unlink($ruta);
            throw $error;
        }
        $libro->disconnectWorksheets();

        return $ruta;
    }
}
