<?php

declare(strict_types=1);

namespace BenefitRack\Pruebas\Unidad\Nucleo;

use PHPUnit\Framework\TestCase;

final class FuncionesTest extends TestCase
{
    public function test_e_escapa_html_y_comillas(): void
    {
        $this->assertSame('&lt;script&gt;&quot;x&quot;&lt;/script&gt;', e('<script>"x"</script>'));
        $this->assertSame('', e(null));
    }
    public function test_formato_pesos_usa_puntos_de_miles(): void
    {
        $this->assertSame('$ 180.000', formato_pesos('180000.00'));
        $this->assertSame('$ 1.080.000', formato_pesos(1080000));
        $this->assertSame('$ 0', formato_pesos(0));
    }

    public function test_formato_fecha_usa_mes_corto_en_espanol(): void
    {
        $this->assertSame('15 nov 2026', formato_fecha('2026-11-15'));
        $this->assertSame('1 ago', formato_fecha('2026-08-01', false));
        $this->assertSame('16 mar 2026', formato_fecha('2026-03-16 14:30:00'));
        $this->assertSame('', formato_fecha(null));
    }
}
