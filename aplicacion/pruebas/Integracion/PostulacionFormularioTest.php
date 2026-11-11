<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Integracion;

use BenefitRack\Nucleo\Aplicacion;
use BenefitRack\Nucleo\Csrf;
use BenefitRack\Nucleo\Enrutador;
use BenefitRack\Nucleo\Fabrica;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Repositorios\RepositorioConvocatorias;
use BenefitRack\Repositorios\RepositorioUsuarios;

/** M5a: el formulario de postulación contra la base real (consultas nuevas y rutas con sus filtros). */
final class PostulacionFormularioTest extends BaseDePrueba
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    private function convocatorias(int $idUsuario): RepositorioConvocatorias
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idUsuario, '127.0.0.1');

        return new RepositorioConvocatorias($bd);
    }

    /** Cambia el estado o las fechas de una convocatoria directamente (como root, solo para preparar la prueba). */
    private function ajustarConvocatoria(int $id, string $columnas): void
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($this->crearAdmin(), '127.0.0.1');
        $bd->pdo()->exec("UPDATE convocatorias SET $columnas WHERE id_convocatoria = $id");
    }

    public function test_abierta_es_true_solo_si_esta_activa_y_hoy_cae_entre_inicio_y_cierre(): void
    {
        $id = $this->crearConvocatoriaActiva();
        $repositorio = $this->convocatorias($this->crearAprendiz());

        $this->assertTrue($repositorio->abierta($id));

        $this->ajustarConvocatoria($id, "estado = 'Inactiva'");
        $this->assertFalse($repositorio->abierta($id));

        $this->ajustarConvocatoria($id, "estado = 'Activa', fecha_inicio = CURDATE() + INTERVAL 2 DAY, fecha_cierre = CURDATE() + INTERVAL 9 DAY");
        $this->assertFalse($repositorio->abierta($id)); // todavía no empieza

        $this->assertFalse($repositorio->abierta(999999)); // no existe
    }

    public function test_id_postulacion_de_dice_si_el_aprendiz_ya_se_postulo(): void
    {
        $idConvocatoria = $this->crearConvocatoriaActiva();
        $idAprendiz = $this->crearAprendiz();
        $repositorio = $this->convocatorias($idAprendiz);
        $this->assertNull($repositorio->idPostulacionDe($idConvocatoria, $idAprendiz));

        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($this->crearAdmin(), '127.0.0.1');
        $bd->pdo()->prepare('INSERT INTO postulaciones (id_usuario, id_convocatoria) VALUES (?, ?)')->execute([$idAprendiz, $idConvocatoria]);

        $this->assertIsInt($repositorio->idPostulacionDe($idConvocatoria, $idAprendiz));
        $this->assertNull($repositorio->idPostulacionDe($idConvocatoria, $this->crearAprendiz())); // otro aprendiz
    }

    public function test_perfil_aprendiz_trae_los_datos_de_la_cuenta_con_el_programa(): void
    {
        $id = $this->crearAprendiz(['nombre' => 'Luisa', 'apellido' => 'Ríos', 'ficha' => '2761190', 'id_programa' => 1, 'telefono' => '3001112233']);
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($id, '127.0.0.1');

        $perfil = (new RepositorioUsuarios($bd))->perfilAprendiz($id);

        $this->assertNotNull($perfil);
        $this->assertSame('Luisa', $perfil['nombre']);
        $this->assertSame('2761190', $perfil['ficha']);
        $this->assertSame('3001112233', $perfil['telefono']);
        $this->assertNotEmpty($perfil['programa']);
        $this->assertNull((new RepositorioUsuarios($bd))->perfilAprendiz($this->crearAdmin())); // un administrador no tiene perfil de aprendiz
    }

    // ---------- Rutas (por el enrutador, como en el navegador) ----------

    private function fabrica(): Fabrica
    {
        return new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
    }

    /**
     * @param array<string, string> $campos
     */
    private function enviar(Fabrica $fabrica, string $metodo, string $ruta, array $campos = []): Respuesta
    {
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 2) . '/rutas.php')($enrutador, $fabrica);
        $aplicacion = new Aplicacion($enrutador, $fabrica->vista(), $this->configuracionDePrueba(), $fabrica);

        return $aplicacion->ejecutar(new Peticion($metodo, $ruta, $campos, [], '127.0.0.1'));
    }

    private function fabricaDeAprendiz(): Fabrica
    {
        $fabrica = $this->fabrica();
        $fabrica->sesion()->iniciarPara($this->crearAprendiz(['nombre' => 'Ana', 'ficha' => '123456']), 'Aprendiz', false, 'Ana');

        return $fabrica;
    }

    public function test_el_aprendiz_ve_el_formulario_de_una_convocatoria_abierta(): void
    {
        $id = $this->crearConvocatoriaActiva();

        $respuesta = $this->enviar($this->fabricaDeAprendiz(), 'GET', "/convocatorias/$id/postulacion");

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('Postúlate a', $respuesta->cuerpo());
        $this->assertStringContainsString('Revisar y enviar', $respuesta->cuerpo());
    }

    public function test_una_convocatoria_inactiva_muestra_cerrada(): void
    {
        $id = $this->crearConvocatoriaActiva();
        $this->ajustarConvocatoria($id, "estado = 'Inactiva'");

        $cuerpo = $this->enviar($this->fabricaDeAprendiz(), 'GET', "/convocatorias/$id/postulacion")->cuerpo();

        $this->assertStringContainsString('Esta convocatoria ya cerró', $cuerpo);
    }

    public function test_una_convocatoria_que_no_existe_es_404(): void
    {
        $this->assertSame(404, $this->enviar($this->fabricaDeAprendiz(), 'GET', '/convocatorias/999999/postulacion')->codigo());
    }

    public function test_el_post_sin_nada_vuelve_al_formulario_con_los_errores_y_no_guarda_archivos(): void
    {
        $id = $this->crearConvocatoriaActiva();

        $respuesta = $this->enviar($this->fabricaDeAprendiz(), 'POST', "/convocatorias/$id/postulacion", ['_csrf' => Csrf::token()]);

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('Revisa ', $respuesta->cuerpo());
        $this->assertStringContainsString('Falta el Documento de identidad', $respuesta->cuerpo());
        $this->assertArrayNotHasKey('postulacion_pendiente', $_SESSION);
    }

    public function test_el_post_sin_token_csrf_es_403(): void
    {
        $id = $this->crearConvocatoriaActiva();

        $this->assertSame(403, $this->enviar($this->fabricaDeAprendiz(), 'POST', "/convocatorias/$id/postulacion")->codigo());
    }

    public function test_un_administrador_no_entra_a_la_postulacion(): void
    {
        $id = $this->crearConvocatoriaActiva();
        $fabrica = $this->fabrica();
        $fabrica->sesion()->iniciarPara($this->crearAdmin(), 'Administrador', false, 'Admin');

        $this->assertSame(403, $this->enviar($fabrica, 'GET', "/convocatorias/$id/postulacion")->codigo());
    }

    public function test_sin_sesion_manda_a_ingresar(): void
    {
        $this->assertSame('/ingresar', $this->enviar($this->fabrica(), 'GET', '/convocatorias/1/postulacion')->destino());
    }
}
