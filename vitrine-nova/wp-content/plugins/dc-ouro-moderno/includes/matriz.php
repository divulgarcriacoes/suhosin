<?php
/**
 * Painel da Matriz (Diretoria do Ouro Moderno): controle da rede de franqueados.
 * Pagina do painel do WordPress (menu "Painel da Matriz"), so para quem gerencia a loja (administradores).
 * Usa os pedidos reais dos franqueados (unidades aprovadas e a equipe delas). Blocos sem dados mostram "em breve".
 * Comissao: fixa em 20% sobre o valor dos produtos; pedido com nota gerada desconta 4,5% da comissao (includes/comissoes.php).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Comissao da Matriz: fixa em 20% sobre as compras da rede (nao muda o preco dos produtos). */
if ( ! defined( 'DCMX_COMISSAO_FIXA' ) ) {
	define( 'DCMX_COMISSAO_FIXA', 20 );
}
if ( ! function_exists( 'dcmx_pct' ) ) {
	function dcmx_pct() {
		return (float) DCMX_COMISSAO_FIXA;
	}
}

if ( ! function_exists( 'dcmx_money' ) ) {
	function dcmx_money( $v ) {
		return 'R$ ' . number_format( (float) $v, 2, ',', '.' );
	}
}

function dcmx_orders_url() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
		return admin_url( 'admin.php?page=wc-orders' );
	}
	return admin_url( 'edit.php?post_type=shop_order' );
}

/* Endereco do painel: no wp-admin (administrador) ou no proprio site (/painel-matriz/, Diretoria e Equipe). */
function dcmx_base() {
	return is_admin() ? admin_url( 'admin.php?page=dcmx' ) : home_url( '/painel-matriz/' );
}

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Painel da Matriz', 'Painel da Matriz', 'dcmx_ver', 'dcmx', 'dcmx_pagina', 'dashicons-chart-area', 3 );
	}
);

/* Categoria principal de um produto (para o grafico por categoria). */
function dcmx_cat_top( $pid ) {
	static $cache = array();
	$pid = (int) $pid;
	if ( isset( $cache[ $pid ] ) ) {
		return $cache[ $pid ];
	}
	$nome  = 'Outros';
	$terms = get_the_terms( $pid, 'product_cat' );
	if ( is_array( $terms ) ) {
		// Ignora as categorias das lojas das escolas (ouro-moderno, franquia-*, padrao): mostra a categoria do produto.
		$terms = array_values(
			array_filter(
				$terms,
				function ( $t ) {
					if ( 'ouro-moderno' === $t->slug || 0 === strpos( $t->slug, 'franquia-' ) ) {
						return false;
					}
					$p = $t->parent ? get_term( (int) $t->parent, 'product_cat' ) : null;
					return ! ( $p && ! is_wp_error( $p ) && 'ouro-moderno' === $p->slug );
				}
			)
		);
	}
	if ( is_array( $terms ) && $terms ) {
		$t = reset( $terms );
		$n = 0;
		while ( $t && (int) $t->parent > 0 && $n < 6 ) {
			$p = get_term( (int) $t->parent, 'product_cat' );
			if ( ! $p || is_wp_error( $p ) ) {
				break;
			}
			$t = $p;
			$n++;
		}
		$nome = html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' );
	}
	$cache[ $pid ] = $nome;
	return $nome;
}

