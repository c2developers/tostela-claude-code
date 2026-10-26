#!/usr/bin/env bash
# Instala y configura WordPress y WooCommerce para España.
#
#   scripts/instalar.sh               instala o deja al día lo que ya hay (se puede repetir)
#   scripts/instalar.sh --desde-cero  borra la base de datos y WordPress, y vuelve a empezar
#
# La configuración la hace scripts/wp-configurar.sh dentro del contenedor de WP-CLI.
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ "${1:-}" == "--desde-cero" ]]; then
  read -r -p "Esto borra la base de datos y todo WordPress de Tostela. ¿Seguro? (escribe «sí»): " respuesta
  [[ "$respuesta" == "sí" ]] || { echo "Cancelado."; exit 1; }
  docker compose down --volumes
fi

# Sin túnel mientras se instala: un WordPress a medio instalar no debe verse desde fuera.
docker compose stop cloudflared 2>/dev/null || true
docker compose up -d db wordpress

docker compose run --rm wpcli bash /proyecto/scripts/wp-configurar.sh
docker compose run --rm wpcli bash /proyecto/scripts/wp-tema.sh

docker compose up -d cloudflared
echo "Listo: https://tostela.c2developers.com (en local, http://127.0.0.1:8086)"
