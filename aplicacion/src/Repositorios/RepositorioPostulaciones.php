<?php

/**
 * REPOSITORIO DE POSTULACIONES
 *
 * Paso 4 del recorrido: aquí vive el SQL de la tabla "postulaciones": crearla, buscarlas con filtros para la lista
 * del administrador, recorrerlas para el Excel y el control de la transacción con la que ServicioPostulacion guarda
 * todo junto o nada.
 *
 * Lista y Excel comparten UNA misma consulta (SELECT + FROM + WHERE), así el Excel siempre respeta los filtros de la
 * pantalla. Los datos del formulario (teléfono, banco, cuenta, motivo...) se leen de postulaciones.datos_formulario
 * (JSON) con JSON_EXTRACT/JSON_UNQUOTE directamente en el SQL: así la base entrega ya los nombres de la EPS, el banco
 * y el tipo de cuenta (resueltos con un JOIN por el id guardado en el JSON) y PHP no tiene que decodificar nada.
 *
 * Las reglas de fondo las impone la BASE DE DATOS con sus triggers (tr_postulacion_inicial): solo el propio
 * aprendiz crea su postulación, la convocatoria debe estar Activa y dentro de sus fechas, y el estado nace
 * Pendiente. La clave única (usuario, convocatoria) impide postularse dos veces.
 *
 * No es "final" a propósito: las pruebas del servicio la reemplazan por una falsa.
 */

declare(strict_types=1);

namespace BenefitRack\Repositorios;

use BenefitRack\Nucleo\BaseDatos;
use BenefitRack\Servicios\FiltroPostulaciones;
use BenefitRack\Servicios\Paginacion;
use Generator;
use PDO;
use PDOStatement;

class RepositorioPostulaciones
{
    /** Un valor de texto guardado en el JSON del formulario (NULL si la postulación no lo trae). */
    private const JSON = "JSON_UNQUOTE(JSON_EXTRACT(p.datos_formulario, '$.%s'))";

    /** El id de un catálogo guardado en el JSON, como número, para poder unirlo con su tabla. */
    private const JSON_ID = "CAST(JSON_UNQUOTE(JSON_EXTRACT(p.datos_formulario, '$.%s')) AS UNSIGNED)";

    /**
     * Las tablas de la consulta. Las uniones LEFT son las que pueden faltar (aprendiz sin programa, postulación
     * sin datos_formulario). Los tres %s son los ids del JSON: EPS, banco y tipo de cuenta.
     */
    private const DESDE = 'FROM postulaciones p
        JOIN usuarios u ON u.id_usuario = p.id_usuario
        JOIN convocatorias c ON c.id_convocatoria = p.id_convocatoria
        JOIN beneficios b ON b.id_beneficio = c.id_beneficio
        LEFT JOIN programas pr ON pr.id_programa = u.id_programa
        LEFT JOIN eps e ON e.id_eps = %s
        LEFT JOIN entidades_bancarias bk ON bk.id_banco = %s
        LEFT JOIN tipos_cuenta tc ON tc.id_tipo_cuenta = %s';

    private const ORDEN = 'ORDER BY p.fecha_creacion DESC, p.id_postulacion DESC';
    /**
     * @param BaseDatos $baseDatos La conexión de esta petición (la base sabe qué aprendiz actúa).
     */
    public function __construct(private readonly BaseDatos $baseDatos)
    {
    }

    /**
     * Crea la postulación en estado Pendiente (la base lo exige).
     *
     * @param int    $idUsuario      El aprendiz dueño.
     * @param int    $idConvocatoria La convocatoria.
     * @param string $datosJson      Todo lo que envió, ya como JSON válido (la columna lo comprueba).
     * @return int El id de la postulación nueva.
     */
    public function crear(int $idUsuario, int $idConvocatoria, string $datosJson): int
    {
        $this->baseDatos->pdo()->prepare(
            'INSERT INTO postulaciones (id_usuario, id_convocatoria, datos_formulario) VALUES (?, ?, ?)'
        )->execute([$idUsuario, $idConvocatoria, $datosJson]);

        return (int) $this->baseDatos->pdo()->lastInsertId();
    }

