<?php

/**
 * CARGA INTERRUMPIDA
 *
 * Paso 4 del recorrido: se lanza cuando algo inesperado corta la confirmación de una carga a la mitad.
 * Las cuentas que ya se habían creado existen y su contraseña temporal SOLO vive en la entrega: por eso
 * la excepción lleva lo hecho hasta ese momento (resultado()) y quien la atrapa debe guardarlo antes
 * de avisar del error. El error original viaja como «previous».
 */

declare(strict_types=1);

namespace BenefitRack\Servicios;

use RuntimeException;
use Throwable;

final class CargaInterrumpida extends RuntimeException
{
    /**
     * @param array{creadas: int, fallidas: int, entrega: list<array{documento: string, nombre: string, correo: string, contrasena: string}>, errores: array<int, array<string, string>>} $resultado Lo que se alcanzó a hacer.
     * @param Throwable $causa El error que cortó la carga.
     */
    public function __construct(private readonly array $resultado, Throwable $causa)
    {
        parent::__construct('La carga se interrumpió por un error inesperado.', 0, $causa);
    }

    /**
     * @return array{creadas: int, fallidas: int, entrega: list<array{documento: string, nombre: string, correo: string, contrasena: string}>, errores: array<int, array<string, string>>}
     */
    public function resultado(): array
    {
        return $this->resultado;
    }
}
