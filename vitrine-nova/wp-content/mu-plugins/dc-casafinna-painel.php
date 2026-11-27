<?php
/**
 * Painel da marca Casafinna (casafinna.giftbrand.com.br/painel-casafinna/): o cliente entra com login e senha dele e sobe os logos.
 * Perfil "Casafinna (marca)": não vê o painel do WordPress, só este painel. O administrador também abre o painel.
 * Guarda os logos na mesma opção do menu "Casafinna" do WordPress (dccf_marca_logos). Sem nada da Ouro Moderno.
 * Para criar o acesso do cliente: Usuários > Adicionar novo > perfil "Casafinna (marca)".
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dccf_painel_url() {
	return home_url( '/painel-casafinna/' );
}

function dccf_painel_pode( $u = null ) {
	$u = $u ? $u : wp_get_current_user();
	return $u && $u->exists() && ( user_can( $u, 'manage_options' ) || in_array( 'casafinna_marca', (array) $u->roles, true ) );
}

function dccf_so_marca( $u = null ) {
	$u = $u ? $u : wp_get_current_user();
	return $u && $u->exists() && ! user_can( $u, 'manage_options' ) && in_array( 'casafinna_marca', (array) $u->roles, true );
}

/* Perfil novo (criado uma vez). */
add_action( 'init', function () {
	if ( ! get_role( 'casafinna_marca' ) ) {
		add_role( 'casafinna_marca', 'Casafinna (marca)', array( 'read' => true ) );
	}
}, 5 );

/* O cliente da marca nunca vê o painel do WordPress nem a barra de administração; o login leva ao painel dele. */
add_filter( 'show_admin_bar', function ( $s ) {
	return dccf_so_marca() ? false : $s;
}, 30 );
add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || ! dccf_so_marca() ) {
		return;
	}
	wp_safe_redirect( dccf_painel_url() );
	exit;
}, 1 );
add_filter( 'login_redirect', function ( $to, $req, $user ) {
	return ( $user instanceof WP_User && dccf_so_marca( $user ) ) ? dccf_painel_url() : $to;
}, 30, 3 );
add_filter( 'woocommerce_login_redirect', function ( $to, $user ) {
	return ( $user instanceof WP_User && dccf_so_marca( $user ) ) ? dccf_painel_url() : $to;
}, 30, 2 );

