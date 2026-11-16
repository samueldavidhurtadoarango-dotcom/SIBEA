<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Integracion;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SIBEA\Controladores\CargaUsuariosControlador;
use SIBEA\Nucleo\Aplicacion;
use SIBEA\Nucleo\Csrf;
use SIBEA\Nucleo\Enrutador;
use SIBEA\Nucleo\Fabrica;
use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Respuesta;
use SIBEA\Repositorios\RepositorioCatalogos;
use SIBEA\Repositorios\RepositorioMigraciones;
use SIBEA\Repositorios\RepositorioUsuarios;
use SIBEA\Servicios\CargaInterrumpida;
use SIBEA\Servicios\ServicioCargaUsuarios;
use SIBEA\Servicios\ServicioUsuarios;

/** M3b: confirmar la carga crea las cuentas y deja el rastro en `migraciones`. */
final class CargaUsuariosTest extends BaseDePrueba
{
    private function servicio(int $idAdmin): ServicioCargaUsuarios
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idAdmin, '127.0.0.1');

        return new ServicioCargaUsuarios(
            new ServicioUsuarios(new RepositorioUsuarios($bd)),
            new RepositorioMigraciones($bd),
            new RepositorioCatalogos($bd)
        );
    }

    /** @return array<string, mixed> */
    private function filaValida(int $numero, ?string $documento = null): array
    {
        $sufijo = bin2hex(random_bytes(4));

        return [
            'fila' => $numero,
            'documento' => $documento ?? (string) random_int(1000000000, 1999999999),
            'nombre' => 'Ana',
            'apellido' => 'Peña',
            'correo' => "carga$sufijo@soy.sena.edu.co",
            'programa' => 'ADSO',
            'id_programa' => (int) (new RepositorioCatalogos($this->baseDatosDeLaApp()))->programas()[0]['id'],
            'ficha' => '2758341',
        ];
    }

    /** @return array<string, mixed> */
    private function migracion(int $idAdmin): array
    {
        $consulta = $this->baseDatosDeLaApp()->pdo()->prepare('SELECT * FROM migraciones WHERE id_usuario = ? ORDER BY id_migracion DESC LIMIT 1');
        $consulta->execute([$idAdmin]);
        $fila = $consulta->fetch();

        return is_array($fila) ? $fila : [];
    }

    public function test_confirmar_crea_dos_cuentas_y_registra_la_migracion_completada(): void
    {
        $admin = $this->crearAdmin();

        $resultado = $this->servicio($admin)->confirmar([$this->filaValida(2), $this->filaValida(3)], 'aprendices.xlsx');

        $this->assertSame(2, $resultado['creadas']);
        $this->assertSame(0, $resultado['fallidas']);
        $this->assertCount(2, $resultado['entrega']);
        $this->assertSame(10, strlen($resultado['entrega'][0]['contrasena']));
        $m = $this->migracion($admin);
        $this->assertSame('usuarios', $m['entidad_destino']);
        $this->assertSame('aprendices.xlsx', $m['nombre_archivo']);
        $this->assertSame(2, (int) $m['registros_exitosos']);
        $this->assertSame('Completada', $m['estado']);
    }

    public function test_una_fila_que_choca_al_confirmar_cuenta_como_fallida_y_las_demas_siguen(): void
    {
        $admin = $this->crearAdmin();
        $existente = $this->crearAprendiz();
        $doc = (string) $this->baseDatosDeLaApp()->pdo()->query("SELECT documento FROM usuarios WHERE id_usuario = $existente")->fetchColumn();

        $resultado = $this->servicio($admin)->confirmar([$this->filaValida(2, $doc), $this->filaValida(3)], 'choque.xlsx');

        $this->assertSame(1, $resultado['creadas']);
        $this->assertSame(1, $resultado['fallidas']);
        $this->assertArrayHasKey(2, $resultado['errores']);
        $m = $this->migracion($admin);
        $this->assertSame('Completada con errores', $m['estado']);
        $this->assertSame(1, (int) $m['registros_fallidos']);
        $detalle = json_decode((string) $m['detalle_errores'], true);
        $this->assertSame(2, $detalle[0]['fila']);
    }

    // ---------- Un fallo inesperado a la mitad de la confirmación ----------

    /** Un repositorio que falla al crear la SEGUNDA cuenta (como un corte de la base a mitad de carga). */
    private function servicioQueFallaEnLaSegunda(\SIBEA\Nucleo\BaseDatos $bd): ServicioCargaUsuarios
    {
        $repositorio = new class ($bd) extends RepositorioUsuarios {
            private int $llamadas = 0;

            public function crear(array $datos, string $hash): int
            {
                if (++$this->llamadas === 2) {
                    throw new \RuntimeException('falla simulada');
                }

                return parent::crear($datos, $hash);
            }
        };

        return new ServicioCargaUsuarios(new ServicioUsuarios($repositorio), new RepositorioMigraciones($bd), new RepositorioCatalogos($bd));
    }

    public function test_un_fallo_inesperado_devuelve_lo_creado_hasta_ahora_y_cierra_la_migracion_fallida(): void
    {
        $admin = $this->crearAdmin();
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($admin, '127.0.0.1');

        try {
            $this->servicioQueFallaEnLaSegunda($bd)->confirmar([$this->filaValida(2), $this->filaValida(3), $this->filaValida(4)], 'corte.xlsx');
            $this->fail('Debía lanzar CargaInterrumpida.');
        } catch (CargaInterrumpida $interrupcion) {
            $this->assertSame(1, $interrupcion->resultado()['creadas']);
            $this->assertCount(1, $interrupcion->resultado()['entrega']);
            $this->assertSame(10, strlen($interrupcion->resultado()['entrega'][0]['contrasena']));
            $this->assertSame('falla simulada', $interrupcion->getPrevious()?->getMessage());
        }
        $m = $this->migracion($admin);
        $this->assertSame('Fallida', $m['estado']);
        $this->assertSame(1, (int) $m['registros_exitosos']);
    }

    public function test_el_controlador_guarda_la_entrega_parcial_y_vacia_las_filas_pendientes(): void
    {
        $fabrica = $this->fabricaDeAdmin();
        $servicio = $this->servicioQueFallaEnLaSegunda($fabrica->baseDatos());
        $fabrica->sesion()->guardarCarga(['archivo' => 'corte.xlsx', 'filas' => [$this->filaValida(2), $this->filaValida(3)], 'errores' => []]);
        $controlador = new CargaUsuariosControlador($fabrica->sesion(), $fabrica->vista(), $servicio);

        // El controlador anota la causa en el registro de errores de PHP: se desvía a un archivo para no ensuciar la salida.
        $registro = tempnam(sys_get_temp_dir(), 'br_');
        $anterior = ini_set('error_log', $registro);
        $respuesta = $controlador->confirmar(new Peticion('POST', '/admin/usuarios/cargar/confirmar', [], [], '127.0.0.1'));
        ini_set('error_log', (string) $anterior);
        $this->assertStringContainsString('falla simulada', (string) file_get_contents($registro));
        @unlink($registro);

        // La cuenta que sí se creó conserva su contraseña en la entrega, y reintentar no repite filas.
        $this->assertCount(1, $fabrica->sesion()->entregaPendiente());
        $this->assertSame([], $_SESSION['carga_usuarios']['filas']);
        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('se interrumpió', $respuesta->cuerpo());
        $this->assertStringContainsString('Descargar Excel de entrega', $respuesta->cuerpo());
    }

    // ---------- Pantallas (por el enrutador, como en el navegador) ----------

    /** @var list<string> Excel de prueba creados por subida() (se borran en tearDown). */
    private array $subidos = [];

    protected function tearDown(): void
    {
        foreach ($this->subidos as $ruta) {
            @unlink($ruta);
        }
    }

    protected function setUp(): void
    {
        $_SESSION = [];
    }

    private function fabricaDeAdmin(): Fabrica
    {
        $fabrica = new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
        $fabrica->sesion()->iniciarPara($this->crearAdmin(), 'Administrador', false, 'Admin Prueba');

        return $fabrica;
    }

    /**
     * @param array<string, string>               $campos
     * @param array<string, array<string, mixed>> $archivos
     */
    private function enviar(Fabrica $fabrica, string $metodo, string $ruta, array $campos = [], array $archivos = []): Respuesta
    {
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 2) . '/rutas.php')($enrutador, $fabrica);
        $aplicacion = new Aplicacion($enrutador, $fabrica->vista(), $this->configuracionDePrueba(), $fabrica);

        return $aplicacion->ejecutar(new Peticion($metodo, $ruta, $campos, $archivos, '127.0.0.1', [], []));
    }

    /**
     * Crea un Excel de prueba y devuelve el arreglo que PHP pondría en $_FILES['archivo'].
     *
     * @param list<list<string>> $filas
     * @return array<string, array<string, mixed>>
     */
    private function subida(array $filas, string $contenidoCrudo = ''): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'br_subida_');
        $this->subidos[] = $ruta;
        if ($contenidoCrudo !== '') {
            file_put_contents($ruta, $contenidoCrudo);
        } else {
            $libro = new Spreadsheet();
            foreach ($filas as $i => $fila) {
                foreach ($fila as $j => $valor) {
                    $libro->getActiveSheet()->getCell([$j + 1, $i + 1])->setValueExplicit($valor, DataType::TYPE_STRING);
                }
            }
            (new Xlsx($libro))->save($ruta);
        }

        return ['archivo' => ['name' => 'aprendices.xlsx', 'tmp_name' => $ruta, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($ruta)]];
    }

    /** @return list<list<string>> */
    private function excelDeDos(): array
    {
        $programa = (string) (new RepositorioCatalogos($this->baseDatosDeLaApp()))->programas()[0]['nombre'];
        $doc = (string) random_int(1000000000, 1999999999);

        return [
            ['Documento', 'Nombre', 'Apellido', 'Correo', 'Programa', 'Ficha'],
            [$doc, 'Ana', 'Peña', "a$doc@soy.sena.edu.co", $programa, '2758341'],
            ['sin-numeros', 'Luis', 'Rojas', "l$doc@soy.sena.edu.co", $programa, '2758341'],
        ];
    }

    public function test_la_vista_previa_guarda_las_filas_validas_en_la_sesion_y_no_crea_nada(): void
    {
        $fabrica = $this->fabricaDeAdmin();
        $antes = (int) $this->baseDatosDeLaApp()->pdo()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();

        $subida = $this->subida($this->excelDeDos());
        $respuesta = $this->enviar($fabrica, 'POST', '/admin/usuarios/cargar', ['_csrf' => Csrf::token()], $subida);

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('Revisa el archivo', $respuesta->cuerpo());
        $this->assertStringContainsString('1 de 2 filas están listas', $respuesta->cuerpo());
        $this->assertCount(1, $_SESSION['carga_usuarios']['filas']);
        $this->assertSame($antes, (int) $this->baseDatosDeLaApp()->pdo()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
    }

    public function test_confirmar_crea_las_cuentas_suma_la_entrega_y_ofrece_las_descargas(): void
    {
        $fabrica = $this->fabricaDeAdmin();
        $this->enviar($fabrica, 'POST', '/admin/usuarios/cargar', ['_csrf' => Csrf::token()], $this->subida($this->excelDeDos()));

        $respuesta = $this->enviar($fabrica, 'POST', '/admin/usuarios/cargar/confirmar', ['_csrf' => Csrf::token()]);

        $this->assertStringContainsString('Se creó 1 cuenta', $respuesta->cuerpo());
        $this->assertStringContainsString('/admin/usuarios/entrega', $respuesta->cuerpo());
        $this->assertStringContainsString('Descargar Excel de entrega', $respuesta->cuerpo());
        $this->assertStringContainsString('Descargar errores', $respuesta->cuerpo());
        $this->assertSame('no-store', $respuesta->cabecera('Cache-Control'));
        $this->assertCount(1, $fabrica->sesion()->entregaPendiente());
        // Confirmar de nuevo no crea nada: las filas ya se usaron.
        $otra = $this->enviar($fabrica, 'POST', '/admin/usuarios/cargar/confirmar', ['_csrf' => Csrf::token()]);
        $this->assertSame('/admin/usuarios/cargar', $otra->destino());
        $this->assertCount(1, $fabrica->sesion()->entregaPendiente());
    }

    public function test_la_descarga_de_errores_trae_fila_campo_y_motivo(): void
    {
        $fabrica = $this->fabricaDeAdmin();
        $this->enviar($fabrica, 'POST', '/admin/usuarios/cargar', ['_csrf' => Csrf::token()], $this->subida($this->excelDeDos()));

        $respuesta = $this->enviar($fabrica, 'GET', '/admin/usuarios/cargar/errores');

        $this->assertSame(200, $respuesta->codigo());
        $ruta = tempnam(sys_get_temp_dir(), 'br_prueba_');
        file_put_contents($ruta, $respuesta->cuerpo());
        $hoja = IOFactory::load($ruta)->getActiveSheet();
        @unlink($ruta);
        $this->assertSame('3', $hoja->getCell('A2')->getValue());
        $this->assertSame('documento', $hoja->getCell('B2')->getValue());
    }

    public function test_un_archivo_invalido_muestra_la_pantalla_de_archivo_invalido(): void
    {
        $fabrica = $this->fabricaDeAdmin();

        $respuesta = $this->enviar($fabrica, 'POST', '/admin/usuarios/cargar', ['_csrf' => Csrf::token()], $this->subida([], 'no es excel'));

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('El archivo no es un Excel', $respuesta->cuerpo());
        $this->assertArrayNotHasKey('carga_usuarios', $_SESSION);
    }

    public function test_sin_archivo_vuelve_al_formulario_con_un_aviso(): void
    {
        $respuesta = $this->enviar($this->fabricaDeAdmin(), 'POST', '/admin/usuarios/cargar', ['_csrf' => Csrf::token()]);

        $this->assertStringContainsString('Elige un archivo', $respuesta->cuerpo());
    }

    public function test_la_plantilla_trae_las_seis_columnas(): void
    {
        $respuesta = $this->enviar($this->fabricaDeAdmin(), 'GET', '/admin/usuarios/plantilla');

        $ruta = tempnam(sys_get_temp_dir(), 'br_prueba_');
        file_put_contents($ruta, $respuesta->cuerpo());
        $hoja = IOFactory::load($ruta)->getActiveSheet();
        @unlink($ruta);
        $this->assertSame(['Documento', 'Nombre', 'Apellido', 'Correo', 'Programa', 'Ficha'], $hoja->rangeToArray('A1:F1')[0]);
    }

    public function test_cargar_sin_token_es_403_y_un_aprendiz_no_entra(): void
    {
        $this->assertSame(403, $this->enviar($this->fabricaDeAdmin(), 'POST', '/admin/usuarios/cargar')->codigo());

        $fabrica = new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
        $fabrica->sesion()->iniciarPara($this->crearAprendiz(), 'Aprendiz', false, 'Aprendiz Prueba');
        $this->assertSame(403, $this->enviar($fabrica, 'GET', '/admin/usuarios/cargar')->codigo());
    }
}
