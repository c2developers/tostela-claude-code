<?php
/**
 * Title: Portada: nuestra historia
 * Slug: tostela/portada-historia
 * Description: La foto del tostador junto a un resumen de la historia de Tostela.
 * Categories: featured
 */
$foto = esc_url( get_theme_file_uri( 'assets/img/tostador.jpg' ) );
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|50","left":"var:preset|spacing|60"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"45%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:45%"><!-- wp:image {"aspectRatio":"4/5","scale":"cover","sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"3px"}}} -->
<figure class="wp-block-image size-large has-custom-border"><img src="<?php echo $foto; ?>" alt="Tostador de café de cobre y acero con granos recién tostados" style="border-radius:3px;aspect-ratio:4/5;object-fit:cover"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"55%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:55%"><!-- wp:paragraph {"className":"etiqueta","textColor":"accent","fontSize":"x-small"} -->
<p class="etiqueta has-accent-color has-text-color has-x-small-font-size">Nuestra historia</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Que el café de casa sea tan bueno como el de tu cafetería favorita</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Compramos café verde de lotes pequeños, a importadores que trabajan directamente con cooperativas y productores. Cada lote lo catamos antes de comprarlo y otra vez después de tostarlo.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Tostamos los martes en lotes de doce kilos, con perfiles distintos para cada origen: más claros para que un Etiopía enseñe sus flores, algo más largos para el chocolate de un Brasil.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--40)"><!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/nuestra-historia/">Leer nuestra historia</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
