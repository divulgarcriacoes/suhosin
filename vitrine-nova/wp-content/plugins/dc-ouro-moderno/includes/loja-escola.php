<?php
/**
 * Loja de cada escola aprovada em ouromoderno.giftbrand.com.br/<endereco-da-escola>/ (sem subdominio novo).
 * O <endereco-da-escola> e o campo omx_slug do cadastro (ex.: escola-top). So assume quando o endereco e de uma escola aprovada
 * e nao existe pagina do site com o mesmo endereco; qualquer outro endereco segue normal.
 * A pagina mostra a barra com o logo e o nome da escola e o catalogo da Ouro Moderno (o mesmo [parceiro_produtos], com busca e filtros).
 * Desligar: define( 'DCOM_LOJA_ESCOLA_MODE', 'off' ) no wp-config.php.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vitrine-escola.php';

/* Cores disponiveis no card dos produtos montados no Elementor (so dentro da loja da franquia): bolinhas das cores do produto, depois do preco. */
add_action(
	'woocommerce_after_shop_loop_item_title',
	function () {
		if ( empty( $GLOBALS['dcom_fr_atual'] ) || ! function_exists( 'dcom_vitrine_cores_estoque' ) ) {
			return;
		}
		global $product;
		if ( ! $product || ! is_object( $product ) ) {
			return;
		}
		$h = (string) dcom_vitrine_cores_estoque( $product );
		$h = (string) preg_replace( '#<span class="dcv-est[^>]*>.*?</span>#s', '', $h ); // o estoque ja aparece no selo "Em estoque"
		if ( '' === trim( $h ) ) {
			return;
		}
		static $css = false;
		if ( ! $css ) {
			$css = true;
			echo '<style>.elementor-widget-woocommerce-products ul.products li.product a.woocommerce-LoopProduct-link>span.dcv-cores{order:3!important;display:flex;flex-wrap:wrap;gap:5px;align-items:center;margin:2px 0}.dcv-cores i{width:16px;height:16px;border-radius:50%;border:1px solid rgba(16,24,40,.18);display:inline-block;box-sizing:border-box}.dcv-cores i.out{opacity:.3}.dcv-cores em{font-style:normal;font-size:11.5px;color:#667085;font-weight:700}</style>'; // phpcs:ignore
		}
		echo $h; // phpcs:ignore
	},
	20
);

/* Endereco da loja da escola (unidade principal). */
function dcom_loja_url( $uid ) {
	$p    = function_exists( 'omx_parent' ) ? (int) omx_parent( (int) $uid ) : (int) $uid;
	$slug = (string) get_user_meta( $p, 'omx_slug', true );
	return '' !== $slug ? 'https://ouromoderno.giftbrand.com.br/' . rawurlencode( $slug ) . '/' : '';
}

/*
 * Categoria de cada franquia: criada na aprovacao (ou na primeira visita da loja, para as ja aprovadas), com o nome da unidade,
 * dentro da categoria "ouro-moderno", ja com os produtos da categoria "Padrao das franquias" (franquia-padrao).
 * A loja da franquia abre nessa categoria. Os produtos de cada franquia podem ser trocados depois, no cadastro do produto.
 */
function dcom_loja_cat_pai() {
	$t = get_term_by( 'slug', 'ouro-moderno', 'product_cat' );
	return $t ? (int) $t->term_id : 0;
}

function dcom_loja_padrao_term() {
	$t = get_term_by( 'slug', 'franquia-padrao', 'product_cat' );
	if ( $t ) {
		return (int) $t->term_id;
	}
	$r = wp_insert_term( 'Padrão das franquias', 'product_cat', array( 'slug' => 'franquia-padrao', 'parent' => dcom_loja_cat_pai(), 'description' => 'Produtos que entram na loja de cada nova franquia aprovada.' ) );
	return is_wp_error( $r ) ? 0 : (int) $r['term_id'];
}

