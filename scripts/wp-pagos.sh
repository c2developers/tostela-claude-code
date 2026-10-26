#!/usr/bin/env bash
# Instala el plugin oficial de Stripe para WooCommerce y lo deja en modo de prueba. Se ejecuta dentro del
# contenedor de WP-CLI (lo lanza scripts/instalar.sh) y se puede repetir.
set -euo pipefail

echo "→ Pagos: Stripe en modo de prueba"
wp plugin is-installed woocommerce-gateway-stripe || wp plugin install woocommerce-gateway-stripe --quiet
wp language plugin install woocommerce-gateway-stripe es_ES --quiet || true
wp plugin activate woocommerce-gateway-stripe --quiet
wp eval-file /proyecto/scripts/pagos.php
