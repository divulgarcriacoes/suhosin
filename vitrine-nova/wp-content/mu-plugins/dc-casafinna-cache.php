<?php
/**
 * Casafinna: quando um produto entra ou sai da loja (categoria CASAFINNA ou filhas), ou os logos/cores do painel mudam,
 * apaga o cache da página inicial do site da Casafinna. O WP Rocket só limpa o domínio principal, então sem isto
 * a loja da Casafinna continuava mostrando a lista antiga.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dccf_limpa_cache_loja() {
	$dir = WP_CONTENT_DIR . '/cache/wp-rocket/casafinna.giftbrand.com.br/';
	foreach ( array( 'index-https.html', 'index-https.html_gzip', 'index.html', 'index.html_gzip' ) as $f ) {
		if ( is_file( $dir . $f ) ) {
			@unlink( $dir . $f ); // phpcs:ignore
		}
	}
}

/* Produto mudou de categoria: se mexeu na CASAFINNA (1345) ou numa filha, limpa. */
add_action(
	'set_object_terms',
	function ( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( 'product_cat' !== $taxonomy || 'product' !== get_post_type( $object_id ) ) {
			return;
		}
		dccf_limpa_cache_loja();
	},
	10,
	4
);

add_action( 'save_post_product', 'dccf_limpa_cache_loja', 99 );
add_action( 'trashed_post', function ( $id ) {
	if ( 'product' === get_post_type( $id ) ) {
		dccf_limpa_cache_loja();
	}
} );
add_action( 'update_option_dccf_marca_logos', 'dccf_limpa_cache_loja' );
add_action( 'add_option_dccf_marca_logos', 'dccf_limpa_cache_loja' );
add_action( 'update_option_dccf_marca_cfg', 'dccf_limpa_cache_loja' );
