<?php
/**
 * Vitrine da loja da escola (/<endereco-da-escola>/): a abertura da loja deixa de ser so uma grade de produtos.
 * Mostra uma chamada com a marca da escola, vantagens, os produtos padrao separados por tema
 * (Material escolar, Mochilas e sacolas, Garrafas e copos, Tecnologia) e o convite para o catalogo completo.
 * Busca, filtros e "Catalogo completo" continuam usando a loja de sempre ([parceiro_produtos]).
 * Os produtos vem da categoria da franquia (franquia-<unidade>), que recebe os da categoria "Padrao das franquias".
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Temas da vitrine: titulo, frase, as categorias de produto que entram nele (na ordem em que aparecem) e a categoria que o "Ver mais" abre. */
function dcom_vitrine_temas() {
	return array(
		'escolar'    => array( 'Material escolar', 'Canetas, lápis, cadernos e blocos: o básico que todo aluno usa.', array( 'canetas', 'lapis-e-lapiseiras', 'cadernos', 'ouro-moderno-cadernos', 'blocos-e-cadernetas', 'kit-escritorio', 'estojos' ), 'canetas' ),
		'mochilas'   => array( 'Mochilas e sacolas', 'Para levar o material e espalhar a marca da escola por onde passa.', array( 'mochilas', 'sacolas', 'bolsas' ), 'bolsas' ),
		'garrafas'   => array( 'Garrafas e copos', 'Das garrafas térmicas premium aos copos do dia a dia.', array( 'garrafas-termicas', 'garrafastermicas', 'squeezes-e-garrafas', 'copos' ), 'squeezes-e-garrafas' ),
		'tecnologia' => array( 'Tecnologia e estudo', 'Fones e pastas para notebook para quem estuda conectado.', array( 'fones-de-ouvido', 'pasta-para-notebook', 'pastas' ), 'fones-de-ouvido' ),
	);
}

/* Produtos da vitrine, separados por tema (cada produto entra no primeiro tema que combinar). */
function dcom_vitrine_grupos( $term_id ) {
	$ids = get_posts(
		array(
			'post_type'   => 'product',
			'post_status' => 'publish',
			'numberposts' => 300,
			'fields'      => 'ids',
			'tax_query'   => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => (int) $term_id, 'include_children' => false ) ), // phpcs:ignore
		)
	);
	$temas  = dcom_vitrine_temas();
	$grupos = array_fill_keys( array_keys( $temas ), array() );
	$grupos['mais'] = array();
	foreach ( $ids as $pid ) {
		$p = wc_get_product( $pid );
		if ( ! $p || ! $p->is_visible() ) {
			continue;
		}
		$slugs = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'slugs' ) );
		$slugs = is_array( $slugs ) ? $slugs : array();
		$alvo  = 'mais';
		foreach ( $temas as $k => $t ) {
			if ( array_intersect( $t[2], $slugs ) ) {
				$alvo = $k;
				break;
			}
		}
		$grupos[ $alvo ][] = $p;
	}
	foreach ( $grupos as $k => $lista ) {
		usort(
			$grupos[ $k ],
			function ( $a, $b ) {
				return (float) $a->get_price() <=> (float) $b->get_price();
			}
		);
	}
	return $grupos;
}

/* Cor do texto sobre a cor da escola: branco em cor escura, azul-marinho em cor clara. */
function dcom_vitrine_txt( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 6 !== strlen( $hex ) ) {
		return '#0B2A5B';
	}
	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );
	return ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) > 150 ? '#0B2A5B' : '#FFFFFF';
}

