<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Servicios;

use BenefitRack\Nucleo\ErrorDeNegocio;
use BenefitRack\Repositorios\RepositorioDocumentos;
use BenefitRack\Repositorios\RepositorioPostulaciones;
use BenefitRack\Repositorios\RepositorioUsuarios;
use BenefitRack\Servicios\AlmacenDocumentos;
use BenefitRack\Servicios\ServicioPostulacion;
use DateTimeImmutable;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Enviar la postulación: todo o nada. Los repositorios son dobles de prueba; el almacén de archivos es el real (en una carpeta temporal). */
final class ServicioPostulacionTest extends TestCase
{
    private string $base;
    private RepositorioPostulaciones&MockObject $postulaciones;
    private RepositorioDocumentos&MockObject $documentos;
    private RepositorioUsuarios&MockObject $usuarios;

    /** @var list<string> Lo que pasó, en orden (para comprobar la secuencia de la transacción). */
    private array $llamadas = [];

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/br-serv-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/temporal/s1', 0700, true);
        mkdir($this->base . '/documentos', 0700, true);

        $this->postulaciones = $this->createMock(RepositorioPostulaciones::class);
        $this->documentos = $this->createMock(RepositorioDocumentos::class);
        $this->usuarios = $this->createMock(RepositorioUsuarios::class);
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

    /** Arma el servicio con los dobles actuales (se llama después de configurarlos). */
    private function servicio(): ServicioPostulacion
    {
        return new ServicioPostulacion(
            $this->postulaciones,
            $this->documentos,
            $this->usuarios,
            new AlmacenDocumentos($this->base),
            new DateTimeImmutable('2026-11-20 10:30:00'),
        );
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        return [
            'telefono' => '3001112233', 'direccion' => 'Calle 1 # 2-3', 'fecha_nacimiento' => '2002-03-15', 'id_eps' => 2,
            'id_entidad_bancaria' => 1, 'id_tipo_cuenta' => 1, 'numero_cuenta' => '91234567890',
            'titular_cuenta' => 'Luisa Ríos', 'motivo' => 'Necesito el plan.', 'acepta_datos' => true,
        ];
    }

    /**
     * @param array<string, int> $bytes Tamaño por campo.
     * @return array<string, array{ruta: string, extension: string, nombre_original: string, bytes: int}>
     */
    private function archivos(array $bytes = []): array
    {
        $archivos = [];
        foreach (['doc_identidad' => 'pdf', 'cert_eps' => 'png', 'cert_bancario' => 'jpg'] as $campo => $extension) {
            $ruta = $this->base . '/temporal/s1/' . bin2hex(random_bytes(16)) . '.' . $extension;
            file_put_contents($ruta, 'contenido de ' . $campo);
            $archivos[$campo] = ['ruta' => $ruta, 'extension' => $extension, 'nombre_original' => "$campo.$extension", 'bytes' => $bytes[$campo] ?? 1000];
        }

        return $archivos;
    }

    /** @return list<string> */
    private function archivosGuardados(): array
    {
        return glob($this->base . '/documentos/*') ?: [];
    }

    /** Anota en $llamadas cada paso de la transacción y de los repositorios. */
    private function anotarLlamadas(): void
    {
        $anotar = function (string $nombre): callable {
            return function () use ($nombre): void {
                $this->llamadas[] = $nombre;
            };
        };
        $this->postulaciones->method('iniciarTransaccion')->willReturnCallback($anotar('begin'));
        $this->postulaciones->method('confirmarTransaccion')->willReturnCallback($anotar('commit'));
        $this->postulaciones->method('revertirTransaccion')->willReturnCallback($anotar('rollback'));
        $this->usuarios->method('actualizarContacto')->willReturnCallback($anotar('contacto'));
        $this->usuarios->method('guardarCuentaBancaria')->willReturnCallback($anotar('bancaria'));
        $this->postulaciones->method('crear')->willReturnCallback(function (): int {
            $this->llamadas[] = 'postulacion';

            return 77;
        });
        $this->documentos->method('crear')->willReturnCallback(function (): int {
            $this->llamadas[] = 'documento';

            return 1;
        });
    }

