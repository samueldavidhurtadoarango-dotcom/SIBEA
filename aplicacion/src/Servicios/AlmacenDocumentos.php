<?php

/**
 * ALMACÉN DE DOCUMENTOS (carpeta definitiva)
 *
 * Paso 4 del recorrido. Cuando el aprendiz pulsa «Enviar postulación», sus tres documentos pasan de la carpeta
 * temporal (almacenamiento/temporal/<sesión>/) a la definitiva (almacenamiento/documentos/) con un nombre aleatorio
 * de 32 caracteres. En la base solo se guarda la ruta RELATIVA («documentos/<32 hex>.<ext>»); la ruta completa en
 * el servidor se arma aquí, así ninguna ruta que venga de afuera (sesión o base) puede apuntar a otro sitio.
 * Por qué fuera de public/: el navegador nunca pide estos archivos por URL; solo salen por DocumentosControlador,
 * que antes comprueba que quien pide sea el dueño o un administrador.
 */

declare(strict_types=1);

namespace BenefitRack\Servicios;

use InvalidArgumentException;
use RuntimeException;

class AlmacenDocumentos
{
    /** Las únicas extensiones que existen (las mismas que deja pasar ValidadorArchivo). */
    private const EXTENSIONES = ['pdf', 'jpg', 'png'];

    /** Forma de una ruta relativa válida: la que fabrica guardar(). */
    private const PATRON_RUTA = '#^documentos/[0-9a-f]{32}\.(pdf|jpg|png)$#';

    /**
     * @param string $carpetaAlmacenamiento Carpeta que contiene «temporal» y «documentos» (aplicacion/almacenamiento).
     */
    public function __construct(private readonly string $carpetaAlmacenamiento)
    {
    }

    /**
     * Pasa un documento de la carpeta temporal a la definitiva, con nombre aleatorio.
     *
     * @param string $rutaTemporal Ruta del archivo en almacenamiento/temporal (la que dejó AlmacenTemporal).
     * @param string $extension    pdf, jpg o png (la que comprobó ValidadorArchivo con el tipo real).
     * @return string La ruta relativa para guardar en la base: «documentos/<32 hex>.<ext>».
     * @throws InvalidArgumentException Si la extensión no es una de las permitidas.
     * @throws RuntimeException         Si el archivo no está dentro de la carpeta temporal o no se pudo mover.
     */
    public function guardar(string $rutaTemporal, string $extension): string
    {
        if (!in_array($extension, self::EXTENSIONES, true)) {
            throw new InvalidArgumentException('Extensión de documento no permitida.');
        }
        $origen = $this->origenSeguro($rutaTemporal);

        $relativa = 'documentos/' . bin2hex(random_bytes(16)) . '.' . $extension;
        // El nombre lo inventa el servidor: nada de lo que escribió quien subió el archivo llega a la ruta.
        if (!rename($origen, $this->carpetaAlmacenamiento . '/' . $relativa)) {
            throw new RuntimeException('No se pudo guardar el documento.');
        }

        return $relativa;
    }

    /**
     * @param string $relativa La ruta que está guardada en la base («documentos/<32 hex>.<ext>»).
     * @return string La ruta completa del archivo en el servidor.
     * @throws InvalidArgumentException Si la ruta no tiene la forma que fabrica guardar() (por ejemplo, trae «..»).
     */
    public function rutaAbsoluta(string $relativa): string
    {
        // Seguridad: si la fila de la base estuviera alterada, una ruta como «../../x» no pasa este patrón.
        if (preg_match(self::PATRON_RUTA, $relativa) !== 1) {
            throw new InvalidArgumentException('Ruta de documento no válida.');
        }

        return rtrim($this->carpetaAlmacenamiento, '/\\') . '/' . $relativa;
    }

    /**
     * Borra un documento (lo usa el envío para dejar todo como estaba si algo falla). Si ya no existe o la ruta
     * no es válida, no hace nada: limpiar no debe causar otro error encima del que se está atendiendo.
     *
     * @param string $relativa Ruta relativa del documento.
     * @return void
     */
    public function borrar(string $relativa): void
    {
        if (preg_match(self::PATRON_RUTA, $relativa) !== 1) {
            return;
        }
        $absoluta = $this->rutaAbsoluta($relativa);
        if (is_file($absoluta)) {
            unlink($absoluta);
        }
    }

    /**
     * Seguridad (Ruling R16): la ruta viene de la sesión y no se confía en ella. Se resuelve con realpath (que
     * quita «..» y enlaces) y solo se acepta si queda DENTRO de almacenamiento/temporal; el «/» final evita que
     * una carpeta hermana como «temporal-otra» pase por tener el mismo comienzo.
     *
     * @return string La ruta real del archivo temporal.
     * @throws RuntimeException Si el archivo no existe o está fuera de la carpeta temporal.
     */
    private function origenSeguro(string $rutaTemporal): string
    {
        $base = realpath($this->carpetaAlmacenamiento . '/temporal');
        $real = realpath($rutaTemporal);
        if ($base === false || $real === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || !is_file($real)) {
            throw new RuntimeException('El documento temporal no está donde se esperaba.');
        }

        return $real;
    }
}