function dcom_loja_garantir_cat( $uid ) {
	$p    = function_exists( 'omx_parent' ) ? (int) omx_parent( (int) $uid ) : (int) $uid;
	$slug = (string) get_user_meta( $p, 'omx_slug', true );
	if ( '' === $slug ) {
		return 0;
	}
	$tid = (int) get_user_meta( $p, 'omx_cat', true );
	if ( $tid && get_term( $tid, 'product_cat' ) instanceof \WP_Term ) {
		return $tid;
	}
	$nome = (string) get_user_meta( $p, 'omx_escola', true );
	$tslg = 'franquia-' . $slug;
	$t    = get_term_by( 'slug', $tslg, 'product_cat' );
	if ( $t ) {
		$tid = (int) $t->term_id;
	} else {
		$r = wp_insert_term( '' !== $nome ? $nome : $slug, 'product_cat', array( 'slug' => $tslg, 'parent' => dcom_loja_cat_pai(), 'description' => 'Loja da franquia ' . $nome . '.' ) );
		if ( is_wp_error( $r ) ) {
			return 0;
		}
		$tid = (int) $r['term_id'];
		// Produtos padrao da rede entram na categoria nova.
		$pad = dcom_loja_padrao_term();
		if ( $pad ) {
			$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $pad, 'include_children' => false ) ) ) ); // phpcs:ignore
			foreach ( $ids as $pid ) {
				wp_set_object_terms( (int) $pid, $tid, 'product_cat', true );
			}
		}
	}
	update_user_meta( $p, 'omx_cat', $tid );
	return $tid;
}

/*
 * Produtos padrao valem para todas as franquias: quando um produto entra ou sai da categoria "Padrao das franquias",
 * ele entra ou sai tambem da categoria de cada franquia (categorias franquia-<unidade>).
 */
function dcom_loja_cats_franquias() {
	$pai = dcom_loja_cat_pai();
	$ids = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $pai, 'hide_empty' => false, 'fields' => 'id=>slug', 'number' => 0 ) );
	$out = array();
	foreach ( (array) $ids as $id => $slug ) {
		if ( 0 === strpos( (string) $slug, 'franquia-' ) && 'franquia-padrao' !== $slug ) {
			$out[] = (int) $id;
		}
	}
	return $out;
}

add_action(
	'set_object_terms',
	function ( $obj, $terms, $tt_ids, $tax, $append, $old_tt_ids ) {
		static $busy = false;
		if ( $busy || 'product_cat' !== $tax || 'product' !== get_post_type( $obj ) ) {
			return;
		}
		$pad = get_term_by( 'slug', 'franquia-padrao', 'product_cat' );
		if ( ! $pad ) {
			return;
		}
		$tem   = in_array( (int) $pad->term_taxonomy_id, array_map( 'intval', (array) $tt_ids ), true );
		$tinha = in_array( (int) $pad->term_taxonomy_id, array_map( 'intval', (array) $old_tt_ids ), true );
		if ( $tem === $tinha ) {
			return;
		}
		$fr = dcom_loja_cats_franquias();
		if ( ! $fr ) {
			return;
		}
		$busy = true;
		if ( $tem ) {
			wp_set_object_terms( (int) $obj, $fr, 'product_cat', true );
		} else {
			wp_remove_object_terms( (int) $obj, $fr, 'product_cat' );
		}
		$busy = false;
	},
	20,
	6
);

/* Cria a categoria "Padrao das franquias" uma vez, para a Matriz colocar os produtos padrao. */
add_action(
	'init',
	function () {
		if ( '1' !== (string) get_option( 'dcom_padrao_ok' ) && taxonomy_exists( 'product_cat' ) ) {
			if ( dcom_loja_padrao_term() ) {
				update_option( 'dcom_padrao_ok', '1', false );
			}
		}
	},
	30
);

