#!/usr/bin/env bash
# Manda correos de prueba por Resend y comprueba que salen. Uso: scripts/probar-correo.sh [destinatario]
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose run --rm wpcli wp eval-file /proyecto/scripts/probar-correo.php "$@"
