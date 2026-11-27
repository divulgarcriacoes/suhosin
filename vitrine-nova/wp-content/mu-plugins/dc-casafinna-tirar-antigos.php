<?php
/**
 * Casafinna (27/11/2026): tira da loja os 20 produtos antigos, os feitos um a um, com o logo desenhado dentro da foto e sem molde.
 * Tira só das categorias da Casafinna (CASAFINNA e as filhas: canecas, canetas, copos, mochilas, sacolas); o produto continua no site.
 * Guarda em dc_casafinna_removidos (1ª passada) e dc_casafinna_removidos2 (2ª passada: todas as filhas da CASAFINNA e tudo que não é do padrão da Ouro) o que cada produto tinha, para desfazer.
 * Roda uma vez (opção dc_casafinna_tirar_v1).
 * Desfazer: logado como administrador, abrir qualquer página do site com ?dc_casafinna_desfazer=1
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Categoria CASAFINNA (1345) e todas as filhas. */
function dc_casafinna_cats() {
	$f = get_term_children( 1345, 'product_cat' );
	return array_values( array_unique( array_map( 'intval', array_merge( array( 1345 ), is_array( $f ) ? $f : array() ) ) ) );
}

add_action( 'init', function () {
	if ( get_option( 'dc_casafinna_tirar_v1' ) || ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	update_option( 'dc_casafinna_tirar_v1', time(), false );
	$ids   = array( 18152, 21954, 40700, 40970, 41389, 41424, 42029, 42290, 42318, 42921, 43055, 53135, 59453, 60098, 60304, 60685, 60865, 61002, 61008, 109101 );
	$cats  = dc_casafinna_cats();
	$antes = array();
	foreach ( $ids as $id ) {
		if ( 'product' !== get_post_type( $id ) ) {
			continue;
		}
		// Segurança: só tira quem NÃO está na lista de produtos da Ouro (1456).
		if ( has_term( 1456, 'product_cat', $id ) ) {
			continue;
		}
		$ant = wp_get_object_terms( $id, 'product_cat', array( 'fields' => 'ids' ) );
		$tem = array_values( array_intersect( (array) $ant, $cats ) );
		if ( $tem ) {
			$antes[ $id ] = $tem;
			wp_remove_object_terms( $id, $tem, 'product_cat' );
		}
	}
	update_option( 'dc_casafinna_removidos', $antes, false );
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
	}
}, 71 );

add_action( 'init', function () {
	if ( get_option( 'dc_casafinna_tirar_v2' ) || ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	update_option( 'dc_casafinna_tirar_v2', time(), false );
	$cats  = dc_casafinna_cats();
	$ids   = get_posts( array( 'post_type' => 'product', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cats ) ) ) ); // phpcs:ignore
	$antes = array();
	foreach ( $ids as $id ) {
		if ( has_term( 1456, 'product_cat', $id ) ) {
			continue; // do padrão da Ouro: fica
		}
		$ant = wp_get_object_terms( $id, 'product_cat', array( 'fields' => 'ids' ) );
		$tem = array_values( array_intersect( (array) $ant, $cats ) );
		if ( $tem ) {
			$antes[ $id ] = $tem;
			wp_remove_object_terms( $id, $tem, 'product_cat' );
		}
	}
	update_option( 'dc_casafinna_removidos2', $antes, false );
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
	}
}, 73 );

/* 3ª passada (27/11/2026): o Kit Matrícula é das escolas, não da Casafinna: sai das categorias da Casafinna. */
add_action( 'init', function () {
	if ( get_option( 'dc_casafinna_tirar_v3' ) || ! function_exists( 'wc_get_product_id_by_sku' ) ) {
		return;
	}
	update_option( 'dc_casafinna_tirar_v3', time(), false );
	$id = (int) wc_get_product_id_by_sku( defined( 'DCKIT_SKU' ) ? DCKIT_SKU : 'KITMAT' );
	if ( ! $id ) {
		return;
	}
	$cats = dc_casafinna_cats();
	$ant  = wp_get_object_terms( $id, 'product_cat', array( 'fields' => 'ids' ) );
	$tem  = array_values( array_intersect( (array) $ant, $cats ) );
	if ( $tem ) {
		wp_remove_object_terms( $id, $tem, 'product_cat' );
		update_option( 'dc_casafinna_removidos3', array( $id => $tem ), false );
	}
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
	}
}, 74 );

add_action( 'init', function () {
	if ( empty( $_GET['dc_casafinna_desfazer'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore
		return;
	}
	foreach ( array( 'dc_casafinna_removidos', 'dc_casafinna_removidos2', 'dc_casafinna_removidos3' ) as $op ) {
		foreach ( (array) get_option( $op, array() ) as $id => $tem ) {
			wp_set_object_terms( (int) $id, array_map( 'intval', (array) $tem ), 'product_cat', true );
		}
		delete_option( $op );
	}
	wp_die( 'Produtos antigos da Casafinna recolocados.' );
}, 72 );