/* Escola aprovada dona do endereco, ou 0. */
function dcom_loja_escola_por_slug( $slug ) {
	$ids = get_users(
		array(
			'role'       => 'escola',
			'meta_key'   => 'omx_slug', // phpcs:ignore
			'meta_value' => $slug, // phpcs:ignore
			'fields'     => 'ID',
			'number'     => 5,
		)
	);
	foreach ( (array) $ids as $id ) {
		$id = (int) $id;
		if ( (int) get_user_meta( $id, 'omx_parent', true ) > 0 ) {
			continue;
		}
		if ( 'aprovada' === (string) get_user_meta( $id, 'omx_status', true ) ) {
			return $id;
		}
	}
	return 0;
}

add_action(
	'template_redirect',
	function () {
		if ( defined( 'DCOM_LOJA_ESCOLA_MODE' ) && 'off' === DCOM_LOJA_ESCOLA_MODE ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore
		$path = untrailingslashit( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
		if ( ! preg_match( '#^/([a-z0-9\-]+)$#', $path, $m ) ) {
			return;
		}
		$res = array( 'my-account-2', 'loja', 'carrinho', 'cart', 'checkout', 'finalizar-compra', 'painel-matriz', 'ouro-moderno', 'ouro-moderno-loja', 'produto', 'wp-admin', 'wp-login', 'feed' );
		if ( in_array( $m[1], $res, true ) || get_page_by_path( $m[1] ) ) {
			return;
		}
		$id = dcom_loja_escola_por_slug( $m[1] );
		if ( ! $id ) {
			return;
		}
		$nome = (string) get_user_meta( $id, 'omx_escola', true );
		$cid  = (string) get_user_meta( $id, 'omx_cidade', true );
		$lgid = (int) get_user_meta( $id, 'omx_logo_escuro', true ) ?: ( (int) get_user_meta( $id, 'omx_logo', true ) ?: ( (int) get_user_meta( $id, 'omx_logo_vertical_escuro', true ) ?: (int) get_user_meta( $id, 'omx_logo_branco', true ) ) ); // topo: colorido para fundos escuros; sem ele, o horizontal colorido
		$logo = $lgid ? (string) wp_get_attachment_image_url( $lgid, 'medium_large' ) : '';
		$cor  = (string) get_user_meta( $id, 'omx_cor', true );
		$cor  = preg_match( '/^#[0-9a-fA-F]{6}$/', $cor ) ? $cor : '#F5B400';
		// Cor de fundo da pagina, escolhida pela escola em Minha loja (padrao cinza claro).
		$fun  = strtoupper( (string) get_user_meta( $id, 'omx_fundo', true ) );
		$fun  = preg_match( '/^#[0-9A-F]{6}$/', $fun ) ? $fun : '#F4F5F8';
		$fesc = function_exists( 'dcom_vitrine_txt' ) && '#FFFFFF' === dcom_vitrine_txt( $fun );
		// Imagem de fundo (foto da escola), opcional: fica em volta do quadro, no lugar da cor.
		$fiid = (int) get_user_meta( $id, 'omx_fundo_img', true );
		$fimg = $fiid ? (string) wp_get_attachment_image_url( $fiid, 'full' ) : '';
		if ( '' !== $fimg ) {
			$fesc = true;
		}
		$om   = 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/11/original-1.webp';
		$bnid = (int) get_user_meta( $id, 'omx_banner', true );
		$ban  = $bnid ? (string) wp_get_attachment_image_url( $bnid, 'full' ) : '';
		// A loja ABRE na categoria da franquia (produtos padrao); busca, filtros, categorias e "Todas as categorias" levam a loja completa.
		$fcat = get_term( dcom_loja_garantir_cat( $id ), 'product_cat' );
		$usou = false;
		foreach ( array( 'omq', 'omc', 'omin', 'omax', 'oms', 'omp' ) as $k ) {
			if ( isset( $_GET[ $k ] ) && '' !== (string) $_GET[ $k ] ) { // phpcs:ignore
				$usou = true;
			}
		}
		if ( isset( $_GET['omc'] ) && 'todas' === $_GET['omc'] ) { // phpcs:ignore
			unset( $_GET['omc'] ); // loja completa
		}
		if ( ! $usou && $fcat instanceof \WP_Term && (int) $fcat->count > 0 ) {
			$_GET['omc'] = $fcat->slug; // phpcs:ignore
		}
		// Imagens por franquia: os produtos desta loja usam a arte desta franquia (e a pagina do produto tambem, pelo cookie).
		$GLOBALS['dcom_fr_atual'] = (int) $id;
		if ( ! headers_sent() ) {
			setcookie( 'dcom_fr', (string) (int) $id, time() + 30 * DAY_IN_SECONDS, '/', '', is_ssl(), true );
		}
		// Abertura da loja (sem busca/filtro): vitrine com os produtos padrao por tema (includes/vitrine-escola.php).
		$vit = '';
		if ( ! $usou && $fcat instanceof \WP_Term && (int) $fcat->count > 0 && function_exists( 'dcom_vitrine_html' ) ) {
			$vit = dcom_vitrine_html( $id, (int) $fcat->term_id, $cor, $nome, (string) get_user_meta( $id, 'omx_slug', true ), add_query_arg( 'omc', 'todas', dcom_loja_url( $id ) ) );
		}
		// Vitrine montada no Elementor (editavel no painel): vale quando a opcao dcom_vitrine_el tem o ID de uma pagina Elementor publicada, ou com ?vit=el (teste).
		$vel = (int) get_option( 'dcom_vitrine_el', 0 );
		if ( isset( $_GET['vit'] ) && 'el' === $_GET['vit'] && ! $vel ) { // phpcs:ignore
			$vel = 120849;
		}
		// Teste ligado so na loja APRENDA MAIS (franquia-aprenda-mais): mostra a vitrine do Elementor sem precisar do ?vit=el. As demais lojas seguem como estao.
		// (Teste da vitrine Elementor na Aprenda Mais desligado: todas as lojas usam a vitrine padrão, igual à CENAIC.)
		if ( ! $usou && $vel && class_exists( '\\Elementor\\Plugin' ) && $fcat instanceof \WP_Term && (int) $fcat->count > 0 ) {
			$vp = get_post( $vel );
			if ( $vp && ( 'publish' === $vp->post_status || ( isset( $_GET['vit'] ) && 'el' === $_GET['vit'] ) || 'franquia-aprenda-mais' === $fcat->slug ) ) { // phpcs:ignore
				$hel = (string) \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $vel, true );
				// [dcom_escola ...] dentro de atributos (cor do botão, link do catálogo) não é processado pelo WordPress: troca aqui.
				$hel = preg_replace_callback(
					'/\[dcom_escola[^\]]*\]/',
					function ( $m ) {
						return esc_attr( do_shortcode( $m[0] ) );
					},
					$hel
				);
				if ( '' !== trim( $hel ) ) {
					$vit = $hel;
				}
			}
		}
		$cat  = '' !== $vit ? $vit : do_shortcode( '[parceiro_produtos]' );
		// Fora da vitrine (catalogo completo, categoria ou busca): botao para voltar a loja exclusiva da escola.
		if ( '' === $vit && $fcat instanceof \WP_Term && (int) $fcat->count > 0 ) {
			$tx   = function_exists( 'dcom_vitrine_txt' ) ? dcom_vitrine_txt( $cor ) : '#0B2A5B';
			$cat  = '<div class="dcle-volta"><a href="' . esc_url( dcom_loja_url( $id ) ) . '" style="background:' . esc_attr( $cor ) . ';color:' . esc_attr( $tx ) . '!important">&#8592; Vitrine ' . esc_html( $nome ) . '</a><span>Voc&ecirc; est&aacute; no cat&aacute;logo completo. Os brindes escolhidos para a ' . esc_html( $nome ) . ' ficam na vitrine.</span></div>' . $cat;
		}
		// Os produtos abrem dentro da loja da franquia (?lf=<unidade>), com o topo da escola.
		$lf  = (string) get_user_meta( $id, 'omx_slug', true );
		$cat = preg_replace_callback(
			'#href="((?:https?://[^"/]+)?/produto/[^"]*)"#',
			function ( $m ) use ( $lf ) {
				return 'href="' . esc_url( add_query_arg( 'lf', $lf, html_entity_decode( $m[1] ) ) ) . '"';
			},
			$cat
		);
		// "Todas as categorias" abre a loja completa (sem isso voltaria para os produtos padrao).
		$cat = preg_replace_callback(
			'#(<a class="dcom-(?:m0|all)" href=")([^"]*)(")#',
			function ( $m ) {
				return $m[1] . esc_url( add_query_arg( 'omc', 'todas', html_entity_decode( $m[2] ) ) ) . $m[3];
			},
			$cat
		);
		$ini  = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $nome, 0, 1 ) ) : strtoupper( substr( $nome, 0, 1 ) );

		status_header( 200 );
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		if ( function_exists( 'dcom_head_limpo' ) ) {
			dcom_head_limpo( $nome . ' · Loja Ouro Moderno', $nome );
		}
		wp_head();
		echo ''
			. '<style id="dcle">html,body{margin:0!important;padding:0!important}body.dcle{background:' . esc_attr( $fun ) . ( '' !== $fimg ? ' url(' . esc_url( $fimg ) . ') center/cover no-repeat fixed' : '' ) . '!important;font-family:Poppins,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:#101828}'
			. '.dcle-top{background:linear-gradient(100deg,#07142b,#0B2A5B 70%);color:#fff;border-bottom:4px solid ' . esc_attr( $cor ) . '}'
			. '.dcle-in{max-width:1240px;margin:0 auto;padding:16px 20px;display:flex;align-items:center;gap:18px;flex-wrap:wrap}'
			. '.dcle-lg{width:64px;height:64px;border-radius:16px;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;flex:0 0 auto;color:#0B2A5B;font-weight:800;font-size:28px}.dcle-lg img{max-width:100%;max-height:100%;object-fit:contain}'
			. '.dcle-lg.so{width:auto;height:118px;min-width:80px;max-width:360px;padding:8px 0;box-sizing:border-box;background:transparent;border-radius:0;overflow:visible}.dcle-lg.so img{height:100%;width:auto;max-width:100%;display:block}.dcle-lg.so picture{display:flex;align-items:center;height:100%}'
			. '.dcle-nm b{display:block;font-size:24px;line-height:1.15}.dcle-nm span{font-size:13.5px;color:#C9D3E8}'
			. '.dcle-om{margin-left:auto;display:flex;align-items:center;gap:10px;font-size:12px;color:#C9D3E8}.dcle-om img{width:150px;height:auto;display:block}'
			. '.dcle-pn{order:3;display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.35);color:#fff!important;text-decoration:none!important;font-weight:700;font-size:13.5px;border-radius:999px;padding:9px 16px;white-space:nowrap}.dcle-pn:hover{background:#fff;color:#0B2A5B!important}'
			. '.dcle-ban{max-width:1240px;margin:18px auto 0;padding:0 20px}.dcle-ban img{display:block;width:100%;height:auto;max-height:340px;object-fit:cover;border-radius:18px}'
			. '.dcle-wrap{max-width:1240px;margin:0 auto;padding:18px 20px 40px}'
			. '.dcle-volta{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin:0 6px 16px}.dcle-volta a{display:inline-flex;align-items:center;gap:6px;border-radius:12px;padding:11px 18px;font-weight:800;font-size:14.5px;text-decoration:none!important;box-shadow:0 4px 12px rgba(16,24,40,.12)}.dcle-volta a:hover{filter:brightness(.95)}.dcle-volta span{font-size:13px;color:#667085}'
			// O catalogo (dcom) foi feito para ocupar a tela toda (100vw): dentro da loja da escola ele respeita o espaco do quadro.
			. '.dcle-wrap .dcom{width:auto!important;margin-left:0!important;margin-right:0!important;padding-left:0!important;padding-right:0!important;overflow:visible!important}.dcle-wrap .dcom-in{max-width:none!important;padding:0 6px!important}'
			// Com cor de fundo escolhida: a cor fica em volta e o conteudo num quadro cinza claro.
			. ( '' !== $fimg ? '@media(max-width:900px){body.dcle{background-attachment:scroll!important}}' : '' )
			. ( '#F4F5F8' !== $fun || '' !== $fimg ? '.dcle-wrap{background:#F4F5F8;border-radius:24px;margin:18px auto 28px;padding:26px 28px 40px;box-shadow:0 12px 40px rgba(0,0,0,.14)}@media(max-width:1280px){.dcle-wrap{margin:18px 16px 28px}}@media(max-width:700px){.dcle-wrap{margin:12px 8px 20px;padding:16px 12px 30px;border-radius:18px}}' : '' )
			. '.om-ft{text-align:center;font-size:12.5px;color:' . ( $fesc ? 'rgba(255,255,255,.8)' : '#667085' ) . ';padding:10px 12px 22px}.om-ft a{color:' . ( $fesc ? '#FFFFFF' : '#B98F34' ) . ';font-weight:600;text-decoration:none}'
			. '@media(max-width:700px){.dcle-om{margin-left:0}}'
			. '</style></head><body class="dcle"><header class="dcle-top"><div class="dcle-in">'
			. ( '' !== $logo
				? '<div class="dcle-lg so"><img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $nome ) . '"></div>' // com logo: so o logo
				: '<div class="dcle-lg">' . esc_html( $ini ) . '</div><div class="dcle-nm"><b>' . esc_html( $nome ) . '</b><span>Loja da escola' . ( '' !== $cid ? ' &middot; ' . esc_html( $cid ) : '' ) . '</span></div>' )
			. '<div class="dcle-om"><span>em parceria com</span><img src="' . esc_url( $om ) . '" alt="Ouro Moderno" width="1013" height="171"></div>'
			// Volta para a Area do Franqueado (o painel da escola).
			. '<a class="dcle-pn" href="' . esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' ) ) . '">&#8592; Painel</a>'
			. '</div></header>' . ( '' !== $ban ? '<div class="dcle-ban"><img src="' . esc_url( $ban ) . '" alt="' . esc_attr( $nome ) . '"></div>' : '' ) . '<main class="dcle-wrap">' . $cat . '</main>' // phpcs:ignore
			. ( function_exists( 'dcom_tela_credito' ) ? dcom_tela_credito() : '' ) . '</body></html>'; // phpcs:ignore
		exit;
	},
	0
);

