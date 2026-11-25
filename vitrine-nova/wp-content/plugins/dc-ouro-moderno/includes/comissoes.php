<?php
/**
 * Painel da Matriz > Comissoes.
 * Comissao fixa (DCMX_COMISSAO_FIXA, 20%) sobre o valor dos produtos de cada pedido da rede (sem o frete).
 * Pedido com "Nota gerada" (meta _dcom_nota = 1): desconta DCMX_DESC_NOTA (4,5%) do valor da comissao desse pedido.
 * Pedidos da rede = feitos pela loja de uma franquia (_dcom_franquia) ou por uma conta da franquia/equipe dela.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DCMX_DESC_NOTA' ) ) {
	define( 'DCMX_DESC_NOTA', 4.5 );
}

/* Tambem carregado pela Area do Franqueado, sem o Painel da Matriz. */
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

function dcmx_desc_nota() {
	return (float) DCMX_DESC_NOTA;
}

/* Conta da franquia ou da equipe => id da franquia aprovada. */
function dcmx_mapa_rede() {
	global $wpdb;
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$map   = array();
	$aprov = array_map( 'intval', (array) $wpdb->get_col( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'omx_status' AND meta_value = 'aprovada'" ) ); // phpcs:ignore
	$set   = array_flip( $aprov );
	foreach ( $aprov as $id ) {
		$map[ $id ] = $id;
	}
	foreach ( $wpdb->get_results( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'omx_parent' AND meta_value + 0 > 0" ) as $r ) { // phpcs:ignore
		if ( isset( $set[ (int) $r->meta_value ] ) ) {
			$map[ (int) $r->user_id ] = (int) $r->meta_value;
		}
	}
	return $map;
}

/* Franquia de um pedido (0 = nao e da rede). */
function dcmx_com_franquia( $o ) {
	$fr = (int) $o->get_meta( '_dcom_franquia' );
	if ( $fr ) {
		return $fr;
	}
	$map  = dcmx_mapa_rede();
	$cust = (int) $o->get_customer_id();
	return isset( $map[ $cust ] ) ? (int) $map[ $cust ] : 0;
}

/* Valores da comissao de um pedido. */
function dcmx_com_pedido( $o ) {
	$base = 0.0;
	foreach ( $o->get_items() as $it ) {
		$base += (float) $it->get_total(); // produtos, ja com desconto de cupom, sem frete
	}
	$base -= (float) $o->get_total_refunded() - (float) $o->get_total_shipping_refunded();
	$base  = max( 0.0, $base );
	$bruta = round( $base * dcmx_pct() / 100, 2 );
	$nota  = '1' === (string) $o->get_meta( '_dcom_nota' );
	$desc  = $nota ? round( $bruta * dcmx_desc_nota() / 100, 2 ) : 0.0;
	return array(
		'base'  => $base,
		'bruta' => $bruta,
		'nota'  => $nota,
		'desc'  => $desc,
		'liq'   => $bruta - $desc,
	);
}

/* Boleto/Pix sem pagamento ha mais de DCOM_PEND_DIAS dias: sai do painel (boleto vencido). */
if ( ! defined( 'DCOM_PEND_DIAS' ) ) {
	define( 'DCOM_PEND_DIAS', 5 );
}
function dcmx_pend_vencido( $o ) {
	if ( 'pending' !== $o->get_status() ) {
		return false;
	}
	$dc = $o->get_date_created();
	return $dc && $dc->getTimestamp() < time() - DCOM_PEND_DIAS * DAY_IN_SECONDS;
}

/* Pedidos da rede entre $ini e $fim (timestamps), de uma franquia ou de todas. Cada item: array( pedido, franquia ). */
function dcmx_com_pedidos( $ini, $fim, $fid = 0 ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}
	$st   = array( 'pending', 'processing', 'on-hold', 'completed' ); // pending = boleto/Pix ainda nao pago (comissao prevista)
	$data = (int) $ini . '...' . (int) $fim;
	$map  = dcmx_mapa_rede();
	if ( $fid > 0 ) {
		$map = array_filter(
			$map,
			function ( $f ) use ( $fid ) {
				return (int) $f === (int) $fid;
			}
		);
	}
	$lista = array();
	$todos = array();
	if ( $map ) {
		$todos = (array) wc_get_orders( array( 'customer' => array_keys( $map ), 'limit' => -1, 'status' => $st, 'date_created' => $data ) );
	}
	if ( function_exists( 'dcom_pedidos_marcados' ) ) {
		$todos = array_merge( $todos, dcom_pedidos_marcados( $fid, (int) $ini, $st, (int) $fim ) );
	}
	foreach ( $todos as $o ) {
		if ( isset( $lista[ $o->get_id() ] ) || dcmx_pend_vencido( $o ) ) {
			continue;
		}
		$fr = dcmx_com_franquia( $o );
		if ( ! $fr || ( $fid > 0 && $fr !== (int) $fid ) ) {
			continue;
		}
		$lista[ $o->get_id() ] = array( $o, $fr );
	}
	uasort(
		$lista,
		function ( $a, $b ) {
			$x = $a[0]->get_date_created();
			$y = $b[0]->get_date_created();
			return ( $y ? $y->getTimestamp() : 0 ) <=> ( $x ? $x->getTimestamp() : 0 );
		}
	);
	return array_values( $lista );
}

/* Mes escolhido (AAAA-MM) e seu intervalo. */
function dcmx_com_mes() {
	$m = isset( $_REQUEST['mes'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['mes'] ) ) : ''; // phpcs:ignore
	if ( ! preg_match( '/^\d{4}-\d{2}$/', $m ) ) {
		$m = wp_date( 'Y-m' );
	}
	$tz  = wp_timezone();
	$ini = new DateTimeImmutable( $m . '-01 00:00:00', $tz );
	$fim = $ini->modify( 'first day of next month' );
	return array( $m, $ini->getTimestamp(), $fim->getTimestamp() - 1 );
}

function dcmx_com_nome( $fid ) {
	$n = (string) get_user_meta( $fid, 'omx_escola', true );
	return '' !== $n ? $n : 'Franquia #' . (int) $fid;
}

/* Salva "Nota gerada" dos pedidos da pagina aberta. */
function dcmx_com_salvar() {
	if ( empty( $_POST['dcmx_com_n'] ) || ! current_user_can( 'dcmx_config' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcmx_com_n'] ) ), 'dcmx_com' ) ) {
		return '';
	}
	$ids  = isset( $_POST['ped'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ped'] ) ) : array();
	$nota = isset( $_POST['nota'] ) ? array_flip( array_map( 'absint', (array) wp_unslash( $_POST['nota'] ) ) ) : array();
	$n    = 0;
	foreach ( $ids as $id ) {
		$o = $id ? wc_get_order( $id ) : false;
		if ( ! $o || ! dcmx_com_franquia( $o ) ) {
			continue;
		}
		$novo = isset( $nota[ $id ] ) ? '1' : '';
		if ( (string) $o->get_meta( '_dcom_nota' ) === $novo ) {
			continue;
		}
		if ( '1' === $novo ) {
			$o->update_meta_data( '_dcom_nota', '1' );
			$o->add_order_note( 'Comissão: nota marcada como gerada no Painel da Matriz (desconto de ' . number_format( dcmx_desc_nota(), 1, ',', '' ) . '% na comissão).' );
		} else {
			$o->delete_meta_data( '_dcom_nota' );
			$o->add_order_note( 'Comissão: "Nota gerada" desmarcada no Painel da Matriz.' );
		}
		$o->save();
		$n++;
	}
	return '<div class="notice notice-success"><p>' . ( $n ? esc_html( $n . ( 1 === $n ? ' pedido atualizado.' : ' pedidos atualizados.' ) ) : 'Nada mudou.' ) . '</p></div>';
}

/* Planilha CSV do mes (abre antes de qualquer saida da pagina). */
function dcmx_com_csv() {
	if ( empty( $_GET['dcmx_csv'] ) || ! current_user_can( 'dcmx_ver' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dcmx_csv' ) ) {
		return;
	}
	list( $mes, $ini, $fim ) = dcmx_com_mes();
	$fid = isset( $_GET['franquia'] ) ? absint( $_GET['franquia'] ) : 0;
	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="comissoes-ouro-moderno-' . $mes . '.csv"' );
	$f = fopen( 'php://output', 'w' );
	fwrite( $f, "\xEF\xBB\xBF" );
	$br = function ( $v ) {
		return number_format( (float) $v, 2, ',', '' );
	};
	fputcsv( $f, array( 'Pedido', 'Data', 'Franquia', 'CNPJ', 'Valor dos produtos', 'Comissao ' . number_format( dcmx_pct(), 0 ) . '%', 'Nota gerada', 'Desconto nota', 'Comissao liquida', 'Situacao' ), ';' );
	foreach ( dcmx_com_pedidos( $ini, $fim, $fid ) as $l ) {
		$o  = $l[0];
		$c  = dcmx_com_pedido( $o );
		$dc = $o->get_date_created();
		fputcsv( $f, array( $o->get_order_number(), $dc ? $dc->date_i18n( 'd/m/Y' ) : '', dcmx_com_nome( $l[1] ), (string) get_user_meta( $l[1], 'omx_cnpj', true ), $br( $c['base'] ), $br( $c['bruta'] ), $c['nota'] ? 'Sim' : 'Nao', $br( $c['desc'] ), $br( $c['liq'] ), 'pending' === $o->get_status() ? 'Aguardando pagamento (prevista)' : 'Pago' ), ';' );
	}
	fclose( $f );
	exit;
}
add_action( 'admin_init', 'dcmx_com_csv' );
add_action( 'template_redirect', 'dcmx_com_csv', -1 );

/* Tela Comissoes. */
function dcmx_tela_comissoes( $base ) {
	$msg = dcmx_com_salvar();
	list( $mes, $ini, $fim ) = dcmx_com_mes();
	$fid  = isset( $_GET['franquia'] ) ? absint( $_GET['franquia'] ) : 0; // phpcs:ignore
	$nf   = isset( $_GET['nf'] ) ? sanitize_key( wp_unslash( $_GET['nf'] ) ) : ''; // phpcs:ignore
	$pode = current_user_can( 'dcmx_config' );
	$pct  = dcmx_pct();
	$dn   = dcmx_desc_nota();

	$lista = dcmx_com_pedidos( $ini, $fim, $fid );
	$tot   = array( 'base' => 0.0, 'bruta' => 0.0, 'desc' => 0.0, 'liq' => 0.0, 'ped' => 0, 'com_nota' => 0, 'pend' => 0, 'prev' => 0.0 );
	$res   = array();
	$linhas = array();
	foreach ( $lista as $l ) {
		$c  = dcmx_com_pedido( $l[0] );
		$fr = $l[1];
		if ( 'pending' === $l[0]->get_status() ) {
			// Aguardando pagamento: aparece na lista, comissao so prevista (fora dos totais).
			$tot['pend']++;
			$tot['prev'] += $c['liq'];
			if ( 'com' !== $nf ) {
				$linhas[] = array( $l[0], $fr, $c );
			}
			continue;
		}
		foreach ( array( 'base', 'bruta', 'desc', 'liq' ) as $k ) {
			$tot[ $k ] += $c[ $k ];
		}
		$tot['ped']++;
		$tot['com_nota'] += $c['nota'] ? 1 : 0;
		if ( ! isset( $res[ $fr ] ) ) {
			$res[ $fr ] = array( 'ped' => 0, 'base' => 0.0, 'bruta' => 0.0, 'desc' => 0.0, 'liq' => 0.0, 'sem' => 0 );
		}
		$res[ $fr ]['ped']++;
		$res[ $fr ]['sem'] += $c['nota'] ? 0 : 1;
		foreach ( array( 'base', 'bruta', 'desc', 'liq' ) as $k ) {
			$res[ $fr ][ $k ] += $c[ $k ];
		}
		if ( ( 'com' === $nf && ! $c['nota'] ) || ( 'sem' === $nf && $c['nota'] ) ) {
			continue;
		}
		$linhas[] = array( $l[0], $fr, $c );
	}
	uasort(
		$res,
		function ( $a, $b ) {
			return $b['liq'] <=> $a['liq'];
		}
	);

	// Filtros: mes (ultimos 12), franquia, nota.
	$nomes_m = array( '', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro' );
	$om      = '';
	$agora   = new DateTimeImmutable( wp_date( 'Y-m-01' ), wp_timezone() );
	for ( $i = 0; $i < 12; $i++ ) {
		$dt  = $agora->modify( '-' . $i . ' months' );
		$k   = $dt->format( 'Y-m' );
		$om .= '<option value="' . esc_attr( $k ) . '"' . selected( $mes, $k, false ) . '>' . esc_html( $nomes_m[ (int) $dt->format( 'n' ) ] . ' ' . $dt->format( 'Y' ) ) . '</option>';
	}
	global $wpdb;
	$opts = array();
	foreach ( $wpdb->get_col( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'omx_status' AND meta_value = 'aprovada'" ) as $id ) { // phpcs:ignore
		$opts[ (int) $id ] = dcmx_com_nome( (int) $id );
	}
	asort( $opts );
	$of = '<option value="0">Todas as Franquias</option>';
	foreach ( $opts as $id => $nm ) {
		$of .= '<option value="' . (int) $id . '"' . selected( $fid, $id, false ) . '>' . esc_html( $nm ) . '</option>';
	}
	$on = '';
	foreach ( array( '' => 'Todas as notas', 'sem' => 'Sem nota', 'com' => 'Com nota gerada' ) as $k => $lb ) {
		$on .= '<option value="' . esc_attr( $k ) . '"' . selected( $nf, $k, false ) . '>' . esc_html( $lb ) . '</option>';
	}
	$hid = is_admin() ? '<input type="hidden" name="page" value="dcmx">' : '';
	$csv = wp_nonce_url( add_query_arg( array( 'tela' => 'comissoes', 'mes' => $mes, 'franquia' => $fid, 'dcmx_csv' => 1 ), $base ), 'dcmx_csv' );

	echo $msg; // phpcs:ignore
	echo '<div class="dcmx-hd"><div><h2>Comiss&otilde;es</h2><p>Comiss&atilde;o fixa de <b>' . esc_html( number_format( $pct, 0 ) ) . '%</b> sobre o valor dos produtos (sem frete). Pedido com nota gerada desconta <b>' . esc_html( number_format( $dn, 1, ',', '' ) ) . '%</b> da comiss&atilde;o.</p></div>'
		. '<form method="get" action="' . esc_url( is_admin() ? admin_url( 'admin.php' ) : $base ) . '">' . $hid . '<input type="hidden" name="tela" value="comissoes"><label class="pl">' . dcmx_ico( 'cal' ) . 'M&ecirc;s:</label><select name="mes">' . $om . '</select><select name="franquia">' . $of . '</select><select name="nf">' . $on . '</select><button type="submit">Aplicar</button></form></div>'; // phpcs:ignore

	echo '<div class="dcmx-k">'
		. '<div><i class="kt">' . dcmx_ico( 'cart' ) . '</i><p><span>Vendas da rede (produtos)</span><b>' . esc_html( dcmx_money( $tot['base'] ) ) . '</b><em style="color:#667085">' . (int) $tot['ped'] . ' pedido(s)</em></p></div>'
		. '<div><i class="kt">' . dcmx_ico( 'coins' ) . '</i><p><span>Comiss&atilde;o bruta (' . esc_html( number_format( $pct, 0 ) ) . '%)</span><b>' . esc_html( dcmx_money( $tot['bruta'] ) ) . '</b></p></div>'
		. '<div><i class="kt">' . dcmx_ico( 'doc' ) . '</i><p><span>Desconto das notas (' . esc_html( number_format( $dn, 1, ',', '' ) ) . '%)</span><b>' . esc_html( dcmx_money( $tot['desc'] ) ) . '</b><em style="color:#667085">' . (int) $tot['com_nota'] . ' de ' . (int) $tot['ped'] . ' com nota</em></p></div>'
		. '<div><i class="kt">' . dcmx_ico( 'check' ) . '</i><p><span>Comiss&atilde;o l&iacute;quida</span><b>' . esc_html( dcmx_money( $tot['liq'] ) ) . '</b>' . ( $tot['pend'] > 0 ? '<em style="display:block;color:#B54708">+ ' . esc_html( dcmx_money( $tot['prev'] ) ) . ' prevista (' . (int) $tot['pend'] . ' aguardando pagamento)</em>' : '' ) . '</p></div></div>'; // phpcs:ignore

	// Resumo por franquia.
	$rs = '';
	foreach ( $res as $fr => $r ) {
		$u   = add_query_arg( array( 'tela' => 'comissoes', 'mes' => $mes, 'franquia' => $fr ), $base );
		$rs .= '<tr><td><a href="' . esc_url( $u ) . '"><strong>' . esc_html( dcmx_com_nome( $fr ) ) . '</strong></a></td><td>' . (int) $r['ped'] . '</td><td>' . esc_html( dcmx_money( $r['base'] ) ) . '</td><td>' . esc_html( dcmx_money( $r['bruta'] ) ) . '</td><td>' . ( $r['desc'] > 0 ? '&minus; ' . esc_html( dcmx_money( $r['desc'] ) ) : '&ndash;' ) . '</td><td><strong>' . esc_html( dcmx_money( $r['liq'] ) ) . '</strong></td><td>' . ( $r['sem'] > 0 ? '<span class="st pr">' . (int) $r['sem'] . ' sem nota</span>' : '<span class="st en">Todas com nota</span>' ) . '</td></tr>'; // phpcs:ignore
	}
	if ( 0 === $fid ) {
		echo '<div class="dcmx-c" style="margin-bottom:16px"><h3>Por franquia</h3><div style="overflow-x:auto"><table class="dcmx-t"><thead><tr><th>Franquia</th><th>Pedidos</th><th>Produtos</th><th>Comiss&atilde;o ' . esc_html( number_format( $pct, 0 ) ) . '%</th><th>Desconto nota</th><th>L&iacute;quida</th><th>Notas</th></tr></thead><tbody>' . ( '' !== $rs ? $rs : '<tr><td colspan="7">Nenhum pedido da rede neste m&ecirc;s.</td></tr>' ) . '</tbody></table></div></div>'; // phpcs:ignore
	}

	// Pedidos (50 por pagina) com o campo "Nota gerada".
	$pp    = 50;
	$pags  = max( 1, (int) ceil( count( $linhas ) / $pp ) );
	$pg    = isset( $_GET['cp'] ) ? min( $pags, max( 1, absint( $_GET['cp'] ) ) ) : 1; // phpcs:ignore
	$pagl  = array_slice( $linhas, ( $pg - 1 ) * $pp, $pp );
	$tr    = '';
	foreach ( $pagl as $l ) {
		list( $o, $fr, $c ) = $l;
		$dc   = $o->get_date_created();
		$link = current_user_can( 'edit_shop_orders' ) ? $o->get_edit_order_url() : '';
		$num  = '#' . esc_html( $o->get_order_number() );
		$tr  .= '<tr><td>' . ( $link ? '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . $num . '</a>' : $num ) . '<input type="hidden" name="ped[]" value="' . (int) $o->get_id() . '"></td><td>' . esc_html( $dc ? $dc->date_i18n( 'd/m/Y' ) : '' ) . '</td><td>' . esc_html( dcmx_com_nome( $fr ) ) . '</td><td>' . dcmx_status( $o->get_status() ) . '</td><td>' . esc_html( dcmx_money( $c['base'] ) ) . '</td><td>' . esc_html( dcmx_money( $c['bruta'] ) ) . '</td>'
			. '<td><label class="nf"><input type="checkbox" name="nota[]" value="' . (int) $o->get_id() . '"' . checked( $c['nota'], true, false ) . disabled( ! $pode, true, false ) . '> Nota gerada</label></td><td>' . ( $c['desc'] > 0 ? '&minus; ' . esc_html( dcmx_money( $c['desc'] ) ) : '&ndash;' ) . '</td><td>' . ( 'pending' === $o->get_status() ? '<span style="color:#B54708">' . esc_html( dcmx_money( $c['liq'] ) ) . ' <small>prevista</small></span>' : '<strong>' . esc_html( dcmx_money( $c['liq'] ) ) . '</strong>' ) . '</td></tr>'; // phpcs:ignore
	}
	$pgs = '';
	if ( $pags > 1 ) {
		for ( $i = 1; $i <= $pags; $i++ ) {
			$u    = add_query_arg( array( 'tela' => 'comissoes', 'mes' => $mes, 'franquia' => $fid, 'nf' => $nf, 'cp' => $i ), $base );
			$pgs .= '<a class="' . ( $i === $pg ? 'on' : '' ) . '" href="' . esc_url( $u ) . '">' . (int) $i . '</a>';
		}
		$pgs = '<div class="dcmx-pg">' . $pgs . '</div>';
	}
	echo '<style>.dcmx-com .nf{display:inline-flex;align-items:center;gap:6px;font-weight:600;white-space:nowrap;cursor:pointer}.dcmx-com .nf input{width:18px;height:18px;accent-color:#C9A24A}.dcmx-com .rod{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:14px}.dcmx-com .bt{display:inline-flex;align-items:center;gap:8px;min-height:40px;border:0;border-radius:10px;padding:0 18px;font-weight:800;cursor:pointer;text-decoration:none}.dcmx-com .bt.p{background:#C9A24A;color:#0B1F3F}.dcmx-com .bt.s{background:#fff;border:1px solid #D0D5DD;color:#101828}.dcmx-com .bt .ic{width:18px;height:18px}.dcmx-pg{display:flex;gap:6px;flex-wrap:wrap}.dcmx-pg a{min-width:34px;height:34px;border-radius:8px;border:1px solid #D0D5DD;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;color:#101828;background:#fff}.dcmx-pg a.on{background:#0B1F3F;color:#fff;border-color:#0B1F3F}.st.rp{background:#FEE4E2;color:#B42318}</style>'; // phpcs:ignore
	echo '<form method="post" class="dcmx-c dcmx-com"><h3>Pedidos' . ( $fid ? ' &middot; ' . esc_html( dcmx_com_nome( $fid ) ) : '' ) . '</h3>' . wp_nonce_field( 'dcmx_com', 'dcmx_com_n', true, false ) // phpcs:ignore
		. '<div style="overflow-x:auto"><table class="dcmx-t"><thead><tr><th>Pedido</th><th>Data</th><th>Franquia</th><th>Situa&ccedil;&atilde;o</th><th>Produtos</th><th>Comiss&atilde;o ' . esc_html( number_format( $pct, 0 ) ) . '%</th><th>Nota</th><th>Desconto ' . esc_html( number_format( $dn, 1, ',', '' ) ) . '%</th><th>L&iacute;quida</th></tr></thead><tbody>' . ( '' !== $tr ? $tr : '<tr><td colspan="9">Nenhum pedido.</td></tr>' ) . '</tbody></table></div>' // phpcs:ignore
		. '<div class="rod">' . $pgs . '<div style="display:flex;gap:10px;margin-left:auto"><a class="bt s" href="' . esc_url( $csv ) . '">' . dcmx_ico( 'doc' ) . 'Baixar planilha</a>' . ( $pode && '' !== $tr ? '<button type="submit" class="bt p">' . dcmx_ico( 'check' ) . 'Salvar notas</button>' : '' ) . '</div></div></form>'; // phpcs:ignore
}

/* Dentro do pedido (WooCommerce): campo "Nota gerada" e a comissao. */
add_action(
	'woocommerce_admin_order_data_after_order_details',
	function ( $order ) {
		if ( ! function_exists( 'dcmx_pct' ) || ! dcmx_com_franquia( $order ) ) {
			return;
		}
		$c = dcmx_com_pedido( $order );
		echo '<div class="dcom-nota-box" style="clear:both;margin:12px 0 0;padding-top:4px"><label for="dcom_nota" style="display:inline-flex;align-items:center;gap:8px;width:auto;float:none;margin:0;cursor:pointer"><input type="checkbox" id="dcom_nota" name="dcom_nota" value="1" style="width:16px!important;height:16px;min-width:0;margin:0;flex:0 0 auto"' . checked( $c['nota'], true, false ) . '> <strong>Nota gerada</strong> (desconta ' . esc_html( number_format( dcmx_desc_nota(), 1, ',', '' ) ) . '% da comiss&atilde;o)</label>'
			. '<span style="display:block;color:#555;margin-top:6px">Comiss&atilde;o ' . esc_html( number_format( dcmx_pct(), 0 ) ) . '%: ' . esc_html( dcmx_money( $c['bruta'] ) ) . ( $c['desc'] > 0 ? ' &minus; ' . esc_html( dcmx_money( $c['desc'] ) ) : '' ) . ' = <strong>' . esc_html( dcmx_money( $c['liq'] ) ) . '</strong></span>' . wp_nonce_field( 'dcom_nota', 'dcom_nota_n', true, false ) . '</div>'; // phpcs:ignore
	},
	30
);
add_action(
	'woocommerce_process_shop_order_meta',
	function ( $order_id ) {
		if ( empty( $_POST['dcom_nota_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcom_nota_n'] ) ), 'dcom_nota' ) ) {
			return;
		}
		$o = wc_get_order( $order_id );
		if ( ! $o ) {
			return;
		}
		if ( ! empty( $_POST['dcom_nota'] ) ) {
			$o->update_meta_data( '_dcom_nota', '1' );
		} else {
			$o->delete_meta_data( '_dcom_nota' );
		}
		$o->save();
	},
	20
);
