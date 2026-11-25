<?php
/**
 * Area do Franqueado (Ouro Moderno): painel da unidade no modelo aprovado (barra no topo, menu lateral,
 * faixa de campanha, numeros, acesso rapido, Minha Escola e banner de brindes).
 * O franqueado e a propria unidade/escola (usuario com o papel 'escola' do cadastro omx_*), dentro de Minha conta.
 * Nao muda dados: so o visual, o menu e os cartoes da tela inicial. Blocos sem dados ainda mostram "em breve".
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Limpeza: o papel 'franqueado' criado numa versao anterior nao e usado (o franqueado e a propria unidade). */
add_action(
	'init',
	function () {
		if ( get_role( 'franqueado' ) && ! get_users( array( 'role' => 'franqueado', 'number' => 1, 'fields' => 'ID' ) ) ) {
			remove_role( 'franqueado' );
		}
	},
	6
);

/* Icones de linha (SVG) e logo desenhado em SVG (o PNG do servidor esta cortado). */
function dcomp_ico_paths( $n ) {
	$m = array(
		'home'     => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
		'coins'    => '<ellipse cx="9" cy="7" rx="6" ry="3"/><path d="M3 7v5c0 1.7 2.7 3 6 3s6-1.3 6-3V7"/><path d="M15 11c3.3 0 6 1.3 6 3v4c0 1.7-2.7 3-6 3-2 0-3.7-.5-4.8-1.2"/>',
		'building' => '<path d="M5 21V4h9v17M14 9h5v12M3 21h18"/><path d="M8 8h2M8 12h2M8 16h2M16 13h1M16 17h1"/>',
		'pencil'   => '<path d="M4 20l1-5L16 4l4 4L9 19z"/><path d="M14 6l4 4"/>',
		'gift'     => '<rect x="3" y="8" width="18" height="4"/><path d="M5 12v9h14v-9M12 8v13"/><path d="M12 8C10 3 6 4 7 6s4 2 5 2zM12 8c2-5 6-4 5-2s-4 2-5 2z"/>',
		'cap'      => '<path d="M12 4L2 9l10 5 10-5z"/><path d="M6 11.500V16c0 1.500 3 3 6 3s6-1.500 6-3v-4.500M22 9v6"/>',
		'mega'     => '<path d="M3 10v4h4l8 5V5L7 10z"/><path d="M18 9a4 4 0 010 6"/>',
		'users'    => '<circle cx="9" cy="8" r="3.500"/><path d="M2 20c0-3.500 3-6 7-6s7 2.500 7 6"/><circle cx="17" cy="9" r="2.500"/><path d="M17 14c3 0 5 2 5 5"/>',
		'cart'     => '<circle cx="9" cy="20" r="1.500"/><circle cx="18" cy="20" r="1.500"/><path d="M2 3h3l2.500 12h11l2-8H6"/>',
		'bars'     => '<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>',
		'doc'      => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h7M9 17h7"/>',
		'help'     => '<circle cx="12" cy="12" r="9"/><path d="M9.500 9.500a2.500 2.500 0 115 .5c0 1.500-2.500 2-2.500 3.500M12 17h.01"/>',
		'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.500-7 8-7s8 3 8 7"/>',
		'folder'   => '<path d="M3 6h6l2 2h10v11H3z"/>',
		'out'      => '<path d="M9 4H4v16h5M16 8l4 4-4 4M20 12H9"/>',
		'bell'     => '<path d="M6 9a6 6 0 0112 0c0 6 2 7 2 7H4s2-1 2-7"/><path d="M10 20a2 2 0 004 0"/>',
		'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.800"/><path d="M21 17l-5-5-9 8"/>',
	);
	return isset( $m[ $n ] ) ? $m[ $n ] : '';
}

function dcomp_ico( $n ) {
	return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . dcomp_ico_paths( $n ) . '</svg>';
}

