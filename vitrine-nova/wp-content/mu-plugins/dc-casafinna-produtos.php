<?php
/**
 * Casafinna (casafinna.giftbrand.com.br): coloca os produtos da loja Ouro (categoria "Padrão das franquias", 1456)
 * na categoria CASAFINNA (1345), que é a que a loja da Casafinna mostra. Roda uma vez, só acrescenta (não tira nada).
 * Depois disso a lista da Casafinna é independente: entra e sai produto marcando a categoria CASAFINNA no cadastro.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	if ( get_option( 'dc_casafinna_prod_v1' ) || ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	$de   = 1456;
	$para = 1345;
	if ( ! term_exists( $de, 'product_cat' ) || ! term_exists( $para, 'product_cat' ) ) {
		return;
	}
	update_option( 'dc_casafinna_prod_v1', time(), false );
	$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $de, 'include_children' => false ) ) ) ); // phpcs:ignore
	$novos = 0;
	foreach ( $ids as $id ) {
		if ( has_term( $para, 'product_cat', $id ) ) {
			continue;
		}
		wp_set_object_terms( (int) $id, array( $para ), 'product_cat', true );
		$novos++;
	}
	update_option( 'dc_casafinna_prod_novos', $novos, false );
}, 70 );
