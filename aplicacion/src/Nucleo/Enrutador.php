<?php

/**
 * ENRUTADOR
 *
 * Paso 2 del recorrido: dada la petición (método + URL), busca en la tabla de
 * rutas (rutas.php) qué controlador la atiende y qué filtros (sesión, rol,
 * CSRF...) deben revisarla antes. No ejecuta nada: solo responde "esta ruta
 * es de tal controlador" y Aplicacion hace el resto.
 */

declare(strict_types=1);

namespace BenefitRack\Nucleo;

use BenefitRack\Filtros\Filtro;

final class Enrutador
{
    /** @var list<array{metodo: string, patron: string, regex: string, destino: callable|array<int, string>, filtros: list<Filtro>}> */
    private array $rutas = [];

    /**
     * Registra una ruta.
     *
     * @param string                    $metodo  GET o POST.
     * @param string                    $patron  URL con parámetros, por ejemplo /admin/postulaciones/{id}.
     * @param callable|array<int,string> $destino [ClaseControlador::class, 'metodo'] o una función.
     * @param list<Filtro>              $filtros Filtros que se ejecutan antes, en orden.
     * @return void
     */
    public function agregar(string $metodo, string $patron, callable|array $destino, array $filtros = []): void
    {
        $this->rutas[] = [
            'metodo' => strtoupper($metodo),
            'patron' => $patron,
            'regex' => $this->aExpresion($patron),
            'destino' => $destino,
            'filtros' => $filtros,
        ];
    }

    /**
     * Lista las rutas registradas (método y patrón). Sirve para que las pruebas de permisos
     * recorran la tabla real y revisen también las rutas que se agreguen en el futuro.
     *
     * @return list<array{metodo: string, patron: string}>
     */
    public function rutas(): array
    {
        return array_map(
            static fn (array $ruta): array => ['metodo' => $ruta['metodo'], 'patron' => $ruta['patron']],
            $this->rutas
        );
    }

    /**
     * Busca la ruta que atiende la petición.
     *
     * @param Peticion $peticion La petición del navegador.
     * @return array{destino: callable|array<int, string>, filtros: list<Filtro>, parametros: array<string, string>}|null
     *         Los datos de la ruta encontrada, o null si ninguna coincide.
     */
    public function resolver(Peticion $peticion): ?array
    {
        foreach ($this->rutas as $ruta) {
            if ($ruta['metodo'] !== $peticion->metodo()) {
                continue;
            }
            if (preg_match($ruta['regex'], $peticion->ruta(), $coincidencias) === 1) {
                return [
                    'destino' => $ruta['destino'],
                    'filtros' => $ruta['filtros'],
                    'parametros' => $this->soloNombrados($coincidencias),
                ];
            }
        }

        return null;
    }

    /**
     * Convierte un patrón como /admin/usuarios/{id} en una expresión regular.
     * Cada {nombre} solo acepta dígitos: así "/usuarios/abc" nunca llega al controlador.
     */
    private function aExpresion(string $patron): string
    {
        $escapado = preg_quote($patron, '#');
        $conParametros = preg_replace('/\\\\\{(\w+)\\\\\}/', '(?P<$1>\d+)', $escapado);

        return '#^' . $conParametros . '$#';
    }

    /**
     * Deja solo los grupos con nombre (los {id}) y descarta los numéricos que agrega preg_match.
     *
     * @param array<int|string, string> $coincidencias
     * @return array<string, string>
     */
    private function soloNombrados(array $coincidencias): array
    {
        return array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
