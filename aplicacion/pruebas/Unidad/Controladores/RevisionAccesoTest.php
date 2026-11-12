<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Controladores;

use BenefitRack\Nucleo\Aplicacion;
use BenefitRack\Nucleo\Configuracion;
use BenefitRack\Nucleo\Enrutador;
use BenefitRack\Nucleo\Fabrica;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Repositorios\RepositorioUsuarios;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Quién puede entrar al detalle de una postulación y a sus decisiones. No necesita base de datos: los filtros
 * (con un repositorio de usuarios falso) responden ANTES de que la fábrica arme el controlador (y abra la conexión).
 */
final class RevisionAccesoTest extends TestCase
{
    private Fabrica $fabrica;
    private Aplicacion $aplicacion;

    protected function setUp(): void
    {
        $_SESSION = [];
        $configuracion = new Configuracion(['bd' => ['host' => '127.0.0.1', 'puerto' => 1, 'nombre' => 'x', 'usuario' => 'x', 'clave' => 'x']]);
        // La sesión se revalida contra la base (M1): aquí un repositorio falso responde que la cuenta sigue activa con lo que dice la sesión.
        $usuarios = $this->createMock(RepositorioUsuarios::class);
        $usuarios->method('estadoParaSesion')->willReturnCallback(static fn (): array => [
            'estado' => 'Activo', 'rol' => $_SESSION['rol'], 'debe_cambiar_contrasena' => (int) $_SESSION['debe_cambiar_contrasena'],
        ]);
        $this->fabrica = new Fabrica($configuracion, dirname(__DIR__, 3) . '/vistas', '127.0.0.1', fn (): RepositorioUsuarios => $usuarios);
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 3) . '/rutas.php')($enrutador, $this->fabrica);
        $this->aplicacion = new Aplicacion($enrutador, $this->fabrica->vista(), $configuracion, $this->fabrica);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function rutasDeRevision(): array
    {
        return [
            'detalle' => ['GET', '/admin/postulaciones/15'],
            'documentos' => ['POST', '/admin/postulaciones/15/documentos'],
            'confirmar' => ['POST', '/admin/postulaciones/15/confirmar'],
            'aprobar' => ['POST', '/admin/postulaciones/15/aprobar'],
            'rechazar' => ['POST', '/admin/postulaciones/15/rechazar'],
        ];
    }

    #[DataProvider('rutasDeRevision')]
    public function test_un_aprendiz_recibe_403(string $metodo, string $ruta): void
    {
        $this->fabrica->sesion()->iniciarPara(5, 'Aprendiz', false, 'Luisa');

        $respuesta = $this->aplicacion->ejecutar(new Peticion($metodo, $ruta, [], [], '127.0.0.1'));

        $this->assertSame(403, $respuesta->codigo());
    }

    #[DataProvider('rutasDeRevision')]
    public function test_sin_sesion_se_va_al_inicio_de_sesion(string $metodo, string $ruta): void
    {
        $respuesta = $this->aplicacion->ejecutar(new Peticion($metodo, $ruta, [], [], '127.0.0.1'));

        $this->assertSame(302, $respuesta->codigo());
        $this->assertSame('/ingresar', $respuesta->destino());
    }

    #[DataProvider('rutasDeRevision')]
    public function test_un_admin_con_contrasena_temporal_es_enviado_a_cambiarla(string $metodo, string $ruta): void
    {
        $this->fabrica->sesion()->iniciarPara(1, 'Administrador', true, 'Admin');

        $respuesta = $this->aplicacion->ejecutar(new Peticion($metodo, $ruta, [], [], '127.0.0.1'));

        $this->assertSame(302, $respuesta->codigo());
        $this->assertSame('/contrasena/nueva', $respuesta->destino());
    }

    /** @return array<string, array{0: string}> */
    public static function rutasQueCambianDatos(): array
    {
        return [
            'documentos' => ['/admin/postulaciones/15/documentos'],
            'confirmar' => ['/admin/postulaciones/15/confirmar'],
            'aprobar' => ['/admin/postulaciones/15/aprobar'],
            'rechazar' => ['/admin/postulaciones/15/rechazar'],
        ];
    }

    #[DataProvider('rutasQueCambianDatos')]
    public function test_un_admin_sin_token_csrf_no_llega_al_controlador(string $ruta): void
    {
        $this->fabrica->sesion()->iniciarPara(1, 'Administrador', false, 'Admin');

        $respuesta = $this->aplicacion->ejecutar(new Peticion('POST', $ruta, [], [], '127.0.0.1'));

        $this->assertSame(403, $respuesta->codigo());
    }
}
