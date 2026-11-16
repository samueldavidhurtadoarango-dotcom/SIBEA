<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Integracion;

use SIBEA\Nucleo\Aplicacion;
use SIBEA\Nucleo\Csrf;
use SIBEA\Nucleo\Enrutador;
use SIBEA\Nucleo\Fabrica;
use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Respuesta;
use SIBEA\Repositorios\RepositorioPostulaciones;

/** M7: «Mi proceso» del aprendiz contra la base real (consulta nueva, ruta con sus filtros y primer ingreso). */
final class MiProcesoTest extends BaseDePrueba
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    private function fabrica(): Fabrica
    {
        return new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
    }

    /** @param array<string, string> $campos */
    private function enviar(Fabrica $fabrica, string $metodo, string $ruta, array $campos = []): Respuesta
    {
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 2) . '/rutas.php')($enrutador, $fabrica);
        $aplicacion = new Aplicacion($enrutador, $fabrica->vista(), $this->configuracionDePrueba(), $fabrica);

        return $aplicacion->ejecutar(new Peticion($metodo, $ruta, $campos, [], '127.0.0.1'));
    }

    /** Postula al aprendiz (como él mismo, así lo exige el trigger) y devuelve el id de la postulación. */
    private function postular(int $idAprendiz, int $idConvocatoria): int
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idAprendiz, '127.0.0.1');
        $bd->pdo()->prepare('INSERT INTO postulaciones (id_usuario, id_convocatoria) VALUES (?, ?)')->execute([$idAprendiz, $idConvocatoria]);

        return (int) $bd->pdo()->lastInsertId();
    }

    private function conSesion(int $id, bool $debeCambiar = false): Fabrica
    {
        $fabrica = $this->fabrica();
        $fabrica->sesion()->iniciarPara($id, 'Aprendiz', $debeCambiar, 'Luisa');

        return $fabrica;
    }

    public function test_del_aprendiz_trae_solo_sus_postulaciones_sin_el_motivo_de_rechazo(): void
    {
        $idConvocatoria = $this->crearConvocatoriaActiva();
        $yo = $this->crearAprendiz();
        $otro = $this->crearAprendiz();
        $this->postular($yo, $idConvocatoria);
        $this->postular($otro, $idConvocatoria);
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($yo, '127.0.0.1');

        $filas = (new RepositorioPostulaciones($bd))->delAprendiz($yo);

        $this->assertCount(1, $filas);
        $this->assertSame('Pendiente', $filas[0]['estado']);
        $this->assertArrayHasKey('beneficio', $filas[0]);
        $this->assertArrayNotHasKey('justificacion_rechazo', $filas[0]);
    }

    public function test_con_una_convocatoria_abierta_y_sin_postulacion_ofrece_postularme(): void
    {
        $idConvocatoria = $this->crearConvocatoriaActiva();

        $respuesta = $this->enviar($this->conSesion($this->crearAprendiz()), 'GET', '/mi-proceso');

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('href="/convocatorias/' . $idConvocatoria . '/postulacion"', $respuesta->cuerpo());
    }

    public function test_tras_postularse_la_tarjeta_pasa_a_en_revision(): void
    {
        $idConvocatoria = $this->crearConvocatoriaActiva();
        $id = $this->crearAprendiz();
        $this->postular($id, $idConvocatoria);

        $cuerpo = $this->enviar($this->conSesion($id), 'GET', '/mi-proceso')->cuerpo();

        $this->assertStringContainsString('Tu postulación está en revisión', $cuerpo);
        $this->assertStringNotContainsString('href="/convocatorias/' . $idConvocatoria . '/postulacion"', $cuerpo);
    }

    public function test_sin_sesion_manda_a_ingresar_y_un_administrador_recibe_403(): void
    {
        $this->assertSame('/ingresar', $this->enviar($this->fabrica(), 'GET', '/mi-proceso')->destino());

        $fabrica = $this->fabrica();
        $fabrica->sesion()->iniciarPara($this->crearAdmin(), 'Administrador', false, 'Admin');
        $this->assertSame(403, $this->enviar($fabrica, 'GET', '/mi-proceso')->codigo());
    }

    public function test_quien_debe_cambiar_su_contrasena_es_enviado_a_cambiarla(): void
    {
        $respuesta = $this->enviar($this->conSesion($this->crearAprendiz(['debe_cambiar_contrasena' => 1]), true), 'GET', '/mi-proceso');

        $this->assertSame('/contrasena/nueva', $respuesta->destino());
    }

    /** Pendiente de la Task 5: tras cambiar la clave en el primer ingreso, /mi-proceso ya se sirve. */
    public function test_despues_del_cambio_de_primer_ingreso_mi_proceso_se_sirve_sin_redireccion(): void
    {
        $id = $this->crearAprendiz(['debe_cambiar_contrasena' => 1]);
        $correo = (string) $this->baseDatosDeLaApp()->pdo()->query("SELECT correo FROM usuarios WHERE id_usuario = $id")->fetchColumn();
        $fabrica = $this->fabrica();
        $this->enviar($fabrica, 'POST', '/ingresar', ['correo' => $correo, 'clave' => self::CLAVE_PRUEBA, '_csrf' => Csrf::token()]);
        $this->enviar($fabrica, 'POST', '/contrasena/nueva', ['nueva' => 'Bienestar2026', 'confirmar' => 'Bienestar2026', '_csrf' => Csrf::token()]);

        $respuesta = $this->enviar($fabrica, 'GET', '/mi-proceso');

        $this->assertSame(200, $respuesta->codigo());
        $this->assertNull($respuesta->destino());
    }
}
