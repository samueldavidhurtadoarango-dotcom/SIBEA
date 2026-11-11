<?php

/**
 * PRUEBA DE PERMISOS SOBRE LA TABLA DE RUTAS REAL (CA-3 y CA-4)
 *
 * No lista las rutas a mano: recorre las que registra rutas.php en el Enrutador.
 * Así, cualquier ruta nueva que se agregue queda revisada sin tocar esta prueba.
 * Se comprueba lo que protegen los filtros, antes de que el controlador haga nada:
 *   - un aprendiz recibe 403 en toda ruta /admin/*;
 *   - todo POST sin token _csrf recibe 403 (salvo /ingresar, que vuelve al formulario: ruling R2);
 *   - sin sesión, toda ruta redirige a /ingresar (salvo el propio formulario GET /ingresar).
 */

declare(strict_types=1);

namespace BenefitRack\Pruebas\Integracion;

use BenefitRack\Nucleo\Aplicacion;
use BenefitRack\Nucleo\Enrutador;
use BenefitRack\Nucleo\Fabrica;
use BenefitRack\Nucleo\Peticion;
use BenefitRack\Nucleo\Respuesta;

final class PermisosRutasTest extends BaseDePrueba
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    private function fabrica(): Fabrica
    {
        return new Fabrica($this->configuracionDePrueba(), dirname(__DIR__, 2) . '/vistas', '127.0.0.1');
    }

    /** Las rutas reales, tal como las deja rutas.php, listas para recorrerlas. */
    private function enrutador(Fabrica $fabrica): Enrutador
    {
        $enrutador = new Enrutador();
        (require dirname(__DIR__, 2) . '/rutas.php')($enrutador, $fabrica);

        return $enrutador;
    }

    /** Lanza una petición a la aplicación completa (sin campos: ni siquiera un _csrf). */
    private function pedir(Fabrica $fabrica, string $metodo, string $patron): Respuesta
    {
        $ruta = str_replace('{id}', '1', $patron);
        $aplicacion = new Aplicacion($this->enrutador($fabrica), $fabrica->vista(), $this->configuracionDePrueba(), $fabrica);

        return $aplicacion->ejecutar(new Peticion($metodo, $ruta, [], [], '127.0.0.1'));
    }

    /** @return list<array{metodo: string, patron: string}> */
    private function rutas(): array
    {
        return $this->enrutador($this->fabrica())->rutas();
    }

    public function test_la_tabla_de_rutas_no_esta_vacia(): void
    {
        $this->assertGreaterThan(20, count($this->rutas()));
    }

    public function test_un_aprendiz_recibe_403_en_cada_ruta_de_admin(): void
    {
        $idAprendiz = $this->crearAprendiz();
        $revisadas = 0;

        foreach ($this->rutas() as $ruta) {
            if (!str_starts_with($ruta['patron'], '/admin/')) {
                continue;
            }
            $fabrica = $this->fabrica();
            $fabrica->sesion()->iniciarPara($idAprendiz, 'Aprendiz', false, 'Luisa');

            $respuesta = $this->pedir($fabrica, $ruta['metodo'], $ruta['patron']);

            $this->assertSame(403, $respuesta->codigo(), "{$ruta['metodo']} {$ruta['patron']} debería dar 403 a un aprendiz");
            $revisadas++;
        }

        $this->assertGreaterThan(15, $revisadas);
    }

    public function test_cada_post_sin_token_csrf_recibe_403(): void
    {
        $idAdmin = $this->crearAdmin();
        $idAprendiz = $this->crearAprendiz();
        $revisadas = 0;

        foreach ($this->rutas() as $ruta) {
            // /ingresar es la excepción documentada (R2): con el token vencido vuelve al formulario.
            if ($ruta['metodo'] !== 'POST' || $ruta['patron'] === '/ingresar') {
                continue;
            }
            $fabrica = $this->fabrica();
            // Cada ruta se prueba con el rol al que pertenece, para que el filtro de rol no sea el que responda.
            if (str_starts_with($ruta['patron'], '/admin/')) {
                $fabrica->sesion()->iniciarPara($idAdmin, 'Administrador', false, 'Ana');
            } else {
                $fabrica->sesion()->iniciarPara($idAprendiz, 'Aprendiz', false, 'Luisa');
            }

            $respuesta = $this->pedir($fabrica, 'POST', $ruta['patron']);

            $this->assertSame(403, $respuesta->codigo(), "POST {$ruta['patron']} sin _csrf debería dar 403");
            $revisadas++;
        }

        $this->assertGreaterThan(10, $revisadas);
    }

    public function test_el_post_de_ingresar_sin_token_vuelve_al_formulario(): void
    {
        $respuesta = $this->pedir($this->fabrica(), 'POST', '/ingresar');

        $this->assertSame(302, $respuesta->codigo());
        $this->assertSame('/ingresar', $respuesta->destino());
    }

    public function test_sin_sesion_toda_ruta_redirige_a_ingresar(): void
    {
        $revisadas = 0;

        foreach ($this->rutas() as $ruta) {
            if ($ruta['metodo'] === 'GET' && $ruta['patron'] === '/ingresar') {
                continue; // el formulario de ingreso es lo único público
            }
            $_SESSION = [];

            $respuesta = $this->pedir($this->fabrica(), $ruta['metodo'], $ruta['patron']);

            $this->assertSame(302, $respuesta->codigo(), "{$ruta['metodo']} {$ruta['patron']} sin sesión debería redirigir");
            $this->assertSame('/ingresar', $respuesta->destino(), "{$ruta['metodo']} {$ruta['patron']} sin sesión debería ir a /ingresar");
            $revisadas++;
        }

        $this->assertGreaterThan(20, $revisadas);
    }
}
