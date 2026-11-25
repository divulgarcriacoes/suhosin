<?php
/**
 * Plugin Name: DC · Ouro Moderno: e-mails das escolas com a marca da loja
 * Description: 2026-11-24. Todo e-mail que vai para uma escola (franquia Ouro Moderno) ou sai do domínio ouromoderno.giftbrand.com.br
 *   troca "Divulgar Criações" pela marca da loja (remetente, assunto e texto) e os links do domínio da Divulgar pelo da loja.
 *   Para mudar o nome: define( 'DCOE_MARCA', '...' ) no wp-config ou edite abaixo.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'DCOE_MARCA' ) ) {
	define( 'DCOE_MARCA', 'GiftBrand' );
}

/* O e-mail é para uma escola (ou alguém da equipe dela)? */
function dcoe_para_escola( $to ) {
	$lista = is_array( $to ) ? $to : explode( ',', (string) $to );
	foreach ( $lista as $e ) {
		$e = trim( (string) $e );
		if ( preg_match( '/<([^>]+)>/', $e, $m ) ) {
			$e = $m[1];
		}
		$u = is_email( $e ) ? get_user_by( 'email', $e ) : false;
		if ( $u && ( in_array( 'escola', (array) $u->roles, true ) || '' !== (string) get_user_meta( $u->ID, 'omx_escola', true ) || (int) get_user_meta( $u->ID, 'omx_parent', true ) > 0 ) ) {
			return true;
		}
	}
	return false;
}

function dcoe_host_ouro() {
	$h = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) $_SERVER['HTTP_HOST'] ) : ''; // phpcs:ignore
	return false !== strpos( $h, 'ouromoderno.giftbrand.com.br' );
}

function dcoe_troca( $txt ) {
	$txt = str_replace( array( 'Divulgar Criações', 'Divulgar Criacoes', 'Divulgar Criaçoes', 'DIVULGAR CRIAÇÕES', 'DIVULGAR CRIACOES', 'Divulgar Criac&otilde;es', 'Divulgar Cria&ccedil;&otilde;es' ), DCOE_MARCA, (string) $txt );
	return str_replace( array( 'https://divulgarcriacoes.com.br', 'http://divulgarcriacoes.com.br', 'https://www.divulgarcriacoes.com.br' ), 'https://ouromoderno.giftbrand.com.br', $txt );
}

$GLOBALS['dcoe_ativo'] = false;

/* Marca o envio (o filtro wp_mail roda antes dos filtros de remetente). */
add_filter(
	'wp_mail',
	function ( $a ) {
		$GLOBALS['dcoe_ativo'] = false;
		if ( empty( $a['to'] ) || ! ( dcoe_para_escola( $a['to'] ) || dcoe_host_ouro() ) ) {
			return $a;
		}
		// Nunca mexe em e-mail para a equipe/administração.
		$adm = (string) get_option( 'admin_email' );
		if ( '' !== $adm && false !== stripos( is_array( $a['to'] ) ? implode( ',', $a['to'] ) : (string) $a['to'], $adm ) ) {
			return $a;
		}
		$GLOBALS['dcoe_ativo'] = true;
		$a['subject'] = dcoe_troca( $a['subject'] );
		$a['message'] = dcoe_troca( $a['message'] );
		if ( ! empty( $a['headers'] ) ) {
			$a['headers'] = is_array( $a['headers'] ) ? array_map( 'dcoe_troca', $a['headers'] ) : dcoe_troca( $a['headers'] );
		}
		if ( false !== strpos( (string) $a['message'], 'dcoe-layout' ) ) {
			return $a; // já montado
		}
		// Primeiro acesso: o "redefinir senha" vira o e-mail de boas-vindas.
		$u = dcoe_usuario( $a['to'] );
		if ( $u && ! get_user_meta( $u->ID, 'omx_primeiro_acesso', true ) && preg_match( '/redefini|senha|password/i', (string) $a['subject'] ) && preg_match( '/href=["\']([^"\']*(?:lost-password|key=)[^"\']*)["\']/i', (string) $a['message'], $mm ) ) {
			$a['subject'] = 'Bem-vinda à sua loja de brindes, ' . dcoe_nome_escola( $u ) . '!';
			$a['message'] = dcoe_html_boas_vindas( $u, html_entity_decode( $mm[1] ) );
		} else {
			$a['message'] = dcoe_reveste( (string) $a['subject'], (string) $a['message'] );
		}
		$a['headers'] = dcoe_header_html( isset( $a['headers'] ) ? $a['headers'] : array() );
		return $a;
	},
	999
);

