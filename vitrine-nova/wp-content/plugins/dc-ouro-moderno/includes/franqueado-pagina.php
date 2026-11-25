<?php
/**
 * Area do Franqueado em pagina propria: a "Minha conta" do site usa o bloco da Divulgar (cartao roxo "Ola, ..." com atalhos),
 * que esconde o conteudo da conta e o menu. Para a franquia (usuario 'escola') a Minha conta abre direto o painel dela:
 * barra Ouro Moderno, menu lateral do franqueado e o conteudo de cada aba, sem o cabecalho/rodape do site.
 * Pedidos enviados (salvar dados, enderecos) continuam com o WooCommerce: a pagina so assume nas visitas (GET).
 * Desligar: define( 'DCOMP_PAGINA_MODE', 'off' ) no wp-config.php.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Cabecalho limpo para as paginas proprias do Ouro Moderno que usam wp_head(): tira o titulo do WordPress
 * ("Page Not Found - Divulgar Criacoes") e o icone da Divulgar, e coloca o titulo e o icone da pagina.
 */
function dcom_head_limpo( $titulo, $letra = 'O' ) {
	global $wp_query;
	if ( $wp_query ) {
		$wp_query->is_404 = false;
	}
	remove_action( 'wp_head', '_wp_render_title_tag', 1 );
	remove_action( 'wp_head', 'wp_site_icon', 99 );
	remove_action( 'wp_head', 'dcpt_favicon_head', 99 );
	add_filter( 'get_site_icon_url', '__return_empty_string', 999 );
	$l   = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( (string) $letra, 0, 1 ) ) : strtoupper( substr( (string) $letra, 0, 1 ) );
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="32" r="31" fill="#F5B400"/><text x="32" y="43" font-family="Arial,Helvetica,sans-serif" font-size="32" font-weight="700" text-anchor="middle" fill="#0B2A5B">' . esc_html( '' !== $l ? $l : 'O' ) . '</text></svg>';
	echo '<title>' . esc_html( $titulo ) . '</title><link rel="icon" href="data:image/svg+xml,' . rawurlencode( $svg ) . '">'; // phpcs:ignore
}

