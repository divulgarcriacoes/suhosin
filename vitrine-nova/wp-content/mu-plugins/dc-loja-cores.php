<?php
/**
 * Loja Divulgar (lista de produtos e categorias): bolinhas das cores que têm estoque, logo abaixo do preço.
 * Usa a tabela de cores/estoque do produto (_dc_color_stock_table). Passando o mouse mostra o nome da cor e o estoque.
 * Não aparece nas lojas das escolas (elas têm o próprio cartão).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dcsw_html( $pid ) {
	if ( ! function_exists( 'dccf_swatch_style' ) ) {
		return '';
	}
	$raw  = get_post_meta( (int) $pid, '_dc_color_stock_table', true );
	$rows = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	if ( ! is_array( $rows ) || ! $rows ) {
		return '';
	}
	$ok = array();
	foreach ( $rows as $r ) {
		if ( empty( $r['label'] ) || ( isset( $r['stock'] ) && (int) $r['stock'] <= 0 ) ) {
			continue;
		}
		$ok[] = $r;
	}
	if ( ! $ok ) {
		return '';
	}
	$max = 10;
	$out = '';
	foreach ( array_slice( $ok, 0, $max ) as $r ) {
		$tip  = $r['label'] . ( isset( $r['stock'] ) ? ' · ' . number_format( (int) $r['stock'], 0, ',', '.' ) . ' un' : '' );
		$out .= '<span class="dcsw-b" title="' . esc_attr( $tip ) . '" style="' . esc_attr( dccf_swatch_style( $r['label'] ) ) . '"></span>';
	}
	if ( count( $ok ) > $max ) {
		$out .= '<span class="dcsw-m">+' . ( count( $ok ) - $max ) . '</span>';
	}
	return '<span class="dcsw" aria-label="Cores em estoque">' . $out . '</span>';
}

add_action(
	'woocommerce_after_shop_loop_item_title',
	function () {
		if ( is_admin() || ( function_exists( 'dcom_fr_atual' ) && dcom_fr_atual() ) ) {
			return;
		}
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		echo dcsw_html( $product->get_id() ); // phpcs:ignore
	},
	11
);

add_action(
	'wp_head',
	function () {
		if ( is_admin() || ! function_exists( 'is_woocommerce' ) || ! ( is_shop() || is_product_taxonomy() || is_product() ) ) {
			return;
		}
		echo '<style id="dcsw-css">.dcsw{display:flex;flex-wrap:wrap;align-items:center;gap:5px;margin:6px 0 4px;order:3}'
			. '.dcsw-b{width:16px;height:16px;border-radius:50%;border:1px solid rgba(0,0,0,.22);display:inline-block;flex:0 0 16px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.35)}'
			. '.dcsw-m{font:600 11px Roboto,Arial,sans-serif;color:#6b7785;margin-left:2px}</style>';
	},
	132
);
