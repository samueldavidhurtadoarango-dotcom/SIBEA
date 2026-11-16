<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Integracion;

use SIBEA\Nucleo\Aplicacion;
use SIBEA\Nucleo\Csrf;
use SIBEA\Nucleo\Enrutador;
use SIBEA\Nucleo\ErrorDeNegocio;
use SIBEA\Nucleo\Fabrica;
use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Respuesta;
use SIBEA\Repositorios\RepositorioDocumentos;
use SIBEA\Repositorios\RepositorioPostulaciones;
use SIBEA\Repositorios\RepositorioUsuarios;
use SIBEA\Servicios\AlmacenDocumentos;
use SIBEA\Servicios\ServicioPostulacion;

/** M5b: enviar la postulación contra la base real (transacción, triggers y archivos). */
final class PostulacionTest extends BaseDePrueba
{
    private string $base;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->base = sys_get_temp_dir() . '/br-int-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/temporal/s1', 0700, true);
        mkdir($this->base . '/documentos', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (['/temporal/s1/*', '/temporal/*', '/documentos/*', '/*'] as $patron) {
            foreach (glob($this->base . $patron) ?: [] as $ruta) {
                is_dir($ruta) ? rmdir($ruta) : unlink($ruta);
            }
        }
        rmdir($this->base);
    }

    /**
     * El servicio tal como lo arma la Fabrica, con la conexión de UN aprendiz (la base ve quién actúa).
     *
     * @param array<string, int> $tipos Campo => id del tipo de documento (se cambia para simular un fallo).
     */
    private function servicioDe(int $idAprendiz, array $tipos = ServicioPostulacion::TIPOS_DOCUMENTO): ServicioPostulacion
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idAprendiz, '127.0.0.1');

        return new ServicioPostulacion(
            new RepositorioPostulaciones($bd),
            new RepositorioDocumentos($bd),
            new RepositorioUsuarios($bd),
            new AlmacenDocumentos($this->base),
            null,
            $tipos,
        );
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        return [
            'telefono' => '3001112233', 'direccion' => 'Calle 1 # 2-3', 'fecha_nacimiento' => '2002-03-15', 'id_eps' => 1,
            'id_entidad_bancaria' => 1, 'id_tipo_cuenta' => 1, 'numero_cuenta' => '91234567890',
            'titular_cuenta' => 'Luisa Ríos', 'motivo' => 'Necesito el plan.', 'acepta_datos' => true,
        ];
    }

    /** @return array<string, array{ruta: string, extension: string, nombre_original: string, bytes: int}> */
    private function archivos(): array
    {
        $archivos = [];
        foreach (['doc_identidad' => 'pdf', 'cert_eps' => 'png', 'cert_bancario' => 'jpg'] as $campo => $extension) {
            $ruta = $this->base . '/temporal/s1/' . bin2hex(random_bytes(16)) . '.' . $extension;
            file_put_contents($ruta, "contenido $campo");
            $archivos[$campo] = ['ruta' => $ruta, 'extension' => $extension, 'nombre_original' => "$campo.$extension", 'bytes' => 15];
        }

        return $archivos;
    }

    /** @return list<string> */
    private function archivosGuardados(): array
    {
        return glob($this->base . '/documentos/*') ?: [];
    }

    /** Cuenta las filas de un aprendiz en «postulaciones» o «documentos» (la consulta la hace un administrador). */
    private function contar(string $tabla, int $idUsuario): int
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($this->crearAdmin(), '127.0.0.1');
        $sql = $tabla === 'postulaciones'
            ? 'SELECT COUNT(*) FROM postulaciones WHERE id_usuario = ?'
            : 'SELECT COUNT(*) FROM documentos_postulacion WHERE id_postulacion IN (SELECT id_postulacion FROM postulaciones WHERE id_usuario = ?)';
        $consulta = $bd->pdo()->prepare($sql);
        $consulta->execute([$idUsuario]);

        return (int) $consulta->fetchColumn();
    }

    public function test_enviar_deja_una_postulacion_pendiente_con_tres_documentos_y_json_valido(): void
    {
        $idAprendiz = $this->crearAprendiz();
        $idConvocatoria = $this->crearConvocatoriaActiva();

        $idPostulacion = $this->servicioDe($idAprendiz)->enviar($idAprendiz, $idConvocatoria, $this->datos(), $this->archivos());

        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idAprendiz, '127.0.0.1');
        $postulacion = $bd->pdo()->query("SELECT estado, datos_formulario FROM postulaciones WHERE id_postulacion = $idPostulacion")->fetch();
        $this->assertSame('Pendiente', $postulacion['estado']);
        $json = json_decode($postulacion['datos_formulario'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('91234567890', $json['numero_cuenta']);
        $this->assertArrayHasKey('fecha_envio', $json);

        $tipos = $bd->pdo()->query("SELECT id_tipo_documento FROM documentos_postulacion WHERE id_postulacion = $idPostulacion ORDER BY id_tipo_documento")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame([1, 2, 4], array_map('intval', $tipos ?: [])); // 4 = Certificado de EPS
        $this->assertCount(3, $this->archivosGuardados());
    }

    public function test_enviar_actualiza_el_contacto_la_eps_y_la_cuenta_bancaria_del_aprendiz(): void
    {
        $idAprendiz = $this->crearAprendiz();
        $this->servicioDe($idAprendiz)->enviar($idAprendiz, $this->crearConvocatoriaActiva(), $this->datos(), $this->archivos());

        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idAprendiz, '127.0.0.1');
        $perfil = (new RepositorioUsuarios($bd))->perfilAprendiz($idAprendiz);
        $this->assertSame('3001112233', $perfil['telefono'] ?? null);
        $this->assertSame('2002-03-15', $perfil['fecha_nacimiento'] ?? null);
        $this->assertSame(1, (int) ($perfil['id_eps'] ?? 0));

        $cuenta = $bd->pdo()->query("SELECT numero_cuenta, titular FROM informacion_bancaria WHERE id_usuario = $idAprendiz")->fetch();
        $this->assertSame(['numero_cuenta' => '91234567890', 'titular' => 'Luisa Ríos'], $cuenta);
    }

    public function test_una_segunda_postulacion_cambia_la_cuenta_ya_guardada_sin_duplicarla(): void
    {
        $idAprendiz = $this->crearAprendiz();
        $this->servicioDe($idAprendiz)->enviar($idAprendiz, $this->crearConvocatoriaActiva(), $this->datos(), $this->archivos());

        $otros = ['numero_cuenta' => '55555555', 'titular_cuenta' => 'Otro Titular'] + $this->datos();
        $this->servicioDe($idAprendiz)->enviar($idAprendiz, $this->crearConvocatoriaActiva(), $otros, $this->archivos());

        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idAprendiz, '127.0.0.1');
        $filas = $bd->pdo()->query("SELECT numero_cuenta FROM informacion_bancaria WHERE id_usuario = $idAprendiz")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['55555555'], $filas);
    }

    public function test_enviar_dos_veces_a_la_misma_convocatoria_falla_y_no_deja_archivos_sueltos(): void
    {
        $idAprendiz = $this->crearAprendiz();
        $idConvocatoria = $this->crearConvocatoriaActiva();
        $servicio = $this->servicioDe($idAprendiz);
        $servicio->enviar($idAprendiz, $idConvocatoria, $this->datos(), $this->archivos());
        $this->assertCount(3, $this->archivosGuardados());

        try {
            $servicio->enviar($idAprendiz, $idConvocatoria, $this->datos(), $this->archivos());
            $this->fail('La segunda postulación debía fallar.');
        } catch (ErrorDeNegocio $error) {
            $this->assertSame('Ya te postulaste a esta convocatoria.', $error->getMessage());
        }

        $this->assertCount(3, $this->archivosGuardados()); // solo los de la primera
        $this->assertSame(1, $this->contar('postulaciones', $idAprendiz));
    }

    public function test_si_falla_el_tercer_documento_no_queda_postulacion_ni_archivos(): void
    {
        $idAprendiz = $this->crearAprendiz();
        // El tipo 99999 no existe: el INSERT del tercer documento viola la clave foránea.
        $servicio = $this->servicioDe($idAprendiz, ['doc_identidad' => 1, 'cert_eps' => 4, 'cert_bancario' => 99999]);

        try {
            $servicio->enviar($idAprendiz, $this->crearConvocatoriaActiva(), $this->datos(), $this->archivos());
            $this->fail('Debía lanzar ErrorDeNegocio.');
        } catch (ErrorDeNegocio) {
            $this->assertSame(0, $this->contar('postulaciones', $idAprendiz));
            $this->assertSame(0, $this->contar('documentos', $idAprendiz));
            $this->assertSame([], $this->archivosGuardados());
        }

        // El contacto y la cuenta también se revirtieron (misma transacción).
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idAprendiz, '127.0.0.1');
        $this->assertNull((new RepositorioUsuarios($bd))->perfilAprendiz($idAprendiz)['telefono'] ?? null);
        $this->assertFalse($bd->pdo()->query("SELECT 1 FROM informacion_bancaria WHERE id_usuario = $idAprendiz")->fetchColumn());
    }

    public function test_la_base_rechaza_enviar_a_una_convocatoria_cerrada_y_no_deja_archivos(): void
    {
        $idAprendiz = $this->crearAprendiz();
        $idConvocatoria = $this->crearConvocatoriaActiva();
        $admin = $this->baseDatosDeLaApp();
        $admin->fijarUsuario($this->crearAdmin(), '127.0.0.1');
        $admin->pdo()->exec("UPDATE convocatorias SET estado = 'Inactiva' WHERE id_convocatoria = $idConvocatoria");

        try {
            $this->servicioDe($idAprendiz)->enviar($idAprendiz, $idConvocatoria, $this->datos(), $this->archivos());
            $this->fail('Debía lanzar ErrorDeNegocio.');
        } catch (ErrorDeNegocio $error) {
            $this->assertStringContainsString('no está abierta', $error->getMessage());
        }
        $this->assertSame([], $this->archivosGuardados());
    }

    public function test_un_aprendiz_no_puede_postular_a_otro(): void
    {
        $victima = $this->crearAprendiz();
        $atacante = $this->crearAprendiz();

        $this->expectException(ErrorDeNegocio::class);
        try {
            // La conexión es del atacante pero el envío pide la postulación de la víctima: los triggers lo impiden.
            $this->servicioDe($atacante)->enviar($victima, $this->crearConvocatoriaActiva(), $this->datos(), $this->archivos());
        } finally {
            $this->assertSame(0, $this->contar('postulaciones', $victima));
            $this->assertSame([], $this->archivosGuardados());
        }
    }

    // ---------- La ruta POST /convocatorias/{id}/postulacion/enviar ----------

    private function enviarPorRuta(int $idAprendiz, int $idConvocatoria, bool $conCsrf): Respuesta
    {
        $fabrica = new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
        $fabrica->sesion()->iniciarPara($idAprendiz, 'Aprendiz', false, 'Ana');
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 2) . '/rutas.php')($enrutador, $fabrica);
        $aplicacion = new Aplicacion($enrutador, $fabrica->vista(), $this->configuracionDePrueba(), $fabrica);
        $campos = $conCsrf ? ['_csrf' => Csrf::token()] : [];

        return $aplicacion->ejecutar(new Peticion('POST', "/convocatorias/$idConvocatoria/postulacion/enviar", $campos, [], '127.0.0.1'));
    }

    public function test_enviar_por_la_ruta_sin_revision_pendiente_vuelve_al_formulario(): void
    {
        $respuesta = $this->enviarPorRuta($this->crearAprendiz(), $id = $this->crearConvocatoriaActiva(), true);

        $this->assertSame("/convocatorias/$id/postulacion", $respuesta->destino());
    }

    public function test_enviar_por_la_ruta_sin_token_csrf_es_403(): void
    {
        $this->assertSame(403, $this->enviarPorRuta($this->crearAprendiz(), $this->crearConvocatoriaActiva(), false)->codigo());
    }
}