add_filter(
	'wp_mail_from_name',
	function ( $n ) {
		return ! empty( $GLOBALS['dcoe_ativo'] ) ? DCOE_MARCA : $n;
	},
	999
);

/* WooCommerce define o remetente pelos próprios filtros. */
add_filter(
	'woocommerce_email_from_name',
	function ( $n, $email = null ) {
		$to = ( is_object( $email ) && ! empty( $email->recipient ) ) ? $email->recipient : '';
		if ( ( '' !== $to && dcoe_para_escola( $to ) ) || dcoe_host_ouro() ) {
			return DCOE_MARCA;
		}
		return $n;
	},
	999,
	2
);

/* Limpa a marca depois do envio. */
add_action(
	'wp_mail_succeeded',
	function () {
		$GLOBALS['dcoe_ativo'] = false;
	}
);
add_action(
	'wp_mail_failed',
	function () {
		$GLOBALS['dcoe_ativo'] = false;
	}
);

/* ---------- Layout dos e-mails das escolas ---------- */

function dcoe_usuario( $to ) {
	$lista = is_array( $to ) ? $to : explode( ',', (string) $to );
	foreach ( $lista as $e ) {
		$e = trim( (string) $e );
		if ( preg_match( '/<([^>]+)>/', $e, $m ) ) {
			$e = $m[1];
		}
		$u = is_email( $e ) ? get_user_by( 'email', $e ) : false;
		if ( $u ) {
			return $u;
		}
	}
	return null;
}

function dcoe_nome_escola( $u ) {
	$p = function_exists( 'omx_parent' ) ? (int) omx_parent( $u->ID ) : (int) $u->ID;
	$n = (string) get_user_meta( $p ? $p : $u->ID, 'omx_escola', true );
	return '' !== $n ? $n : 'sua escola';
}

function dcoe_header_html( $h ) {
	$h = is_array( $h ) ? $h : array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', (string) $h ) ) ) );
	$h = array_values(
		array_filter(
			$h,
			function ( $x ) {
				return 0 !== stripos( (string) $x, 'content-type:' );
			}
		)
	);
	$h[] = 'Content-Type: text/html; charset=UTF-8';
	return $h;
}

function dcoe_botao( $url, $txt ) {
	return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0"><tr><td style="background:#F5B400;border-radius:12px"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:15px 28px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;color:#0B2A5B;text-decoration:none">' . $txt . '</a></td></tr></table>';
}

function dcoe_layout( $titulo, $conteudo ) {
	$base = 'https://ouromoderno.giftbrand.com.br';
	$gb   = $base . '/wp-content/uploads/2026/10/logo-gift-brand-vertical-branco-300x199.png';
	$om   = $base . '/wp-content/uploads/2026/11/ouro-moderno-email-branco.png';
	return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( wp_strip_all_tags( $titulo ) ) . '</title>'
		. '<style>.dcoe-c a{color:#0B2A5B;font-weight:bold}.dcoe-c p{margin:0 0 14px}.dcoe-c table.td,.dcoe-c .td{border:1px solid #EAECF0;border-collapse:collapse}.dcoe-c th,.dcoe-c td.td{padding:8px 10px;text-align:left}@media(max-width:620px){.dcoe-w{width:100%!important}.dcoe-p{padding-left:22px!important;padding-right:22px!important}.dcoe-om{display:none!important}}</style></head>'
		. '<body style="margin:0;padding:0;background:#EEF1F6"><!-- dcoe-layout -->'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EEF1F6"><tr><td align="center" style="padding:28px 12px">'
		. '<table role="presentation" class="dcoe-w" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#FFFFFF;border-radius:18px;overflow:hidden;font-family:Arial,Helvetica,sans-serif">'
		. '<tr><td class="dcoe-p" style="background:#0B2A5B;padding:22px 32px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
		. '<td valign="middle"><a href="' . esc_url( $base ) . '"><img src="' . esc_url( $gb ) . '" width="120" alt="GiftBrand" style="display:block;width:120px;height:auto;border:0"></a></td>'
		. '<td class="dcoe-om" valign="middle" align="right" style="color:#C9D3E8;font-size:11px;letter-spacing:.5px">em parceria com<br><img src="' . esc_url( $om ) . '" width="160" alt="Ouro Moderno" style="display:inline-block;width:160px;height:auto;border:0;margin-top:6px"></td>'
		. '</tr></table></td></tr>'
		. '<tr><td style="height:5px;line-height:5px;font-size:0;background:#F5B400">&nbsp;</td></tr>'
		. '<tr><td class="dcoe-p dcoe-c" style="padding:34px 38px 18px;color:#344054;font-size:15px;line-height:1.6">'
		. '<h1 style="margin:0 0 18px;color:#0B2A5B;font-size:26px;line-height:1.25;font-family:Arial,Helvetica,sans-serif">' . $titulo . '</h1>'
		. $conteudo
		. '</td></tr>'
		. '<tr><td class="dcoe-p" style="padding:20px 38px 26px;background:#F8FAFC;border-top:1px solid #EEF0F4;color:#667085;font-size:12.5px;line-height:1.6">'
		. '<b style="color:#0B2A5B">GiftBrand</b> &middot; Seu negócio de brindes, pronto para vender.<br>Loja oficial de brindes da rede Ouro Moderno &middot; <a href="' . esc_url( $base ) . '" style="color:#0B2A5B">ouromoderno.giftbrand.com.br</a>'
		. '</td></tr></table></td></tr></table></body></html>';
}