function dcomp_ico_uri( $n, $cor = '#1C2433' ) {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="' . $cor . '" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . dcomp_ico_paths( $n ) . '</svg>';
	return 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
}

/* Logo oficial (midia 120226, original-1.webp, 1013x171). */
function dcomp_logo() {
	return '<div class="lg"><img src="https://divulgarcriacoes.com.br/wp-content/uploads/2026/11/original-1.webp" alt="Ouro Moderno" width="1013" height="171" style="display:block;width:260px;max-width:60vw;height:auto;flex:0 0 auto"></div>';
}

function dcomp_ativo() {
	return function_exists( 'omx_is_escola' ) && function_exists( 'is_account_page' ) && is_account_page() && is_user_logged_in() && omx_is_escola();
}

function dcomp_fr() {
	return function_exists( 'omx_is_escola' ) && is_user_logged_in() && omx_is_escola();
}

/* Link da loja para a unidade: a Loja do franqueado (ouromoderno.giftbrand.com.br/<unidade>/) quando aprovada; senao a Loja Ouro Moderno (que mostra "Cadastro em analise"). */
function dcomp_link_loja() {
	$uid = get_current_user_id();
	$p   = function_exists( 'omx_parent' ) ? (int) omx_parent( $uid ) : $uid;
	if ( 'aprovada' === (string) get_user_meta( $p, 'omx_status', true ) && function_exists( 'dcom_loja_url' ) ) {
		$u = dcom_loja_url( $p );
		if ( '' !== $u ) {
			return $u;
		}
	}
	return home_url( '/loja/?om=1' );
}

add_filter(
	'body_class',
	function ( $c ) {
		if ( dcomp_ativo() ) {
			$c[] = 'dcomp';
			if ( function_exists( 'is_wc_endpoint_url' ) && ! is_wc_endpoint_url() ) {
				$c[] = 'dcomp-dash';
			}
		}
		return $c;
	}
);

/* Menu lateral do franqueado. As chaves dcomp-* ainda nao tem tela: aparecem com "em breve" (ou abrem o catalogo). */
add_filter(
	'woocommerce_account_menu_items',
	function ( $items ) {
		if ( ! dcomp_fr() ) {
			return $items;
		}
		return array(
			'dashboard'        => 'Início',
			'escola-loja'      => 'Minha Escola',
			'dcomp-artes'      => 'Personalize seu produto',
			'dcomp-catalogo'   => 'Minha Loja',
			'dcomp-cursos'     => 'Cursos Ouro Moderno',
			'escola-campanhas' => 'Marketing e Campanhas',
			'escola-usuarios'  => 'Equipe de Trabalho',
			'orders'           => 'Orçamentos e Pedidos',
			'escola-compras'   => 'Resultados e Relatórios',
			'escola-condicoes' => 'Condições especiais',
			'escola-suporte'   => 'Suporte e Ajuda',
			'edit-account'     => 'Dados da conta',
			'customer-logout'  => 'Sair',
		);
	},
	40
);

add_filter(
	'woocommerce_get_endpoint_url',
	function ( $url, $endpoint ) {
		if ( 'dcomp-catalogo' === $endpoint ) {
			return dcomp_link_loja();
		}
		if ( 0 === strpos( (string) $endpoint, 'dcomp-' ) ) {
			return '#';
		}
		return $url;
	},
	10,
	2
);

