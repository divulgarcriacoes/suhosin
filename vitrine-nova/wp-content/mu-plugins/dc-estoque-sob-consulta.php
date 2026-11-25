<?php
/**
 * Plugin Name: DC - Estoque sob consulta
 * Description: Guarda quando cada produto foi visto pela última vez no catálogo do fornecedor (Spot, XBZ, Asia). Produto que some do catálogo por mais de 3 dias deixa de mostrar números de estoque e passa a mostrar "Estoque sob consulta".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DCSC_DIAS', 3 );

/** Fontes: chave => metas que marcam o produto como daquele fornecedor. */
function dcsc_fontes() {
	return array(
		'spot' => array( '_dcspot_ref', '_dcspot_source' ),
		'xbz'  => array( '_dcxbz_source' ),
		'asia' => array( '_dcasia_source' ),
	);
}

/**
 * Marca como "visto agora" todo produto do fornecedor que aparece no catálogo.
 *
 * @param string   $fonte  spot|xbz|asia.
 * @param callable $achou  fn( string $codigo ) : bool.
 */
function dc_forn_marcar_vistos( $fonte, $achou ) {
	global $wpdb;
	$f = dcsc_fontes();
	if ( empty( $f[ $fonte ] ) || ! is_callable( $achou ) ) {
		return 0;
	}
	$in   = "'" . implode( "','", array_map( 'esc_sql', $f[ $fonte ] ) ) . "'";
	$rows = $wpdb->get_results(
		"SELECT p.ID,
		        ( SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = '_sku' LIMIT 1 ) AS sku,
		        ( SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key IN ('_dcspot_ref','_dcasia_ref') LIMIT 1 ) AS ref,
		        ( SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = '_dc_color_stock_table' LIMIT 1 ) AS tab
		   FROM {$wpdb->posts} p
		  WHERE p.post_type = 'product' AND p.post_status = 'publish'
		    AND EXISTS ( SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key IN ( $in ) )"
	); // phpcs:ignore
	$agora = time();
	$n     = 0;
	foreach ( $rows as $r ) {
		$cods = array( $r->sku, $r->ref );
		$tab  = json_decode( (string) $r->tab, true );
		foreach ( is_array( $tab ) ? $tab : array() as $t ) {
			if ( is_array( $t ) && ! empty( $t['sku'] ) ) {
				$cods[] = $t['sku'];
			}
		}
		foreach ( array_filter( $cods ) as $c ) {
			if ( call_user_func( $achou, (string) $c ) ) {
				update_post_meta( (int) $r->ID, '_dc_forn_visto', $agora );
				$n++;
				break;
			}
		}
	}
	if ( ! get_option( 'dc_forn_inicio_' . $fonte ) ) {
		update_option( 'dc_forn_inicio_' . $fonte, $agora, false );
	}
	update_option( 'dc_forn_marcados_' . $fonte, array( 't' => $agora, 'vistos' => $n, 'total' => count( $rows ) ), false );
	return $n;
}

/** O produto sumiu do catálogo do fornecedor há mais de 3 dias? */
function dc_forn_sumido( $pid ) {
	static $cache = array();
	$pid = (int) $pid;
	if ( isset( $cache[ $pid ] ) ) {
		return $cache[ $pid ];
	}
	$cache[ $pid ] = false;
	foreach ( dcsc_fontes() as $fonte => $metas ) {
		$tem = false;
		foreach ( $metas as $m ) {
			if ( '' !== (string) get_post_meta( $pid, $m, true ) ) {
				$tem = true;
				break;
			}
		}
		if ( ! $tem ) {
			continue;
		}
		$ini = (int) get_option( 'dc_forn_inicio_' . $fonte );
		if ( ! $ini ) {
			return $cache[ $pid ];
		}
		$visto         = (int) get_post_meta( $pid, '_dc_forn_visto', true );
		$cache[ $pid ] = ( time() - max( $visto, $ini ) ) > DCSC_DIAS * DAY_IN_SECONDS;
		return $cache[ $pid ];
	}
	return $cache[ $pid ];
}

/** Só nas páginas vistas pelo cliente (não mexe no admin, no orçamento, no carrinho nem nos sincronizadores). */
function dcsc_tela() {
	return ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! ( defined( 'WP_CLI' ) && WP_CLI )
		&& isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'];
}

// Produto sumido: sem número de estoque.
foreach ( array( 'woocommerce_product_get_manage_stock', 'woocommerce_product_variation_get_manage_stock' ) as $hk ) {
	add_filter(
		$hk,
		function ( $v, $p ) {
			return ( $v && dcsc_tela() && is_object( $p ) && dc_forn_sumido( $p->get_id() ) ) ? false : $v;
		},
		99,
		2
	);
}
// Cores: continuam aparecendo (sem número) em vez de sumirem.
add_filter(
	'get_post_metadata',
	function ( $v, $pid, $key, $single ) {
		if ( '_dc_color_stock_table' !== $key || ! dcsc_tela() || ! dc_forn_sumido( $pid ) ) {
			return $v;
		}
		global $wpdb;
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_dc_color_stock_table' LIMIT 1", $pid ) );
		$tab = json_decode( (string) $raw, true );
		if ( ! is_array( $tab ) ) {
			return $v;
		}
		foreach ( $tab as $i => $t ) {
			if ( is_array( $t ) ) {
				$tab[ $i ]['stock'] = 1;
			}
		}
		$GLOBALS['dcsc_na_tela'] = true;
		$j = wp_json_encode( $tab );
		return $single ? array( $j ) : array( $j );
	},
	20,
	4
);

// Na tela: troca os números por "sob consulta".
add_action(
	'wp_footer',
	function () {
		if ( ! dcsc_tela() ) {
			return;
		}
		$ids = array();
		if ( function_exists( 'is_product' ) && is_product() && dc_forn_sumido( get_queried_object_id() ) ) {
			$ids[] = 'p';
		}
		if ( ! $ids ) {
			return;
		}
		?>
<style>.dc-cores .dcc-st{color:#6B7785!important}</style>
<script id="dcsc">(function(){function fx(){document.querySelectorAll(".dc-cores").forEach(function(r){var t=r.querySelector(".dcc-tot");if(t)t.innerHTML="<strong>Estoque sob consulta</strong> &middot; confirmamos a disponibilidade no or&ccedil;amento";r.querySelectorAll(".dcc-st").forEach(function(s){s.textContent="sob consulta";});var c=r.querySelector(".dcc-cur:not(.vazio)");if(c&&/em estoque/i.test(c.textContent))c.textContent=c.textContent.replace(/\s*—\s*em estoque:.*$/i," — estoque sob consulta");});}fx();document.addEventListener("click",function(){setTimeout(fx,0);},true);document.addEventListener("dc:cor",function(){setTimeout(fx,0);});})();</script>
		<?php
	},
	99
);
