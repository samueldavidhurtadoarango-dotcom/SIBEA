<?php

/**
 * SERVICIO DE CARGA DE APRENDICES DESDE EXCEL
 *
 * Paso 4 del recorrido: las reglas de negocio de crear muchas cuentas con un solo archivo.
 * Se hace en DOS tiempos a propósito:
 *   1. leer(): revisa el archivo fila por fila y NO crea nada (es la vista previa).
 *   2. confirmar(): crea solo las filas que estaban bien, cuando el administrador lo decide.
 * Así se ven todos los errores antes de tocar la base y se evita una carga a medias por un
 * descuido. Cada carga deja su rastro en la tabla «migraciones».
 * No tiene SQL (está en los repositorios) ni HTML (está en las vistas).
 */

declare(strict_types=1);

namespace BenefitRack\Servicios;

use BenefitRack\Nucleo\ErrorDeNegocio;
use BenefitRack\Repositorios\RepositorioCatalogos;
use BenefitRack\Repositorios\RepositorioMigraciones;
use BenefitRack\Validacion\ValidadorUsuario;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as LectorXlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

final class ServicioCargaUsuarios
{
    /** Columnas de la plantilla, en el orden de la plantilla (en el archivo pueden venir en otro orden). */
    public const COLUMNAS = ['Documento', 'Nombre', 'Apellido', 'Correo', 'Programa', 'Ficha'];

    /** Máximo de filas de aprendices por archivo (sin contar el encabezado). */
    public const MAXIMO_FILAS = 2000;

    private const ENTIDAD_DESTINO = 'usuarios';
    private const PREFIJO_ERROR = 'El archivo no es un Excel válido o le faltan columnas: ';

    /** Qué decir cuando una celda obligatoria está vacía (el validador solo dice «inválido»). */
    private const FALTA = [
        'documento' => 'Falta el documento',
        'nombre' => 'Falta el nombre',
        'apellido' => 'Falta el apellido',
        'correo' => 'Falta el correo',
        'programa' => 'Falta el programa',
        'ficha' => 'Falta la ficha',
    ];

    private readonly ValidadorUsuario $validador;

    /**
     * @param ServicioUsuarios        $usuarios   Crea cada cuenta (con su contraseña temporal).
     * @param RepositorioMigraciones  $migraciones Donde queda el registro de la carga.
     * @param RepositorioCatalogos    $catalogos  Los programas que el archivo puede nombrar.
     * @param ValidadorUsuario|null   $validador  Revisa cada fila (por defecto, el mismo del formulario).
     */
    public function __construct(
        private readonly ServicioUsuarios $usuarios,
        private readonly RepositorioMigraciones $migraciones,
        private readonly RepositorioCatalogos $catalogos,
        ?ValidadorUsuario $validador = null,
    ) {
        $this->validador = $validador ?? new ValidadorUsuario();
    }

    /**
     * Revisa el Excel sin crear nada. La fila 1 son los encabezados, así que los datos cuentan desde la 2
     * (el mismo número que el administrador ve en Excel).
     *
     * @param string $rutaXlsx Ruta del archivo (el temporal que dejó PHP al subirlo).
     * @return array{filas: list<array<string, mixed>>, errores: array<int, array<string, string>>}
     *         filas: las válidas, ya limpias (documento, nombre, apellido, correo, id_programa, ficha) más
     *         «fila» y «programa» para mostrarlas; errores: número de fila => campo => mensaje.
     * @throws ErrorDeNegocio Si no es un .xlsx, le faltan columnas o pasa de 2 000 filas.
     */
    public function leer(string $rutaXlsx): array
    {
        $hoja = $this->abrirHoja($rutaXlsx);
        if ($hoja->getHighestDataRow() - 1 > self::MAXIMO_FILAS) {
            throw new ErrorDeNegocio('El archivo tiene más de ' . self::MAXIMO_FILAS . ' filas de aprendices. Divídelo en varios archivos.');
        }

        $filasDelArchivo = $hoja->toArray(null, false, true, false);
        $posiciones = $this->posicionesDeColumnas(array_shift($filasDelArchivo) ?? []);

        return $this->revisarFilas($filasDelArchivo, $posiciones);
    }

