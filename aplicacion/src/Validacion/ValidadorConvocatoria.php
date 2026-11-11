<?php

/**
 * VALIDADOR DE UNA CONVOCATORIA
 *
 * Paso 4 del recorrido. Revisa lo que el administrador escribió en el formulario
 * «Nueva convocatoria» ANTES de llegar a la base de datos, para poder avisar campo
 * por campo. La base repite las reglas de fondo con CHECK y triggers: esto solo
 * existe para dar mensajes amables. Devuelve los datos ya limpios (números como
 * números, el valor como decimal «180000.00») o un mensaje por cada campo con problema.
 */

declare(strict_types=1);

namespace BenefitRack\Validacion;

use DateTimeImmutable;

final class ValidadorConvocatoria extends Validador
{
    private const NOMBRE_MAXIMO = 150;
    private const REQUISITOS_MAXIMO = 5000;
    private const CUPOS_MAXIMO = 100000;
    private const PAGOS_MAXIMO = 120;
    private const MESES_MAXIMO = 120;
    /** La columna es DECIMAL(12,2): caben 10 dígitos enteros. */
    private const VALOR_MAXIMO_DIGITOS = 10;
    /** Con qué estado puede nacer una convocatoria (los demás los pone el cierre). */
    private const ESTADOS_INICIALES = ['Activa', 'Inactiva'];

    /**
     * @param array<string, string> $d Campos: nombre, id_beneficio, fecha_inicio, fecha_cierre, cupos,
     *                                 requisitos, valor_pago, numero_pagos, duracion_meses y estado (opcional).
     * @return ResultadoValidacion Si es válido, datos(): los mismos campos ya limpios (estado = Inactiva si no viene).
     */
    public function validar(array $d): ResultadoValidacion
    {
        $resultado = new ResultadoValidacion();

        $nombre = trim($d['nombre'] ?? '');
        $requisitos = trim($d['requisitos'] ?? '');
        $estado = trim($d['estado'] ?? '') ?: 'Inactiva';
        $idBeneficio = $this->entero($d['id_beneficio'] ?? '', 1);
        $cupos = $this->entero($d['cupos'] ?? '', self::CUPOS_MAXIMO);
        $pagos = $this->entero($d['numero_pagos'] ?? '', self::PAGOS_MAXIMO);
        $meses = $this->entero($d['duracion_meses'] ?? '', self::MESES_MAXIMO);
        $valor = $this->valorEnPesos($d['valor_pago'] ?? '');

        // En el mismo orden del formulario, para que el resumen de errores se lea de arriba abajo.
        $this->revisarTexto($resultado, 'nombre', $nombre, self::NOMBRE_MAXIMO, 'Escribe el nombre de la convocatoria.');
        $this->revisarNumero($resultado, 'id_beneficio', $idBeneficio, 'Elige un beneficio de la lista.');
        $this->revisarFechas($resultado, $d['fecha_inicio'] ?? '', $d['fecha_cierre'] ?? '');
        $this->revisarNumero($resultado, 'cupos', $cupos, 'Los cupos deben ser un número entero mayor que 0.');
        $this->revisarNumero($resultado, 'valor_pago', $valor, 'El valor de cada pago debe ser mayor que 0, por ejemplo 180000 o 180.000.');
        $this->revisarNumero($resultado, 'numero_pagos', $pagos, 'El número de pagos debe ser un entero mayor que 0.');
        $this->revisarNumero($resultado, 'duracion_meses', $meses, 'La duración debe ser un número entero de meses, mayor que 0.');
        $this->revisarTexto($resultado, 'requisitos', $requisitos, self::REQUISITOS_MAXIMO, 'Escribe los requisitos, uno por línea.');
        if (!in_array($estado, self::ESTADOS_INICIALES, true)) {
            $resultado->agregar('estado', 'Elige Activa o Inactiva.');
        }

        // Los datos solo salen si todo es válido (contrato de ResultadoValidacion).
        if (!$resultado->esValido()) {
            return $resultado;
        }

        return new ResultadoValidacion([
            'nombre' => $nombre,
            'id_beneficio' => $idBeneficio,
            'fecha_inicio' => trim($d['fecha_inicio'] ?? ''),
            'fecha_cierre' => trim($d['fecha_cierre'] ?? ''),
            'cupos' => $cupos,
            'requisitos' => $requisitos,
            'valor_pago' => $valor,
            'numero_pagos' => $pagos,
            'duracion_meses' => $meses,
            'estado' => $estado,
        ]);
    }

