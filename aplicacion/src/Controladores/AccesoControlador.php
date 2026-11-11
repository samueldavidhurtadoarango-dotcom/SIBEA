<?php

/**
 * CONTROLADOR DE ACCESO (iniciar y cerrar sesión)
 *
 * Paso 4 del recorrido: atiende las rutas /, /ingresar y /salir. Recibe la petición,
 * valida lo que escribió la persona, le pregunta al ServicioAcceso y decide qué
 * responder: una página (la pantalla de ingreso en alguno de sus estados) o una
 * redirección. No tiene SQL ni reglas de negocio.
 */

declare(strict_types=1);

namespace BenefitRack\Controladores;

use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Nucleo\Sesion;
use BenefitRack\Nucleo\Vista;
use BenefitRack\Servicios\ResultadoAcceso;
use BenefitRack\Servicios\ServicioAcceso;

final class AccesoControlador
{
    /**
     * @param Sesion         $sesion   Quién está (o no) dentro.
     * @param Vista          $vista    Dibuja la pantalla de ingreso.
     * @param ServicioAcceso $servicio Reglas del inicio de sesión.
     */
    public function __construct(
        private readonly Sesion $sesion,
        private readonly Vista $vista,
        private readonly ServicioAcceso $servicio,
    ) {
    }

    /**
     * GET / : manda a cada persona a su pantalla de inicio (o a ingresar si no hay sesión).
     *
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta Una redirección.
     */
    public function inicio(Peticion $peticion): Respuesta
    {
        return Respuesta::redirigir($this->destinoSegunRol());
    }

    /**
     * GET /ingresar : muestra el formulario vacío. Quien ya tiene sesión va a su inicio.
     *
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta La pantalla de ingreso, o una redirección.
     */
    public function formulario(Peticion $peticion): Respuesta
    {
        if ($this->sesion->idUsuario() !== null) {
            return Respuesta::redirigir($this->destinoSegunRol());
        }

        return $this->pantalla('normal', '');
    }

    /**
     * POST /ingresar : valida, consulta al servicio y entra o vuelve a mostrar el formulario.
     * (El filtro de CSRF ya revisó el token antes de llegar aquí.)
     *
     * @param Peticion $peticion Trae los campos "correo" y "clave".
     * @return Respuesta Redirección si entró; si no, la pantalla de ingreso con su estado.
     */
    public function ingresar(Peticion $peticion): Respuesta
    {
        $correo = $peticion->campo('correo');
        $clave = $peticion->campo('clave');

        $errores = $this->validar($correo, $clave);
        if ($errores !== []) {
            return $this->pantalla('validacion', $correo, $errores);
        }

        $resultado = $this->servicio->ingresar($correo, $clave);
        if (!$resultado->exito) {
            return $this->pantalla($resultado->estadoVista, $correo);
        }

        return $this->abrirSesion($resultado);
    }

    /**
     * POST /salir : cierra la sesión y vuelve al formulario de ingreso.
     * (Los filtros de sesión y CSRF ya pasaron.)
     *
     * @param Peticion $peticion La petición en curso.
     * @return Respuesta Redirección a /ingresar.
     */
    public function salir(Peticion $peticion): Respuesta
    {
        $this->sesion->cerrar();

        return Respuesta::redirigir('/ingresar');
    }

    /**
     * Guarda a la persona en la sesión (esto renueva el id de sesión) y la manda a donde corresponde.
     */
    private function abrirSesion(ResultadoAcceso $resultado): Respuesta
    {
        $this->sesion->iniciarPara($resultado->idUsuario, $resultado->rol, $resultado->debeCambiar, $resultado->nombre);

        // Con contraseña temporal, lo primero es elegir una propia (la pantalla la hace la tarea 5).
        if ($resultado->debeCambiar) {
            return Respuesta::redirigir('/contrasena/nueva');
        }

        return Respuesta::redirigir($this->destinoSegunRol());
    }

    /**
     * Revisa lo mínimo antes de ir a la base: correo con forma de correo y clave no vacía.
     *
     * @return array<string, string> Mensaje por campo ('correo', 'clave'); vacío si todo está bien.
     */
    private function validar(string $correo, string $clave): array
    {
        $errores = [];
        if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            $errores['correo'] = 'Revisa tu correo. Debe verse así: nombre@soy.sena.edu.co';
        }
        if ($clave === '') {
            $errores['clave'] = 'Escribe tu contraseña para continuar.';
        }

        return $errores;
    }

    /** A dónde va cada persona: el administrador a las postulaciones, el aprendiz a su proceso, el visitante a ingresar. */
    private function destinoSegunRol(): string
    {
        return match ($this->sesion->rol()) {
            'Administrador' => '/admin/postulaciones',
            'Aprendiz' => '/mi-proceso',
            default => '/ingresar',
        };
    }

    /**
     * Dibuja la pantalla de ingreso.
     *
     * @param string               $estado  normal, validacion, datos-incorrectos, cuenta-bloqueada o cuenta-inactiva.
     * @param string               $correo  Lo que escribió (se conserva; la contraseña nunca se devuelve).
     * @param array<string, string> $errores Mensajes por campo (solo en el estado "validacion").
     */
    private function pantalla(string $estado, string $correo, array $errores = []): Respuesta
    {
        $html = $this->vista->mostrar('acceso/ingresar', [
            'estado' => $estado,
            'correo' => $correo,
            'errores' => $errores,
            'titulo' => 'Inicia sesión',
        ]);

        return Respuesta::html($html);
    }
}
