<?php
/**
 * Crea (o actualiza) las páginas de contenido/paginas/ y deja enlazadas las legales en WooCommerce, en la
 * privacidad de WordPress y en la página de pago. Se puede repetir.
 * El pie de la web ya enlaza a todas (tema/tostela/parts/footer.html).
 * Uso: wp eval-file /proyecto/scripts/paginas.php
 */

const RUTA_PAGINAS = '/proyecto/contenido/paginas/';

// Como administrador: así WordPress no filtra el HTML de las páginas (estilos, formulario…).
$admin = get_user_by( 'login', 'tostela-gestion' );
wp_set_current_user( $admin->ID );

$paginas = array(
	'nuestra-historia'       => 'Nuestra historia',
	'contacto'               => 'Contacto',
	'envios-y-devoluciones'  => 'Envíos y devoluciones',
	'desistir'               => 'Desistir del contrato',
	'aviso-legal'            => 'Aviso legal',
	'condiciones-de-venta'   => 'Condiciones de venta',
	'politica-de-privacidad' => 'Política de privacidad',
	'politica-de-cookies'    => 'Política de cookies',
);

$ids   = array();
$orden = 0;
foreach ( $paginas as $slug => $titulo ) {
	$archivo = RUTA_PAGINAS . $slug . '.html';
	if ( ! is_readable( $archivo ) ) {
		WP_CLI::error( "No encuentro $archivo" );
	}
	$datos     = array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'post_title'     => $titulo,
		'post_name'      => $slug,
		'post_content'   => file_get_contents( $archivo ),
		'menu_order'     => $orden++,
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
	);
	$existente = get_page_by_path( $slug );
	if ( $existente ) {
		$datos['ID'] = $existente->ID;
	}
	$id = $existente ? wp_update_post( wp_slash( $datos ), true ) : wp_insert_post( wp_slash( $datos ), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( "$slug: " . $id->get_error_message() );
	}
	$ids[ $slug ] = $id;
}

// WooCommerce usa la página de devoluciones que crea al instalarse (un borrador): la nuestra la sustituye.
$borrador = get_page_by_path( 'reembolso_devoluciones' );
if ( $borrador && 'draft' === $borrador->post_status ) {
	wp_delete_post( $borrador->ID, true );
}
update_option( 'woocommerce_refund_returns_page_id', $ids['envios-y-devoluciones'] );

// Condiciones y privacidad: WooCommerce y WordPress las enlazan donde toca (el pago, el registro, el pie del pedido).
update_option( 'woocommerce_terms_page_id', $ids['condiciones-de-venta'] );
update_option( 'wp_page_for_privacy_policy', $ids['politica-de-privacidad'] );
update_option(
	'woocommerce_checkout_terms_and_conditions_checkbox_text',
	'He leído y acepto las [terms]. Tengo derecho a <a href="/desistir/" target="_blank" rel="noopener">desistir del contrato</a> durante 14 días (<a href="/envios-y-devoluciones/" target="_blank" rel="noopener">más información</a>).'
);
update_option( 'woocommerce_checkout_privacy_policy_text', 'Usaremos tus datos personales para gestionar tu pedido, como explica nuestra [privacy_policy].' );
update_option( 'woocommerce_registration_privacy_policy_text', 'Usaremos tus datos personales para crear tu cuenta, como explica nuestra [privacy_policy].' );

// La página de pago: hay que marcar las condiciones para comprar, y el botón dice que el pedido obliga a pagar.
$pago      = wc_get_page_id( 'checkout' );
$contenido = get_post_field( 'post_content', $pago );
$abrir      = 'target="_blank" rel="noopener"'; // En pestaña nueva, para no perder el pedido que se está rellenando.
$terminos   = wp_json_encode(
	array(
		'checkbox' => true,
		'text'     => "He leído y acepto las <a href=\"/condiciones-de-venta/\" $abrir>condiciones de venta</a> y la <a href=\"/politica-de-privacidad/\" $abrir>política de privacidad</a>. "
			. "Tengo derecho a <a href=\"/desistir/\" $abrir>desistir del contrato</a> durante 14 días (<a href=\"/envios-y-devoluciones/\" $abrir>más información</a>).",
	),
	JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
);
$nuevo     = preg_replace( '#<!-- wp:woocommerce/checkout-terms-block[^>]*-->#', '<!-- wp:woocommerce/checkout-terms-block ' . $terminos . ' -->', $contenido, 1 );
$nuevo     = preg_replace( '#<!-- wp:woocommerce/checkout-actions-block[^>]*-->#', '<!-- wp:woocommerce/checkout-actions-block {"placeOrderButtonLabel":"Realizar pedido con obligación de pago"} -->', $nuevo, 1 );
if ( $nuevo !== $contenido ) {
	wp_update_post( wp_slash( array( 'ID' => $pago, 'post_content' => $nuevo ) ) );
}

wp_cache_flush();
printf( "Páginas: %d creadas o actualizadas; condiciones, privacidad y devoluciones enlazadas en WooCommerce y en el pago.\n", count( $ids ) );
