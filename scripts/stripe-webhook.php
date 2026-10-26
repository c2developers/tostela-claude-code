<?php
/**
 * Registra en la cuenta de Stripe (en modo de prueba) el webhook de esta tienda, con los eventos que usa el plugin,
 * y guarda su secreto en los ajustes. Sin él, los pedidos pagados se quedan «pendientes».
 *
 * No toca los webhooks de otras tiendas: solo borra endpoints creados por este plugin que apunten exactamente a la URL
 * de esta tienda (nunca a otras) y, si ya existe uno nuestro con el secreto guardado, no hace nada.
 *
 * Uso: wp eval-file /proyecto/scripts/stripe-webhook.php
 */

if ( 'yes' !== ( get_option( 'woocommerce_stripe_settings' )['testmode'] ?? '' ) ) {
	WP_CLI::error( 'Stripe no está en modo de prueba: este script solo registra el webhook de prueba.' );
}

/** Los endpoints de la cuenta, resumidos (sin secretos). */
$instantanea = function () {
	$r = WC_Stripe_API::request( array( 'limit' => 100 ), 'webhook_endpoints', 'GET' );
	if ( isset( $r->error ) ) {
		WP_CLI::error( 'Stripe: ' . ( $r->error->message ?? 'error desconocido' ) );
	}
	$resumen = array();
	foreach ( $r->data as $e ) {
		$resumen[ $e->id ] = array(
			'url'      => $e->url,
			'estado'   => $e->status,
			'eventos'  => count( $e->enabled_events ),
			'metadata' => json_encode( $e->metadata ),
		);
	}
	return $resumen;
};

$url     = WC_Stripe_Helper::get_webhook_url();
$ajustes = WC_Stripe_Helper::get_stripe_settings();
$antes   = $instantanea();

$nuestros = array_filter(
	$antes,
	function ( $e ) use ( $url ) {
		return $e['url'] === $url;
	}
);
if ( $nuestros ) {
	$id_guardado = $ajustes['test_webhook_data']['id'] ?? '';
	if ( $id_guardado && isset( $nuestros[ $id_guardado ] ) && ! empty( $ajustes['test_webhook_secret'] ) ) {
		WP_CLI::success( 'Ya está registrado y con su secreto guardado: no hago nada.' );
		return;
	}
	// El secreto de un endpoint solo se ve al crearlo: sin él guardado no se puede reutilizar. Se avisa en vez de borrar.
	WP_CLI::error( 'Ya hay un webhook en Stripe para ' . $url . ' pero esta tienda no tiene su secreto. Bórralo en el panel de Stripe (Desarrolladores > Webhooks) y vuelve a ejecutar esto.' );
}

WC_Stripe::get_instance()->account->configure_webhooks( 'test' );

$despues = $instantanea();
$nuevos  = array_diff_key( $despues, $antes );
$perdidos = array_diff_key( $antes, $despues );
$cambiados = array_filter(
	array_intersect_key( $antes, $despues ),
	function ( $e, $id ) use ( $despues ) {
		return $e !== $despues[ $id ];
	},
	ARRAY_FILTER_USE_BOTH
);

echo 'Webhooks en la cuenta antes: ' . count( $antes ) . ' | después: ' . count( $despues ) . "\n";
foreach ( $nuevos as $id => $e ) {
	echo '  + nuevo  ' . substr( $id, 0, 8 ) . '… ' . $e['url'] . ' (' . $e['estado'] . ', ' . $e['eventos'] . " eventos)\n";
}
foreach ( array_intersect_key( $antes, $despues ) as $id => $e ) {
	echo '  = igual  ' . substr( $id, 0, 8 ) . '… ' . $e['url'] . ' (' . $e['estado'] . ', ' . $e['eventos'] . ' eventos)' . ( isset( $cambiados[ $id ] ) ? '  ¡HA CAMBIADO!' : '' ) . "\n";
}
foreach ( $perdidos as $id => $e ) {
	echo '  - BORRADO ' . substr( $id, 0, 8 ) . '… ' . $e['url'] . "  ¡NO DEBERÍA!\n";
}
$guardado = WC_Stripe_Helper::get_stripe_settings();
echo 'Esta tienda guarda ahora el webhook de: ' . ( $guardado['test_webhook_data']['url'] ?? '(ninguno)' ) . ' | secreto guardado: ' . ( ! empty( $guardado['test_webhook_secret'] ) ? 'sí' : 'NO' ) . "\n";
