<?php
/**
 * Plugin Name: DC · Layout padrão das lojas parceiras
 * Description: Página inicial padrão de TODAS as lojas em subdomínio (casafinna, ambient e as próximas de dcpt_cfg()): banner, vantagens, categorias com foto, destaques, catálogo com busca e filtros, como funciona. Substitui a saídea dos shortcodes [parceiro_produtos], [ambient_produtos] e [casafinna_produtos]. Loja nova entra sozinha pelo dcpt_cfg() do snippet #109112; ajustes por loja (destaques, banner) ficam em dclay_extra() abaixo.
 * Version: 1.0.0
 * Author: Divulgar Criações
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ajustes por loja. destaques = IDs dos produtos do bloco "Destaques" (vazio = os mais vendidos da loja).
// hero = false quando a página da loja já tem banner próprio no Elementor (Casafinna).
function dclay_extra() {
	return array(
		'casafinna.giftbrand.com.br' => array(
			'hero'      => false,
			'cats'      => false, // a pagina da Casafinna ja tem a faixa de icones de categoria no Elementor.
			'destaques' => array(),
		),
		'ambient.giftbrand.com.br'   => array(
			'hero'      => true,
			'destaques' => array( 60156, 40970, 60981, 60865 ),
		),
		// Wyda e industria (as outras sao lojas de moveis): texto do banner voltado a colaboradores e eventos internos.
		'wyda.giftbrand.com.br'      => array(
			'hero'      => true,
			'destaques' => array(),
			'titulo'    => 'Brindes com a marca Wyda para colaboradores, clientes e eventos',
			'texto'     => 'Kits de integração, SIPAT, campanhas internas e fim de ano. Escolha o produto, envie a arte e receba tudo gravado, com envio para todo o Brasil.',
		),
	);
}

function dclay_stores() {
	$s = array(
		'casafinna.giftbrand.com.br' => array(
			'nome'  => 'Casafinna',
			'cat'   => 'casafinna',
			'page'  => 109107,
			'cor'   => '#D4AF37',
			'texto' => '#14110D',
			'fundo' => '#14110D',
		),
	);
	if ( function_exists( 'dcpt_cfg' ) ) {
		foreach ( dcpt_cfg() as $h => $c ) {
			$s[ $h ] = array(
				'nome'  => $c['nome'],
				'cat'   => $c['cat'],
				'page'  => (int) $c['page'],
				'cor'   => $c['cor'],
				'texto' => isset( $c['cor_texto'] ) ? $c['cor_texto'] : '#FFFFFF',
				'fundo' => $c['fundo'],
			);
		}
	}
	$s = apply_filters( 'dclay_stores', $s ); // ajustes vindos de outros módulos (ex.: cores da Casafinna no painel)
	$x = dclay_extra();
	foreach ( $s as $h => $c ) {
		$s[ $h ] = array_merge( array( 'hero' => true, 'cats' => true, 'destaques' => array() ), $c, isset( $x[ $h ] ) ? $x[ $h ] : array(), array( 'host' => $h ) );
	}
	return $s;
}

// Loja atual: pelo endereço; no site da Divulgar (editor/visualização da página da loja), pelo ID da página.
function dclay_cur() {
	$h = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( preg_replace( '/:\d+$/', '', (string) $_SERVER['HTTP_HOST'] ) ) : ''; // phpcs:ignore
	$s = dclay_stores();
	if ( isset( $s[ $h ] ) ) {
		return $s[ $h ];
	}
	$pid = (int) get_the_ID();
	foreach ( $s as $c ) {
		if ( $pid > 0 && (int) $c['page'] === $pid ) {
			return $c;
		}
	}
	return null;
}

function dclay_money( $v ) {
	return 'R$&nbsp;' . number_format( (float) $v, 2, ',', '.' );
}

function dclay_norm( $s ) {
	return strtolower( remove_accents( wp_strip_all_tags( (string) $s ) ) );
}

function dclay_swatches( $pid ) {
	$raw  = get_post_meta( (int) $pid, '_dc_color_stock_table', true );
	$rows = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	if ( ! is_array( $rows ) || ! $rows || ! function_exists( 'dccf_swatch_style' ) ) {
		return '';
	}
	$out = '';
	$n   = 0;
	foreach ( $rows as $r ) {
		if ( empty( $r['label'] ) || ( isset( $r['stock'] ) && (int) $r['stock'] <= 0 ) ) {
			continue;
		}
		if ( ++$n > 8 ) {
			$out .= '<span class="dcl-more">+' . ( count( $rows ) - 8 ) . '</span>';
			break;
		}
		$out .= '<span class="dcl-sw" title="' . esc_attr( $r['label'] ) . '" style="' . esc_attr( dccf_swatch_style( $r['label'] ) ) . '"></span>';
	}
	return '' !== $out ? '<div class="dcl-sws" aria-label="Cores disponíveis">' . $out . '</div>' : '';
}

function dclay_card( $p, $subs, $badge = '' ) {
	$pid   = $p->get_id();
	$link  = wp_make_link_relative( get_permalink( $pid ) );
	$min   = class_exists( 'DC_Min_Order_Qty' ) ? (int) DC_Min_Order_Qty::min_qty_for_product( $pid ) : 0;
	$price = (float) $p->get_price();
	return '<article class="dcl-card" data-cats="' . esc_attr( implode( ' ', array_keys( $subs ) ) ) . '" data-name="' . esc_attr( dclay_norm( $p->get_name() . ' ' . $p->get_sku() . ' ' . implode( ' ', $subs ) ) ) . '">'
		. '<a class="dcl-img" href="' . esc_url( $link ) . '">' . $p->get_image( 'woocommerce_thumbnail' ) . ( '' !== $badge ? '<span class="dcl-badge">' . esc_html( $badge ) . '</span>' : '' ) . '</a>'
		. '<div class="dcl-body"><h3><a href="' . esc_url( $link ) . '">' . esc_html( $p->get_name() ) . '</a></h3>'
		. dclay_swatches( $pid )
		. ( $price > 0 ? '<span class="dcl-from">a partir de</span><span class="dcl-price">' . dclay_money( $price ) . '</span>' : '' )
		. ( $min > 0 ? '<span class="dcl-min">Pedido mínimo: ' . (int) $min . ' unidades</span>' : '' )
		. '<a class="dcl-btn" href="' . esc_url( $link ) . '">Comprar</a></div></article>';
}

function dclay_icon( $d ) {
	return '<span class="dcl-ico"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $d . '</svg></span>';
}

function dclay_shortcode() {
	$c = dclay_cur();
	if ( ! $c || ! function_exists( 'wc_get_products' ) ) {
		return '';
	}
	$list = wc_get_products(
		array(
			'status'   => 'publish',
			'limit'    => 300,
			'category' => array( $c['cat'] ),
			'orderby'  => 'title',
			'order'    => 'ASC',
		)
	);
	if ( empty( $list ) ) {
		return '<p style="text-align:center;color:#6B6B64;">Em breve, novos produtos.</p>';
	}
	$root = get_term_by( 'slug', $c['cat'], 'product_cat' );
	$rid  = ( $root && ! is_wp_error( $root ) ) ? (int) $root->term_id : 0;

	// Subcategorias da loja de cada produto + menor pedido mínimo.
	$by_id   = array();
	$subsOf  = array();
	$allSubs = array();
	$subImg  = array();
	$minAll  = 0;
	foreach ( $list as $p ) {
		$pid           = $p->get_id();
		$by_id[ $pid ] = $p;
		$mine          = array();
		$terms         = get_the_terms( $pid, 'product_cat' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( (int) $t->parent === $rid ) {
					$mine[ $t->slug ] = $t->name;
					if ( ! isset( $subImg[ $t->slug ] ) && $p->get_image_id() ) {
						$subImg[ $t->slug ] = (int) $p->get_image_id();
					}
				}
			}
		}
		$mine           = apply_filters( 'dclay_subs_produto', $mine, $pid, $c, $terms );
		$subsOf[ $pid ] = $mine;
		$allSubs        = $allSubs + $mine;
		$m              = class_exists( 'DC_Min_Order_Qty' ) ? (int) DC_Min_Order_Qty::min_qty_for_product( $pid ) : 0;
		if ( $m > 0 && ( 0 === $minAll || $m < $minAll ) ) {
			$minAll = $m;
		}
	}
	asort( $allSubs );

	// Destaques: os escolhidos em dclay_extra(); senão os mais vendidos.
	$dest = array();
	foreach ( (array) $c['destaques'] as $id ) {
		if ( isset( $by_id[ (int) $id ] ) ) {
			$dest[] = $by_id[ (int) $id ];
		}
	}
	if ( ! $dest ) {
		$tmp = $list;
		usort(
			$tmp,
			function ( $a, $b ) {
				return (int) $b->get_total_sales() - (int) $a->get_total_sales();
			}
		);
		$dest = array_slice( $tmp, 0, 4 );
	}
	$dest = array_slice( $dest, 0, 4 );

	$cor   = esc_attr( $c['cor'] );
	$txt   = esc_attr( $c['texto'] );
	$fundo = esc_attr( $c['fundo'] );
	$nome  = esc_html( $c['nome'] );

	$o  = '<style>'
		. '.dcl{--dcl-cor:' . $cor . ';--dcl-txt:' . $txt . ';--dcl-fundo:' . $fundo . ';color:#14110D;background:#F7F4EE}'
		. '.dcl *{box-sizing:border-box}.dcl a{text-decoration:none}'
		. '.dcl-wrap{max-width:1240px;margin:0 auto;padding-left:20px;padding-right:20px}'
		. '.dcl-hero{background:var(--dcl-fundo);color:#fff;border-top:1px solid rgba(255,255,255,.08)}'
		. '.dcl-hero .dcl-wrap{display:flex;flex-wrap:wrap;gap:48px;align-items:center;padding-top:56px;padding-bottom:64px}'
		. '.dcl-hero-t{flex:1 1 460px;min-width:0;display:flex;flex-direction:column;gap:20px}'
		. '.dcl-eyebrow{font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#E8D6C0}'
		. '.dcl-hero h1{margin:0;font-weight:800;font-size:46px;line-height:1.1;color:#fff}'
		. '.dcl-hero p{margin:0;font-size:18px;line-height:1.55;color:#D9D2C3;max-width:520px}'
		. '.dcl-ctas{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px}'
		. '.dcl-cta{display:inline-flex;align-items:center;min-height:48px;padding:0 28px;border-radius:10px;font-weight:700;font-size:16px}'
		. '.dcl-cta.is-main{background:var(--dcl-cor);color:var(--dcl-txt)!important}.dcl-cta.is-ghost{border:2px solid #fff;color:#fff!important}'
		. '.dcl-hero-g{flex:1 1 420px;min-width:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}'
		. '.dcl-hero-g a{display:block;aspect-ratio:1/1;border-radius:14px;overflow:hidden;background:#fff}.dcl-hero-g img{width:100%!important;height:100%!important;object-fit:cover;display:block}'
		. '.dcl-trust{background:#fff;border-bottom:1px solid #E4DCC8}.dcl-trust .dcl-wrap{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:20px;padding-top:24px;padding-bottom:24px}'
		. '.dcl-tr{display:flex;gap:14px;align-items:center}.dcl-tr strong{display:block;font-size:15px}.dcl-tr small{display:block;font-size:13px;color:#5E5A52}'
		. '.dcl-ico{width:44px;height:44px;border-radius:50%;background:#F3EDE4;color:var(--dcl-fundo);display:flex;align-items:center;justify-content:center;flex-shrink:0}'
		. '.dcl-sec{padding-top:52px}.dcl-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:22px}'
		. '.dcl-head h2{margin:0;font-weight:800;font-size:30px;color:#14110D}.dcl-head .dcl-eyebrow{color:#7A6A55;display:block;margin-bottom:6px}'
		. '.dcl-link{font-weight:700;font-size:15px;color:#14110D!important;border-bottom:2px solid var(--dcl-cor)}'
		. '.dcl-cats{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px}'
		. '.dcl-cat{display:flex;flex-direction:column;gap:10px;background:#fff;border:1px solid #E4DCC8;border-radius:14px;padding:12px;cursor:pointer;font:inherit;color:#14110D;text-align:center}'
		. '.dcl-cat:hover{border-color:var(--dcl-cor)}.dcl-cat span.dcl-ci{display:block;aspect-ratio:1/1;border-radius:10px;overflow:hidden;background:#EFE9DD}.dcl-cat img{width:100%!important;height:100%!important;object-fit:cover;display:block}'
		. '.dcl-cat b{font-size:15px;letter-spacing:.5px}'
		. '.dcl-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px}@media(max-width:1100px){.dcl-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:780px){.dcl-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}}'
		. '.dcl-card{background:#fff;border:1px solid #E4DCC8;border-radius:16px;overflow:hidden;display:flex;flex-direction:column;min-width:0}'
		. '.dcl-img{position:relative;display:block;aspect-ratio:1/1;background:#fff}.dcl-img img{width:100%!important;height:100%!important;object-fit:contain;display:block}'
		. '.dcl-badge{position:absolute;top:12px;left:12px;background:var(--dcl-fundo);color:#fff;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;border-radius:999px;padding:6px 10px}'
		. '.dcl-body{padding:14px 16px 16px;display:flex;flex-direction:column;gap:6px;flex-grow:1}'
		. '.dcl-body h3{margin:0 0 2px;font-size:15px;line-height:1.3;font-weight:700}.dcl-body h3 a{color:#14110D!important}'
		. '.dcl-sws{display:flex;flex-wrap:wrap;gap:5px;margin:2px 0}.dcl-sw{width:18px;height:18px;border-radius:5px;border:1px solid #CFC6B2;display:inline-block}.dcl-more{font-size:12px;color:#5E5A52;align-self:center}'
		. '.dcl-from{font-size:12px;color:#5E5A52;margin-top:4px}.dcl-price{font-weight:800;font-size:22px;line-height:1.1}.dcl-min{font-size:13px;color:#5E5A52}'
		. '.dcl-btn{margin-top:auto;display:flex;align-items:center;justify-content:center;min-height:44px;background:var(--dcl-cor);color:var(--dcl-txt)!important;font-weight:700;font-size:15px;border-radius:10px;margin-top:10px}'
		. '.dcl-btn:hover{filter:brightness(.92)}'
		. '.dcl-tools{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:16px}'
		. '.dcl-search{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #CFC6B2;border-radius:999px;padding:0 16px;flex:0 1 360px;min-height:48px}'
		. '.dcl-search input{border:0!important;outline:none;background:transparent!important;font-size:15px;width:100%;min-height:44px;box-shadow:none!important;padding:0!important}'
		. '.dcl-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:22px}'
		. '.dcl-chips button{cursor:pointer;font:inherit;font-weight:600;font-size:14px;border-radius:999px;padding:0 18px;min-height:44px;border:1px solid #CFC6B2;background:#fff;color:#14110D}'
		. '.dcl-chips button.is-on{background:var(--dcl-cor);border-color:var(--dcl-cor);color:var(--dcl-txt)}'
		. '.dcl-empty{display:none;text-align:center;color:#5E5A52;padding:30px 0}'
		. '.dcl-how{background:#fff;border-top:1px solid #E4DCC8;margin-top:56px}.dcl-how .dcl-wrap{padding-top:52px;padding-bottom:56px}'
		. '.dcl-how h2{margin:0 0 28px;font-weight:800;font-size:30px;text-align:center;color:#14110D}'
		. '.dcl-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:24px}'
		. '.dcl-step{display:flex;flex-direction:column;gap:10px;align-items:center;text-align:center;padding:8px 12px}'
		. '.dcl-step i{font-style:normal;width:52px;height:52px;border-radius:50%;background:var(--dcl-cor);color:var(--dcl-txt);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:22px}'
		. '.dcl-step strong{font-size:17px}.dcl-step span{font-size:14px;color:#5E5A52;line-height:1.5}'
		. '@media(max-width:600px){.dcl-hero h1{font-size:32px}.dcl-head h2,.dcl-how h2{font-size:24px}.dcl-hero .dcl-wrap{padding-top:36px;padding-bottom:40px;gap:28px}}'
		// O tema pinta todo <button> de roxo: aqui os botoes da loja voltam ao visual do layout.
		. '.dcl button.dcl-cat,.dcl button.dcl-cat:hover,.dcl button.dcl-cat:focus{background:#fff!important;color:#14110D!important;border:1px solid #E4DCC8!important;border-radius:14px!important;padding:12px!important;text-transform:none!important;box-shadow:none!important;line-height:1.3!important;font-weight:400!important;letter-spacing:0!important}'
		. '.dcl button.dcl-cat:hover{border-color:var(--dcl-cor)!important}.dcl button.dcl-cat b{font-weight:700;text-transform:uppercase;font-size:14px;letter-spacing:.5px}'
		. '.dcl .dcl-chips button,.dcl .dcl-chips button:hover,.dcl .dcl-chips button:focus{background:#fff!important;color:#14110D!important;border:1px solid #CFC6B2!important;border-radius:999px!important;padding:0 18px!important;text-transform:none!important;box-shadow:none!important;letter-spacing:0!important}'
		. '.dcl .dcl-chips button.is-on,.dcl .dcl-chips button.is-on:hover{background:var(--dcl-cor)!important;border-color:var(--dcl-cor)!important;color:var(--dcl-txt)!important}'
		. '.dcl .sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}'
		. '</style><div class="dcl">';

	// Banner.
	if ( ! empty( $c['hero'] ) ) {
		$g = '';
		foreach ( array_slice( $dest, 0, 4 ) as $p ) {
			$g .= '<a href="' . esc_url( wp_make_link_relative( get_permalink( $p->get_id() ) ) ) . '" aria-label="' . esc_attr( $p->get_name() ) . '">' . $p->get_image( 'woocommerce_thumbnail' ) . '</a>';
		}
		$o .= '<section class="dcl-hero"><div class="dcl-wrap"><div class="dcl-hero-t">'
			. '<span class="dcl-eyebrow">Loja de brindes ' . $nome . '</span>'
			. '<h1>' . ( ! empty( $c['titulo'] ) ? esc_html( $c['titulo'] ) : 'Brindes com a marca ' . $nome . ' para clientes, equipe e eventos' ) . '</h1>'
			. '<p>' . ( ! empty( $c['texto'] ) ? esc_html( $c['texto'] ) : 'Escolha o produto, envie a sua arte e receba tudo gravado. Pedido mínimo baixo e envio para todo o Brasil.' ) . '</p>'
			. '<div class="dcl-ctas"><a class="dcl-cta is-main" href="#produtos">Ver produtos</a><a class="dcl-cta is-ghost" href="#como-funciona">Como funciona</a></div>'
			. '</div><div class="dcl-hero-g">' . $g . '</div></div></section>';
	}

	// Vantagens.
	$o .= '<section class="dcl-trust" aria-label="Vantagens"><div class="dcl-wrap">'
		. '<div class="dcl-tr">' . dclay_icon( '<path d="M12 19l7-7 3 3-7 7-3-3z"></path><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"></path><circle cx="11" cy="11" r="2"></circle>' ) . '<div><strong>Gravação com a sua marca</strong><small>Silk, laser ou UV conforme o produto</small></div></div>'
		. '<div class="dcl-tr">' . dclay_icon( '<path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.7z"></path><path d="M3.3 7L12 12l8.7-5"></path><path d="M12 22V12"></path>' ) . '<div><strong>Pedido mínimo baixo</strong><small>' . ( $minAll > 0 ? 'A partir de ' . (int) $minAll . ' unidades' : 'Consulte em cada produto' ) . '</small></div></div>'
		. '<div class="dcl-tr">' . dclay_icon( '<path d="M1 3h15v13H1z"></path><path d="M16 8h4l3 3v5h-7z"></path><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle>' ) . '<div><strong>Envio para todo o Brasil</strong><small>Frete calculado no carrinho</small></div></div>'
		. '</div></section>';

	// Categorias com foto.
	if ( ! empty( $c['cats'] ) && count( $allSubs ) > 1 ) {
		$o .= '<section class="dcl-wrap dcl-sec" aria-labelledby="dcl-tcat"><div class="dcl-head"><h2 id="dcl-tcat">Compre por categoria</h2><a class="dcl-link" href="#produtos">Ver todos os produtos →</a></div><div class="dcl-cats">';
		foreach ( $allSubs as $slug => $name ) {
			$t    = get_term_by( 'slug', $slug, 'product_cat' );
			$tid  = $t ? (int) get_term_meta( $t->term_id, 'thumbnail_id', true ) : 0;
			$img  = $tid ? $tid : ( isset( $subImg[ $slug ] ) ? $subImg[ $slug ] : 0 );
			$o   .= '<button type="button" class="dcl-cat" data-cat="' . esc_attr( $slug ) . '"><span class="dcl-ci">' . ( $img ? wp_get_attachment_image( $img, 'woocommerce_thumbnail', false, array( 'alt' => '' ) ) : '' ) . '</span><b>' . esc_html( $name ) . '</b></button>';
		}
		$o .= '</div></section>';
	}

	// Destaques.
	if ( $dest ) {
		$o .= '<section class="dcl-wrap dcl-sec" aria-labelledby="dcl-tdest"><div class="dcl-head"><div><span class="dcl-eyebrow">Mais pedidos</span><h2 id="dcl-tdest">Destaques da ' . $nome . '</h2></div></div><div class="dcl-grid">';
		foreach ( $dest as $p ) {
			$o .= dclay_card( $p, array(), 'Mais pedido' );
		}
		$o .= '</div></section>';
	}

	// Catálogo.
	$o .= '<section id="produtos" class="dcl-wrap dcl-sec" aria-labelledby="dcl-tall"><div class="dcl-tools"><h2 id="dcl-tall" style="margin:0;font-weight:800;font-size:30px;color:#14110D">Todos os produtos</h2>'
		. '<div class="dcl-search"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5E5A52" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg><label for="dcl-q" class="sr">Buscar produto</label><input id="dcl-q" type="search" placeholder="Buscar produto (ex.: caneca)"></div></div>';
	if ( $allSubs ) {
		$o .= '<div class="dcl-chips" role="group" aria-label="Filtrar por categoria"><button type="button" class="is-on" data-cat="">Todos</button>';
		foreach ( $allSubs as $slug => $name ) {
			$o .= '<button type="button" data-cat="' . esc_attr( $slug ) . '">' . esc_html( $name ) . '</button>';
		}
		$o .= '</div>';
	}
	$o .= '<div class="dcl-grid dcl-all">';
	foreach ( $list as $p ) {
		$o .= dclay_card( $p, $subsOf[ $p->get_id() ] );
	}
	$o .= '</div><p class="dcl-empty">Nenhum produto encontrado.</p></section>';

	// Como funciona.
	$o .= '<section id="como-funciona" class="dcl-how" aria-labelledby="dcl-tcomo"><div class="dcl-wrap"><h2 id="dcl-tcomo">Como funciona</h2><div class="dcl-steps">'
		. '<div class="dcl-step"><i>1</i><strong>Escolha o produto</strong><span>Selecione a cor e a quantidade. O preço por unidade já aparece na hora.</span></div>'
		. '<div class="dcl-step"><i>2</i><strong>Envie a sua marca</strong><span>Anexe o logo no produto ou envie depois. Mandamos a arte para aprovação.</span></div>'
		. '<div class="dcl-step"><i>3</i><strong>Receba gravado</strong><span>Produzimos e enviamos para todo o Brasil, com acompanhamento do pedido.</span></div>'
		. '</div></div></section></div>';

	// Filtros, busca e cartões de categoria.
	$o .= '<script>(function(){var cat="",q="";function norm(s){return (s||"").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g,"");}'
		. 'function apply(){var n=0;document.querySelectorAll(".dcl-all .dcl-card").forEach(function(d){var l=(d.getAttribute("data-cats")||"").split(" ");var ok=(!cat||l.indexOf(cat)>-1)&&(!q||(d.getAttribute("data-name")||"").indexOf(q)>-1);d.style.display=ok?"":"none";if(ok){n++;}});'
		. 'document.querySelectorAll(".dcl-chips button").forEach(function(b){b.classList.toggle("is-on",b.getAttribute("data-cat")===cat);});var e=document.querySelector(".dcl-empty");if(e){e.style.display=n?"none":"block";}}'
		. 'document.addEventListener("click",function(e){var b=e.target.closest(".dcl-chips button,.dcl-cat");if(!b){return;}cat=b.getAttribute("data-cat")||"";apply();if(b.classList.contains("dcl-cat")){var s=document.getElementById("produtos");if(s){s.scrollIntoView({behavior:"smooth"});}}});'
		. 'document.addEventListener("input",function(e){if(e.target&&e.target.id==="dcl-q"){q=norm(e.target.value.trim());apply();}});})();</script>';

	return $o; // phpcs:ignore
}

// Troca a saída dos shortcodes das lojas (os snippets registram no init; este roda depois).
add_action(
	'init',
	function () {
		foreach ( array( 'parceiro_produtos', 'ambient_produtos', 'casafinna_produtos' ) as $sc ) {
			remove_shortcode( $sc );
			add_shortcode( $sc, 'dclay_shortcode' );
		}
	},
	999
);

// Nas lojas: sem "Produtos relacionados" e sem "Voce tambem pode gostar" na pagina do produto
// (mostravam produtos de fora da loja).
function dclay_on_store() {
	$h = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( preg_replace( '/:\d+$/', '', (string) $_SERVER['HTTP_HOST'] ) ) : ''; // phpcs:ignore
	$s = dclay_stores();
	return isset( $s[ $h ] );
}
add_filter(
	'woocommerce_related_products',
	function ( $ids ) {
		return dclay_on_store() ? array() : $ids;
	},
	99
);
add_filter(
	'woocommerce_product_related_posts_force_display',
	function ( $v ) {
		return dclay_on_store() ? false : $v;
	},
	99
);
add_action(
	'wp',
	function () {
		if ( dclay_on_store() ) {
			remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
			remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
		}
	}
);
add_action(
	'wp_head',
	function () {
		if ( dclay_on_store() ) {
			echo '<style>section.related.products,section.up-sells,.elementor-widget-woocommerce-product-related,.elementor-widget-woocommerce-product-upsell{display:none!important}'
				// Logo do cabecalho 30% menor (Ambient 96px -> 67px; Casafinna 120px -> 84px).
				. '.dcpt-bar>a img{height:67px!important}.dcpt-bar>a img.dcpt-ico{width:67px!important}.dchd-bar img{height:84px!important}'
				. '@media(max-width:600px){.dcpt-bar>a img{height:40px!important}.dcpt-bar>a img.dcpt-ico{width:40px!important}.dchd-bar img{height:56px!important}}</style>';
		}
	},
	100
);

// Nas lojas, a página inicial ocupa a largura toda (sem a margem do tema em volta do conteúdo).
add_action(
	'wp_head',
	function () {
		$c = dclay_cur();
		if ( ! $c || ! is_front_page() ) {
			return;
		}
		echo '<style>body{background:#F7F4EE}.page-id-' . (int) $c['page'] . ' .site-main,.page-id-' . (int) $c['page'] . ' .page-content,.page-id-' . (int) $c['page'] . ' .entry-content{max-width:none!important;width:100%!important;padding:0!important;margin:0!important}.dcl{width:100vw;position:relative;left:50%;margin-left:-50vw}.e-con:has(>.elementor-widget-shortcode .dcl),.e-con:has(>.e-con-inner>.elementor-widget-shortcode .dcl){padding-top:0!important;padding-bottom:0!important}</style>';
	},
	100
);
