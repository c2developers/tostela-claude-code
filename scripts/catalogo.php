<?php
/**
 * Carga contenido/productos.json en WooCommerce, con sus fotos. Se puede repetir: lo que ya existe
 * (se reconoce por el SKU) se actualiza, y las variaciones que ya no estén en el JSON se borran.
 * Uso: wp eval-file /proyecto/scripts/catalogo.php
 *
 * Cada café es un producto variable con las variaciones molienda × peso, IVA reducido (10 %).
 * Los accesorios son productos simples con el IVA general (21 %). Los precios llevan el IVA incluido.
 */

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

const RUTA_JSON     = '/proyecto/contenido/productos.json';
const RUTA_IMAGENES = '/proyecto/assets/img/';

// Códigos cortos para el SKU de cada variación y peso en kg para el envío.
const CODIGO_MOLIENDA = array(
	'En grano'        => 'GRA',
	'Espresso'        => 'ESP',
	'Filtro'          => 'FIL',
	'Prensa francesa' => 'PRE',
);
const KILOS = array(
	'250 g' => 0.25,
	'1 kg'  => 1,
);

function fallar( string $mensaje ): void {
	WP_CLI::error( $mensaje );
}

function categoria( array $datos ): int {
	$existente = term_exists( $datos['slug'], 'product_cat' );
	$campos    = array(
		'slug'        => $datos['slug'],
		'description' => $datos['descripcion'],
	);
	if ( $existente ) {
		wp_update_term( (int) $existente['term_id'], 'product_cat', $campos + array( 'name' => $datos['nombre'] ) );
		return (int) $existente['term_id'];
	}
	$nuevo = wp_insert_term( $datos['nombre'], 'product_cat', $campos );
	if ( is_wp_error( $nuevo ) ) {
		fallar( $nuevo->get_error_message() );
	}
	return (int) $nuevo['term_id'];
}

/** Atributo global (para filtrar en la tienda) con sus términos, en orden. Devuelve [id del atributo, ids de términos]. */
function atributo( string $slug, string $nombre, array $terminos ): array {
	$id = wc_attribute_taxonomy_id_by_name( $slug );
	$ajustes = array(
		'name'         => $nombre,
		'slug'         => $slug,
		'type'         => 'select',
		'order_by'     => 'menu_order',
		'has_archives' => false,
	);
	if ( $id ) {
		wc_update_attribute( $id, $ajustes );
	} else {
		$id = wc_create_attribute( $ajustes );
		if ( is_wp_error( $id ) ) {
			fallar( $id->get_error_message() );
		}
	}
	$taxonomia = wc_attribute_taxonomy_name( $slug );
	if ( ! taxonomy_exists( $taxonomia ) ) {
		register_taxonomy( $taxonomia, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false ) );
	}
	$ids = array();
	foreach ( array_values( $terminos ) as $orden => $termino ) {
		$existente = term_exists( $termino, $taxonomia );
		$nuevo     = $existente ? $existente : wp_insert_term( $termino, $taxonomia );
		if ( is_wp_error( $nuevo ) ) {
			fallar( $nuevo->get_error_message() );
		}
		update_term_meta( (int) $nuevo['term_id'], 'order', $orden ); // El orden manual: En grano, Espresso… y 250 g antes que 1 kg.
		$ids[ $termino ] = (int) $nuevo['term_id'];
	}
	return array( (int) $id, $ids );
}

/** Sube la foto a la mediateca una sola vez (se reconoce por el nombre del archivo). */
function imagen( string $archivo, string $texto_alternativo ): int {
	$existentes = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_key'       => '_tostela_archivo',
			'meta_value'     => $archivo,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	if ( $existentes ) {
		return (int) $existentes[0];
	}
	$origen = RUTA_IMAGENES . $archivo;
	if ( ! is_readable( $origen ) ) {
		fallar( "No encuentro la foto $origen" );
	}
	$temporal = wp_tempnam( $archivo );
	copy( $origen, $temporal );
	$id = media_handle_sideload( array( 'name' => $archivo, 'tmp_name' => $temporal ), 0, $texto_alternativo );
	if ( is_wp_error( $id ) ) {
		fallar( $id->get_error_message() );
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $texto_alternativo );
	update_post_meta( $id, '_tostela_archivo', $archivo );
	return (int) $id;
}

function producto_por_sku( string $sku, string $clase ): WC_Product {
	$id = wc_get_product_id_by_sku( $sku );
	if ( $id ) {
		$producto = wc_get_product( $id );
		if ( ! $producto instanceof $clase ) {
			fallar( "El SKU $sku ya existe y no es un producto de tipo $clase." );
		}
		return $producto;
	}
	return new $clase();
}

function atributo_producto( int $id, string $taxonomia, array $ids_terminos, int $posicion, bool $variacion ): WC_Product_Attribute {
	$atributo = new WC_Product_Attribute();
	$atributo->set_id( $id );
	$atributo->set_name( $taxonomia );
	$atributo->set_options( array_values( $ids_terminos ) );
	$atributo->set_position( $posicion );
	$atributo->set_visible( true );
	$atributo->set_variation( $variacion );
	return $atributo;
}

function atributo_texto( string $nombre, array $valores, int $posicion ): WC_Product_Attribute {
	$atributo = new WC_Product_Attribute();
	$atributo->set_id( 0 );
	$atributo->set_name( $nombre );
	$atributo->set_options( $valores );
	$atributo->set_position( $posicion );
	$atributo->set_visible( true );
	$atributo->set_variation( false );
	return $atributo;
}

