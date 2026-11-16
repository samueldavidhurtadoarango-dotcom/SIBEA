<?php

/**
 * SERVICIO DE USUARIOS (gestión de aprendices por el administrador)
 *
 * Paso 4 del recorrido: las reglas de negocio de crear cuentas, cambiar su estado y
 * restablecer contraseñas. El controlador le entrega datos ya validados; este servicio
 * genera la contraseña temporal, guarda SOLO su hash y traduce los errores de la base
 * a mensajes que se pueden mostrar. No tiene SQL (está en RepositorioUsuarios).
 */

declare(strict_types=1);

namespace SIBEA\Servicios;

use PDOException;
use SIBEA\Nucleo\ErrorDeNegocio;
use SIBEA\Nucleo\TraductorErroresBd;
use SIBEA\Repositorios\RepositorioUsuarios;

final class ServicioUsuarios
{
    /** Filas por página en la lista. */
    public const POR_PAGINA = 20;

    /** Los dos estados que el administrador puede poner a mano («desbloquear» es pasar a Activo). */
    private const ESTADOS_PERMITIDOS = ['Activo', 'Inactivo'];

    /** Código de MariaDB para «clave única repetida» (documento o correo ya existentes). */
    private const CODIGO_DUPLICADO = 1062;

    private readonly GeneradorContrasena $generador;

    /**
     * @param RepositorioUsuarios       $usuarios  Donde se leen y guardan las cuentas.
     * @param GeneradorContrasena|null  $generador Inventa la contraseña temporal (por defecto, el real).
     */
    public function __construct(private readonly RepositorioUsuarios $usuarios, ?GeneradorContrasena $generador = null)
    {
        $this->generador = $generador ?? new GeneradorContrasena();
    }

    /**
     * Una página de la lista de aprendices.
     *
     * @param string $buscar Texto a buscar en nombre, documento o correo (vacío = todos).
     * @param int    $pagina Número de página pedido (si es menor que 1 o pasa de la última, se corrige).
     * @return array{filas: list<array<string, mixed>>, total: int, pagina: int, paginas: int, porPagina: int}
     */
    public function listar(string $buscar, int $pagina): array
    {
        $total = $this->usuarios->contarAprendices($buscar);
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = min(max(1, $pagina), $paginas);
        $filas = $this->usuarios->listarAprendices($buscar, self::POR_PAGINA, ($pagina - 1) * self::POR_PAGINA);

        return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas, 'porPagina' => self::POR_PAGINA];
    }

    /**
     * @param int $id Id del aprendiz.
     * @return array<string, mixed> Sus datos (id_usuario, nombre, apellido, documento, correo, estado).
     * @throws ErrorDeNegocio Si no existe o no es aprendiz.
     */
    public function aprendiz(int $id): array
    {
        return $this->usuarios->buscarAprendiz($id) ?? throw new ErrorDeNegocio('No encontramos a ese aprendiz.');
    }

    /**
     * Crea la cuenta de un aprendiz con una contraseña temporal.
     *
     * @param array<string, mixed> $datos documento, nombre, apellido, correo, id_programa y ficha (ya validados).
     * @return array{id: int, contrasena: string} El id y la contraseña en claro, para entregarla UNA vez.
     * @throws ErrorDeNegocio Si el documento o el correo ya tienen cuenta.
     */
    public function crearAprendiz(array $datos): array
    {
        $contrasena = $this->generador->generar();

        try {
            // Seguridad: a la base solo llega el hash (bcrypt con sal propia); la contraseña en claro
            // solo vive en esta variable y en lo que se le muestra al administrador.
            $id = $this->usuarios->crear($datos, password_hash($contrasena, PASSWORD_DEFAULT));
        } catch (PDOException $error) {
            // Regla de negocio: no se crean dos cuentas con el mismo documento o correo (claves únicas de la base).
            if (($error->errorInfo[1] ?? 0) === self::CODIGO_DUPLICADO) {
                throw new ErrorDeNegocio('Ya existe una cuenta con ese documento o con ese correo. No se creó otra.', 0, $error);
            }
            throw TraductorErroresBd::traducir($error);
        }

        return ['id' => $id, 'contrasena' => $contrasena];
    }

    /**
     * Activa o desactiva una cuenta de aprendiz («desbloquear» = poner Activo).
     *
     * @param int    $id            Id del aprendiz.
     * @param string $estado        Activo o Inactivo.
     * @param string $justificacion El motivo (la base lo exige y lo guarda en la auditoría).
     * @return void
     * @throws ErrorDeNegocio Si el estado no es permitido, no es un aprendiz o la base rechaza el cambio.
     */
    public function cambiarEstado(int $id, string $estado, string $justificacion): void
    {
        if (!in_array($estado, self::ESTADOS_PERMITIDOS, true)) {
            throw new ErrorDeNegocio('Solo se puede dejar una cuenta Activa o Inactiva.');
        }
        // Se confirma que sea un APRENDIZ: así esta pantalla nunca puede desactivar a un administrador (ni a quien la usa).
        $this->aprendiz($id);

        try {
            // La justificación es obligatoria: si viene vacía, la base responde con su propio mensaje.
            $this->usuarios->cambiarEstado($id, $estado, trim($justificacion));
        } catch (PDOException $error) {
            throw TraductorErroresBd::traducir($error);
        }
    }

    /**
     * Pone una contraseña temporal nueva a un aprendiz (la anterior deja de servir).
     *
     * @param int $id Id del aprendiz.
     * @return string La contraseña nueva en claro, para entregarla UNA vez.
     * @throws ErrorDeNegocio Si no es un aprendiz o la base rechaza el cambio.
     */
    public function restablecerContrasena(int $id): string
    {
        $this->aprendiz($id);
        $contrasena = $this->generador->generar();

        try {
            // La base deja debe_cambiar_contrasena = 1 sola: quien cambia la clave es otra persona (el administrador).
            $cambiada = $this->usuarios->restablecerContrasena($id, password_hash($contrasena, PASSWORD_DEFAULT));
        } catch (PDOException $error) {
            throw TraductorErroresBd::traducir($error);
        }
        if (!$cambiada) {
            throw new ErrorDeNegocio('No pudimos restablecer la contraseña de esa cuenta.');
        }

        return $contrasena;
    }
}
