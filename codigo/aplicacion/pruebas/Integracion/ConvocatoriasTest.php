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
use SIBEA\Repositorios\RepositorioConvocatorias;
use SIBEA\Servicios\ServicioConvocatorias;

/** M4: crear, editar, activar, desactivar y cerrar convocatorias; las abiertas para un aprendiz. */
final class ConvocatoriasTest extends BaseDePrueba
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    private function repositorio(int $idUsuario): RepositorioConvocatorias
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idUsuario, '127.0.0.1');

        return new RepositorioConvocatorias($bd);
    }

    /** Un servicio conectado como lo haría la Fabrica: la base sabe quién actúa. */
    private function servicio(int $idUsuario): ServicioConvocatorias
    {
        return new ServicioConvocatorias($this->repositorio($idUsuario));
    }

    /**
     * @param array<string, mixed> $cambios
     * @return array<string, mixed> Datos ya validados de una convocatoria nueva (Inactiva, abierta desde ayer por 30 días).
     */
    private function datosNuevos(array $cambios = []): array
    {
        return $cambios + [
            'nombre' => 'Convocatoria ' . bin2hex(random_bytes(4)),
            'id_beneficio' => 1,
            'fecha_inicio' => date('Y-m-d', strtotime('-1 day')),
            'fecha_cierre' => date('Y-m-d', strtotime('+30 days')),
            'cupos' => 10,
            'requisitos' => 'Matrícula activa.',
            'valor_pago' => '180000.00',
            'numero_pagos' => 4,
            'duracion_meses' => 4,
            'estado' => 'Inactiva',
        ];
    }

    /** @return array<string, mixed> */
    private function filaDe(int $id): array
    {
        $consulta = $this->baseDatosDeLaApp()->pdo()->query("SELECT * FROM convocatorias WHERE id_convocatoria = $id");
        $fila = $consulta === false ? false : $consulta->fetch();

        return is_array($fila) ? $fila : [];
    }

    private function idPorNombre(string $nombre): ?int
    {
        $consulta = $this->baseDatosDeLaApp()->pdo()->prepare('SELECT id_convocatoria FROM convocatorias WHERE nombre = ?');
        $consulta->execute([$nombre]);
        $id = $consulta->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** Postula al aprendiz (como administrador). Devuelve el id de la postulación; con $aprobar la deja Aprobada y ocupa un cupo. */
    private function postular(int $idAdmin, int $idAprendiz, int $idConvocatoria, bool $aprobar): int
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario($idAdmin, '127.0.0.1');
        $pdo = $bd->pdo();
        $pdo->prepare('INSERT INTO postulaciones (id_usuario, id_convocatoria) VALUES (?, ?)')->execute([$idAprendiz, $idConvocatoria]);
        $id = (int) $pdo->lastInsertId();
        if ($aprobar) {
            $pdo->exec("UPDATE postulaciones SET estado = 'Validada' WHERE id_postulacion = $id");
            $pdo->exec("UPDATE postulaciones SET estado = 'Aprobada' WHERE id_postulacion = $id");
        }

        return $id;
    }

    // ---------- Servicio y repositorio ----------

    public function test_crear_devuelve_el_id_y_guarda_los_datos(): void
    {
        $admin = $this->crearAdmin();
        $datos = $this->datosNuevos(['valor_pago' => '90000.00']);

        $id = $this->servicio($admin)->crear($datos);

        $fila = $this->filaDe($id);
        $this->assertSame($datos['nombre'], $fila['nombre']);
        $this->assertSame('Inactiva', $fila['estado']);
        $this->assertSame('90000.00', $fila['valor_pago']);
        $this->assertSame(4, (int) $fila['numero_pagos']);
    }

    public function test_crear_y_activar_dos_convocatorias_deja_las_dos_activas(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $primera = $servicio->crear($this->datosNuevos());
        $segunda = $servicio->crear($this->datosNuevos());

        $servicio->cambiarEstado($primera, 'activar', null);
        $servicio->cambiarEstado($segunda, 'activar', null);

        $this->assertSame('Activa', $this->filaDe($primera)['estado']);
        $this->assertSame('Activa', $this->filaDe($segunda)['estado']);
    }

    public function test_desactivar_deja_la_convocatoria_inactiva(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $id = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));

        $servicio->cambiarEstado($id, 'desactivar', null);

        $this->assertSame('Inactiva', $this->filaDe($id)['estado']);
    }

    public function test_cerrar_anticipado_sin_motivo_lanza_error_de_negocio(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $id = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));

        foreach ([null, '', '   '] as $motivo) {
            try {
                $servicio->cambiarEstado($id, 'cerrar-anticipado', $motivo);
                $this->fail('Debió exigir el motivo');
            } catch (ErrorDeNegocio $error) {
                $this->assertStringContainsString('motivo', $error->getMessage());
            }
        }
        $this->assertSame('Activa', $this->filaDe($id)['estado']);
    }

    public function test_cerrar_anticipado_guarda_motivo_y_la_base_anota_quien_y_cuando(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $id = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));

        $servicio->cambiarEstado($id, 'cerrar-anticipado', '  Se agotó el presupuesto.  ');

        $fila = $this->filaDe($id);
        $this->assertSame('Cierre anticipado', $fila['estado']);
        $this->assertSame('Se agotó el presupuesto.', $fila['motivo_cierre']);
        $this->assertSame($admin, (int) $fila['usuario_cierre']);
        $this->assertNotNull($fila['fecha_cierre_real']);
    }

    public function test_cerrar_normal_no_guarda_motivo(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $id = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));

        $servicio->cambiarEstado($id, 'cerrar', 'texto que se ignora');

        $fila = $this->filaDe($id);
        $this->assertSame('Cerrada', $fila['estado']);
        $this->assertNull($fila['motivo_cierre']);
        $this->assertSame($admin, (int) $fila['usuario_cierre']);
    }

    public function test_reabrir_una_cerrada_borra_el_motivo_y_los_datos_del_cierre(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $id = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));
        $servicio->cambiarEstado($id, 'cerrar-anticipado', 'Por error');

        $servicio->cambiarEstado($id, 'activar', null);

        $fila = $this->filaDe($id);
        $this->assertSame('Activa', $fila['estado']);
        $this->assertNull($fila['motivo_cierre']);
        $this->assertNull($fila['usuario_cierre']);
        $this->assertNull($fila['fecha_cierre_real']);
    }

    public function test_crear_con_el_beneficio_2_funciona(): void
    {
        $admin = $this->crearAdmin();

        $id = $this->servicio($admin)->crear($this->datosNuevos(['id_beneficio' => 2]));

        $this->assertSame(2, (int) $this->filaDe($id)['id_beneficio']);
    }

    public function test_no_se_puede_volver_a_cerrar_una_convocatoria_cerrada(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        foreach (['cerrar', 'cerrar-anticipado'] as $primera) {
            $id = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));
            $servicio->cambiarEstado($id, $primera, 'Motivo original');
            $antes = $this->filaDe($id);

            foreach (['cerrar', 'cerrar-anticipado'] as $segunda) {
                try {
                    $servicio->cambiarEstado($id, $segunda, 'Otro motivo');
                    $this->fail("Debió rechazar $primera -> $segunda");
                } catch (ErrorDeNegocio $error) {
                    $this->assertStringContainsString('cerrada', $error->getMessage());
                }
            }
            $this->assertSame($antes, $this->filaDe($id));
        }
    }

    public function test_una_accion_desconocida_lanza_error_de_negocio(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $id = $servicio->crear($this->datosNuevos());

        try {
            $servicio->cambiarEstado($id, 'borrar', null);
            $this->fail('Debió rechazar la acción');
        } catch (ErrorDeNegocio) {
            $this->assertSame('Inactiva', $this->filaDe($id)['estado']);
        }
    }

    public function test_cambiar_el_estado_de_una_convocatoria_que_no_existe_lanza_error_de_negocio(): void
    {
        $this->expectException(ErrorDeNegocio::class);

        $this->servicio($this->crearAdmin())->cambiarEstado(999999, 'activar', null);
    }

    public function test_un_aprendiz_que_intenta_crear_recibe_error_de_negocio_porque_la_base_lo_impide(): void
    {
        $aprendiz = $this->crearAprendiz();
        $datos = $this->datosNuevos();

        try {
            $this->servicio($aprendiz)->crear($datos);
            $this->fail('La base debió impedirlo');
        } catch (ErrorDeNegocio $error) {
            $this->assertStringContainsString('administrador', $error->getMessage());
        }
        $this->assertNull($this->idPorNombre($datos['nombre']));
    }

    public function test_un_aprendiz_no_puede_cambiar_el_estado(): void
    {
        $admin = $this->crearAdmin();
        $id = $this->servicio($admin)->crear($this->datosNuevos());

        $this->expectException(ErrorDeNegocio::class);
        $this->servicio($this->crearAprendiz())->cambiarEstado($id, 'activar', null);
    }

    public function test_actualizar_cambia_los_datos(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $id = $servicio->crear($this->datosNuevos());

        $servicio->actualizar($id, $this->datosNuevos(['nombre' => 'Nombre nuevo', 'cupos' => 40, 'valor_pago' => '50000.00']));

        $fila = $this->filaDe($id);
        $this->assertSame('Nombre nuevo', $fila['nombre']);
        $this->assertSame(40, (int) $fila['cupos']);
        $this->assertSame('50000.00', $fila['valor_pago']);
    }

    public function test_actualizar_no_cambia_el_estado(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $id = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));

        $servicio->actualizar($id, $this->datosNuevos(['estado' => 'Inactiva']));

        $this->assertSame('Activa', $this->filaDe($id)['estado']);
    }

    public function test_no_se_edita_una_convocatoria_cerrada(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $id = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));
        $servicio->cambiarEstado($id, 'cerrar', null);

        $this->expectException(ErrorDeNegocio::class);
        $servicio->actualizar($id, $this->datosNuevos());
    }

    public function test_bajar_los_cupos_por_debajo_de_las_aprobadas_muestra_el_texto_de_la_base(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $id = $servicio->crear($this->datosNuevos(['estado' => 'Activa', 'cupos' => 5]));
        $this->postular($admin, $this->crearAprendiz(), $id, true);
        $this->postular($admin, $this->crearAprendiz(), $id, true);

        try {
            $servicio->actualizar($id, $this->datosNuevos(['cupos' => 1]));
            $this->fail('La base debió impedirlo');
        } catch (ErrorDeNegocio $error) {
            $this->assertStringContainsString('aprobadas', $error->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function filaListada(ServicioConvocatorias $servicio, int $id): array
    {
        foreach ($servicio->listar('todas')['filas'] as $fila) {
            if ((int) $fila['id_convocatoria'] === $id) {
                return $fila;
            }
        }
        $this->fail("La convocatoria $id no salió en la lista");
    }

    public function test_listar_cuenta_solo_las_postulaciones_aprobadas_como_cupos_usados(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $id = $servicio->crear($this->datosNuevos(['estado' => 'Activa', 'cupos' => 5]));
        $this->postular($admin, $this->crearAprendiz(), $id, true);
        // Una postulación que solo está Pendiente no ocupa cupo.
        $this->postular($admin, $this->crearAprendiz(), $id, false);

        $fila = $this->filaListada($servicio, $id);

        $this->assertSame(1, (int) $fila['cupos_usados']);
        $this->assertSame(5, (int) $fila['cupos']);
        $this->assertSame('Alimentación', $fila['beneficio']);
    }

    public function test_listar_filtra_por_estado_y_cuenta_cada_grupo(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $activa = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));
        $inactiva = $servicio->crear($this->datosNuevos());
        $cerrada = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));
        $servicio->cambiarEstado($cerrada, 'cerrar', null);
        $anticipada = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));
        $servicio->cambiarEstado($anticipada, 'cerrar-anticipado', 'Motivo');

        $ids = static fn (string $filtro): array => array_map(
            static fn (array $fila): int => (int) $fila['id_convocatoria'],
            $servicio->listar($filtro)['filas']
        );

        $this->assertContains($activa, $ids('activas'));
        $this->assertNotContains($inactiva, $ids('activas'));
        $this->assertContains($inactiva, $ids('inactivas'));
        // «Cerradas» reúne las dos formas de cierre.
        $this->assertContains($cerrada, $ids('cerradas'));
        $this->assertContains($anticipada, $ids('cerradas'));
        $this->assertNotContains($activa, $ids('cerradas'));
        // Los contadores de las pestañas no dependen del filtro elegido.
        $contadores = $servicio->listar('inactivas')['contadores'];
        $this->assertSame(count($ids('todas')), $contadores['todas']);
        $this->assertSame(count($ids('cerradas')), $contadores['cerradas']);
        // Un filtro desconocido se trata como «todas».
        $this->assertCount(count($ids('todas')), $ids('cualquier-cosa'));
    }

    public function test_listar_pone_las_abiertas_primero(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $cerrada = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));
        $servicio->cambiarEstado($cerrada, 'cerrar', null);
        $activa = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));

        $ids = array_map(static fn (array $fila): int => (int) $fila['id_convocatoria'], $servicio->listar('todas')['filas']);

        $this->assertLessThan(array_search($cerrada, $ids, true), array_search($activa, $ids, true));
    }

    public function test_abiertas_para_devuelve_las_activas_dentro_de_fechas_con_la_postulacion_del_aprendiz(): void
    {
        $admin = $this->crearAdmin();
        $servicio = $this->servicio($admin);
        $abierta = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));
        $otraAbierta = $servicio->crear($this->datosNuevos(['estado' => 'Activa']));
        $inactiva = $servicio->crear($this->datosNuevos());
        $vencida = $servicio->crear($this->datosNuevos([
            'estado' => 'Activa', 'fecha_inicio' => date('Y-m-d', strtotime('-40 days')), 'fecha_cierre' => date('Y-m-d', strtotime('-10 days')),
        ]));
        $futura = $servicio->crear($this->datosNuevos([
            'estado' => 'Activa', 'fecha_inicio' => date('Y-m-d', strtotime('+10 days')), 'fecha_cierre' => date('Y-m-d', strtotime('+40 days')),
        ]));
        $aprendiz = $this->crearAprendiz();
        $idPostulacion = $this->postular($admin, $aprendiz, $abierta, false);

        $propias = [];
        foreach ($this->repositorio($aprendiz)->abiertasPara($aprendiz) as $fila) {
            $propias[(int) $fila['id_convocatoria']] = $fila['id_postulacion'];
        }

        $this->assertArrayHasKey($abierta, $propias);
        $this->assertArrayHasKey($otraAbierta, $propias);
        $this->assertSame($idPostulacion, (int) $propias[$abierta]);
        $this->assertNull($propias[$otraAbierta]);
        $this->assertArrayNotHasKey($inactiva, $propias);
        $this->assertArrayNotHasKey($vencida, $propias);
        $this->assertArrayNotHasKey($futura, $propias);
    }

    public function test_abiertas_para_no_mezcla_las_postulaciones_de_otro_aprendiz(): void
    {
        $admin = $this->crearAdmin();
        $abierta = $this->servicio($admin)->crear($this->datosNuevos(['estado' => 'Activa']));
        $conPostulacion = $this->crearAprendiz();
        $sinPostulacion = $this->crearAprendiz();
        $this->postular($admin, $conPostulacion, $abierta, false);

        foreach ($this->repositorio($sinPostulacion)->abiertasPara($sinPostulacion) as $fila) {
            if ((int) $fila['id_convocatoria'] === $abierta) {
                $this->assertNull($fila['id_postulacion']);
                $this->assertSame('Alimentación', $fila['beneficio']);

                return;
            }
        }
        $this->fail('La convocatoria abierta no salió');
    }

    // ---------- Pantallas (por el enrutador, como en el navegador) ----------

    private function fabrica(): Fabrica
    {
        return new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
    }

    /**
     * @param array<string, string> $campos   Datos del formulario (POST).
     * @param array<string, string> $consulta Datos de la URL (?estado=...).
     */
    private function enviar(Fabrica $fabrica, string $metodo, string $ruta, array $campos = [], array $consulta = []): Respuesta
    {
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 2) . '/rutas.php')($enrutador, $fabrica);
        $aplicacion = new Aplicacion($enrutador, $fabrica->vista(), $this->configuracionDePrueba(), $fabrica);

        return $aplicacion->ejecutar(new Peticion($metodo, $ruta, $campos, [], '127.0.0.1', [], $consulta));
    }

    /** Una fábrica con un administrador dentro; devuelve también su id para crear datos a su nombre. */
    private function fabricaDeAdmin(): Fabrica
    {
        $fabrica = $this->fabrica();
        $fabrica->sesion()->iniciarPara($this->crearAdmin(), 'Administrador', false, 'Admin Prueba');

        return $fabrica;
    }

    /** @return array<string, string> Un formulario correcto, como lo manda el navegador. */
    private function camposDelFormulario(string $nombre): array
    {
        return [
            'nombre' => $nombre,
            'id_beneficio' => '1',
            'fecha_inicio' => date('Y-m-d', strtotime('-1 day')),
            'fecha_cierre' => date('Y-m-d', strtotime('+30 days')),
            'cupos' => '25',
            'requisitos' => 'Matrícula activa.',
            'valor_pago' => '180.000',
            'numero_pagos' => '6',
            'duracion_meses' => '6',
            'estado' => 'Activa',
            '_csrf' => Csrf::token(),
        ];
    }

    public function test_la_lista_muestra_las_convocatorias_con_cupos_pagos_y_estado(): void
    {
        $fabrica = $this->fabricaDeAdmin();
        $nombre = 'Lista ' . bin2hex(random_bytes(3));
        $this->servicio($this->crearAdmin())->crear($this->datosNuevos(['nombre' => $nombre, 'estado' => 'Activa', 'cupos' => 25]));

        $respuesta = $this->enviar($fabrica, 'GET', '/admin/convocatorias');

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString($nombre, $respuesta->cuerpo());
        $this->assertStringContainsString('0 de 25', $respuesta->cuerpo());
        $this->assertStringContainsString('4 pagos de $ 180.000', $respuesta->cuerpo());
        $this->assertStringContainsString('br-etiqueta--ok', $respuesta->cuerpo());
        $this->assertStringContainsString(formato_fecha(date('Y-m-d', strtotime('+30 days'))), $respuesta->cuerpo());
    }

    public function test_la_lista_filtra_por_estado_con_la_url(): void
    {
        $fabrica = $this->fabricaDeAdmin();
        $servicio = $this->servicio($this->crearAdmin());
        $activa = 'Solo activa ' . bin2hex(random_bytes(3));
        $inactiva = 'Solo inactiva ' . bin2hex(random_bytes(3));
        $servicio->crear($this->datosNuevos(['nombre' => $activa, 'estado' => 'Activa']));
        $servicio->crear($this->datosNuevos(['nombre' => $inactiva]));

        $cuerpo = $this->enviar($fabrica, 'GET', '/admin/convocatorias', [], ['estado' => 'inactivas'])->cuerpo();

        $this->assertStringContainsString($inactiva, $cuerpo);
        $this->assertStringNotContainsString($activa, $cuerpo);
    }

    public function test_el_nombre_se_escapa_en_la_lista(): void
    {
        $fabrica = $this->fabricaDeAdmin();
        $this->servicio($this->crearAdmin())->crear($this->datosNuevos(['nombre' => '<script>alert(1)</script>']));

        $cuerpo = $this->enviar($fabrica, 'GET', '/admin/convocatorias')->cuerpo();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $cuerpo);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $cuerpo);
    }

    public function test_un_aprendiz_no_entra_a_convocatorias(): void
    {
        $fabrica = $this->fabrica();
        $fabrica->sesion()->iniciarPara($this->crearAprendiz(), 'Aprendiz', false, 'Ana');

        $this->assertSame(403, $this->enviar($fabrica, 'GET', '/admin/convocatorias')->codigo());
        $this->assertSame(403, $this->enviar($fabrica, 'GET', '/admin/convocatorias/nueva')->codigo());
        $this->assertSame(403, $this->enviar($fabrica, 'POST', '/admin/convocatorias/nueva', ['_csrf' => Csrf::token()])->codigo());
        $this->assertSame(403, $this->enviar($fabrica, 'POST', '/admin/convocatorias/1/estado', ['_csrf' => Csrf::token()])->codigo());
    }

    public function test_sin_sesion_manda_a_ingresar(): void
    {
        $this->assertSame('/ingresar', $this->enviar($this->fabrica(), 'GET', '/admin/convocatorias')->destino());
    }

    public function test_el_formulario_de_nueva_convocatoria_se_muestra_con_los_beneficios(): void
    {
        $respuesta = $this->enviar($this->fabricaDeAdmin(), 'GET', '/admin/convocatorias/nueva');

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('Nueva convocatoria', $respuesta->cuerpo());
        $this->assertStringContainsString('name="_csrf"', $respuesta->cuerpo());
        $this->assertStringContainsString('Alimentación', $respuesta->cuerpo());
    }

    public function test_crear_por_la_ruta_guarda_y_vuelve_a_la_lista(): void
    {
        $nombre = 'Por ruta ' . bin2hex(random_bytes(3));

        $respuesta = $this->enviar($this->fabricaDeAdmin(), 'POST', '/admin/convocatorias/nueva', $this->camposDelFormulario($nombre));

        $this->assertSame('/admin/convocatorias', $respuesta->destino());
        $id = $this->idPorNombre($nombre);
        $this->assertNotNull($id);
        $fila = $this->filaDe($id);
        $this->assertSame('Activa', $fila['estado']);
        $this->assertSame('180000.00', $fila['valor_pago']);
    }

    public function test_crear_con_errores_vuelve_al_formulario_sin_guardar(): void
    {
        $nombre = 'Con errores ' . bin2hex(random_bytes(3));
        // La fábrica va primero: al iniciar sesión se renueva el token CSRF y el formulario debe traer el vigente.
        $fabrica = $this->fabricaDeAdmin();
        $campos = ['cupos' => '0', 'fecha_cierre' => date('Y-m-d', strtotime('-1 day'))] + $this->camposDelFormulario($nombre);

        $respuesta = $this->enviar($fabrica, 'POST', '/admin/convocatorias/nueva', $campos);

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('Revisa 2 cosas', $respuesta->cuerpo());
        $this->assertStringContainsString($nombre, $respuesta->cuerpo());
        $this->assertNull($this->idPorNombre($nombre));
    }

    public function test_crear_sin_token_es_403(): void
    {
        $campos = $this->camposDelFormulario('Sin token');
        unset($campos['_csrf']);

        $this->assertSame(403, $this->enviar($this->fabricaDeAdmin(), 'POST', '/admin/convocatorias/nueva', $campos)->codigo());
        $this->assertNull($this->idPorNombre('Sin token'));
    }

    public function test_editar_por_la_ruta_muestra_los_datos_y_los_guarda(): void
    {
        $fabrica = $this->fabricaDeAdmin();
        $id = $this->servicio($this->crearAdmin())->crear($this->datosNuevos(['nombre' => 'Antes de editar']));

        $formulario = $this->enviar($fabrica, 'GET', "/admin/convocatorias/$id/editar");
        $this->assertSame(200, $formulario->codigo());
        $this->assertStringContainsString('Antes de editar', $formulario->cuerpo());

        $guardada = $this->enviar($fabrica, 'POST', "/admin/convocatorias/$id/editar", ['nombre' => 'Después de editar'] + $this->camposDelFormulario('x'));
        $this->assertSame('/admin/convocatorias', $guardada->destino());
        $this->assertSame('Después de editar', $this->filaDe($id)['nombre']);
    }

    public function test_editar_una_que_no_existe_es_404(): void
    {
        $this->assertSame(404, $this->enviar($this->fabricaDeAdmin(), 'GET', '/admin/convocatorias/999999/editar')->codigo());
    }

    public function test_cerrar_anticipado_por_la_ruta_exige_el_motivo(): void
    {
        $fabrica = $this->fabricaDeAdmin();
        $id = $this->servicio($this->crearAdmin())->crear($this->datosNuevos(['estado' => 'Activa']));

        $sin = $this->enviar($fabrica, 'POST', "/admin/convocatorias/$id/estado", ['accion' => 'cerrar-anticipado', 'motivo' => '', '_csrf' => Csrf::token()]);
        $this->assertSame('/admin/convocatorias', $sin->destino());
        $this->assertSame('Activa', $this->filaDe($id)['estado']);

        $con = $this->enviar($fabrica, 'POST', "/admin/convocatorias/$id/estado", ['accion' => 'cerrar-anticipado', 'motivo' => 'Sin presupuesto', '_csrf' => Csrf::token()]);
        $this->assertSame('/admin/convocatorias', $con->destino());
        $this->assertSame('Cierre anticipado', $this->filaDe($id)['estado']);
    }

    public function test_activar_por_la_ruta_pone_activa_la_convocatoria(): void
    {
        $fabrica = $this->fabricaDeAdmin();
        $id = $this->servicio($this->crearAdmin())->crear($this->datosNuevos());

        $this->enviar($fabrica, 'POST', "/admin/convocatorias/$id/estado", ['accion' => 'activar', '_csrf' => Csrf::token()]);

        $this->assertSame('Activa', $this->filaDe($id)['estado']);
    }

    public function test_la_lista_pide_el_motivo_en_el_cierre_anticipado(): void
    {
        $fabrica = $this->fabricaDeAdmin();
        $this->servicio($this->crearAdmin())->crear($this->datosNuevos(['nombre' => 'Todavía abierta ' . bin2hex(random_bytes(3)), 'estado' => 'Activa']));

        $cuerpo = $this->enviar($fabrica, 'GET', '/admin/convocatorias')->cuerpo();

        // La convocatoria cierra en 30 días: cerrarla hoy es un cierre anticipado.
        $this->assertStringContainsString('data-cambio="cerrar-anticipado"', $cuerpo);
        $this->assertStringContainsString('name="motivo"', $cuerpo);
    }

    public function test_sin_ninguna_convocatoria_la_lista_muestra_el_aviso_de_vacio(): void
    {
        $contadores = ['todas' => 0, 'activas' => 0, 'inactivas' => 0, 'cerradas' => 0];

        $cuerpo = $this->fabrica()->vista()->mostrar('convocatorias/lista', ['filas' => [], 'contadores' => $contadores, 'filtro' => 'todas'], 'admin');

        $this->assertStringContainsString('Aún no hay convocatorias.', $cuerpo);
        $this->assertStringContainsString('/admin/convocatorias/nueva', $cuerpo);
    }

    public function test_un_filtro_sin_resultados_lo_dice_sin_ofrecer_crear_la_primera(): void
    {
        $contadores = ['todas' => 3, 'activas' => 3, 'inactivas' => 0, 'cerradas' => 0];

        $cuerpo = $this->fabrica()->vista()->mostrar('convocatorias/lista', ['filas' => [], 'contadores' => $contadores, 'filtro' => 'inactivas'], 'admin');

        $this->assertStringContainsString('No hay convocatorias inactivas.', $cuerpo);
        $this->assertStringNotContainsString('Aún no hay convocatorias.', $cuerpo);
    }
}
