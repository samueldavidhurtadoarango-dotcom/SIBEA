<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Integracion;

use BenefitRack\Nucleo\Aplicacion;
use BenefitRack\Nucleo\Enrutador;
use BenefitRack\Nucleo\ErrorDeNegocio;
use BenefitRack\Nucleo\Fabrica;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Repositorios\RepositorioDocumentos;
use BenefitRack\Repositorios\RepositorioPostulaciones;
use BenefitRack\Servicios\ServicioRevision;
use PDO;

/** M6b: revisar los documentos y decidir una postulación, con las reglas de la base de datos de verdad. */
final class RevisionTest extends BaseDePrueba
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    private function pdoComo(int $idAdmin): PDO
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idAdmin, '127.0.0.1');

        return $bd->pdo();
    }

    /** El servicio armado como lo arma la fábrica: dos repositorios con la conexión del administrador. */
    private function servicioComo(int $idAdmin): ServicioRevision
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idAdmin, '127.0.0.1');

        return new ServicioRevision(new RepositorioPostulaciones($bd), new RepositorioDocumentos($bd));
    }

    /**
     * Una postulación Pendiente con sus tres documentos Pendiente.
     *
     * @param array<string, mixed>|null $datosFormulario
     * @return array{id: int, documentos: list<int>}
     */
    private function postularConDocumentos(int $idAdmin, int $idConvocatoria, ?array $datosFormulario = null): array
    {
        $pdo = $this->pdoComo($idAdmin);
        $json = $datosFormulario === null ? null : json_encode($datosFormulario, JSON_UNESCAPED_UNICODE);
        $pdo->prepare('INSERT INTO postulaciones (id_usuario, id_convocatoria, datos_formulario) VALUES (?, ?, ?)')
            ->execute([$this->crearAprendiz(), $idConvocatoria, $json]);
        $id = (int) $pdo->lastInsertId();

        $documentos = [];
        foreach ([1, 2, 4] as $tipo) {
            $pdo->prepare('INSERT INTO documentos_postulacion (id_postulacion, id_tipo_documento, nombre_archivo, ruta_archivo, extension, tamano_mb) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$id, $tipo, "doc$tipo.pdf", 'documentos/' . bin2hex(random_bytes(16)) . '.pdf', 'pdf', '0.41']);
            $documentos[] = (int) $pdo->lastInsertId();
        }

        return ['id' => $id, 'documentos' => $documentos];
    }

    /**
     * @param list<int> $documentos
     * @return array<int, array{estado: string, observacion: string}>
     */
    private function todosValidos(array $documentos): array
    {
        return array_fill_keys($documentos, ['estado' => 'Validado', 'observacion' => '']);
    }

    private function estadoDe(int $idPostulacion): string
    {
        $consulta = $this->baseDatosDeLaApp()->pdo()->query("SELECT estado FROM postulaciones WHERE id_postulacion = $idPostulacion");

        return (string) ($consulta === false ? '' : $consulta->fetchColumn());
    }

    /** Deja una postulación Validada (documentos válidos + confirmar). */
    private function validada(int $idAdmin, int $idConvocatoria): int
    {
        $p = $this->postularConDocumentos($idAdmin, $idConvocatoria);
        $servicio = $this->servicioComo($idAdmin);
        $servicio->marcarDocumentos($p['id'], $this->todosValidos($p['documentos']));
        $servicio->confirmar($p['id']);

        return $p['id'];
    }

    public function test_el_recorrido_completo_confirma_aprueba_y_deja_historial_y_asignacion(): void
    {
        $admin = $this->crearAdmin();
        $p = $this->postularConDocumentos($admin, $this->crearConvocatoriaActiva(5));
        $servicio = $this->servicioComo($admin);

        $servicio->marcarDocumentos($p['id'], $this->todosValidos($p['documentos']));
        $servicio->confirmar($p['id']);
        $this->assertSame('Validada', $this->estadoDe($p['id']));
        $servicio->aprobar($p['id']);

        $detalle = $servicio->detalle($p['id']);
        $this->assertSame('Aprobada', $detalle['estado'] ?? null);
        $this->assertSame(1, (int) ($detalle['aprobadas'] ?? 0));
        $this->assertSame(['Pendiente', 'Validada', 'Aprobada'], array_map(static fn (array $h): string => (string) $h['estado_nuevo'], array_reverse($detalle['historial'] ?? [])));
        $this->assertNotNull($detalle['asignacion'] ?? null); // la base crea la asignación al aprobar
    }

    public function test_un_documento_no_valido_guarda_su_observacion_y_quien_lo_reviso(): void
    {
        $admin = $this->crearAdmin();
        $p = $this->postularConDocumentos($admin, $this->crearConvocatoriaActiva());
        $servicio = $this->servicioComo($admin);

        $servicio->marcarDocumentos($p['id'], [$p['documentos'][2] => ['estado' => 'Rechazado', 'observacion' => 'Foto borrosa']]);

        $documentos = $servicio->detalle($p['id'])['documentos'] ?? [];
        $este = array_values(array_filter($documentos, static fn (array $d): bool => (int) $d['id_documento'] === $p['documentos'][2]))[0];
        $this->assertSame('Rechazado', $este['estado_validacion']);
        $this->assertSame('Foto borrosa', $este['observaciones']);
    }

    public function test_volver_a_marcar_valido_un_documento_borra_la_observacion(): void
    {
        $admin = $this->crearAdmin();
        $p = $this->postularConDocumentos($admin, $this->crearConvocatoriaActiva());
        $servicio = $this->servicioComo($admin);
        $servicio->marcarDocumentos($p['id'], [$p['documentos'][0] => ['estado' => 'Rechazado', 'observacion' => 'Ilegible']]);

        $servicio->marcarDocumentos($p['id'], [$p['documentos'][0] => ['estado' => 'Validado', 'observacion' => '']]);

        $documentos = $servicio->detalle($p['id'])['documentos'] ?? [];
        $este = array_values(array_filter($documentos, static fn (array $d): bool => (int) $d['id_documento'] === $p['documentos'][0]))[0];
        $this->assertSame('Validado', $este['estado_validacion']);
        $this->assertNull($este['observaciones']);
    }

    public function test_confirmar_con_un_documento_pendiente_se_rechaza_y_la_postulacion_sigue_pendiente(): void
    {
        $admin = $this->crearAdmin();
        $p = $this->postularConDocumentos($admin, $this->crearConvocatoriaActiva());
        $servicio = $this->servicioComo($admin);
        $servicio->marcarDocumentos($p['id'], [$p['documentos'][0] => ['estado' => 'Validado', 'observacion' => '']]);

        try {
            $servicio->confirmar($p['id']);
            $this->fail('Debió lanzar ErrorDeNegocio');
        } catch (ErrorDeNegocio) {
            $this->assertSame('Pendiente', $this->estadoDe($p['id']));
        }
    }

    public function test_rechazar_sin_motivo_falla_y_con_motivo_queda_rechazada_con_la_justificacion(): void
    {
        $admin = $this->crearAdmin();
        $p = $this->postularConDocumentos($admin, $this->crearConvocatoriaActiva());
        $servicio = $this->servicioComo($admin);

        try {
            $servicio->rechazar($p['id'], '  ');
            $this->fail('Debió lanzar ErrorDeNegocio');
        } catch (ErrorDeNegocio) {
            $this->assertSame('Pendiente', $this->estadoDe($p['id']));
        }

        $servicio->rechazar($p['id'], 'El certificado de EPS no se alcanza a leer');

        $detalle = $servicio->detalle($p['id']);
        $this->assertSame('Rechazada', $detalle['estado'] ?? null);
        $this->assertSame('El certificado de EPS no se alcanza a leer', $detalle['justificacion_rechazo'] ?? null);
    }

    /** Review Focus 2: la última plaza solo la gana una aprobación; la otra ve «Ya no hay cupos». */
    public function test_con_un_solo_cupo_la_segunda_aprobacion_falla_con_ya_no_hay_cupos(): void
    {
        $admin = $this->crearAdmin();
        $otroAdmin = $this->crearAdmin();
        $convocatoria = $this->crearConvocatoriaActiva(1);
        $primera = $this->validada($admin, $convocatoria);
        $segunda = $this->validada($otroAdmin, $convocatoria);

        $this->servicioComo($admin)->aprobar($primera);

        try {
            $this->servicioComo($otroAdmin)->aprobar($segunda);
            $this->fail('La base debió rechazar la segunda aprobación');
        } catch (ErrorDeNegocio $error) {
            $this->assertSame('Ya no hay cupos en esta convocatoria', $error->getMessage());
        }
        $this->assertSame('Aprobada', $this->estadoDe($primera));
        $this->assertSame('Validada', $this->estadoDe($segunda));
    }

    public function test_el_detalle_resuelve_los_nombres_de_eps_banco_y_tipo_de_cuenta(): void
    {
        $admin = $this->crearAdmin();
        $pdo = $this->baseDatosDeLaApp()->pdo();
        $ids = [
            'id_eps' => (int) $pdo->query('SELECT MIN(id_eps) FROM eps')->fetchColumn(),
            'id_entidad_bancaria' => (int) $pdo->query('SELECT MIN(id_banco) FROM entidades_bancarias')->fetchColumn(),
            'id_tipo_cuenta' => (int) $pdo->query('SELECT MIN(id_tipo_cuenta) FROM tipos_cuenta')->fetchColumn(),
        ];
        $p = $this->postularConDocumentos($admin, $this->crearConvocatoriaActiva(), $ids + ['telefono' => '3001112233', 'direccion' => 'Calle 1 # 2-3', 'fecha_nacimiento' => '2008-02-29', 'numero_cuenta' => '123456']);

        $detalle = $this->servicioComo($admin)->detalle($p['id']);

        $this->assertNotEmpty($detalle['eps'] ?? null);
        $this->assertNotEmpty($detalle['banco'] ?? null);
        $this->assertNotEmpty($detalle['tipo_cuenta'] ?? null);
        $this->assertSame('3001112233', $detalle['telefono'] ?? null);
        $this->assertSame('Calle 1 # 2-3', $detalle['direccion'] ?? null);
        $this->assertCount(3, $detalle['documentos'] ?? []);
    }

    public function test_un_detalle_inexistente_es_null(): void
    {
        $this->assertNull($this->servicioComo($this->crearAdmin())->detalle(99999999));
    }

    // ---------- Pantallas (por el enrutador, como en el navegador) ----------

    private function pedirDetalle(Fabrica $fabrica, int $idPostulacion): int
    {
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 2) . '/rutas.php')($enrutador, $fabrica);
        $aplicacion = new Aplicacion($enrutador, $fabrica->vista(), $this->configuracionDePrueba(), $fabrica);

        return $aplicacion->ejecutar(new Peticion('GET', "/admin/postulaciones/$idPostulacion", [], [], '127.0.0.1'))->codigo();
    }

    public function test_el_admin_abre_el_detalle_y_el_aprendiz_recibe_403(): void
    {
        $admin = $this->crearAdmin();
        $p = $this->postularConDocumentos($admin, $this->crearConvocatoriaActiva());

        $fabrica = new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
        $fabrica->sesion()->iniciarPara($admin, 'Administrador', false, 'Admin Prueba');
        $this->assertSame(200, $this->pedirDetalle($fabrica, $p['id']));
        $this->assertSame(404, $this->pedirDetalle($fabrica, 99999999));

        // Otra persona en la misma fábrica: se cierra la sesión del admin y entra una aprendiz.
        $_SESSION = [];
        $fabrica->sesion()->iniciarPara($this->crearAprendiz(), 'Aprendiz', false, 'Luisa');
        $this->assertSame(403, $this->pedirDetalle($fabrica, $p['id']));
    }
}