add_action(
	'wp_head',
	function () {
		if ( ! dcomp_ativo() ) {
			return;
		}
		echo '<style id="dcomp-css">'
			. ':root{--dn:#0B1F3F;--dg:#C9A24A;--dgl:#F3E6C4}'
			. 'body.dcomp{background:#F4F5F8}'
			. '.dcomp .woocommerce{display:grid!important;grid-template-columns:268px minmax(0,1fr);gap:18px 26px;max-width:1360px;width:100%;margin:0 auto!important;padding:16px 20px 50px!important;box-sizing:border-box}'
			. '.dcomp .woocommerce>*{grid-column:2;min-width:0}'
			. '.dcomp .woocommerce>.dcomp-top{grid-column:1/-1}'
			. '.dcomp .woocommerce>.woocommerce-MyAccount-navigation{grid-column:1;grid-row:2/span 40}'
			/* barra do topo */
			. '.dcomp-top{background:linear-gradient(100deg,#07142b,var(--dn) 70%);color:#fff;border-radius:18px;padding:14px 26px;display:flex;align-items:center;gap:22px;flex-wrap:wrap;position:relative;overflow:hidden;box-shadow:0 10px 26px rgba(7,20,43,.25)}'
			. '.dcomp-top:after{content:"";position:absolute;right:210px;top:-60px;width:520px;height:120px;border-bottom:3px solid var(--dg);border-radius:50%;transform:rotate(-6deg);opacity:.85;pointer-events:none}'
			. '.dcomp-top .lg{display:flex;align-items:center;gap:12px;color:#fff}.dcomp-top .lg svg{width:62px;height:46px;flex:0 0 auto}.dcomp-top .lg b{display:block;font-size:31px;line-height:1;font-weight:800;letter-spacing:-.4px;white-space:nowrap}.dcomp-top .lg b sup{font-size:10px;font-weight:600;vertical-align:top;position:relative;top:2px;margin-left:2px}.dcomp-top .lg span{display:block;font-size:10px;letter-spacing:3.4px;color:#C9D3E8;margin-top:7px;white-space:nowrap}'
			. '.dcomp-top .tt{border-left:1px solid rgba(255,255,255,.3);padding-left:22px}.dcomp-top .tt b{display:block;font-size:22px;font-weight:700;line-height:1.1}.dcomp-top .tt span{font-size:13px;color:#C9D3E8}'
			. '.dcomp-top .us{margin-left:auto;display:flex;align-items:center;gap:12px;position:relative;z-index:1}.dcomp-top .av{width:46px;height:46px;border-radius:50%;background:#fff;color:var(--dn);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px}.dcomp-top .us b{display:block;font-size:14px}.dcomp-top .us span{font-size:12.5px;color:#C9D3E8}'
			/* menu lateral */
			. '.dcomp .woocommerce-MyAccount-navigation{float:none!important;width:auto!important;background:#fff;border:1px solid #E7E9F0;border-radius:18px;padding:14px;align-self:start;box-shadow:0 6px 18px rgba(11,31,63,.06)}'
			. '.dcomp .woocommerce-MyAccount-navigation ul{list-style:none;margin:0;padding:0}'
			. '.dcomp .woocommerce-MyAccount-navigation li{margin:0 0 3px;border:0}'
			. '.dcomp .woocommerce-MyAccount-navigation li a{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-radius:12px;color:#1C2433!important;font-weight:600;font-size:15px;text-decoration:none!important;border:0}'
			. '.dcomp .woocommerce-MyAccount-navigation li a:hover{background:#F1F3F8}'
			. '.dcomp .woocommerce-MyAccount-navigation li.is-active a{background:#E8EAF0;font-weight:800}'
			. '.dcomp .woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--customer-logout a{color:#7A1B1B!important}'
			. '.dcomp .woocommerce-MyAccount-content{float:none!important;width:auto!important;background:transparent;padding:0!important}'
			. '.dcomp .omx-box,.dcomp .woocommerce-MyAccount-content>table,.dcomp .woocommerce-orders-table{background:#fff;border:1px solid #E7E9F0;border-radius:16px;box-shadow:0 6px 18px rgba(11,31,63,.05)}'
			. '.dcomp-dash .woocommerce-MyAccount-content>p:nth-of-type(-n+2){display:none}'
			. '.dcomp-dash .woocommerce-MyAccount-content>.omx-box,.dcomp-dash .woocommerce-MyAccount-content>.omx-grid{display:none}'
			/* tela inicial */
			. '.dcomp-hi{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin:0 0 14px}.dcomp-hi h2{margin:0;font-size:clamp(26px,3vw,34px);font-weight:800;color:#101828}.dcomp-hi p{margin:2px 0 0;color:#5B6478;font-size:17px}.dcomp-hi .dt{background:#fff;border:1px solid #E7E9F0;border-radius:12px;padding:10px 16px;font-size:14px;font-weight:600;color:#344054}'
			. '.dcomp-ban{position:relative;border-radius:20px;overflow:hidden;min-height:240px;display:flex;align-items:center;padding:30px 36px;background:#0B1F3F url(https://divulgarcriacoes.com.br/wp-content/uploads/2026/11/FUNDO-SITE.jpg) right center/cover no-repeat;margin:0 0 14px}'
			. '.dcomp-ban:before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(7,20,43,.96) 0%,rgba(7,20,43,.88) 42%,rgba(7,20,43,.15) 100%)}'
			. '.dcomp-ban>div{position:relative;max-width:520px;color:#fff}.dcomp-ban h3{margin:0 0 8px;font-size:clamp(28px,3.4vw,42px);line-height:1.05;font-weight:800;color:#fff}.dcomp-ban h3 em{font-style:normal;color:var(--dg)}.dcomp-ban p{margin:0 0 16px;font-size:16px;color:#E3E8F3}'
			. '.dcomp-btn{display:inline-block;background:var(--dg);color:#0B1F3F!important;font-weight:800;border-radius:12px;padding:13px 24px;text-decoration:none!important}'
			. '.dcomp-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:0 0 20px}'
			. '.dcomp-kpi{background:#fff;border:1px solid #E7E9F0;border-radius:16px;padding:16px 18px;box-shadow:0 6px 18px rgba(11,31,63,.05)}.dcomp-kpi b{display:block;font-size:30px;line-height:1.1;color:#101828;font-weight:800}.dcomp-kpi span{font-size:14px;color:#667085}.dcomp-kpi small{display:block;color:#8A6D1F;font-size:11px;font-weight:700;margin-top:2px}'
			. '.dcomp-h{display:flex;align-items:center;gap:10px;margin:6px 0 12px;font-size:22px;font-weight:800;color:#101828}.dcomp-h:before{content:"";width:5px;height:24px;border-radius:3px;background:var(--dg)}'
			. '.dcomp-qa{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:0 0 22px}'
			. '.dcomp-qa a{display:block;background:#fff;border:1px solid #E7E9F0;border-radius:16px;padding:16px 18px;text-decoration:none!important;color:#101828!important;box-shadow:0 6px 18px rgba(11,31,63,.05)}.dcomp-qa a:hover{border-color:var(--dg)}'
			. '.dcomp-qa b{display:block;font-size:16px;margin:0 0 4px}.dcomp-qa span{font-size:13.5px;color:#667085;line-height:1.35}.dcomp-qa em{display:inline-block;margin-left:6px;font-style:normal;font-size:10px;font-weight:700;color:#8A6D1F;background:var(--dgl);border-radius:999px;padding:2px 8px;text-transform:uppercase;vertical-align:middle}'
			. '.dcomp-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:16px}'
			. '.dcomp-esc{background:#fff;border:1px solid #E7E9F0;border-radius:18px;padding:20px 22px;box-shadow:0 6px 18px rgba(11,31,63,.05)}.dcomp-esc h4{margin:0 0 6px;font-size:22px;font-weight:800;color:#101828}.dcomp-esc p{margin:4px 0;color:#344054;font-size:15px}.dcomp-esc .bt{display:inline-block;margin-top:12px;border:1px solid #D0D5DD;border-radius:10px;padding:9px 18px;font-weight:700;color:#101828!important;text-decoration:none!important}'
			. '.dcomp-pr{position:relative;border-radius:18px;overflow:hidden;min-height:200px;display:flex;align-items:center;justify-content:flex-end;padding:22px 26px;background:#F4EBD3 url(https://divulgarcriacoes.com.br/wp-content/uploads/2026/11/kit-boas-vindas.jpg) left center/auto 100% no-repeat}'
			. '.dcomp-pr>div{max-width:300px}.dcomp-pr h4{margin:0 0 6px;font-size:24px;font-weight:800;color:#101828;line-height:1.1}.dcomp-pr p{margin:0 0 12px;color:#344054;font-size:14.5px}'
			. '.dcomp .ic{width:22px;height:22px;flex:0 0 auto}'
			. '.dcomp-top .us .bt{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;background:rgba(255,255,255,.08)}'
			. '.dcomp .woocommerce-MyAccount-navigation li a{justify-content:flex-start!important;gap:12px}'
			. '.dcomp .woocommerce-MyAccount-navigation li a:before{content:"";width:22px;height:22px;flex:0 0 auto;background:var(--ic) center/contain no-repeat}'
			. '.dcomp .woocommerce-MyAccount-navigation li a:after{margin-left:auto!important}'
			. '.dcomp-kpi{display:flex;align-items:center;gap:14px}.dcomp-kpi .kt{width:46px;height:46px;border-radius:12px;background:#FBF5E4;color:#B98F34;display:flex;align-items:center;justify-content:center;flex:0 0 auto}.dcomp-kpi .kt .ic{width:26px;height:26px}.dcomp-kpi p{margin:0;min-width:0}'
			. '.dcomp-qa a{display:flex;gap:14px;align-items:flex-start}.dcomp-qa a .ic{width:30px;height:30px;color:#1C2433;margin-top:2px}.dcomp-qa a>div{min-width:0}'
			. '@media(max-width:1100px){.dcomp-kpis,.dcomp-qa{grid-template-columns:repeat(2,minmax(0,1fr))}.dcomp-row{grid-template-columns:1fr}}'
			. '@media(max-width:860px){.dcomp .woocommerce{grid-template-columns:1fr}.dcomp .woocommerce>*,.dcomp .woocommerce>.woocommerce-MyAccount-navigation{grid-column:1;grid-row:auto}.dcomp-top:after{display:none}.dcomp-top .us{margin-left:0}.dcomp-pr{justify-content:flex-start;padding-top:150px;background-size:auto 150px;background-position:center top}}'
			. '</style>'; // phpcs:ignore
		$mi = array( 'dashboard' => 'home', 'escola-loja' => 'building', 'dcomp-artes' => 'pencil', 'dcomp-catalogo' => 'gift', 'dcomp-cursos' => 'cap', 'escola-campanhas' => 'mega', 'escola-usuarios' => 'users', 'orders' => 'cart', 'escola-comissoes' => 'coins', 'escola-compras' => 'bars', 'escola-condicoes' => 'doc', 'escola-suporte' => 'help', 'edit-account' => 'user', 'customer-logout' => 'out' );
		$ic = '';
		foreach ( $mi as $ep => $nm ) {
			$ic .= '.dcomp .woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--' . $ep . ' a{--ic:url("' . dcomp_ico_uri( $nm ) . '")}';
		}
		echo '<style id="dcomp-ico">' . $ic . '</style>'; // phpcs:ignore
	},
	99
);

