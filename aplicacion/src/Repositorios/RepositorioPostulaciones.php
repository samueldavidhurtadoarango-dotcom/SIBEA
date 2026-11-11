<?php

/**
 * REPOSITORIO DE POSTULACIONES
 *
 * Paso 4 del recorrido: aquí vive el SQL de la tabla "postulaciones" (por ahora, crearla) y el control de la
 * transacción con la que ServicioPostulacion guarda todo junto o nada.
 *
 * Las reglas de fondo las impone la BASE DE DATOS con sus triggers (tr_postulacion_inicial): solo el propio
 * aprendiz crea su postulación, la convocatoria debe estar Activa y dentro de sus fechas, y el estado nace
 * Pendiente. La clave única (usuario, convocatoria) impide postularse dos veces.
 *
 * No es "final" a propósito: las pruebas del servicio la reemplazan por una falsa.
 */

declare(strict_types=1);

namespace BenefitRack\Repositorios;

use BenefitRack\Nucleo\BaseDatos;

class RepositorioPostulaciones
{
    /**
     * @param BaseDatos $baseDatos La conexión de esta petición (la base sabe qué aprendiz actúa).
     */
    public function __construct(private readonly BaseDatos $baseDatos)
    {
    }

    /**
     * Crea la postulación en estado Pendiente (la base lo exige).
     *
     * @param int    $idUsuario      El aprendiz dueño.
     * @param int    $idConvocatoria La convocatoria.
     * @param string $datosJson      Todo lo que envió, ya como JSON válido (la columna lo comprueba).
     * @return int El id de la postulación nueva.
     */
    public function crear(int $idUsuario, int $idConvocatoria, string $datosJson): int
    {
        $this->baseDatos->pdo()->prepare(
            'INSERT INTO postulaciones (id_usuario, id_convocatoria, datos_formulario) VALUES (?, ?, ?)'
        )->execute([$idUsuario, $idConvocatoria, $datosJson]);

        return (int) $this->baseDatos->pdo()->lastInsertId();
    }

    /**
     * Empieza una transacción: lo que se escriba después se confirma o se deshace todo junto.
     *
     * @return void
     */
    public function iniciarTransaccion(): void
    {
        $this->baseDatos->pdo()->beginTransaction();
    }

    /**
     * Confirma todo lo escrito desde iniciarTransaccion().
     *
     * @return void
     */
    public function confirmarTransaccion(): void
    {
        $this->baseDatos->pdo()->commit();
    }

    /**
     * Deshace todo lo escrito desde iniciarTransaccion(). Si no hay transacción abierta (por ejemplo, el commit
     * ya la cerró al fallar) no hace nada, para no tapar el error original con otro.
     *
     * @return void
     */
    public function revertirTransaccion(): void
    {
        if ($this->baseDatos->pdo()->inTransaction()) {
            $this->baseDatos->pdo()->rollBack();
        }
    }
}
