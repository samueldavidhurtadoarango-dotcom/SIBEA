<?php

/**
 * VALIDADOR DE UN APRENDIZ NUEVO
 *
 * Paso 4 del recorrido. Revisa los datos de UN aprendiz: los usa el formulario
 * «Crear una cuenta» y, más adelante, cada fila del Excel. Devuelve los datos ya
 * limpios (documento solo con dígitos, correo en minúsculas, id del programa) o
 * un mensaje por cada campo con problema.
 */

declare(strict_types=1);

namespace SIBEA\Validacion;

final class ValidadorUsuario extends Validador
{
    private const DOCUMENTO_MINIMO = 6;
    private const DOCUMENTO_MAXIMO = 12;
    private const FICHA_MAXIMO = 30;
    private const NOMBRE_MAXIMO = 100;
    private const CORREO_MAXIMO = 150;

    /**
     * @param array<string, string> $fila              Campos: documento, nombre, apellido, correo, programa (el nombre) y ficha.
     * @param array<string, int>    $programasPorNombre Nombre del programa => su id (los programas activos).
     * @return ResultadoValidacion Si es válido, datos(): documento, nombre, apellido, correo, id_programa y ficha.
     */
    public function validar(array $fila, array $programasPorNombre): ResultadoValidacion
    {
        $resultado = new ResultadoValidacion();

        $documento = $this->soloDigitos($fila['documento'] ?? '');
        $nombre = trim($fila['nombre'] ?? '');
        $apellido = trim($fila['apellido'] ?? '');
        $correo = mb_strtolower(trim($fila['correo'] ?? ''));
        $ficha = trim($fila['ficha'] ?? '');
        $idPrograma = $programasPorNombre[trim($fila['programa'] ?? '')] ?? null;

        $this->revisarDocumento($resultado, $documento);
        $this->revisarTexto($resultado, 'nombre', $nombre, 'El nombre es obligatorio.');
        $this->revisarTexto($resultado, 'apellido', $apellido, 'El apellido es obligatorio.');
        $this->revisarCorreo($resultado, $correo);
        if ($idPrograma === null) {
            $resultado->agregar('programa', 'Elige un programa de la lista.');
        }
        if (!$this->cumple('/^[0-9]{1,' . self::FICHA_MAXIMO . '}$/', $ficha)) {
            $resultado->agregar('ficha', 'La ficha debe tener solo números.');
        }

        // Los datos solo salen si todo es válido (contrato de ResultadoValidacion).
        if (!$resultado->esValido()) {
            return $resultado;
        }

        return new ResultadoValidacion([
            'documento' => $documento,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'correo' => $correo,
            'id_programa' => $idPrograma,
            'ficha' => $ficha,
        ]);
    }

    /**
     * El documento llega a veces como «1.020.345.678» o con espacios: se quitan los
     * puntos y los espacios. Si quedan letras u otros símbolos, no se "arregla": se rechaza.
     */
    private function soloDigitos(string $documento): string
    {
        return str_replace(['.', ' '], '', trim($documento));
    }

    private function revisarDocumento(ResultadoValidacion $resultado, string $documento): void
    {
        $patron = '/^[0-9]{' . self::DOCUMENTO_MINIMO . ',' . self::DOCUMENTO_MAXIMO . '}$/';
        if (!$this->cumple($patron, $documento)) {
            $resultado->agregar('documento', 'El documento debe tener entre 6 y 12 números, sin letras.');
        }
    }

    private function revisarTexto(ResultadoValidacion $resultado, string $campo, string $texto, string $mensajeVacio): void
    {
        if ($texto === '') {
            $resultado->agregar($campo, $mensajeVacio);
        } elseif (mb_strlen($texto) > self::NOMBRE_MAXIMO) {
            $resultado->agregar($campo, 'Es demasiado largo (máximo 100 letras).');
        }
    }

    private function revisarCorreo(ResultadoValidacion $resultado, string $correo): void
    {
        if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false || mb_strlen($correo) > self::CORREO_MAXIMO) {
            $resultado->agregar('correo', 'Escribe un correo válido, por ejemplo nombre@soy.sena.edu.co.');
        }
    }
}