/* Bolinhas das cores disponiveis (tabela de cores do produto: _dc_color_stock_table) e o estoque total. */
function dcom_vitrine_cores_estoque( $p ) {
	$out  = '';
	$raw  = get_post_meta( $p->get_id(), '_dc_color_stock_table', true );
	$rows = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	if ( is_array( $rows ) && $rows ) {
		$sw = '';
		$n  = 0;
		$tt = 0;
		foreach ( $rows as $r ) {
			if ( empty( $r['label'] ) ) {
				continue;
			}
			$st = isset( $r['stock'] ) ? (int) $r['stock'] : 0;
			$tt++;
			if ( $n >= 9 ) {
				continue;
			}
			$n++;
			$est = function_exists( 'dccf_swatch_style' ) ? dccf_swatch_style( $r['label'] ) : 'background:#EEE';
			$sw .= '<i class="' . ( $st > 0 ? '' : 'out' ) . '" title="' . esc_attr( $r['label'] . ' - ' . ( $st > 0 ? number_format( $st, 0, ',', '.' ) . ' un.' : 'sem estoque' ) ) . '" style="' . esc_attr( $est ) . '"></i>';
		}
		if ( '' !== $sw ) {
			$out .= '<span class="dcv-cores">' . $sw . ( $tt > $n ? '<em>+' . ( $tt - $n ) . '</em>' : '' ) . '</span>';
		}
	}
	if ( $p->managing_stock() && null !== $p->get_stock_quantity() ) {
		$q    = (int) $p->get_stock_quantity();
		$out .= '<span class="dcv-est' . ( $q > 0 ? '' : ' out' ) . '">' . ( $q > 0 ? 'Em estoque: ' . number_format( $q, 0, ',', '.' ) . ' un.' : 'Sem estoque no momento' ) . '</span>';
	}
	return $out;
}

/* Preco unitario da 1a linha da tabela de quantidades (a mesma conta da pagina do produto, na gravacao mais barata). Cache de 6 horas. */
function dcom_vitrine_preco_1a_linha( $pid ) {
	if ( ! function_exists( 'dcprice_qty_tiers_for_product' ) || ! function_exists( 'dcprice_calculate_for_product' ) || ! function_exists( 'dcprice_discount_curve' ) ) {
		return 0.0;
	}
	$ck = 'dcv_p1_' . (int) $pid;
	$c  = get_transient( $ck );
	if ( false !== $c ) {
		return (float) $c;
	}
	$best  = 0.0;
	$tiers = dcprice_qty_tiers_for_product( $pid );
	if ( ! empty( $tiers ) ) {
		$opts  = function_exists( 'dcprice_technique_options_for_product' ) ? (array) dcprice_technique_options_for_product( $pid ) : array();
		$keys  = $opts ? array_map( function ( $o ) { return '' === ( $o['key'] ?? '' ) ? null : $o['key']; }, $opts ) : array( null );
		$curve = dcprice_discount_curve( $pid );
		$d1    = isset( $curve[1] ) ? (float) $curve[1] : 0.0;
		foreach ( $keys as $k ) {
			$b = dcprice_calculate_for_product( $pid, $tiers[0], $k );
			if ( is_wp_error( $b ) || empty( $b['unit_price'] ) ) {
				continue;
			}
			$u = round( (float) $b['unit_price'] * ( 1 - $d1 ), 2 );
			if ( $u > 0 && ( 0.0 === $best || $u < $best ) ) {
				$best = $u;
			}
		}
	}
	set_transient( $ck, $best, 6 * HOUR_IN_SECONDS );
	return $best;
}

function dcom_vitrine_card( $p, $lf ) {
	$pid   = $p->get_id();
	$link  = add_query_arg( 'lf', $lf, get_permalink( $pid ) );
	$price = (float) $p->get_price();
	// Mostra o valor da 1a linha da tabela "Escolha a quantidade" (pedido minimo), calculado pelo motor de precos.
	$p1 = dcom_vitrine_preco_1a_linha( $pid );
	if ( $p1 > 0 ) {
		$price = $p1;
	}
	$min   = class_exists( 'DC_Min_Order_Qty' ) ? (int) DC_Min_Order_Qty::min_qty_for_product( $pid ) : 0;
	$slugs = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'slugs' ) );
	$slugs = is_array( $slugs ) ? $slugs : array();
	$selo  = '';
	if ( array_intersect( array( 'garrafas-termicas', 'garrafastermicas' ), $slugs ) || $price >= 30 ) {
		$selo = '<span class="dcv-selo pr">Premium</span>';
	} elseif ( $price > 0 && $price <= 2 ) {
		$selo = '<span class="dcv-selo ec">Econômico</span>';
	}
	$sku = (string) $p->get_sku();
	return '<a class="dcv-card" href="' . esc_url( $link ) . '">' . $selo
		. '<span class="dcv-img">' . $p->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ) . '</span>'
		. ( '' !== $sku ? '<span class="dcv-sku">SKU ' . esc_html( $sku ) . '</span>' : '' )
		. '<span class="dcv-nm">' . esc_html( $p->get_name() ) . '</span>'
		. dcom_vitrine_cores_estoque( $p )
		. ( $price > 0 ? '<span class="dcv-pr"><small>a partir de</small> R$ ' . esc_html( number_format( $price, 2, ',', '.' ) ) . '<small>/un</small></span>' : '' )
		. ( $min > 0 ? '<span class="dcv-min">Pedido mínimo: ' . (int) $min . ' un.</span>' : '' )
		. '<span class="dcv-bt">Ver produto</span></a>';
}