/* Envio de um logo: só PNG, JPG ou WebP, até 8 MB. Devolve o ID do arquivo ou um texto de erro. */
function dccf_enviar_logo( $campo ) {
	if ( empty( $_FILES[ $campo ]['name'] ) || ! empty( $_FILES[ $campo ]['error'] ) ) { // phpcs:ignore
		return '';
	}
	if ( (int) $_FILES[ $campo ]['size'] > 8 * 1024 * 1024 ) { // phpcs:ignore
		return 'O arquivo passa de 8 MB.';
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$tipos = function () {
		return array( 'png' => 'image/png', 'jpg|jpeg' => 'image/jpeg', 'webp' => 'image/webp' );
	};
	add_filter( 'upload_mimes', $tipos, 99 );
	$ck = wp_check_filetype_and_ext( (string) $_FILES[ $campo ]['tmp_name'], (string) $_FILES[ $campo ]['name'], $tipos() ); // phpcs:ignore
	if ( empty( $ck['type'] ) ) {
		remove_filter( 'upload_mimes', $tipos, 99 );
		return 'Envie o logo em PNG, JPG ou WebP.';
	}
	$id = media_handle_upload( $campo, 0, array( 'post_title' => 'Logo Casafinna ' . $campo ), array( 'test_form' => false, 'mimes' => $tipos() ) );
	remove_filter( 'upload_mimes', $tipos, 99 );
	return is_wp_error( $id ) ? 'Não foi possível enviar: ' . $id->get_error_message() : (int) $id;
}

/* A página /painel-casafinna/ (login obrigatório). */
add_action( 'template_redirect', function () {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore
	$path = untrailingslashit( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
	if ( '/painel-casafinna' !== $path ) {
		return;
	}
	nocache_headers();
	if ( ! is_user_logged_in() ) {
		$login = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
		wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( dccf_painel_url() ), $login ) );
		exit;
	}
	if ( ! dccf_painel_pode() ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	$slots = dccf_logo_slots();
	$sv    = dccf_logos_salvos();
	$msg   = '';
	$erro  = '';
	if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
		if ( ! isset( $_POST['dccf_pn'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dccf_pn'] ) ), 'dccf_painel' ) ) {
			$erro = 'A página expirou. Atualize e tente de novo.';
		} else {
			foreach ( array_keys( $slots ) as $k ) {
				if ( ! empty( $_POST[ 'tirar_' . $k ] ) ) {
					unset( $sv[ $k ] );
					continue;
				}
				$r = dccf_enviar_logo( $k );
				if ( is_int( $r ) && $r > 0 ) {
					$sv[ $k ] = $r;
				} elseif ( is_string( $r ) && '' !== $r ) {
					$erro = $r;
				}
			}
			update_option( 'dccf_marca_logos', $sv, false );
			$msg = '' === $erro ? 'Logos salvos.' : '';
		}
	}

	$feitos = count( $sv );
	$obrig  = array( 'omx_logo', 'omx_logo_branco' );
	$faltam = array();
	foreach ( $obrig as $k ) {
		if ( empty( $sv[ $k ] ) ) {
			$faltam[] = $slots[ $k ][0];
		}
	}
	$logo_site = 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/10/imgi_37_casa-logo4-2.png';
	$cards     = '';
	foreach ( $slots as $k => $s ) {
		$id  = isset( $sv[ $k ] ) ? (int) $sv[ $k ] : 0;
		$url = $id ? (string) wp_get_attachment_image_url( $id, 'medium' ) : '';
		if ( $id && '' === $url ) {
			$url = (string) wp_get_attachment_url( $id );
		}
		$obr    = in_array( $k, $obrig, true );
		$cards .= '<div class="c"><h3>' . esc_html( $s[0] ) . ( $obr ? ' <span class="ob">obrigatório</span>' : '' ) . '</h3><p>' . esc_html( $s[1] ) . '</p>'
			. '<div class="v ' . esc_attr( $s[2] ) . '">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '<em>Nenhum arquivo</em>' ) . '</div>'
			. '<label class="bt">' . ( $url ? 'Trocar o arquivo' : 'Escolher o arquivo' ) . '<input type="file" name="' . esc_attr( $k ) . '" accept="image/png,image/jpeg,image/webp"></label>'
			. ( $url ? '<label class="tx"><input type="checkbox" name="tirar_' . esc_attr( $k ) . '" value="1"> Tirar este logo</label>' : '' ) . '</div>';
	}

	status_header( 200 );
	header( 'Content-Type: text/html; charset=UTF-8' );
	echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Painel Casafinna</title>'
		. '<style>*{box-sizing:border-box}body{margin:0;background:#F7F3EC;color:#14110D;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;font-size:15px}'
		. '.top{background:#14110D;padding:14px 22px;display:flex;align-items:center;gap:16px}.top img{height:54px;width:auto}.top .sp{flex:1}.top a{color:#EBBA63;text-decoration:none;font-weight:600;font-size:14px}'
		. '.w{max-width:1040px;margin:0 auto;padding:26px 18px 50px}h1{font-size:26px;margin:0 0 6px}.sub{color:#5B5448;line-height:1.55;margin:0 0 20px}'
		. '.ok{background:#ECFDF3;border-left:4px solid #12805C;padding:11px 14px;border-radius:8px;margin:0 0 16px}.er{background:#FEF3F2;border-left:4px solid #B42318;padding:11px 14px;border-radius:8px;margin:0 0 16px;color:#912018}'
		. '.al{background:#FFF8E6;border-left:4px solid #D4AF37;padding:11px 14px;border-radius:8px;margin:0 0 16px}'
		. '.g{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}.c{background:#fff;border:1px solid #E8DFCF;border-radius:16px;padding:16px}'
		. '.c h3{margin:0 0 4px;font-size:16px}.c p{margin:0 0 12px;color:#6B6B64;font-size:13px}.ob{background:#14110D;color:#EBBA63;font-size:11px;padding:2px 8px;border-radius:99px;margin-left:6px;vertical-align:2px}'
		. '.v{height:130px;border-radius:12px;border:1px dashed #D5C9B3;display:flex;align-items:center;justify-content:center;overflow:hidden;margin:0 0 12px}.v.claro{background:#fff}.v.escuro{background:#14110D}.v img{max-width:86%;max-height:86%;object-fit:contain}.v em{color:#9A9384;font-size:13px;font-style:normal}'
		. '.bt{display:block;text-align:center;background:#D4AF37;color:#14110D;font-weight:700;border-radius:10px;padding:11px;cursor:pointer}.bt input{display:none}.tx{display:block;margin-top:10px;font-size:13px;color:#6B6B64}'
		. '.sv{margin-top:20px}.sv button{background:#14110D;color:#EBBA63;border:0;border-radius:12px;padding:15px 34px;font-size:16px;font-weight:700;cursor:pointer}.nm{font-size:12.5px;color:#6B6B64;margin-top:6px}'
		. '</style></head><body><div class="top"><img src="' . esc_url( $logo_site ) . '" alt="Casafinna"><span class="sp"></span><a href="' . esc_url( home_url( '/' ) ) . '">Ver a loja</a><a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Sair</a></div>'
		. '<div class="w"><h1>Logos da Casafinna <span style="font-size:14px;color:#6B6B64;font-weight:600">(' . (int) $feitos . ' de 6 enviados)</span></h1>'
		. '<p class="sub">Envie os logos da marca. A loja usa o logo certo em cada produto (fundo claro ou escuro, formato horizontal ou vertical). Os dois <b>obrigatórios</b> são o horizontal colorido e o horizontal branco. Use <b>PNG com fundo transparente</b>; JPG deixa uma caixa branca em volta.</p>'
		. ( '' !== $msg ? '<div class="ok">' . esc_html( $msg ) . '</div>' : '' )
		. ( '' !== $erro ? '<div class="er">' . esc_html( $erro ) . '</div>' : '' )
		. ( $faltam ? '<div class="al">Ainda falta enviar: <b>' . esc_html( implode( ', ', $faltam ) ) . '</b>.</div>' : '' )
		. '<form method="post" enctype="multipart/form-data">' . wp_nonce_field( 'dccf_painel', 'dccf_pn', true, false ) . '<div class="g">' . $cards . '</div>'
		. '<div class="sv"><button type="submit">Salvar logos</button><div class="nm">Escolha os arquivos e clique em salvar. Pode enviar vários de uma vez.</div></div></form></div>'
		. '<script>document.querySelectorAll(".bt input").forEach(function(i){i.addEventListener("change",function(){var l=i.parentNode;if(i.files&&i.files[0]){l.firstChild.nodeValue=i.files[0].name;}});});</script></body></html>'; // phpcs:ignore
	exit;
}, 1 );
