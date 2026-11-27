<?php
/**
 * Casafinna (página 109107, casafinna.giftbrand.com.br): o arquivo de estilo do Elementor dessa página sumiu (404),
 * então a grade de ícones das categorias saía sem layout (ícones gigantes empilhados). Gera o arquivo de novo, uma vez.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	if ( get_option( 'dc_casafinna_css_v1' ) || ! class_exists( '\Elementor\Core\Files\CSS\Post' ) || ! get_post( 109107 ) ) {
		return;
	}
	update_option( 'dc_casafinna_css_v1', time(), false );
	delete_post_meta( 109107, '_elementor_css' );
	$css = \Elementor\Core\Files\CSS\Post::create( 109107 );
	$css->update();
}, 90 );
