<?php
/**
 * O WP Rocket adia os scripts até a pessoa mexer na página ("atrasar JS"). Os scripts do logo nos produtos (dcmk-js)
 * e dos ícones de categoria (dccf-icones) precisam rodar na hora, senão o logo só aparece depois de rolar a página.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'rocket_delay_js_exclusions',
	function ( $list ) {
		$list   = is_array( $list ) ? $list : array();
		$list[] = 'dcmk-js';
		$list[] = 'dccf-icones';
		$list[] = 'dcmk';
		return $list;
	}
);
