<?php
/**
 * Mouse Pad Tecnologia: os 10 produtos copiados do BLAIR 93282-106 (SKUs 93282-T01 a T10) foram excluídos em 26/11/2026.
 * A escola escolhe a estampa nas imagens que já estão dentro do produto original.
 * Roda uma vez (opção dcmp_tec_del_v1): apaga os 10 produtos, as 10 imagens geradas e as opções antigas.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	if ( get_option( 'dcmp_tec_del_v1' ) || ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	update_option( 'dcmp_tec_del_v1', time(), false );
	$feitos = array();
	for ( $i = 1; $i <= 10; $i++ ) {
		$pid = (int) wc_get_product_id_by_sku( '93282-T' . sprintf( '%02d', $i ) );
		$p   = $pid ? wc_get_product( $pid ) : null;
		if ( $p ) {
			delete_transient( 'dcv_p1_' . $pid );
			$p->delete( true );
			$feitos[] = $pid;
		}
	}
	// Imagens geradas para esses produtos (não são usadas em mais nenhum lugar).
	for ( $a = 121620; $a <= 121629; $a++ ) {
		if ( 'attachment' === get_post_type( $a ) ) {
			wp_delete_attachment( $a, true );
		}
	}
	foreach ( array( 'dcmp_tec_ids', 'dcmp_tec_v1', 'dcmp_tec_v2', 'dcmp_tec_v3' ) as $o ) {
		delete_option( $o );
	}
	update_option( 'dcmp_tec_del_log', $feitos, false );
	if ( function_exists( 'wc_delete_product_transients' ) ) {
		wc_delete_product_transients();
	}
}, 50 );

// Uma vez: apaga as 10 fotos de fundo de tecnologia baixadas do banco de imagens (121651 a 121660), que não foram usadas.
add_action( 'init', function () {
	if ( get_option( 'dcmp_tec_del_v2' ) ) {
		return;
	}
	update_option( 'dcmp_tec_del_v2', time(), false );
	for ( $a = 121651; $a <= 121660; $a++ ) {
		if ( 'attachment' === get_post_type( $a ) && 0 === strpos( get_the_title( $a ), 'Fundo tecnologia' ) ) {
			wp_delete_attachment( $a, true );
		}
	}
}, 51 );
