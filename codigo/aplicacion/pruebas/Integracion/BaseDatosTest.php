<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Integracion;

use PDO;
use RuntimeException;

final class BaseDatosTest extends BaseDePrueba
{
    private function valorDe(PDO $pdo, string $variable): mixed
    {
        return $pdo->query("SELECT $variable")->fetchColumn();
    }

    public function test_conecta_con_el_usuario_de_la_aplicacion_y_no_con_root(): void
    {
        $pdo = $this->baseDatosDeLaApp()->pdo();

        $this->assertStringStartsWith('sibea_app@', (string) $pdo->query('SELECT CURRENT_USER()')->fetchColumn());
    }

    public function test_fijar_usuario_guarda_id_e_ip(): void
    {
        $bd = $this->baseDatosDeLaApp();

        $bd->fijarUsuario(5, '10.0.0.9');

        $this->assertSame('5', (string) $this->valorDe($bd->pdo(), '@app_id_usuario'));
        $this->assertSame('10.0.0.9', $this->valorDe($bd->pdo(), '@app_ip'));
    }

    /** Review Focus 1: al volver a anónimo (inicio de sesión) la variable debe quedar en NULL, no en el id anterior. */
    public function test_fijar_usuario_nulo_deja_la_variable_en_null_despues_de_un_id(): void
    {
        $bd = $this->baseDatosDeLaApp();
        $bd->fijarUsuario(5, '10.0.0.9');

        $bd->fijarUsuario(null, '10.0.0.9');

        $this->assertNull($this->valorDe($bd->pdo(), '@app_id_usuario'));
    }

    public function test_con_justificacion_fija_el_texto_durante_la_accion_y_lo_borra_despues(): void
    {
        $bd = $this->baseDatosDeLaApp();

        $dentro = $bd->conJustificacion('Cambio autorizado por Bienestar', fn (): mixed => $this->valorDe($bd->pdo(), '@app_justificacion'));

        $this->assertSame('Cambio autorizado por Bienestar', $dentro);
        $this->assertNull($this->valorDe($bd->pdo(), '@app_justificacion'));
    }

    public function test_con_justificacion_limpia_la_variable_aunque_la_accion_lance_excepcion(): void
    {
        $bd = $this->baseDatosDeLaApp();

        try {
            $bd->conJustificacion('Motivo', static function (): void {
                throw new RuntimeException('falló la acción');
            });
            $this->fail('La excepción debía salir de conJustificacion.');
        } catch (RuntimeException $e) {
            $this->assertSame('falló la acción', $e->getMessage());
        }

        $this->assertNull($this->valorDe($bd->pdo(), '@app_justificacion'));
    }

    public function test_las_consultas_son_preparadas_de_verdad_y_los_errores_lanzan_excepcion(): void
    {
        $pdo = $this->baseDatosDeLaApp()->pdo();

        $this->assertFalse((bool) $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function test_los_ayudantes_crean_usuarios_y_convocatoria(): void
    {
        $this->assertGreaterThan(0, $this->crearAdmin());
        $this->assertGreaterThan(0, $this->crearAprendiz(['nombre' => 'Luisa']));
        $this->assertGreaterThan(0, $this->crearConvocatoriaActiva(3));
    }
}
