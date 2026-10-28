#!/bin/sh
# Ejecuta las dos pruebas de concurrencia con dos clientes en paralelo.
# Uso: sh ejecutar.sh "<ruta a mysql.exe> -u root -P 3306 -h 127.0.0.1"
#
# ATENCIÓN: este guion BORRA y vuelve a crear la base benefitrack_v2
# (ejecuta benefitrack_v2.sql). No lo use sobre una base con datos reales.
M="$1 --default-character-set=utf8mb4 --table"
DIR=$(dirname "$0")
# La salida de la sesión A va a un archivo temporal del sistema, fuera del
# repositorio, y se borra al terminar.
TMPA=$(mktemp "${TMPDIR:-/tmp}/benefitrack_concurrencia.XXXXXX") || exit 1
trap 'rm -f "$TMPA"' EXIT INT TERM
$M < "$DIR/../../benefitrack_v2.sql" || exit 1
$M < "$DIR/preparar.sql"
echo "===== Prueba B1: dos aprobaciones en paralelo con 1 cupo ====="
$M < "$DIR/b1_sesion_a.sql" > "$TMPA" 2>&1 &
$M < "$DIR/b1_sesion_b.sql" 2>&1
wait
cat "$TMPA"
$M < "$DIR/b1_resultado.sql"
echo "===== Prueba I1: los dos últimos pagos en paralelo ====="
$M < "$DIR/i1_sesion_a.sql" > "$TMPA" 2>&1 &
$M < "$DIR/i1_sesion_b.sql" 2>&1
wait
cat "$TMPA"
$M < "$DIR/i1_resultado.sql"