/* HTML da vitrine. $todas = endereco do catalogo completo. */
function dcom_vitrine_html( $id, $term_id, $cor, $nome, $lf, $todas ) {
	$grupos = dcom_vitrine_grupos( $term_id );
	$temas  = dcom_vitrine_temas();
	$total  = 0;
	foreach ( $grupos as $l ) {
		$total += count( $l );
	}
	if ( ! $total ) {
		return '';
	}
	// Colagem da chamada: um produto de cada tema (o mais caro de cada, que costuma ser o mais vistoso).
	$cola = array();
	foreach ( array( 'garrafas', 'mochilas', 'tecnologia', 'escolar' ) as $k ) {
		if ( ! empty( $grupos[ $k ] ) ) {
			// Prefere um produto que já tem molde (aparece com o logo da escola).
			$esc = null;
			foreach ( array_reverse( $grupos[ $k ] ) as $gp ) {
				$rg = get_post_meta( $gp->get_id(), '_dcmk', true );
				if ( is_array( $rg ) && ! empty( $rg['q'] ) ) {
					$esc = $gp;
					break;
				}
			}
			$cola[] = $esc ? $esc : end( $grupos[ $k ] );
		}
	}
	$cola_html = '';
	foreach ( array_slice( $cola, 0, 4 ) as $p ) {
		$cola_html .= '<span><a href="' . esc_url( $p->get_permalink() ) . '" style="position:relative;display:flex;width:100%;height:100%;align-items:center;justify-content:center">' . $p->get_image( 'woocommerce_thumbnail' ) . '</a></span>';
	}
	$nav = '';
	foreach ( $temas as $k => $t ) {
		if ( ! empty( $grupos[ $k ] ) ) {
			$nav .= '<a href="#dcv-' . esc_attr( $k ) . '">' . esc_html( $t[0] ) . '</a>';
		}
	}
	$c   = esc_attr( $cor );
	$ct  = dcom_vitrine_txt( $cor );
	// O conteudo fica num quadro cinza claro; a cor de fundo da escola (omx_fundo) fica em volta dele (loja-escola.php).
	$fu  = '#F4F5F8';
	$fe  = false;
	$css = '<style id="dcv">'
		. '.dcv{--c:' . $c . ';--ct:' . $ct . ';--f:' . esc_attr( $fu ) . ';--ft:' . ( $fe ? '#FFFFFF' : '#0B2A5B' ) . ';--fp:' . ( $fe ? 'rgba(255,255,255,.82)' : '#475467' ) . ';--az:#0B2A5B;font-family:Poppins,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:#101828}'
		. '.dcv *{box-sizing:border-box}'
		. '.dcv-hero{display:grid;grid-template-columns:1.1fr 1fr;gap:28px;align-items:center;background:linear-gradient(120deg,#fff 55%,#FFF7E0);border:1px solid #E7E9F0;border-radius:24px;padding:34px 36px;box-shadow:0 10px 30px rgba(16,24,40,.08);margin:0 0 18px;overflow:hidden}'
		. '.dcv-hero small{display:inline-block;background:var(--az);color:#fff;font-weight:700;font-size:12px;letter-spacing:.06em;text-transform:uppercase;border-radius:999px;padding:6px 12px}'
		. '.dcv-hero h1{font-size:38px;line-height:1.12;margin:14px 0 10px;color:var(--az)}.dcv-hero h1 em{font-style:normal;color:var(--c);filter:brightness(.85)}'
		. '.dcv-hero p{font-size:16px;color:#475467;margin:0 0 22px;max-width:520px}'
		. '.dcv-bts{display:flex;gap:10px;flex-wrap:wrap}.dcv-b1,.dcv-b2{display:inline-block;border-radius:12px;padding:13px 22px;font-weight:800;text-decoration:none!important;font-size:15px}'
		. '.dcv-b1{background:var(--c);color:var(--ct)!important}.dcv-b2{background:#fff;color:var(--az)!important;border:2px solid var(--az)}'
		. '.dcv-cola{display:grid;grid-template-columns:1fr 1fr;gap:12px}.dcv-cola>span{background:#fff;border-radius:18px;border:1px solid #EEF0F4;aspect-ratio:1/1;display:flex;align-items:center;justify-content:center;padding:10px;box-shadow:0 8px 24px rgba(11,42,91,.08)}'
		. '.dcv-cola>span:nth-child(2),.dcv-cola>span:nth-child(4){transform:translateY(18px)}.dcv-cola img{max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;border-radius:10px}'
		. '.dcv-van{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:0 0 22px}.dcv-van div{background:var(--az);color:#fff;border-radius:16px;padding:16px 18px;display:flex;gap:12px;align-items:center;font-size:14px;line-height:1.35}'
		. '.dcv-van b{display:block;font-size:15px}.dcv-van i{flex:0 0 40px;height:40px;border-radius:12px;background:var(--c);color:var(--ct);display:flex;align-items:center;justify-content:center;font-style:normal;font-weight:900;font-size:18px}'
		. '.dcv-nav{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin:0 0 8px;position:sticky;top:0;z-index:5;background:var(--f);padding:10px 0}'
		. '.dcv-nav a{background:#fff;border:1px solid #D0D5DD;border-radius:999px;padding:8px 16px;font-weight:700;color:var(--az)!important;text-decoration:none!important;font-size:14px}.dcv-nav a:hover{background:var(--az);color:#fff!important;border-color:var(--az)}'
		. '.dcv-sec{margin:26px 0 0;scroll-margin-top:70px}.dcv-sh{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin:0 0 12px;border-bottom:3px solid ' . ( $fe ? 'rgba(255,255,255,.55)' : 'var(--c)' ) . ';padding-bottom:10px}'
		. '.dcv-sh h2{margin:0;font-size:24px;color:var(--ft)}.dcv-sh p{margin:4px 0 0;color:var(--fp);font-size:14px}.dcv-sh a{color:var(--ft)!important;font-weight:700;font-size:14px;white-space:nowrap;text-decoration:none!important}'
		. '.dcv-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}'
		. '.dcv-card{position:relative;background:#fff;border:1px solid #E7E9F0;border-radius:18px;padding:12px 12px 14px;display:flex;flex-direction:column;gap:6px;text-decoration:none!important;color:#101828!important;transition:transform .15s,box-shadow .15s}'
		. '.dcv-card:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(11,42,91,.12)}'
		. '.dcv-img{aspect-ratio:1/1;background:#F8F9FB;border-radius:12px;display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:4px}.dcv-img img{max-width:92%;max-height:92%;width:auto!important;height:auto!important;object-fit:contain}'
		. '.dcv-nm{font-weight:700;font-size:14px;line-height:1.3;min-height:36px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}'
		. '.dcv-pr{font-size:20px;font-weight:800;color:var(--az)}.dcv-pr small{font-size:11px;font-weight:600;color:#667085;margin:0 3px}.dcv-pr small:first-child{display:block;margin:0}'
		. '.dcv-sku{font-size:11.5px;color:#98A2B3;font-weight:600;letter-spacing:.02em}'
		. '.dcv-cores{display:flex;flex-wrap:wrap;gap:5px;align-items:center}.dcv-cores i{width:16px;height:16px;border-radius:50%;border:1px solid rgba(16,24,40,.18);display:inline-block;box-sizing:border-box}.dcv-cores i.out{opacity:.3}.dcv-cores em{font-style:normal;font-size:11.5px;color:#667085;font-weight:700}'
		. '.dcv-est{font-size:12px;color:#067647;font-weight:600}.dcv-est.out{color:#B42318}'
		. '.dcv-min{font-size:12px;color:#667085}.dcv-bt{margin-top:auto;text-align:center;background:var(--c);color:var(--ct);font-weight:800;border-radius:10px;padding:9px;font-size:14px}'
		. '.dcv-selo{position:absolute;top:10px;left:10px;z-index:2;font-size:11px;font-weight:800;border-radius:999px;padding:4px 10px;letter-spacing:.02em}.dcv-selo.pr{background:var(--az);color:#fff}.dcv-selo.ec{background:#E7F6EC;color:#1E7B3C}'
		. '.dcv-cta{margin:34px 0 0;background:linear-gradient(110deg,#07142b,var(--az));color:#fff;border-radius:22px;padding:28px 30px;display:flex;gap:20px;align-items:center;justify-content:space-between;flex-wrap:wrap}'
		. '.dcv-cta h3{margin:0 0 6px;font-size:24px;color:#fff}.dcv-cta p{margin:0;color:#C9D3E8}.dcv-cta form{display:flex;gap:8px;flex:1 1 360px;max-width:520px}'
		. '.dcv-cta input{flex:1;border:0;border-radius:12px;padding:13px 14px;font-size:15px;min-width:0}.dcv-cta button{border:0;background:var(--c);color:var(--ct);font-weight:800;border-radius:12px;padding:0 18px;cursor:pointer;font-size:15px}'
		. '.dcv-cta .dcv-b1{white-space:nowrap}'
		. '@media(max-width:1100px){.dcv-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}'
		. '@media(max-width:860px){.dcv-hero{grid-template-columns:1fr;padding:24px 20px}.dcv-hero h1{font-size:30px}.dcv-van{grid-template-columns:1fr}.dcv-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}'
		. '@media(max-width:560px){.dcv-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.dcv-sh{flex-direction:column;align-items:flex-start}.dcv-cola>span:nth-child(2),.dcv-cola>span:nth-child(4){transform:none}.dcv-pr{font-size:18px}}'
		. '</style>';

	$h  = $css . '<div class="dcv">';
	$h .= '<section class="dcv-hero"><div><small>Loja oficial &middot; ' . esc_html( $nome ) . '</small>'
		. '<h1>Brindes com a <em>marca da sua escola</em></h1>'
		. '<p>Escolha os produtos, envie a arte e receba tudo personalizado. Preço especial da rede Ouro Moderno, do brinde econômico ao presente premium.</p>'
		. '<div class="dcv-bts"><a class="dcv-b1" href="#dcv-vitrine">Ver os brindes</a><a class="dcv-b2" href="' . esc_url( $todas ) . '">Catálogo completo</a></div></div>'
		. '<div class="dcv-cola">' . $cola_html . '</div></section>';
	$h .= '<div class="dcv-van">'
		. '<div><i>&#10003;</i><span><b>Personalizado</b>Com o logo da escola em cada peça.</span></div>'
		. '<div><i>$</i><span><b>Preço de rede</b>Valores especiais Ouro Moderno.</span></div>'
		. '<div><i>&#10148;</i><span><b>Entrega no Brasil todo</b>Frete calculado no carrinho.</span></div>'
		. '</div>';
	$h .= '<nav class="dcv-nav" id="dcv-vitrine">' . $nav . '<a href="' . esc_url( $todas ) . '">Todos os produtos &rarr;</a></nav>';
	foreach ( $temas as $k => $t ) {
		if ( empty( $grupos[ $k ] ) ) {
			continue;
		}
		$cards = '';
		foreach ( $grupos[ $k ] as $p ) {
			$cards .= dcom_vitrine_card( $p, $lf );
		}
		$h .= '<section class="dcv-sec" id="dcv-' . esc_attr( $k ) . '"><div class="dcv-sh"><div><h2>' . esc_html( $t[0] ) . '</h2><p>' . esc_html( $t[1] ) . '</p></div>'
			. '<a href="' . esc_url( ! empty( $t[3] ) ? add_query_arg( 'omc', $t[3], $todas ) : $todas ) . '">Ver mais &rarr;</a></div><div class="dcv-grid">' . $cards . '</div></section>';
	}
	if ( ! empty( $grupos['mais'] ) ) {
		$cards = '';
		foreach ( $grupos['mais'] as $p ) {
			$cards .= dcom_vitrine_card( $p, $lf );
		}
		$h .= '<section class="dcv-sec" id="dcv-mais"><div class="dcv-sh"><div><h2>Mais brindes</h2><p>Outras ideias para presentear alunos, pais e equipe.</p></div></div><div class="dcv-grid">' . $cards . '</div></section>';
	}
	$h .= '<section class="dcv-cta"><div><h3>Procurando outro brinde?</h3><p>São milhares de produtos no catálogo completo.</p></div>'
		. '<form method="get" action="' . esc_url( remove_query_arg( array( 'omq', 'omc' ), $todas ) ) . '"><input type="search" name="omq" placeholder="Ex.: agenda, caneca, ecobag" aria-label="Buscar produto"><button type="submit">Buscar</button></form>'
		. '<a class="dcv-b1" href="' . esc_url( $todas ) . '">Ver catálogo completo</a></section>';
	$h .= '</div>';
	return $h;
}
