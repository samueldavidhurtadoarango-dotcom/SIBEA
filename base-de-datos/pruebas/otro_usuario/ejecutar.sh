#!/bin/sh
# Prueba de la lista blanca de pr_exigir_administrador (I2) con un usuario de
# conexión que NO es root ni benefitrack_app.
# Uso: sh ejecutar.sh "<ruta a mysql.exe> -u root -P 3306 -h 127.0.0.1"
#
# Necesita la base benefitrack_v2 ya creada (benefitrack_v2.sql). No la borra.
# Crea el usuario temporal 'benefitrack_otro'@'localhost' y un aprendiz de
# prueba (documento PRUEBA-OTRO); al terminar (aunque falle o se interrumpa)
# borra los dos.
M="$1 --default-character-set=utf8mb4 --table"
DIR=$(dirname "$0")
case "$1" in
  *"-u root"*) ;;
  *) echo "El primer argumento debe incluir '-u root'"; exit 1 ;;
esac
OTRO=$(printf '%s' "$1" | sed 's/-u root/-u benefitrack_otro -pOtro_Prueba_1/')
OTRO="$OTRO --default-character-set=utf8mb4 --table"
limpiar() { $M < "$DIR/limpiar.sql"; }
trap limpiar EXIT
trap 'exit 130' INT TERM
$M < "$DIR/preparar.sql" || exit 1
$OTRO --force < "$DIR/prueba.sql" 2>&1
