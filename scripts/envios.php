<?php
/**
 * Zonas y tarifas de envío de Tostela. Se puede repetir: borra las zonas y las vuelve a crear.
 * Uso: wp eval-file /proyecto/scripts/envios.php
 *
 * Península: 4,90 € (gratis desde 35 €). Baleares: 7,90 €. Canarias, Ceuta y Melilla: sin envío.
 * Los 4,90 € y 7,90 € llevan el IVA del café (10 %) y WooCommerce guarda el coste sin IVA, así que se
 * introducen divididos entre 1,10. Con solo accesorios el IVA es del 21 %: 5,39 € y 8,69 €.
 */

const GRATIS_DESDE   = 35;
const PENINSULA_BRUTO = 4.90;
const BALEARES_BRUTO  = 7.90;

function sin_iva( float $bruto ): string {
	return (string) round( $bruto / 1.10, 6 );
}

function anadir_tarifa( WC_Shipping_Zone $zona, string $metodo, array $ajustes ): void {
	$instancia = $zona->add_shipping_method( $metodo );
	update_option( "woocommerce_{$metodo}_{$instancia}_settings", $ajustes );
}

// Fuera las zonas anteriores y las tarifas de «el resto del mundo».
foreach ( array_keys( WC_Shipping_Zones::get_zones() ) as $id ) {
	WC_Shipping_Zones::get_zone( $id )->delete();
}
$resto = new WC_Shipping_Zone( 0 );
foreach ( $resto->get_shipping_methods() as $metodo ) {
	$resto->delete_shipping_method( $metodo->get_instance_id() );
}

// Baleares.
$baleares = new WC_Shipping_Zone();
$baleares->set_zone_name( 'Baleares' );
$baleares->set_zone_order( 1 );
$baleares->add_location( 'ES:PM', 'state' );
$baleares->save();
anadir_tarifa(
	$baleares,
	'flat_rate',
	array(
		'title'      => 'Envío a Baleares (48/72 h)',
		'tax_status' => 'taxable',
		'cost'       => sin_iva( BALEARES_BRUTO ),
	)
);

// Península: todas las provincias de España menos Baleares (Canarias, Ceuta y Melilla no están en la lista).
$peninsula = new WC_Shipping_Zone();
$peninsula->set_zone_name( 'Península' );
$peninsula->set_zone_order( 2 );
foreach ( array_keys( WC()->countries->get_states( 'ES' ) ) as $provincia ) {
	if ( 'PM' !== $provincia ) {
		$peninsula->add_location( "ES:$provincia", 'state' );
	}
}
$peninsula->save();
anadir_tarifa(
	$peninsula,
	'flat_rate',
	array(
		'title'      => 'Envío estándar (24/48 h)',
		'tax_status' => 'taxable',
		'cost'       => sin_iva( PENINSULA_BRUTO ),
	)
);
anadir_tarifa(
	$peninsula,
	'free_shipping',
	array(
		'title'            => 'Envío gratis',
		'requires'         => 'min_amount',
		'min_amount'       => (string) GRATIS_DESDE,
		'ignore_discounts' => 'no',
	)
);

WC_Cache_Helper::get_transient_version( 'shipping', true );
echo 'Zonas de envío: ' . count( WC_Shipping_Zones::get_zones() ) . " (Baleares y Península)\n";
