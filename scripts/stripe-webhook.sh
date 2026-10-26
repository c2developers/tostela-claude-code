#!/usr/bin/env bash
# Registra el webhook de esta tienda en tu cuenta de Stripe de prueba (ver scripts/stripe-webhook.php).
# No va en instalar.sh: escribe en tu cuenta de Stripe, así que se lanza a mano y solo si hace falta.
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose run --rm wpcli wp eval-file /proyecto/scripts/stripe-webhook.php
