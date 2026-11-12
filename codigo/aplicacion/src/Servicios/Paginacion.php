<?php

/**
 * PAGINACIÓN
 *
 * Paso 4 del recorrido: dos cuentas pequeñas que necesitan las listas con páginas: cuántas páginas hay y qué
 * página mostrar si piden una que no existe (?pagina=0 o ?pagina=999 no deben dar error ni una página vacía).
 */

declare(strict_types=1);

namespace BenefitRack\Servicios;

final class Paginacion
{
    /**
     * @param int $total     Cuántas filas hay en total.
     * @param int $porPagina Cuántas caben en cada página.
     * @return int Cuántas páginas hay (mínimo 1, aunque no haya filas).
     */
    public static function paginas(int $total, int $porPagina): int
    {
        return max(1, (int) ceil($total / $porPagina));
    }

    /**
     * @param int $pedida  La página que pidió la persona.
     * @param int $paginas Cuántas páginas hay.
     * @return int La página pedida, pero dentro de 1 y la última.
     */
    public static function corregir(int $pedida, int $paginas): int
    {
        return min(max(1, $pedida), $paginas);
    }
}
