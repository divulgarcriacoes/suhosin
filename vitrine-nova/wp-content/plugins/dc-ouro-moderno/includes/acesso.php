<?php
/**
 * Acesso da Matriz (mesma logica do Alta Conexao): quem e da Diretoria Ouro Moderno ou da Equipe da Matriz
 * nunca ve o painel do WordPress. Sem barra de administracao, sem /wp-admin, sem a "Minha conta" vazia:
 * o login leva direto ao Painel da Matriz, que abre no proprio site em /painel-matriz/.
 * O administrador continua vendo o painel no wp-admin (menu "Painel da Matriz") e tambem pode abrir /painel-matriz/.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Conta que so tem o Painel da Matriz (Diretoria ou Equipe), sem ser administrador. */
function dcmx_so_matriz( $user = null ) {
	if ( null === $user ) {
		$user = wp_get_current_user();
	}
	if ( ! ( $user instanceof \WP_User ) || ! $user->exists() ) {
		return false;
	}
	if ( user_can( $user, 'manage_options' ) || user_can( $user, 'manage_woocommerce' ) ) {
		return false;
	}
	return (bool) array_intersect( array( 'dcmx_diretoria', 'dcmx_equipe' ), (array) $user->roles );
}

function dcmx_url_front() {
	return home_url( '/painel-matriz/' );
}

/* Login (tela do WordPress e formulario da Minha conta do WooCommerce) leva ao painel. */
add_filter(
	'login_redirect',
	function ( $to, $req, $user ) {
		return ( $user instanceof \WP_User && dcmx_so_matriz( $user ) ) ? dcmx_url_front() : $to;
	},
	20,
	3
);
add_filter(
	'woocommerce_login_redirect',
	function ( $to, $user ) {
		return ( $user instanceof \WP_User && dcmx_so_matriz( $user ) ) ? dcmx_url_front() : $to;
	},
	20,
	2
);

/* Sem barra de administracao. */
add_filter(
	'show_admin_bar',
	function ( $show ) {
		return dcmx_so_matriz() ? false : $show;
	},
	20
);

/* Sem /wp-admin (o WooCommerce mandaria para a Minha conta vazia): volta para o painel. */
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ! dcmx_so_matriz() ) {
			return;
		}
		wp_safe_redirect( dcmx_url_front() );
		exit;
	},
	1
);

/*
 * Pagina /painel-matriz/ no proprio site. Roda antes do travamento das lojas (dcpt_lock, prioridade 1),
 * entao funciona tambem em ouromoderno.giftbrand.com.br. A "Minha conta" de quem e so da Matriz tambem cai aqui.
 */
add_action(
	'template_redirect',
	function () {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore
		$path = untrailingslashit( (string) wp_parse_url( $uri, PHP_URL_PATH ) );

		if ( '/painel-matriz' !== $path ) {
			if ( dcmx_so_matriz() && function_exists( 'is_account_page' ) && is_account_page()
				&& ! ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'customer-logout' ) || is_wc_endpoint_url( 'lost-password' ) ) ) ) {
				wp_safe_redirect( dcmx_url_front() );
				exit;
			}
			return;
		}

		if ( ! is_user_logged_in() ) {
			$login = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
			wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( dcmx_url_front() ), $login ) );
			exit;
		}
		if ( ! current_user_can( 'dcmx_ver' ) || ! function_exists( 'dcmx_pagina' ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		ob_start();
		dcmx_pagina();
		$html = ob_get_clean();
		echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Painel da Matriz · Ouro Moderno</title>'
			. '<style>html,body{margin:0;padding:0;background:#F4F5F8}body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;font-size:14px;color:#101828}'
			. '.dcmx{margin:0!important;min-height:100vh;display:flex;flex-direction:column}.dcmx-wrap{flex:1}'
			. '.notice{background:#fff;border-left:4px solid #12805C;border-radius:10px;padding:2px 14px;margin:0 0 14px}'
			. '.dcmx-cfg input[type=text]{min-height:38px;border:1px solid #D0D5DD;border-radius:10px;padding:0 10px}.dcmx-cfg .button-primary{border:0;border-radius:10px;cursor:pointer}'
			. '</style></head><body>' . $html . '</body></html>'; // phpcs:ignore
		exit;
	},
	0
);

/* Tela Entrar no modelo do Grupo Alta Linha (tela cheia, cores Ouro Moderno). */
require_once __DIR__ . '/entrar.php';
