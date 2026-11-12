<?php

/**
 * CONTROLADOR DE CONTRASEÑA (cambio obligatorio en el primer ingreso)
 *
 * Paso 4 del recorrido: atiende /contrasena/nueva (GET y POST). Quien entra con la
 * contraseña temporal que le dio Bienestar debe elegir una propia antes de usar
 * la aplicación. Esta ruta NO lleva el filtro de primer ingreso (sería un bucle).
 * No tiene SQL ni reglas: valida el ServicioContrasena y este controlador elige la pantalla.
 */

declare(strict_types=1);

namespace BenefitRack\Controladores;

use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Nucleo\Sesion;
use BenefitRack\Nucleo\Vista;
use BenefitRack\Servicios\ServicioContrasena;

final class ContrasenaControlador
{
    /**
     * @param Sesion             $sesion   Quién está dentro.
     * @param Vista              $vista    Dibuja las pantallas.
     * @param ServicioContrasena $servicio Reglas del cambio de contraseña.
     */
    public function __construct(
        private readonly Sesion $sesion,
        private readonly Vista $vista,
        private readonly ServicioContrasena $servicio,
    ) {
    }

    /**
     * GET /contrasena/nueva : muestra el formulario vacío.
     *
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta La pantalla «Crea tu contraseña».
     */
    public function formulario(Peticion $peticion): Respuesta
    {
        if (!$this->sesion->debeCambiarContrasena()) {
            return Respuesta::redirigir($this->sesion->rutaDeInicio());
        }

        return $this->pantallaFormulario([]);
    }

    /**
     * POST /contrasena/nueva : guarda la contraseña o vuelve a mostrar el formulario con los errores.
     * (Los filtros de sesión y CSRF ya pasaron.)
     *
     * @param Peticion $peticion Trae los campos "nueva" y "confirmar".
     * @return Respuesta La pantalla «Todo listo» o el formulario con errores.
     */
    public function guardar(Peticion $peticion): Respuesta
    {
        // Seguridad: esta pantalla es solo para quien tiene la contraseña temporal. Si no, cualquiera con una
        // sesión abierta (PC sin vigilar, cookie robada) podría fijar una clave sin conocer la actual.
        if (!$this->sesion->debeCambiarContrasena()) {
            return Respuesta::redirigir($this->sesion->rutaDeInicio());
        }

        // Se lee con campo() igual que el ingreso (recorta espacios): lo que se guarda es lo que el ingreso va a comparar.
        $nueva = $peticion->campo('nueva');
        $confirmacion = $peticion->campo('confirmar');

        // El id sale de la sesión, nunca del formulario: nadie puede cambiar la clave de otra persona.
        $resultado = $this->servicio->cambiarPropia((int) $this->sesion->idUsuario(), $nueva, $confirmacion);
        if (!$resultado->esValido()) {
            return $this->pantallaFormulario($resultado->errores());
        }

        $this->sesion->marcarContrasenaCambiada();

        return Respuesta::html($this->vista->mostrar('contrasena/exito', [
            'titulo' => 'Contraseña cambiada',
            'inicio' => $this->sesion->rutaDeInicio(),
        ]));
    }

    /**
     * @param array<string, string> $errores Mensajes por campo ('nueva', 'confirmar'); vacío si no hay.
     */
    private function pantallaFormulario(array $errores): Respuesta
    {
        return Respuesta::html($this->vista->mostrar('contrasena/nueva', [
            'titulo' => 'Crea tu contraseña',
            'errores' => $errores,
            'nombre' => $this->sesion->nombre(),
        ]));
    }
}