/* Barra do topo, em todas as abas do painel. */
add_action(
	'woocommerce_before_account_navigation',
	function () {
		if ( ! dcomp_ativo() || ! function_exists( 'omx_parent' ) ) {
			return;
		}
		$p    = omx_parent( get_current_user_id() );
		$nome = (string) get_user_meta( $p, 'omx_escola', true );
		$nome = '' !== $nome ? $nome : 'Unidade';
		$cid  = (string) get_user_meta( $p, 'omx_cidade', true );
		$ini  = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $nome, 0, 1 ) ) : strtoupper( substr( $nome, 0, 1 ) );
		echo '<div class="dcomp-top">' . dcomp_logo() . '<div class="tt"><b>&Aacute;rea do Franqueado</b><span>Central de Gest&atilde;o, Marketing e Compras</span></div>' // phpcs:ignore
			. '<div class="us"><span class="bt" title="Avisos">' . dcomp_ico( 'bell' ) . '</span><span class="bt" title="Ajuda">' . dcomp_ico( 'help' ) . '</span><span class="av">' . esc_html( $ini ) . '</span><div><b>' . esc_html( $nome ) . '</b><span>' . esc_html( '' !== $cid ? $cid : 'Ouro Moderno' ) . '</span></div></div></div>'; // phpcs:ignore
	},
	1
);

