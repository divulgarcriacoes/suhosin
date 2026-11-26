<?php
/**
 * Catálogos do painel (snippet 83942): estoque e cores disponíveis em cada produto.
 * - dccat_estoque_cores( $p ): cores com estoque (bolinha da cor, nome e quantidade) e o estoque total.
 *   A página pública do catálogo mostra isso quando o catálogo tem _dcc_show_estoque = 1 (botão no painel).
 * - Uma vez: cria o catálogo "COPOS E GARRAFAS TÉRMICAS" com os copos térmicos e as garrafas térmicas que têm estoque.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dccat_estoque_cores( $p ) {
	if ( ! $p instanceof WC_Product ) {
		return '';
	}
	$cores = '';
	$raw   = get_post_meta( $p->get_id(), '_dc_color_stock_table', true );
	$rows  = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	if ( is_array( $rows ) ) {
		foreach ( $rows as $r ) {
			$st = isset( $r['stock'] ) ? (int) $r['stock'] : 0;
			if ( empty( $r['label'] ) || $st <= 0 ) {
				continue;
			}
			$sw     = function_exists( 'dccf_swatch_style' ) ? dccf_swatch_style( $r['label'] ) : 'background:#EEE';
			$cores .= '<span class="ec-c"><i style="' . esc_attr( $sw ) . '"></i>' . esc_html( $r['label'] ) . ' <b>' . esc_html( number_format( $st, 0, ',', '.' ) ) . '</b></span>';
		}
	}
	$tot = '';
	if ( $p->managing_stock() && null !== $p->get_stock_quantity() && (int) $p->get_stock_quantity() > 0 ) {
		$tot = '<div class="ec-t">Em estoque: <b>' . esc_html( number_format( (int) $p->get_stock_quantity(), 0, ',', '.' ) ) . ' un.</b></div>';
	}
	if ( '' === $cores && '' === $tot ) {
		return '';
	}
	return '<div class="ec">' . $tot . ( '' !== $cores ? '<div class="ec-l"><small>Cores disponíveis:</small>' . $cores . '</div>' : '' ) . '</div>';
}

function dccat_estoque_css() {
	return '.ec{font-size:11.5px;color:#333;display:flex;flex-direction:column;gap:4px}.ec-t{color:#067647}.ec-l{display:flex;flex-wrap:wrap;gap:4px 8px;align-items:center}.ec-l small{width:100%;color:#6B7785}.ec-c{display:inline-flex;align-items:center;gap:4px;white-space:nowrap}.ec-c i{width:12px;height:12px;border-radius:50%;border:1px solid rgba(0,0,0,.2);display:inline-block;-webkit-print-color-adjust:exact;print-color-adjust:exact}.ec-c b{color:#067647}';
}

// Página pública do catálogo (?dc_catalogo=TOKEN): acrescenta estoque e cores embaixo do nome de cada produto,
// só nos catálogos com _dcc_show_estoque = 1. Roda antes do snippet 83942 (prioridade 1) e trabalha no HTML que ele gera.
add_action( 'template_redirect', function () {
	if ( empty( $_GET['dc_catalogo'] ) || is_admin() ) { // phpcs:ignore
		return;
	}
	$token = sanitize_text_field( wp_unslash( $_GET['dc_catalogo'] ) ); // phpcs:ignore
	$ids   = get_posts( array( 'post_type' => 'dc_catalog', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_dcc_token', 'meta_value' => $token ) ); // phpcs:ignore
	// Desligado: o catálogo público já mostra "Estoque" e "Cores" por outro recurso do site (ficava repetido).
	if ( true || empty( $ids ) || ! get_post_meta( (int) $ids[0], '_dcc_show_estoque', true ) ) {
		return;
	}
	ob_start(
		function ( $html ) {
			if ( false === strpos( $html, 'class="card"' ) ) {
				return $html;
			}
			$html = preg_replace_callback(
				'#<div class="sku">SKU ([^<]*)</div><div class="nm">(.*?)</div>#s',
				function ( $m ) {
					$pid = function_exists( 'wc_get_product_id_by_sku' ) ? (int) wc_get_product_id_by_sku( html_entity_decode( trim( $m[1] ) ) ) : 0;
					$p   = $pid ? wc_get_product( $pid ) : null;
					return $m[0] . ( $p ? dccat_estoque_cores( $p ) : '' );
				},
				$html
			);
			return str_replace( '</style></head>', dccat_estoque_css() . '</style></head>', $html );
		}
	);
}, 0 );

// Uma vez: cria o catálogo de copos e garrafas térmicas com estoque.
add_action( 'init', function () {
	if ( get_option( 'dccat_termicas_v1' ) || ! post_type_exists( 'dc_catalog' ) || ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	update_option( 'dccat_termicas_v1', time(), false );
	$grupos = array(
		array( 1344, 1377, 1378 ), // garrafas térmicas (Térmicas, Inox, Bambu)
		array( 996, 998 ),         // copos térmicos (Térmicos, Inox)
	);
	$lista  = array();
	foreach ( $grupos as $cats ) {
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cats ) ), // phpcs:ignore
			)
		);
		$bloco = array();
		foreach ( $ids as $pid ) {
			$p = wc_get_product( $pid );
			if ( ! $p || ! $p->is_in_stock() || in_array( $pid, $lista, true ) ) {
				continue;
			}
			if ( $p->managing_stock() && (int) $p->get_stock_quantity() <= 0 ) {
				continue;
			}
			$bloco[ $pid ] = (float) $p->get_price();
		}
		asort( $bloco );
		$lista = array_merge( $lista, array_keys( $bloco ) );
	}
	$cid = wp_insert_post( array( 'post_type' => 'dc_catalog', 'post_status' => 'publish', 'post_title' => 'COPOS E GARRAFAS TÉRMICAS', 'post_author' => 1 ) );
	if ( $cid && ! is_wp_error( $cid ) ) {
		update_post_meta( $cid, '_dcc_owner', 1 );
		update_post_meta( $cid, '_dcc_token', wp_generate_password( 16, false ) );
		update_post_meta( $cid, '_dcc_products', array_map( 'intval', $lista ) );
		update_post_meta( $cid, '_dcc_show_price', 1 );
		update_post_meta( $cid, '_dcc_show_estoque', 1 );
		update_option( 'dccat_termicas_id', (int) $cid, false );
	}
}, 60 );
