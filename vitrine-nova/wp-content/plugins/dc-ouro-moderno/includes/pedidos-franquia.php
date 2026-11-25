<?php
/**
 * Pedido feito a partir da loja de uma franquia fica marcado com ela (_dcom_franquia = id da franquia), seja quem for o comprador.
 * A franquia vem do cookie dcom_fr (gravado na loja /<unidade>/ e na pagina do produto com ?lf=).
 * Aparece em WooCommerce > Pedidos (coluna "Loja" e dentro do pedido) e entra nas compras da franquia (Painel da Matriz e Area do Franqueado).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Franquia aprovada do cookie, ou 0. */
function dcom_pedido_franquia_cookie() {
	$id = isset( $_COOKIE['dcom_fr'] ) ? absint( $_COOKIE['dcom_fr'] ) : 0; // phpcs:ignore
	if ( ! $id || 'aprovada' !== (string) get_user_meta( $id, 'omx_status', true ) || (int) get_user_meta( $id, 'omx_parent', true ) > 0 ) {
		return 0;
	}
	return $id;
}

function dcom_pedido_marcar( $order ) {
	if ( ! is_object( $order ) || $order->get_meta( '_dcom_franquia' ) ) {
		return;
	}
	$fid = dcom_pedido_franquia_cookie();
	if ( ! $fid ) {
		return;
	}
	$order->update_meta_data( '_dcom_franquia', $fid );
	$order->update_meta_data( '_dcom_franquia_nome', (string) get_user_meta( $fid, 'omx_escola', true ) );
}
add_action( 'woocommerce_checkout_create_order', 'dcom_pedido_marcar', 20, 1 );
add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'dcom_pedido_marcar', 20, 1 );

/*
 * Pedidos marcados com a franquia (ou com qualquer franquia, quando $fid = 0), entre $desde e $ate (timestamps; 0 = sem limite).
 * Os IDs vem direto da tabela de metadados (pedidos no modo antigo ou HPOS): no modo antigo o wc_get_orders
 * IGNORA meta_query e devolvia todos os pedidos do site.
 */
function dcom_pedidos_marcados( $fid = 0, $desde = 0, $status = array( 'processing', 'on-hold', 'completed' ), $ate = 0 ) {
	global $wpdb;
	if ( ! function_exists( 'wc_get_order' ) ) {
		return array();
	}
	$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	if ( $hpos ) {
		$tab = $wpdb->prefix . 'wc_orders_meta';
		$sql = $fid ? $wpdb->prepare( "SELECT DISTINCT order_id FROM {$tab} WHERE meta_key = '_dcom_franquia' AND meta_value = %s", (string) (int) $fid ) : "SELECT DISTINCT order_id FROM {$tab} WHERE meta_key = '_dcom_franquia' AND meta_value <> ''"; // phpcs:ignore
	} else {
		$sql = $fid ? $wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_dcom_franquia' AND meta_value = %s", (string) (int) $fid ) : "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_dcom_franquia' AND meta_value <> ''"; // phpcs:ignore
	}
	$ids = array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore
	$st  = array_map(
		function ( $x ) {
			return 0 === strpos( $x, 'wc-' ) ? substr( $x, 3 ) : $x;
		},
		(array) $status
	);
	$out = array();
	foreach ( $ids as $id ) {
		$o = wc_get_order( $id );
		if ( ! $o || 'shop_order' !== $o->get_type() || ! in_array( $o->get_status(), $st, true ) ) {
			continue;
		}
		if ( $fid && (int) $o->get_meta( '_dcom_franquia' ) !== (int) $fid ) {
			continue;
		}
		$dc = $o->get_date_created();
		$ts = $dc ? $dc->getTimestamp() : 0;
		if ( ( $desde > 0 && $ts < $desde ) || ( $ate > 0 && $ts > $ate ) ) {
			continue;
		}
		$out[] = $o;
	}
	usort(
		$out,
		function ( $a, $b ) {
			$x = $a->get_date_created();
			$y = $b->get_date_created();
			return ( $y ? $y->getTimestamp() : 0 ) <=> ( $x ? $x->getTimestamp() : 0 );
		}
	);
	return $out;
}

/* Coluna "Loja" na lista de pedidos (HPOS e modo antigo). */
function dcom_pedido_col( $cols ) {
	$novo = array();
	foreach ( $cols as $k => $v ) {
		$novo[ $k ] = $v;
		if ( 'order_number' === $k ) {
			$novo['dcom_loja'] = 'Loja';
		}
	}
	if ( ! isset( $novo['dcom_loja'] ) ) {
		$novo['dcom_loja'] = 'Loja';
	}
	return $novo;
}
function dcom_pedido_col_val( $col, $order ) {
	if ( 'dcom_loja' !== $col ) {
		return;
	}
	$order = is_object( $order ) ? $order : wc_get_order( $order );
	$nome  = $order ? (string) $order->get_meta( '_dcom_franquia_nome' ) : '';
	echo '' !== $nome ? '<mark class="order-status" style="background:#FFF4D6;color:#5B4300"><span>' . esc_html( $nome ) . '</span></mark>' : '&ndash;';
}
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'dcom_pedido_col', 20 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'dcom_pedido_col_val', 20, 2 );
add_filter( 'manage_edit-shop_order_columns', 'dcom_pedido_col', 20 );
add_action(
	'manage_shop_order_posts_custom_column',
	function ( $col, $post_id ) {
		dcom_pedido_col_val( $col, $post_id );
	},
	20,
	2
);

/* Dentro do pedido: "Loja: <franquia>". */
add_action(
	'woocommerce_admin_order_data_after_order_details',
	function ( $order ) {
		$nome = (string) $order->get_meta( '_dcom_franquia_nome' );
		if ( '' !== $nome ) {
			echo '<p class="form-field form-field-wide" style="margin-top:12px"><strong>Loja:</strong> ' . esc_html( $nome ) . ' <span style="color:#777">(pedido feito pela loja da franquia)</span></p>';
		}
	}
);