add_action(
	'template_redirect',
	function () {
		if ( defined( 'DCOMP_PAGINA_MODE' ) && 'off' === DCOMP_PAGINA_MODE ) {
			return;
		}
		if ( 'GET' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET' ) ) { // phpcs:ignore
			return;
		}
		if ( isset( $_GET['omx_msg'] ) && 'ok-cadastro' === $_GET['omx_msg'] ) { // phpcs:ignore -- tela de parabens (entrar.php)
			return;
		}
		if ( ! function_exists( 'dcomp_ativo' ) || ! dcomp_ativo() ) {
			return;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'customer-logout' ) || is_wc_endpoint_url( 'lost-password' ) ) ) {
			return;
		}

		// O painel antigo (aba escola-painel) virou a tela Inicio do franqueado.
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'escola-painel' ) && empty( $_GET['omx_msg'] ) ) { // phpcs:ignore
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}
		$dash = function_exists( 'is_wc_endpoint_url' ) && ! is_wc_endpoint_url();
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">';
		$nm_u = function_exists( 'omx_parent' ) ? (string) get_user_meta( omx_parent( get_current_user_id() ), 'omx_escola', true ) : '';
		dcom_head_limpo( 'Área do Franqueado' . ( '' !== $nm_u ? ' · ' . $nm_u : '' ) . ' · Ouro Moderno', '' !== $nm_u ? $nm_u : 'O' );
		wp_head();
		// Icones do menu claros (menu azul escuro, como o Painel da Matriz); no item ativo, escuros.
		$icn = '';
		if ( function_exists( 'dcomp_ico_uri' ) ) {
			$mi = array( 'dashboard' => 'home', 'escola-loja' => 'building', 'dcomp-artes' => 'pencil', 'dcomp-catalogo' => 'gift', 'dcomp-cursos' => 'cap', 'escola-campanhas' => 'mega', 'escola-usuarios' => 'users', 'orders' => 'cart', 'escola-comissoes' => 'coins', 'escola-compras' => 'bars', 'escola-condicoes' => 'doc', 'escola-suporte' => 'help', 'edit-account' => 'user', 'customer-logout' => 'out' );
			foreach ( $mi as $ep => $nm ) {
				$sel  = '.dcomp .dcfr-cols .woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--' . $ep;
				$icn .= $sel . ' a{--ic:url("' . dcomp_ico_uri( $nm, '#D7DFEE' ) . '")}' . $sel . '.is-active a{--ic:url("' . dcomp_ico_uri( $nm, '#0B1F3F' ) . '")}';
			}
		}
		echo '<style id="dcomp-pagina">html,body{margin:0!important;padding:0!important}body.dcomp{background:#F4F5F8!important;font-family:Poppins,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:#101828}'
			. '.dcomp .woocommerce{padding-top:18px!important;display:grid!important;grid-template-columns:300px minmax(0,1fr)!important;align-items:start}'
			. '.dcomp .woocommerce>.dcomp-top{grid-column:1/-1!important;grid-row:1!important}'
			. '.dcomp .woocommerce>.woocommerce-MyAccount-navigation{grid-column:1!important;grid-row:2!important}'
			. '.dcomp .woocommerce>.woocommerce-MyAccount-content{grid-column:2!important;grid-row:2!important}'
			. '.dcomp .woocommerce-MyAccount-navigation li a{white-space:nowrap}'
			. '.dcfr{max-width:none;margin:0;padding:0;box-sizing:border-box}'
			. '.dcfr>.dcomp-top{border-radius:0!important;box-shadow:none!important;margin:0!important;padding:14px 26px!important}'
			. '.dcfr-cols{display:flex;gap:0;align-items:stretch;margin:0;min-height:calc(100vh - 76px)}'
			. '.dcfr-cols>.woocommerce-MyAccount-navigation{flex:0 0 300px;width:300px!important;float:none!important;margin:0!important;box-sizing:border-box;background:#0B1F3F!important;border:0!important;border-radius:0!important;box-shadow:none!important;padding:14px 10px!important;align-self:stretch!important}'
			. '.dcomp .dcfr-cols .woocommerce-MyAccount-navigation li a{color:#D7DFEE!important;font-weight:600!important;font-size:14.5px!important;padding:12px 14px!important;border-radius:10px!important;background:transparent}'
			. '.dcomp .dcfr-cols .woocommerce-MyAccount-navigation li a:hover{background:rgba(255,255,255,.08)!important;color:#fff!important}'
			. '.dcomp .dcfr-cols .woocommerce-MyAccount-navigation li.is-active a{background:linear-gradient(90deg,#B98F34,#D9B45A)!important;color:#0B1F3F!important;font-weight:700!important}'
			. '.dcomp .dcfr-cols .woocommerce-MyAccount-navigation li a:after{display:none!important}'
			. '.dcfr-cols>.woocommerce-MyAccount-content{flex:1 1 auto;min-width:0;width:auto!important;float:none!important;margin:0!important;padding:22px 26px 40px!important;box-sizing:border-box}'
			. '@media(max-width:860px){.dcfr-cols{flex-direction:column;min-height:0}.dcfr-cols>.woocommerce-MyAccount-navigation{flex:none;width:100%!important}.dcfr-cols>.woocommerce-MyAccount-content{padding:16px!important}}'
			. $icn
			. '.dcomp .woocommerce>.woocommerce-MyAccount-navigation{float:none!important;width:auto!important;margin:0!important;position:static!important}'
			. '.dcomp .woocommerce>.woocommerce-MyAccount-content{float:none!important;width:auto!important;margin:0!important}'
			. '.dcomp .woocommerce{max-width:1360px!important;width:100%!important;margin:0 auto!important;padding:18px 20px 40px!important;box-sizing:border-box;gap:18px 26px!important}'
			. '@media(max-width:860px){.dcomp .woocommerce{grid-template-columns:1fr!important}.dcomp .woocommerce>.woocommerce-MyAccount-navigation,.dcomp .woocommerce>.woocommerce-MyAccount-content{grid-column:1!important;grid-row:auto!important}}'
			. '.dcomp .woocommerce-MyAccount-content{min-width:0}.dcomp .woocommerce-MyAccount-content .omx-box{padding:18px 20px}'
			. '.dcomp .woocommerce-MyAccount-content table{width:100%;border-collapse:collapse}.dcomp .woocommerce-MyAccount-content th,.dcomp .woocommerce-MyAccount-content td{padding:10px 12px;border-bottom:1px solid #F0F1F5;text-align:left}'
			. '.dcomp .woocommerce-MyAccount-content input[type=text],.dcomp .woocommerce-MyAccount-content input[type=email],.dcomp .woocommerce-MyAccount-content input[type=tel],.dcomp .woocommerce-MyAccount-content input[type=password],.dcomp .woocommerce-MyAccount-content select,.dcomp .woocommerce-MyAccount-content textarea{border:1px solid #D0D5DD;border-radius:10px;padding:10px 12px;min-height:42px;box-sizing:border-box;max-width:100%}'
			. '.dcomp .woocommerce-MyAccount-content .button,.dcomp .woocommerce-MyAccount-content button[type=submit]{background:#C9A24A;color:#0B1F3F;border:0;border-radius:10px;padding:11px 20px;font-weight:800;cursor:pointer;text-decoration:none}'
			. '.om-ft{text-align:center;font-size:12.5px;color:#667085;padding:10px 12px 22px}.om-ft a{color:#B98F34;font-weight:600;text-decoration:none}'
			. '</style></head><body class="woocommerce-account woocommerce-page dcomp' . ( $dash ? ' dcomp-dash' : '' ) . '"><div class="dcfr">';
		// Barra do topo e menu montados aqui (sem o modelo de navegacao do tema, que mudava a ordem e o tamanho).
		ob_start();
		do_action( 'woocommerce_before_account_navigation' );
		$topo = ob_get_clean();
		echo $topo; // phpcs:ignore
		echo '<div class="dcfr-cols">';
		$menu = '';
		foreach ( (array) wc_get_account_menu_items() as $ep => $rotulo ) {
			$cls   = function_exists( 'wc_get_account_menu_item_classes' ) ? wc_get_account_menu_item_classes( $ep ) : 'woocommerce-MyAccount-navigation-link woocommerce-MyAccount-navigation-link--' . $ep;
			$menu .= '<li class="' . esc_attr( $cls ) . '"><a href="' . esc_url( wc_get_account_endpoint_url( $ep ) ) . '">' . esc_html( $rotulo ) . '</a></li>';
		}
		echo '<nav class="woocommerce-MyAccount-navigation" aria-label="Menu da conta"><ul>' . $menu . '</ul></nav>'; // phpcs:ignore
		echo '<div class="woocommerce-MyAccount-content">';
		if ( function_exists( 'woocommerce_output_all_notices' ) ) {
			woocommerce_output_all_notices();
		}
		// O conteudo das abas antigas (snippet #109112) ainda mostra o endereco por subdominio: troca pelo endereco da loja em /<unidade>/.
		ob_start();
		do_action( 'woocommerce_account_content' );
		$cont = (string) ob_get_clean();
		$cont = preg_replace( '#Para colocar ou trocar o logo da escola, envie o arquivo pelo\s*<a[^>]*>.*?</a>\.?#s', 'O logo e o banner da loja voc&ecirc; envia no quadro <b>Logo e banner da loja</b>, logo abaixo.', $cont );
		echo preg_replace( '#https?://([a-z0-9\-]+)\.ouromoderno\.giftbrand\.com\.br/?#i', 'https://ouromoderno.giftbrand.com.br/$1/', $cont ); // phpcs:ignore
		echo '</div></div></div>';
		echo function_exists( 'dcom_tela_credito' ) ? dcom_tela_credito() : ''; // phpcs:ignore
		if ( function_exists( 'dcomp_loja_links_js' ) ) {
			dcomp_loja_links_js(); // botoes da aba Pedidos e links da loja em nova aba (esta pagina nao roda o wp_footer)
		}
		echo '</body></html>';
		exit;
	},
	0
);

