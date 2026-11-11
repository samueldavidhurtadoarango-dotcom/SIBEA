<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Validacion;

/**
 * Ayuda de las pruebas: crea archivos reales en la carpeta temporal del sistema y los
 * describe como lo hace PHP en $_FILES. Los borra al terminar la prueba (limpiar()).
 */
trait ArchivosDePrueba
{
    /** @var list<string> Rutas creadas por esta prueba. */
    private array $archivosCreados = [];

    /** @param int $total Tamaño exacto en bytes (se rellena con espacios). */
    private function bytesPdf(int $total = 200): string
    {
        return str_pad("%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n", $total, ' ');
    }

    private function bytesPng(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true);
    }

    private function bytesJpg(): string
    {
        return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";
    }

    /**
     * @return array{name: string, type: string, tmp_name: string, error: int, size: int} Como lo entrega PHP.
     */
    private function subido(string $bytes, string $nombre = 'documento.pdf'): array
    {
        $ruta = (string) tempnam(sys_get_temp_dir(), 'brp');
        file_put_contents($ruta, $bytes);
        $this->archivosCreados[] = $ruta;

        // "type" lo manda el navegador y puede mentir: a propósito dice PDF siempre.
        return ['name' => $nombre, 'type' => 'application/pdf', 'tmp_name' => $ruta, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)];
    }

    private function limpiar(): void
    {
        foreach ($this->archivosCreados as $ruta) {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
        $this->archivosCreados = [];
    }
}
