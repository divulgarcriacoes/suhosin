<?php
/**
 * Area do Franqueado > Minhas Comissoes: a franquia ve a comissao de cada pedido e o desconto da nota fiscal.
 * Comissao 20% sobre os produtos; pedido com nota gerada desconta 4,5% da comissao (includes/comissoes.php).
 * So a conta principal da franquia ve (a equipe nao).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Aba /my-account-2/escola-comissoes/. */
add_filter(
	'woocommerce_get_query_vars',
	function ( $v ) {
		$v['escola-comissoes'] = 'escola-comissoes';
		return $v;
	}
);
add_action(
	'init',
	function () {
		add_rewrite_endpoint( 'escola-comissoes', EP_ROOT | EP_PAGES );
		if ( get_option( 'dcom_com_rw' ) !== '1' ) {
			flush_rewrite_rules( false );
			update_option( 'dcom_com_rw', '1', false );
		}
	},
	20
);
add_filter(
	'woocommerce_endpoint_escola-comissoes_title',
	function () {
		return 'Minhas Comissões';
	}
);

/* Franquia da conta logada, so se for a conta principal (0 = nao ve comissoes). */
function dcom_com_dono() {
	if ( ! is_user_logged_in() ) {
		return 0;
	}
	$uid = get_current_user_id();
	$p   = function_exists( 'omx_parent' ) ? (int) omx_parent( $uid ) : $uid;
	return ( $p === $uid && '' !== (string) get_user_meta( $uid, 'omx_escola', true ) ) ? $uid : 0;
}

/* Equipe nao ve a aba. */
add_filter(
	'woocommerce_account_menu_items',
	function ( $items ) {
		if ( isset( $items['escola-comissoes'] ) && ! dcom_com_dono() ) {
			unset( $items['escola-comissoes'] );
		}
		return $items;
	},
	50
);

/* Totais da franquia num intervalo. */
function dcom_com_totais( $fid, $ini, $fim ) {
	$t = array( 'base' => 0.0, 'bruta' => 0.0, 'desc' => 0.0, 'liq' => 0.0, 'ped' => 0, 'nota' => 0, 'linhas' => array(), 'pend' => 0, 'prev' => 0.0 );
	foreach ( dcmx_com_pedidos( $ini, $fim, $fid ) as $l ) {
		$c = dcmx_com_pedido( $l[0] );
		if ( 'pending' === $l[0]->get_status() ) {
			$t['pend']++;
			$t['prev']    += $c['liq'];
			$t['linhas'][] = array( $l[0], $c );
			continue;
		}
		foreach ( array( 'base', 'bruta', 'desc', 'liq' ) as $k ) {
			$t[ $k ] += $c[ $k ];
		}
		$t['ped']++;
		$t['nota']    += $c['nota'] ? 1 : 0;
		$t['linhas'][] = array( $l[0], $c );
	}
	return $t;
}

/* Cartao "Comissao do mes" na tela inicial. */
function dcom_com_kpi( $fid ) {
	if ( (int) $fid !== dcom_com_dono() ) {
		return '<div class="dcomp-kpi"><i class="kt">' . dcomp_ico( 'image' ) . '</i><p><b>&mdash;</b><span>Artes produzidas</span><small>em breve</small></p></div>';
	}
	list( , $ini, $fim ) = dcmx_com_mes();
	$t = dcom_com_totais( $fid, $ini, $fim );
	return '<a class="dcomp-kpi" style="text-decoration:none" href="' . esc_url( wc_get_account_endpoint_url( 'escola-comissoes' ) ) . '"><i class="kt">' . dcomp_ico( 'coins' ) . '</i><p><b>' . esc_html( dcmx_money( $t['liq'] ) ) . '</b><span>Comiss&atilde;o do m&ecirc;s</span>'
		. ( $t['desc'] > 0 ? '<small style="color:#B42318">&minus; ' . esc_html( dcmx_money( $t['desc'] ) ) . ' da nota fiscal</small>' : '<small>' . esc_html( number_format( dcmx_pct(), 0 ) ) . '% sobre os produtos</small>' ) . '</p></a>';
}

