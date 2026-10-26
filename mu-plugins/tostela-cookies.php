<?php
/**
 * Plugin Name: Tostela: analítica y publicidad con consentimiento
 * Description: Google Analytics y el píxel de Meta con Consent Mode v2. No se carga nada de Google ni de Meta hasta que el visitante lo acepta en el aviso de cookies (CookieYes).
 */

defined( 'ABSPATH' ) || exit;

const TOSTELA_GA_ID    = 'G-7W4E0KE2BV';
const TOSTELA_PIXEL_ID = '2587624965028846';

/**
 * Va en la cabecera, antes que cualquier otro script:
 * 1. Consent Mode v2: todo denegado por defecto (ad_storage, ad_user_data, ad_personalization y analytics_storage).
 * 2. Cuando CookieYes avisa de lo que ha elegido el visitante (o lo recuerda de otra visita), se actualiza el
 *    consentimiento y solo entonces se descarga cada etiqueta: Google Analytics con la categoría «Analítica» y
 *    el píxel de Meta con «Publicidad». Si rechaza, no se pide nada a Google ni a Meta.
 */
add_action(
	'wp_head',
	function () {
		if ( is_admin() || is_feed() || wp_doing_ajax() ) {
			return;
		}
		?>
<script id="tostela-consentimiento">
(function () {
	var GA_ID = <?php echo wp_json_encode( TOSTELA_GA_ID ); ?>;
	var PIXEL_ID = <?php echo wp_json_encode( TOSTELA_PIXEL_ID ); ?>;
	var cargado = { google: false, meta: false };

	window.dataLayer = window.dataLayer || [];
	function gtag() { window.dataLayer.push(arguments); }
	window.gtag = gtag;

	// Consent Mode v2: todo denegado hasta que el visitante elija.
	gtag('consent', 'default', {
		ad_storage: 'denied',
		ad_user_data: 'denied',
		ad_personalization: 'denied',
		analytics_storage: 'denied',
		functionality_storage: 'denied',
		personalization_storage: 'denied',
		security_storage: 'granted',
		wait_for_update: 500
	});
	gtag('set', 'ads_data_redaction', true);

	function estado(permitido) { return permitido ? 'granted' : 'denied'; }

	function cargarGoogle() {
		if (cargado.google) { return; }
		cargado.google = true;
		var s = document.createElement('script');
		s.async = true;
		s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA_ID);
		document.head.appendChild(s);
		gtag('js', new Date());
		gtag('config', GA_ID);
	}

	function cargarMeta() {
		if (cargado.meta) { return; }
		cargado.meta = true;
		/* Código base del píxel de Meta, ya sin ejecutarse hasta ahora. */
		!function (f, b, e, v, n, t, s) {
			if (f.fbq) { return; }
			n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
			if (!f._fbq) { f._fbq = n; }
			n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = [];
			t = b.createElement(e); t.async = !0; t.src = v;
			s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
		}(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
		window.fbq('init', PIXEL_ID);
		window.fbq('track', 'PageView');
	}

	function aplicar(categorias) {
		var analitica = !!categorias.analytics;
		var publicidad = !!categorias.advertisement;
		gtag('consent', 'update', {
			analytics_storage: estado(analitica),
			ad_storage: estado(publicidad),
			ad_user_data: estado(publicidad),
			ad_personalization: estado(publicidad)
		});
		if (analitica) { cargarGoogle(); }
		if (publicidad) {
			cargarMeta();
			if (window.fbq) { window.fbq('consent', 'grant'); }
		} else if (window.fbq) {
			window.fbq('consent', 'revoke');
		}
	}

	// Lo que ha elegido el visitante, según CookieYes (también lo que recuerda de una visita anterior).
	function leerConsentimiento() {
		if (typeof window.getCkyConsent !== 'function') { return; }
		var consentimiento = window.getCkyConsent();
		if (consentimiento && consentimiento.isUserActionCompleted) {
			aplicar(consentimiento.categories || {});
		}
	}

	document.addEventListener('cookieyes_consent_update', function () { setTimeout(leerConsentimiento, 0); });
	document.addEventListener('DOMContentLoaded', leerConsentimiento);
})();
</script>
		<?php
	},
	1
);
