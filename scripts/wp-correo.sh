#!/usr/bin/env bash
# Instala WP Mail SMTP y deja los correos de WooCommerce saliendo por Resend. Se ejecuta dentro del contenedor
# de WP-CLI (lo lanza scripts/instalar.sh) y se puede repetir.
set -euo pipefail

echo "→ Correo: WP Mail SMTP con Resend"
wp plugin is-installed wp-mail-smtp || wp plugin install wp-mail-smtp --quiet
wp language plugin install wp-mail-smtp es_ES --quiet || true
wp plugin activate wp-mail-smtp --quiet
wp eval-file /proyecto/scripts/correo.php
