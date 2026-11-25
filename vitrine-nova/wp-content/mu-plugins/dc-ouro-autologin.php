<?php
/**
 * Plugin Name: DC · Ouro Moderno: autologin pela API
 * Description: 2026-11-23. O sistema da rede Ouro Moderno pede um link de entrada para a escola e ela entra direto na Area do Franqueado, sem digitar e-mail e senha.
 *   POST /wp-json/ouromoderno/v1/franquias/entrar   (cabecalho X-Ouro-Key, a mesma chave da API)
 *   corpo: { "email": "..." }  ou  { "id": 123 }  ou  { "cnpj": "..." }   (opcional: "destino": "painel" | "loja")
 *   resposta: { "url": "https://ouromoderno.giftbrand.com.br/?om_entrar=...", "expira_em": "...", "valido_segundos": 300, ...dados da franquia }
 * O link vale 5 minutos e UMA vez so. Nunca loga administrador. Precisa do plugin dc-ouro-moderno ativo (usa a chave e o registro da API dele).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DCOM_AL_SEGUNDOS' ) ) {
	define( 'DCOM_AL_SEGUNDOS', 300 );
}

function dcom_al_base() {
	return 'https://ouromoderno.giftbrand.com.br';
}

/* Acha a escola pelo id, e-mail ou CNPJ (so usuario de franquia, nunca administrador). */
function dcom_al_achar( $p ) {
	$uid = isset( $p['id'] ) ? absint( $p['id'] ) : 0;
	if ( ! $uid && ! empty( $p['email'] ) ) {
		$u   = get_user_by( 'email', sanitize_email( (string) $p['email'] ) );
		$uid = $u ? (int) $u->ID : 0;
	}
	if ( ! $uid && ! empty( $p['cnpj'] ) ) {
		$c   = preg_replace( '/\D/', '', (string) $p['cnpj'] );
		$ids = '' !== $c ? get_users( array( 'role' => 'escola', 'meta_key' => 'billing_cnpj', 'meta_value' => $c, 'fields' => 'ID', 'number' => 1 ) ) : array(); // phpcs:ignore
		$uid = $ids ? (int) $ids[0] : 0;
	}
	$u = $uid ? get_userdata( $uid ) : false;
	if ( ! $u || user_can( $u, 'manage_options' ) ) {
		return 0;
	}
	$escola = in_array( 'escola', (array) $u->roles, true ) || '' !== (string) get_user_meta( $uid, 'omx_parent', true );
	return $escola ? (int) $uid : 0;
}

function dcom_al_criar( WP_REST_Request $r ) {
	$p = $r->get_json_params();
	if ( ! is_array( $p ) || ! $p ) {
		$p = $r->get_params();
	}
	$uid = dcom_al_achar( (array) $p );
	if ( ! $uid ) {
		return new WP_REST_Response( array( 'erro' => 'nao_encontrada', 'mensagem' => 'Franquia nao encontrada. Envie email, id ou cnpj.' ), 404 );
	}
	$tok  = wp_generate_password( 48, false, false );
	$dest = ( isset( $p['destino'] ) && 'loja' === $p['destino'] ) ? 'loja' : 'painel';
	set_transient( 'dcom_al_' . hash( 'sha256', $tok ), array( 'u' => $uid, 'd' => $dest ), DCOM_AL_SEGUNDOS );
	$extra = array(
		'url'             => add_query_arg( 'om_entrar', $tok, dcom_al_base() . '/' ),
		'expira_em'       => gmdate( 'c', time() + DCOM_AL_SEGUNDOS ),
		'valido_segundos' => DCOM_AL_SEGUNDOS,
		'uso_unico'       => true,
	);
	$dados = function_exists( 'dcom_api_franquia_json' ) ? dcom_api_franquia_json( $uid, $extra ) : array_merge( array( 'id' => $uid ), $extra );
	return new WP_REST_Response( $dados, 201 );
}

add_action(
	'rest_api_init',
	function () {
		if ( ! function_exists( 'dcom_api_ok' ) ) {
			return; // plugin dc-ouro-moderno desligado: sem chave, sem autologin.
		}
		register_rest_route(
			'ouromoderno/v1',
			'/franquias/entrar',
			array(
				array( 'methods' => 'POST', 'callback' => 'dcom_al_criar', 'permission_callback' => 'dcom_api_ok' ),
			)
		);
	}
);

/* Abertura do link: confere, apaga (uso unico), loga e manda para a Area do Franqueado. */
add_action(
	'init',
	function () {
		if ( empty( $_GET['om_entrar'] ) ) { // phpcs:ignore
			return;
		}
		nocache_headers();
		$tok  = sanitize_text_field( wp_unslash( $_GET['om_entrar'] ) ); // phpcs:ignore
		$key  = 'dcom_al_' . hash( 'sha256', $tok );
		$dado = get_transient( $key );
		delete_transient( $key );
		$base = dcom_al_base();
		if ( ! is_array( $dado ) || empty( $dado['u'] ) ) {
			wp_safe_redirect( $base . '/my-account-2/entrar-escola/?omx_msg=link-expirado' );
			exit;
		}
		$u = get_userdata( (int) $dado['u'] );
		if ( ! $u || user_can( $u, 'manage_options' ) ) {
			wp_safe_redirect( $base . '/my-account-2/entrar-escola/' );
			exit;
		}
		wp_clear_auth_cookie();
		wp_set_current_user( $u->ID );
		wp_set_auth_cookie( $u->ID, true );
		do_action( 'wp_login', $u->user_login, $u );
		update_user_meta( $u->ID, 'omx_autologin_em', current_time( 'mysql' ) );
		$dest = $base . '/my-account-2/';
		if ( 'loja' === $dado['d'] && function_exists( 'dcom_loja_url' ) ) {
			$l = (string) dcom_loja_url( $u->ID );
			if ( '' !== $l ) {
				$dest = $l;
			}
		}
		// Primeiro acesso (ou ainda falta logo): abre a tela de boas-vindas, que pede os logos (dc-ouro-boas-vindas.php).
		$prim = ! get_user_meta( $u->ID, 'omx_primeiro_acesso', true );
		if ( $prim ) {
			update_user_meta( $u->ID, 'omx_primeiro_acesso', current_time( 'mysql' ) );
		}
		if ( $prim || ( function_exists( 'dcbv_faltam' ) && dcbv_faltam( (int) $u->ID ) ) ) {
			$dest = $base . '/my-account-2/?boas-vindas=1';
		}
		wp_safe_redirect( $dest );
		exit;
	},
	1
);

/* O redirecionamento seguro precisa aceitar o dominio da Ouro Moderno. */
add_filter(
	'allowed_redirect_hosts',
	function ( $h ) {
		$h[] = 'ouromoderno.giftbrand.com.br';
		return $h;
	}
);
