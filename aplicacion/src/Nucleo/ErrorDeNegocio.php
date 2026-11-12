<?php

/**
 * ERROR DE NEGOCIO
 *
 * Se lanza en el paso 4 (controladores y servicios) cuando algo no se puede
 * hacer por una regla del sistema ("Ese registro ya existe", "No hay cupos").
 * Su mensaje ya está escrito en lenguaje sencillo, así que el controlador lo
 * puede mostrar tal cual a la persona. Los errores técnicos (cosas que no
 * esperábamos) NO usan esta clase: esos llegan hasta la página 500.
 */

declare(strict_types=1);

namespace BenefitRack\Nucleo;

use RuntimeException;

final class ErrorDeNegocio extends RuntimeException
{
}