/* Tela inicial do franqueado. */
add_action(
	'woocommerce_account_dashboard',
	function () {
		if ( ! dcomp_ativo() || ! function_exists( 'omx_parent' ) || ! function_exists( 'wc_get_orders' ) ) {
			return;
		}
		$uid  = get_current_user_id();
		$p    = omx_parent( $uid );
		$nome = (string) get_user_meta( $p, 'omx_escola', true );
		$nome = '' !== $nome ? $nome : 'Unidade';
		$cid  = (string) get_user_meta( $p, 'omx_cidade', true );
		$tel  = (string) get_user_meta( $p, 'omx_tel', true );
		$st   = (string) get_user_meta( $p, 'omx_status', true );
		$url  = function_exists( 'dcom_loja_url' ) ? dcom_loja_url( $uid ) : '';
		$ids  = function_exists( 'omx_group_ids' ) ? (array) omx_group_ids( $uid ) : array( $uid );

		// Valor em compras (brindes): soma dos pedidos da unidade que nao foram cancelados.
		$total = 0.0;
		$vistos = array();
		foreach ( (array) wc_get_orders( array( 'customer' => $ids, 'limit' => 500, 'status' => array( 'pending', 'processing', 'on-hold', 'completed' ) ) ) as $o ) {
			$total += (float) $o->get_total();
			$vistos[ $o->get_id() ] = 1;
		}
		// Pedidos feitos pela loja da franquia por outros compradores.
		if ( function_exists( 'dcom_pedidos_marcados' ) ) {
			foreach ( dcom_pedidos_marcados( $p, 0, array( 'pending', 'processing', 'on-hold', 'completed' ) ) as $o ) {
				if ( empty( $vistos[ $o->get_id() ] ) ) {
					$total += (float) $o->get_total();
				}
			}
		}
		$ep = function ( $e ) {
			return esc_url( wc_get_account_endpoint_url( $e ) );
		};
		$qa = array(
			array( 'Minha Escola', 'Dados, logotipo, endereço e redes sociais', $ep( 'escola-loja' ), '', 'building' ),
			array( 'Personalize seu produto', 'Escolha o produto, o curso e monte a arte', '#', 'em breve', 'pencil' ),
			array( 'Minha Loja', 'A loja da sua unidade, com todos os produtos', esc_url( dcomp_link_loja() ), '', 'gift' ),
			array( 'Cursos Ouro Moderno', 'Materiais e campanhas de cada curso', '#', 'em breve', 'cap' ),
			array( 'Equipe de Trabalho', 'Cadastrar colaboradores e definir permissões', $ep( 'escola-usuarios' ), '', 'users' ),
			array( 'Marketing e Campanhas', 'Campanhas da rede e materiais para baixar', $ep( 'escola-campanhas' ), '', 'mega' ),
			array( 'Orçamentos e Pedidos', 'Solicite, aprove e acompanhe suas compras', $ep( 'orders' ), '', 'cart' ),
			array( 'Resultados e Relatórios', 'Leads, matrículas e desempenho da unidade', $ep( 'escola-compras' ), '', 'bars' ),
		);

		echo '<div class="dcomp-hi"><div><h2>Ol&aacute;, ' . esc_html( $nome ) . '!</h2><p>Bem-vindo &agrave; sua central de gest&atilde;o, marketing e brindes.</p></div><div class="dt">' . esc_html( wp_date( 'D, d \d\e F \d\e Y' ) ) . '</div></div>'; // phpcs:ignore
		$ban = function_exists( 'dcom_campanha_banner_html' ) ? dcom_campanha_banner_html() : '';
		echo '' !== $ban ? $ban : '<div class="dcomp-ban"><div><h3>Campanha de <br>Matr&iacute;culas <em>' . esc_html( wp_date( 'Y' ) ) . '</em></h3><p>Gere artes, divulgue seus cursos, use brindes personalizados e conquiste mais alunos.</p><a class="dcomp-btn" href="/meu-orcamento/">Criar minha campanha &rarr;</a></div></div>'; // phpcs:ignore
		echo '<div class="dcomp-kpis">' // phpcs:ignore
			. '<div class="dcomp-kpi"><i class="kt">' . dcomp_ico( 'users' ) . '</i><p><b>&mdash;</b><span>Leads recebidos</span><small>em breve</small></p></div>'
			. '<div class="dcomp-kpi"><i class="kt">' . dcomp_ico( 'cap' ) . '</i><p><b>&mdash;</b><span>Matr&iacute;culas realizadas</span><small>em breve</small></p></div>'
			. '<div class="dcomp-kpi"><i class="kt">' . dcomp_ico( 'cart' ) . '</i><p><b>R$ ' . esc_html( number_format( $total, 2, ',', '.' ) ) . '</b><span>Em compras (brindes)</span></p></div>'
			. ( function_exists( 'dcom_com_kpi' ) ? dcom_com_kpi( $p ) : '<div class="dcomp-kpi"><i class="kt">' . dcomp_ico( 'image' ) . '</i><p><b>&mdash;</b><span>Artes produzidas</span><small>em breve</small></p></div>' ) . '</div>';
		echo '<div class="dcomp-h">Acesso r&aacute;pido</div><div class="dcomp-qa">'; // phpcs:ignore
		foreach ( $qa as $q ) {
			echo '<a href="' . $q[2] . '">' . dcomp_ico( $q[4] ) . '<div><b>' . esc_html( $q[0] ) . ( '' !== $q[3] ? '<em>' . esc_html( $q[3] ) . '</em>' : '' ) . '</b><span>' . esc_html( $q[1] ) . '</span></div></a>'; // phpcs:ignore
		}
		echo '</div>'; // phpcs:ignore
		$info = '<h4>' . esc_html( $nome ) . '</h4>' . ( '' !== $cid ? '<p>' . esc_html( $cid ) . '</p>' : '' ) . ( '' !== $tel ? '<p>WhatsApp: ' . esc_html( $tel ) . '</p>' : '' )
			. ( ( 'aprovada' === $st && '' !== $url ) ? '<p>Sua loja: <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a></p>' : '<p>Cadastro em an&aacute;lise.</p>' );
		echo '<div class="dcomp-row"><div><div class="dcomp-h">Minha Escola</div><div class="dcomp-esc">' . $info . '<a class="bt" href="' . $ep( 'escola-loja' ) . '">Editar dados</a></div></div>' // phpcs:ignore
			. '<div class="dcomp-pr"><div><h4>Brindes que fortalecem sua marca</h4><p>Conhe&ccedil;a a linha completa da Loja Ouro Moderno e personalize com a sua unidade.</p><a class="dcomp-btn" href="' . esc_url( dcomp_link_loja() ) . '">Abrir minha loja &rarr;</a></div></div></div>'; // phpcs:ignore
	},
	3
);