/* Todos os numeros do painel, para o periodo (em dias) e a franquia escolhidos (0 = todas). */
function dcmx_dados( $dias, $fid ) {
	global $wpdb;
	$names = array();
	foreach ( $wpdb->get_results( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'omx_escola'" ) as $r ) { // phpcs:ignore
		$names[ (int) $r->user_id ] = (string) $r->meta_value;
	}
	$aprov = array_map( 'intval', (array) $wpdb->get_col( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'omx_status' AND meta_value = 'aprovada'" ) ); // phpcs:ignore
	$set   = array_flip( $aprov );
	$map   = array();
	foreach ( $aprov as $id ) {
		$map[ $id ] = $id;
	}
	foreach ( $wpdb->get_results( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'omx_parent' AND meta_value + 0 > 0" ) as $r ) { // phpcs:ignore
		$pid = (int) $r->meta_value;
		if ( isset( $set[ $pid ] ) ) {
			$map[ (int) $r->user_id ] = $pid;
		}
	}
	if ( $fid > 0 ) {
		$map = array_filter(
			$map,
			function ( $f ) use ( $fid ) {
				return (int) $f === (int) $fid;
			}
		);
	}
	$d = array(
		'franquias'   => $fid > 0 ? ( isset( $set[ $fid ] ) ? 1 : 0 ) : count( $aprov ),
		'novas'       => 0,
		'total'       => 0.0,
		'total_ant'   => 0.0,
		'com'         => 0.0,
		'com_ant'     => 0.0,
		'com_desc'    => 0.0,
		'pedidos'     => 0,
		'pedidos_ant' => 0,
		'aguardando'  => 0,
		'aguard_val'  => 0.0,
		'meses'       => array(),
		'cats'        => array(),
		'prods'       => array(),
		'ultimas'     => array(),
		'nomes'       => $names,
		'aprov'       => $aprov,
	);
	$t0 = time() - $dias * DAY_IN_SECONDS;
	$t1 = $t0 - $dias * DAY_IN_SECONDS;
	$d['novas'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} m ON m.user_id = u.ID AND m.meta_key = 'omx_status' AND m.meta_value = 'aprovada' WHERE u.user_registered >= %s", gmdate( 'Y-m-d H:i:s', $t0 ) ) ); // phpcs:ignore
	// Ultimos 7 meses (do mais antigo ao atual).
	for ( $i = 6; $i >= 0; $i-- ) {
		$d['meses'][ gmdate( 'Y-m', strtotime( '-' . $i . ' months', time() ) ) ] = 0.0;
	}
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return $d;
	}
	$janela = max( 215, 2 * $dias );
	$orders = empty( $map ) ? array() : wc_get_orders(
		array(
			'customer'     => array_keys( $map ),
			'limit'        => -1,
			'date_created' => '>=' . ( time() - $janela * DAY_IN_SECONDS ),
			'status'       => array( 'pending', 'processing', 'on-hold', 'completed' ),
			'orderby'      => 'date',
			'order'        => 'DESC',
		)
	);
	// Pedidos feitos pela loja de uma franquia (qualquer comprador), sem repetir.
	if ( function_exists( 'dcom_pedidos_marcados' ) ) {
		$vistos = array();
		foreach ( (array) $orders as $o ) {
			$vistos[ $o->get_id() ] = 1;
		}
		foreach ( dcom_pedidos_marcados( $fid > 0 ? $fid : 0, time() - $janela * DAY_IN_SECONDS, array( 'pending', 'processing', 'on-hold', 'completed' ) ) as $o ) {
			if ( empty( $vistos[ $o->get_id() ] ) ) {
				$orders[] = $o;
			}
		}
		usort(
			$orders,
			function ( $a, $b ) {
				$x = $a->get_date_created();
				$y = $b->get_date_created();
				return ( $y ? $y->getTimestamp() : 0 ) <=> ( $x ? $x->getTimestamp() : 0 );
			}
		);
	}
	$cats  = array();
	$prods = array();
	foreach ( (array) $orders as $o ) {
		$dc = $o->get_date_created();
		$ts = $dc ? $dc->getTimestamp() : 0;
		$v  = (float) $o->get_total();
		$mk = gmdate( 'Y-m', $ts );
		// Boleto/Pix ainda nao pago: aparece nas ultimas compras, mas so entra nos totais quando o pagamento cair.
		if ( 'pending' === $o->get_status() ) {
			if ( $ts >= $t0 && ! ( function_exists( 'dcmx_pend_vencido' ) && dcmx_pend_vencido( $o ) ) ) {
				$d['aguardando']++;
				$d['aguard_val'] += $v;
				if ( count( $d['ultimas'] ) < 5 ) {
					$cust = (int) $o->get_customer_id();
					$fr   = (int) $o->get_meta( '_dcom_franquia' );
					$fr   = $fr ? $fr : ( isset( $map[ $cust ] ) ? $map[ $cust ] : 0 );
					$d['ultimas'][] = array(
						'franquia' => isset( $names[ $fr ] ) ? $names[ $fr ] : 'Franquia',
						'pedido'   => (int) $o->get_id(),
						'data'     => $dc ? $dc->date_i18n( 'd/m/Y' ) : '',
						'valor'    => $v,
						'status'   => 'pending',
					);
				}
			}
			continue;
		}
		if ( isset( $d['meses'][ $mk ] ) ) {
			$d['meses'][ $mk ] += $v;
		}
		$cv = function_exists( 'dcmx_com_pedido' ) ? dcmx_com_pedido( $o ) : array( 'liq' => $v * dcmx_pct() / 100 );
		if ( $ts >= $t0 ) {
			$d['com']   += $cv['liq'];
			$d['com_desc'] += isset( $cv['desc'] ) ? $cv['desc'] : 0;
			$d['total'] += $v;
			$d['pedidos']++;
			if ( count( $d['ultimas'] ) < 5 ) {
				$cust = (int) $o->get_customer_id();
				$fr   = (int) $o->get_meta( '_dcom_franquia' );
				$fr   = $fr ? $fr : ( isset( $map[ $cust ] ) ? $map[ $cust ] : 0 );
				$d['ultimas'][] = array(
					'franquia' => isset( $names[ $fr ] ) ? $names[ $fr ] : 'Franquia',
					'pedido'   => (int) $o->get_id(),
					'data'     => $dc ? $dc->date_i18n( 'd/m/Y' ) : '',
					'valor'    => $v,
					'status'   => (string) $o->get_status(),
				);
			}
			foreach ( $o->get_items() as $it ) {
				$pidp = (int) $it->get_product_id();
				$q    = (int) $it->get_quantity();
				$tot  = (float) $it->get_total();
				$cn   = dcmx_cat_top( $pidp );
				$cats[ $cn ] = ( isset( $cats[ $cn ] ) ? $cats[ $cn ] : 0 ) + $tot;
				$pn   = wp_strip_all_tags( (string) $it->get_name() );
				if ( ! isset( $prods[ $pidp ] ) ) {
					$prods[ $pidp ] = array( 'nome' => $pn, 'qtd' => 0 );
				}
				$prods[ $pidp ]['qtd'] += $q;
			}
		} elseif ( $ts >= $t1 ) {
			$d['total_ant'] += $v;
			$d['com_ant']   += $cv['liq'];
			$d['pedidos_ant']++;
		}
	}
	arsort( $cats );
	$d['cats'] = $cats;
	uasort(
		$prods,
		function ( $a, $b ) {
			return $b['qtd'] <=> $a['qtd'];
		}
	);
	$d['prods'] = array_slice( $prods, 0, 5, true );
	return $d;
}

function dcmx_var( $cur, $ant ) {
	if ( $ant <= 0 ) {
		return '';
	}
	$p = ( $cur - $ant ) / $ant * 100;
	return '<em class="' . ( $p >= 0 ? 'up' : 'dn' ) . '">' . ( $p >= 0 ? '&uarr; +' : '&darr; ' ) . esc_html( number_format( $p, 1, ',', '' ) ) . '%</em>';
}

function dcmx_status( $s ) {
	$m = array(
		'processing' => array( 'Em produ&ccedil;&atilde;o', 'pr' ),
		'on-hold'    => array( 'Aguardando', 'ag' ),
		'pending'    => array( 'Aguardando pagamento', 'pg' ),
		'completed'  => array( 'Conclu&iacute;do', 'en' ),
	);
	$x = isset( $m[ $s ] ) ? $m[ $s ] : array( esc_html( $s ), 'ag' );
	return '<span class="st ' . $x[1] . '">' . $x[0] . '</span>';
}

function dcmx_linha_svg( $meses ) {
	$w = 640;
	$h = 230;
	$l = 56;
	$b = 34;
	$t = 16;
	$r = 20;
	$vals = array_values( $meses );
	$max  = max( $vals );
	$max  = $max > 0 ? $max : 1;
	// Escala "bonita": arredonda para cima.
	$pot  = pow( 10, max( 0, floor( log10( $max ) ) ) );
	$topo = ceil( $max / $pot ) * $pot;
	$topo = max( 4, $topo ); // sem compras (ou valores muito baixos): escala 0 a 4, sem repetir zeros
	$n    = max( 1, count( $vals ) - 1 );
	$pts  = array();
	$i    = 0;
	foreach ( $meses as $k => $v ) {
		$x     = $l + ( $w - $l - $r ) * ( $i / $n );
		$y     = $t + ( $h - $t - $b ) * ( 1 - ( $v / $topo ) );
		$pts[] = array( $x, $y, $k, $v );
		$i++;
	}
	$linha = '';
	foreach ( $pts as $p ) {
		$linha .= round( $p[0], 1 ) . ',' . round( $p[1], 1 ) . ' ';
	}
	$area = $l . ',' . ( $h - $b ) . ' ' . $linha . ( $w - $r ) . ',' . ( $h - $b );
	$s    = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Evolu&ccedil;&atilde;o das compras" style="width:100%;height:auto">';
	for ( $g = 0; $g <= 4; $g++ ) {
		$yy = $t + ( $h - $t - $b ) * ( $g / 4 );
		$vv = $topo * ( 1 - $g / 4 );
		$s .= '<line x1="' . $l . '" x2="' . ( $w - $r ) . '" y1="' . round( $yy, 1 ) . '" y2="' . round( $yy, 1 ) . '" stroke="#E7E9F0"/><text x="' . ( $l - 8 ) . '" y="' . round( $yy + 4, 1 ) . '" font-size="8.8" text-anchor="end" fill="#667085">' . esc_html( $vv >= 1000 ? number_format( $vv / 1000, 0, ',', '.' ) . ' mil' : (int) $vv ) . '</text>';
	}
	$s .= '<defs><linearGradient id="dcmxa" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#D9B45A" stop-opacity=".45"/><stop offset="1" stop-color="#D9B45A" stop-opacity=".03"/></linearGradient></defs><polygon points="' . $area . '" fill="url(#dcmxa)"/><polyline points="' . trim( $linha ) . '" fill="none" stroke="#C9A24A" stroke-width="2.5"/>';
	foreach ( $pts as $p ) {
		$s .= '<circle cx="' . round( $p[0], 1 ) . '" cy="' . round( $p[1], 1 ) . '" r="4" fill="#fff" stroke="#C9A24A" stroke-width="2"/><text x="' . round( $p[0], 1 ) . '" y="' . ( $h - 10 ) . '" font-size="8.8" text-anchor="middle" fill="#667085">' . esc_html( substr( $p[2], 5, 2 ) . '/' . substr( $p[2], 2, 2 ) ) . '</text>';
	}
	$ult = end( $pts );
	if ( $ult && $ult[3] > 0 ) {
		$tx  = 'R$ ' . number_format( $ult[3], 2, ',', '.' );
		$bw  = 14 + 7.2 * strlen( $tx );
		$bx  = min( $w - $bw - 4, max( 4, $ult[0] - $bw / 2 ) );
		$by  = max( 2, $ult[1] - 34 );
		$s  .= '<rect x="' . round( $bx, 1 ) . '" y="' . round( $by, 1 ) . '" width="' . round( $bw, 1 ) . '" height="22" rx="6" fill="#F3E6C4"/><text x="' . round( $bx + $bw / 2, 1 ) . '" y="' . round( $by + 15, 1 ) . '" font-size="9.6" font-weight="700" text-anchor="middle" fill="#0B1F3F">' . esc_html( $tx ) . '</text>';
	}
	return $s . '</svg>';
}

/* Icones de linha (SVG inline, herdam a cor). */
function dcmx_ico( $n ) {
	$m = array(
		'home'   => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
		'store'  => '<path d="M4 9l1.5-5h13L20 9"/><path d="M4 9a2.7 2.7 0 005.3 0 2.7 2.7 0 005.4 0A2.7 2.7 0 0020 9"/><path d="M5 12v8h14v-8"/><path d="M10 20v-5h4v5"/>',
		'cart'   => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12h11l2-8H6"/>',
		'doc'    => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h7M9 17h7"/>',
		'coins'  => '<ellipse cx="9" cy="7" rx="6" ry="3"/><path d="M3 7v5c0 1.7 2.7 3 6 3s6-1.3 6-3V7"/><path d="M15 11c3.3 0 6 1.300 6 3v4c0 1.700-2.700 3-6 3-2 0-3.700-.5-4.800-1.200"/>',
		'bars'   => '<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>',
		'trend'  => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 6-7"/>',
		'mega'   => '<path d="M3 10v4h4l8 5V5L7 10z"/><path d="M18 9a4 4 0 010 6"/>',
		'image'  => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.800"/><path d="M21 17l-5-5-9 8"/>',
		'users'  => '<circle cx="9" cy="8" r="3.500"/><path d="M2 20c0-3.500 3-6 7-6s7 2.500 7 6"/><circle cx="17" cy="9" r="2.500"/><path d="M17 14c3 0 5 2 5 5"/>',
		'gear'   => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
		'out'    => '<path d="M9 4H4v16h5M16 8l4 4-4 4M20 12H9"/>',
		'check'  => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 12l3 3 5-6"/>',
		'truck'  => '<path d="M2 6h12v10H2zM14 9h4l4 4v3h-8"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
		'bell'   => '<path d="M6 9a6 6 0 0112 0c0 6 2 7 2 7H4s2-1 2-7"/><path d="M10 20a2 2 0 004 0"/>',
		'help'   => '<circle cx="12" cy="12" r="9"/><path d="M9.500 9.500a2.500 2.500 0 115 .5c0 1.500-2.500 2-2.500 3.500M12 17h.01"/>',
		'cal'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.500-7 8-7s8 3 8 7"/>',
	);
	$d = isset( $m[ $n ] ) ? $m[ $n ] : '';
	return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/* Logo oficial (midia 120226, original-1.webp, 1013x171). */
function dcmx_logo() {
	return '<div class="lg"><img src="https://divulgarcriacoes.com.br/wp-content/uploads/2026/11/original-1.webp" alt="Ouro Moderno" width="1013" height="171" style="display:block;width:260px;max-width:60vw;height:auto;flex:0 0 auto"></div>';
}

function dcmx_css() {
	return '<style>'
		. '.dcmx{--dn:#0B1F3F;--dg:#C9A24A;margin:-10px -20px 0 -20px;font-family:inherit}.dcmx *{box-sizing:border-box}'
		. '.dcmx-top{background:linear-gradient(100deg,#07142b,var(--dn) 70%);color:#fff;padding:14px 26px;display:flex;align-items:center;gap:22px;flex-wrap:wrap}.dcmx-top .lg{display:flex;align-items:center;gap:12px;color:#fff}.dcmx-top .lg svg{width:62px;height:46px;flex:0 0 auto}.dcmx-top .lg b{display:block;font-size:31px;line-height:1;font-weight:800;letter-spacing:-.4px;white-space:nowrap}.dcmx-top .lg b sup{font-size:10px;font-weight:600;vertical-align:top;position:relative;top:2px;margin-left:2px}.dcmx-top .lg span{display:block;font-size:10px;letter-spacing:3.4px;color:#C9D3E8;margin-top:7px;white-space:nowrap}.dcmx-top .tt{border-left:1px solid rgba(255,255,255,.3);padding-left:22px}.dcmx-top .tt b{display:block;font-size:22px}.dcmx-top .tt span{font-size:13px;color:#C9D3E8}.dcmx-top .us{margin-left:auto;text-align:right}.dcmx-top .us b{display:block;font-size:14px}.dcmx-top .us span{font-size:12.5px;color:#C9D3E8}'
		. '.dcmx-wrap{display:grid;grid-template-columns:340px minmax(0,1fr);min-height:640px;background:#F4F5F8}'
		. '.dcmx-nav{background:var(--dn);padding:14px 10px}.dcmx-nav a{display:flex;align-items:center;justify-content:space-between;color:#D7DFEE;text-decoration:none;font-weight:600;font-size:14.5px;padding:12px 14px;border-radius:10px;margin:0 0 3px}.dcmx-nav a:hover{background:rgba(255,255,255,.08);color:#fff}.dcmx-nav a.on{background:linear-gradient(90deg,#B98F34,#D9B45A);color:#0B1F3F}.dcmx-nav a{gap:8px}.dcmx-nav i{flex:0 0 auto;white-space:nowrap;font-style:normal;font-size:9px;font-weight:700;letter-spacing:.4px;color:#0B1F3F;background:#F3E6C4;border-radius:999px;padding:2px 7px;text-transform:uppercase}'
		. '.dcmx-main{padding:18px 22px 30px;min-width:0}.dcmx-hd{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:14px}.dcmx-hd h2{margin:0;padding:0;font-size:32px;line-height:1.25;font-weight:800;color:#101828}.dcmx-hd p{margin:6px 0 0;line-height:1.4;color:#5B6478;font-size:15px}.dcmx-hd form{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.dcmx-hd select{min-height:40px;border:1px solid #D0D5DD;border-radius:10px;padding:0 12px;background:#fff;max-width:240px}.dcmx-hd button{min-height:40px;border:0;border-radius:10px;background:var(--dg);color:#0B1F3F;font-weight:800;padding:0 16px;cursor:pointer}'
		. '.dcmx-k{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:16px}.dcmx-k div{background:#fff;border:1px solid #E7E9F0;border-radius:16px;padding:16px 18px;box-shadow:0 6px 18px rgba(11,31,63,.05)}.dcmx-k span{display:block;font-size:13px;color:#667085}.dcmx-k b{display:block;font-size:26px;color:#101828;margin:2px 0}.dcmx-k em{font-style:normal;font-size:12.5px;font-weight:700}.up{color:#12805C}.dn{color:#B42318}'
		. '.dcmx-g{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:14px;margin-bottom:16px}.dcmx-c{background:#fff;border:1px solid #E7E9F0;border-radius:16px;padding:16px 18px;box-shadow:0 6px 18px rgba(11,31,63,.05)}.dcmx-c h3{margin:0 0 10px;font-size:18px;font-weight:800;color:#101828;display:flex;align-items:center;gap:8px}.dcmx-c h3:before{content:"";width:4px;height:18px;border-radius:2px;background:var(--dg)}'
		. '.dcmx-don{display:flex;align-items:center;gap:18px;flex-wrap:wrap}.dcmx-don .d{width:170px;height:170px;border-radius:50%;position:relative;flex:0 0 auto}.dcmx-don .d:after{content:"";position:absolute;inset:26px;background:#fff;border-radius:50%}.dcmx-don .d span{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:1;font-size:13px;color:#667085;text-align:center}.dcmx-don .d span b{font-size:14px;color:#101828}.dcmx-leg{flex:1;min-width:170px}.dcmx-leg div{display:flex;justify-content:space-between;gap:10px;padding:5px 0;border-bottom:1px solid #F0F1F5;font-size:13.5px}.dcmx-leg i{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:7px}'
		. '.dcmx-t{width:100%;border-collapse:collapse;font-size:13.5px}.dcmx-t th{text-align:left;color:#475467;background:#F4F5F8;padding:8px 10px}.dcmx-t td{padding:9px 10px;border-bottom:1px solid #F0F1F5}.st{display:inline-block;border-radius:999px;padding:3px 12px;font-size:12px;font-weight:700}.st.en{background:#D1FADF;color:#05603A}.st.pr{background:#FEF0C7;color:#93370D}.st.ag{background:#D1E9FF;color:#175CD3}.st.pg{background:#FEF0C7;color:#B54708}'
		. '.dcmx-top5 div{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1.5fr) 70px;gap:10px;align-items:center;padding:7px 0;border-bottom:1px solid #F0F1F5;font-size:13.5px}.dcmx-top5 .bar{height:14px;border-radius:7px;background:linear-gradient(90deg,#B98F34,#D9B45A)}.dcmx-top5 b{text-align:right}'
		. '.dcmx-a{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.dcmx-a a{display:block;text-align:center;background:#fff;border:1px solid #E7E9F0;border-radius:14px;padding:16px 8px;text-decoration:none;color:#101828;font-weight:700;font-size:14px;box-shadow:0 6px 18px rgba(11,31,63,.05)}.dcmx-a a:hover{border-color:var(--dg)}.dcmx-a i{display:block;font-style:normal;font-size:10px;color:#8A6D1F;font-weight:700;margin-top:4px;text-transform:uppercase}'
		. '.dcmx-cfg .button-primary{background:var(--dg);border-color:var(--dg);color:#0B1F3F;font-weight:800;min-height:40px;padding:0 20px}.dcmx-cfg .button-primary:hover{background:#D9B45A;border-color:#D9B45A;color:#0B1F3F}.dcmx-cfg{background:#fff;border:1px solid #E7E9F0;border-radius:16px;padding:20px;max-width:520px}'
		. '.dcmx .ic{width:22px;height:22px;flex:0 0 auto}.dcmx-nav a .t{display:flex;align-items:center;gap:12px;min-width:0}.dcmx-nav a .t em{font-style:normal;font-size:14.5px;font-weight:600;line-height:1.2;white-space:nowrap}.dcmx-nav a.on .t em{color:#0B1F3F}'
		. '.dcmx-top{position:relative;overflow:hidden}.dcmx-top:after{content:"";position:absolute;right:-6%;bottom:-78px;width:62%;height:100px;border-radius:50%;border-top:3px solid #D9B45A;pointer-events:none;opacity:.85}.dcmx-top .us{display:flex;align-items:center;gap:14px;position:relative;z-index:1}.dcmx-top .us>div{text-align:left;margin-left:2px}.dcmx-top .us .bt{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;background:rgba(255,255,255,.08)}.dcmx-top .us .av{width:44px;height:44px;border-radius:50%;background:#fff;color:#0B1F3F;display:flex;align-items:center;justify-content:center}.dcmx-top .us .av .ic{width:26px;height:26px}'
		. '.dcmx-k div{display:flex;align-items:center;gap:14px}.dcmx-k .kt{flex:0 0 auto;width:58px;height:58px;border-radius:14px;background:linear-gradient(135deg,#D9B45A,#B98F34);color:#fff;display:flex;align-items:center;justify-content:center}.dcmx-k .kt .ic{width:30px;height:30px}.dcmx-k p{margin:0;min-width:0}.dcmx-k p b{font-size:25px}'
		. '.dcmx-hd .pl{display:flex;align-items:center;gap:6px;font-weight:600;color:#344054;font-size:14px}.dcmx-hd .pl .ic{width:18px;height:18px}'
		. '.dcmx-a a .ic{display:block;margin:0 auto 8px;width:30px;height:30px;color:#B98F34}'
		. '@media(max-width:1200px){.dcmx-k{grid-template-columns:repeat(2,minmax(0,1fr))}.dcmx-g{grid-template-columns:1fr}.dcmx-a{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:860px){.dcmx-wrap{grid-template-columns:1fr}.dcmx-a{grid-template-columns:repeat(2,minmax(0,1fr))}}'
		. '</style>';
}

/* E-mail de aprovacao (HTML, com botoes clicaveis e a marca Ouro Moderno). */
function dcmx_email_aprovada( $resp, $escola, $loja, $entra ) {
	$bt = function ( $url, $txt, $cor, $fg ) {
		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 12px"><tr><td style="border-radius:12px;background:' . $cor . '"><a href="' . esc_url( $url ) . '" target="_blank" style="display:inline-block;padding:15px 34px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;color:' . $fg . ';text-decoration:none;border-radius:12px">' . esc_html( $txt ) . '</a></td></tr></table>';
	};
	return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
		. '<body style="margin:0;padding:0;background:#EEF1F6"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EEF1F6"><tr><td align="center" style="padding:28px 12px">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden">'
		. '<tr><td align="center" style="background:#0B2A5B;padding:26px 20px"><img src="https://divulgarcriacoes.com.br/wp-content/uploads/2026/11/original-1.webp" width="240" alt="Ouro Moderno" style="display:block;width:240px;max-width:80%;height:auto;border:0"></td></tr>'
		. '<tr><td style="height:5px;background:#F5B400;font-size:0;line-height:0">&nbsp;</td></tr>'
		. '<tr><td style="padding:34px 32px 10px;font-family:Arial,Helvetica,sans-serif;color:#1C2433;text-align:center">'
		. '<div style="font-size:26px;font-weight:bold;color:#0B2A5B;margin:0 0 12px">Parab&eacute;ns, ' . esc_html( $resp ) . '!</div>'
		. '<div style="font-size:16px;line-height:1.6;color:#344054;margin:0 0 8px">O cadastro da <b>' . esc_html( $escola ) . '</b> foi <b style="color:#12805C">aprovado</b>.</div>'
		. '<div style="font-size:16px;line-height:1.6;color:#344054;margin:0 0 26px">A loja da sua escola j&aacute; est&aacute; liberada. Entre com o e-mail e a senha que voc&ecirc; cadastrou.</div>'
		. $bt( $loja, 'Abrir minha loja', '#F5B400', '#0B2A5B' )
		. $bt( $entra, 'Entrar no painel', '#0B2A5B', '#ffffff' )
		. '<div style="font-size:13px;line-height:1.6;color:#667085;margin:18px 0 0">Endere&ccedil;o da loja: <a href="' . esc_url( $loja ) . '" style="color:#0B2A5B">' . esc_html( $loja ) . '</a></div>'
		. '</td></tr><tr><td align="center" style="padding:24px 20px 28px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#98A2B3">Ouro Moderno &mdash; Desenvolvido por Giftbrand</td></tr>'
		. '</table></td></tr></table></body></html>';
}

/* Aprovar / reprovar franquia (POST da tela Franquias). Roda antes de montar o painel, para o aviso e a contagem ja sairem certos. */
function dcmx_fr_processar() {
	$aviso = '';
	// Aprovar / reprovar uma franquia (Diretoria e administrador).
	if ( current_user_can( 'dcmx_config' ) && isset( $_POST['dcmx_fr_acao'], $_POST['dcmx_fr_id'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( isset( $_POST['dcmx_fr_n'] ) ? $_POST['dcmx_fr_n'] : '' ) ), 'dcmx_fr' ) ) { // phpcs:ignore
		$fid  = absint( $_POST['dcmx_fr_id'] ); // phpcs:ignore
		$acao = sanitize_key( wp_unslash( $_POST['dcmx_fr_acao'] ) ); // phpcs:ignore
		$fu   = $fid ? get_userdata( $fid ) : false;
		if ( $fu && in_array( 'escola', (array) $fu->roles, true ) && (int) get_user_meta( $fid, 'omx_parent', true ) <= 0 && in_array( $acao, array( 'aprovar', 'reprovar' ), true ) ) {
			$nome_e = (string) get_user_meta( $fid, 'omx_escola', true );
			if ( 'aprovar' === $acao ) {
				if ( '' === (string) get_user_meta( $fid, 'omx_slug', true ) ) {
					update_user_meta( $fid, 'omx_slug', sanitize_title( '' !== $nome_e ? $nome_e : 'escola-' . $fid ) );
				}
				update_user_meta( $fid, 'omx_status', 'aprovada' );
				if ( function_exists( 'dcom_loja_garantir_cat' ) ) {
					dcom_loja_garantir_cat( $fid ); // categoria da franquia com os produtos padrao
				}
				update_user_meta( $fid, 'omx_aprovada_em', current_time( 'mysql' ) );
				update_user_meta( $fid, 'omx_aprovada_por', get_current_user_id() );
				delete_transient( 'omx_cfg_cache' );
				$slug  = (string) get_user_meta( $fid, 'omx_slug', true );
				$lurl  = function_exists( 'dcom_loja_url' ) ? dcom_loja_url( $fid ) : 'https://ouromoderno.giftbrand.com.br/' . rawurlencode( $slug ) . '/';
				$entra = 'https://ouromoderno.giftbrand.com.br/my-account-2/entrar-escola/';
				wp_mail(
					$fu->user_email,
					'[Ouro Moderno] Cadastro aprovado: ' . $nome_e,
					dcmx_email_aprovada( $fu->display_name, $nome_e, $lurl, $entra ),
					array( 'Content-Type: text/html; charset=UTF-8', 'From: Ouro Moderno <' . sanitize_email( (string) get_option( 'admin_email' ) ) . '>' )
				);
				$aviso = '<div class="notice"><p><b>' . esc_html( $nome_e ) . '</b> aprovada. A loja foi liberada em ' . esc_html( $lurl ) . ' e a escola recebeu um e-mail.</p></div>';
			} else {
				update_user_meta( $fid, 'omx_status', 'suspensa' );
				delete_transient( 'omx_cfg_cache' );
				$aviso = '<div class="notice"><p><b>' . esc_html( $nome_e ) . '</b> reprovada. A loja da escola fica fechada.</p></div>';
			}
		}
	}
	return $aviso;
}

/* Quantas franquias aguardam aprovacao (unidade principal, papel escola, situacao diferente de aprovada). */
function dcmx_pendentes() {
	static $n = null;
	if ( null !== $n ) {
		return $n;
	}
	global $wpdb;
	$cap = $wpdb->get_blog_prefix() . 'capabilities';
	// Uma consulta so (a rede tem mais de mil escolas).
	$n = (int) $wpdb->get_var( // phpcs:ignore
		$wpdb->prepare(
			"SELECT COUNT(DISTINCT e.user_id) FROM {$wpdb->usermeta} e
			INNER JOIN {$wpdb->usermeta} c ON c.user_id = e.user_id AND c.meta_key = %s AND c.meta_value LIKE %s
			LEFT JOIN {$wpdb->usermeta} s ON s.user_id = e.user_id AND s.meta_key = 'omx_status'
			LEFT JOIN {$wpdb->usermeta} p ON p.user_id = e.user_id AND p.meta_key = 'omx_parent'
			WHERE e.meta_key = 'omx_escola' AND e.meta_value <> ''
			AND ( s.meta_value IS NULL OR s.meta_value <> 'aprovada' )
			AND ( p.meta_value IS NULL OR p.meta_value + 0 <= 0 )",
			$cap,
			'%"escola"%'
		)
	);
	return $n;
}

/* Tela Franquias: lista das unidades (somente leitura), com busca e filtro de situacao. */
function dcmx_tela_franquias( $base, $pode_us ) {
	global $wpdb;
	$q  = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore
	$sf = isset( $_GET['situacao'] ) ? sanitize_key( wp_unslash( $_GET['situacao'] ) ) : ''; // phpcs:ignore
	$pode_ap = current_user_can( 'dcmx_config' );
	$aviso   = isset( $GLOBALS['dcmx_fr_aviso'] ) ? (string) $GLOBALS['dcmx_fr_aviso'] : '';
	$ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'omx_escola' AND meta_value <> ''" ) ); // phpcs:ignore
	if ( $ids ) {
		cache_users( $ids ); // carrega usuarios e metas de uma vez (rede com mais de mil escolas)
	}
	// Equipe de cada unidade (contas ligadas pelo omx_parent).
	$equipe = array();
	foreach ( $wpdb->get_results( "SELECT meta_value, COUNT(*) AS n FROM {$wpdb->usermeta} WHERE meta_key = 'omx_parent' AND meta_value + 0 > 0 GROUP BY meta_value" ) as $r ) { // phpcs:ignore
		$equipe[ (int) $r->meta_value ] = (int) $r->n;
	}
	$rot   = array( 'aprovada' => array( 'Ativa', 'en' ), 'pendente' => array( 'Em an&aacute;lise', 'pr' ), 'suspensa' => array( 'Reprovada', 'rp' ), 'reprovada' => array( 'Reprovada', 'pr' ), 'bloqueada' => array( 'Bloqueada', 'pr' ) );
	$lin   = array();
	$conta = array( 'todas' => 0, 'aprovada' => 0, 'outras' => 0 );
	foreach ( $ids as $id ) {
		$u = get_userdata( $id );
		if ( ! $u ) {
			continue;
		}
		$par = (int) get_user_meta( $id, 'omx_parent', true );
		if ( $par > 0 && $par !== $id ) {
			continue; // e da equipe de outra unidade, nao uma franquia
		}
		$nome = (string) get_user_meta( $id, 'omx_escola', true );
		$cid  = (string) get_user_meta( $id, 'omx_cidade', true );
		$tel  = (string) get_user_meta( $id, 'omx_tel', true );
		$st   = (string) get_user_meta( $id, 'omx_status', true );
		$conta['todas']++;
		$conta[ 'aprovada' === $st ? 'aprovada' : 'outras' ]++;
		if ( 'ativas' === $sf && 'aprovada' !== $st ) {
			continue;
		}
		if ( 'outras' === $sf && 'aprovada' === $st ) {
			continue;
		}
		if ( '' !== $q && false === stripos( $nome . ' ' . $cid . ' ' . $u->user_email . ' ' . $tel, $q ) ) {
			continue;
		}
		$lin[] = array( $nome, $cid, $tel, $u->user_email, $st, isset( $equipe[ $id ] ) ? $equipe[ $id ] : 0, 0, 0.0, '', $id, $u->user_registered );
	}
	// Em analise primeiro, depois por nome.
	usort(
		$lin,
		function ( $a, $b ) {
			$pa = 'aprovada' === $a[4] ? 1 : 0;
			$pb = 'aprovada' === $b[4] ? 1 : 0;
			return $pa !== $pb ? $pa - $pb : strcasecmp( $a[0], $b[0] );
		}
	);
	// Paginas de 50: pedidos e compras so das escolas da pagina aberta.
	$pp    = 50;
	$total = count( $lin );
	$pags  = max( 1, (int) ceil( $total / $pp ) );
	$pg    = isset( $_GET['fp'] ) ? min( $pags, max( 1, absint( $_GET['fp'] ) ) ) : 1; // phpcs:ignore
	$lin   = array_slice( $lin, ( $pg - 1 ) * $pp, $pp );
	foreach ( $lin as $k => $l ) {
		$id    = (int) $l[9];
		$grupo = array( $id );
		foreach ( (array) get_users( array( 'meta_key' => 'omx_parent', 'meta_value' => $id, 'fields' => 'ID' ) ) as $e ) { // phpcs:ignore
			$grupo[] = (int) $e;
		}
		$tot = 0.0;
		$ped = 0;
		if ( function_exists( 'wc_get_orders' ) ) {
			$vist = array();
			foreach ( (array) wc_get_orders( array( 'customer' => $grupo, 'limit' => 500, 'status' => array( 'processing', 'on-hold', 'completed' ) ) ) as $o ) {
				$tot += (float) $o->get_total();
				$ped++;
				$vist[ $o->get_id() ] = 1;
			}
			if ( function_exists( 'dcom_pedidos_marcados' ) ) {
				foreach ( dcom_pedidos_marcados( $id ) as $o ) {
					if ( empty( $vist[ $o->get_id() ] ) ) {
						$tot += (float) $o->get_total();
						$ped++;
					}
				}
			}
		}
		$lin[ $k ][6] = $ped;
		$lin[ $k ][7] = $tot;
		$lin[ $k ][8] = function_exists( 'dcom_loja_url' ) ? dcom_loja_url( $id ) : '';
	}
	$f = function ( $v, $t ) use ( $base, $q, $sf ) {
		$u = add_query_arg( array( 'tela' => 'franquias', 'situacao' => $v ), $base );
		return '<a class="fx' . ( $sf === $v ? ' on' : '' ) . '" href="' . esc_url( $u ) . '">' . $t . '</a>';
	};
	echo '<style>.dcmx-fl{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px}.dcmx-fl .fx{padding:8px 14px;border-radius:999px;border:1px solid #D0D5DD;background:#fff;color:#344054;text-decoration:none;font-weight:700;font-size:13px}.dcmx-fl .fx.on{background:var(--dn);border-color:var(--dn);color:#fff}.dcmx-fl form{margin-left:auto;display:flex;gap:8px}.dcmx-fl input[type=search]{min-height:38px;border:1px solid #D0D5DD;border-radius:10px;padding:0 12px;min-width:240px}.dcmx-fl button{min-height:38px;border:0;border-radius:10px;background:var(--dg);color:#0B1F3F;font-weight:800;padding:0 16px;cursor:pointer}.dcmx-t td a{color:#0B1F3F;font-weight:700}.dcmx-t small{color:#667085}</style>'; // phpcs:ignore
	echo '<div class="dcmx-hd"><div><h2>Franquias</h2><p>Todas as unidades da rede Ouro Moderno, com a equipe e as compras de cada uma.</p></div>'
		. ( $pode_us ? '<form><a class="button" style="min-height:40px;line-height:40px;border-radius:10px;padding:0 16px;background:#fff;border:1px solid #D0D5DD;color:#0B1F3F;font-weight:700;text-decoration:none" href="' . esc_url( admin_url( 'users.php?role=escola' ) ) . '">Gerenciar no WordPress</a></form>' : '' ) . '</div>'; // phpcs:ignore
	echo '<div class="dcmx-fl">' . $f( '', 'Todas (' . (int) $conta['todas'] . ')' ) . $f( 'ativas', 'Ativas (' . (int) $conta['aprovada'] . ')' ) . $f( 'outras', 'Em an&aacute;lise / outras (' . (int) $conta['outras'] . ')' ) // phpcs:ignore
		. '<form method="get" action="' . esc_url( is_admin() ? admin_url( 'admin.php' ) : $base ) . '">' . ( is_admin() ? '<input type="hidden" name="page" value="dcmx">' : '' ) . '<input type="hidden" name="tela" value="franquias"><input type="hidden" name="situacao" value="' . esc_attr( $sf ) . '"><input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="Buscar unidade, cidade, e-mail..."><button type="submit">Buscar</button></form></div>'; // phpcs:ignore
	$bt = function ( $id, $acao, $txt, $nome ) {
		$msg = 'aprovar' === $acao ? 'Aprovar ' . $nome . ' e liberar a loja?' : $txt . ' ' . $nome . '? A loja da escola fica fechada.';
		return '<form method="post" style="display:inline" onsubmit="return confirm(' . esc_attr( wp_json_encode( $msg ) ) . ');">' . wp_nonce_field( 'dcmx_fr', 'dcmx_fr_n', false, false ) . '<input type="hidden" name="dcmx_fr_id" value="' . (int) $id . '"><input type="hidden" name="dcmx_fr_acao" value="' . esc_attr( $acao ) . '"><button type="submit" class="fb ' . esc_attr( $acao ) . '">' . esc_html( $txt ) . '</button></form>';
	};
	echo '<style>.dcmx-t td.ac{white-space:nowrap}.dcmx-t .fb{border:0;border-radius:9px;padding:7px 13px;font-weight:800;font-size:12.5px;cursor:pointer;margin:0 4px 0 0}.dcmx-t .fb.aprovar{background:#12805C;color:#fff}.dcmx-t .fb.reprovar{background:#fff;color:#B42318;border:1px solid #F1B5AE}.st.rp{background:#FEE4E2;color:#912018}.dcmx-main .notice{background:#ECFDF3;border:1px solid #ABEFC6;border-left:5px solid #12805C;border-radius:12px;padding:4px 16px;margin:0 0 14px}</style>' . $aviso; // phpcs:ignore
	echo '<div class="dcmx-c" style="overflow-x:auto"><table class="dcmx-t"><thead><tr><th>Unidade</th><th>Cidade</th><th>Contato</th><th>Situa&ccedil;&atilde;o</th><th>Equipe</th><th>Pedidos</th><th>Compras</th><th>Loja</th>' . ( $pode_ap ? '<th>A&ccedil;&otilde;es</th>' : '' ) . '</tr></thead><tbody>';
	if ( ! $lin ) {
		echo '<tr><td colspan="9" style="text-align:center;color:#667085;padding:22px">Nenhuma franquia encontrada.</td></tr>';
	}
	foreach ( $lin as $l ) {
		$r = isset( $rot[ $l[4] ] ) ? $rot[ $l[4] ] : array( '' !== $l[4] ? esc_html( ucfirst( $l[4] ) ) : 'Sem situa&ccedil;&atilde;o', 'ag' );
		echo '<tr><td><b>' . esc_html( '' !== $l[0] ? $l[0] : 'Unidade #' . $l[9] ) . '</b><br><small>desde ' . esc_html( mysql2date( 'd/m/Y', $l[10] ) ) . '</small></td><td>' . esc_html( $l[1] ) . '</td><td>' . esc_html( $l[3] ) . ( '' !== $l[2] ? '<br><small>' . esc_html( $l[2] ) . '</small>' : '' ) . '</td><td><span class="st ' . esc_attr( $r[1] ) . '">' . $r[0] . '</span></td><td>' . (int) $l[5] . '</td><td>' . (int) $l[6] . '</td><td>' . esc_html( dcmx_money( $l[7] ) ) . '</td><td>' . ( '' !== $l[8] && 'aprovada' === $l[4] ? '<a href="' . esc_url( $l[8] ) . '" target="_blank" rel="noopener">Abrir</a>' : '<small>ap&oacute;s aprova&ccedil;&atilde;o</small>' ) . '</td>' // phpcs:ignore
			. ( $pode_ap ? '<td class="ac">' . ( 'aprovada' !== $l[4] ? $bt( $l[9], 'aprovar', 'Aprovar', $l[0] ) : '' ) . ( 'suspensa' !== $l[4] ? $bt( $l[9], 'reprovar', 'aprovada' === $l[4] ? 'Suspender' : 'Reprovar', $l[0] ) : '' ) . '</td>' : '' ) // phpcs:ignore
			. '</tr>';
	}
	echo '</tbody></table></div>';
	if ( $pags > 1 ) {
		$nav = '';
		for ( $i = 1; $i <= $pags; $i++ ) {
			if ( $i > 2 && $i < $pags - 1 && abs( $i - $pg ) > 2 ) {
				if ( false === strpos( substr( $nav, -12 ), '&hellip;' ) ) {
					$nav .= '<span class="pg-r">&hellip;</span>';
				}
				continue;
			}
			$u    = add_query_arg( array( 'tela' => 'franquias', 'situacao' => $sf, 'q' => $q, 'fp' => $i ), $base );
			$nav .= '<a class="pg' . ( $i === $pg ? ' on' : '' ) . '" href="' . esc_url( $u ) . '">' . $i . '</a>';
		}
		echo '<style>.dcmx-pg{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:14px 0 0}.dcmx-pg .pg{min-width:36px;height:36px;border-radius:10px;border:1px solid #D0D5DD;background:#fff;color:#344054;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-weight:700}.dcmx-pg .pg.on{background:#0B1F3F;border-color:#0B1F3F;color:#fff}.dcmx-pg span{color:#667085}</style><div class="dcmx-pg"><span>' . (int) $total . ' escola(s) &middot; p&aacute;gina ' . (int) $pg . ' de ' . (int) $pags . '</span>' . $nav . '</div>'; // phpcs:ignore
	}
}

function dcmx_pagina() {
	if ( ! current_user_can( 'dcmx_ver' ) ) {
		wp_die( 'Sem permissão.' );
	}
	$tela = isset( $_GET['tela'] ) ? sanitize_key( wp_unslash( $_GET['tela'] ) ) : ''; // phpcs:ignore
	$msg  = '';
	if ( 'config' === $tela && current_user_can( 'dcmx_config' ) && isset( $_POST['dcmx_pct'] ) && check_admin_referer( 'dcmx_cfg', 'dcmx_n' ) ) { // phpcs:ignore
		// comissao fixa (DCMX_COMISSAO_FIXA): nada a gravar // phpcs:ignore
		$msg = '<div class="notice notice-success"><p>Percentual salvo.</p></div>';
	}
	$u    = wp_get_current_user();
	$base = dcmx_base();
	$GLOBALS['dcmx_fr_aviso'] = dcmx_fr_processar();
	$pend = dcmx_pendentes();
	$nav  = array(
		array( 'Dashboard', $base, '', 'on' , 'home' ),
		array( 'Franquias', add_query_arg( 'tela', 'franquias', $base ), $pend > 0 ? sprintf( '%02d', $pend ) : '', '' , 'store', 'al' ),
		array( 'Loja Ouro Moderno', 'https://ouromoderno.giftbrand.com.br/', '', '' , 'cart' ),
		array( 'Compras da Rede', dcmx_orders_url(), '', '' , 'cart' ),
		array( 'Orçamentos', admin_url( 'admin.php?page=dc-painel-comercial' ), '', '' , 'doc' ),
		array( 'Comissões', add_query_arg( 'tela', 'comissoes', $base ), '', '' , 'coins' ),
		array( 'Produtos Mais Comprados', add_query_arg( 'tela', 'produtos', $base ), '', '' , 'bars' ),
		array( 'Relatórios', add_query_arg( 'tela', 'relatorios', $base ), '', '' , 'trend' ),
		array( 'Campanhas da Rede', add_query_arg( 'tela', 'campanhas', $base ), '', '' , 'mega' ),
		array( 'Materiais e Artes', add_query_arg( 'tela', 'materiais', $base ), '', '' , 'image' ),
		array( 'Equipe da Matriz', admin_url( 'users.php?role=administrator' ), '', '' , 'users' ),
		array( 'Configurações', add_query_arg( 'tela', 'config', $base ), '', '' , 'gear' ),
		array( 'Pedidos de Arte', add_query_arg( 'tela', 'pedidosarte', $base ), '', '' , 'image' ),
			array( 'Estampas da Rede', add_query_arg( 'tela', 'estampas', $base ), '', '' , 'image' ),
	);
	if ( ! is_admin() ) {
		$nav[] = array( 'Sair', wp_logout_url( home_url( '/' ) ), '', '', 'out' );
	}
	if ( 'config' === $tela && ! current_user_can( 'dcmx_config' ) ) {
		$tela = '';
	}
	if ( 'config' === $tela ) {
		$nav[0][3] = '';
		$nav[11][3] = 'on';
	}
	if ( 'franquias' === $tela ) {
		$nav[0][3] = '';
		$nav[1][3] = 'on';
	}
	if ( 'comissoes' === $tela ) {
		$nav[0][3] = '';
		$nav[5][3] = 'on';
	}
	$telas_novas = array( 'produtos' => 6, 'relatorios' => 7, 'campanhas' => 8, 'materiais' => 9, 'pedidosarte' => 12, 'estampas' => 13 );
	if ( isset( $telas_novas[ $tela ] ) ) {
		$nav[0][3] = '';
		$nav[ $telas_novas[ $tela ] ][3] = 'on';
	}
	// Esconde o que a conta nao consegue abrir (a Diretoria so tem o painel; o administrador ve tudo).
	$pode_wp = current_user_can( 'manage_woocommerce' );
	$pode_us = current_user_can( 'list_users' );
	$pode_cf = current_user_can( 'dcmx_config' );
	$nav     = array_values(
		array_filter(
			$nav,
			function ( $n ) use ( $pode_wp, $pode_us, $pode_cf ) {
				switch ( $n[0] ) {
					case 'Equipe da Matriz':
						return $pode_us;
					case 'Compras da Rede':
					case 'Orçamentos':
						return $pode_wp;
					case 'Configurações':
						return $pode_cf;
				}
				return true;
			}
		)
	);
	echo dcmx_css(); // phpcs:ignore
	echo '<div class="dcmx"><div class="dcmx-top">' . dcmx_logo() . '<div class="tt"><b>Painel da Matriz</b><span>Controle da Rede de Franquias</span></div><div class="us"><a class="bt sino" title="' . esc_attr( $pend > 0 ? $pend . ' franquia(s) aguardando aprovação' : 'Sem avisos' ) . '" href="' . esc_url( add_query_arg( array( 'tela' => 'franquias', 'situacao' => 'outras' ), $base ) ) . '">' . dcmx_ico( 'bell' ) . ( $pend > 0 ? '<em>' . (int) $pend . '</em>' : '' ) . '</a><span class="bt" title="Ajuda">' . dcmx_ico( 'help' ) . '</span><span class="av">' . dcmx_ico( 'user' ) . '</span><div><b>Matriz Ouro Moderno</b><span>' . esc_html( current_user_can( 'manage_options' ) ? 'Administrador' : 'Diretoria' ) . '</span></div></div></div><div class="dcmx-wrap"><div class="dcmx-nav">'; // phpcs:ignore
	foreach ( $nav as $n ) {
		echo '<a class="' . esc_attr( $n[3] . ( ! empty( $n[5] ) && '' !== $n[2] ? ' ' . $n[5] : '' ) ) . '" href="' . esc_url( $n[1] ) . '"' . ( 0 === strpos( $n[1], 'https://ouromoderno.' ) ? ' target="_blank" rel="noopener"' : '' ) . '><span class="t">' . dcmx_ico( $n[4] ) . '<em>' . esc_html( $n[0] ) . '</em></span>' . ( '' !== $n[2] ? '<i>' . esc_html( $n[2] ) . '</i>' : '' ) . '</a>'; // phpcs:ignore
	}
	echo '<style>.dcmx-nav a.al i{background:#F5B400!important;color:#0B1F3F!important;width:28px;height:28px;padding:0!important;border-radius:50%!important;display:inline-flex;align-items:center;justify-content:center;font-size:12px!important;font-weight:800!important;letter-spacing:0!important;box-shadow:0 0 0 3px rgba(245,180,0,.25)}.dcmx-top .us a.bt{position:relative;text-decoration:none}.dcmx-top .us .sino em{position:absolute;top:-4px;right:-4px;min-width:20px;height:20px;border-radius:10px;background:#F5B400;color:#0B1F3F;font:800 11px/20px system-ui,sans-serif;font-style:normal;text-align:center;padding:0 5px}.dcmx-av{display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:#FFF8E1;border:1px solid #F5C451;border-left:5px solid #F5B400;border-radius:14px;padding:14px 18px;margin:0 0 16px;color:#5B4300;font-size:15px}.dcmx-av b{color:#0B1F3F}.dcmx-av a{margin-left:auto;background:#0B1F3F;color:#fff!important;text-decoration:none;font-weight:700;border-radius:10px;padding:9px 16px;font-size:14px}</style>'; // phpcs:ignore
	echo '</div><div class="dcmx-main">' . $msg; // phpcs:ignore
	if ( $pend > 0 && 'franquias' !== $tela ) {
		echo '<div class="dcmx-av">' . dcmx_ico( 'bell' ) . '<span><b>' . (int) $pend . ' ' . ( 1 === $pend ? 'franquia aguardando' : 'franquias aguardando' ) . ' aprova&ccedil;&atilde;o.</b> Confira os dados e aprove para liberar a loja.</span><a href="' . esc_url( add_query_arg( array( 'tela' => 'franquias', 'situacao' => 'outras' ), $base ) ) . '">Ver franquias</a></div>'; // phpcs:ignore
	}

	if ( 'config' === $tela ) {
		echo '<div class="dcmx-hd"><div><h2>Configura&ccedil;&otilde;es</h2><p>Regras da Matriz.</p></div></div><form method="post" class="dcmx-cfg">' . wp_nonce_field( 'dcmx_cfg', 'dcmx_n', true, false ) // phpcs:ignore
			. '<p><strong>Comiss&atilde;o da Matriz</strong><br><span style="display:inline-block;margin-top:6px;background:#F3E6C4;color:#0B1F3F;font-weight:800;border-radius:10px;padding:8px 14px">' . esc_html( number_format( dcmx_pct(), 0 ) ) . '% fixa</span></p><p style="color:#5B6478;margin:0">Calculada sobre as compras da rede. N&atilde;o muda o pre&ccedil;o dos produtos.</p></form>' . ( function_exists( 'dcom_api_config_html' ) ? dcom_api_config_html() : '' ) . '</div></div></div>'; // phpcs:ignore
		return;
	}
	if ( 'franquias' === $tela ) {
		dcmx_tela_franquias( $base, $pode_us );
		echo '</div></div></div>';
		return;
	}
	if ( 'comissoes' === $tela ) {
		dcmx_tela_comissoes( $base );
		echo '</div></div></div>';
		return;
	}
	if ( isset( $telas_novas[ $tela ] ) && function_exists( 'dcmx_tela_' . $tela ) ) {
		call_user_func( 'dcmx_tela_' . $tela, $base );
		echo '</div></div></div>';
		return;
	}

	try {
		$dias_ok = array( 7 => 'Últimos 7 dias', 30 => 'Últimos 30 dias', 90 => 'Últimos 90 dias', 365 => 'Últimos 12 meses' );
		$dias    = isset( $_GET['periodo'] ) ? absint( $_GET['periodo'] ) : 30; // phpcs:ignore
		$dias    = isset( $dias_ok[ $dias ] ) ? $dias : 30;
		$fid     = isset( $_GET['franquia'] ) ? absint( $_GET['franquia'] ) : 0; // phpcs:ignore
		$d       = dcmx_dados( $dias, $fid );
		$pct     = dcmx_pct();
		$com     = $d['com'];
		$com_ant = $d['com_ant'];

		$opt = '<option value="0">Todas as Franquias</option>';
		$lst = array();
		foreach ( $d['aprov'] as $id ) {
			if ( isset( $d['nomes'][ $id ] ) ) {
				$lst[ $id ] = $d['nomes'][ $id ];
			}
		}
		asort( $lst );
		foreach ( $lst as $id => $nm ) {
			$opt .= '<option value="' . (int) $id . '"' . selected( $fid, $id, false ) . '>' . esc_html( $nm ) . '</option>';
		}
		$per = '';
		foreach ( $dias_ok as $k => $lb ) {
			$per .= '<option value="' . (int) $k . '"' . selected( $dias, $k, false ) . '>' . esc_html( $lb ) . '</option>';
		}
		echo '<div class="dcmx-hd"><div><h2>Ol&aacute;, Matriz!</h2><p>Acompanhe as compras, os or&ccedil;amentos, as comiss&otilde;es e o desempenho de toda a rede Ouro Moderno.</p></div><form method="get" action="' . esc_url( is_admin() ? admin_url( 'admin.php' ) : $base ) . '">' . ( is_admin() ? '<input type="hidden" name="page" value="dcmx">' : '' ) . '<label class="pl">' . dcmx_ico( 'cal' ) . 'Per&iacute;odo:</label><select name="periodo">' . $per . '</select><select name="franquia">' . $opt . '</select><button type="submit">Aplicar</button></form></div>'; // phpcs:ignore

		echo '<div class="dcmx-k">' // phpcs:ignore
			. '<div><i class="kt">' . dcmx_ico( 'store' ) . '</i><p><span>Franquias Ativas</span><b>' . (int) $d['franquias'] . '</b>' . ( $d['novas'] > 0 ? '<em class="up">&uarr; +' . (int) $d['novas'] . ' novas</em>' : '' ) . '</p></div>'
			. '<div><i class="kt">' . dcmx_ico( 'cart' ) . '</i><p><span>Total de Compras</span><b>' . esc_html( dcmx_money( $d['total'] ) ) . '</b>' . dcmx_var( $d['total'], $d['total_ant'] ) . '</p></div>' // phpcs:ignore
			. '<div><i class="kt">' . dcmx_ico( 'doc' ) . '</i><p><span>Pedidos pagos</span><b>' . (int) $d['pedidos'] . '</b>' . dcmx_var( $d['pedidos'], $d['pedidos_ant'] ) . ( $d['aguardando'] > 0 ? '<em style="display:block;color:#B54708">+ ' . (int) $d['aguardando'] . ' aguardando pagamento (' . esc_html( dcmx_money( $d['aguard_val'] ) ) . ')</em>' : '' ) . '</p></div>' // phpcs:ignore
			. '<div><i class="kt">' . dcmx_ico( 'coins' ) . '</i><p><span>Comiss&atilde;o (' . esc_html( number_format( $pct, 0 ) ) . '%, l&iacute;quida)</span><b>' . esc_html( dcmx_money( $com ) ) . '</b>' . dcmx_var( $com, $com_ant ) . ( $d['com_desc'] > 0 ? '<em style="display:block;color:#B42318">&minus; ' . esc_html( dcmx_money( $d['com_desc'] ) ) . ' descontados das notas (' . esc_html( number_format( dcmx_desc_nota(), 1, ',', '' ) ) . '%)</em>' : '' ) . '</p></div></div>'; // phpcs:ignore

		// Categorias: 4 maiores + Outros.
		$cores = array( '#C9A24A', '#475467', '#98A2B3', '#D0D5DD', '#EAECF0' );
		$tot_c = array_sum( $d['cats'] );
		$fat   = array();
		$leg   = '';
		$ac    = 0.0;
		$i     = 0;
		$outros = 0.0;
		foreach ( $d['cats'] as $nm => $v ) {
			if ( $i < 4 ) {
				$p     = $tot_c > 0 ? $v / $tot_c * 100 : 0;
				$fat[] = $cores[ $i ] . ' ' . round( $ac, 2 ) . '% ' . round( $ac + $p, 2 ) . '%';
				$ac   += $p;
				$leg  .= '<div><span><i style="background:' . $cores[ $i ] . '"></i>' . esc_html( $nm ) . '</span><b>' . esc_html( number_format( $p, 0 ) ) . '%</b></div>';
			} else {
				$outros += $v;
			}
			$i++;
		}
		if ( $outros > 0 && $tot_c > 0 ) {
			$p     = $outros / $tot_c * 100;
			$fat[] = $cores[4] . ' ' . round( $ac, 2 ) . '% 100%';
			$leg  .= '<div><span><i style="background:' . $cores[4] . '"></i>Outros</span><b>' . esc_html( number_format( $p, 0 ) ) . '%</b></div>';
		}
		$grad = $fat ? 'conic-gradient(' . implode( ',', $fat ) . ')' : '#EAECF0';

		echo '<div class="dcmx-g"><div class="dcmx-c"><h3>Evolu&ccedil;&atilde;o das Compras da Rede</h3>' . dcmx_linha_svg( $d['meses'] ) . '</div>' // phpcs:ignore
			. '<div class="dcmx-c"><h3>Compras por Categoria</h3><div class="dcmx-don"><div class="d" style="background:' . esc_attr( $grad ) . '"><span><b>' . esc_html( dcmx_money( $tot_c ) ) . '</b>Total</span></div><div class="dcmx-leg">' . ( '' !== $leg ? $leg : '<p>Sem compras no per&iacute;odo.</p>' ) . '</div></div></div></div>'; // phpcs:ignore

		$lin = '';
		foreach ( $d['ultimas'] as $x ) {
			$lin .= '<tr><td><strong>' . esc_html( $x['franquia'] ) . '</strong></td><td>#' . (int) $x['pedido'] . '</td><td>' . esc_html( $x['data'] ) . '</td><td>' . esc_html( dcmx_money( $x['valor'] ) ) . '</td><td>' . dcmx_status( $x['status'] ) . '</td></tr>'; // phpcs:ignore
		}
		$topo = '';
		$mx   = 0;
		foreach ( $d['prods'] as $pp ) {
			$mx = max( $mx, (int) $pp['qtd'] );
		}
		foreach ( $d['prods'] as $pp ) {
			$w     = $mx > 0 ? round( $pp['qtd'] / $mx * 100 ) : 0;
			$topo .= '<div><span>' . esc_html( wp_trim_words( $pp['nome'], 5, '…' ) ) . '</span><span><div class="bar" style="width:' . (int) $w . '%"></div></span><b>' . esc_html( number_format( (int) $pp['qtd'], 0, ',', '.' ) ) . ' un</b></div>'; // phpcs:ignore
		}
		echo '<div class="dcmx-g" style="grid-template-columns:minmax(0,1.3fr) minmax(0,1fr)"><div class="dcmx-c"><h3>&Uacute;ltimas Compras da Rede</h3>' // phpcs:ignore
			. ( '' !== $lin ? '<table class="dcmx-t"><tr><th>Franquia</th><th>Pedido</th><th>Data</th><th>Valor</th><th>Status</th></tr>' . $lin . '</table>' : '<p>Nenhuma compra no per&iacute;odo.</p>' ) . '</div>' // phpcs:ignore
			. '<div class="dcmx-c"><h3>Top 5 Produtos da Rede</h3><div class="dcmx-top5">' . ( '' !== $topo ? $topo : '<p>Sem compras no per&iacute;odo.</p>' ) . '</div></div></div>'; // phpcs:ignore

		echo '<div class="dcmx-a"><a href="https://ouromoderno.giftbrand.com.br/" target="_blank" rel="noopener">' . dcmx_ico( 'cart' ) . 'Abrir a Loja</a>' . '<a href="' . esc_url( add_query_arg( 'tela', 'franquias', $base ) ) . '">' . dcmx_ico( 'store' ) . 'Gerenciar Franquias</a>' . ( $pode_wp ? '<a href="' . esc_url( admin_url( 'admin.php?page=dc-painel-comercial' ) ) . '">' . dcmx_ico( 'check' ) . 'Aprovar Or&ccedil;amentos</a><a href="' . esc_url( dcmx_orders_url() ) . '">' . dcmx_ico( 'truck' ) . 'Acompanhar Pedidos</a>' : '' ) . '<a href="' . esc_url( add_query_arg( 'tela', 'comissoes', $base ) ) . '">' . dcmx_ico( 'coins' ) . 'Calcular Comiss&otilde;es</a><a href="' . esc_url( add_query_arg( 'tela', 'materiais', $base ) ) . '">' . dcmx_ico( 'image' ) . 'Enviar Materiais e Artes</a><a href="' . esc_url( add_query_arg( 'tela', 'relatorios', $base ) ) . '">' . dcmx_ico( 'bars' ) . 'Relat&oacute;rios da Rede</a></div>'; // phpcs:ignore
	} catch ( \Throwable $e ) {
		echo '<div class="notice notice-error"><p>N&atilde;o foi poss&iacute;vel montar o painel: ' . esc_html( $e->getMessage() ) . '</p></div>'; // phpcs:ignore
	}
	echo '</div></div></div>'; // phpcs:ignore
}

require_once __DIR__ . '/comissoes.php';
require_once __DIR__ . '/matriz-telas.php';
