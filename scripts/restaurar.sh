#!/usr/bin/env bash
# Restaura una copia hecha con scripts/copia.sh: sustituye la base de datos y las fotos de ahora por las de la copia.
#
#   scripts/restaurar.sh copias/AAAA-MM-DD_HHMMSS
#
# Las copias no llevan los plugins ni el tema: en una máquina nueva, primero scripts/instalar.sh y después esto.
set -euo pipefail
cd "$(dirname "$0")/.."

origen="${1:-}"
origen="${origen%/}"
[[ -n "$origen" ]] || { echo "Uso: scripts/restaurar.sh copias/AAAA-MM-DD_HHMMSS"; exit 1; }
[[ -f "$origen/base-de-datos.sql.gz" && -f "$origen/fotos.tar.gz" ]] || { echo "No encuentro la copia en $origen"; exit 1; }
( cd "$origen" && sha256sum -c --quiet sumas.sha256 ) || { echo "La copia está dañada: no se restaura nada."; exit 1; }

echo "Se va a SUSTITUIR la base de datos y las fotos de ahora por las de $origen."
read -r -p "¿Seguro? (escribe «sí»): " respuesta
[[ "$respuesta" == "sí" ]] || { echo "Cancelado."; exit 1; }

docker compose up -d db wordpress

echo "→ Base de datos"
docker compose run --rm wpcli wp db clean --yes
gunzip -c "$origen/base-de-datos.sql.gz" | docker compose exec -T db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot "$MARIADB_DATABASE"'

echo "→ Fotos"
docker compose exec -T wordpress rm -rf /var/www/html/wp-content/uploads
docker compose exec -T wordpress tar -xzf - -C /var/www/html/wp-content < "$origen/fotos.tar.gz"
docker compose exec -T wordpress chown -R www-data:www-data /var/www/html/wp-content/uploads

docker compose run --rm wpcli wp cache flush > /dev/null
echo "✓ Copia restaurada desde $origen"
