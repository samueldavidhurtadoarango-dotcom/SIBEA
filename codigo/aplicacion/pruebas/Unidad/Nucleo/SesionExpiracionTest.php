<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Nucleo;

use PHPUnit\Framework\TestCase;
use SIBEA\Nucleo\Sesion;

/** B1: la sesión caduca por inactividad y por duración máxima (con un reloj que la prueba controla). */
final class SesionExpiracionTest extends TestCase
{
    private const MIN = 60;
    private const HORA = 3600;

    private int $ahora = 1_000_000;
    private Sesion $sesion;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->sesion = new Sesion(fn (): int => $this->ahora);
        $this->sesion->iniciarPara(7, 'Aprendiz', false, 'Luisa');
    }

    public function test_recien_ingresada_no_ha_expirado(): void
    {
        $this->assertFalse($this->sesion->haExpirado(30 * self::MIN, 8 * self::HORA));
    }

    public function test_pasada_la_inactividad_expira(): void
    {
        $this->ahora += 30 * self::MIN + 1;

        $this->assertTrue($this->sesion->haExpirado(30 * self::MIN, 8 * self::HORA));
    }

    public function test_justo_dentro_del_limite_de_inactividad_no_expira(): void
    {
        $this->ahora += 30 * self::MIN;

        $this->assertFalse($this->sesion->haExpirado(30 * self::MIN, 8 * self::HORA));
    }

    public function test_registrar_actividad_reinicia_el_contador_de_inactividad(): void
    {
        $this->ahora += 20 * self::MIN;
        $this->sesion->registrarActividad();
        $this->ahora += 20 * self::MIN;

        $this->assertFalse($this->sesion->haExpirado(30 * self::MIN, 8 * self::HORA));
    }

    public function test_aunque_haya_actividad_la_duracion_maxima_la_vence(): void
    {
        // Una petición cada 10 minutos durante 8 horas: nunca hay inactividad, pero la sesión no puede vivir para siempre.
        for ($i = 0; $i < 48; $i++) {
            $this->ahora += 10 * self::MIN;
            $this->sesion->registrarActividad();
        }
        $this->assertFalse($this->sesion->haExpirado(30 * self::MIN, 8 * self::HORA)); // exactamente 8 h

        $this->ahora += 10 * self::MIN;
        $this->sesion->registrarActividad();

        $this->assertTrue($this->sesion->haExpirado(30 * self::MIN, 8 * self::HORA));
    }

    public function test_una_sesion_sin_marcas_de_tiempo_se_considera_expirada(): void
    {
        $_SESSION = ['id_usuario' => 7, 'rol' => 'Aprendiz'];

        $this->assertTrue($this->sesion->haExpirado(30 * self::MIN, 8 * self::HORA));
    }

    public function test_sin_usuario_no_hay_nada_que_expirar(): void
    {
        $_SESSION = [];

        $this->assertFalse($this->sesion->haExpirado(30 * self::MIN, 8 * self::HORA));
    }

    public function test_cerrar_con_aviso_borra_la_identidad_y_deja_el_mensaje(): void
    {
        $this->sesion->cerrarConAviso('Tu sesión expiró por seguridad. Vuelve a ingresar.');

        $this->assertNull($this->sesion->idUsuario());
        $this->assertNull($this->sesion->rol());
        $mensajes = $this->sesion->tomarMensajes();
        $this->assertCount(1, $mensajes);
        $this->assertSame('aviso', $mensajes[0]['tipo']);
        $this->assertSame('Tu sesión expiró por seguridad. Vuelve a ingresar.', $mensajes[0]['texto']);
    }

    public function test_fijar_debe_cambiar_actualiza_la_bandera(): void
    {
        $this->sesion->fijarDebeCambiar(true);
        $this->assertTrue($this->sesion->debeCambiarContrasena());

        $this->sesion->fijarDebeCambiar(false);
        $this->assertFalse($this->sesion->debeCambiarContrasena());
    }
}
