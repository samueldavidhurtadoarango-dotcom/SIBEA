#!/bin/sh
# Ejecuta las dos pruebas de concurrencia con dos clientes en paralelo.
# Uso: sh ejecutar.sh "<ruta a mysql.exe> -u root -P 3306 -h 127.0.0.1"
# ATENCIÓN: recrea la base benefitrack_v2 (la borra).
M="$1 --default-character-set=utf8mb4 --table"
DIR=$(dirname "$0")
$M < "$DIR/../../benefitrack_v2.sql" || exit 1
$M < "$DIR/preparar.sql"
echo "===== Prueba B1: dos aprobaciones en paralelo con 1 cupo ====="
$M < "$DIR/b1_sesion_a.sql" > "$DIR/.a.out" 2>&1 &
$M < "$DIR/b1_sesion_b.sql" 2>&1
wait
cat "$DIR/.a.out"
$M < "$DIR/b1_resultado.sql"
echo "===== Prueba I1: los dos últimos pagos en paralelo ====="
$M < "$DIR/i1_sesion_a.sql" > "$DIR/.a.out" 2>&1 &
$M < "$DIR/i1_sesion_b.sql" 2>&1
wait
cat "$DIR/.a.out"
$M < "$DIR/i1_resultado.sql"
rm -f "$DIR/.a.out"