/* Topo da loja da franquia (o mesmo da pagina /<unidade>/), para a pagina do produto. */
function dcom_loja_topo_html( $id ) {
	$nome = (string) get_user_meta( $id, 'omx_escola', true );
	$cid  = (string) get_user_meta( $id, 'omx_cidade', true );
	$lgid = (int) get_user_meta( $id, 'omx_logo_escuro', true ) ?: ( (int) get_user_meta( $id, 'omx_logo', true ) ?: ( (int) get_user_meta( $id, 'omx_logo_vertical_escuro', true ) ?: (int) get_user_meta( $id, 'omx_logo_branco', true ) ) ); // topo: colorido para fundos escuros; sem ele, o horizontal colorido
	$logo = $lgid ? (string) wp_get_attachment_image_url( $lgid, 'medium_large' ) : '';
	$cor  = (string) get_user_meta( $id, 'omx_cor', true );
	$cor  = preg_match( '/^#[0-9a-fA-F]{6}$/', $cor ) ? $cor : '#F5B400';
	$ini  = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $nome, 0, 1 ) ) : strtoupper( substr( $nome, 0, 1 ) );
	$url  = dcom_loja_url( $id );
	$om   = 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/11/original-1.webp';
	return '<style>.dclp-top{background:linear-gradient(100deg,#07142b,#0B2A5B 70%);color:#fff;border-bottom:4px solid ' . esc_attr( $cor ) . ';font-family:Poppins,system-ui,-apple-system,"Segoe UI",Arial,sans-serif}.dclp-in{max-width:1240px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}'
		. '.dclp-lg{display:flex;align-items:center;gap:12px;color:#fff!important;text-decoration:none!important}.dclp-lg img{height:100px;width:auto;max-width:340px;display:block;margin:4px 0}.dclp-lg picture{display:flex;align-items:center}.dclp-lg i{width:56px;height:56px;border-radius:14px;background:#fff;color:#0B2A5B;display:flex;align-items:center;justify-content:center;font-style:normal;font-weight:800;font-size:24px}.dclp-lg b{font-size:22px}'
		. '.dclp-vt{margin-left:auto;background:' . esc_attr( $cor ) . ';color:#0B2A5B!important;font-weight:800;border-radius:999px;padding:10px 18px;text-decoration:none!important;font-size:14px}.dclp-om{display:flex;align-items:center;gap:8px;font-size:12px;color:#C9D3E8}.dclp-om img{width:130px;height:auto;display:block}'
		. '.dcomb,.dcpt-bar,.dcpt-foot,#site-header,header.site-header,.elementor-location-header{display:none!important}</style>'
		. '<header class="dclp-top"><div class="dclp-in"><a class="dclp-lg" href="' . esc_url( $url ) . '">' . ( '' !== $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $nome ) . '">' : '<i>' . esc_html( $ini ) . '</i><b>' . esc_html( $nome ) . '</b>' ) . '</a>'
		. '<a class="dclp-vt" href="' . esc_url( $url ) . '">&lsaquo; Voltar &agrave; loja</a><div class="dclp-om"><span>em parceria com</span><img src="' . esc_url( $om ) . '" alt="Ouro Moderno" width="1013" height="171"></div></div></header>';
}

