#!/usr/bin/env bash
# Vuelve a cargar los envíos y el catálogo (contenido/productos.json) sin reinstalar nada.
# Se puede repetir: lo que ya existe se actualiza, no se duplica.
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose run --rm wpcli wp eval-file /proyecto/scripts/envios.php
docker compose run --rm wpcli wp eval-file /proyecto/scripts/catalogo.php
