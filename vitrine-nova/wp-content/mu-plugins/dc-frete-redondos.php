<?php
/**
 * Frete (26/11/2026): produtos XBZ redondos/finos (canetas, copos, garrafas, guarda-chuvas...) vêm do fornecedor com
 * profundidade 0 (só largura = diâmetro e altura). O sistema ignorava e deixava o chute de 20 x 15 x 10 cm,
 * e o frete saía caro. Agora o diâmetro entra também como profundidade (c = l).
 * Roda uma vez (opção dcfrete_redondos_v1): só mexe nos produtos que ainda estão com medida chutada (_dc_ship_auto d=1).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	if ( get_option( 'dcfrete_redondos_v2' ) || ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	$map = get_option( 'dcfrete_map_xbz', array() );
	$map = isset( $map['dados'] ) ? $map['dados'] : array();
	if ( ! $map ) {
		return;
	}
	@set_time_limit( 300 ); // phpcs:ignore
	// Sem marca no começo: se o PHP parar no meio, na próxima visita continua (só pega quem ainda está com medida chutada).
	$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_query' => array( array( 'key' => '_dc_ship_auto', 'value' => '"d":1', 'compare' => 'LIKE' ) ) ) ); // phpcs:ignore
	$feitos = 0;
	foreach ( $ids as $id ) {
		if ( 'xbz' !== get_post_meta( $id, '_dcfrete_forn', true ) ) {
			continue;
		}
		$sku = (string) get_post_meta( $id, '_sku', true );
		if ( ! isset( $map[ $sku ] ) ) {
			continue;
		}
		$m = $map[ $sku ];
		if ( (float) $m['c'] > 0 || (float) $m['l'] <= 0 || (float) $m['a'] <= 0 ) {
			continue;
		}
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		$p->set_length( (string) $m['l'] );
		$p->set_width( (string) $m['l'] );
		$p->set_height( (string) $m['a'] );
		$p->save();
		$auto      = json_decode( (string) get_post_meta( $id, '_dc_ship_auto', true ), true );
		$auto      = is_array( $auto ) ? $auto : array();
		$auto['d'] = 0;
		update_post_meta( $id, '_dc_ship_auto', wp_json_encode( $auto ) );
		$feitos++;
	}
	update_option( 'dcfrete_redondos_feitos', $feitos, false );
	update_option( 'dcfrete_redondos_v2', time(), false );
	if ( class_exists( 'WC_Cache_Helper' ) ) {
		WC_Cache_Helper::get_transient_version( 'shipping', true );
	}
}, 65 );