/* Tela Minhas Comissoes. */
add_action(
	'woocommerce_account_escola-comissoes_endpoint',
	function () {
		$fid = dcom_com_dono();
		if ( ! $fid ) {
			echo '<p>As comiss&otilde;es aparecem s&oacute; para a conta principal da unidade.</p>';
			return;
		}
		list( $mes, $ini, $fim ) = dcmx_com_mes();
		$t   = dcom_com_totais( $fid, $ini, $fim );
		$pct = number_format( dcmx_pct(), 0 );
		$dn  = number_format( dcmx_desc_nota(), 1, ',', '' );

		$nomes = array( '', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro' );
		$om    = '';
		$agora = new DateTimeImmutable( wp_date( 'Y-m-01' ), wp_timezone() );
		for ( $i = 0; $i < 12; $i++ ) {
			$dt  = $agora->modify( '-' . $i . ' months' );
			$k   = $dt->format( 'Y-m' );
			$om .= '<option value="' . esc_attr( $k ) . '"' . selected( $mes, $k, false ) . '>' . esc_html( $nomes[ (int) $dt->format( 'n' ) ] . ' ' . $dt->format( 'Y' ) ) . '</option>';
		}

		echo '<style>.dccf-k{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:14px 0 18px}.dccf-k div{background:#fff;border:1px solid #E7E9F0;border-radius:16px;padding:16px 18px}.dccf-k span{display:block;font-size:13.5px;color:#667085}.dccf-k b{display:block;font-size:26px;color:#101828;margin-top:2px}.dccf-k .ds b{color:#B42318}.dccf-k .lq{background:#FBF5E4;border-color:#E9D49A}'
			. '.dccf-t{width:100%;border-collapse:collapse;font-size:14px;background:#fff;border:1px solid #E7E9F0;border-radius:14px;overflow:hidden}.dccf-t th{text-align:left;background:#F4F5F8;color:#475467;padding:9px 10px;font-weight:700}.dccf-t td{padding:10px;border-top:1px solid #F0F1F5}.dccf-t .ds{color:#B42318;font-weight:700}.dccf-t tfoot td{font-weight:800;background:#FAFAFB}'
			. '.dccf-nf{display:inline-block;border-radius:999px;padding:3px 10px;font-size:12px;font-weight:700}.dccf-nf.s{background:#FEE4E2;color:#B42318}.dccf-nf.n{background:#F2F4F7;color:#475467}.dccf-i{background:#F4F5F8;border-radius:12px;padding:12px 14px;color:#475467;font-size:13.5px;margin:0 0 14px}'
			. '.dccf-f{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.dccf-f select{min-height:40px;border:1px solid #D0D5DD;border-radius:10px;padding:0 12px}'
			. '@media(max-width:760px){.dccf-k{grid-template-columns:1fr}.dccf-w{overflow-x:auto}}</style>';
		echo '<h2 style="margin:0 0 6px">Minhas Comiss&otilde;es</h2>';
		echo '<form method="get" class="dccf-f"><label for="dccf-mes"><b>M&ecirc;s:</b></label><select id="dccf-mes" name="mes" onchange="this.form.submit()">' . $om . '</select><noscript><button type="submit" class="button">Ver</button></noscript></form>'; // phpcs:ignore
		echo '<div class="dccf-k"><div><span>Comiss&atilde;o (' . esc_html( $pct ) . '% dos produtos)</span><b>' . esc_html( dcmx_money( $t['bruta'] ) ) . '</b></div>'
			. '<div class="ds"><span>Descontado da nota fiscal (' . esc_html( $dn ) . '%)</span><b>' . ( $t['desc'] > 0 ? '&minus; ' : '' ) . esc_html( dcmx_money( $t['desc'] ) ) . '</b></div>'
			. '<div class="lq"><span>Comiss&atilde;o a receber</span><b>' . esc_html( dcmx_money( $t['liq'] ) ) . '</b>' . ( $t['pend'] > 0 ? '<span style="color:#B54708;margin-top:4px">+ ' . esc_html( dcmx_money( $t['prev'] ) ) . ' prevista (' . (int) $t['pend'] . ' pedido(s) aguardando pagamento)</span>' : '' ) . '</div></div>';
		echo '<p class="dccf-i">Sua comiss&atilde;o &eacute; de <b>' . esc_html( $pct ) . '%</b> sobre o valor dos produtos de cada pedido da sua loja (o frete n&atilde;o entra). Quando a <b>nota fiscal</b> do pedido &eacute; gerada, s&atilde;o descontados <b>' . esc_html( $dn ) . '%</b> do valor da comiss&atilde;o desse pedido.</p>';

		$tr = '';
		foreach ( $t['linhas'] as $l ) {
			list( $o, $c ) = $l;
			$dc  = $o->get_date_created();
			$tr .= '<tr><td>#' . esc_html( $o->get_order_number() ) . '</td><td>' . esc_html( $dc ? $dc->date_i18n( 'd/m/Y' ) : '' ) . '</td><td>' . esc_html( dcmx_money( $c['base'] ) ) . '</td><td>' . esc_html( dcmx_money( $c['bruta'] ) ) . '</td>'
				. '<td>' . ( $c['nota'] ? '<span class="dccf-nf s">Nota gerada</span>' : '<span class="dccf-nf n">Sem nota</span>' ) . '</td>'
				. '<td class="ds">' . ( $c['desc'] > 0 ? '&minus; ' . esc_html( dcmx_money( $c['desc'] ) ) : '&ndash;' ) . '</td><td>' . ( 'pending' === $o->get_status() ? '<span style="color:#B54708">' . esc_html( dcmx_money( $c['liq'] ) ) . '<br><small>aguardando pagamento</small></span>' : '<b>' . esc_html( dcmx_money( $c['liq'] ) ) . '</b>' ) . '</td></tr>';
		}
		echo '<div class="dccf-w"><table class="dccf-t"><thead><tr><th>Pedido</th><th>Data</th><th>Produtos</th><th>Comiss&atilde;o ' . esc_html( $pct ) . '%</th><th>Nota fiscal</th><th>Desconto ' . esc_html( $dn ) . '%</th><th>A receber</th></tr></thead><tbody>' // phpcs:ignore
			. ( '' !== $tr ? $tr : '<tr><td colspan="7">Nenhum pedido neste m&ecirc;s.</td></tr>' ) . '</tbody>' // phpcs:ignore
			. ( '' !== $tr ? '<tfoot><tr><td colspan="2">Total pago (' . (int) $t['ped'] . ')</td><td>' . esc_html( dcmx_money( $t['base'] ) ) . '</td><td>' . esc_html( dcmx_money( $t['bruta'] ) ) . '</td><td>' . (int) $t['nota'] . ' com nota</td><td class="ds">' . ( $t['desc'] > 0 ? '&minus; ' : '' ) . esc_html( dcmx_money( $t['desc'] ) ) . '</td><td>' . esc_html( dcmx_money( $t['liq'] ) ) . '</td></tr></tfoot>' : '' ) . '</table></div>'; // phpcs:ignore
	}
);
