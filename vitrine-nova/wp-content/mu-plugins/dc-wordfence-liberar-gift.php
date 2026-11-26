<?php
/**
 * Wordfence: libera o endereço do sistema da Gift/Ouro Moderno (chama a API /ouromoderno/v1/), para o firewall nunca bloquear.
 * Acrescenta à lista "Allowlisted IPs" do Wordfence uma vez. Se o endereço da Gift mudar, atualizar aqui (opção dcwf_gift_ip_v1).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	if ( get_option( 'dcwf_gift_ip_v1' ) || ! class_exists( 'wfConfig' ) ) {
		return;
	}
	$ip   = '44.194.39.85';
	$list = (string) wfConfig::get( 'whitelisted', '' );
	$itens = array_values( array_filter( array_map( 'trim', explode( ',', $list ) ) ) );
	if ( ! in_array( $ip, $itens, true ) ) {
		$itens[] = $ip;
		wfConfig::set( 'whitelisted', implode( ',', $itens ) );
	}
	update_option( 'dcwf_gift_ip_v1', time(), false );
}, 80 );
