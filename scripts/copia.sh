#!/usr/bin/env bash
# Copia de seguridad de la base de datos y de las fotos de la tienda.
#
#   scripts/copia.sh                  crea copias/AAAA-MM-DD_HHMMSS/ con la base de datos y las fotos
#   scripts/copia.sh --conservar 10   además, borra las copias antiguas y se queda con las 10 últimas
#
# Dentro van los pedidos, los datos de los clientes y las claves de Stripe: por eso copias/ no se sube a GitHub
# (está en .gitignore) y se crea solo para tu usuario. El .env no se copia: guárdalo aparte (gestor de contraseñas).
# Para volver atrás: scripts/restaurar.sh copias/AAAA-MM-DD_HHMMSS
set -euo pipefail
cd "$(dirname "$0")/.."

conservar=0
if [[ "${1:-}" == "--conservar" ]]; then
  conservar="${2:-}"
  [[ "$conservar" =~ ^[1-9][0-9]*$ ]] || { echo "Indica cuántas copias conservar: scripts/copia.sh --conservar 10"; exit 1; }
fi

docker compose ps --status running --services | grep -qx db || {
  echo "La base de datos no está en marcha. Arráncala con: docker compose up -d"
  exit 1
}

sello=$(date +%Y-%m-%d_%H%M%S)
destino="copias/$sello"
umask 077
mkdir -p "$destino"
# Si algo falla, no se deja una copia a medias.
trap 'echo "✗ La copia ha fallado; se descarta $destino"; rm -rf -- "$destino"' ERR

echo "→ Base de datos"
# La contraseña se lee dentro del contenedor: no pasa por la línea de órdenes ni se muestra.
docker compose exec -T db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb-dump -uroot --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 "$MARIADB_DATABASE"' \
  | gzip -9 > "$destino/base-de-datos.sql.gz"
gzip -t "$destino/base-de-datos.sql.gz"
gunzip -c "$destino/base-de-datos.sql.gz" | tail -n 1 | grep -q 'Dump completed' || { echo "El volcado está incompleto"; false; }

echo "→ Fotos (la mediateca de WordPress)"
docker compose exec -T wordpress tar -czf - -C /var/www/html/wp-content uploads > "$destino/fotos.tar.gz"
tar -tzf "$destino/fotos.tar.gz" > /dev/null

tablas=$(gunzip -c "$destino/base-de-datos.sql.gz" | grep -c '^CREATE TABLE')
ficheros=$(tar -tzf "$destino/fotos.tar.gz" | grep -vc '/$')
( cd "$destino" && sha256sum base-de-datos.sql.gz fotos.tar.gz > sumas.sha256 )
cat > "$destino/LEEME.txt" <<EOF
Copia de seguridad de Tostela
Hecha el: $(date '+%d/%m/%Y a las %H:%M')
Contiene: base-de-datos.sql.gz ($tablas tablas) y fotos.tar.gz ($ficheros ficheros de la mediateca)
Restaurar: scripts/restaurar.sh copias/$sello
Ojo: lleva pedidos, datos de clientes y las claves de Stripe. No la subas a ningún sitio público.
EOF
trap - ERR

if (( conservar > 0 )); then
  mapfile -t antiguas < <(ls -1d copias/????-??-??_?????? 2>/dev/null | sort | head -n "-$conservar")
  for antigua in "${antiguas[@]}"; do
    rm -rf -- "$antigua"
    echo "  (borrada la copia antigua $antigua)"
  done
fi

echo "✓ Copia hecha en $destino ($(du -sh "$destino" | cut -f1)): $tablas tablas y $ficheros ficheros de fotos"