function descripcion_cafe( array $cafe ): string {
	$html  = '<p><strong>Notas de cata:</strong> ' . esc_html( implode( ', ', $cafe['notas'] ) ) . '.</p>';
	$html .= '<p>' . esc_html( $cafe['corto'] ) . '</p>';
	$html .= '<p>Tostado cada martes y enviado al día siguiente. Elige la molienda y el peso: en grano, espresso, filtro o prensa francesa.</p>';
	return $html;
}

$datos = json_decode( file_get_contents( RUTA_JSON ), true, 512, JSON_THROW_ON_ERROR );

$categorias = array();
foreach ( $datos['categorias'] as $categoria ) {
	$categorias[ $categoria['slug'] ] = categoria( $categoria );
}

$pesos = array();
foreach ( $datos['cafes'] as $cafe ) {
	foreach ( array_keys( $cafe['precios'] ) as $peso ) {
		$pesos[ $peso ] = true;
	}
}
list( $id_molienda, $terminos_molienda ) = atributo( 'molienda', 'Molienda', $datos['molienda'] );
list( $id_peso, $terminos_peso )         = atributo( 'peso', 'Peso', array_keys( $pesos ) );

$orden        = 0;
$variaciones  = 0;
foreach ( $datos['cafes'] as $cafe ) {
	$producto = producto_por_sku( $cafe['sku'], WC_Product_Variable::class );
	$producto->set_name( $cafe['nombre'] );
	$producto->set_sku( $cafe['sku'] );
	$producto->set_status( 'publish' );
	$producto->set_catalog_visibility( 'visible' );
	$producto->set_featured( (bool) $cafe['destacado'] );
	$producto->set_short_description( $cafe['corto'] );
	$producto->set_description( descripcion_cafe( $cafe ) );
	$producto->set_category_ids( array( $categorias['cafes'] ) );
	$producto->set_image_id( imagen( $cafe['imagen'], 'Bolsa de café ' . $cafe['nombre'] . ' de Tostela' ) );
	$producto->set_tax_status( 'taxable' );
	$producto->set_tax_class( 'iva-reducido' );
	$producto->set_menu_order( $orden++ );
	$producto->set_manage_stock( false );

	$atributos = array(
		atributo_producto( $id_molienda, 'pa_molienda', $terminos_molienda, 0, true ),
		atributo_producto( $id_peso, 'pa_peso', array_intersect_key( $terminos_peso, $cafe['precios'] ), 1, true ),
		atributo_texto( 'Notas de cata', $cafe['notas'], 2 ),
	);
	foreach ( $cafe['ficha'] as $nombre => $valor ) {
		$atributos[] = atributo_texto( $nombre, array( $valor ), count( $atributos ) );
	}
	$producto->set_attributes( $atributos );
	$id_producto = $producto->save();

	$conservadas = array();
	foreach ( $datos['molienda'] as $molienda ) {
		foreach ( $cafe['precios'] as $peso => $precio ) {
			$sku       = $cafe['sku'] . '-' . CODIGO_MOLIENDA[ $molienda ] . '-' . strtoupper( str_replace( ' ', '', $peso ) );
			$variacion = producto_por_sku( $sku, WC_Product_Variation::class );
			$variacion->set_parent_id( $id_producto );
			$variacion->set_sku( $sku );
			$variacion->set_status( 'publish' );
			$variacion->set_attributes(
				array(
					'pa_molienda' => get_term( $terminos_molienda[ $molienda ] )->slug,
					'pa_peso'     => get_term( $terminos_peso[ $peso ] )->slug,
				)
			);
			$variacion->set_regular_price( (string) $precio );
			$variacion->set_weight( (string) KILOS[ $peso ] );
			$variacion->set_manage_stock( false );
			$variacion->set_stock_status( 'instock' );
			$conservadas[] = $variacion->save();
			++$variaciones;
		}
	}
	foreach ( $producto->get_children() as $id_hija ) {
		if ( ! in_array( $id_hija, $conservadas, true ) ) {
			wc_get_product( $id_hija )->delete( true );
		}
	}
	WC_Product_Variable::sync( $id_producto );
	wc_delete_product_transients( $id_producto );
}

foreach ( $datos['accesorios'] as $accesorio ) {
	$producto = producto_por_sku( $accesorio['sku'], WC_Product_Simple::class );
	$producto->set_name( $accesorio['nombre'] );
	$producto->set_sku( $accesorio['sku'] );
	$producto->set_status( 'publish' );
	$producto->set_catalog_visibility( 'visible' );
	$producto->set_short_description( $accesorio['corto'] );
	$producto->set_description( '<p>' . esc_html( $accesorio['corto'] ) . '</p>' );
	$producto->set_category_ids( array( $categorias['accesorios'] ) );
	$producto->set_image_id( imagen( $accesorio['imagen'], $accesorio['nombre'] ) );
	$producto->set_tax_status( 'taxable' );
	$producto->set_tax_class( '' ); // IVA general: 21 %.
	$producto->set_regular_price( (string) $accesorio['precio'] );
	$producto->set_menu_order( $orden++ );
	$producto->set_manage_stock( false );
	$producto->set_stock_status( 'instock' );
	$producto->save();
}

printf(
	"Catálogo cargado: %d cafés (%d variaciones) y %d accesorios.\n",
	count( $datos['cafes'] ),
	$variaciones,
	count( $datos['accesorios'] )
);
