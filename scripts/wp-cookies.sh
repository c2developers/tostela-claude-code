#!/usr/bin/env bash
# Instala CookieYes y deja el aviso de cookies en español. Se ejecuta dentro del contenedor de WP-CLI
# (lo lanza scripts/instalar.sh) y se puede repetir.
set -euo pipefail

echo "→ Cookies: CookieYes en español"
wp plugin is-installed cookie-law-info || wp plugin install cookie-law-info --quiet
wp language plugin install cookie-law-info es_ES --quiet || true
wp plugin activate cookie-law-info --quiet
wp eval-file /proyecto/scripts/cookies.php