    /**
     * Una página de la lista de postulaciones, ya filtrada.
     *
     * @param FiltroPostulaciones $filtro    Los filtros ya revisados.
     * @param int                 $pagina    La página pedida (si no existe, se corrige a la más cercana).
     * @param int                 $porPagina Filas por página.
     * @return array{filas: list<array<string, mixed>>, total: int, pagina: int, paginas: int, porPagina: int}
     *         Las filas de la página y los datos para dibujar los números de página.
     */
    public function buscar(FiltroPostulaciones $filtro, int $pagina, int $porPagina = 20): array
    {
        [$donde, $parametros] = $this->condiciones($filtro);
        $total = $this->contar($donde, $parametros);
        $paginas = Paginacion::paginas($total, $porPagina);
        $pagina = Paginacion::corregir($pagina, $paginas);

        // LIMIT y OFFSET se enlazan como enteros (bindValue con PARAM_INT): así llegan sin comillas.
        $consulta = $this->preparar($this->sqlDeFilas($donde) . ' LIMIT ? OFFSET ?', [...$parametros, $porPagina, ($pagina - 1) * $porPagina]);
        $consulta->execute();

        return ['filas' => $consulta->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas, 'porPagina' => $porPagina];
    }

    /**
     * Todas las postulaciones que cumplen los filtros, de una en una (para el Excel). Es un generador: cada fila
     * se entrega cuando el exportador la pide y se olvida después, así PHP no arma otra lista gigante.
     * (El controlador de PDO ya trae el resultado a memoria; lo que se evita es duplicarlo.)
     *
     * @param FiltroPostulaciones $filtro Los mismos filtros de la pantalla.
     * @return Generator<int, array<string, mixed>> Cada fila trae las claves de ExportadorExcel::COLUMNAS y los conteos de documentos.
     */
    public function todasParaExportar(FiltroPostulaciones $filtro): Generator
    {
        [$donde, $parametros] = $this->condiciones($filtro);
        $consulta = $this->preparar($this->sqlDeFilas($donde), $parametros);
        $consulta->execute();

        while (($fila = $consulta->fetch()) !== false) {
            yield $fila;
        }
    }

    /**
     * Cuántas postulaciones cumplen los filtros (para calcular las páginas).
     *
     * @param string      $donde      El WHERE ya armado (con signos de pregunta).
     * @param list<mixed> $parametros Los valores de esos signos.
     */
    private function contar(string $donde, array $parametros): int
    {
        $consulta = $this->preparar('SELECT COUNT(*) ' . $this->desde() . ' ' . $donde, $parametros);
        $consulta->execute();

        return (int) $consulta->fetchColumn();
    }

    /**
     * El SELECT de cada fila. Los alias coinciden con las claves de ExportadorExcel::COLUMNAS, así la misma fila
     * sirve para la pantalla y para el Excel. Los documentos se resumen con subconsultas: cuántos hay, cuántos
     * esperan revisión, cuántos son no válidos y si hay una solicitud de documento Pendiente.
     */
    private function sqlDeFilas(string $donde): string
    {
        $documentos = '(SELECT COUNT(*) FROM documentos_postulacion d WHERE d.id_postulacion = p.id_postulacion%s)';
        $columnas = [
            'p.id_postulacion', 'u.documento', "CONCAT(u.nombre, ' ', u.apellido) AS nombre", 'u.correo',
            sprintf(self::JSON, 'telefono') . ' AS telefono', 'pr.nombre AS programa', 'u.ficha', 'e.nombre AS eps',
            'c.nombre AS convocatoria', 'b.nombre AS beneficio', 'p.estado', 'p.fecha_creacion AS fecha',
            'bk.nombre AS banco', 'tc.nombre AS tipo_cuenta',
            sprintf(self::JSON, 'numero_cuenta') . ' AS numero_cuenta', sprintf(self::JSON, 'titular_cuenta') . ' AS titular',
            sprintf(self::JSON, 'motivo') . ' AS motivo',
            sprintf($documentos, '') . ' AS docs_total',
            sprintf($documentos, " AND d.estado_validacion = 'Pendiente'") . ' AS docs_pendientes',
            sprintf($documentos, " AND d.estado_validacion = 'Rechazado'") . ' AS docs_no_validos',
            "EXISTS (SELECT 1 FROM solicitudes_documentacion s WHERE s.id_postulacion = p.id_postulacion AND s.estado = 'Pendiente') AS documento_pedido",
        ];

        return 'SELECT ' . implode(', ', $columnas) . ' ' . $this->desde() . ' ' . $donde . ' ' . self::ORDEN;
    }

