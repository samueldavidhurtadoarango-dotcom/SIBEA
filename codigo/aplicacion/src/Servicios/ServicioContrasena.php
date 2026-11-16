<?php

/**
 * SERVICIO DE CONTRASEÑA (cambio de la contraseña propia)
 *
 * Paso 4 del recorrido: el controlador le entrega lo que escribió la persona;
 * este servicio valida, convierte la contraseña en un hash y la guarda.
 * Al guardarla, la BASE DE DATOS pone debe_cambiar_contrasena = 0 (siempre que
 * la conexión tenga @app_id_usuario igual al id de la persona: la Fabrica se encarga).
 */

declare(strict_types=1);

namespace SIBEA\Servicios;

use SIBEA\Repositorios\RepositorioUsuarios;
use SIBEA\Validacion\ResultadoValidacion;
use SIBEA\Validacion\ValidadorContrasena;

final class ServicioContrasena
{
    private readonly ValidadorContrasena $validador;

    /**
     * @param RepositorioUsuarios      $usuarios  Donde se guarda el hash.
     * @param ValidadorContrasena|null $validador Las reglas de la contraseña (por defecto, las de la maqueta).
     */
    public function __construct(private readonly RepositorioUsuarios $usuarios, ?ValidadorContrasena $validador = null)
    {
        $this->validador = $validador ?? new ValidadorContrasena();
    }

    /**
     * Cambia la contraseña de la propia persona si cumple las reglas.
     *
     * @param int    $idUsuario    Quien está en la sesión (nunca un id que venga del formulario).
     * @param string $nueva        Contraseña nueva, leída con Peticion::campo().
     * @param string $confirmacion La misma, escrita otra vez.
     * @return ResultadoValidacion Válido si se guardó; si no, los errores por campo y no se cambia nada.
     */
    public function cambiarPropia(int $idUsuario, string $nueva, string $confirmacion): ResultadoValidacion
    {
        $resultado = $this->validador->validar($nueva, $confirmacion);
        if (!$resultado->esValido()) {
            return $resultado;
        }

        // Seguridad: jamás se guarda la contraseña, solo su hash (PASSWORD_DEFAULT = bcrypt con sal propia).
        $this->usuarios->cambiarContrasena($idUsuario, password_hash($nueva, PASSWORD_DEFAULT));

        return $resultado;
    }
}
