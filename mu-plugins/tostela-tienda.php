<?php
/**
 * Plugin Name: Tostela: reglas de la tienda
 * Description: Solo se envía a la península y Baleares, el envío gratis sustituye a la tarifa normal y el IVA del envío se reparte según lo que lleva el carrito.
 */

defined( 'ABSPATH' ) || exit;

// Canarias (Las Palmas y Santa Cruz de Tenerife), Ceuta y Melilla: allí no se aplica el IVA, sino el IGIC o el IPSI.
const TOSTELA_PROVINCIAS_SIN_ENVIO = array( 'GC', 'TF', 'CE', 'ML' );
const TOSTELA_CP_SIN_ENVIO         = array( '35', '38', '51', '52' );

/**
 * ¿La petición viene de este equipo (localhost:8086) y no de internet? Las que llegan por el túnel de
 * Cloudflare siempre traen las cabeceras Cf-Ray y Cf-Connecting-Ip, y además el túnel solo acepta el dominio.
 */
function tostela_es_local() {
	$host = $_SERVER['HTTP_HOST'] ?? '';
	return in_array( $host, array( 'localhost:8086', '127.0.0.1:8086' ), true )
		&& empty( $_SERVER['HTTP_CF_RAY'] )
		&& empty( $_SERVER['HTTP_CF_CONNECTING_IP'] );
}

// Mientras la tienda está en «próximamente», en local se ve entera: así se pueden hacer las compras de prueba.
add_filter( 'woocommerce_coming_soon_exclude', 'tostela_es_local' );

/** Quita esas provincias del desplegable de la dirección. */
add_filter(
	'woocommerce_states',
	function ( $estados ) {
		foreach ( TOSTELA_PROVINCIAS_SIN_ENVIO as $codigo ) {
			unset( $estados['ES'][ $codigo ] );
		}
		return $estados;
	}
);

/**
 * Stripe.js (y con él las cookies de Stripe: __stripe_mid, __stripe_sid, m…) solo se carga donde se paga, en
 * «Finalizar compra». Sin esto, el plugin lo carga también en la ficha de producto y en el carrito.
 */
add_filter( 'wc_stripe_load_scripts_on_product_page_when_prbs_disabled', '__return_false' );
add_filter( 'wc_stripe_load_scripts_on_cart_page_when_prbs_disabled', '__return_false' );

/** La plantilla de la ficha de producto de WooCommerce trae «Category: » sin traducir. */
add_filter(
	'render_block_data',
	function ( $bloque ) {
		if ( 'core/post-terms' === ( $bloque['blockName'] ?? '' ) && isset( $bloque['attrs']['prefix'] ) ) {
			$bloque['attrs']['prefix'] = strtr(
				$bloque['attrs']['prefix'],
				array(
					'Category: ' => 'Categoría: ',
					'Categories: ' => 'Categorías: ',
					'Tag: '      => 'Etiqueta: ',
					'Tags: '     => 'Etiquetas: ',
				)
			);
		}
		return $bloque;
	}
);

/** Un código postal de Canarias, Ceuta o Melilla no puede comprar aunque se elija otra provincia. */
function tostela_cp_sin_envio( $codigo_postal ) {
	$prefijo = substr( preg_replace( '/\D/', '', (string) $codigo_postal ), 0, 2 );
	return in_array( $prefijo, TOSTELA_CP_SIN_ENVIO, true );
}

const TOSTELA_AVISO_SIN_ENVIO = 'De momento no enviamos a Canarias, Ceuta ni Melilla.';

// Pago con el bloque de «Finalizar compra».
add_action(
	'woocommerce_store_api_cart_errors',
	function ( $errores, $carrito ) {
		$cliente = $carrito->get_customer();
		if ( $cliente && tostela_cp_sin_envio( $cliente->get_shipping_postcode() ) ) {
			$errores->add( 'tostela_sin_envio', TOSTELA_AVISO_SIN_ENVIO );
		}
	},
	10,
	2
);

// Pago clásico (shortcode), por si alguna vez se usa.
add_action(
	'woocommerce_after_checkout_validation',
	function ( $datos, $errores ) {
		$cp = ! empty( $datos['ship_to_different_address'] ) ? ( $datos['shipping_postcode'] ?? '' ) : ( $datos['billing_postcode'] ?? '' );
		if ( tostela_cp_sin_envio( $cp ) ) {
			$errores->add( 'tostela_sin_envio', TOSTELA_AVISO_SIN_ENVIO );
		}
	},
	10,
	2
);

/**
 * Tarifas de envío:
 * 1. Si hay envío gratis, es la única opción.
 * 2. El coste se introduce sin IVA y el IVA se reparte entre los tipos del carrito, en proporción al valor
 *    de cada uno: con café y accesorios, el envío lleva un poco de 10 % y un poco de 21 %.
 */
add_filter(
	'woocommerce_package_rates',
	function ( $tarifas, $paquete ) {
		$gratis = array_filter(
			$tarifas,
			function ( $tarifa ) {
				return 'free_shipping' === $tarifa->get_method_id();
			}
		);
		if ( $gratis ) {
			return $gratis;
		}

		$valor_por_clase = array();
		foreach ( $paquete['contents'] as $linea ) {
			$producto = $linea['data'];
			$clase    = $producto->get_tax_class();
			$valor    = (float) wc_get_price_excluding_tax( $producto, array( 'qty' => $linea['quantity'] ) );
			$valor_por_clase[ $clase ] = ( $valor_por_clase[ $clase ] ?? 0 ) + $valor;
		}
		$total = array_sum( $valor_por_clase );
		if ( count( $valor_por_clase ) < 2 || $total <= 0 ) {
			return $tarifas; // Un solo tipo de IVA: WooCommerce ya lo hace bien.
		}

		$ubicacion = WC_Tax::get_tax_location();
		if ( 4 !== count( $ubicacion ) ) {
			return $tarifas;
		}
		list( $pais, $provincia, $cp, $ciudad ) = $ubicacion;

		foreach ( $tarifas as $tarifa ) {
			$coste = (float) $tarifa->get_cost();
			if ( $coste <= 0 || ! $tarifa->get_taxes() ) {
				continue;
			}
			$impuestos = array();
			foreach ( $valor_por_clase as $clase => $valor ) {
				$tipos = WC_Tax::find_shipping_rates(
					array(
						'country'   => $pais,
						'state'     => $provincia,
						'postcode'  => $cp,
						'city'      => $ciudad,
						'tax_class' => $clase,
					)
				);
				foreach ( WC_Tax::calc_shipping_tax( $coste * $valor / $total, $tipos ) as $id_tipo => $importe ) {
					$impuestos[ $id_tipo ] = ( $impuestos[ $id_tipo ] ?? 0 ) + $importe;
				}
			}
			$tarifa->set_taxes( $impuestos );
		}
		return $tarifas;
	},
	20,
	2
);