/* Pagina do produto aberta pela loja da franquia (?lf=<unidade>): topo da escola, arte da escola e sem o topo da Ouro Moderno. */
add_action(
	'template_redirect',
	function () {
		if ( ! function_exists( 'is_product' ) ) {
			return;
		}
		$id = 0;
		if ( is_product() && ! empty( $_GET['lf'] ) ) { // phpcs:ignore
			$id = dcom_loja_escola_por_slug( sanitize_title( wp_unslash( $_GET['lf'] ) ) ); // phpcs:ignore
		} elseif ( ( is_cart() || is_checkout() ) && ! empty( $_COOKIE['dcom_fr'] ) ) { // phpcs:ignore -- carrinho e checkout de quem veio da loja da franquia
			$cid = absint( $_COOKIE['dcom_fr'] ); // phpcs:ignore
			$slg = (string) get_user_meta( $cid, 'omx_slug', true );
			$id  = '' !== $slg ? dcom_loja_escola_por_slug( $slg ) : 0;
		}
		if ( ! $id ) {
			return;
		}
		$GLOBALS['dcom_fr_atual'] = $id;
		if ( ! headers_sent() ) {
			setcookie( 'dcom_fr', (string) $id, time() + 30 * DAY_IN_SECONDS, '/', '', is_ssl(), true );
		}
		remove_action( 'wp_body_open', 'dcpt_header', 5 );
		add_action(
			'wp_body_open',
			function () use ( $id ) {
				echo dcom_loja_topo_html( $id ); // phpcs:ignore
			},
			1
		);
	},
	5
);

/* As categorias internas (franquias, Padrao das franquias, Ouro Moderno) nao aparecem na lista de categorias do produto.
   So mexe nos links exibidos (term_links), nao na categoria do produto, para nao afetar as regras das lojas. */
add_filter(
	'term_links-product_cat',
	function ( $links ) {
		if ( is_admin() ) {
			return $links;
		}
		return array_values(
			array_filter(
				(array) $links,
				function ( $l ) {
					return ! preg_match( '#/(franquia-[a-z0-9\-]+|ouro-moderno)/?"#', (string) $l );
				}
			)
		);
	},
	40
);
