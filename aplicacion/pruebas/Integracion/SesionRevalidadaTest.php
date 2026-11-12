<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Integracion;

use BenefitRack\Nucleo\Aplicacion;
use BenefitRack\Nucleo\Csrf;
use BenefitRack\Nucleo\Enrutador;
use BenefitRack\Nucleo\Fabrica;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Repositorios\RepositorioUsuarios;

/** M1: la sesión se revalida contra la base en cada petición (necesita la base temporal del puerto 3398). */
final class SesionRevalidadaTest extends BaseDePrueba
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    private function fabricaDe(int $id, string $rol): Fabrica
    {
        $fabrica = new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
        $fabrica->sesion()->iniciarPara($id, $rol, false, 'Prueba');

        return $fabrica;
    }

    /** @param array<string, string> $campos */
    private function enviar(Fabrica $fabrica, string $metodo, string $ruta, array $campos = []): Respuesta
    {
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 2) . '/rutas.php')($enrutador, $fabrica);
        $aplicacion = new Aplicacion($enrutador, $fabrica->vista(), $this->configuracionDePrueba(), $fabrica);

        return $aplicacion->ejecutar(new Peticion($metodo, $ruta, $campos, [], '127.0.0.1'));
    }

    /** Desactiva la cuenta como lo haría un administrador (la base exige @app_id_usuario y la justificación). */
    private function desactivar(int $idAprendiz): void
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($this->crearAdmin(), '127.0.0.1');
        (new RepositorioUsuarios($bd))->cambiarEstado($idAprendiz, 'Inactivo', 'Prueba de sesión revalidada');
    }

    public function test_estado_para_sesion_trae_estado_rol_y_marca_de_primer_ingreso(): void
    {
        $id = $this->crearAprendiz(['debe_cambiar_contrasena' => 1]);
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($id, '127.0.0.1');
        $repositorio = new RepositorioUsuarios($bd);

        $estado = $repositorio->estadoParaSesion($id);

        $this->assertSame('Activo', $estado['estado'] ?? null);
        $this->assertSame('Aprendiz', $estado['rol'] ?? null);
        $this->assertSame(1, (int) ($estado['debe_cambiar_contrasena'] ?? -1));
        $this->assertNull($repositorio->estadoParaSesion(999999));
    }

    public function test_un_aprendiz_activo_sigue_entrando(): void
    {
        $id = $this->crearAprendiz();

        $respuesta = $this->enviar($this->fabricaDe($id, 'Aprendiz'), 'GET', '/mi-proceso');

        $this->assertSame(200, $respuesta->codigo());
    }

    public function test_al_desactivar_al_aprendiz_su_siguiente_peticion_va_a_ingresar(): void
    {
        $id = $this->crearAprendiz();
        $fabrica = $this->fabricaDe($id, 'Aprendiz');
        $this->assertSame(200, $this->enviar($fabrica, 'GET', '/mi-proceso')->codigo());

        $this->desactivar($id);
        $respuesta = $this->enviar($fabrica, 'GET', '/mi-proceso');

        $this->assertSame('/ingresar', $respuesta->destino());
        $this->assertNull($fabrica->sesion()->idUsuario());
        $this->assertSame('Tu cuenta no está activa. Habla con el administrador.', $fabrica->sesion()->tomarMensajes()[0]['texto']);
    }

    public function test_un_aprendiz_desactivado_no_puede_postularse(): void
    {
        $idConvocatoria = $this->crearConvocatoriaActiva();
        $id = $this->crearAprendiz();
        $fabrica = $this->fabricaDe($id, 'Aprendiz');
        $this->desactivar($id);

        $respuesta = $this->enviar($fabrica, 'POST', "/convocatorias/$idConvocatoria/postulacion/enviar", ['_csrf' => Csrf::token()]);

        $this->assertSame('/ingresar', $respuesta->destino());
    }

    public function test_si_la_base_dice_que_cambio_el_rol_la_sesion_se_cierra(): void
    {
        $id = $this->crearAprendiz();

        // La sesión cree que es administrador, pero en la base es aprendiz.
        $fabrica = $this->fabricaDe($id, 'Administrador');
        $respuesta = $this->enviar($fabrica, 'GET', '/admin/postulaciones');

        $this->assertSame('/ingresar', $respuesta->destino());
        $this->assertNull($fabrica->sesion()->idUsuario());
    }
}