    /**
     * Convierte «25» en 25. Solo acepta dígitos (nada de decimales, signos ni letras).
     *
     * @return int|null El número, o null si no es un entero entre 1 y $maximo.
     */
    private function entero(string $texto, int $maximo): ?int
    {
        $texto = trim($texto);
        if (!$this->cumple('/^[0-9]{1,9}$/', $texto)) {
            return null;
        }
        $numero = (int) $texto;

        return $numero >= 1 && $numero <= $maximo ? $numero : null;
    }

    /**
     * Convierte «$ 180.000», «180.000» o «180000» en «180000.00». Los puntos son separadores de
     * miles (así se escriben los pesos en Colombia): no se aceptan comas ni centavos.
     *
     * @return string|null El valor con dos decimales, o null si no es un monto válido mayor que 0.
     */
    private function valorEnPesos(string $texto): ?string
    {
        // Se quita el signo $ y los espacios de los bordes: queda «180.000» o «180000».
        $limpio = trim(str_replace('$', '', $texto));
        $conFormato = $this->cumple('/^[0-9]+$/', $limpio) || $this->cumple('/^[0-9]{1,3}(\.[0-9]{3})+$/', $limpio);
        $digitos = str_replace('.', '', $limpio);
        if (!$conFormato || strlen($digitos) > self::VALOR_MAXIMO_DIGITOS || (int) $digitos < 1) {
            return null;
        }

        return number_format((float) $digitos, 2, '.', '');
    }

    private function revisarTexto(ResultadoValidacion $resultado, string $campo, string $texto, int $maximo, string $mensajeVacio): void
    {
        if ($texto === '') {
            $resultado->agregar($campo, $mensajeVacio);
        } elseif (mb_strlen($texto) > $maximo) {
            $resultado->agregar($campo, "Es demasiado largo (máximo $maximo letras).");
        }
    }

    private function revisarNumero(ResultadoValidacion $resultado, string $campo, int|string|null $valor, string $mensaje): void
    {
        if ($valor === null) {
            $resultado->agregar($campo, $mensaje);
        }
    }

    /** Las dos fechas deben existir (AAAA-MM-DD) y el cierre debe ser DESPUÉS del inicio. */
    private function revisarFechas(ResultadoValidacion $resultado, string $inicio, string $cierre): void
    {
        $fechaInicio = $this->fecha($inicio);
        $fechaCierre = $this->fecha($cierre);

        if ($fechaInicio === null) {
            $resultado->agregar('fecha_inicio', 'Escribe una fecha de inicio válida.');
        }
        if ($fechaCierre === null) {
            $resultado->agregar('fecha_cierre', 'Escribe una fecha de cierre válida.');
        } elseif ($fechaInicio !== null && $fechaCierre <= $fechaInicio) {
            $resultado->agregar('fecha_cierre', 'La fecha de cierre debe ser después de la fecha de inicio.');
        }
    }

    /** @return DateTimeImmutable|null La fecha, o null si no existe (por ejemplo 30 de febrero). */
    private function fecha(string $texto): ?DateTimeImmutable
    {
        $texto = trim($texto);
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);

        // createFromFormat "corrige" las fechas imposibles (30 feb -> 2 mar): se compara de vuelta para detectarlo.
        return $fecha !== false && $fecha->format('Y-m-d') === $texto ? $fecha : null;
    }
}
