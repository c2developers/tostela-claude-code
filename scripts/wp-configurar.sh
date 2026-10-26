#!/usr/bin/env bash
# Configura WordPress y WooCommerce para España. Se ejecuta dentro del contenedor de WP-CLI
# (lo lanza scripts/instalar.sh) y se puede repetir: lo que ya está hecho se deja igual.
set -euo pipefail

URL="https://tostela.c2developers.com"
TITULO="Tostela"
LEMA="Café de especialidad tostado cada martes"
ADMIN_USUARIO="tostela-gestion"
ADMIN_CORREO="video@c2developers.com"

# Dirección de la tienda (inventada, como la marca).
TIENDA_DIRECCION="Calle del Tostadero, 12"
TIENDA_CIUDAD="Madrid"
TIENDA_CP="28012"
TIENDA_PROVINCIA="ES:M"

opcion() { wp option update "$@" --quiet; }
woo() { command wp wc "$@" --user="$ADMIN_USUARIO"; }

echo "→ Esperando a WordPress…"
until [[ -f wp-config.php ]]; do sleep 2; done

echo "→ WordPress"
if ! wp core is-installed 2>/dev/null; then
  wp core install --url="$URL" --title="$TITULO" --admin_user="$ADMIN_USUARIO" \
    --admin_email="$ADMIN_CORREO" --admin_password="$WP_ADMIN_PASSWORD" --locale=es_ES --skip-email
  # Fuera el contenido de ejemplo (entrada, página y borrador de privacidad).
  wp post delete $(wp post list --post_type=post,page --format=ids) --force --quiet
fi
wp language core install es_ES --activate --quiet
opcion blogname "$TITULO"
opcion blogdescription "$LEMA"
opcion admin_email "$ADMIN_CORREO"
opcion timezone_string Europe/Madrid
opcion date_format "j \d\e F \d\e Y"
opcion time_format "H:i"
opcion start_of_week 1
opcion default_comment_status closed
# Los buscadores no indexan la tienda hasta que esté terminada: al publicar, esto se pone en 1.
opcion blog_public 0
wp rewrite structure "/%postname%/" --quiet
wp plugin delete hello akismet --quiet 2>/dev/null || true
wp theme delete twentytwentythree twentytwentyfour --quiet 2>/dev/null || true

echo "→ WooCommerce"
# La traducción va antes de activarlo, para que cree sus páginas en español.
wp plugin is-installed woocommerce || wp plugin install woocommerce --quiet
wp language plugin install woocommerce es_ES --quiet
wp plugin activate woocommerce --quiet
woo tool run install_pages --quiet

# Sin asistente, sugerencias ni envío de datos de uso.
opcion woocommerce_onboarding_profile '{"skipped":true}' --format=json
opcion woocommerce_task_list_hidden_lists '["setup","extended"]' --format=json
opcion woocommerce_show_marketplace_suggestions no
opcion woocommerce_allow_tracking no
# Sin «atribución de pedidos»: guarda cookies de seguimiento (sbjs_*) en todas las páginas antes de que el visitante
# elija nada en el aviso de cookies. La analítica ya la hace Google Analytics, y solo con consentimiento.
opcion woocommerce_feature_order_attribution_enabled no

# Modo «próximamente» hasta que la tienda esté lista: quien no ha entrado como administrador ve un aviso.
opcion woocommerce_coming_soon yes
opcion woocommerce_store_pages_only no

echo "→ Tienda: dirección, venta solo en España y euros"
opcion woocommerce_store_address "$TIENDA_DIRECCION"
opcion woocommerce_store_address_2 ""
opcion woocommerce_store_city "$TIENDA_CIUDAD"
opcion woocommerce_store_postcode "$TIENDA_CP"
opcion woocommerce_default_country "$TIENDA_PROVINCIA"
opcion woocommerce_allowed_countries specific
opcion woocommerce_specific_allowed_countries '["ES"]' --format=json
opcion woocommerce_ship_to_countries ""
opcion woocommerce_default_customer_address base
opcion woocommerce_currency EUR
opcion woocommerce_currency_pos right_space
opcion woocommerce_price_thousand_sep "."
opcion woocommerce_price_decimal_sep ","
opcion woocommerce_price_num_decimals 2
opcion woocommerce_weight_unit kg
opcion woocommerce_dimension_unit cm

echo "→ IVA: 21 % general (accesorios) y 10 % reducido (café)"
opcion woocommerce_calc_taxes yes
opcion woocommerce_prices_include_tax yes
opcion woocommerce_tax_based_on shipping
opcion woocommerce_shipping_tax_class inherit
opcion woocommerce_tax_round_at_subtotal no
opcion woocommerce_tax_display_shop incl
opcion woocommerce_tax_display_cart incl
opcion woocommerce_tax_total_display itemized
opcion woocommerce_price_display_suffix "IVA incluido"

# Clases: solo la estándar y «IVA reducido». Los tipos se rehacen cada vez.
for clase in $(woo tax_class list --field=slug); do
  [[ "$clase" == "standard" ]] || woo tax_class delete "$clase" --force=true --quiet
done
# WooCommerce avisa de un «id» que las clases no tienen: es inofensivo, así que se silencia.
woo tax_class create --name="IVA reducido" >/dev/null 2>&1
for id in $(woo tax list --field=id); do
  woo tax delete "$id" --force=true --quiet
done
woo tax create --country=ES --rate=21 --name="IVA 21 %" --shipping=true --priority=1 --quiet
woo tax create --country=ES --rate=10 --name="IVA 10 %" --shipping=true --priority=1 --class=iva-reducido --quiet

wp cache flush --quiet
echo "✓ WordPress y WooCommerce configurados"
