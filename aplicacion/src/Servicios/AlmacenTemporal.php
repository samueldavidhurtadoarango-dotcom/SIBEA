<?php

/**
 * ALMACÉN TEMPORAL DE DOCUMENTOS
 *
 * Paso 4 del recorrido. Entre «Revisar y enviar» y «Enviar postulación» los documentos ya revisados esperan en
 * aplicacion/almacenamiento/temporal/<id de sesión>/ con un nombre aleatorio. Más tarde el envío los pasa a la
 * carpeta definitiva (o se descartan si la persona corrige o abandona).
 * Por qué fuera de public/: lo que está en public/ el navegador lo puede pedir por URL; los documentos de identidad
 * y bancarios solo deben salir de la aplicación después de comprobar quién los pide.
 * Por qué nombre aleatorio: el nombre que trae el archivo lo escribe quien lo sube (podría ser "../../x.php" o
 * adivinable); el aleatorio no depende de nadie y no se puede adivinar.
 */

declare(strict_types=1);

namespace BenefitRack\Servicios;

use InvalidArgumentException;
use RuntimeException;

class AlmacenTemporal
{
    /**
     * @param string $carpetaBase Carpeta que contiene una subcarpeta por sesión (aplicacion/almacenamiento/temporal).
     */
    public function __construct(private readonly string $carpetaBase)
    {
    }

    /**
     * Guarda un documento ya validado en la carpeta de la sesión con nombre aleatorio.
     *
     * @param array{ruta: string, extension: string, nombre_original: string, bytes: int} $archivo Lo que entregó ValidadorArchivo.
     * @param string                                                                       $idSesion Id de la sesión de PHP.
     * @return array{ruta: string, extension: string, nombre_original: string, bytes: int} Lo mismo, con la ruta nueva.
     * @throws InvalidArgumentException Si el id de sesión tiene caracteres raros.
     * @throws RuntimeException         Si no se pudo crear la carpeta o mover el archivo.
     */
    public function guardar(array $archivo, string $idSesion): array
    {
        $carpeta = $this->carpetaDe($idSesion);
        if (!is_dir($carpeta) && !mkdir($carpeta, 0700, true) && !is_dir($carpeta)) {
            throw new RuntimeException('No se pudo crear la carpeta temporal.');
        }

        // La extensión sale del tipo real que ya comprobó finfo (viene en $archivo), no del nombre original.
        $destino = $carpeta . '/' . bin2hex(random_bytes(16)) . '.' . $archivo['extension'];
        if (!$this->mover($archivo['ruta'], $destino)) {
            throw new RuntimeException('No se pudo guardar el documento.');
        }

        return ['ruta' => $destino] + $archivo;
    }

    /**
     * Borra archivos temporales. Solo toca los que están dentro de la carpeta base: una ruta de otro sitio se ignora,
     * así que aunque la sesión trajera una ruta manipulada no se puede borrar nada ajeno.
     *
     * @param list<array{ruta: string, extension?: string, nombre_original?: string, bytes?: int}> $archivos
     * @return void
     */
    public function descartar(array $archivos): void
    {
        $base = realpath($this->carpetaBase);
        foreach ($archivos as $archivo) {
            $real = realpath($archivo['ruta']);
            if ($base !== false && $real !== false && str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
                unlink($real);
            }
        }
    }

    /**
     * Mueve el archivo subido a su destino. Está aparte para poder cambiarlo en las pruebas: move_uploaded_file()
     * solo funciona con archivos que PHP recibió por una petición HTTP de verdad (lo comprueba para que nadie
     * pueda hacer pasar por «subido» un archivo del servidor); con PHP por consola siempre falla.
     *
     * @return bool true si se movió.
     */
    protected function mover(string $origen, string $destino): bool
    {
        return move_uploaded_file($origen, $destino);
    }

    /** La carpeta de una sesión. Seguridad: el id solo puede tener letras, números, coma y guion (nunca "/" ni ".."). */
    private function carpetaDe(string $idSesion): string
    {
        if (preg_match('/^[A-Za-z0-9,-]{1,128}$/', $idSesion) !== 1) {
            throw new InvalidArgumentException('Id de sesión no permitido.');
        }

        return rtrim($this->carpetaBase, '/\\') . '/' . $idSesion;
    }
}
