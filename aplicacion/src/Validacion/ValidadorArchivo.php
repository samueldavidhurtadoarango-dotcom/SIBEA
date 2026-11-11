<?php

/**
 * VALIDADOR DE UN ARCHIVO SUBIDO
 *
 * Paso 4 del recorrido. Revisa UN documento de los que sube el aprendiz (cédula, certificado de EPS, certificado
 * bancario): que se haya subido, que pese 5 MB o menos y que sea de verdad un PDF, JPG o PNG.
 * Esta clase NO mueve ni guarda nada: solo mira el archivo temporal que dejó PHP. Guardarlo es trabajo de AlmacenTemporal.
 */

declare(strict_types=1);

namespace BenefitRack\Validacion;

final class ValidadorArchivo
{
    /** 5 MB exactos: 5 * 1024 * 1024. */
    public const TAMANO_MAXIMO = 5242880;

    /** Tipo real (lo que dice finfo al leer el contenido) => extensión con la que se guarda. */
    private const TIPOS_PERMITIDOS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * @param array<string, mixed>|null $archivoSubido Lo que PHP entrega en $_FILES para ese campo (o null si no vino).
     * @param string                    $campo         Nombre del campo del formulario (clave del error).
     * @param string                    $nombreLegible Cómo se llama el documento para la persona («Certificado de EPS»).
     * @return ResultadoValidacion Si es válido, datos(): ruta (el temporal de PHP), extension, nombre_original y bytes.
     */
    public function validar(?array $archivoSubido, string $campo, string $nombreLegible): ResultadoValidacion
    {
        $resultado = new ResultadoValidacion();
        $error = $this->errorDe($archivoSubido, $nombreLegible);
        if ($error !== null) {
            $resultado->agregar($campo, $error);

            return $resultado;
        }

        // Aquí ya se sabe que el archivo existe y es legible (errorDe lo comprobó).
        $ruta = (string) $archivoSubido['tmp_name'];

        return new ResultadoValidacion([
            'ruta' => $ruta,
            'extension' => self::TIPOS_PERMITIDOS[$this->tipoReal($ruta)],
            'nombre_original' => $this->nombreLimpio((string) ($archivoSubido['name'] ?? '')),
            'bytes' => (int) filesize($ruta),
        ]);
    }

    /**
     * @param array<string, mixed>|null $archivo
     * @return string|null El mensaje del primer problema, o null si el archivo está bien.
     */
    private function errorDe(?array $archivo, string $nombreLegible): ?string
    {
        $codigo = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($archivo === null || $codigo === UPLOAD_ERR_NO_FILE) {
            return "Falta el $nombreLegible";
        }
        // PHP ya cortó la subida por pasarse del límite del servidor o del formulario.
        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
            return 'El archivo pesa más de 5 MB';
        }
        $ruta = (string) ($archivo['tmp_name'] ?? '');
        if ($codigo !== UPLOAD_ERR_OK || $ruta === '' || !is_file($ruta)) {
            return 'No se pudo recibir el archivo. Inténtalo de nuevo';
        }
        // El tamaño se mide en el archivo real, no con el número que declara el navegador (puede mentir).
        if (filesize($ruta) > self::TAMANO_MAXIMO) {
            return 'El archivo pesa más de 5 MB';
        }

        return array_key_exists($this->tipoReal($ruta), self::TIPOS_PERMITIDOS) ? null : 'Solo se aceptan PDF, JPG o PNG';
    }

    /**
     * Seguridad: el tipo se averigua leyendo el CONTENIDO con finfo, no mirando la extensión ni el
     * "type" que manda el navegador: ambos los escribe quien sube el archivo y un .exe renombrado a .pdf los engaña.
     *
     * @return string El tipo MIME real (por ejemplo "application/pdf").
     */
    private function tipoReal(string $ruta): string
    {
        $tipo = (new \finfo(FILEINFO_MIME_TYPE))->file($ruta);

        return $tipo === false ? '' : $tipo;
    }

    /** Solo el nombre, sin carpetas: «../../x/cedula.pdf» queda «cedula.pdf». Se usa para mostrarlo, nunca para guardar. */
    private function nombreLimpio(string $nombre): string
    {
        $nombre = basename(str_replace('\\', '/', $nombre));

        return mb_substr($nombre, 0, 150);
    }
}
