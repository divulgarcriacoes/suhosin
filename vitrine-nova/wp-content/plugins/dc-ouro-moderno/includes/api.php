<?php
/**
 * API da Ouro Moderno para o sistema da rede cadastrar franquias direto no portal.
 *   POST /wp-json/ouromoderno/v1/franquias          cadastra (ou devolve a que ja existe com o mesmo e-mail)
 *   GET  /wp-json/ouromoderno/v1/franquias?email=   consulta a situacao (tambem ?id= ou ?cnpj=)
 * Autenticacao: cabecalho  X-Ouro-Key: <chave>  (a chave fica no Painel da Matriz > Configuracoes).
 * Documento para a equipe tecnica: docs/API-OURO-MODERNO.md.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dcom_api_chave() {
	$k = (string) get_option( 'dcom_api_key' );
	if ( '' === $k ) {
		$k = wp_generate_password( 40, false, false );
		update_option( 'dcom_api_key', $k, false );
	}
	return $k;
}

function dcom_api_ok( WP_REST_Request $r ) {
	$k = (string) $r->get_header( 'x_ouro_key' );
	if ( '' === $k ) {
		$a = (string) $r->get_header( 'authorization' );
		if ( 0 === stripos( $a, 'Bearer ' ) ) {
			$k = trim( substr( $a, 7 ) );
		}
	}
	return '' !== $k && hash_equals( dcom_api_chave(), $k );
}

function dcom_api_situacao_txt( $st ) {
	$m = array( 'aprovada' => 'aprovada', 'pendente' => 'em_analise', 'suspensa' => 'reprovada' );
	return isset( $m[ $st ] ) ? $m[ $st ] : 'em_analise';
}

/* Resposta padrao com os dados da franquia. */
function dcom_api_franquia_json( $uid, $extra = array() ) {
	$u  = get_userdata( $uid );
	$st = (string) get_user_meta( $uid, 'omx_status', true );
	$d  = array(
		'id'         => (int) $uid,
		'escola'     => (string) get_user_meta( $uid, 'omx_escola', true ),
		'email'      => $u ? $u->user_email : '',
		'cnpj'       => (string) get_user_meta( $uid, 'omx_cnpj', true ),
		'cidade'     => (string) get_user_meta( $uid, 'omx_cidade', true ),
		'situacao'   => dcom_api_situacao_txt( $st ),
		'endereco'   => (string) get_user_meta( $uid, 'omx_slug', true ),
		'loja_url'   => function_exists( 'dcom_loja_url' ) ? dcom_loja_url( $uid ) : '',
		'loja_ativa' => 'aprovada' === $st,
		'entrar_url' => 'https://ouromoderno.giftbrand.com.br/my-account-2/entrar-escola/',
		'criada_em'  => $u ? mysql_to_rfc3339( $u->user_registered ) : '',
	);
	return array_merge( $d, $extra );
}

/* Link para a escola criar a senha (WooCommerce > Minha conta > Nova senha). */
function dcom_api_link_senha( $uid ) {
	$u   = get_userdata( $uid );
	$key = $u ? get_password_reset_key( $u ) : '';
	if ( ! $u || is_wp_error( $key ) || '' === $key ) {
		return '';
	}
	$base = 'https://ouromoderno.giftbrand.com.br/my-account-2/lost-password/';
	return add_query_arg( array( 'key' => $key, 'id' => (int) $uid ), $base );
}

