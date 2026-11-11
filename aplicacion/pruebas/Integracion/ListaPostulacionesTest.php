<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Integracion;

use BenefitRack\Nucleo\Aplicacion;
use BenefitRack\Nucleo\Enrutador;
use BenefitRack\Nucleo\Fabrica;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Repositorios\RepositorioPostulaciones;
use BenefitRack\Servicios\FiltroPostulaciones;

/** M6a: la lista de postulaciones del administrador, sus filtros y los datos que van al Excel. */
final class ListaPostulacionesTest extends BaseDePrueba
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    private function repositorio(int $idAdmin): RepositorioPostulaciones
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idAdmin, '127.0.0.1');

        return new RepositorioPostulaciones($bd);
    }

    /** @param array<string, string> $consulta */
    private function filtro(array $consulta): FiltroPostulaciones
    {
        return FiltroPostulaciones::desdePeticion(new Peticion('GET', '/admin/postulaciones', [], [], '127.0.0.1', [], $consulta));
    }

    /**
     * Postula al aprendiz (como administrador, que es quien puede escribir cualquier dueño) y, si se pide, la deja Aprobada.
     *
     * @param array<string, mixed>|null $datosFormulario Lo que iría en datos_formulario.
     */
    private function postular(int $idAdmin, int $idAprendiz, int $idConvocatoria, bool $aprobar = false, ?array $datosFormulario = null): int
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idAdmin, '127.0.0.1');
        $pdo = $bd->pdo();
        $json = $datosFormulario === null ? null : json_encode($datosFormulario, JSON_UNESCAPED_UNICODE);
        $pdo->prepare('INSERT INTO postulaciones (id_usuario, id_convocatoria, datos_formulario) VALUES (?, ?, ?)')->execute([$idAprendiz, $idConvocatoria, $json]);
        $id = (int) $pdo->lastInsertId();
        if ($aprobar) {
            $pdo->exec("UPDATE postulaciones SET estado = 'Validada' WHERE id_postulacion = $id");
            $pdo->exec("UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = $id");
        }

        return $id;
    }

    /** El primer id de un catálogo (la base de prueba ya trae los catálogos cargados). */
    private function primerId(string $tabla, string $columna): int
    {
        $consulta = $this->baseDatosDeLaApp()->pdo()->query("SELECT MIN($columna) FROM $tabla");

        return (int) ($consulta === false ? 0 : $consulta->fetchColumn());
    }

    /**
     * Tres postulaciones a la misma convocatoria (una Aprobada); la segunda es de una aprendiz «Peña».
     *
     * @return array{admin: int, convocatoria: int, ids: list<int>, sufijo: string}
     */
    private function escenario(): array
    {
        $admin = $this->crearAdmin();
        $convocatoria = $this->crearConvocatoriaActiva(10);
        $sufijo = bin2hex(random_bytes(3));
        $ids = [
            $this->postular($admin, $this->crearAprendiz(['nombre' => 'Luisa' . $sufijo, 'apellido' => 'Ríos']), $convocatoria),
            $this->postular($admin, $this->crearAprendiz(['nombre' => 'Karen' . $sufijo, 'apellido' => 'Peña']), $convocatoria, true),
            $this->postular($admin, $this->crearAprendiz(['nombre' => 'Juan' . $sufijo, 'apellido' => 'Gómez']), $convocatoria),
        ];

        return ['admin' => $admin, 'convocatoria' => $convocatoria, 'ids' => $ids, 'sufijo' => $sufijo];
    }

    /**
     * @param array{filas: list<array<string, mixed>>} $resultado
     * @return list<int> Los id_postulacion de las filas de la página.
     */
    private function idsDe(array $resultado): array
    {
        return array_map(static fn (array $fila): int => (int) $fila['id_postulacion'], $resultado['filas']);
    }

    public function test_filtrar_por_estado_aprobada_devuelve_solo_esa(): void
    {
        $e = $this->escenario();

        $resultado = $this->repositorio($e['admin'])->buscar($this->filtro(['estado' => 'Aprobada', 'convocatoria' => (string) $e['convocatoria']]), 1);

        $this->assertSame(1, $resultado['total']);
        $this->assertSame([$e['ids'][1]], $this->idsDe($resultado));
    }

    public function test_buscar_pena_encuentra_a_una_aprendiz_de_apellido_pena(): void
    {
        $e = $this->escenario();

        // La base compara sin distinguir mayúsculas ni tildes (utf8mb4_unicode_ci): «peña» encuentra «Peña».
        $resultado = $this->repositorio($e['admin'])->buscar($this->filtro(['q' => 'peña', 'convocatoria' => (string) $e['convocatoria']]), 1);

        $this->assertSame([$e['ids'][1]], $this->idsDe($resultado));
    }

    public function test_buscar_por_documento(): void
    {
        $e = $this->escenario();
        $consulta = $this->baseDatosDeLaApp()->pdo()->query("SELECT documento FROM usuarios u JOIN postulaciones p ON p.id_usuario = u.id_usuario WHERE p.id_postulacion = {$e['ids'][2]}");
        $documento = (string) ($consulta === false ? '' : $consulta->fetchColumn());

        $resultado = $this->repositorio($e['admin'])->buscar($this->filtro(['q' => $documento]), 1);

        $this->assertSame([$e['ids'][2]], $this->idsDe($resultado));
    }

    public function test_los_comodines_del_buscador_se_buscan_como_letras(): void
    {
        $e = $this->escenario();

        // «%» y «_» sin escapar coincidirían con todo; escapados no coinciden con ningún nombre.
        foreach (['%', '_', '\\', 'Ka%'] as $texto) {
            $resultado = $this->repositorio($e['admin'])->buscar($this->filtro(['q' => $texto, 'convocatoria' => (string) $e['convocatoria']]), 1);
            $this->assertSame(0, $resultado['total'], "q=$texto");
        }
    }

    public function test_un_intento_de_inyeccion_en_el_texto_no_rompe_ni_devuelve_todo(): void
    {
        $e = $this->escenario();

        $resultado = $this->repositorio($e['admin'])->buscar($this->filtro(['q' => "' OR '1'='1", 'convocatoria' => (string) $e['convocatoria']]), 1);

        $this->assertSame(0, $resultado['total']);
    }

    public function test_filtrar_por_convocatoria_trae_solo_las_de_esa_convocatoria(): void
    {
        $e = $this->escenario();
        $otra = $this->crearConvocatoriaActiva(5);
        $this->postular($e['admin'], $this->crearAprendiz(), $otra);

        $resultado = $this->repositorio($e['admin'])->buscar($this->filtro(['convocatoria' => (string) $e['convocatoria']]), 1);

        $this->assertSame(3, $resultado['total']);
        $this->assertEqualsCanonicalizing($e['ids'], $this->idsDe($resultado));
    }

    public function test_filtrar_por_fechas_incluye_el_dia_hasta_completo(): void
    {
        $e = $this->escenario();
        $hoy = date('Y-m-d');

        $conHoy = $this->repositorio($e['admin'])->buscar($this->filtro(['desde' => $hoy, 'hasta' => $hoy, 'convocatoria' => (string) $e['convocatoria']]), 1);
        $conAyer = $this->repositorio($e['admin'])->buscar($this->filtro(['hasta' => date('Y-m-d', strtotime('-1 day')), 'convocatoria' => (string) $e['convocatoria']]), 1);

        $this->assertSame(3, $conHoy['total']);
        $this->assertSame(0, $conAyer['total']);
    }

    public function test_la_paginacion_corrige_una_pagina_que_no_existe(): void
    {
        $e = $this->escenario();

        $resultado = $this->repositorio($e['admin'])->buscar($this->filtro(['convocatoria' => (string) $e['convocatoria']]), 99, 2);

        $this->assertSame(2, $resultado['paginas']);
        $this->assertSame(2, $resultado['pagina']);
        $this->assertCount(1, $resultado['filas']);
    }

    public function test_para_exportar_resuelve_eps_banco_y_tipo_de_cuenta_desde_datos_formulario(): void
    {
        $admin = $this->crearAdmin();
        $convocatoria = $this->crearConvocatoriaActiva(10);
        $eps = $this->primerId('eps', 'id_eps');
        $banco = $this->primerId('entidades_bancarias', 'id_banco');
        $tipo = $this->primerId('tipos_cuenta', 'id_tipo_cuenta');
        $this->postular($admin, $this->crearAprendiz(), $convocatoria, false, [
            'telefono' => '3001112233', 'direccion' => 'Calle 1', 'fecha_nacimiento' => '2002-03-15',
            'id_eps' => $eps, 'id_entidad_bancaria' => $banco, 'id_tipo_cuenta' => $tipo,
            'numero_cuenta' => '0012345678', 'titular_cuenta' => 'Titular Ñandú', 'motivo' => 'Lo necesito.',
            'acepta_datos' => true, 'fecha_envio' => '2026-11-15 09:30:00',
        ]);

        $filas = iterator_to_array($this->repositorio($admin)->todasParaExportar($this->filtro(['convocatoria' => (string) $convocatoria])), false);

        $this->assertCount(1, $filas);
        $this->assertSame('3001112233', $filas[0]['telefono']);
        $this->assertSame('0012345678', $filas[0]['numero_cuenta']);
        $this->assertSame('Titular Ñandú', $filas[0]['titular']);
        $this->assertSame('Lo necesito.', $filas[0]['motivo']);
        $this->assertNotEmpty($filas[0]['eps']);
        $this->assertNotEmpty($filas[0]['banco']);
        $this->assertNotEmpty($filas[0]['tipo_cuenta']);
        $this->assertSame('Pendiente', $filas[0]['estado']);
    }

    public function test_una_postulacion_sin_datos_formulario_se_exporta_con_celdas_vacias(): void
    {
        $e = $this->escenario();

        $filas = iterator_to_array($this->repositorio($e['admin'])->todasParaExportar($this->filtro(['convocatoria' => (string) $e['convocatoria']])), false);

        $this->assertCount(3, $filas);
        $this->assertNull($filas[0]['banco']);
    }

    public function test_exportar_respeta_los_filtros(): void
    {
        $e = $this->escenario();

        $filas = iterator_to_array($this->repositorio($e['admin'])->todasParaExportar($this->filtro(['estado' => 'Aprobada', 'convocatoria' => (string) $e['convocatoria']])), false);

        $this->assertCount(1, $filas);
        $this->assertSame('Aprobada', $filas[0]['estado']);
    }

    // ---------- Pantallas (por el enrutador, como en el navegador) ----------

    private function fabrica(): Fabrica
    {
        return new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
    }

    /**
     * @param array<string, mixed> $consulta
     */
    private function pedir(Fabrica $fabrica, string $ruta, array $consulta = []): Respuesta
    {
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 2) . '/rutas.php')($enrutador, $fabrica);
        $aplicacion = new Aplicacion($enrutador, $fabrica->vista(), $this->configuracionDePrueba(), $fabrica);

        return $aplicacion->ejecutar(new Peticion('GET', $ruta, [], [], '127.0.0.1', [], $consulta));
    }

    public function test_el_admin_ve_la_lista_y_descarga_el_excel(): void
    {
        $e = $this->escenario();
        $fabrica = $this->fabrica();
        $fabrica->sesion()->iniciarPara($e['admin'], 'Administrador', false, 'Admin Prueba');

        $lista = $this->pedir($fabrica, '/admin/postulaciones', ['convocatoria' => (string) $e['convocatoria']]);
        $excel = $this->pedir($fabrica, '/admin/postulaciones/excel', ['convocatoria' => (string) $e['convocatoria'], 'columnas' => ['documento', 'nombre', 'estado']]);

        $this->assertSame(200, $lista->codigo());
        $this->assertStringContainsString('Peña', $lista->cuerpo());
        $this->assertSame(200, $excel->codigo());
        $this->assertSame('PK', substr($excel->cuerpo(), 0, 2));
    }

    public function test_un_aprendiz_no_puede_ver_la_lista_ni_el_excel(): void
    {
        $fabrica = $this->fabrica();
        $fabrica->sesion()->iniciarPara($this->crearAprendiz(), 'Aprendiz', false, 'Luisa');

        $this->assertSame(403, $this->pedir($fabrica, '/admin/postulaciones')->codigo());
        $this->assertSame(403, $this->pedir($fabrica, '/admin/postulaciones/excel', ['columnas' => ['documento']])->codigo());
    }

    public function test_sin_sesion_la_lista_manda_a_ingresar(): void
    {
        $respuesta = $this->pedir($this->fabrica(), '/admin/postulaciones');

        $this->assertSame(302, $respuesta->codigo());
        $this->assertSame('/ingresar', $respuesta->destino());
    }
}
