<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Validacion;

use BenefitRack\Validacion\ValidadorArchivo;
use PHPUnit\Framework\TestCase;

/** Reglas de cada documento: se subió, pesa ≤ 5 MB y es de verdad PDF, JPG o PNG (CA-7). */
final class ValidadorArchivoTest extends TestCase
{
    use ArchivosDePrueba;

    protected function tearDown(): void
    {
        $this->limpiar();
    }

    public function test_un_exe_renombrado_pdf_se_rechaza_por_su_tipo_real(): void
    {
        $archivo = $this->subido("MZ\x90\x00\x03\x00\x00\x00" . str_repeat("\x00", 200), 'cedula.pdf');

        $resultado = (new ValidadorArchivo())->validar($archivo, 'doc_identidad', 'Documento de identidad');

        $this->assertFalse($resultado->esValido());
        $this->assertSame('Solo se aceptan PDF, JPG o PNG', $resultado->errores()['doc_identidad']);
        $this->assertSame([], $resultado->datos());
    }

    public function test_un_archivo_de_5_242_881_bytes_pesa_demasiado(): void
    {
        $archivo = $this->subido($this->bytesPdf(5242881));

        $resultado = (new ValidadorArchivo())->validar($archivo, 'cert_eps', 'Certificado de EPS');

        $this->assertSame('El archivo pesa más de 5 MB', $resultado->errores()['cert_eps']);
    }

    public function test_un_archivo_de_exactamente_5_mb_se_acepta(): void
    {
        $archivo = $this->subido($this->bytesPdf(5242880));

        $this->assertTrue((new ValidadorArchivo())->validar($archivo, 'cert_eps', 'Certificado de EPS')->esValido());
    }

    public function test_lo_que_no_se_subio_dice_falta_el_documento(): void
    {
        $validador = new ValidadorArchivo();
        $sinArchivo = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];

        $this->assertSame('Falta el Certificado de EPS', $validador->validar(null, 'cert_eps', 'Certificado de EPS')->errores()['cert_eps']);
        $this->assertSame('Falta el Certificado de EPS', $validador->validar($sinArchivo, 'cert_eps', 'Certificado de EPS')->errores()['cert_eps']);
    }

    public function test_si_php_ya_lo_rechazo_por_tamano_dice_que_pesa_mas_de_5_mb(): void
    {
        $archivo = ['name' => 'a.pdf', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0];

        $resultado = (new ValidadorArchivo())->validar($archivo, 'cert_eps', 'Certificado de EPS');

        $this->assertSame('El archivo pesa más de 5 MB', $resultado->errores()['cert_eps']);
    }

    public function test_un_archivo_que_no_llego_completo_pide_intentar_de_nuevo(): void
    {
        $archivo = ['name' => 'a.pdf', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0];

        $resultado = (new ValidadorArchivo())->validar($archivo, 'cert_eps', 'Certificado de EPS');

        $this->assertSame('No se pudo recibir el archivo. Inténtalo de nuevo', $resultado->errores()['cert_eps']);
    }

    public function test_pdf_jpg_y_png_se_aceptan_con_la_extension_del_tipo_real(): void
    {
        $validador = new ValidadorArchivo();

        // El nombre dice una cosa y el contenido otra: manda el contenido.
        $pdf = $validador->validar($this->subido($this->bytesPdf(), 'raro.png'), 'doc_identidad', 'Documento de identidad');
        $jpg = $validador->validar($this->subido($this->bytesJpg(), 'foto.pdf'), 'doc_identidad', 'Documento de identidad');
        $png = $validador->validar($this->subido($this->bytesPng(), 'foto.jpg'), 'doc_identidad', 'Documento de identidad');

        $this->assertSame('pdf', $pdf->datos()['extension']);
        $this->assertSame('jpg', $jpg->datos()['extension']);
        $this->assertSame('png', $png->datos()['extension']);
    }

    public function test_los_datos_traen_ruta_bytes_y_el_nombre_original_sin_carpetas(): void
    {
        $archivo = $this->subido($this->bytesPdf(300), '../../etc/cedula.pdf');

        $datos = (new ValidadorArchivo())->validar($archivo, 'doc_identidad', 'Documento de identidad')->datos();

        $this->assertSame($archivo['tmp_name'], $datos['ruta']);
        $this->assertSame(300, $datos['bytes']);
        $this->assertSame('cedula.pdf', $datos['nombre_original']);
    }

    public function test_si_el_archivo_temporal_no_existe_no_se_acepta(): void
    {
        $archivo = ['name' => 'a.pdf', 'type' => '', 'tmp_name' => '/no/existe/brp', 'error' => UPLOAD_ERR_OK, 'size' => 10];

        $resultado = (new ValidadorArchivo())->validar($archivo, 'cert_eps', 'Certificado de EPS');

        $this->assertSame('No se pudo recibir el archivo. Inténtalo de nuevo', $resultado->errores()['cert_eps']);
    }
}
