<?php
/**
 * Configura el aviso de cookies de CookieYes en español, con «Rechazar» igual de visible que «Aceptar», las
 * categorías y la lista de cookies de la tienda. Se puede repetir: sustituye lo que había.
 * La carga de Google Analytics y del píxel de Meta, con Consent Mode v2, está en mu-plugins/tostela-cookies.php.
 * Uso: wp eval-file /proyecto/scripts/cookies.php
 */

use CookieYes\Lite\Admin\Modules\Banners\Includes\Banner;
use CookieYes\Lite\Admin\Modules\Banners\Includes\Controller as Controlador_Banners;
use CookieYes\Lite\Admin\Modules\Cookies\Includes\Cookie;
use CookieYes\Lite\Admin\Modules\Cookies\Includes\Cookie_Controller;

global $wpdb;

const COOKIES_DOMINIO = 'tostela.c2developers.com';

// Colores de la tienda.
const TERRACOTA = '#A84A28';
const ESPRESSO  = '#2A1B14';
const LINO      = '#FFFDF9';
const LINEA     = '#E6D9C3';

/** Idioma de CookieYes: solo español. */
$ajustes                          = get_option( 'cky_settings' );
$ajustes['languages']             = array( 'selected' => array( 'es' ), 'default' => 'es' );
update_option( 'cky_settings', $ajustes );