/* Area do Franqueado em pagina propria (sem o bloco Minha conta da Divulgar). */
require_once __DIR__ . '/franqueado-pagina.php';

/* Loja de cada escola em ouromoderno.giftbrand.com.br/<endereco-da-escola>/. */
require_once __DIR__ . '/loja-escola.php';

/* Logo e banner da loja, enviados pela unidade na aba Minha loja. */
require_once __DIR__ . '/identidade.php';

/* Imagens por franquia (arte da escola no produto). */
require_once __DIR__ . '/fotos-franquia.php';

/* Pedidos marcados com a franquia e endereco/CNPJ da franquia no cadastro e no checkout. */
require_once __DIR__ . '/pedidos-franquia.php';
require_once __DIR__ . '/endereco-franquia.php';

/* API para o sistema da rede cadastrar franquias (POST/GET /wp-json/ouromoderno/v1/franquias). */
require_once __DIR__ . '/api.php';

/* Comissoes: so a Matriz Ouro Moderno ve (Painel da Matriz > Comissoes). O franqueado nao tem comissao nem acesso a ela:
   a aba "Minhas Comissoes" (comissoes-franquia.php) foi desligada em 12/11/2026. */
require_once __DIR__ . '/comissoes.php';

/* Quem abrir o endereco antigo da aba de comissoes volta para o Inicio. */
add_action(
	'template_redirect',
	function () {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore
		if ( false !== strpos( $uri, '/escola-comissoes' ) && function_exists( 'wc_get_page_permalink' ) ) {
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}
	},
	-10
);

