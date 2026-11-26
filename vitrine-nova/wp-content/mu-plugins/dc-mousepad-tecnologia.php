<?php
/**
 * Mouse Pad Tecnologia (26/11/2026): 10 produtos copiados do BLAIR 93282-106 (mouse pad para sublimação, 23 x 22 cm),
 * cada um com um mockup gerado no Magnific (estampa de tecnologia, centro limpo para o logo).
 * Na vitrine de cada escola o logo da escola entra pelo mockup (_dcmk): grande no meio; em 3 deles também pequeno no canto.
 * Roda uma vez (opção dcmp_tec_v1). Preço, gravação, frete e medidas vêm do produto original.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dcmp_tec_lista() {
	// imagem (anexo), área do logo no meio em % da foto [x0, x1, y0, y1], fundo escuro?, logo extra no canto [x0, x1, y0, y1] ou null
	return array(
		1  => array( 121620, array( 24, 71, 30, 68 ), true, null ),
		2  => array( 121621, array( 25, 69, 30, 68 ), true, array( 60, 77, 70, 78 ) ),
		3  => array( 121622, array( 20, 66, 28, 70 ), true, null ),
		4  => array( 121623, array( 22, 69, 30, 68 ), true, null ),
		5  => array( 121624, array( 25, 72, 30, 68 ), true, array( 60, 76, 70, 78 ) ),
		6  => array( 121625, array( 26, 71, 30, 70 ), false, null ),
		7  => array( 121626, array( 27, 70, 34, 66 ), false, null ),
		8  => array( 121627, array( 25, 74, 32, 70 ), false, null ),
		9  => array( 121628, array( 25, 72, 32, 71 ), false, array( 61, 78, 72, 79 ) ),
		10 => array( 121629, array( 25, 75, 32, 72 ), false, null ),
	);
}

function dcmp_tec_quad( $r ) {
	return array( (float) $r[0], (float) $r[2], (float) $r[1], (float) $r[2], (float) $r[1], (float) $r[3], (float) $r[0], (float) $r[3] );
}

add_action( 'init', function () {
	if ( get_option( 'dcmp_tec_v1' ) || ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	$orig = wc_get_product( 60350 );
	if ( ! $orig ) {
		return;
	}
	update_option( 'dcmp_tec_v1', time(), false );
	if ( ! class_exists( 'WC_Admin_Duplicate_Product' ) ) {
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-duplicate-product.php';
	}
	$dup  = new WC_Admin_Duplicate_Product();
	$cats = array_unique( array_merge( $orig->get_category_ids(), array( 1456, 1457, 1458, 1469, 1471 ) ) );
	$ids  = array();
	foreach ( dcmp_tec_lista() as $n => $c ) {
		$num = sprintf( '%02d', $n );
		$sku = '93282-T' . $num;
		if ( wc_get_product_id_by_sku( $sku ) ) {
			continue;
		}
		$p = $dup->product_duplicate( $orig );
		if ( ! $p ) {
			continue;
		}
		$p->set_name( 'Mouse Pad Tecnologia ' . $num );
		$p->set_slug( 'mouse-pad-tecnologia-' . $num );
		$p->set_sku( $sku );
		$p->set_status( 'publish' );
		$p->set_catalog_visibility( 'visible' );
		$p->set_image_id( (int) $c[0] );
		$p->set_gallery_image_ids( array() );
		$p->set_category_ids( $cats );
		$p->set_short_description( 'Mouse pad sublimado com estampa de tecnologia e a marca da sua escola. Base em borracha de 2 mm, 23 x 22 cm.' );
		$p->set_description( '<p><strong>Mouse Pad Tecnologia ' . $num . '</strong> com a marca da sua escola.</p><p>Mouse pad para sublimação com base em borracha de 2 mm, 230 x 220 mm. Estampa de tecnologia em toda a superfície e o logo da escola no centro.</p>' );
		$p->save();
		$pid = $p->get_id();
		foreach ( array( '_dcspot_ref', '_dcspot_source', '_dcspot_created', '_dcspot_migrated', '_dcspot_migrate_mode', '_emcp_sync_uid', 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword', '_dc_rel_add', '_dc_rel_to', '_dc_vg_fim', '_dc_ct_pre' ) as $k ) {
			delete_post_meta( $pid, $k );
		}
		update_post_meta( $pid, 'rank_math_title', 'Mouse Pad Tecnologia ' . $num . ' personalizado com a marca da escola' );
		$regra = array(
			'x' => ( $c[1][0] + $c[1][1] ) / 2,
			'y' => ( $c[1][2] + $c[1][3] ) / 2,
			'w' => 90,
			'r' => 0,
			'm' => 'cor',
			't' => $c[2] ? 'escuro' : 'claro',
			'l' => 'h',
			'q' => dcmp_tec_quad( $c[1] ),
		);
		if ( $c[3] ) {
			$regra['qx'] = array( array( 'q' => dcmp_tec_quad( $c[3] ), 'm' => 'cor', 'l' => 'h', 't' => $c[2] ? 'escuro' : 'claro', 'd' => 0 ) );
		}
		update_post_meta( $pid, '_dcmk', $regra );
		$ids[ $n ] = $pid;
	}
	update_option( 'dcmp_tec_ids', $ids, false );
}, 50 );

// Regra de preço: os 10 seguem a mesma regra do BLAIR 93282-106 ("Mousepad barato": mínimo 50, margem 175%).
add_filter( 'option_dcprr_regras', function ( $r ) {
	if ( ! is_array( $r ) ) {
		return $r;
	}
	$novos = array();
	for ( $i = 1; $i <= 10; $i++ ) {
		$novos[] = '93282-T' . sprintf( '%02d', $i );
	}
	foreach ( $r as $k => $x ) {
		if ( isset( $x['skus'] ) && false !== strpos( (string) $x['skus'], '93282-106' ) && false === strpos( (string) $x['skus'], '93282-T01' ) ) {
			$r[ $k ]['skus'] = rtrim( (string) $x['skus'], ", " ) . ', ' . implode( ', ', $novos );
		}
	}
	return $r;
}, 20 );

// Uma vez: limpa o preço guardado da vitrine (6 h) depois de entrar na regra.
add_action( 'init', function () {
	if ( get_option( 'dcmp_tec_v3' ) ) {
		return;
	}
	update_option( 'dcmp_tec_v3', time(), false );
	foreach ( (array) get_option( 'dcmp_tec_ids', array() ) as $pid ) {
		delete_transient( 'dcv_p1_' . (int) $pid );
	}
}, 52 );

// Uma vez: devolve a referência do fornecedor (Spot 93282) às cópias, para o motor de preços calcular a tabela como no BLAIR original.
add_action( 'init', function () {
	if ( get_option( 'dcmp_tec_v2' ) ) {
		return;
	}
	$ids = get_option( 'dcmp_tec_ids', array() );
	if ( ! is_array( $ids ) || ! $ids ) {
		return;
	}
	update_option( 'dcmp_tec_v2', time(), false );
	foreach ( $ids as $pid ) {
		update_post_meta( (int) $pid, '_dcspot_ref', '93282' );
		update_post_meta( (int) $pid, '_dcspot_source', 'spot' );
		delete_transient( 'dcv_p1_' . (int) $pid );
	}
}, 51 );
