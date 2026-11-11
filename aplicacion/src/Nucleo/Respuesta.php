<?php

/**
 * RESPUESTA
 *
 * Último paso del recorrido: el controlador devuelve una Respuesta (una página,
 * una redirección o un archivo para descargar) y index.php la envía al navegador
 * con enviar(). Hay tres formas de crearla: html(), redirigir() y archivo().
 */

declare(strict_types=1);

namespace BenefitRack\Nucleo;

final class Respuesta
{
    /**
     * @param int                   $codigo    Código HTTP (200, 302, 404...).
     * @param string                $cuerpo    Contenido que se envía.
     * @param array<string, string> $cabeceras Cabeceras HTTP (nombre => valor).
     * @param string|null           $archivo   Ruta de un archivo a enviar en lugar del cuerpo.
     */
    private function __construct(
        private readonly int $codigo,
        private readonly string $cuerpo,
        private readonly array $cabeceras,
        private readonly ?string $archivo = null,
    ) {
    }

    /**
     * @param string $cuerpo El HTML de la página.
     * @param int    $codigo Código HTTP (200 por defecto).
     * @return Respuesta Una página web.
     */
    public static function html(string $cuerpo, int $codigo = 200): Respuesta
    {
        return new self($codigo, $cuerpo, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * @param string $url A dónde mandar al navegador.
     * @return Respuesta Una redirección (302).
     */
    public static function redirigir(string $url): Respuesta
    {
        return new self(302, '', ['Location' => $url]);
    }

    /**
     * @param string $ruta           Ruta del archivo en el servidor.
     * @param string $nombreDescarga Nombre con el que lo verá quien descarga.
     * @param string $tipoMime       Tipo del archivo (application/pdf, image/png...).
     * @param bool   $enLinea        true para que el navegador lo muestre (PDF e imágenes); false (por defecto) para que lo descargue.
     * @return Respuesta Una descarga o un archivo para ver en el navegador.
     */
    public static function archivo(string $ruta, string $nombreDescarga, string $tipoMime, bool $enLinea = false): Respuesta
    {
        // Seguridad: se reemplazan comillas, saltos de línea y símbolos raros
        // del nombre para que no puedan colarse otras cabeceras HTTP.
        $nombreSeguro = preg_replace('/[^A-Za-z0-9._ -]/', '_', $nombreDescarga);

        return new self(200, '', [
            'Content-Type' => $tipoMime,
            'Content-Length' => (string) filesize($ruta),
            'Content-Disposition' => ($enLinea ? 'inline' : 'attachment') . '; filename="' . $nombreSeguro . '"',
            'X-Content-Type-Options' => 'nosniff',
        ], $ruta);
    }

    /**
     * Una descarga cuyo contenido ya está en memoria (no hay archivo que pueda quedar en el servidor).
     *
     * @param string $contenido      Lo que se descarga.
     * @param string $nombreDescarga Nombre con el que lo verá quien descarga.
     * @param string $tipoMime       Tipo del archivo.
     * @return Respuesta Una descarga.
     */
    public static function descarga(string $contenido, string $nombreDescarga, string $tipoMime): Respuesta
    {
        // Mismo saneo del nombre que en archivo(): nada que pueda colar otras cabeceras.
        $nombreSeguro = preg_replace('/[^A-Za-z0-9._ -]/', '_', $nombreDescarga);

        return new self(200, $contenido, [
            'Content-Type' => $tipoMime,
            'Content-Length' => (string) strlen($contenido),
            'Content-Disposition' => 'attachment; filename="' . $nombreSeguro . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Copia de esta respuesta que el navegador no debe guardar (páginas y archivos con contraseñas).
     * Se pide de forma explícita para no depender de la configuración de PHP.
     *
     * @return Respuesta La misma respuesta con Cache-Control: no-store y Pragma: no-cache.
     */
    public function sinCache(): Respuesta
    {
        return new self($this->codigo, $this->cuerpo, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache'] + $this->cabeceras, $this->archivo);
    }

    /**
     * @param string $nombre Nombre de la cabecera HTTP.
     * @return string|null Su valor, o null si la respuesta no la lleva.
     */
    public function cabecera(string $nombre): ?string
    {
        return $this->cabeceras[$nombre] ?? null;
    }

    /**
     * @return int El código HTTP.
     */
    public function codigo(): int
    {
        return $this->codigo;
    }

    /**
     * @return string El cuerpo (vacío en redirecciones y descargas).
     */
    public function cuerpo(): string
    {
        return $this->cuerpo;
    }

    /**
     * @return string|null El destino si es una redirección; si no, null.
     */
    public function destino(): ?string
    {
        return $this->cabeceras['Location'] ?? null;
    }

    /**
     * Manda la respuesta al navegador: código, cabeceras y contenido.
     *
     * @return void
     */
    public function enviar(): void
    {
        http_response_code($this->codigo);
        foreach ($this->cabeceras as $nombre => $valor) {
            header("$nombre: $valor");
        }
        if ($this->archivo !== null) {
            readfile($this->archivo);

            return;
        }
        echo $this->cuerpo;
    }
}
