<?php
/**
 * Plugin Name: DC · Ouro Moderno: escola aprovada entra (LoginPress)
 * Description: 2026-11-24. O LoginPress marca toda conta nova como "aguardando aprovação" e barra o login com senha. Escola com status aprovada na Ouro Moderno
 *   (omx_status = aprovada) fica liberada no LoginPress automaticamente: na aprovação, no login e, uma vez, para as já aprovadas. Escola em análise continua barrada.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dcolp_libera( $uid ) {
	$uid = (int) $uid;
	if ( $uid && 'aprovada' === (string) get_user_meta( $uid, 'omx_status', true ) && 'inactive' === (string) get_user_meta( $uid, 'loginpress_user_verification', true ) ) {
		update_user_meta( $uid, 'loginpress_user_verification', 'active' );
	}
}

/* Na hora do login (antes da checagem do LoginPress). */
add_filter(
	'wp_authenticate_user',
	function ( $user ) {
		if ( $user instanceof WP_User ) {
			dcolp_libera( $user->ID );
		}
		return $user;
	},
	1
);

/* Quando a escola é aprovada (painel da matriz, API ou cadastro). */
add_action(
	'updated_user_meta',
	function ( $mid, $uid, $key, $val ) {
		if ( 'omx_status' === $key && 'aprovada' === $val ) {
			dcolp_libera( $uid );
		}
	},
	10,
	4
);
add_action(
	'added_user_meta',
	function ( $mid, $uid, $key, $val ) {
		if ( 'omx_status' === $key && 'aprovada' === $val ) {
			dcolp_libera( $uid );
		}
	},
	10,
	4
);

/* Uma vez: libera as escolas já aprovadas que ficaram presas. */
add_action(
	'init',
	function () {
		if ( get_option( 'dcolp_v1' ) ) {
			return;
		}
		$ids = get_users(
			array(
				'fields'     => 'ID',
				'number'     => -1,
				'meta_query' => array( // phpcs:ignore
					'relation' => 'AND',
					array( 'key' => 'omx_status', 'value' => 'aprovada' ),
					array( 'key' => 'loginpress_user_verification', 'value' => 'inactive' ),
				),
			)
		);
		foreach ( (array) $ids as $id ) {
			update_user_meta( (int) $id, 'loginpress_user_verification', 'active' );
		}
		update_option( 'dcolp_v1', current_time( 'mysql' ) . ' liberadas: ' . count( (array) $ids ), false );
	},
	99
);
