<?php
/**
 * Plugin Name: Tostela: desistimiento
 * Description: Formulario para desistir del contrato (14 días). La tienda recibe el aviso por correo, el cliente recibe un acuse de recibo y queda constancia en el panel (WooCommerce > Desistimientos) y en las notas del pedido.
 */

defined( 'ABSPATH' ) || exit;

const TOSTELA_DESISTIR_PAGINA   = 'desistir';
const TOSTELA_DESISTIR_TIPO     = 'tostela_desistir';
const TOSTELA_DESISTIR_PLAZO    = 14;
const TOSTELA_DESISTIR_INTENTOS = 5; // Por hora y por visitante.

/** Dónde llega el aviso: la opción tostela_desistir_destino (para pruebas) o el correo del administrador. */
function tostela_desistir_destino() {
	$destino = get_option( 'tostela_desistir_destino' );
	return is_email( $destino ) ? $destino : get_option( 'admin_email' );
}

/** Registro de los desistimientos: solo se ven en el panel, nunca en la web. */
add_action(
	'init',
	function () {
		register_post_type(
			TOSTELA_DESISTIR_TIPO,
			array(
				'labels'       => array(
					'name'               => 'Desistimientos',
					'singular_name'      => 'Desistimiento',
					'menu_name'          => 'Desistimientos',
					'all_items'          => 'Desistimientos',
					'edit_item'          => 'Ver desistimiento',
					'search_items'       => 'Buscar desistimientos',
					'not_found'          => 'Todavía no hay desistimientos.',
					'not_found_in_trash' => 'No hay desistimientos en la papelera.',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'woocommerce',
				'supports'     => array( 'title', 'editor' ),
				'capabilities' => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap' => true,
			)
		);
	}
);

/** Identifica al visitante (por la IP real que llega por Cloudflare) para limitar los intentos, sin guardar la IP. */
function tostela_desistir_clave_visitante() {
	$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
	return 'tostela_des_' . md5( $ip . wp_salt() );
}

/**
 * Valida el envío, lo guarda y manda los dos correos.
 * Devuelve array( 'errores' => [...], 'valores' => [...] ) o array( 'referencia' => 'DES-…' ).
 */
function tostela_desistir_gestionar( $datos ) {
	$valores = array(
		'nombre'   => sanitize_text_field( $datos['nombre'] ?? '' ),
		'correo'   => sanitize_email( $datos['correo'] ?? '' ),
		'pedido'   => preg_replace( '/\D/', '', (string) ( $datos['pedido'] ?? '' ) ),
		'fecha'    => sanitize_text_field( $datos['fecha'] ?? '' ),
		'articulos' => mb_substr( sanitize_textarea_field( $datos['articulos'] ?? '' ), 0, 1000 ),
	);
	$errores = array();

	if ( ! wp_verify_nonce( $datos['tostela_nonce'] ?? '', 'tostela_desistir' ) ) {
		return array( 'errores' => array( 'La página ha caducado. Recárgala e inténtalo de nuevo.' ), 'valores' => $valores );
	}
	// Casilla trampa que un humano no ve: si viene rellena, es un robot. Se le contesta como si hubiera ido bien.
	if ( ! empty( $datos['web'] ) ) {
		return array( 'referencia' => 'DES-' . wp_date( 'Ymd' ) . '-AAAAA' );
	}
	$clave = tostela_desistir_clave_visitante();
	if ( (int) get_transient( $clave ) >= TOSTELA_DESISTIR_INTENTOS ) {
		return array( 'errores' => array( 'Has enviado demasiadas solicitudes seguidas. Inténtalo de nuevo dentro de una hora o escríbenos a ' . tostela_desistir_destino() . '.' ), 'valores' => $valores );
	}

	if ( '' === $valores['nombre'] ) {
		$errores[] = 'Escribe tu nombre y apellidos.';
	}
	if ( ! is_email( $valores['correo'] ) ) {
		$errores[] = 'Escribe un correo electrónico válido: ahí te mandaremos el acuse de recibo.';
	}
	if ( '' === $valores['pedido'] || strlen( $valores['pedido'] ) > 9 ) {
		$errores[] = 'Escribe el número de pedido (solo el número, por ejemplo 96).';
	}
	$recibido = DateTime::createFromFormat( '!Y-m-d', $valores['fecha'], wp_timezone() );
	if ( ! $recibido || $recibido->format( 'Y-m-d' ) !== $valores['fecha'] ) {
		$errores[] = 'Indica la fecha en que recibiste el pedido.';
	} elseif ( $recibido->getTimestamp() > time() ) {
		$errores[] = 'La fecha de recepción no puede ser futura.';
	}
	if ( $errores ) {
		return array( 'errores' => $errores, 'valores' => $valores );
	}

	set_transient( $clave, (int) get_transient( $clave ) + 1, HOUR_IN_SECONDS );

	$referencia = 'DES-' . wp_date( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 5, false, false ) );
	$dias       = (int) floor( ( time() - $recibido->getTimestamp() ) / DAY_IN_SECONDS );
	$en_plazo   = $dias <= TOSTELA_DESISTIR_PLAZO;
	$cuando     = wp_date( 'j \d\e F \d\e Y \a \l\a\s H:i' );
	$fecha_ok   = wp_date( 'j \d\e F \d\e Y', $recibido->getTimestamp() );
	$articulos  = '' !== $valores['articulos'] ? $valores['articulos'] : 'Todo el pedido';

	$pedido     = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $valores['pedido'] ) : false;
	$existe     = $pedido instanceof WC_Order;
	$coincide   = $existe && 0 === strcasecmp( trim( $pedido->get_billing_email() ), $valores['correo'] );

	$detalle = implode(
		"\n",
		array(
			"Referencia: $referencia",
			"Recibido el: $cuando",
			"Nombre: {$valores['nombre']}",
			"Correo: {$valores['correo']}",
			"Pedido: #{$valores['pedido']}",
			"Fecha en que recibió el pedido: $fecha_ok ($dias días)",
			"Productos: $articulos",
		)
	);
	$comprobaciones = implode(
		"\n",
		array(
			'En plazo (14 días): ' . ( $en_plazo ? 'sí' : "NO: han pasado $dias días" ),
			'El pedido existe: ' . ( $existe ? 'sí' : 'NO' ),
			'El correo coincide con el del pedido: ' . ( $coincide ? 'sí' : ( $existe ? 'NO' : 'no se puede comprobar' ) ),
		)
	);

	$id = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => TOSTELA_DESISTIR_TIPO,
				'post_status'  => 'private',
				'post_title'   => "$referencia · Pedido #{$valores['pedido']} · {$valores['nombre']}",
				'post_content' => $detalle . "\n\n" . $comprobaciones,
			)
		)
	);

	if ( $coincide ) {
		$pedido->add_order_note( "El cliente ha solicitado desistir del contrato (ref. $referencia). Fecha de recepción indicada: $fecha_ok." );
	}

	$destino = tostela_desistir_destino();
	$enlace  = $id && ! is_wp_error( $id ) ? "\n\nVer en el panel: " . admin_url( "post.php?post=$id&action=edit" ) : '';
	$enlace .= $existe ? "\nVer el pedido: " . $pedido->get_edit_order_url() : '';

	wp_mail(
		$destino,
		"Desistimiento del pedido #{$valores['pedido']} ($referencia)",
		"Un cliente ha solicitado desistir del contrato.\n\n$detalle\n\n$comprobaciones$enlace\n\nPuedes contestarle directamente a este correo.",
		array( 'Reply-To: ' . $valores['nombre'] . ' <' . $valores['correo'] . '>' )
	);

	$acuse = "Hola, {$valores['nombre']}:\n\n"
		. "Hemos recibido tu solicitud de desistimiento del contrato. Este correo es tu acuse de recibo.\n\n$detalle\n\n"
		. "Qué pasa ahora: te escribiremos en un día laborable con las instrucciones para devolver el pedido (recuerda que el café abierto no se puede devolver, por higiene). "
		. "Te devolveremos el dinero en un máximo de 14 días desde que recibimos tu solicitud.\n\n"
		. "Si tienes alguna duda, responde a este correo o escríbenos a $destino.\n\nTostela";
	wp_mail( $valores['correo'], "Hemos recibido tu desistimiento ($referencia)", $acuse, array( 'Reply-To: ' . $destino ) );

	return array( 'referencia' => $referencia );
}