    /**
     * Crea las cuentas de las filas válidas. Si una choca con la base (por ejemplo, otra carga creó ese
     * mismo documento entre la vista previa y ahora), esa fila se cuenta como fallida con el mensaje ya
     * traducido y las demás siguen: un solo choque no debe tirar abajo toda la carga.
     *
     * @param list<array<string, mixed>> $filasValidas Las filas que devolvió leer() (con su número de «fila»).
     * @param string                     $nombreArchivo El nombre del Excel, para el registro de la carga.
     * @return array{creadas: int, fallidas: int, entrega: list<array{documento: string, nombre: string, correo: string, contrasena: string}>, errores: array<int, array<string, string>>}
     *         entrega: lo que lleva el Excel de entrega (con la contraseña temporal de cada cuenta creada).
     */
    public function confirmar(array $filasValidas, string $nombreArchivo): array
    {
        // La carga queda «En proceso» antes de crear nada: si algo se corta, el rastro existe.
        $id = $this->migraciones->iniciar(self::ENTIDAD_DESTINO, mb_substr($nombreArchivo, 0, 255), count($filasValidas));
        $resultado = ['creadas' => 0, 'fallidas' => 0, 'entrega' => [], 'errores' => []];

        try {
            foreach ($filasValidas as $fila) {
                $resultado = $this->crearFila($fila, $resultado);
            }
        } catch (Throwable $error) {
            // Algo inesperado: la carga se cierra «Fallida» (la base no deja reabrirla) y el error sigue su camino.
            $this->migraciones->finalizar($id, $resultado['creadas'], $resultado['fallidas'], 'Fallida', self::aplanarErrores($resultado['errores']));
            throw $error;
        }

        $estado = $resultado['fallidas'] > 0 ? 'Completada con errores' : 'Completada';
        $this->migraciones->finalizar($id, $resultado['creadas'], $resultado['fallidas'], $estado, self::aplanarErrores($resultado['errores']));

        return $resultado;
    }

    /**
     * Convierte «fila => campo => mensaje» en una lista simple (para guardarla como JSON o ponerla en un Excel).
     *
     * @param array<int, array<string, string>> $errores
     * @return list<array{fila: int, campo: string, motivo: string}>
     */
    public static function aplanarErrores(array $errores): array
    {
        $lista = [];
        foreach ($errores as $fila => $campos) {
            foreach ($campos as $campo => $motivo) {
                $lista[] = ['fila' => $fila, 'campo' => $campo, 'motivo' => $motivo];
            }
        }

        return $lista;
    }

    /** Abre el .xlsx; cualquier otra cosa (un .txt renombrado, un archivo dañado) es un error de negocio, no un 500. */
    private function abrirHoja(string $ruta): Worksheet
    {
        $lector = new LectorXlsx();
        $lector->setReadDataOnly(true); // solo valores: nada de estilos, macros ni vínculos externos

        try {
            if (!$lector->canRead($ruta)) {
                throw new ErrorDeNegocio(self::PREFIJO_ERROR . 'el archivo no se pudo abrir como libro de Excel (.xlsx).');
            }

            return $lector->load($ruta)->getActiveSheet();
        } catch (ErrorDeNegocio $error) {
            throw $error;
        } catch (Throwable $error) {
            throw new ErrorDeNegocio(self::PREFIJO_ERROR . 'el archivo no se pudo abrir como libro de Excel (.xlsx).', 0, $error);
        }
    }

    /**
     * Busca en qué posición está cada columna de la plantilla (por su nombre, sin importar el orden).
     *
     * @param list<mixed> $encabezados La fila 1 del archivo.
     * @return array<string, int> Nombre de la columna => posición (0, 1, 2...).
     * @throws ErrorDeNegocio Si falta alguna columna: el mensaje las nombra.
     */
    private function posicionesDeColumnas(array $encabezados): array
    {
        $porNombre = [];
        foreach ($encabezados as $posicion => $titulo) {
            $porNombre[mb_strtolower(trim((string) $titulo))] = $posicion;
        }

        $posiciones = [];
        $faltan = [];
        foreach (self::COLUMNAS as $columna) {
            $posicion = $porNombre[mb_strtolower($columna)] ?? null;
            $posicion === null ? $faltan[] = $columna : $posiciones[$columna] = $posicion;
        }
        if ($faltan !== []) {
            throw new ErrorDeNegocio(self::PREFIJO_ERROR . implode(', ', $faltan) . '.');
        }

        return $posiciones;
    }

