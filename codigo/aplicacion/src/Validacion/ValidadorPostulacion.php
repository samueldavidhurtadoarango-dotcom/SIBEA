<?php

/**
 * VALIDADOR DE LA POSTULACIÓN
 *
 * Paso 4 del recorrido. Revisa todo el formulario «Postúlate a…»: contacto, EPS, cuenta bancaria, motivo,
 * la aceptación del tratamiento de datos y los tres documentos. Devuelve los datos ya limpios (teléfono solo con
 * dígitos, fecha como AAAA-MM-DD, ids como números, los archivos ya revisados bajo la clave «archivos») o un mensaje
 * por cada campo con problema.
 * Por qué el servidor valida aunque el navegador ya lo haya hecho: el JavaScript del navegador lo controla quien
 * usa la página y se puede saltar; solo lo que se revisa aquí es de fiar.
 */

declare(strict_types=1);

namespace SIBEA\Validacion;

use DateTimeImmutable;

final class ValidadorPostulacion extends Validador
{
    private const MOTIVO_MAXIMO = 500;
    private const EDAD_MINIMA = 14;
    private const EDAD_MAXIMA = 60;
    private const TEXTO_MAXIMO = 200;

    /** Campo del formulario => [nombre legible del documento]. El tipo de documento (1, 4, 2) lo pone el servicio de envío. */
    public const DOCUMENTOS = [
        'doc_identidad' => 'Documento de identidad',
        'cert_eps' => 'Certificado de EPS',
        'cert_bancario' => 'Certificado bancario',
    ];

    private readonly ValidadorArchivo $validadorArchivo;
    private readonly DateTimeImmutable $hoy;

    /**
     * @param ValidadorArchivo|null  $validadorArchivo Revisa cada documento (por defecto el normal).
     * @param DateTimeImmutable|null $hoy              El día de hoy, para calcular la edad (se cambia solo en las pruebas).
     */
    public function __construct(?ValidadorArchivo $validadorArchivo = null, ?DateTimeImmutable $hoy = null)
    {
        $this->validadorArchivo = $validadorArchivo ?? new ValidadorArchivo();
        $this->hoy = $hoy ?? new DateTimeImmutable('today');
    }

    /**
     * @param array<string, string>                    $campos    Lo escrito en el formulario (telefono, direccion, fecha_nacimiento, id_eps,
     *                                                            id_entidad_bancaria, id_tipo_cuenta, numero_cuenta, titular_cuenta, motivo, acepta_datos).
     * @param array<string, array<string, mixed>|null> $archivos  Los archivos subidos por campo (doc_identidad, cert_eps, cert_bancario).
     * @param array<string, list<array{id: int, nombre: string}>> $catalogos Listas permitidas: eps, bancos y tiposCuenta.
     * @return ResultadoValidacion Si es válido, datos(): los campos limpios y «archivos» (campo => ruta, extension, nombre_original, bytes).
     */
    public function validar(array $campos, array $archivos, array $catalogos): ResultadoValidacion
    {
        $resultado = new ResultadoValidacion();

        $telefono = $this->soloDigitos($campos['telefono'] ?? '');
        $direccion = trim($campos['direccion'] ?? '');
        $fecha = $this->fechaIso(trim($campos['fecha_nacimiento'] ?? ''));
        $numeroCuenta = trim($campos['numero_cuenta'] ?? '');
        $titular = trim($campos['titular_cuenta'] ?? '');
        $motivo = trim($campos['motivo'] ?? '');

        if (!$this->cumple('/^[0-9]{7,10}$/', $telefono)) {
            $resultado->agregar('telefono', 'El teléfono debe tener de 7 a 10 números');
        }
        $this->revisarTexto($resultado, 'direccion', $direccion, 'Escribe tu dirección de residencia');
        $this->revisarFecha($resultado, $fecha);
        $idEps = $this->idDeLista($resultado, $campos, 'id_eps', $catalogos['eps'] ?? [], 'Elige tu EPS de la lista');
        $idBanco = $this->idDeLista($resultado, $campos, 'id_entidad_bancaria', $catalogos['bancos'] ?? [], 'Elige tu banco de la lista');
        $idTipo = $this->idDeLista($resultado, $campos, 'id_tipo_cuenta', $catalogos['tiposCuenta'] ?? [], 'Elige el tipo de cuenta');
        if (!$this->cumple('/^[0-9]{6,20}$/', $numeroCuenta)) {
            $resultado->agregar('numero_cuenta', 'El número de cuenta debe tener de 6 a 20 números, sin puntos ni guiones');
        }
        $this->revisarTexto($resultado, 'titular_cuenta', $titular, 'Escribe el nombre del titular de la cuenta');
        if (mb_strlen($motivo) > self::MOTIVO_MAXIMO) {
            $resultado->agregar('motivo', 'El motivo puede tener máximo ' . self::MOTIVO_MAXIMO . ' caracteres');
        }
        if (($campos['acepta_datos'] ?? '') === '') {
            $resultado->agregar('acepta_datos', 'Debes declarar que la información es verdadera y autorizar el tratamiento de tus datos');
        }
        $archivosLimpios = $this->revisarArchivos($resultado, $archivos);

        // Los datos solo salen si todo es válido (contrato de ResultadoValidacion): con errores no se expone nada a medias.
        if (!$resultado->esValido()) {
            return $resultado;
        }

        return new ResultadoValidacion([
            'telefono' => $telefono,
            'direccion' => $direccion,
            'fecha_nacimiento' => $fecha,
            'id_eps' => $idEps,
            'id_entidad_bancaria' => $idBanco,
            'id_tipo_cuenta' => $idTipo,
            'numero_cuenta' => $numeroCuenta,
            'titular_cuenta' => $titular,
            'motivo' => $motivo,
            'acepta_datos' => true,
            'archivos' => $archivosLimpios,
        ]);
    }

