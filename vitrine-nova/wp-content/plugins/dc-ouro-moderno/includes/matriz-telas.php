<?php
/**
 * Painel da Matriz: Produtos Mais Comprados, Relatorios, Campanhas da Rede e Materiais e Artes.
 * Campanhas e materiais ficam nas opcoes dcom_campanhas / dcom_materiais e aparecem para as escolas
 * na Area do Franqueado (aba Marketing e Campanhas e banner da tela inicial).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ dados */

/* Pedidos PAGOS da rede no periodo: array( pedido, franquia ). */
function dcmx_rede_pagos( $ini, $fim, $fid = 0 ) {
	if ( ! function_exists( 'dcmx_com_pedidos' ) ) {
		return array();
	}
	$out = array();
	foreach ( dcmx_com_pedidos( $ini, $fim, $fid ) as $l ) {
		if ( 'pending' !== $l[0]->get_status() ) {
			$out[] = $l;
		}
	}
	return $out;
}

function dcmx_periodos() {
	return array( 30 => 'Últimos 30 dias', 90 => 'Últimos 90 dias', 180 => 'Últimos 6 meses', 365 => 'Últimos 12 meses' );
}

function dcmx_filtros_html( $base, $tela, $dias, $fid, $extra = '' ) {
	$per = '';
	foreach ( dcmx_periodos() as $k => $lb ) {
		$per .= '<option value="' . (int) $k . '"' . selected( $dias, $k, false ) . '>' . esc_html( $lb ) . '</option>';
	}
	global $wpdb;
	$opts = array();
	foreach ( $wpdb->get_col( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'omx_status' AND meta_value = 'aprovada'" ) as $id ) { // phpcs:ignore
		$opts[ (int) $id ] = (string) get_user_meta( (int) $id, 'omx_escola', true );
	}
	asort( $opts );
	$of = '<option value="0">Todas as Franquias</option>';
	foreach ( $opts as $id => $nm ) {
		$of .= '<option value="' . (int) $id . '"' . selected( $fid, $id, false ) . '>' . esc_html( $nm ) . '</option>';
	}
	return '<form method="get" action="' . esc_url( is_admin() ? admin_url( 'admin.php' ) : $base ) . '">' . ( is_admin() ? '<input type="hidden" name="page" value="dcmx">' : '' ) . '<input type="hidden" name="tela" value="' . esc_attr( $tela ) . '"><label class="pl">' . dcmx_ico( 'cal' ) . 'Per&iacute;odo:</label><select name="dias">' . $per . '</select><select name="franquia">' . $of . '</select>' . $extra . '<button type="submit">Aplicar</button></form>'; // phpcs:ignore
}

function dcmx_get_dias() {
	$d = isset( $_GET['dias'] ) ? absint( $_GET['dias'] ) : 90; // phpcs:ignore
	return array_key_exists( $d, dcmx_periodos() ) ? $d : 90;
}

/* --------------------------------------------------- Produtos mais comprados */

function dcmx_tela_produtos( $base ) {
	$dias = dcmx_get_dias();
	$fid  = isset( $_GET['franquia'] ) ? absint( $_GET['franquia'] ) : 0; // phpcs:ignore
	$prod = array();
	$tq   = 0;
	$tv   = 0.0;
	foreach ( dcmx_rede_pagos( time() - $dias * DAY_IN_SECONDS, time(), $fid ) as $l ) {
		list( $o, $fr ) = $l;
		foreach ( $o->get_items() as $it ) {
			$pid = (int) $it->get_product_id();
			if ( ! isset( $prod[ $pid ] ) ) {
				$prod[ $pid ] = array( 'nome' => wp_strip_all_tags( (string) $it->get_name() ), 'qtd' => 0, 'val' => 0.0, 'ped' => array(), 'fr' => array() );
			}
			$q                        = (int) $it->get_quantity();
			$v                        = (float) $it->get_total();
			$prod[ $pid ]['qtd']     += $q;
			$prod[ $pid ]['val']     += $v;
			$prod[ $pid ]['ped'][ $o->get_id() ] = 1;
			$prod[ $pid ]['fr'][ $fr ]           = 1;
			$tq += $q;
			$tv += $v;
		}
	}
	$ord = isset( $_GET['ord'] ) && 'valor' === $_GET['ord'] ? 'val' : 'qtd'; // phpcs:ignore
	uasort(
		$prod,
		function ( $a, $b ) use ( $ord ) {
			return $b[ $ord ] <=> $a[ $ord ];
		}
	);
	$prod = array_slice( $prod, 0, 100, true );
	$mx   = 0;
	foreach ( $prod as $p ) {
		$mx = max( $mx, $p[ $ord ] );
	}
	$tr = '';
	$n  = 0;
	foreach ( $prod as $pid => $p ) {
		$n++;
		$img = get_the_post_thumbnail_url( $pid, 'thumbnail' );
		$sku = get_post_meta( $pid, '_sku', true );
		$w   = $mx > 0 ? round( $p[ $ord ] / $mx * 100 ) : 0;
		$tr .= '<tr><td style="color:#667085">' . (int) $n . '</td><td><div style="display:flex;align-items:center;gap:10px">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="" style="width:44px;height:44px;object-fit:contain;border-radius:8px;background:#fff;border:1px solid #EEE">' : '' ) . '<div><strong>' . esc_html( $p['nome'] ) . '</strong>' . ( $sku ? '<br><small style="color:#667085">SKU ' . esc_html( $sku ) . '</small>' : '' ) . '</div></div></td>'
			. '<td style="min-width:140px"><div style="height:10px;border-radius:5px;background:linear-gradient(90deg,#B98F34,#D9B45A);width:' . (int) $w . '%"></div></td>'
			. '<td><strong>' . esc_html( number_format( $p['qtd'], 0, ',', '.' ) ) . '</strong> un</td><td>' . esc_html( dcmx_money( $p['val'] ) ) . '</td><td>' . (int) count( $p['ped'] ) . '</td><td>' . (int) count( $p['fr'] ) . '</td></tr>';
	}
	$sel = '<select name="ord"><option value="qtd"' . selected( $ord, 'qtd', false ) . '>Ordenar por quantidade</option><option value="valor"' . selected( $ord, 'val', false ) . '>Ordenar por valor</option></select>';
	echo '<div class="dcmx-hd"><div><h2>Produtos Mais Comprados</h2><p>O que a rede mais compra (pedidos pagos), para planejar estoque e campanhas.</p></div>' . dcmx_filtros_html( $base, 'produtos', $dias, $fid, $sel ) . '</div>'; // phpcs:ignore
	echo '<div class="dcmx-k" style="grid-template-columns:repeat(3,minmax(0,1fr))"><div><i class="kt">' . dcmx_ico( 'bars' ) . '</i><p><span>Produtos diferentes</span><b>' . (int) count( $prod ) . '</b></p></div><div><i class="kt">' . dcmx_ico( 'cart' ) . '</i><p><span>Unidades compradas</span><b>' . esc_html( number_format( $tq, 0, ',', '.' ) ) . '</b></p></div><div><i class="kt">' . dcmx_ico( 'coins' ) . '</i><p><span>Valor em produtos</span><b>' . esc_html( dcmx_money( $tv ) ) . '</b></p></div></div>'; // phpcs:ignore
	echo '<div class="dcmx-c"><h3>Ranking</h3><div style="overflow-x:auto"><table class="dcmx-t"><thead><tr><th>#</th><th>Produto</th><th></th><th>Quantidade</th><th>Valor</th><th>Pedidos</th><th>Escolas</th></tr></thead><tbody>' . ( '' !== $tr ? $tr : '<tr><td colspan="7">Nenhuma compra paga no per&iacute;odo.</td></tr>' ) . '</tbody></table></div></div>'; // phpcs:ignore
}