function dcom_api_email_boas_vindas( $uid, $link ) {
	$u = get_userdata( $uid );
	if ( ! $u || '' === $link ) {
		return;
	}
	$esc = (string) get_user_meta( $uid, 'omx_escola', true );
	$bt  = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 12px"><tr><td style="border-radius:12px;background:#F5B400"><a href="' . esc_url( $link ) . '" target="_blank" style="display:inline-block;padding:15px 34px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;color:#0B2A5B;text-decoration:none;border-radius:12px">Criar minha senha</a></td></tr></table>';
	$html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#EEF1F6"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EEF1F6"><tr><td align="center" style="padding:28px 12px">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden">'
		. '<tr><td align="center" style="background:#0B2A5B;padding:26px 20px"><img src="https://divulgarcriacoes.com.br/wp-content/uploads/2026/11/original-1.webp" width="240" alt="Ouro Moderno" style="display:block;width:240px;max-width:80%;height:auto;border:0"></td></tr>'
		. '<tr><td style="height:5px;background:#F5B400;font-size:0;line-height:0">&nbsp;</td></tr>'
		. '<tr><td style="padding:34px 32px 26px;font-family:Arial,Helvetica,sans-serif;color:#1C2433;text-align:center">'
		. '<div style="font-size:24px;font-weight:bold;color:#0B2A5B;margin:0 0 12px">Bem-vindo ao portal Ouro Moderno!</div>'
		. '<div style="font-size:16px;line-height:1.6;color:#344054;margin:0 0 22px">O cadastro da <b>' . esc_html( $esc ) . '</b> foi criado. Para entrar na &Aacute;rea do Franqueado, crie a sua senha:</div>'
		. $bt . '<div style="font-size:13px;color:#667085;margin:14px 0 0">Seu acesso &eacute; o e-mail <b>' . esc_html( $u->user_email ) . '</b>.</div>'
		. '</td></tr><tr><td align="center" style="padding:0 20px 26px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#98A2B3">Ouro Moderno &mdash; Desenvolvido por Giftbrand</td></tr></table></td></tr></table></body></html>';
	wp_mail( $u->user_email, '[Ouro Moderno] Crie sua senha de acesso', $html, array( 'Content-Type: text/html; charset=UTF-8', 'From: Ouro Moderno <' . sanitize_email( (string) get_option( 'admin_email' ) ) . '>' ) );
}

/* Endereco unico da loja a partir do nome da escola. */
function dcom_api_slug( $nome, $pedido = '' ) {
	$base = sanitize_title( '' !== $pedido ? $pedido : $nome );
	$base = '' !== $base ? $base : 'escola';
	$res  = array( 'www', 'ouromoderno', 'ambient', 'casafinna', 'admin', 'mail', 'my-account-2', 'loja', 'carrinho', 'checkout', 'painel-matriz', 'ouro-moderno', 'ouro-moderno-loja', 'produto', 'wp-admin', 'wp-login', 'feed' );
	$slug = $base;
	$i    = 2;
	while ( in_array( $slug, $res, true ) || get_page_by_path( $slug ) || get_users( array( 'meta_key' => 'omx_slug', 'meta_value' => $slug, 'fields' => 'ID', 'number' => 1 ) ) ) { // phpcs:ignore
		$slug = $base . '-' . $i;
		$i++;
	}
	return $slug;
}

