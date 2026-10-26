#!/usr/bin/env bash
# Activa el tema Tostela (hijo de Twenty Twenty-Five) y deja la portada lista. Se ejecuta dentro del
# contenedor de WP-CLI (lo lanza scripts/instalar.sh) y se puede repetir.
set -euo pipefail

echo "→ Tema Tostela"
wp theme is-installed twentytwentyfive || wp theme install twentytwentyfive --quiet
wp theme activate tostela --quiet

# Fuera los temas que no se usan: solo quedan el hijo y su padre.
for tema in $(wp theme list --status=inactive --field=name); do
  [[ "$tema" == "twentytwentyfive" ]] || wp theme delete "$tema" --quiet
done

# La portada es una página «Inicio»; su aspecto lo da la plantilla front-page.html del tema.
inicio=$(wp post list --post_type=page --name=inicio --field=ID)
[[ -n "$inicio" ]] || inicio=$(wp post create --post_type=page --post_status=publish \
  --post_title="Inicio" --post_name=inicio --porcelain)
wp option update show_on_front page --quiet
wp option update page_on_front "$inicio" --quiet

wp rewrite flush --quiet
wp cache flush --quiet
echo "✓ Tema activado y portada configurada"
