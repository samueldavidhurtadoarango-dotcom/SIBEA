<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Nucleo;

use PHPUnit\Framework\TestCase;
use SIBEA\Nucleo\Sesion;
use SIBEA\Nucleo\Vista;

/** Comprueba lo importante de los tres marcos (publica, admin, aprendiz) y de las páginas de error. */
final class MarcosTest extends TestCase
{
    private Sesion $sesion;
    private Vista $vista;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->sesion = new Sesion();
        $this->vista = new Vista(dirname(__DIR__, 3) . '/vistas', $this->sesion);
    }

    public function test_el_marco_admin_marca_el_enlace_activo_segun_la_url(): void
    {
        $this->vista->compartir('rutaActual', '/admin/usuarios/5');

        $html = $this->vista->mostrar('errores/404', [], 'admin');

        $this->assertMatchesRegularExpression('#href="/admin/usuarios"[^>]*aria-current="page"#', $html);
        $this->assertDoesNotMatchRegularExpression('#href="/admin/postulaciones"[^>]*aria-current#', $html);
    }

    public function test_el_menu_admin_solo_tiene_tres_opciones(): void
    {
        $html = $this->vista->mostrar('errores/404', [], 'admin');

        foreach (['/admin/convocatorias', '/admin/postulaciones', '/admin/usuarios'] as $url) {
            $this->assertStringContainsString('href="' . $url . '"', $html);
        }
        $this->assertStringNotContainsString('Pagos', $html);
        $this->assertStringNotContainsString('Dashboard', $html);
    }

    public function test_los_marcos_con_sesion_traen_salir_con_token_csrf(): void
    {
        foreach (['admin', 'aprendiz'] as $marco) {
            $html = $this->vista->mostrar('errores/404', [], $marco);

            $this->assertStringContainsString('action="/salir"', $html);
            $this->assertStringContainsString('name="_csrf" value="' . $_SESSION['_csrf'] . '"', $html);
        }
    }

    public function test_el_saludo_del_aprendiz_escapa_el_nombre(): void
    {
        $this->sesion->iniciarPara(3, 'Aprendiz', false, '<script>alert(1)</script>');

        $html = $this->vista->mostrar('errores/404', [], 'aprendiz');

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_los_mensajes_se_muestran_escapados_y_una_sola_vez(): void
    {
        $this->sesion->mensaje('error', 'Ojo <b>aquí</b>');

        $primera = $this->vista->mostrar('errores/404');
        $segunda = $this->vista->mostrar('errores/404');

        $this->assertStringContainsString('br-alerta--error', $primera);
        $this->assertStringContainsString('Ojo &lt;b&gt;aquí&lt;/b&gt;', $primera);
        $this->assertStringNotContainsString('br-alerta', $segunda);
    }

    public function test_la_pagina_403_muestra_el_mensaje_que_se_le_pasa(): void
    {
        $html = $this->vista->mostrar('errores/403', ['mensaje' => 'Sin permiso <x>']);

        $this->assertStringContainsString('Error 403', $html);
        $this->assertStringContainsString('Sin permiso &lt;x&gt;', $html);
    }
}