    /**
     * Revisa los tres documentos; cada problema queda anotado bajo el nombre de su campo.
     *
     * @param array<string, array<string, mixed>|null> $archivos
     * @return array<string, array<string, mixed>> Los archivos válidos por campo.
     */
    private function revisarArchivos(ResultadoValidacion $resultado, array $archivos): array
    {
        $limpios = [];
        foreach (self::DOCUMENTOS as $campo => $nombreLegible) {
            $revisado = $this->validadorArchivo->validar($archivos[$campo] ?? null, $campo, $nombreLegible);
            foreach ($revisado->errores() as $mensaje) {
                $resultado->agregar($campo, $mensaje);
            }
            if ($revisado->esValido()) {
                $limpios[$campo] = $revisado->datos();
            }
        }

        return $limpios;
    }

    private function revisarTexto(ResultadoValidacion $resultado, string $campo, string $texto, string $mensaje): void
    {
        if ($texto === '' || mb_strlen($texto) > self::TEXTO_MAXIMO) {
            $resultado->agregar($campo, $mensaje);
        }
    }

    /** La fecha ya viene en AAAA-MM-DD (o null si no se pudo leer); aquí se revisa que la edad sea de 14 a 60 años. */
    private function revisarFecha(ResultadoValidacion $resultado, ?string $fecha): void
    {
        if ($fecha === null) {
            $resultado->agregar('fecha_nacimiento', 'Escribe tu fecha de nacimiento, por ejemplo 15/03/2002');

            return;
        }
        $edad = (new DateTimeImmutable($fecha))->diff($this->hoy);
        // invert = 1 significa que la fecha es futura: no es una persona nacida todavía.
        if ($edad->invert === 1 || $edad->y < self::EDAD_MINIMA || $edad->y > self::EDAD_MAXIMA) {
            $resultado->agregar('fecha_nacimiento', 'La edad debe estar entre ' . self::EDAD_MINIMA . ' y ' . self::EDAD_MAXIMA . ' años');
        }
    }

    /**
     * @param array<string, string>                    $campos
     * @param list<array{id: int, nombre: string}>     $lista  Los valores permitidos.
     * @return int|null El id elegido si está en la lista; si no, null y se anota el error.
     */
    private function idDeLista(ResultadoValidacion $resultado, array $campos, string $campo, array $lista, string $mensaje): ?int
    {
        $enviado = trim($campos[$campo] ?? '');
        // Seguridad: no basta con que sea un número; tiene que ser uno de los que ofrece la lista (la base solo acepta esos).
        foreach ($lista as $opcion) {
            if ($enviado !== '' && (string) $opcion['id'] === $enviado) {
                return $opcion['id'];
            }
        }
        $resultado->agregar($campo, $mensaje);

        return null;
    }

    /** Quita espacios, guiones y paréntesis de un teléfono («300 123-4567» queda «3001234567»). Las letras se quedan y fallan la regla. */
    private function soloDigitos(string $texto): string
    {
        return (string) preg_replace('/[\s\-().]/u', '', $texto);
    }

    /**
     * Acepta AAAA-MM-DD (lo que manda un campo de fecha) y DD/MM/AAAA (lo que se escribe a mano).
     *
     * @return string|null La fecha como AAAA-MM-DD, o null si no existe en el calendario.
     */
    private function fechaIso(string $texto): ?string
    {
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $texto, $m) === 1) {
            [$anio, $mes, $dia] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $texto, $m) === 1) {
            [$dia, $mes, $anio] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($mes, $dia, $anio) ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia) : null;
    }
}