    public function test_envia_todo_en_una_transaccion_y_en_el_orden_del_enunciado(): void
    {
        $this->anotarLlamadas();

        $id = $this->servicio()->enviar(7, 5, $this->datos(), $this->archivos());

        $this->assertSame(77, $id);
        $this->assertSame(['begin', 'contacto', 'bancaria', 'postulacion', 'documento', 'documento', 'documento', 'commit'], $this->llamadas);
        $this->assertCount(3, $this->archivosGuardados());
    }

    public function test_actualiza_el_contacto_y_la_cuenta_con_lo_que_envio_el_aprendiz(): void
    {
        $this->usuarios->expects($this->once())->method('actualizarContacto')
            ->with(7, '3001112233', 'Calle 1 # 2-3', '2002-03-15', 2);
        $this->usuarios->expects($this->once())->method('guardarCuentaBancaria')
            ->with(7, 1, 1, '91234567890', 'Luisa Ríos');

        $this->servicio()->enviar(7, 5, $this->datos(), $this->archivos());
    }

    public function test_el_json_de_la_postulacion_trae_todo_lo_enviado_y_la_fecha(): void
    {
        $this->postulaciones->expects($this->once())->method('crear')
            ->with(7, 5, $this->callback(function (string $json): bool {
                $datos = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

                return $datos['numero_cuenta'] === '91234567890'
                    && $datos['motivo'] === 'Necesito el plan.'
                    && $datos['titular_cuenta'] === 'Luisa Ríos'
                    && $datos['fecha_envio'] === '2026-11-20 10:30:00'
                    && !isset($datos['archivos']);
            }))
            ->willReturn(1);

        $this->servicio()->enviar(7, 5, $this->datos(), $this->archivos());
    }

    public function test_guarda_tres_documentos_con_sus_tipos_1_4_y_2(): void
    {
        $guardados = [];
        $this->documentos->method('crear')->willReturnCallback(function (int $idPostulacion, int $tipo, string $nombre, string $ruta, string $extension) use (&$guardados): int {
            $guardados[$tipo] = [$nombre, $ruta, $extension];

            return 1;
        });

        $this->servicio()->enviar(7, 5, $this->datos(), $this->archivos());

        $this->assertSame([1, 4, 2], array_keys($guardados)); // identidad, EPS, bancario
        $this->assertSame('cert_eps.png', $guardados[4][0]);
        $this->assertMatchesRegularExpression('#^documentos/[0-9a-f]{32}\.png$#', $guardados[4][1]);
        $this->assertSame('png', $guardados[4][2]);
    }

    /** @return array<string, array{int, string}> */
    public static function tamanos(): array
    {
        return [
            'un byte sube al mínimo 0.01' => [1, '0.01'],
            'exactamente 1 MB' => [1048576, '1.00'],
            '1.43 MB se redondea hacia arriba' => [1500000, '1.44'],
            'exactamente 5 MB (el máximo)' => [5242880, '5.00'],
        ];
    }

    #[DataProvider('tamanos')]
    public function test_el_tamano_en_mb_se_redondea_hacia_arriba_con_minimo_0_01(int $bytes, string $esperado): void
    {
        $tamano = null;
        $this->documentos->method('crear')->willReturnCallback(function (int $i, int $t, string $n, string $r, string $e, string $mb) use (&$tamano): int {
            $tamano ??= $mb;

            return 1;
        });

        $this->servicio()->enviar(7, 5, $this->datos(), $this->archivos(['doc_identidad' => $bytes]));

        $this->assertSame($esperado, $tamano);
    }

    public function test_el_nombre_del_archivo_se_limpia_de_rutas_y_simbolos(): void
    {
        $archivos = $this->archivos();
        $archivos['doc_identidad']['nombre_original'] = '../../ced<ula>.pdf';
        $nombre = null;
        $this->documentos->method('crear')->willReturnCallback(function (int $i, int $t, string $n) use (&$nombre): int {
            $nombre ??= $n;

            return 1;
        });

        $this->servicio()->enviar(7, 5, $this->datos(), $archivos);

        $this->assertSame('ced_ula_.pdf', $nombre);
    }

