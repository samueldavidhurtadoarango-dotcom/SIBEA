<?php

/**
 * TRADUCTOR DE ERRORES DE LA BASE DE DATOS
 *
 * Paso 4 del recorrido: cuando una consulta falla, los repositorios o servicios
 * pasan la PDOException por aquí y obtienen un ErrorDeNegocio con un mensaje
 * que sí se puede mostrar. Así nunca se filtran nombres de tablas ni de
 * columnas a la pantalla.
 */

declare(strict_types=1);

namespace SIBEA\Nucleo;

use PDOException;

final class TraductorErroresBd
{
    /** Los triggers de la base lanzan SQLSTATE 45000 con un texto ya pensado para personas. */
    private const ESTADO_REGLA_DE_NEGOCIO = '45000';

    /** Código de MariaDB para "entrada duplicada" (clave única repetida). */
    private const CODIGO_DUPLICADO = 1062;

    /**
     * Convierte un error de la base en un error de negocio entendible.
     *
     * @param PDOException $error El error original de PDO.
     * @return ErrorDeNegocio 45000: el texto de la base; 1062: "Ese registro ya existe";
     *                        cualquier otro: un mensaje genérico (el detalle técnico se queda en el log).
     */
    public static function traducir(PDOException $error): ErrorDeNegocio
    {
        $estado = (string) ($error->errorInfo[0] ?? '');
        $codigo = (int) ($error->errorInfo[1] ?? 0);
        $texto = (string) ($error->errorInfo[2] ?? '');

        if ($estado === self::ESTADO_REGLA_DE_NEGOCIO && $texto !== '') {
            return new ErrorDeNegocio($texto, 0, $error);
        }
        if ($codigo === self::CODIGO_DUPLICADO) {
            return new ErrorDeNegocio('Ese registro ya existe', 0, $error);
        }

        // Seguridad: el mensaje técnico real queda en el log del servidor, no en la pantalla.
        error_log('[SIBEA] Error de base de datos: ' . $error->getMessage());

        return new ErrorDeNegocio('No pudimos guardar los cambios. Intenta de nuevo.', 0, $error);
    }
}
