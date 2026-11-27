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
	$dir = WP_CONTENT_DIR . '/cache/wp-rocket/casafinna.giftbrand.com.br';
	if ( ! is_dir( $dir ) ) {
		return;
	}
	// Página inicial e todas as páginas de produto/categoria (o WP Rocket guarda uma pasta por endereço).
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		if ( $f->isFile() && 0 === strpos( $f->getFilename(), 'index' ) ) {
			@unlink( $f->getPathname() ); // phpcs:ignore
		}
	}
}

/* Uma vez: o cache antigo das páginas de produto ainda não tinha o logo. */
add_action(
	'init',
	function () {
		if ( ! get_option( 'dccf_cache_v3' ) ) {
			update_option( 'dccf_cache_v3', time(), false );
			dccf_limpa_cache_loja();
		}
	},
	80
);

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
