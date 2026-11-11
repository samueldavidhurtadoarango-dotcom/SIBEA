<?php

/**
 * PETICIÓN
 *
 * Paso 1 del recorrido: index.php convierte lo que mandó el navegador
 * ($_SERVER, $_POST, $_FILES) en este objeto. Así el resto de la aplicación
 * no toca las variables globales de PHP y se puede probar con datos inventados.
 */

declare(strict_types=1);

namespace BenefitRack\Nucleo;

final class Peticion
{
    /**
     * @param string                              $metodo     GET o POST.
     * @param string                              $ruta       La URL sin dominio ni parámetros (/admin/usuarios).
     * @param array<string, mixed>                $campos     Datos del formulario ($_POST).
     * @param array<string, array<string, mixed>> $archivos   Archivos subidos ($_FILES).
     * @param string                              $ip         IP de quien pide.
     * @param array<string, string>               $parametros Valores de {id} en la URL (los llena el enrutador).
     * @param array<string, mixed>                $consulta   Datos de la URL después del "?" ($_GET), por ejemplo ?q=ana.
     */
    public function __construct(
        private readonly string $metodo,
        private readonly string $ruta,
        private readonly array $campos,
        private readonly array $archivos,
        private readonly string $ip,
        private readonly array $parametros = [],
        private readonly array $consulta = [],
    ) {
    }

    /**
     * Arma la petición a partir de las variables globales de PHP.
     *
     * @return Peticion La petición actual.
     */
    public static function desdeGlobales(): Peticion
    {
        $ruta = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        // "/ingresar/" y "/ingresar" son la misma pantalla.
        $ruta = $ruta === '/' ? '/' : rtrim($ruta, '/');

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $ruta,
            $_POST,
            $_FILES,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            [],
            $_GET,
        );
    }

    /**
     * Copia de esta petición con los parámetros de la URL ya resueltos.
     *
     * @param array<string, string> $parametros Por ejemplo ['id' => '15'].
     * @return Peticion La nueva petición (la original no cambia).
     */
    public function conParametros(array $parametros): Peticion
    {
        return new self($this->metodo, $this->ruta, $this->campos, $this->archivos, $this->ip, $parametros, $this->consulta);
    }

    /**
     * @return string El método HTTP en mayúsculas (GET o POST).
     */
    public function metodo(): string
    {
        return $this->metodo;
    }

    /**
     * @return string La ruta pedida, sin parámetros (?x=1).
     */
    public function ruta(): string
    {
        return $this->ruta;
    }

    /**
     * Lee un campo del formulario.
     *
     * @param string $nombre     Nombre del campo.
     * @param string $porDefecto Valor si el campo no vino o no es texto.
     * @return string El texto sin espacios al inicio ni al final.
     */
    public function campo(string $nombre, string $porDefecto = ''): string
    {
        $valor = $this->campos[$nombre] ?? $porDefecto;

        return is_string($valor) ? trim($valor) : $porDefecto;
    }

    /**
     * Lee un dato de la URL (lo que va después del "?").
     *
     * @param string $nombre     Nombre del dato (por ejemplo "q" en ?q=ana).
     * @param string $porDefecto Valor si no vino o no es texto.
     * @return string El texto sin espacios al inicio ni al final.
     */
    public function consulta(string $nombre, string $porDefecto = ''): string
    {
        $valor = $this->consulta[$nombre] ?? $porDefecto;

        return is_string($valor) ? trim($valor) : $porDefecto;
    }

    /**
     * Lee de la URL una lista de textos, como la mandan las casillas de un formulario (?columnas[]=a&columnas[]=b).
     *
     * @param string $nombre Nombre del dato (por ejemplo "columnas").
     * @return list<string> Los textos de la lista; lo que no sea texto se descarta, y si no es una lista, queda vacía.
     */
    public function consultaLista(string $nombre): array
    {
        $valor = $this->consulta[$nombre] ?? [];

        return is_array($valor) ? array_values(array_filter($valor, 'is_string')) : [];
    }

    /**
     * @param string $nombre Nombre del campo de archivo.
     * @return array<string, mixed>|null Los datos de $_FILES de ese campo, o null si no existe.
     */
    public function archivo(string $nombre): ?array
    {
        return $this->archivos[$nombre] ?? null;
    }

    /**
     * @param string $nombre Nombre del parámetro de la URL (por ejemplo "id").
     * @return string Su valor, o cadena vacía si no existe.
     */
    public function parametro(string $nombre): string
    {
        return $this->parametros[$nombre] ?? '';
    }

    /**
     * @return string La IP de quien hace la petición (se guarda en la auditoría).
     */
    public function ip(): string
    {
        return $this->ip;
    }
}