    /** El FROM con los tres catálogos unidos por los ids que vienen dentro del JSON del formulario. */
    private function desde(): string
    {
        return sprintf(self::DESDE, sprintf(self::JSON_ID, 'id_eps'), sprintf(self::JSON_ID, 'id_entidad_bancaria'), sprintf(self::JSON_ID, 'id_tipo_cuenta'));
    }

    /**
     * Arma el WHERE. SEGURIDAD: el SQL solo lleva signos de pregunta; ningún valor del filtro se pega al texto
     * de la consulta. Los valores viajan aparte, así no se pueden volver SQL (inyección).
     *
     * @return array{0: string, 1: list<int|string>} El WHERE (puede ser vacío) y los valores de sus signos, en orden.
     */
    private function condiciones(FiltroPostulaciones $filtro): array
    {
        $condiciones = [];
        $parametros = [];
        if ($filtro->texto !== '') {
            $condiciones[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE ? OR u.documento LIKE ?)";
            // SEGURIDAD (LIKE): % y _ son comodines; si la persona los escribe, se escapan para buscarlos como letras.
            $patron = '%' . addcslashes($filtro->texto, '%_\\') . '%';
            array_push($parametros, $patron, $patron);
        }
        $this->agregarSiHay($condiciones, $parametros, 'c.id_beneficio = ?', $filtro->idBeneficio);
        $this->agregarSiHay($condiciones, $parametros, 'u.id_programa = ?', $filtro->idPrograma);
        $this->agregarSiHay($condiciones, $parametros, 'c.id_convocatoria = ?', $filtro->idConvocatoria);
        $this->agregarSiHay($condiciones, $parametros, 'p.estado = ?', $filtro->estado);
        $this->agregarSiHay($condiciones, $parametros, 'p.fecha_creacion >= ?', $filtro->desde);
        // «hasta» incluye el día completo: se compara contra el inicio del día siguiente.
        $this->agregarSiHay($condiciones, $parametros, 'p.fecha_creacion < DATE_ADD(?, INTERVAL 1 DAY)', $filtro->hasta);

        return [$condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones), $parametros];
    }

    /**
     * Suma una condición y su valor, solo si el filtro está activo (no es null).
     *
     * @param list<string>     $condiciones Se le agrega la condición.
     * @param list<int|string> $parametros  Se le agrega el valor.
     */
    private function agregarSiHay(array &$condiciones, array &$parametros, string $condicion, int|string|null $valor): void
    {
        if ($valor !== null) {
            $condiciones[] = $condicion;
            $parametros[] = $valor;
        }
    }

    /**
     * Prepara la consulta y enlaza cada valor en su posición: los enteros como enteros (los necesita el LIMIT),
     * lo demás como texto.
     *
     * @param list<mixed> $parametros
     */
    private function preparar(string $sql, array $parametros): PDOStatement
    {
        $consulta = $this->baseDatos->pdo()->prepare($sql);
        foreach ($parametros as $posicion => $valor) {
            $consulta->bindValue($posicion + 1, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        return $consulta;
    }
    /**
     * Empieza una transacción: lo que se escriba después se confirma o se deshace todo junto.
     *
     * @return void
     */
    public function iniciarTransaccion(): void
    {
        $this->baseDatos->pdo()->beginTransaction();
    }

    /**
     * Confirma todo lo escrito desde iniciarTransaccion().
     *
     * @return void
     */
    public function confirmarTransaccion(): void
    {
        $this->baseDatos->pdo()->commit();
    }

    /**
     * Deshace todo lo escrito desde iniciarTransaccion(). Si no hay transacción abierta (por ejemplo, el commit
     * ya la cerró al fallar) no hace nada, para no tapar el error original con otro.
     *
     * @return void
     */
    public function revertirTransaccion(): void
    {
        if ($this->baseDatos->pdo()->inTransaction()) {
            $this->baseDatos->pdo()->rollBack();
        }
    }
}
