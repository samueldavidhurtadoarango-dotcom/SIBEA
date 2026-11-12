<?php

/**
 * CONFIGURACIÓN
 *
 * Paso 0 del recorrido de la petición: antes de hacer nada, index.php lee
 * el archivo de configuración (config/config.local.php, que NO se sube a git
 * porque tiene la clave de la base de datos) y lo guarda aquí.
 * Las claves se piden con puntos: "bd.host", "app.depuracion".
 */

declare(strict_types=1);

namespace BenefitRack\Nucleo;

use InvalidArgumentException;

final class Configuracion
{
    /** @param array<string, mixed> $valores Arreglo de dos niveles: grupo => clave => valor. */
    public function __construct(private readonly array $valores)
    {
    }

    /**
     * Crea la configuración leyendo un archivo PHP que devuelve un arreglo.
     *
     * @param string $ruta Ruta del archivo (por ejemplo config/config.local.php).
     * @return Configuracion La configuración ya cargada.
     */
    public static function desdeArchivo(string $ruta): Configuracion
    {
        if (!is_file($ruta)) {
            throw new InvalidArgumentException("No existe el archivo de configuración: $ruta");
        }
        $valores = require $ruta;
        if (!is_array($valores)) {
            throw new InvalidArgumentException("El archivo de configuración debe devolver un arreglo: $ruta");
        }

        return new self($valores);
    }

    /**
     * Devuelve un valor de la configuración.
     *
     * @param string $clave Clave con punto, por ejemplo "bd.puerto".
     * @return mixed El valor guardado. Si la clave no existe lanza una excepción
     *               (es mejor fallar fuerte que trabajar con un dato inventado).
     */
    public function obtener(string $clave): mixed
    {
        $valor = $this->valores;
        foreach (explode('.', $clave) as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                throw new InvalidArgumentException("Falta la clave de configuración: $clave");
            }
            $valor = $valor[$parte];
        }

        return $valor;
    }

    /**
     * Como obtener(), pero si la clave no existe devuelve un valor por defecto en vez de fallar.
     * Sirve para ajustes opcionales (zona horaria, tiempos de la sesión).
     *
     * @param string $clave      Clave con punto, por ejemplo "app.zona_horaria".
     * @param mixed  $porDefecto Lo que se devuelve si la clave no está en el archivo.
     * @return mixed El valor guardado o el de por defecto.
     */
    public function obtenerOpcional(string $clave, mixed $porDefecto): mixed
    {
        try {
            return $this->obtener($clave);
        } catch (InvalidArgumentException) {
            return $porDefecto;
        }
    }
}
