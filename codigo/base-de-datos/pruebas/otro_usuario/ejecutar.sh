#!/bin/sh
# Prueba de la lista blanca de pr_exigir_administrador con un usuario de
# conexión que NO es root ni sibea_app.
#
# Uso (desde cualquier carpeta):
#   sh ejecutar.sh <ruta a mysql.exe> [host] [puerto]
#   sh ejecutar.sh "C:/xampp/mysql/bin/mysql.exe" 127.0.0.1 3306
# Se conecta como root. Si root tiene contraseña, pásela en la variable de
# entorno SIBEA_ROOT_PASSWORD (no en la línea de comandos).
#
# Necesita la base sibea ya creada (sibea.sql); no la
# borra. Crea el usuario temporal sibea_otro (para 'localhost' y
# '127.0.0.1') con una contraseña aleatoria y permisos mínimos, y un aprendiz
# de prueba (PRUEBA-OTRO). Al terminar, aunque falle o se interrumpa, borra
# el usuario, el aprendiz y los archivos temporales.
# Sale con 0 si el veredicto es CORRECTO y con 1 si no.

MYSQL="$1"
HOST="${2:-127.0.0.1}"
PUERTO="${3:-3306}"
DIR=$(dirname "$0")

if [ -z "$MYSQL" ] || [ ! -f "$MYSQL" ]; then
  echo "Uso: sh ejecutar.sh <ruta a mysql.exe> [host] [puerto]"
  exit 1
fi

# Archivos temporales fuera del repositorio, solo legibles por este usuario.
umask 077
TMPD=$(mktemp -d "${TMPDIR:-/tmp}/sibea_otro.XXXXXX") || exit 1
CNF_ROOT="$TMPD/root.cnf"
CNF_OTRO="$TMPD/otro.cnf"

# mysql.exe es un programa de Windows: necesita rutas de Windows.
nativo() { cygpath -m "$1" 2>/dev/null || printf '%s' "$1"; }

CLAVE="Bt_$(od -An -N12 -tx1 /dev/urandom | tr -d ' \n')"
if [ "$CLAVE" = "Bt_" ]; then
  echo "No se pudo generar una contraseña aleatoria"
  rm -rf "$TMPD"
  exit 1
fi

{
  printf '[client]\nuser=root\nhost=%s\nport=%s\n' "$HOST" "$PUERTO"
  [ -n "$SIBEA_ROOT_PASSWORD" ] && printf 'password=%s\n' "$SIBEA_ROOT_PASSWORD"
} > "$CNF_ROOT"
printf '[client]\nuser=sibea_otro\npassword=%s\nhost=%s\nport=%s\n' "$CLAVE" "$HOST" "$PUERTO" > "$CNF_OTRO"

como_root() { "$MYSQL" --defaults-extra-file="$(nativo "$CNF_ROOT")" --default-character-set=utf8mb4 --table "$@"; }
como_otro() { "$MYSQL" --defaults-extra-file="$(nativo "$CNF_OTRO")" --default-character-set=utf8mb4 --table "$@"; }

limpiar() {
  como_root --force < "$DIR/limpiar.sql"
  rm -rf "$TMPD"
}
trap limpiar EXIT
trap 'exit 130' INT TERM

# La contraseña va por la entrada estándar, nunca en la línea de comandos.
printf "CREATE OR REPLACE USER 'sibea_otro'@'localhost' IDENTIFIED BY '%s';\nCREATE OR REPLACE USER 'sibea_otro'@'127.0.0.1' IDENTIFIED BY '%s';\n" \
  "$CLAVE" "$CLAVE" | como_root || exit 1
como_root < "$DIR/preparar.sql" || exit 1

SALIDA=$(como_otro --force < "$DIR/prueba.sql" 2>&1)
printf '%s\n' "$SALIDA"
case "$SALIDA" in
  *"| CORRECTO:"*) exit 0 ;;
  *) exit 1 ;;
esac