    public function test_si_falla_el_tercer_documento_revierte_y_no_deja_ningun_archivo(): void
    {
        $this->postulaciones->method('crear')->willReturn(77);
        $this->postulaciones->expects($this->once())->method('revertirTransaccion');
        $this->postulaciones->expects($this->never())->method('confirmarTransaccion');
        $llamada = 0;
        $this->documentos->method('crear')->willReturnCallback(function () use (&$llamada): int {
            if (++$llamada === 3) {
                throw new PDOException('FK', 23000);
            }

            return 1;
        });

        try {
            $this->servicio()->enviar(7, 5, $this->datos(), $this->archivos());
            $this->fail('Debía lanzar ErrorDeNegocio.');
        } catch (ErrorDeNegocio) {
            $this->assertSame([], $this->archivosGuardados());
        }
    }

    public function test_una_postulacion_repetida_se_traduce_a_un_mensaje_claro_y_revierte(): void
    {
        $this->postulaciones->method('crear')->willThrowException($this->errorBd('23000', 1062));
        $this->postulaciones->expects($this->once())->method('revertirTransaccion');

        try {
            $this->servicio()->enviar(7, 5, $this->datos(), $this->archivos());
            $this->fail('Debía lanzar ErrorDeNegocio.');
        } catch (ErrorDeNegocio $error) {
            $this->assertSame('Ya te postulaste a esta convocatoria.', $error->getMessage());
            $this->assertSame([], $this->archivosGuardados());
        }
    }

    public function test_un_error_de_regla_de_la_base_muestra_el_mensaje_de_la_base(): void
    {
        $this->postulaciones->method('crear')->willThrowException($this->errorBd('45000', 1644, 'La convocatoria no está abierta'));

        $this->expectException(ErrorDeNegocio::class);
        $this->expectExceptionMessage('La convocatoria no está abierta');
        $this->servicio()->enviar(7, 5, $this->datos(), $this->archivos());
    }

    public function test_un_fallo_inesperado_al_mover_un_archivo_revierte_limpia_y_no_muestra_detalles_tecnicos(): void
    {
        $archivos = $this->archivos();
        $archivos['cert_bancario']['ruta'] = '/ruta/que/no/existe.jpg'; // el tercero no se puede mover
        $this->postulaciones->method('crear')->willReturn(77);
        $this->postulaciones->expects($this->once())->method('revertirTransaccion');
        $this->postulaciones->expects($this->never())->method('confirmarTransaccion');

        try {
            $this->servicio()->enviar(7, 5, $this->datos(), $archivos);
            $this->fail('Debía lanzar ErrorDeNegocio.');
        } catch (ErrorDeNegocio $error) {
            $this->assertSame('No pudimos guardar tu postulación. Intenta de nuevo.', $error->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $error->getPrevious());
            $this->assertSame([], $this->archivosGuardados()); // los dos ya movidos se borraron
        }
    }

    public function test_si_falla_el_commit_tambien_borra_los_archivos(): void
    {
        $this->postulaciones->method('crear')->willReturn(77);
        $this->postulaciones->method('confirmarTransaccion')->willThrowException(new PDOException('commit'));
        $this->postulaciones->expects($this->once())->method('revertirTransaccion');

        try {
            $this->servicio()->enviar(7, 5, $this->datos(), $this->archivos());
            $this->fail('Debía lanzar ErrorDeNegocio.');
        } catch (ErrorDeNegocio) {
            $this->assertSame([], $this->archivosGuardados());
        }
    }

    private function errorBd(string $estado, int $codigo, string $texto = 'error'): PDOException
    {
        $error = new PDOException($texto);
        $error->errorInfo = [$estado, $codigo, $texto];

        return $error;
    }
}
