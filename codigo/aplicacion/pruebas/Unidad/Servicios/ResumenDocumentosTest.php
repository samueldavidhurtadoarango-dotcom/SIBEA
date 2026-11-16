<?php

declare(strict_types=1);

namespace SIBEA\Pruebas\Unidad\Servicios;

use PHPUnit\Framework\TestCase;
use SIBEA\Servicios\ResumenDocumentos;

/** El texto de la columna «Documentos» de la lista de postulaciones (spec 002, vista 3). */
final class ResumenDocumentosTest extends TestCase
{
    /** @return array<string, int> */
    private function fila(int $total, int $pendientes, int $noValidos, int $pedido): array
    {
        return ['docs_total' => $total, 'docs_pendientes' => $pendientes, 'docs_no_validos' => $noValidos, 'documento_pedido' => $pedido];
    }

    public function test_con_documento_pedido_dice_documento_pedido(): void
    {
        $this->assertSame('Documento pedido', ResumenDocumentos::texto($this->fila(3, 0, 1, 1)));
    }

    public function test_con_no_validos_dice_cuantos(): void
    {
        $this->assertSame('1 no válido', ResumenDocumentos::texto($this->fila(3, 0, 1, 0)));
        $this->assertSame('2 no válidos', ResumenDocumentos::texto($this->fila(3, 0, 2, 0)));
    }

    public function test_con_pendientes_dice_cuantos_faltan_por_revisar(): void
    {
        $this->assertSame('2 por revisar', ResumenDocumentos::texto($this->fila(3, 2, 0, 0)));
    }

    public function test_todos_validos(): void
    {
        $this->assertSame('Todos válidos', ResumenDocumentos::texto($this->fila(3, 0, 0, 0)));
    }

    public function test_sin_documentos(): void
    {
        $this->assertSame('Sin documentos', ResumenDocumentos::texto($this->fila(0, 0, 0, 0)));
    }
}
