<?php
/**
 * Deja Stripe en modo de prueba, solo con tarjeta. No toca las claves: la conexión con la cuenta de Stripe
 * se hace a mano desde WooCommerce > Ajustes > Pagos > Stripe. Se puede repetir.
 * Uso: wp eval-file /proyecto/scripts/pagos.php
 */

$ajustes = get_option( 'woocommerce_stripe_settings', array() );
$ajustes = is_array( $ajustes ) ? $ajustes : array();

$ajustes = array_merge(
	$ajustes,
	array(
		'enabled'                                   => 'yes',
		'testmode'                                  => 'yes',
		'title'                                     => 'Tarjeta de crédito o débito',
		'capture'                                   => 'yes',
		'statement_descriptor'                      => 'TOSTELA',
		'short_statement_descriptor'                => 'TOSTELA',
		'upe_checkout_experience_accepted_payments' => array( 'card' ),
		// Sin Link ni Amazon Pay, tampoco como botones de pago rápido (el plugin entiende "" como «en ningún sitio»).
		'link_button_locations'                     => '',
		'amazon_pay_button_locations'               => '',
		// Sin botones de pago rápido (Apple Pay y Google Pay): cargaban Stripe.js, y con él las cookies de Stripe, en la
		// ficha de producto y en el carrito. Así Stripe solo entra donde se paga: en «Finalizar compra».
		'express_checkout'                          => 'no',
		// Los métodos de pago los decide esta lista (solo tarjeta) y no el panel de Stripe, donde hay Apple Pay, Google
		// Pay, Link, Amazon Pay y más activados. Y el pago clásico con tarjeta, sin el «checkout optimizado» de Stripe
		// (que deja el pedido pendiente hasta que llega el webhook).
		'pmc_enabled'                               => 'no',
		'optimized_checkout_element'                => 'no',
		'adaptive_pricing'                          => 'no',
	)
);
update_option( 'woocommerce_stripe_settings', $ajustes );

// Sin transferencia, cheque ni contrareembolso: solo se paga con tarjeta.
foreach ( array( 'bacs', 'cheque', 'cod' ) as $pasarela ) {
	$otra = get_option( "woocommerce_{$pasarela}_settings", array() );
	update_option( "woocommerce_{$pasarela}_settings", array_merge( is_array( $otra ) ? $otra : array(), array( 'enabled' => 'no' ) ) );
}

// Solo se dice si hay claves de prueba, nunca cuáles son.
$conectado = ! empty( $ajustes['test_publishable_key'] ) && ! empty( $ajustes['test_secret_key'] );
echo 'Stripe en modo de prueba: sí. Cuenta conectada: ' . ( $conectado ? 'sí' : 'no (falta conectarla)' ) . "\n";
