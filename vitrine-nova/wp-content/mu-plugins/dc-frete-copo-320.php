<?php
/**
 * Copo Térmico Inox 320ml (14726B, produto 41461): medida real do fornecedor (XBZ: 8,8 x 11,7 cm, 179 g).
 * O cadastro tinha 20 x 15 x 10 cm (chute da tela por categoria) e o frete saía muito caro.
 * Roda uma vez (opção dcfrete_copo320_v1).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	if ( get_option( 'dcfrete_copo320_v1' ) || ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	$p = wc_get_product( 41461 );
	if ( ! $p ) {
		return;
	}
	update_option( 'dcfrete_copo320_v1', time(), false );
	$p->set_length( '8.8' );
	$p->set_width( '8.8' );
	$p->set_height( '11.7' );
	$p->set_weight( '0.179' );
	$p->save();
	update_post_meta( 41461, '_dc_ship_auto', wp_json_encode( array( 'cat' => 177, 'w' => 0, 'd' => 0 ) ) );
	if ( class_exists( 'WC_Cache_Helper' ) ) {
		WC_Cache_Helper::get_transient_version( 'shipping', true );
	}
}, 60 );
