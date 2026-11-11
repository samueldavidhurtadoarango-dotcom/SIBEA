<?php

/**
 * RESULTADO DE UNA VALIDACIÓN
 *
 * Paso 4 del recorrido: todo validador devuelve este objeto. Dice si lo que
 * escribió la persona es válido, trae un mensaje por cada campo con problema
 * y guarda los datos ya limpios para que el servicio los use.
 */

declare(strict_types=1);

namespace BenefitRack\Validacion;

final class ResultadoValidacion
{
    /** @var array<string, string> Mensaje de error por campo. */
    private array $errores = [];

    /**
     * @param array<string, mixed> $datos Los datos ya limpios (vacío si todavía no hay).
     */
    public function __construct(private readonly array $datos = [])
    {
    }

    /**
     * Anota un problema en un campo (si el campo ya tenía uno, queda el primero).
     *
     * @param string $campo   Nombre del campo del formulario.
     * @param string $mensaje Lo que se le muestra a la persona.
     * @return void
     */
    public function agregar(string $campo, string $mensaje): void
    {
        $this->errores[$campo] ??= $mensaje;
    }

    /**
     * @return bool true si no hay ningún error.
     */
    public function esValido(): bool
    {
        return $this->errores === [];
    }

    /**
     * @return array<string, string> Mensaje de error por campo.
     */
    public function errores(): array
    {
        return $this->errores;
    }

    /**
     * @return array<string, mixed> Los datos ya limpios.
     */
    public function datos(): array
    {
        return $this->datos;
    }
}
