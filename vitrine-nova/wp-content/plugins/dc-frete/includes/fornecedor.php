<?php
/**
 * DC Frete - peso e medidas vindos do fornecedor (XBZ, Asia, Spot).
 * Parte pura (sem WordPress) no topo; a tela do painel vem depois.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'DCFRETE_CLI' ) ) {
	exit;
}

/** XBZ: Peso em GRAMAS; Altura/Largura/Profundidade em cm. */
function dcfrete_forn_xbz( $p ) {
	$g = (float) ( isset( $p['Peso'] ) ? $p['Peso'] : 0 );
	return array(
		'peso' => ( $g > 0 && $g / 1000 <= 30 ) ? round( $g / 1000, 3 ) : 0,
		'c'    => (float) ( isset( $p['Profundidade'] ) ? $p['Profundidade'] : 0 ),
		'l'    => (float) ( isset( $p['Largura'] ) ? $p['Largura'] : 0 ),
		'a'    => (float) ( isset( $p['Altura'] ) ? $p['Altura'] : 0 ),
	);
}

/** Asia: peso em KG; altura/largura/comprimento em cm. */
function dcfrete_forn_asia( $p ) {
	$kg = (float) ( isset( $p['peso'] ) ? $p['peso'] : 0 );
	return array(
		'peso' => ( $kg > 0 && $kg < 30 ) ? round( $kg, 3 ) : 0,
		'c'    => (float) ( isset( $p['comprimento'] ) ? $p['comprimento'] : 0 ),
		'l'    => (float) ( isset( $p['largura'] ) ? $p['largura'] : 0 ),
		'a'    => (float) ( isset( $p['altura'] ) ? $p['altura'] : 0 ),
	);
}

/** Spot: peso da caixa master dividido pelas unidades da caixa. Sem medida da unidade. */
function dcfrete_forn_spot( $r ) {
	$bq = (float) ( isset( $r['BoxQuantity'] ) ? $r['BoxQuantity'] : 0 );
	$bw = (float) ( isset( $r['BoxWeightKG'] ) ? $r['BoxWeightKG'] : 0 );
	$w  = ( $bq >= 1 && $bw > 0 ) ? round( $bw / $bq, 3 ) : 0;
	return array( 'peso' => ( $w > 0 && $w <= 30 ) ? $w : 0, 'c' => 0, 'l' => 0, 'a' => 0 );
}

/**
 * Decide o que gravar.
 * $atual  = array( peso, c, l, a ) como esta no produto
 * $forn   = array( peso, c, l, a ) do fornecedor
 * $marca  = array( 'w' => 0/1, 'd' => 0/1 ) do _dc_ship_auto (1 = foi chute da tela por categoria)
 * Regra: so troca o que esta vazio ou foi chute da tela. Valor digitado a mao nunca muda.
 * Retorna array( 'peso' => novo|null, 'dim' => array(c,l,a)|null, 'estimada' => bool )
 */
function dcfrete_forn_decidir( $atual, $forn, $marca ) {
	$out = array( 'peso' => null, 'dim' => null, 'estimada' => false );

	$sem_peso = (float) $atual['peso'] <= 0;
	if ( $forn['peso'] > 0 && ( $sem_peso || ! empty( $marca['w'] ) ) && abs( (float) $atual['peso'] - $forn['peso'] ) > 0.0005 ) {
		$out['peso'] = $forn['peso'];
	}

	$sem_dim = (float) $atual['c'] <= 0 || (float) $atual['l'] <= 0 || (float) $atual['a'] <= 0;
	if ( $sem_dim || ! empty( $marca['d'] ) ) {
		$c = (float) $forn['c'];
		$l = (float) $forn['l'];
		$a = (float) $forn['a'];
		if ( $c <= 0 && $l > 0 && $a > 0 ) {
			$c = $l; // objeto redondo/fino (caneta, copo, garrafa): sem profundidade no catalogo, a largura é o diâmetro
		}
		if ( $c > 0 && $l > 0 ) {
			if ( $a <= 0 ) {
				$a = $l; // objeto redondo/fino: sem altura no catalogo, usa a largura
				$out['estimada'] = true;
			}
			$mudou = abs( (float) $atual['c'] - $c ) > 0.05 || abs( (float) $atual['l'] - $l ) > 0.05 || abs( (float) $atual['a'] - $a ) > 0.05;
			if ( $mudou ) {
				$out['dim'] = array( $c, $l, $a );
			}
		}
	}
	return $out;
}
