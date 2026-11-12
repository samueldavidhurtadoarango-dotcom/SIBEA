<?php

/**
 * RESUMEN DE DOCUMENTOS
 *
 * Paso 4 del recorrido: decide el texto corto de la columna «Documentos» de la lista de postulaciones y del
 * Excel (spec 002, vista 3). Se ve en una sola palabra clave cuál es la situación de los documentos de una
 * postulación. El orden de las reglas importa: lo más urgente gana.
 */

declare(strict_types=1);

namespace BenefitRack\Servicios;

final class ResumenDocumentos
{
    /**
     * @param array<string, mixed> $fila Una fila del repositorio: docs_total, docs_pendientes, docs_no_validos
     *                                   y documento_pedido (1 si hay una solicitud de documento Pendiente).
     * @return string «Documento pedido», «2 no válidos», «2 por revisar», «Todos válidos» o «Sin documentos».
     */
    public static function texto(array $fila): string
    {
        $total = (int) ($fila['docs_total'] ?? 0);
        $noValidos = (int) ($fila['docs_no_validos'] ?? 0);
        $pendientes = (int) ($fila['docs_pendientes'] ?? 0);

        if ((int) ($fila['documento_pedido'] ?? 0) === 1) {
            return 'Documento pedido';
        }
        if ($noValidos > 0) {
            return $noValidos === 1 ? '1 no válido' : "$noValidos no válidos";
        }
        if ($pendientes > 0) {
            return "$pendientes por revisar";
        }

        return $total === 0 ? 'Sin documentos' : 'Todos válidos';
    }
}
