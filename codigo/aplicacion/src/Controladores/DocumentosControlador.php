<?php

/**
 * CONTROLADOR DE DOCUMENTOS (ver o descargar un documento de una postulación)
 *
 * Paso 4 del recorrido: atiende GET /documentos/{id}. Los archivos viven fuera de public/, así que la única forma
 * de verlos es pasar por aquí, y aquí se comprueba (en el servidor, en cada petición) que quien pide sea el
 * aprendiz dueño de la postulación o un administrador. Cualquier otra persona recibe 403.
 * El archivo se entrega «en línea» (el navegador lo muestra: PDF e imágenes), con nosniff (el navegador no
 * adivina otro tipo) y sin caché (son documentos personales).
 * Los filtros de sesión y primer ingreso ya pasaron antes de llegar aquí (ver rutas.php).
 */

declare(strict_types=1);

namespace SIBEA\Controladores;

use InvalidArgumentException;
use SIBEA\Nucleo\Peticion;
use SIBEA\Nucleo\Respuesta;
use SIBEA\Nucleo\Sesion;
use SIBEA\Nucleo\Vista;
use SIBEA\Repositorios\RepositorioDocumentos;
use SIBEA\Servicios\AlmacenDocumentos;

final class DocumentosControlador
{
    /** Extensión => tipo de contenido (las únicas que existen: PDF, JPG y PNG). */
    private const TIPOS_MIME = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'];

    /**
     * @param Sesion               $sesion     Dice quién pide el documento.
     * @param Vista                $vista      Dibuja las páginas 403 y 404.
     * @param RepositorioDocumentos $documentos Busca el documento y el dueño de su postulación.
     * @param AlmacenDocumentos    $almacen    Dice dónde está el archivo en el servidor.
     */
    public function __construct(
        private readonly Sesion $sesion,
        private readonly Vista $vista,
        private readonly RepositorioDocumentos $documentos,
        private readonly AlmacenDocumentos $almacen,
    ) {
    }

    /**
     * GET /documentos/{id} : entrega el archivo si quien lo pide es el dueño o un administrador.
     *
     * @param Peticion $peticion Trae {id} en la URL.
     * @return Respuesta El archivo, 403 si no tiene permiso o 404 si no existe.
     */
    public function descargar(Peticion $peticion): Respuesta
    {
        $documento = $this->documentos->buscarConDueno((int) $peticion->parametro('id'));
        if ($documento === null) {
            return $this->error('errores/404', 404);
        }
        // Seguridad (control de acceso en el servidor): el dueño de la postulación o un administrador; nadie más.
        if (!$this->puedeVer((int) $documento['id_usuario'])) {
            return $this->error('errores/403', 403);
        }

        try {
            $ruta = $this->almacen->rutaAbsoluta((string) $documento['ruta_archivo']);
        } catch (InvalidArgumentException) {
            return $this->error('errores/404', 404); // una ruta guardada que no es de nuestra carpeta nunca se entrega
        }
        if (!is_file($ruta)) {
            return $this->error('errores/404', 404);
        }

        $mime = self::TIPOS_MIME[(string) $documento['extension']] ?? 'application/octet-stream';

        // Las imágenes se ven en el navegador; el PDF se descarga: el visor de PDF no funciona dentro de «sandbox».
        return Respuesta::archivo($ruta, (string) $documento['nombre_archivo'], $mime, $mime !== 'application/pdf')->sinCache();
    }

    /** Un administrador ve cualquiera; un aprendiz, solo los de su propia postulación. */
    private function puedeVer(int $idDueno): bool
    {
        return $this->sesion->rol() === 'Administrador'
            || ($this->sesion->rol() === 'Aprendiz' && $this->sesion->idUsuario() === $idDueno);
    }

    /** La página de error dentro del marco que corresponde al rol (sin sesión, el público). */
    private function error(string $plantilla, int $codigo): Respuesta
    {
        $marco = match ($this->sesion->rol()) {
            'Administrador' => 'admin',
            'Aprendiz' => 'aprendiz',
            default => 'publica',
        };

        return Respuesta::html($this->vista->mostrar($plantilla, [], $marco), $codigo);
    }
}