/* ---------------------------------------------------------------- Relatorios */

function dcmx_tela_relatorios( $base ) {
	global $wpdb;
	$dias = dcmx_get_dias();
	$fid  = isset( $_GET['franquia'] ) ? absint( $_GET['franquia'] ) : 0; // phpcs:ignore
	$ini  = time() - $dias * DAY_IN_SECONDS;

	// Todas as franquias (aprovadas e em analise), mesmo sem compra.
	$esc = array();
	foreach ( $wpdb->get_col( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'omx_escola' AND meta_value <> ''" ) as $id ) { // phpcs:ignore
		$id = (int) $id;
		if ( (int) get_user_meta( $id, 'omx_parent', true ) > 0 || ( $fid && $fid !== $id ) ) {
			continue;
		}
		$esc[ $id ] = array( 'nome' => (string) get_user_meta( $id, 'omx_escola', true ), 'cid' => (string) get_user_meta( $id, 'omx_cidade', true ), 'st' => (string) get_user_meta( $id, 'omx_status', true ), 'ped' => 0, 'val' => 0.0, 'com' => 0.0, 'ult' => 0, 'pend' => 0 );
	}
	// Ultimos 12 meses.
	$meses = array();
	$agora = new DateTimeImmutable( wp_date( 'Y-m-01' ), wp_timezone() );
	for ( $i = 11; $i >= 0; $i-- ) {
		$meses[ $agora->modify( '-' . $i . ' months' )->format( 'Y-m' ) ] = array( 'ped' => 0, 'val' => 0.0, 'com' => 0.0 );
	}
	$ini12 = $agora->modify( '-11 months' )->getTimestamp();
	foreach ( function_exists( 'dcmx_com_pedidos' ) ? dcmx_com_pedidos( min( $ini, $ini12 ), time(), $fid ) : array() as $l ) {
		list( $o, $fr ) = $l;
		$dc = $o->get_date_created();
		$ts = $dc ? $dc->getTimestamp() : 0;
		if ( ! isset( $esc[ $fr ] ) ) {
			continue;
		}
		if ( 'pending' === $o->get_status() ) {
			if ( $ts >= $ini ) {
				$esc[ $fr ]['pend']++;
			}
			continue;
		}
		$c = dcmx_com_pedido( $o );
		$v = (float) $o->get_total();
		if ( $ts >= $ini ) {
			$esc[ $fr ]['ped']++;
			$esc[ $fr ]['val'] += $v;
			$esc[ $fr ]['com'] += $c['liq'];
		}
		$esc[ $fr ]['ult'] = max( $esc[ $fr ]['ult'], $ts );
		$mk                = wp_date( 'Y-m', $ts );
		if ( isset( $meses[ $mk ] ) ) {
			$meses[ $mk ]['ped']++;
			$meses[ $mk ]['val'] += $v;
			$meses[ $mk ]['com'] += $c['liq'];
		}
	}
	uasort(
		$esc,
		function ( $a, $b ) {
			return $b['val'] <=> $a['val'] ?: strcasecmp( $a['nome'], $b['nome'] );
		}
	);
	$tp = 0;
	$tv = 0.0;
	$tc = 0.0;
	$ativas = 0;
	$tr = '';
	$rot = array( 'aprovada' => array( 'Ativa', 'en' ), 'pendente' => array( 'Em an&aacute;lise', 'pr' ), 'suspensa' => array( 'Reprovada', 'rp' ) );
	foreach ( $esc as $id => $e ) {
		$tp += $e['ped'];
		$tv += $e['val'];
		$tc += $e['com'];
		$ativas += $e['ped'] > 0 ? 1 : 0;
		$r   = isset( $rot[ $e['st'] ] ) ? $rot[ $e['st'] ] : array( 'Em an&aacute;lise', 'pr' );
		$tr .= '<tr><td><strong>' . esc_html( $e['nome'] ) . '</strong><br><small style="color:#667085">' . esc_html( $e['cid'] ) . '</small></td><td><span class="st ' . $r[1] . '">' . $r[0] . '</span></td><td>' . (int) $e['ped'] . ( $e['pend'] ? ' <small style="color:#B54708">+' . (int) $e['pend'] . ' aguard.</small>' : '' ) . '</td><td>' . esc_html( dcmx_money( $e['val'] ) ) . '</td><td>' . esc_html( $e['ped'] ? dcmx_money( $e['val'] / $e['ped'] ) : '–' ) . '</td><td>' . esc_html( dcmx_money( $e['com'] ) ) . '</td><td>' . ( $e['ult'] ? esc_html( wp_date( 'd/m/Y', $e['ult'] ) ) : '<span style="color:#B42318">nunca comprou</span>' ) . '</td></tr>'; // phpcs:ignore
	}
	$tm  = '';
	$nms = array( '', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez' );
	foreach ( array_reverse( $meses, true ) as $k => $m ) {
		$tm .= '<tr><td>' . esc_html( $nms[ (int) substr( $k, 5, 2 ) ] . '/' . substr( $k, 0, 4 ) ) . '</td><td>' . (int) $m['ped'] . '</td><td>' . esc_html( dcmx_money( $m['val'] ) ) . '</td><td>' . esc_html( $m['ped'] ? dcmx_money( $m['val'] / $m['ped'] ) : '–' ) . '</td><td>' . esc_html( dcmx_money( $m['com'] ) ) . '</td></tr>';
	}
	$csv = wp_nonce_url( add_query_arg( array( 'tela' => 'relatorios', 'dias' => $dias, 'franquia' => $fid, 'dcmx_relcsv' => 1 ), $base ), 'dcmx_relcsv' );
	echo '<div class="dcmx-hd"><div><h2>Relat&oacute;rios</h2><p>Desempenho de cada escola e da rede (pedidos pagos).</p></div>' . dcmx_filtros_html( $base, 'relatorios', $dias, $fid ) . '</div>'; // phpcs:ignore
	echo '<div class="dcmx-k"><div><i class="kt">' . dcmx_ico( 'store' ) . '</i><p><span>Escolas que compraram</span><b>' . (int) $ativas . ' de ' . (int) count( $esc ) . '</b></p></div><div><i class="kt">' . dcmx_ico( 'doc' ) . '</i><p><span>Pedidos pagos</span><b>' . (int) $tp . '</b></p></div><div><i class="kt">' . dcmx_ico( 'cart' ) . '</i><p><span>Ticket m&eacute;dio</span><b>' . esc_html( $tp ? dcmx_money( $tv / $tp ) : 'R$ 0,00' ) . '</b></p></div><div><i class="kt">' . dcmx_ico( 'coins' ) . '</i><p><span>Comiss&atilde;o l&iacute;quida</span><b>' . esc_html( dcmx_money( $tc ) ) . '</b></p></div></div>'; // phpcs:ignore
	echo '<div class="dcmx-c" style="margin-bottom:16px"><h3 style="justify-content:space-between">Por escola <a href="' . esc_url( $csv ) . '" style="font-size:13px;font-weight:700;color:#8A6D1F;text-decoration:none">' . dcmx_ico( 'doc' ) . ' Baixar planilha</a></h3><div style="overflow-x:auto"><table class="dcmx-t"><thead><tr><th>Escola</th><th>Situa&ccedil;&atilde;o</th><th>Pedidos</th><th>Valor</th><th>Ticket m&eacute;dio</th><th>Comiss&atilde;o</th><th>&Uacute;ltima compra</th></tr></thead><tbody>' . ( '' !== $tr ? $tr : '<tr><td colspan="7">Nenhuma escola.</td></tr>' ) . '</tbody></table></div></div>'; // phpcs:ignore
	echo '<div class="dcmx-c"><h3>M&ecirc;s a m&ecirc;s (12 meses)</h3><div style="overflow-x:auto"><table class="dcmx-t"><thead><tr><th>M&ecirc;s</th><th>Pedidos</th><th>Valor</th><th>Ticket m&eacute;dio</th><th>Comiss&atilde;o</th></tr></thead><tbody>' . $tm . '</tbody></table></div></div>'; // phpcs:ignore
}

/* Planilha do relatorio por escola. */
function dcmx_rel_csv() {
	if ( empty( $_GET['dcmx_relcsv'] ) || ! current_user_can( 'dcmx_ver' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dcmx_relcsv' ) ) {
		return;
	}
	$dias = dcmx_get_dias();
	$fid  = isset( $_GET['franquia'] ) ? absint( $_GET['franquia'] ) : 0;
	$sum  = array();
	foreach ( dcmx_rede_pagos( time() - $dias * DAY_IN_SECONDS, time(), $fid ) as $l ) {
		list( $o, $fr ) = $l;
		if ( ! isset( $sum[ $fr ] ) ) {
			$sum[ $fr ] = array( 0, 0.0, 0.0 );
		}
		$c = dcmx_com_pedido( $o );
		$sum[ $fr ][0]++;
		$sum[ $fr ][1] += (float) $o->get_total();
		$sum[ $fr ][2] += $c['liq'];
	}
	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="relatorio-ouro-moderno-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$f = fopen( 'php://output', 'w' );
	fwrite( $f, "\xEF\xBB\xBF" );
	fputcsv( $f, array( 'Escola', 'Cidade', 'CNPJ', 'Pedidos pagos', 'Valor', 'Ticket medio', 'Comissao liquida' ), ';' );
	foreach ( $sum as $fr => $s ) {
		fputcsv( $f, array( (string) get_user_meta( $fr, 'omx_escola', true ), (string) get_user_meta( $fr, 'omx_cidade', true ), (string) get_user_meta( $fr, 'omx_cnpj', true ), $s[0], number_format( $s[1], 2, ',', '' ), number_format( $s[0] ? $s[1] / $s[0] : 0, 2, ',', '' ), number_format( $s[2], 2, ',', '' ) ), ';' );
	}
	fclose( $f );
	exit;
}
add_action( 'admin_init', 'dcmx_rel_csv' );
add_action( 'template_redirect', 'dcmx_rel_csv', -1 );

/* ------------------------------------------------ Campanhas e Materiais */

require_once __DIR__ . '/campanhas-franquia.php';

/* Envia um arquivo do formulario para a Biblioteca de Midia. */
function dcom_upload( $campo, $tipos ) {
	if ( empty( $_FILES[ $campo ]['name'] ) ) { // phpcs:ignore
		return 0;
	}
	if ( (int) $_FILES[ $campo ]['size'] > 20 * MB_IN_BYTES ) { // phpcs:ignore
		return new WP_Error( 'grande', 'Arquivo maior que 20 MB.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	return media_handle_upload( $campo, 0, array(), array( 'test_form' => false, 'mimes' => $tipos ) );
}

function dcmx_tipos_img() {
	return array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
}

function dcmx_tipos_mat() {
	return array_merge( dcmx_tipos_img(), array( 'pdf' => 'application/pdf', 'zip' => 'application/zip', 'mp4' => 'video/mp4', 'cdr' => 'application/octet-stream', 'ai' => 'application/postscript', 'psd' => 'image/vnd.adobe.photoshop' ) );
}

function dcmx_tela_campanhas( $base ) {
	$pode = current_user_can( 'dcmx_config' );
	$msg  = '';
	$list = dcom_campanhas();
	if ( $pode && isset( $_POST['dcmx_camp_n'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcmx_camp_n'] ) ), 'dcmx_camp' ) ) {
		$acao = isset( $_POST['acao'] ) ? sanitize_key( wp_unslash( $_POST['acao'] ) ) : '';
		$id   = isset( $_POST['cid'] ) ? sanitize_key( wp_unslash( $_POST['cid'] ) ) : '';
		if ( 'excluir' === $acao && isset( $list[ $id ] ) ) {
			unset( $list[ $id ] );
			$msg = 'Campanha exclu&iacute;da.';
		} elseif ( 'alternar' === $acao && isset( $list[ $id ] ) ) {
			$list[ $id ]['ativo'] = empty( $list[ $id ]['ativo'] ) ? 1 : 0;
			$msg = $list[ $id ]['ativo'] ? 'Campanha no ar.' : 'Campanha pausada.';
		} elseif ( 'salvar' === $acao ) {
			$tit = isset( $_POST['titulo'] ) ? sanitize_text_field( wp_unslash( $_POST['titulo'] ) ) : '';
			if ( '' === $tit ) {
				$msg = 'Informe o t&iacute;tulo.';
			} else {
				$img = dcom_upload( 'imagem', dcmx_tipos_img() );
				if ( is_wp_error( $img ) ) {
					$msg = esc_html( $img->get_error_message() );
				} else {
					$id          = '' !== $id && isset( $list[ $id ] ) ? $id : 'c' . wp_generate_password( 8, false, false );
					$old         = isset( $list[ $id ] ) ? $list[ $id ] : array();
					$list[ $id ] = array(
						'id'     => $id,
						'titulo' => $tit,
						'texto'  => isset( $_POST['texto'] ) ? sanitize_textarea_field( wp_unslash( $_POST['texto'] ) ) : '',
						'inicio' => isset( $_POST['inicio'] ) ? sanitize_text_field( wp_unslash( $_POST['inicio'] ) ) : '',
						'fim'    => isset( $_POST['fim'] ) ? sanitize_text_field( wp_unslash( $_POST['fim'] ) ) : '',
						'link'   => isset( $_POST['link'] ) ? esc_url_raw( wp_unslash( $_POST['link'] ) ) : '',
						'botao'  => isset( $_POST['botao'] ) ? sanitize_text_field( wp_unslash( $_POST['botao'] ) ) : '',
						'img'    => $img ? (int) $img : ( isset( $old['img'] ) ? (int) $old['img'] : 0 ),
						'ativo'  => isset( $old['ativo'] ) ? $old['ativo'] : 1,
					);
					$msg = 'Campanha salva. Ela aparece para as escolas no painel delas.';
				}
			}
		}
		update_option( 'dcom_campanhas', $list, false );
	}
	$ed = isset( $_GET['editar'] ) ? sanitize_key( wp_unslash( $_GET['editar'] ) ) : ''; // phpcs:ignore
	$e  = isset( $list[ $ed ] ) ? $list[ $ed ] : array( 'id' => '', 'titulo' => '', 'texto' => '', 'inicio' => wp_date( 'Y-m-d' ), 'fim' => '', 'link' => '', 'botao' => '', 'img' => 0 );
	$hoje = wp_date( 'Y-m-d' );
	$cards = '';
	foreach ( array_reverse( $list, true ) as $id => $c ) {
		$no_ar = ! empty( $c['ativo'] ) && ( empty( $c['inicio'] ) || $c['inicio'] <= $hoje ) && ( empty( $c['fim'] ) || $c['fim'] >= $hoje );
		$img   = $c['img'] ? wp_get_attachment_image_url( $c['img'], 'medium' ) : '';
		$per   = ( $c['inicio'] ? wp_date( 'd/m/Y', strtotime( $c['inicio'] ) ) : '' ) . ( $c['fim'] ? ' a ' . wp_date( 'd/m/Y', strtotime( $c['fim'] ) ) : ' (sem fim)' );
		$f     = function ( $acao, $txt, $cls ) use ( $id ) {
			return '<form method="post" style="display:inline">' . wp_nonce_field( 'dcmx_camp', 'dcmx_camp_n', true, false ) . '<input type="hidden" name="acao" value="' . esc_attr( $acao ) . '"><input type="hidden" name="cid" value="' . esc_attr( $id ) . '"><button class="cb ' . esc_attr( $cls ) . '"' . ( 'excluir' === $acao ? ' onclick="return confirm(\'Excluir esta campanha?\')"' : '' ) . '>' . esc_html( $txt ) . '</button></form>';
		};
		$cards .= '<div class="dcmx-cp">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="">' : '<div class="ph">' . dcmx_ico( 'mega' ) . '</div>' ) . '<div class="tx"><span class="st ' . ( $no_ar ? 'en' : 'pr' ) . '">' . ( $no_ar ? 'No ar' : ( empty( $c['ativo'] ) ? 'Pausada' : 'Fora do per&iacute;odo' ) ) . '</span><h4>' . esc_html( $c['titulo'] ) . '</h4><p>' . esc_html( wp_trim_words( $c['texto'], 24, '…' ) ) . '</p><small>' . esc_html( $per ) . '</small>'
			. ( $pode ? '<div class="ac"><a class="cb" href="' . esc_url( add_query_arg( array( 'tela' => 'campanhas', 'editar' => $id ), $base ) ) . '#form">Editar</a>' . $f( 'alternar', empty( $c['ativo'] ) ? 'Colocar no ar' : 'Pausar', '' ) . $f( 'excluir', 'Excluir', 'rp' ) . '</div>' : '' ) . '</div></div>'; // phpcs:ignore
	}
	echo '<style>.dcmx-cps{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;margin-bottom:16px}.dcmx-cp{background:#fff;border:1px solid #E7E9F0;border-radius:16px;overflow:hidden;display:flex;flex-direction:column}.dcmx-cp img,.dcmx-cp .ph{width:100%;height:150px;object-fit:cover;background:#0B1F3F;color:#D9B45A;display:flex;align-items:center;justify-content:center}.dcmx-cp .ph .ic{width:48px;height:48px}.dcmx-cp .tx{padding:14px 16px}.dcmx-cp h4{margin:8px 0 4px;font-size:17px}.dcmx-cp p{margin:0 0 6px;color:#475467;font-size:13.5px}.dcmx-cp small{color:#667085}.dcmx-cp .ac{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}.cb{display:inline-block;border:1px solid #D0D5DD;background:#fff;border-radius:8px;padding:6px 12px;font-weight:700;font-size:12.5px;color:#101828;text-decoration:none;cursor:pointer}.cb.rp{color:#B42318;border-color:#F1B5AE}.dcmx-fm{display:grid;grid-template-columns:1fr 1fr;gap:12px}.dcmx-fm label{display:block;font-weight:700;font-size:13px;color:#344054}.dcmx-fm input[type=text],.dcmx-fm input[type=url],.dcmx-fm input[type=date],.dcmx-fm textarea{width:100%;margin-top:4px;border:1px solid #D0D5DD;border-radius:10px;padding:9px 10px;font:inherit}.dcmx-fm .w{grid-column:1/-1}.dcmx-bt{background:#C9A24A;color:#0B1F3F;border:0;border-radius:10px;padding:11px 20px;font-weight:800;cursor:pointer}@media(max-width:700px){.dcmx-fm{grid-template-columns:1fr}}</style>'; // phpcs:ignore
	echo '<div class="dcmx-hd"><div><h2>Campanhas da Rede</h2><p>Campanhas que aparecem no painel de todas as escolas (banner da tela inicial e aba Marketing e Campanhas).</p></div></div>';
	if ( '' !== $msg ) {
		echo '<div class="notice"><p>' . $msg . '</p></div>'; // phpcs:ignore
	}
	echo '<div class="dcmx-cps">' . ( '' !== $cards ? $cards : '<div class="dcmx-c">Nenhuma campanha ainda. Crie a primeira abaixo.</div>' ) . '</div>'; // phpcs:ignore
	if ( $pode ) {
		echo '<form method="post" enctype="multipart/form-data" class="dcmx-c" id="form"><h3>' . ( $e['id'] ? 'Editar campanha' : 'Nova campanha' ) . '</h3>' . wp_nonce_field( 'dcmx_camp', 'dcmx_camp_n', true, false ) . '<input type="hidden" name="acao" value="salvar"><input type="hidden" name="cid" value="' . esc_attr( $e['id'] ) . '">' // phpcs:ignore
			. '<div class="dcmx-fm"><label class="w">T&iacute;tulo<input type="text" name="titulo" required value="' . esc_attr( $e['titulo'] ) . '" placeholder="Ex.: Campanha de Matr&iacute;culas 2027"></label>'
			. '<label class="w">Texto<textarea name="texto" rows="3" placeholder="O que a escola deve fazer nesta campanha">' . esc_textarea( $e['texto'] ) . '</textarea></label>'
			. '<label>In&iacute;cio<input type="date" name="inicio" value="' . esc_attr( $e['inicio'] ) . '"></label><label>Fim (vazio = sem fim)<input type="date" name="fim" value="' . esc_attr( $e['fim'] ) . '"></label>'
			. '<label>Link do bot&atilde;o (opcional)<input type="url" name="link" value="' . esc_attr( $e['link'] ) . '" placeholder="https://..."></label><label>Texto do bot&atilde;o<input type="text" name="botao" value="' . esc_attr( $e['botao'] ) . '" placeholder="Ver produtos da campanha"></label>'
			. '<label class="w">Imagem do banner (JPG, PNG ou WEBP, horizontal, ex.: 1600 &times; 500)' . ( $e['img'] ? ' &mdash; j&aacute; tem imagem; envie outra para trocar' : '' ) . '<input type="file" name="imagem" accept="image/jpeg,image/png,image/webp" style="margin-top:6px"></label></div>'
			. '<p style="margin:14px 0 0"><button class="dcmx-bt" type="submit">Salvar campanha</button>' . ( $e['id'] ? ' <a class="cb" href="' . esc_url( add_query_arg( 'tela', 'campanhas', $base ) ) . '">Cancelar</a>' : '' ) . '</p></form>';
	}
}

function dcmx_tela_materiais( $base ) {
	$pode = current_user_can( 'dcmx_config' );
	$msg  = '';
	$list = dcom_materiais();
	if ( $pode && isset( $_POST['dcmx_mat_n'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcmx_mat_n'] ) ), 'dcmx_mat' ) ) {
		$acao = isset( $_POST['acao'] ) ? sanitize_key( wp_unslash( $_POST['acao'] ) ) : '';
		if ( 'excluir' === $acao ) {
			$id = isset( $_POST['mid'] ) ? sanitize_key( wp_unslash( $_POST['mid'] ) ) : '';
			unset( $list[ $id ] );
			$msg = 'Material removido da lista (o arquivo continua na Biblioteca de M&iacute;dia).';
		} else {
			$tit = isset( $_POST['titulo'] ) ? sanitize_text_field( wp_unslash( $_POST['titulo'] ) ) : '';
			$att = dcom_upload( 'arquivo', dcmx_tipos_mat() );
			if ( is_wp_error( $att ) ) {
				$msg = esc_html( $att->get_error_message() );
			} elseif ( ! $att ) {
				$msg = 'Escolha o arquivo.';
			} else {
				$id          = 'm' . wp_generate_password( 8, false, false );
				$list[ $id ] = array(
					'id'     => $id,
					'titulo' => '' !== $tit ? $tit : get_the_title( $att ),
					'cat'    => isset( $_POST['cat'] ) ? sanitize_text_field( wp_unslash( $_POST['cat'] ) ) : 'Geral',
					'att'    => (int) $att,
					't'      => time(),
				);
				$msg = 'Material enviado. As escolas j&aacute; podem baixar.';
			}
		}
		update_option( 'dcom_materiais', $list, false );
	}
	$cats = array( 'Artes para redes sociais', 'Banners e impressos', 'Logotipos', 'Apresentações', 'Vídeos', 'Geral' );
	$oc   = '';
	foreach ( $cats as $c ) {
		$oc .= '<option>' . esc_html( $c ) . '</option>';
	}
	$tr = '';
	foreach ( array_reverse( $list, true ) as $id => $m ) {
		$url  = wp_get_attachment_url( $m['att'] );
		$th   = wp_attachment_is_image( $m['att'] ) ? wp_get_attachment_image_url( $m['att'], 'thumbnail' ) : '';
		$ext  = strtoupper( (string) pathinfo( (string) $url, PATHINFO_EXTENSION ) );
		$tr  .= '<tr><td>' . ( $th ? '<img src="' . esc_url( $th ) . '" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px">' : '<span class="st ag">' . esc_html( $ext ) . '</span>' ) . '</td><td><strong>' . esc_html( $m['titulo'] ) . '</strong></td><td>' . esc_html( $m['cat'] ) . '</td><td>' . esc_html( wp_date( 'd/m/Y', $m['t'] ) ) . '</td><td><a class="cb" href="' . esc_url( (string) $url ) . '" target="_blank" rel="noopener" download>Baixar</a> '
			. ( $pode ? '<form method="post" style="display:inline">' . wp_nonce_field( 'dcmx_mat', 'dcmx_mat_n', true, false ) . '<input type="hidden" name="acao" value="excluir"><input type="hidden" name="mid" value="' . esc_attr( $id ) . '"><button class="cb rp" onclick="return confirm(\'Remover este material?\')">Remover</button></form>' : '' ) . '</td></tr>'; // phpcs:ignore
	}
	echo '<style>.cb{display:inline-block;border:1px solid #D0D5DD;background:#fff;border-radius:8px;padding:6px 12px;font-weight:700;font-size:12.5px;color:#101828;text-decoration:none;cursor:pointer}.cb.rp{color:#B42318;border-color:#F1B5AE}.dcmx-fm{display:grid;grid-template-columns:2fr 1fr 2fr auto;gap:12px;align-items:end}.dcmx-fm label{display:block;font-weight:700;font-size:13px;color:#344054}.dcmx-fm input[type=text],.dcmx-fm select{width:100%;margin-top:4px;border:1px solid #D0D5DD;border-radius:10px;padding:9px 10px;font:inherit}.dcmx-bt{background:#C9A24A;color:#0B1F3F;border:0;border-radius:10px;padding:11px 20px;font-weight:800;cursor:pointer}@media(max-width:900px){.dcmx-fm{grid-template-columns:1fr}}</style>'; // phpcs:ignore
	echo '<div class="dcmx-hd"><div><h2>Materiais e Artes</h2><p>Arquivos que as escolas baixam no painel delas (aba Marketing e Campanhas).</p></div></div>';
	if ( '' !== $msg ) {
		echo '<div class="notice"><p>' . $msg . '</p></div>'; // phpcs:ignore
	}
	if ( $pode ) {
		echo '<form method="post" enctype="multipart/form-data" class="dcmx-c" style="margin-bottom:16px"><h3>Enviar material</h3>' . wp_nonce_field( 'dcmx_mat', 'dcmx_mat_n', true, false ) . '<input type="hidden" name="acao" value="enviar"><div class="dcmx-fm"><label>Nome<input type="text" name="titulo" placeholder="Ex.: Post matr&iacute;culas abertas"></label><label>Tipo<select name="cat">' . $oc . '</select></label><label>Arquivo (imagem, PDF, ZIP, v&iacute;deo; at&eacute; 20 MB)<input type="file" name="arquivo" required style="margin-top:6px"></label><button class="dcmx-bt" type="submit">Enviar</button></div></form>'; // phpcs:ignore
	}
	echo '<div class="dcmx-c"><h3>Materiais (' . (int) count( $list ) . ')</h3><div style="overflow-x:auto"><table class="dcmx-t"><thead><tr><th></th><th>Nome</th><th>Tipo</th><th>Enviado</th><th></th></tr></thead><tbody>' . ( '' !== $tr ? $tr : '<tr><td colspan="5">Nenhum material enviado ainda.</td></tr>' ) . '</tbody></table></div></div>'; // phpcs:ignore
}

/* Mostra arquivos .ai, .cdr e .psd tambem na Biblioteca (so quem gerencia a Matriz envia). */
add_filter(
	'upload_mimes',
	function ( $m ) {
		if ( current_user_can( 'dcmx_config' ) ) {
			$m['cdr'] = 'application/octet-stream';
			$m['psd'] = 'image/vnd.adobe.photoshop';
		}
		return $m;
	}
);