/** Procesa el envío en la propia página, antes de pintarla; si va bien, redirige para que recargar no lo repita. */
add_action(
	'template_redirect',
	function () {
		if ( ! is_page( TOSTELA_DESISTIR_PAGINA ) ) {
			return;
		}
		nocache_headers();
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['tostela_desistir'] ) ) {
			return;
		}
		$resultado = tostela_desistir_gestionar( wp_unslash( $_POST ) );
		if ( ! empty( $resultado['referencia'] ) ) {
			wp_safe_redirect( add_query_arg( 'referencia', $resultado['referencia'], get_permalink() ), 303 );
			exit;
		}
		$GLOBALS['tostela_desistir'] = $resultado;
	},
	5
);

/** El formulario: [tostela_desistir] */
add_shortcode(
	'tostela_desistir',
	function () {
		$referencia = isset( $_GET['referencia'] ) ? sanitize_text_field( wp_unslash( $_GET['referencia'] ) ) : '';
		if ( preg_match( '/^DES-\d{8}-[A-Z0-9]{5}$/', $referencia ) ) {
			return '<div class="tostela-aviso-ok" role="status"><p><strong>Hemos recibido tu desistimiento.</strong></p>'
				. '<p>Tu referencia es <strong>' . esc_html( $referencia ) . '</strong>. Te hemos enviado un acuse de recibo por correo y te contestaremos en un día laborable con las instrucciones para la devolución.</p>'
				. '<p>Si no te llega el correo en unos minutos, mira en la carpeta de spam o escríbenos a ' . esc_html( tostela_desistir_destino() ) . ' indicando la referencia.</p></div>';
		}

		$estado  = $GLOBALS['tostela_desistir'] ?? array();
		$valores = $estado['valores'] ?? array();
		$campo   = function ( $nombre ) use ( $valores ) {
			return esc_attr( $valores[ $nombre ] ?? '' );
		};
		$html = '';
		if ( ! empty( $estado['errores'] ) ) {
			$html .= '<div class="tostela-errores" role="alert"><p><strong>Revisa el formulario:</strong></p><ul>';
			foreach ( $estado['errores'] as $error ) {
				$html .= '<li>' . esc_html( $error ) . '</li>';
			}
			$html .= '</ul></div>';
		}

		$html .= '<form class="tostela-formulario" method="post" action="' . esc_url( get_permalink() ) . '">'
			. '<input type="hidden" name="tostela_desistir" value="1">'
			. wp_nonce_field( 'tostela_desistir', 'tostela_nonce', true, false )
			. '<label>Nombre y apellidos<input type="text" name="nombre" value="' . $campo( 'nombre' ) . '" required autocomplete="name"></label>'
			. '<label>Correo electrónico<span class="tostela-ayuda">Ahí te mandaremos el acuse de recibo.</span><input type="email" name="correo" value="' . $campo( 'correo' ) . '" required autocomplete="email"></label>'
			. '<label>Número de pedido<span class="tostela-ayuda">Solo el número, por ejemplo 96. Lo tienes en el correo de confirmación.</span><input type="text" name="pedido" value="' . $campo( 'pedido' ) . '" required inputmode="numeric" pattern="[#0-9 ]*" autocomplete="off"></label>'
			. '<label>Fecha en que recibiste el pedido<input type="date" name="fecha" value="' . $campo( 'fecha' ) . '" required max="' . esc_attr( wp_date( 'Y-m-d' ) ) . '"></label>'
			. '<label>Productos de los que desistes<span class="tostela-ayuda">Opcional. Si lo dejas en blanco, entendemos que es todo el pedido.</span><textarea name="articulos" rows="3" maxlength="1000">' . esc_textarea( $valores['articulos'] ?? '' ) . '</textarea></label>'
			. '<div class="tostela-trampa" aria-hidden="true"><label>No rellenes este campo<input type="text" name="web" tabindex="-1" autocomplete="off"></label></div>'
			. '<p class="tostela-ayuda">Usaremos estos datos solo para gestionar tu desistimiento, como explica nuestra <a href="' . esc_url( home_url( '/politica-de-privacidad/' ) ) . '">política de privacidad</a>. No tienes que dar ningún motivo.</p>'
			. '<div class="wp-block-buttons"><div class="wp-block-button"><button type="submit" class="wp-block-button__link wp-element-button">Desistir del contrato</button></div></div>'
			. '</form>';

		return $html;
	}
);