    /**
     * @param list<list<mixed>>  $filasDelArchivo Las filas de datos (sin el encabezado).
     * @param array<string, int> $posiciones      Dónde está cada columna.
     * @return array{filas: list<array<string, mixed>>, errores: array<int, array<string, string>>}
     */
    private function revisarFilas(array $filasDelArchivo, array $posiciones): array
    {
        $programas = $this->programasPorNombre();
        $vistos = ['documento' => [], 'correo' => []];
        $validas = [];
        $errores = [];

        foreach ($filasDelArchivo as $indice => $celdas) {
            $numero = $indice + 2; // la fila 1 es el encabezado
            $campos = $this->camposDeLaFila($celdas, $posiciones);
            if (implode('', $campos) === '') {
                continue; // fila en blanco: no es un aprendiz, se ignora
            }

            $resultado = $this->validador->validar($campos, $programas);
            $problemas = $resultado->esValido()
                ? $this->repetidos($resultado->datos(), $vistos)
                : $this->mensajesDeFalta($resultado->errores(), $campos);

            if ($problemas !== []) {
                $errores[$numero] = $problemas;
                continue;
            }
            $validas[] = ['fila' => $numero, 'programa' => $campos['programa']] + $resultado->datos();
        }

        return ['filas' => $validas, 'errores' => $errores];
    }

    /**
     * @param list<mixed>        $celdas
     * @param array<string, int> $posiciones
     * @return array<string, string> documento, nombre, apellido, correo, programa y ficha, recortados.
     */
    private function camposDeLaFila(array $celdas, array $posiciones): array
    {
        $campos = [];
        foreach ($posiciones as $columna => $posicion) {
            $campos[mb_strtolower($columna)] = trim((string) ($celdas[$posicion] ?? ''));
        }

        return $campos;
    }

    /**
     * Si la celda estaba vacía, el mensaje es «Falta el correo» y no el genérico del validador.
     *
     * @param array<string, string> $errores Los mensajes del validador.
     * @param array<string, string> $campos  Lo que traía la fila.
     * @return array<string, string>
     */
    private function mensajesDeFalta(array $errores, array $campos): array
    {
        foreach ($errores as $campo => $mensaje) {
            if (($campos[$campo] ?? '') === '') {
                $errores[$campo] = self::FALTA[$campo] ?? $mensaje;
            }
        }

        return $errores;
    }

    /**
     * Regla de negocio: el documento y el correo no pueden repetirse dentro del archivo (la base tampoco lo
     * permitiría). Gana la primera fila; las siguientes son error. Solo se anotan como «vistos» los de una
     * fila que sí se acepta.
     *
     * @param array<string, mixed>                     $datos  La fila ya validada.
     * @param array{documento: array<string, true>, correo: array<string, true>} $vistos Lo ya visto en el archivo (se actualiza).
     * @return array<string, string> Los problemas encontrados (vacío si no hay).
     */
    private function repetidos(array $datos, array &$vistos): array
    {
        $problemas = [];
        if (isset($vistos['documento'][(string) $datos['documento']])) {
            $problemas['documento'] = 'Documento repetido en el archivo';
        }
        if (isset($vistos['correo'][(string) $datos['correo']])) {
            $problemas['correo'] = 'Correo repetido en el archivo';
        }
        if ($problemas === []) {
            $vistos['documento'][(string) $datos['documento']] = true;
            $vistos['correo'][(string) $datos['correo']] = true;
        }

        return $problemas;
    }

    /**
     * Crea una cuenta y suma el resultado; si la base la rechaza, la anota como fallida y sigue.
     *
     * @param array<string, mixed> $fila
     * @param array{creadas: int, fallidas: int, entrega: list<array{documento: string, nombre: string, correo: string, contrasena: string}>, errores: array<int, array<string, string>>} $resultado
     * @return array{creadas: int, fallidas: int, entrega: list<array{documento: string, nombre: string, correo: string, contrasena: string}>, errores: array<int, array<string, string>>}
     */
    private function crearFila(array $fila, array $resultado): array
    {
        try {
            $cuenta = $this->usuarios->crearAprendiz($fila);
        } catch (ErrorDeNegocio $error) {
            $resultado['fallidas']++;
            $resultado['errores'][(int) $fila['fila']] = ['cuenta' => $error->getMessage()];

            return $resultado;
        }

        $resultado['creadas']++;
        $resultado['entrega'][] = [
            'documento' => (string) $fila['documento'],
            'nombre' => $fila['nombre'] . ' ' . $fila['apellido'],
            'correo' => (string) $fila['correo'],
            'contrasena' => $cuenta['contrasena'],
        ];

        return $resultado;
    }

    /** @return array<string, int> Nombre del programa => id (para el validador). */
    private function programasPorNombre(): array
    {
        $mapa = [];
        foreach ($this->catalogos->programas() as $programa) {
            $mapa[$programa['nombre']] = $programa['id'];
        }

        return $mapa;
    }
}