/* Reveste um e-mail qualquer (WooCommerce ou texto simples) com o layout. */
function dcoe_reveste( $assunto, $msg ) {
	if ( preg_match( '/<html|<body|<p[\s>]|<table/i', $msg ) ) {
		$tit = preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $msg, $m ) ? trim( wp_strip_all_tags( $m[1] ) ) : $assunto;
		if ( preg_match( '/<div[^>]*id=["\']body_content_inner["\'][^>]*>(.*)<\/div>\s*<\/td>/is', $msg, $m ) ) {
			$corpo = $m[1];
		} elseif ( preg_match( '/<body[^>]*>(.*)<\/body>/is', $msg, $m ) ) {
			$corpo = $m[1];
		} else {
			$corpo = $msg;
		}
		$corpo = preg_replace( '/<h1[^>]*>.*?<\/h1>/is', '', $corpo, 1 );
		return dcoe_layout( esc_html( $tit ), $corpo );
	}
	return dcoe_layout( esc_html( $assunto ), wpautop( make_clickable( esc_html( $msg ) ) ) );
}

/* E-mail de boas-vindas (primeiro acesso). */
function dcoe_html_boas_vindas( $u, $link ) {
	$escola = dcoe_nome_escola( $u );
	$nome   = trim( (string) get_user_meta( $u->ID, 'first_name', true ) );
	$c      = '<p>Olá' . ( '' !== $nome ? ', ' . esc_html( $nome ) : '' ) . '!</p>'
		. '<p>A loja de brindes da <b>' . esc_html( $escola ) . '</b> está pronta, em parceria com a rede <b>Ouro Moderno</b>. Lá você encontra cadernos, canetas, squeezes, sacolas, power banks e muitos outros brindes, <b>todos com a marca da sua escola</b>.</p>'
		. '<p><b>Você entra na sua loja direto pelo sistema da Ouro Moderno</b>, com entrada automática: não precisa de senha.</p>'
		. dcoe_botao( dcoe_loja_url( $u ), 'Ver minha loja' )
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FFFBEB;border:1px solid #FEDF89;border-radius:12px"><tr><td style="padding:14px 16px;font-size:14px;color:#7A2E0E;line-height:1.55"><b>No primeiro acesso</b> vamos pedir o logo da escola (horizontal e vertical, colorido e branco). Com ele, todos os produtos da sua loja já aparecem com a sua marca. Não tem o logo em boa qualidade? Nossa equipe GiftBrand faz para você.</td></tr></table>'
		. '<p style="margin-top:18px;font-size:13px;color:#667085">Quer entrar também direto pelo site, sem passar pela Ouro Moderno? <a href="' . esc_url( $link ) . '" style="color:#0B2A5B">Crie uma senha aqui</a> (opcional). Seu login é <b>' . esc_html( $u->user_email ) . '</b>. O link vale por 24 horas.</p>';
	return dcoe_layout( 'Bem-vinda à sua loja de brindes!', $c );
}

function dcoe_loja_url( $u ) {
	$p = function_exists( 'omx_parent' ) ? (int) omx_parent( $u->ID ) : (int) $u->ID;
	$l = function_exists( 'dcom_loja_url' ) ? (string) dcom_loja_url( $p ? $p : $u->ID ) : '';
	return '' !== $l ? $l : 'https://ouromoderno.giftbrand.com.br/my-account-2/';
}
