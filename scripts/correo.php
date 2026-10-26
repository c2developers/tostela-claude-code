<?php
/**
 * Deja los correos de WooCommerce con la marca de Tostela y comprueba que WP Mail SMTP manda por Resend.
 * La clave y el remitente llegan por las constantes que define docker-compose.yml (desde .env); aquí
 * no se muestran nunca. Se puede repetir.
 * Uso: wp eval-file /proyecto/scripts/correo.php
 */

$remitente = defined( 'WPMS_MAIL_FROM' ) ? WPMS_MAIL_FROM : '';
$nombre    = defined( 'WPMS_MAIL_FROM_NAME' ) ? WPMS_MAIL_FROM_NAME : 'Tostela';

// Remitente y aspecto de los correos de WooCommerce (los colores de la tienda).
if ( $remitente ) {
	update_option( 'woocommerce_email_from_address', $remitente );
}
update_option( 'woocommerce_email_from_name', $nombre );
update_option( 'woocommerce_email_base_color', '#A84A28' );
update_option( 'woocommerce_email_background_color', '#FBF6EE' );
update_option( 'woocommerce_email_body_background_color', '#FFFDF9' );
update_option( 'woocommerce_email_text_color', '#2A1B14' );
update_option( 'woocommerce_email_footer_text', 'Tostela · Café de especialidad tostado cada martes' );

$mailer = \WPMailSMTP\Options::init()->get( 'mail', 'mailer' );
printf(
	"Correo: WP Mail SMTP con el mailer «%s». Clave de Resend: %s. Remitente: %s.\n",
	$mailer,
	defined( 'WPMS_RESEND_API_KEY' ) && WPMS_RESEND_API_KEY ? 'cargada' : 'FALTA',
	$remitente ? 'configurado' : 'FALTA'
);