/* Campanhas da Rede e Materiais e Artes (aba Marketing e Campanhas). */
require_once __DIR__ . '/campanhas-franquia.php';

/* "Minha Loja" (menu, atalho e botao do inicio) abre a loja em uma nova aba; o painel continua aberto.
   Chamado pela pagina propria da Area do Franqueado (franqueado-pagina.php, que nao roda o wp_footer) e pelo wp_footer. */
function dcomp_loja_links_js() {
	static $feito = false;
	if ( $feito || ! dcomp_ativo() ) {
		return;
	}
	$feito = true;
		$u = wp_json_encode( untrailingslashit( dcomp_link_loja() ) );
		// Botoes "Fazer um orcamento" / "Ver catalogo" da aba Pedidos (vem do snippet #109112): tamanho de botao, texto centralizado.
		// Os dois (e o "Criar minha campanha" do inicio) levam a loja da escola, em nova aba: no endereco da Ouro, /meu-orcamento/ cai na pagina da Ouro.
		echo '<style id="dcomp-omxbtn">.dcomp .woocommerce-MyAccount-content p:has(>a.omx-btn){display:flex;flex-wrap:wrap;gap:10px;margin:0 0 18px}'
			. '.dcomp .woocommerce-MyAccount-content a.omx-btn{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:auto!important;height:auto!important;min-height:46px;padding:12px 22px!important;margin:0!important;border-radius:12px!important;line-height:1.2!important;font-weight:800!important;font-size:15px!important;text-align:center!important;box-shadow:none!important;background:#F5B400!important;color:#0B2A5B!important;border:0!important}'
			. '.dcomp .woocommerce-MyAccount-content a.omx-btn+a.omx-btn{background:#fff!important;border:2px solid #0B2A5B!important}</style>'; // phpcs:ignore
		echo '<script>(function(){var u=' . $u . ';document.querySelectorAll("a[href=\\"/#catalogo\\"]").forEach(function(a){a.href=u+"/?omc=todas";a.target="_blank";a.rel="noopener";});document.querySelectorAll("a[href=\\"/meu-orcamento/\\"]").forEach(function(a){a.href=u+"/";});function n(h){return String(h||"").replace(/\/+$/,"");}document.querySelectorAll("a[href]").forEach(function(a){if(n(a.getAttribute("href"))===u||a.closest(".woocommerce-MyAccount-navigation-link--dcomp-catalogo")){a.target="_blank";a.rel="noopener";}});})();</script>'; // phpcs:ignore
}
add_action( 'wp_footer', 'dcomp_loja_links_js', 99 );
