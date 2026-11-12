<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Herramientas;

use PHPUnit\Framework\TestCase;

/** CA-12: el revisor de comentarios rechaza comentarios vacíos o de relleno, no solo los ausentes. */
final class RevisarComentariosTest extends TestCase
{
    private string $raiz;

    protected function setUp(): void
    {
        $this->raiz = sys_get_temp_dir() . '/br_comentarios_' . bin2hex(random_bytes(4));
        mkdir($this->raiz . '/src', 0777, true);
        mkdir($this->raiz . '/vistas', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->raiz . '/{src,vistas}/*', GLOB_BRACE) ?: [] as $archivo) {
            unlink($archivo);
        }
        rmdir($this->raiz . '/src');
        rmdir($this->raiz . '/vistas');
        rmdir($this->raiz);
    }

    /** @return array{0: int, 1: string} Código de salida y salida de error del revisor sobre un archivo de src/. */
    private function revisar(string $codigoPhp): array
    {
        file_put_contents($this->raiz . '/src/Ejemplo.php', $codigoPhp);
        $proceso = proc_open([PHP_BINARY, dirname(__DIR__, 3) . '/herramientas/revisar_comentarios.php', $this->raiz], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias);
        $this->assertIsResource($proceso);
        stream_get_contents($tuberias[1]);
        $errores = (string) stream_get_contents($tuberias[2]);

        return [proc_close($proceso), $errores];
    }

    private function archivo(string $cabecera, string $antesDelMetodo): string
    {
        return "<?php\n\n$cabecera\n\ndeclare(strict_types=1);\n\nfinal class Ejemplo\n{\n$antesDelMetodo    public function hacer(): void\n    {\n    }\n}\n";
    }

    public function test_un_archivo_bien_comentado_pasa(): void
    {
        [$codigo] = $this->revisar($this->archivo("/**\n * EJEMPLO: hace algo en el paso 4.\n */", "    /**\n     * Hace algo.\n     */\n"));

        $this->assertSame(0, $codigo);
    }

    public function test_un_bloque_inicial_vacio_se_rechaza(): void
    {
        [$codigo, $errores] = $this->revisar($this->archivo("/**\n *\n */", "    /** Hace algo. */\n"));

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('bloque de comentario inicial', $errores);
    }

    public function test_un_bloque_inicial_que_no_es_docblock_se_rechaza(): void
    {
        [$codigo] = $this->revisar($this->archivo('/* nota */', "    /** Hace algo. */\n"));

        $this->assertSame(1, $codigo);
    }

    public function test_un_docblock_vacio_en_un_metodo_publico_se_rechaza(): void
    {
        [$codigo, $errores] = $this->revisar($this->archivo("/**\n * EJEMPLO: hace algo.\n */", "    /** */\n"));

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('hacer', $errores);
    }

    public function test_un_comentario_comun_no_cuenta_como_docblock(): void
    {
        [$codigo] = $this->revisar($this->archivo("/**\n * EJEMPLO: hace algo.\n */", "    /* x */\n"));

        $this->assertSame(1, $codigo);
    }

    public function test_un_atributo_entre_el_docblock_y_el_metodo_se_acepta(): void
    {
        [$codigo] = $this->revisar($this->archivo("/**\n * EJEMPLO: hace algo.\n */", "    /**\n     * Hace algo.\n     */\n    #[\Override]\n"));

        $this->assertSame(0, $codigo);
    }
}
