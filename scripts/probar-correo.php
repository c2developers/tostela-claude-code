<?php
/**
 * Manda dos correos de prueba (uno sencillo y otro con la plantilla de WooCommerce) y comprueba que salen por
 * Resend. No muestra nunca la clave ni el remitente.
 * Uso: scripts/probar-correo.sh [destinatario]      (por defecto, delivered@resend.dev, el buzón de pruebas de Resend)
 */

$destino = $args[0] ?? 'delivered@resend.dev';
$marca   = gmdate( 'H:i:s' ) . ' UTC';
$errores = array();

add_action(
	'wp_mail_failed',
	function ( $error ) use ( &$errores ) {
		$errores[] = preg_replace( '/re_[A-Za-z0-9_]+/', 're_…', $error->get_error_message() );
	}
);

echo 'Mailer: ', \WPMailSMTP\Options::init()->get( 'mail', 'mailer' ), " | destino: $destino\n\n";

$subject_1 = "Prueba de Tostela (correo sencillo) $marca";
$enviado_1 = wp_mail( $destino, $subject_1, "Hola,\n\nEste es un correo de prueba de la tienda Tostela, enviado con WP Mail SMTP por Resend.\n" );
echo ( $enviado_1 ? '✓' : '✗' ), " Correo sencillo: wp_mail ", $enviado_1 ? 'lo ha entregado a Resend' : 'ha fallado', "\n";

$subject_2 = "Prueba de Tostela (plantilla de WooCommerce) $marca";
$mailer    = WC()->mailer();
$html      = $mailer->wrap_message( 'Prueba de correo de WooCommerce', '<p>Así se verán los correos de pedido de Tostela: con los colores de la tienda y enviados por Resend.</p>' );
$enviado_2 = $mailer->send( $destino, $subject_2, $html );
echo ( $enviado_2 ? '✓' : '✗' ), " Correo de WooCommerce: ", $enviado_2 ? 'entregado a Resend' : 'ha fallado', "\n";

foreach ( $errores as $error ) {
	echo "  Error: $error\n";
}

// Lo que dice Resend: solo se puede consultar si la clave tiene permiso completo (no solo de envío).
// Se vigila hasta 45 s: Resend los da primero como «queued» y luego como «delivered».
$estados = array();
for ( $intento = 0; $intento < 15; $intento++ ) {
	sleep( 3 );
	$respuesta = wp_remote_get(
		'https://api.resend.com/emails?limit=6',
		array(
			'headers' => array( 'Authorization' => 'Bearer ' . WPMS_RESEND_API_KEY ),
			'timeout' => 20,
		)
	);
	$codigo = wp_remote_retrieve_response_code( $respuesta );
	$cuerpo = json_decode( wp_remote_retrieve_body( $respuesta ), true );
	if ( 200 !== $codigo ) {
		break;
	}
	$estados = array();
	foreach ( $cuerpo['data'] ?? array() as $correo ) {
		if ( in_array( $correo['subject'] ?? '', array( $subject_1, $subject_2 ), true ) ) {
			$estados[ $correo['subject'] ] = $correo;
		}
	}
	$pendientes = array_filter(
		$estados,
		function ( $correo ) {
			return in_array( $correo['last_event'] ?? '', array( 'queued', 'sent' ), true );
		}
	);
	if ( 2 === count( $estados ) && ! $pendientes ) {
		break;
	}
}
echo "\nConsulta a Resend (código $codigo, a los ", ( $intento + 1 ) * 3, " s):\n";
if ( 200 === $codigo && $estados ) {
	foreach ( $estados as $correo ) {
		printf( "  %s | para %s | «%s» | estado: %s\n", substr( $correo['created_at'] ?? '', 11, 8 ), implode( ',', $correo['to'] ?? array() ), $correo['subject'], $correo['last_event'] ?? '?' );
	}
} else {
	echo '  No se puede ver el estado de entrega: ', $cuerpo['name'] ?? 'sin detalle', ' — ', $cuerpo['message'] ?? '', "\n";
}

global $wpdb;
$tabla = $wpdb->prefix . 'wpmailsmtp_debug_events';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tabla ) ) === $tabla ) {
	$eventos = $wpdb->get_results( "SELECT created_at, content FROM $tabla WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 3 MINUTE) ORDER BY id DESC LIMIT 3" );
	echo "\nErrores registrados por WP Mail SMTP en los últimos 3 minutos: ", $eventos ? '' : "ninguno\n";
	foreach ( $eventos as $evento ) {
		echo '  ', $evento->created_at, ' ', mb_substr( preg_replace( '/re_[A-Za-z0-9_]+/', 're_…', wp_strip_all_tags( $evento->content ) ), 0, 220 ), "\n";
	}
}

if ( ! $enviado_1 || ! $enviado_2 ) {
	WP_CLI::halt( 1 );
}