/* Situacao da unidade (a equipe usa a da unidade principal). */
function dcomp_status_unidade( $uid = 0 ) {
	$uid = $uid ? (int) $uid : get_current_user_id();
	$p   = function_exists( 'omx_parent' ) ? (int) omx_parent( $uid ) : $uid;
	return (string) get_user_meta( $p, 'omx_status', true );
}

/*
 * Loja fechada enquanto o cadastro esta em analise: a escola logada que ainda nao foi aprovada nao abre a Loja Ouro Moderno
 * (/my-account-2/ouro-moderno-loja/) nem o catalogo da rede (/loja/?om=1). Ve uma tela "Cadastro em analise" com o botao do painel.
 */
add_action(
	'template_redirect',
	function () {
		if ( ! is_user_logged_in() || ! function_exists( 'omx_is_escola' ) || ! omx_is_escola() ) {
			return;
		}
		if ( 'aprovada' === dcomp_status_unidade() ) {
			return;
		}
		$loja = is_page( 120476 ) || ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) && isset( $_GET['om'] ) ); // phpcs:ignore
		if ( ! $loja ) {
			return;
		}
		$painel = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
		$logo   = 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/11/original-1.webp';
		$fundo  = function_exists( 'dcom_tela_fundo' ) ? dcom_tela_fundo() : 'background:#0B2A5B';
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Cadastro em análise · Ouro Moderno</title>'
			. '<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
			. '<style>*{box-sizing:border-box}html,body{margin:0;padding:0;min-height:100%}body{min-height:100vh;' . $fundo . ';font-family:Poppins,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:#fff;display:flex;flex-direction:column}'
			. '.om-w{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:6vh 16px 28px}.om-lg{display:block;width:300px;max-width:78vw;height:auto;margin:0 auto 26px}'
			. '.om-c{width:520px;max-width:100%;text-align:center;background:rgba(7,25,58,.80);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);border:1px solid rgba(245,180,0,.55);border-radius:26px;box-shadow:0 24px 70px rgba(0,0,0,.55);padding:38px 34px 32px}'
			. '.om-ic{width:84px;height:84px;margin:0 auto 18px;border-radius:50%;border:3px solid #F5B400;color:#F5B400;display:flex;align-items:center;justify-content:center}.om-ic svg{width:42px;height:42px}'
			. '.om-c h1{margin:0 0 10px;font-size:32px;line-height:1.15;font-weight:800}.om-c p{margin:0 0 12px;color:#D3DCE9;font-size:15.5px;line-height:1.55}.om-c p b{color:#fff}'
			. '.om-bt{display:block;width:100%;margin:22px 0 0;padding:16px;border-radius:14px;background:linear-gradient(180deg,#FFC933 0%,#F5B400 100%);color:#0B2A5B;font:700 17px Poppins,system-ui,sans-serif;text-decoration:none}'
			. '.om-ft{text-align:center;font-size:12.5px;color:#BFCBE0;padding:14px 12px 18px}.om-ft a{color:#F5B400;font-weight:600;text-decoration:none}</style></head><body>'
			. '<main class="om-w"><img class="om-lg" src="' . esc_url( $logo ) . '" alt="Ouro Moderno" width="1013" height="171">'
			. '<div class="om-c"><div class="om-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>'
			. '<h1>Cadastro em an&aacute;lise</h1>'
			. '<p>Recebemos o cadastro da sua escola e a nossa equipe est&aacute; analisando.</p>'
			. '<p>A <b>loja</b> &eacute; liberada assim que o cadastro for <b>aprovado</b>. At&eacute; l&aacute;, voc&ecirc; acompanha tudo pelo seu painel.</p>'
			. '<a class="om-bt" href="' . esc_url( $painel ) . '">Ir para o meu painel</a></div></main>'
			. ( function_exists( 'dcom_tela_credito' ) ? dcom_tela_credito() : '' ) . '</body></html>'; // phpcs:ignore
		exit;
	},
	0
);