/** Categorías: solo se enseñan las que usa la tienda. */
$categorias = array(
	'necessary'     => array( 'Necesarias', 'Hacen que la tienda funcione: guardan tu carrito y tu sesión de compra y recuerdan lo que eliges en este aviso. Sin ellas no se puede comprar, por eso siempre están activas.', true ),
	'functional'    => array( 'Funcionales', 'No usamos cookies funcionales.', false ),
	'analytics'     => array( 'Analítica', 'Nos ayudan a saber cuántas personas visitan la tienda y qué páginas miran (Google Analytics). Solo se activan si las aceptas.', true ),
	'performance'   => array( 'Rendimiento', 'No usamos cookies de rendimiento.', false ),
	'advertisement' => array( 'Publicidad', 'Sirven para medir la eficacia de nuestros anuncios (píxel de Meta). Solo se activan si las aceptas.', true ),
);
$id_categoria = array();
foreach ( $categorias as $slug => $datos ) {
	$wpdb->update(
		$wpdb->prefix . 'cky_cookie_categories',
		array(
			'name'        => wp_json_encode( array( 'es' => $datos[0] ), JSON_UNESCAPED_UNICODE ),
			'description' => wp_json_encode( array( 'es' => '<p>' . $datos[1] . '</p>' ), JSON_UNESCAPED_UNICODE ),
			'visibility'  => $datos[2] ? 1 : 0,
		),
		array( 'slug' => $slug )
	);
	$id_categoria[ $slug ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT category_id FROM {$wpdb->prefix}cky_cookie_categories WHERE slug = %s", $slug ) );
}

/** Cookies de la tienda (las mismas que explica la página de política de cookies). */
$wpdb->query( "DELETE FROM {$wpdb->prefix}cky_cookies" );
$cookies = array(
	array( 'cookieyes-consent', 'cookieyes-consent', 'necessary', 'Recuerda lo que has elegido en el aviso de cookies.', '1 año', '' ),
	array( 'wp_woocommerce_session_*', 'wp-woocommerce-session', 'necessary', 'Guarda tu carrito y tu sesión de compra.', '2 días', '' ),
	array( 'woocommerce_cart_hash', 'woocommerce-cart-hash', 'necessary', 'Ayuda a WooCommerce a saber cuándo cambia el contenido del carrito.', 'Sesión', '' ),
	array( 'woocommerce_items_in_cart', 'woocommerce-items-in-cart', 'necessary', 'Indica si hay productos en el carrito.', 'Sesión', '' ),
	// Stripe (el procesador de pagos) las guarda en el carrito y al pagar, para prevenir el fraude.
	array( '__stripe_mid', 'stripe-mid', 'necessary', 'Stripe, nuestro procesador de pagos, la usa para prevenir el fraude. Solo se guarda en el carrito y al pagar.', '1 año', '' ),
	array( '__stripe_sid', 'stripe-sid', 'necessary', 'Stripe, nuestro procesador de pagos, la usa para prevenir el fraude. Solo se guarda en el carrito y al pagar.', '30 minutos', '' ),
	array( 'm', 'stripe-m', 'necessary', 'Stripe (m.stripe.com) la usa para prevenir el fraude en los pagos. Solo se guarda en el carrito y al pagar.', '2 años', '' ),
	array( '__cf_bm', 'cf-bm', 'necessary', 'hCaptcha, que usa Stripe para distinguir personas de robots al pagar. Solo se guarda al pagar.', '30 minutos', '' ),
	array( '_ga', 'ga', 'analytics', 'Google Analytics la usa para distinguir a los visitantes y contar las visitas.', '2 años', 'googletagmanager.com|google-analytics.com|analytics.google.com' ),
	// Con su nombre exacto (lleva el identificador de medición) para que CookieYes la borre si se retira el consentimiento.
	array( '_ga_7W4E0KE2BV', 'ga-id', 'analytics', 'Google Analytics guarda con ella el estado de la sesión.', '2 años', 'googletagmanager.com|google-analytics.com|analytics.google.com' ),
	array( '_fbp', 'fbp', 'advertisement', 'El píxel de Meta la usa para medir la eficacia de los anuncios.', '3 meses', 'connect.facebook.net|facebook.com/tr' ),
);
foreach ( $cookies as $datos ) {
	$cookie = new Cookie();
	$cookie->set_name( $datos[0] );
	$cookie->set_slug( $datos[1] );
	$cookie->set_description( array( 'es' => $datos[3] ) );
	$cookie->set_duration( array( 'es' => $datos[4] ) );
	$cookie->set_domain( COOKIES_DOMINIO );
	$cookie->set_category( $id_categoria[ $datos[2] ] );
	$cookie->set_type( 0 );
	$cookie->set_discovered( false );
	$cookie->set_url_pattern( $datos[5] );
	$cookie->set_meta( array() );
	Cookie_Controller::get_instance()->create_item( $cookie );
}

/** El banner (RGPD). */
$banner    = new Banner( 1 );
$settings  = $banner->get_settings();
$settings['settings']['languages'] = array( 'default' => 'es', 'selected' => array( 'es' ) );
$settings['settings']['type']      = 'box';
$settings['settings']['position']  = 'bottom-left';
$settings['settings']['theme']     = 'light';

// Colores de la marca en todo el banner: el azul por defecto pasa a terracota y los textos a espresso.
$cambios = array(
	'#1863DC' => TERRACOTA,
	'#0056A7' => TERRACOTA,
	'#212121' => ESPRESSO,
	'#F4F4F4' => LINEA,
	'#008000' => '#5E6E53',
);
$recolorear = function ( $valor ) use ( &$recolorear, $cambios ) {
	if ( is_array( $valor ) ) {
		return array_map( $recolorear, $valor );
	}
	return is_string( $valor ) && isset( $cambios[ strtoupper( $valor ) ] ) ? $cambios[ strtoupper( $valor ) ] : $valor;
};
$settings['config'] = $recolorear( $settings['config'] );

$settings['config']['notice']['styles']['background-color']                = LINO;
$settings['config']['preferenceCenter']['styles']['background-color']      = LINO;
$settings['config']['preferenceCenter']['styles']['color']                 = ESPRESSO;

// «Aceptar» y «Rechazar» exactamente con el mismo estilo (mismo color, tamaño y peso), en el aviso y en el centro de preferencias.
$boton_principal = array(
	'color'            => LINO,
	'background-color' => TERRACOTA,
	'border-color'     => TERRACOTA,
);
// (El mismo ancho para los tres botones del aviso está en el tema: tema/tostela/theme.json. CookieYes solo guarda colores.)
foreach ( array( 'accept', 'reject' ) as $boton ) {
	$settings['config']['notice']['elements']['buttons']['elements'][ $boton ]['styles']       = $boton_principal;
	$settings['config']['preferenceCenter']['elements']['buttons']['elements'][ $boton ]['styles'] = $boton_principal;
}
$settings['config']['preferenceCenter']['elements']['buttons']['elements']['save']['styles'] = $boton_principal;
$settings['config']['categoryPreview']['elements']['buttons']['elements']['save']['styles']  = $boton_principal;
$settings['config']['notice']['elements']['buttons']['elements']['settings']['styles']       = array(
	'color'            => TERRACOTA,
	'background-color' => 'transparent',
	'border-color'     => TERRACOTA,
);

// Sin aceptar por desplazarse, por navegar ni por esperar: solo vale un clic en un botón.
$settings['behaviours']['respectGPC']['status'] = true;
foreach ( array( 'idle', 'navigation', 'pageScroll' ) as $accion ) {
	unset( $settings['behaviours']['legacyFunctions'][ $accion ] );
}

$contenidos = array(
	'es' => array(
		'notice'          => array(
			'elements' => array(
				'title'       => 'Cookies en Tostela',
				'description' => '<p>Usamos cookies propias para que la tienda funcione y, solo si nos lo permites, cookies de Google Analytics para medir las visitas y del píxel de Meta para medir los anuncios. Puedes aceptarlas, rechazarlas o elegir cuáles. Más información en la <a href="/politica-de-cookies/">política de cookies</a>.</p>',
				'privacyLink' => '',
				'buttons'     => array(
					'elements' => array(
						'accept'    => 'Aceptar',
						'reject'    => 'Rechazar',
						'settings'  => 'Personalizar',
						'readMore'  => 'Política de cookies',
						'donotSell' => 'No vender ni compartir mi información personal',
					),
				),
				'closeButton' => 'Cerrar',
			),
		),
		'categoryPreview' => array(
			'elements' => array(
				'buttons' => array( 'elements' => array( 'save' => 'Guardar preferencias' ) ),
			),
		),
		'preferenceCenter' => array(
			'elements' => array(
				'title'       => 'Preferencias de cookies',
				'description' => '<p>Elige qué cookies permites. Las necesarias siempre están activas porque sin ellas la tienda no funciona (el carrito y la sesión). Las de analítica y publicidad solo se activan si las aceptas, y puedes cambiarlo cuando quieras desde el botón de cookies de la esquina.</p>',
				'showMore'    => 'Mostrar más',
				'showLess'    => 'Mostrar menos',
				'category'    => array(
					'elements' => array(
						'alwaysEnabled' => 'Siempre activas',
						'enable'        => 'Permitir',
						'disable'       => 'Desactivar',
					),
				),
				'buttons'     => array(
					'elements' => array(
						'accept' => 'Aceptar todas',
						'save'   => 'Guardar preferencias',
						'reject' => 'Rechazar todas',
					),
				),
				'closeButton' => 'Cerrar',
			),
		),
		'optoutPopup'     => $banner->get_contents()['en']['optoutPopup'] ?? array(),
		'revisitConsent'  => array( 'elements' => array( 'title' => 'Preferencias de cookies' ) ),
		'auditTable'      => array(
			'elements' => array(
				'headers' => array( 'elements' => array( 'id' => 'Cookie', 'duration' => 'Duración', 'description' => 'Descripción' ) ),
				'message' => 'No hay cookies que mostrar.',
			),
		),
		'videoPlaceholder' => $banner->get_contents()['en']['videoPlaceholder'] ?? array(),
	),
);

$banner->set_settings( $settings );
$banner->set_contents( $contenidos );
Controlador_Banners::get_instance()->update_item( $banner );

delete_option( 'cky_banner_template' );
wp_cache_flush();

printf(
	"Cookies: banner en español (%s), %d cookies en %d categorías visibles. Consent Mode v2 y etiquetas: mu-plugins/tostela-cookies.php.\n",
	$banner->get_type(),
	count( $cookies ),
	count( array_filter( $categorias, function ( $c ) { return $c[2]; } ) )
);
