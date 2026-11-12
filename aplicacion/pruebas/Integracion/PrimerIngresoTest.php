<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Integracion;

use BenefitRack\Nucleo\Aplicacion;
use BenefitRack\Nucleo\Csrf;
use BenefitRack\Nucleo\Enrutador;
use BenefitRack\Nucleo\Fabrica;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;
use BenefitRack\Repositorios\RepositorioUsuarios;
use BenefitRack\Servicios\ServicioContrasena;

/** CA-2: la contraseña temporal obliga a elegir una propia; la base baja la marca a 0. */
final class PrimerIngresoTest extends BaseDePrueba
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    private function marcaDe(int $id): int
    {
        return (int) $this->baseDatosDeLaApp()->pdo()
            ->query("SELECT debe_cambiar_contrasena FROM usuarios WHERE id_usuario = $id")->fetchColumn();
    }

    private function correoDe(int $id): string
    {
        return (string) $this->baseDatosDeLaApp()->pdo()
            ->query("SELECT correo FROM usuarios WHERE id_usuario = $id")->fetchColumn();
    }

    private function servicioPara(int $id): ServicioContrasena
    {
        $bd = $this->baseDatosDeLaApp();
        // La base solo baja la marca si quien actúa es el mismo usuario (como hace Fabrica::baseDatos()).
        $bd->fijarUsuario($id, '127.0.0.1');

        return new ServicioContrasena(new RepositorioUsuarios($bd));
    }

    private function fabrica(): Fabrica
    {
        return new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
    }

    /** @param array<string, string> $campos */
    private function enviar(Fabrica $fabrica, string $metodo, string $ruta, array $campos = []): Respuesta
    {
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 2) . '/rutas.php')($enrutador, $fabrica);
        $aplicacion = new Aplicacion($enrutador, $fabrica->vista(), $this->configuracionDePrueba(), $fabrica);

        return $aplicacion->ejecutar(new Peticion($metodo, $ruta, $campos, [], '127.0.0.1'));
    }

    /** Ingresa con la clave temporal y devuelve la fábrica con la sesión abierta. */
    private function ingresarConTemporal(int $id): Fabrica
    {
        $fabrica = $this->fabrica();
        $this->enviar($fabrica, 'POST', '/ingresar', ['correo' => $this->correoDe($id), 'clave' => self::CLAVE_PRUEBA, '_csrf' => Csrf::token()]);

        return $fabrica;
    }

    /** @return array<string, string> */
    private function cambio(string $nueva, string $confirmacion): array
    {
        return ['nueva' => $nueva, 'confirmar' => $confirmacion, '_csrf' => Csrf::token()];
    }

    public function test_un_aprendiz_nuevo_debe_cambiar_la_contrasena_y_despues_ya_no(): void
    {
        $id = $this->crearAprendiz(['debe_cambiar_contrasena' => 1]);
        $this->assertSame(1, $this->marcaDe($id));

        $resultado = $this->servicioPara($id)->cambiarPropia($id, 'Bienestar2026', 'Bienestar2026');

        $this->assertTrue($resultado->esValido());
        $this->assertSame(0, $this->marcaDe($id));
    }

    public function test_si_la_validacion_falla_no_se_toca_nada(): void
    {
        $id = $this->crearAprendiz(['debe_cambiar_contrasena' => 1]);

        $resultado = $this->servicioPara($id)->cambiarPropia($id, 'abc', 'abc');

        $this->assertFalse($resultado->esValido());
        $this->assertSame(1, $this->marcaDe($id));
    }

    public function test_se_guarda_un_hash_y_no_la_contrasena(): void
    {
        $id = $this->crearAprendiz(['debe_cambiar_contrasena' => 1]);
        $this->servicioPara($id)->cambiarPropia($id, 'Bienestar2026', 'Bienestar2026');

        $hash = (string) $this->baseDatosDeLaApp()->pdo()->query("SELECT password_hash FROM usuarios WHERE id_usuario = $id")->fetchColumn();

        $this->assertNotSame('Bienestar2026', $hash);
        $this->assertTrue(password_verify('Bienestar2026', $hash));
    }

    public function test_el_formulario_se_muestra_a_quien_debe_cambiar(): void
    {
        $id = $this->crearAprendiz(['debe_cambiar_contrasena' => 1]);
        $fabrica = $this->ingresarConTemporal($id);

        $respuesta = $this->enviar($fabrica, 'GET', '/contrasena/nueva');

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('Crea tu contraseña', $respuesta->cuerpo());
        $this->assertStringContainsString('name="_csrf"', $respuesta->cuerpo());
    }

    public function test_sin_sesion_el_formulario_manda_a_ingresar(): void
    {
        $respuesta = $this->enviar($this->fabrica(), 'GET', '/contrasena/nueva');

        $this->assertSame('/ingresar', $respuesta->destino());
    }

    public function test_un_cambio_correcto_muestra_exito_y_lleva_a_mi_proceso(): void
    {
        $id = $this->crearAprendiz(['debe_cambiar_contrasena' => 1]);
        $fabrica = $this->ingresarConTemporal($id);

        $respuesta = $this->enviar($fabrica, 'POST', '/contrasena/nueva', $this->cambio('Bienestar2026', 'Bienestar2026'));

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('Todo listo', $respuesta->cuerpo());
        $this->assertStringContainsString('href="/mi-proceso"', $respuesta->cuerpo());
        $this->assertFalse($fabrica->sesion()->debeCambiarContrasena());
        $this->assertSame(0, $this->marcaDe($id));
    }

    public function test_un_administrador_va_a_postulaciones_despues_del_cambio(): void
    {
        $id = $this->crearAdminConContrasenaTemporal();
        $fabrica = $this->fabrica();
        $fabrica->sesion()->iniciarPara($id, 'Administrador', true, 'Admin');

        $respuesta = $this->enviar($fabrica, 'POST', '/contrasena/nueva', $this->cambio('Bienestar2026', 'Bienestar2026'));

        $this->assertStringContainsString('href="/admin/postulaciones"', $respuesta->cuerpo());
    }

    public function test_un_cambio_con_errores_vuelve_a_mostrar_el_formulario_y_no_cambia_nada(): void
    {
        $id = $this->crearAprendiz(['debe_cambiar_contrasena' => 1]);
        $fabrica = $this->ingresarConTemporal($id);

        $respuesta = $this->enviar($fabrica, 'POST', '/contrasena/nueva', $this->cambio('luisa1234', 'luisa123'));

        $this->assertSame(200, $respuesta->codigo());
        $this->assertStringContainsString('Revisa 2 cosas para continuar', $respuesta->cuerpo());
        $this->assertStringContainsString('le falta una letra mayúscula', $respuesta->cuerpo());
        $this->assertStringNotContainsString('luisa1234', $respuesta->cuerpo()); // la contraseña nunca se devuelve
        $this->assertTrue($fabrica->sesion()->debeCambiarContrasena());
        $this->assertSame(1, $this->marcaDe($id));
    }

    public function test_el_cambio_sin_token_es_403(): void
    {
        $id = $this->crearAprendiz(['debe_cambiar_contrasena' => 1]);
        $fabrica = $this->ingresarConTemporal($id);

        $respuesta = $this->enviar($fabrica, 'POST', '/contrasena/nueva', ['nueva' => 'Bienestar2026', 'confirmar' => 'Bienestar2026']);

        $this->assertSame(403, $respuesta->codigo());
        $this->assertSame(1, $this->marcaDe($id));
    }

    private function hashDe(int $id): string
    {
        return (string) $this->baseDatosDeLaApp()->pdo()->query("SELECT password_hash FROM usuarios WHERE id_usuario = $id")->fetchColumn();
    }

    public function test_quien_no_debe_cambiar_es_redirigido_en_get_y_en_post_y_su_clave_no_cambia(): void
    {
        $id = $this->crearAprendiz(); // ya eligió su clave: debe_cambiar_contrasena = 0
        $antes = $this->hashDe($id);
        $fabrica = $this->fabrica();
        $fabrica->sesion()->iniciarPara($id, 'Aprendiz', false, 'Ana');

        $get = $this->enviar($fabrica, 'GET', '/contrasena/nueva');
        $post = $this->enviar($fabrica, 'POST', '/contrasena/nueva', $this->cambio('Bienestar2026', 'Bienestar2026'));

        $this->assertSame('/mi-proceso', $get->destino());
        $this->assertSame('/mi-proceso', $post->destino());
        $this->assertSame($antes, $this->hashDe($id));
    }

    public function test_despues_del_cambio_el_filtro_de_primer_ingreso_deja_pasar(): void
    {
        $id = $this->crearAprendiz(['debe_cambiar_contrasena' => 1]);
        $fabrica = $this->ingresarConTemporal($id);
        $peticion = new Peticion('GET', '/mi-proceso', [], [], '127.0.0.1');
        $this->assertSame('/contrasena/nueva', $fabrica->filtroPrimerIngreso()->aplicar($peticion)?->destino());

        $this->enviar($fabrica, 'POST', '/contrasena/nueva', $this->cambio('Bienestar2026', 'Bienestar2026'));

        $this->assertNull($fabrica->filtroPrimerIngreso()->aplicar($peticion));
    }

    public function test_la_contrasena_con_espacios_en_los_bordes_sirve_en_el_ingreso(): void
    {
        // Peticion::campo() recorta los espacios: se guarda y se compara igual, así que nunca queda una clave "imposible".
        $id = $this->crearAprendiz(['debe_cambiar_contrasena' => 1]);
        $fabrica = $this->ingresarConTemporal($id);
        $this->enviar($fabrica, 'POST', '/contrasena/nueva', $this->cambio(' Bienestar2026 ', ' Bienestar2026 '));

        $respuesta = $this->enviar($this->fabrica(), 'POST', '/ingresar', ['correo' => $this->correoDe($id), 'clave' => ' Bienestar2026 ', '_csrf' => Csrf::token()]);

        $this->assertSame('/mi-proceso', $respuesta->destino());
    }
}
