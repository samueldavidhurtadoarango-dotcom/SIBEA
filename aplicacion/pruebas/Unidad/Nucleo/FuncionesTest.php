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
}
