<?php
/**
 * Plugin Name: DC - Estoque por cor (XBZ e Asia)
 * Description: Nas rodadas dos sincronizadores XBZ e Asia, atualiza a tabela "Cores disponíveis" (_dc_color_stock_table) cor por cor, com o estoque do fornecedor. Cor que acabou fica 0 (e some da tela); cor nova do fornecedor entra na tabela.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Estoque total = soma das cores mostradas (para o total bater com as "Cores disponíveis").
 * Roda no fim de cada sincronização XBZ/Asia que deu certo.
 */
function dcce_totais( $fonte ) {
	global $wpdb;
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT t.post_id, t.meta_value AS tab, st.meta_value AS stk
			   FROM {$wpdb->postmeta} t
			   JOIN {$wpdb->postmeta} f ON f.post_id = t.post_id AND f.meta_key = %s
			   JOIN {$wpdb->posts} p ON p.ID = t.post_id AND p.post_type = 'product' AND p.post_status = 'publish'
			   LEFT JOIN {$wpdb->postmeta} st ON st.post_id = t.post_id AND st.meta_key = '_stock'
			  WHERE t.meta_key = '_dc_color_stock_table' AND t.meta_value <> ''",
			$fonte
		)
	);
	$n = 0;
	foreach ( $rows as $r ) {
		$tab = json_decode( (string) $r->tab, true );
		if ( ! is_array( $tab ) || ! $tab ) {
			continue;
		}
		$soma = 0;
		foreach ( $tab as $t ) {
			$soma += is_array( $t ) && isset( $t['stock'] ) ? max( 0, (int) $t['stock'] ) : 0;
		}
		if ( null !== $r->stk && '' !== $r->stk && (int) $r->stk === $soma ) {
			continue;
		}
		$p = wc_get_product( (int) $r->post_id );
		if ( ! $p || $p->is_type( 'variable' ) ) {
			continue;
		}
		$p->set_manage_stock( true );
		$p->set_stock_quantity( $soma );
		$p->set_stock_status( 'instock' );
		$p->save();
		$n++;
	}
	update_option( 'dcce_totais_' . ltrim( $fonte, '_' ), array( 't' => current_time( 'mysql' ), 'ajustados' => $n ), false );
	return $n;
}
foreach ( array( 'dcxbz_last_report' => '_dcxbz_source', 'dcasia_last_report' => '_dcasia_source' ) as $dcce_op => $dcce_f ) {
	$dcce_cb = function ( $a, $b = null ) use ( $dcce_f ) {
		$rep = is_array( $b ) ? $b : $a; // update_option_* (old, new) / add_option_* (name, value)
		if ( is_array( $rep ) && ! empty( $rep['ok'] ) && ! empty( $rep['finished'] ) && empty( $rep['dry_run'] ) && function_exists( 'wc_get_product' ) ) {
			dcce_totais( $dcce_f );
		}
	};
	add_action( 'update_option_' . $dcce_op, $dcce_cb, 20, 2 );
	add_action( 'add_option_' . $dcce_op, $dcce_cb, 20, 2 );
}

/** Normaliza código: maiúsculas, sem espaço, sem prefixo P@/E@... e sem *CP*. */
function dcce_norm( $c ) {
	$c = strtoupper( trim( (string) $c ) );
	$c = preg_replace( '/^[A-Z]{1,3}@/', '', $c );
	$c = preg_replace( '/\*[A-Z]+\*$/', '', $c );
	return preg_replace( '/\s+/', '', $c );
}

/** XBZ: cada linha do catálogo é uma cor. => [ código-da-cor => [q, l, f] ] */
function dcce_mapa_xbz( $catalog ) {
	$m = array();
	foreach ( (array) $catalog as $p ) {
		if ( ! is_array( $p ) || empty( $p['CodigoComposto'] ) ) {
			continue;
		}
		$k = dcce_norm( $p['CodigoComposto'] );
		$q = max( 0, (int) ( $p['QuantidadeDisponivel'] ?? 0 ) );
		$l = trim( (string) ( $p['CorWebPrincipal'] ?? ( $p['Cor'] ?? '' ) ) );
		$f = preg_replace( '#-[A-Z]{2,4}(?:/[A-Z]{2,4})?$#', '', $k );
		if ( ! isset( $m[ $k ] ) ) {
			$m[ $k ] = array( 'q' => 0, 'l' => $l, 'f' => $f );
		}
		$m[ $k ]['q'] += $q;
	}
	return $m;
}