function dcom_api_cadastrar( WP_REST_Request $r ) {
	$p = $r->get_json_params();
	if ( ! is_array( $p ) || ! $p ) {
		$p = $r->get_body_params();
	}
	$g = function ( $k ) use ( $p ) {
		return isset( $p[ $k ] ) ? sanitize_text_field( (string) $p[ $k ] ) : '';
	};
	$esc  = $g( 'escola' );
	$resp = $g( 'responsavel' );
	$mail = sanitize_email( $g( 'email' ) );
	$tel  = $g( 'telefone' );
	$cid  = $g( 'cidade' );
	$uf   = strtoupper( substr( $g( 'uf' ), 0, 2 ) );
	$falta = array();
	foreach ( array( 'escola' => $esc, 'responsavel' => $resp, 'email' => $mail, 'telefone' => $tel, 'cidade' => $cid, 'uf' => $uf ) as $k => $v ) {
		if ( '' === $v ) {
			$falta[] = $k;
		}
	}
	if ( $mail && ! is_email( $mail ) ) {
		$falta[] = 'email (invalido)';
	}
	if ( $falta ) {
		return new WP_REST_Response( array( 'erro' => 'campos_obrigatorios', 'mensagem' => 'Faltam campos obrigatorios.', 'campos' => $falta ), 422 );
	}
	$ex = get_user_by( 'email', $mail );
	if ( ! $ex ) {
		$ex = get_user_by( 'login', $mail );
	}
	if ( $ex ) {
		if ( in_array( 'escola', (array) $ex->roles, true ) ) {
			return new WP_REST_Response( array_merge( array( 'erro' => 'ja_cadastrada', 'mensagem' => 'Ja existe uma franquia com este e-mail.' ), dcom_api_franquia_json( $ex->ID ) ), 409 );
		}
		return new WP_REST_Response( array( 'erro' => 'email_em_uso', 'mensagem' => 'Este e-mail ja tem uma conta no site que nao e de franquia.' ), 409 );
	}
	$slug = dcom_api_slug( $esc, $g( 'endereco' ) );
	$uid  = wp_insert_user(
		array(
			'user_login'   => $mail,
			'user_email'   => $mail,
			'user_pass'    => wp_generate_password( 24 ),
			'display_name' => $resp,
			'first_name'   => explode( ' ', $resp, 2 )[0],
			'last_name'    => isset( explode( ' ', $resp, 2 )[1] ) ? explode( ' ', $resp, 2 )[1] : '',
			'role'         => 'escola',
		)
	);
	if ( is_wp_error( $uid ) ) {
		return new WP_REST_Response( array( 'erro' => 'nao_criado', 'mensagem' => $uid->get_error_message() ), 500 );
	}
	$cnpj = preg_replace( '/\D/', '', $g( 'cnpj' ) );
	$meta = array(
		'omx_escola' => $esc,
		'omx_cargo'  => $g( 'cargo' ),
		'omx_tel'    => $tel,
		'omx_cidade' => $cid . ' - ' . $uf,
		'omx_cnpj'   => $g( 'cnpj' ),
		'omx_tipo'   => $g( 'tipo' ),
		'omx_alunos' => $g( 'alunos' ),
		'omx_origem' => 'API Ouro Moderno',
		'omx_slug'   => $slug,
		'omx_status' => 'pendente',
		'omx_api'    => current_time( 'mysql' ),
		'omx_api_ref' => $g( 'referencia' ),
	);
	foreach ( $meta as $k => $v ) {
		if ( '' !== $v ) {
			update_user_meta( $uid, $k, $v );
		}
	}
	$end = array(
		'postcode'     => preg_replace( '/\D/', '', $g( 'cep' ) ),
		'address_1'    => $g( 'rua' ),
		'number'       => $g( 'numero' ),
		'address_2'    => $g( 'complemento' ),
		'neighborhood' => $g( 'bairro' ),
		'city'         => $cid,
		'state'        => $uf,
		'country'      => 'BR',
		'company'      => $esc,
	);
	foreach ( array( 'billing', 'shipping' ) as $t ) {
		foreach ( $end as $k => $v ) {
			if ( '' !== $v ) {
				update_user_meta( $uid, $t . '_' . $k, $v );
			}
		}
	}
	update_user_meta( $uid, 'billing_phone', $tel );
	update_user_meta( $uid, 'billing_email', $mail );
	$pn = explode( ' ', $resp, 2 );
	update_user_meta( $uid, 'billing_first_name', $pn[0] );
	update_user_meta( $uid, 'shipping_first_name', $pn[0] );
	if ( isset( $pn[1] ) ) {
		update_user_meta( $uid, 'billing_last_name', $pn[1] );
		update_user_meta( $uid, 'shipping_last_name', $pn[1] );
	}
	if ( '' !== $cnpj ) {
		update_user_meta( $uid, 'billing_cnpj', $cnpj );
		update_user_meta( $uid, 'billing_persontype', '2' );
	}

	// Ja aprovada? (opcao no Painel da Matriz > Configuracoes)
	if ( '1' === (string) get_option( 'dcom_api_aprova' ) ) {
		update_user_meta( $uid, 'omx_status', 'aprovada' );
		update_user_meta( $uid, 'omx_aprovada_em', current_time( 'mysql' ) );
		if ( function_exists( 'dcom_loja_garantir_cat' ) ) {
			dcom_loja_garantir_cat( $uid );
		}
	}
	delete_transient( 'omx_cfg_cache' );

	$link = dcom_api_link_senha( $uid );
	if ( ! isset( $p['enviar_email'] ) || ! in_array( strtolower( (string) $p['enviar_email'] ), array( '0', 'false', 'nao', 'não' ), true ) ) {
		dcom_api_email_boas_vindas( $uid, $link );
	}
	wp_mail( get_option( 'admin_email' ), '[Ouro Moderno] Nova franquia pela API: ' . $esc, "Franquia: $esc\nResponsavel: $resp\nE-mail: $mail\nWhatsApp: $tel\nCidade: $cid - $uf\nCNPJ: " . $g( 'cnpj' ) . "\nSituacao: " . get_user_meta( $uid, 'omx_status', true ) . "\n\n" . ( 'aprovada' === get_user_meta( $uid, 'omx_status', true ) ? 'Ja aprovada automaticamente (cadastro pela API). Ver em Painel da Matriz > Franquias.' : 'Aprovar no Painel da Matriz > Franquias.' ) );

	return new WP_REST_Response( dcom_api_franquia_json( $uid, array( 'criar_senha_url' => $link ) ), 201 );
}

