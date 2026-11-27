<?php
/**
 * Casafinna: os 10 ícones de categorias da página inicial (canetas, canecas, chaveiros, copos, cozinha e churrasco,
 * garrafas térmicas, kits executivos, mochilas, outros brindes, sacolas) filtram a lista "Todos os produtos".
 * Cada produto da loja entra num desses grupos conforme a categoria que ele já tem no cadastro (sem mexer nas categorias).
 * Os botões de filtro da loja passam a ser esses mesmos grupos. Só vale no site da Casafinna.
 * Os ícones apontam para #g-canetas, #g-canecas ... e o script abaixo aplica o filtro e rola até a lista.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dccf_grupos() {
	// slug do grupo => array( nome, ids das categorias (e das filhas) que entram nele )
	return array(
		'g-canetas'   => array( 'Canetas', array( 141, 537, 1349 ) ),
		'g-canecas'   => array( 'Canecas', array( 150, 1357 ) ),
		'g-chaveiros' => array( 'Chaveiros', array( 153, 1351 ) ),
		'g-copos'     => array( 'Copos', array( 177, 1356 ) ),
		'g-cozinha'   => array( 'Cozinha e churrasco', array( 1346, 1347, 1348, 1350 ) ),
		'g-garrafas'  => array( 'Garrafas térmicas', array( 170, 1344 ) ),
		'g-kits'      => array( 'Kits executivos', array( 1012, 147, 160, 1353 ) ),
		'g-mochilas'  => array( 'Mochilas', array( 994, 163, 1355 ) ),
		'g-sacolas'   => array( 'Sacolas', array( 135, 1352 ) ),
		'g-outros'    => array( 'Outros brindes', array() ),
	);
}

add_filter(
	'dclay_subs_produto',
	function ( $mine, $pid, $c, $terms ) {
		if ( ! ( ( function_exists( 'dccf_host_casafinna' ) && dccf_host_casafinna() ) || ( function_exists( 'dccast_host_cast' ) && dccast_host_cast() ) ) ) {
			return $mine;
		}
		$ids = array();
		foreach ( (array) $terms as $t ) {
			$ids[ (int) $t->term_id ] = 1;
			foreach ( get_ancestors( (int) $t->term_id, 'product_cat' ) as $a ) {
				$ids[ (int) $a ] = 1;
			}
		}
		$g   = dccf_grupos();
		$out = array();
		if ( isset( $ids[135] ) || isset( $ids[1352] ) ) {
			$out['g-sacolas'] = $g['g-sacolas'][0]; // sacolas são filhas de "Bolsas": ficam só em Sacolas
		} else {
			foreach ( $g as $slug => $d ) {
				if ( 'g-sacolas' !== $slug && array_intersect_key( $ids, array_flip( $d[1] ) ) ) {
					$out[ $slug ] = $d[0];
				}
			}
		}
		if ( ! $out ) {
			$out['g-outros'] = $g['g-outros'][0];
		}
		return $out;
	},
	10,
	4
);

add_action(
	'wp_footer',
	function () {
		if ( is_admin() || ! ( ( function_exists( 'dccf_host_casafinna' ) && dccf_host_casafinna() ) || ( function_exists( 'dccast_host_cast' ) && dccast_host_cast() ) ) ) {
			return;
		}
		echo '<script id="dccf-icones">(function(){function ir(g){var b=document.querySelector(\'.dcl-chips button[data-cat="\'+g+\'"]\');if(!b){return false;}b.click();var s=document.getElementById("produtos");if(s){s.scrollIntoView({behavior:"smooth"});}return true;}'
			. 'document.addEventListener("click",function(e){var a=e.target.closest(\'a[href^="#g-"]\');if(!a){return;}var g=a.getAttribute("href").slice(1);if(ir(g)){e.preventDefault();history.replaceState(null,"","#"+g);}});'
			. 'window.addEventListener("load",function(){if(/^#g-/.test(location.hash)){setTimeout(function(){ir(location.hash.slice(1));},300);}});})();</script>'; // phpcs:ignore
	},
	99
);
