<?php
/**
 * Limpa o cache do WP Rocket uma vez por versão (26-27/11/2026: quantidade em unidades; bolinhas de cor; estilo da Casafinna).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
add_action( 'init', function () {
	if ( get_option( 'dc_limpa_cache_20261127a' ) ) {
		return;
	}
	update_option( 'dc_limpa_cache_20261127a', time(), false );
	if ( function_exists( 'rocket_clean_minify' ) ) {
		rocket_clean_minify( 'css' );
	}
	if ( function_exists( 'rocket_clean_post' ) ) {
		rocket_clean_post( 109107 );
	}
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
	}
}, 99 );