function dcom_api_consultar( WP_REST_Request $r ) {
	$uid = absint( $r->get_param( 'id' ) );
	if ( ! $uid && $r->get_param( 'email' ) ) {
		$u   = get_user_by( 'email', sanitize_email( (string) $r->get_param( 'email' ) ) );
		$uid = $u ? (int) $u->ID : 0;
	}
	if ( ! $uid && $r->get_param( 'cnpj' ) ) {
		$c   = preg_replace( '/\D/', '', (string) $r->get_param( 'cnpj' ) );
		$ids = get_users( array( 'role' => 'escola', 'meta_key' => 'billing_cnpj', 'meta_value' => $c, 'fields' => 'ID', 'number' => 1 ) ); // phpcs:ignore
		$uid = $ids ? (int) $ids[0] : 0;
	}
	$u = $uid ? get_userdata( $uid ) : false;
	if ( ! $u || ! in_array( 'escola', (array) $u->roles, true ) ) {
		return new WP_REST_Response( array( 'erro' => 'nao_encontrada', 'mensagem' => 'Franquia nao encontrada.' ), 404 );
	}
	return new WP_REST_Response( dcom_api_franquia_json( $uid ), 200 );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ouromoderno/v1',
			'/franquias',
			array(
				array( 'methods' => 'POST', 'callback' => 'dcom_api_cadastrar', 'permission_callback' => 'dcom_api_ok' ),
				array( 'methods' => 'GET', 'callback' => 'dcom_api_consultar', 'permission_callback' => 'dcom_api_ok' ),
			)
		);
	}
);

/*
 * Registro de acessos: cada chamada a /ouromoderno/v1/ (com chave certa ou errada) fica guardada (ultimas 200).
 * Guarda data, metodo, resultado, IP e a escola/e-mail. A chave nunca e gravada.
 */
function dcom_api_ip() {
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $h ) {
		if ( ! empty( $_SERVER[ $h ] ) ) {
			$ip = trim( explode( ',', (string) wp_unslash( $_SERVER[ $h ] ) )[0] ); // phpcs:ignore
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
	}
	return '';
}

