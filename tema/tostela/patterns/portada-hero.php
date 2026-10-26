<?php
/**
 * Title: Portada: hero
 * Slug: tostela/portada-hero
 * Description: Foto grande con el titular y los dos botones de la portada.
 * Categories: featured
 */
$foto = esc_url( get_theme_file_uri( 'assets/img/hero.jpg' ) );
?>
<!-- wp:cover {"url":"<?php echo $foto; ?>","dimRatio":100,"customGradient":"linear-gradient(90deg,rgba(34,22,16,0.9) 0%,rgba(34,22,16,0.72) 40%,rgba(34,22,16,0.1) 72%,rgba(34,22,16,0.1) 100%)","focalPoint":{"x":0.78,"y":0.5},"minHeight":680,"contentPosition":"center left","isDark":true,"align":"full","className":"tostela-hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull has-custom-content-position is-position-center-left tostela-hero" style="padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50);min-height:680px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim has-background-gradient" style="background:linear-gradient(90deg,rgba(34,22,16,0.9) 0%,rgba(34,22,16,0.72) 40%,rgba(34,22,16,0.1) 72%,rgba(34,22,16,0.1) 100%)"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo $foto; ?>" style="object-position:78% 50%" data-object-fit="cover" data-object-position="78% 50%"/><div class="wp-block-cover__inner-container"><!-- wp:group {"align":"wide","layout":{"type":"constrained","contentSize":"34rem","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"className":"etiqueta","textColor":"accent-1","fontSize":"x-small"} -->
<p class="etiqueta has-accent-1-color has-text-color has-x-small-font-size">Café de especialidad</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"textColor":"accent-5"} -->
<h1 class="wp-block-heading has-accent-5-color has-text-color">El café de tu cafetería favorita, en tu cocina</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-5","fontSize":"large"} -->
<p class="has-accent-5-color has-text-color has-large-font-size">Lotes pequeños de origen único, tostados cada martes y enviados al día siguiente.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--40)"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/tienda/">Ver los cafés</a></div>
<!-- /wp:button -->

<!-- wp:button {"textColor":"accent-5","className":"is-style-outline","style":{"border":{"color":"#fffdf9"}}} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-accent-5-color has-text-color wp-element-button" href="/nuestra-historia/" style="border-color:#fffdf9">Nuestra historia</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->
