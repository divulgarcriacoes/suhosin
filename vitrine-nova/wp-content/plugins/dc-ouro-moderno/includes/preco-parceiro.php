<?php
/**
 * Loja do parceiro (giftbrand.com.br/?loja=CODIGO, snippet 87827): mostra o preco calculado na hora (faixa A, pedido minimo),
 * igual a Divulgar e a Ouro Moderno. O snippet monta a pagina antes da acao 'wp', e o mu-plugin dc-loja-preco-vivo so
 * entra depois dela, entao essa loja mostrava o preco antigo gravado no produto.
 * So vale nessa pagina (que nao usa o carrinho do WooCommerce).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dcom_pp_ativo() {
	static $r = null;
	if ( null === $r ) {
		$h = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( preg_replace( '/:\d+$/', '', (string) wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : ''; // phpcs:ignore
		$r = ! is_admin() && ! empty( $_GET['loja'] ) && ( 'giftbrand.com.br' === $h || 'www.giftbrand.com.br' === $h ); // phpcs:ignore
	}
	return $r;
}

function dcom_pp_preco( $price, $product ) {
	static $busy  = false;
	static $cache = array();
	if ( $busy || did_action( 'wp' ) || ! dcom_pp_ativo() || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
		return $price; // depois da acao 'wp' o mu-plugin dc-loja-preco-vivo ja cuida
	}
	if ( ! function_exists( 'dcprice_calculate_with_curve' ) || ! function_exists( 'dcprice_product_is_priceable' ) ) {
		return $price;
	}
	$pid = (int) $product->get_id();
	if ( $pid <= 0 || $product->is_type( 'variation' ) ) {
		return $price;
	}
	if ( ! array_key_exists( $pid, $cache ) ) {
		$cache[ $pid ] = null;
		$busy          = true;
		try {
			if ( dcprice_product_is_priceable( $pid ) ) {
				$min = class_exists( 'DC_Min_Order_Qty' ) ? max( 1, (int) DC_Min_Order_Qty::min_qty_for_product( $pid ) ) : 1;
				$r   = dcprice_calculate_with_curve( $pid, $min );
				if ( ! is_wp_error( $r ) && ! empty( $r['unit_price'] ) && (float) $r['unit_price'] > 0 ) {
					$cache[ $pid ] = (string) round( (float) $r['unit_price'], 2 );
				}
			}
		} catch ( \Throwable $e ) {
			error_log( 'dc-ouro-moderno (preco parceiro): ' . $e->getMessage() ); // phpcs:ignore
		}
		$busy = false;
	}
	return null === $cache[ $pid ] ? $price : $cache[ $pid ];
}
add_filter( 'woocommerce_product_get_price', 'dcom_pp_preco', 51, 2 );
add_filter( 'woocommerce_product_get_regular_price', 'dcom_pp_preco', 51, 2 );