add_filter(
	'rest_post_dispatch',
	function ( $res, $server, $req ) {
		if ( ! ( $req instanceof WP_REST_Request ) || 0 !== strpos( (string) $req->get_route(), '/ouromoderno/v1' ) ) {
			return $res;
		}
		$cod  = ( $res instanceof WP_HTTP_Response ) ? (int) $res->get_status() : 0;
		$data = ( $res instanceof WP_HTTP_Response ) ? $res->get_data() : array();
		$data = is_array( $data ) ? $data : array();
		$quem = '';
		foreach ( array( 'escola', 'email', 'id', 'cnpj' ) as $k ) {
			$v = $req->get_param( $k );
			if ( is_scalar( $v ) && '' !== (string) $v ) {
				$quem = ( 'id' === $k ? 'id ' : '' ) . sanitize_text_field( (string) $v );
				break;
			}
		}
		if ( 'escola' === $k && $req->get_param( 'email' ) ) {
			$quem .= ' (' . sanitize_email( (string) $req->get_param( 'email' ) ) . ')';
		}
		$erro = isset( $data['erro'] ) ? (string) $data['erro'] : ( isset( $data['code'] ) ? (string) $data['code'] : '' );
		$log  = get_option( 'dcom_api_log' );
		$log  = is_array( $log ) ? $log : array();
		array_unshift(
			$log,
			array(
				't'  => time(),
				'm'  => $req->get_method(),
				'c'  => $cod,
				'e'  => sanitize_key( $erro ),
				'q'  => mb_substr( $quem, 0, 140 ),
				'ip' => dcom_api_ip(),
				'id' => isset( $data['id'] ) ? (int) $data['id'] : 0,
			)
		);
		update_option( 'dcom_api_log', array_slice( $log, 0, 200 ), false );
		return $res;
	},
	10,
	3
);

/* Quadro "Acessos da API": ultimo acesso, contagem e a lista das ultimas chamadas. */
function dcom_api_log_html() {
	$log = get_option( 'dcom_api_log' );
	$log = is_array( $log ) ? $log : array();
	$ok  = 0;
	$neg = 0;
	foreach ( $log as $l ) {
		if ( (int) $l['c'] >= 200 && (int) $l['c'] < 300 ) {
			$ok++;
		} elseif ( 401 === (int) $l['c'] || 403 === (int) $l['c'] ) {
			$neg++;
		}
	}
	$cad = get_users( array( 'meta_key' => 'omx_origem', 'meta_value' => 'API Ouro Moderno', 'fields' => 'ID', 'count_total' => false, 'number' => 500 ) ); // phpcs:ignore
	$rot = array( 'POST' => 'Cadastro', 'GET' => 'Consulta' );
	$res = function ( $l ) {
		$c = (int) $l['c'];
		if ( 201 === $c ) {
			return array( 'Franquia criada', 'en' );
		}
		if ( 200 === $c ) {
			return array( 'OK', 'en' );
		}
		if ( 401 === $c || 403 === $c ) {
			return array( 'Chave errada ou ausente', 'rp' );
		}
		$m = array( 'ja_cadastrada' => 'J&aacute; cadastrada', 'email_em_uso' => 'E-mail em uso', 'campos_obrigatorios' => 'Faltam campos', 'nao_encontrada' => 'N&atilde;o encontrada', 'nao_criado' => 'Erro ao criar' );
		return array( isset( $m[ $l['e'] ] ) ? $m[ $l['e'] ] : 'Erro ' . $c, 'pr' );
	};
	$tr = '';
	foreach ( array_slice( $log, 0, 30 ) as $l ) {
		list( $tx, $cl ) = $res( $l );
		$tr .= '<tr><td style="white-space:nowrap">' . esc_html( wp_date( 'd/m/Y H:i', (int) $l['t'] ) ) . '</td><td>' . esc_html( isset( $rot[ $l['m'] ] ) ? $rot[ $l['m'] ] : $l['m'] ) . '</td><td>' . esc_html( (string) $l['q'] ) . '</td><td><span class="st ' . esc_attr( $cl ) . '">' . $tx . '</span></td><td style="color:#667085">' . esc_html( (string) $l['ip'] ) . '</td></tr>'; // phpcs:ignore
	}
	$ult = $log ? sprintf( '%s (h&aacute; %s)', esc_html( wp_date( 'd/m/Y \à\s H:i', (int) $log[0]['t'] ) ), esc_html( human_time_diff( (int) $log[0]['t'] ) ) ) : 'nenhum acesso ainda';
	return '<style>.st.rp{background:#FEE4E2;color:#912018}</style><div class="dcmx-cfg" style="margin-top:16px;max-width:980px"><h3 style="margin:0 0 6px">Acessos da API</h3>'
		. '<p style="margin:0 0 12px;color:#5B6478">Cada chamada do sistema da Ouro Moderno fica registrada aqui (as &uacute;ltimas 200), inclusive as recusadas por chave errada.</p>'
		. '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:0 0 14px">'
		. '<span style="background:#F4F5F8;border-radius:10px;padding:10px 14px"><b>&Uacute;ltimo acesso:</b> ' . $ult . '</span>'
		. '<span style="background:#D1FADF;color:#05603A;border-radius:10px;padding:10px 14px"><b>' . (int) $ok . '</b> com sucesso</span>'
		. '<span style="background:#FEE4E2;color:#B42318;border-radius:10px;padding:10px 14px"><b>' . (int) $neg . '</b> recusados (chave)</span>'
		. '<span style="background:#F3E6C4;color:#0B1F3F;border-radius:10px;padding:10px 14px"><b>' . count( (array) $cad ) . '</b> franquias cadastradas pela API</span></div>'
		. ( '' !== $tr ? '<div style="overflow-x:auto"><table class="dcmx-t"><thead><tr><th>Quando</th><th>Tipo</th><th>Escola / e-mail</th><th>Resultado</th><th>IP</th></tr></thead><tbody>' . $tr . '</tbody></table></div>' : '<p style="margin:0">O sistema da Ouro Moderno ainda n&atilde;o chamou a API.</p>' )
		. '</div>';
}