/** Asia: cada produto tem variações (cores). */
function dcce_mapa_asia( $catalog ) {
	$m = array();
	foreach ( (array) $catalog as $p ) {
		if ( ! is_array( $p ) || empty( $p['variacoes'] ) || ! is_array( $p['variacoes'] ) ) {
			continue;
		}
		$f = dcce_norm( $p['referencia'] ?? '' );
		foreach ( $p['variacoes'] as $v ) {
			if ( empty( $v['referencia'] ) ) {
				continue;
			}
			$k = dcce_norm( $v['referencia'] );
			$l = isset( $v['atributos']['cor']['value'] ) && is_string( $v['atributos']['cor']['value'] ) ? trim( $v['atributos']['cor']['value'] ) : '';
			foreach ( '' !== $l ? array() : array( 'cor', 'nome_cor', 'cor_nome', 'descricao_cor' ) as $ck ) {
				if ( ! empty( $v[ $ck ] ) && is_string( $v[ $ck ] ) ) {
					$l = trim( $v[ $ck ] );
					break;
				}
			}
			$m[ $k ] = array( 'q' => max( 0, (int) ( $v['qtd_estoque'] ?? 0 ) ), 'l' => $l, 'f' => $f );
		}
	}
	return $m;
}

/**
 * Atualiza as tabelas de cores dos produtos de uma fonte.
 *
 * @param array  $mapa  código-da-cor => [q, l, f].
 * @param string $fonte meta que marca a origem (_dcxbz_source / _dcasia_source).
 * @param bool   $dry   só simula.
 * @return array contagem.
 */
function dc_cores_atualizar( $mapa, $fonte, $dry = false ) {
	global $wpdb;
	$res = array( 'produtos' => 0, 'alterados' => 0, 'cores_novas' => 0 );
	if ( count( $mapa ) < 100 ) { // catálogo incompleto: não mexe
		return $res;
	}
	// Família => códigos de cor (para incluir cores que o fornecedor tem e a tabela não).
	$fam = array();
	foreach ( $mapa as $k => $v ) {
		if ( '' !== $v['f'] && $v['f'] !== $k ) {
			$fam[ $v['f'] ][] = $k;
		}
	}
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT t.post_id, t.meta_value AS tab, s.meta_value AS sku, r.meta_value AS ref
			   FROM {$wpdb->postmeta} t
			   JOIN {$wpdb->postmeta} f ON f.post_id = t.post_id AND f.meta_key = %s
			   LEFT JOIN {$wpdb->postmeta} s ON s.post_id = t.post_id AND s.meta_key = '_sku'
			   LEFT JOIN {$wpdb->postmeta} r ON r.post_id = t.post_id AND r.meta_key = '_dcasia_ref'
			  WHERE t.meta_key = '_dc_color_stock_table' AND t.meta_value <> ''",
			$fonte
		)
	);
	foreach ( $rows as $row ) {
		$tab = json_decode( (string) $row->tab, true );
		if ( ! is_array( $tab ) || ! $tab ) {
			continue;
		}
		$achou = false;
		$tem   = array();
		foreach ( $tab as $t ) {
			$k = dcce_norm( is_array( $t ) && isset( $t['sku'] ) ? $t['sku'] : '' );
			$tem[ $k ] = true;
			if ( '' !== $k && isset( $mapa[ $k ] ) ) {
				$achou = true;
			}
		}
		if ( ! $achou ) {
			continue; // nenhuma cor deste produto no catálogo: não mexe
		}
		$res['produtos']++;
		$mudou = false;
		foreach ( $tab as $i => $t ) {
			if ( ! is_array( $t ) ) {
				continue;
			}
			$k    = dcce_norm( isset( $t['sku'] ) ? $t['sku'] : '' );
			$novo = isset( $mapa[ $k ] ) ? (int) $mapa[ $k ]['q'] : 0;
			if ( ! isset( $t['stock'] ) || (int) $t['stock'] !== $novo ) {
				$tab[ $i ]['stock'] = $novo;
				$mudou              = true;
			}
		}
		// Cores novas do fornecedor (com nome e estoque).
		foreach ( array_unique( array_filter( array( dcce_norm( $row->sku ), dcce_norm( $row->ref ) ) ) ) as $f ) {
			foreach ( isset( $fam[ $f ] ) ? $fam[ $f ] : array() as $k ) {
				if ( isset( $tem[ $k ] ) || $mapa[ $k ]['q'] <= 0 || '' === $mapa[ $k ]['l'] ) {
					continue;
				}
				$tab[]     = array( 'sku' => $k, 'label' => $mapa[ $k ]['l'], 'stock' => (int) $mapa[ $k ]['q'] );
				$tem[ $k ] = true;
				$mudou     = true;
				$res['cores_novas']++;
			}
		}
		if ( ! $mudou ) {
			continue;
		}
		$res['alterados']++;
		if ( ! $dry ) {
			update_post_meta( (int) $row->post_id, '_dc_color_stock_table', wp_json_encode( array_values( $tab ) ) );
		}
	}
	return $res;
}