/* Quadro "API para o sistema da rede" em Painel da Matriz > Configuracoes (salva aqui mesmo). */
function dcom_api_config_html() {
	if ( ! current_user_can( 'dcmx_config' ) ) {
		return '';
	}
	$msg = '';
	if ( isset( $_POST['dcom_api_n'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcom_api_n'] ) ), 'dcom_api' ) ) {
		if ( ! empty( $_POST['dcom_api_nova'] ) ) {
			update_option( 'dcom_api_key', wp_generate_password( 40, false, false ), false );
			$msg = 'Chave nova gerada. A chave antiga parou de funcionar.';
		} else {
			update_option( 'dcom_api_aprova', empty( $_POST['dcom_api_aprova'] ) ? '0' : '1', false );
			$msg = 'Configura&ccedil;&atilde;o da API salva.';
		}
	}
	$url = rest_url( 'ouromoderno/v1/franquias' );
	return '<form method="post" class="dcmx-cfg" style="margin-top:16px;max-width:760px">' . wp_nonce_field( 'dcom_api', 'dcom_api_n', true, false )
		. '<h3 style="margin:0 0 6px">API para o sistema da rede</h3>'
		. ( '' !== $msg ? '<div class="notice"><p>' . $msg . '</p></div>' : '' )
		. '<p style="margin:0 0 10px;color:#5B6478">O sistema da Ouro Moderno usa estes dados para cadastrar franquias direto no portal. Passe para a equipe t&eacute;cnica junto com o documento da API.</p>'
		. '<p><strong>Endere&ccedil;o</strong><br><code style="user-select:all">' . esc_html( $url ) . '</code></p>'
		. '<p><strong>Chave (cabe&ccedil;alho X-Ouro-Key)</strong><br><code style="user-select:all">' . esc_html( dcom_api_chave() ) . '</code></p>'
		. '<p><label><input type="checkbox" name="dcom_api_aprova" value="1"' . checked( '1', (string) get_option( 'dcom_api_aprova' ), false ) . '> Cadastros pela API j&aacute; entram <b>aprovados</b> (sem passar pela aprova&ccedil;&atilde;o em Franquias)</label></p>'
		. '<p><button class="button button-primary" type="submit">Salvar</button> <button class="button" type="submit" name="dcom_api_nova" value="1" onclick="return confirm(\'Gerar chave nova? A chave atual para de funcionar no sistema da rede.\');">Gerar chave nova</button></p></form>' . dcom_api_log_html();
}
