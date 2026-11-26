<?php
/**
 * Sacola do topo (widget "Mini Cart" do Premium Addons): mostra o valor real do carrinho em reais (R$ 1.234,56)
 * e a quantidade de itens. A página fica guardada em cache sem carrinho; o valor chega pelo
 * "cart fragments" do WooCommerce, que roda ao abrir a página e a cada item adicionado.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dcmc_valor() {
	// Soma das linhas do carrinho: é a mesma base do "Subtotal" e do "Total" que aparecem no carrinho e no pedido.
	$t = 0;
	if ( function_exists( 'WC' ) && WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $item ) {
			$linha = isset( $item['line_subtotal'] ) ? (float) $item['line_subtotal'] : 0;
			// Produto com tabela de faixas: preço da maior faixa que cabe na quantidade (igual à página do produto).
			if ( function_exists( 'dcprice_qty_tiers_for_product' ) && function_exists( 'dcprice_calculate_with_curve' ) && function_exists( 'dcprice_product_is_priceable' ) && dcprice_product_is_priceable( (int) $item['product_id'] ) ) {
				$qtd    = (int) $item['quantity'];
				$faixas = array_map( 'intval', (array) dcprice_qty_tiers_for_product( (int) $item['product_id'] ) );
				sort( $faixas );
				$usar = $faixas ? $faixas[0] : $qtd;
				foreach ( $faixas as $fx ) {
					if ( $fx > 0 && $fx <= $qtd ) {
						$usar = $fx;
					}
				}
				$r = dcprice_calculate_with_curve( (int) $item['product_id'], max( 1, $usar ) );
				if ( ! is_wp_error( $r ) && isset( $r['unit_price'] ) ) {
					$linha = (float) $r['unit_price'] * $qtd;
				}
			}
			$t += $linha;
		}
	}
	return 'R$ ' . number_format( $t, 2, ',', '.' );
}

function dcmc_qtd() {
	return ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
}

// Recalcula o carrinho antes de montar a sacola e o mini-carrinho (senão eles mostram o preço de vitrine, e não o da faixa).
add_filter( 'woocommerce_add_to_cart_fragments', function ( $f ) {
	if ( function_exists( 'WC' ) && WC()->cart ) {
		WC()->cart->calculate_totals();
	}
	return $f;
}, 1 );

add_filter( 'woocommerce_add_to_cart_fragments', function ( $f ) {
	$n = dcmc_qtd();
	$f['span.pa-woo-mc__subtotal'] = '<span class="pa-woo-mc__subtotal">' . esc_html( dcmc_valor() ) . '</span>';
	$f['span.pa-woo-mc__badge']    = '<span class="pa-woo-mc__badge' . ( $n ? '' : ' pa-hide-badge' ) . '">' . esc_html( number_format( $n, 0, ',', '.' ) ) . '</span>';
	return $f;
}, 99 );

// Na página guardada em cache a sacola vem zerada. Se o navegador tem carrinho (cookie do WooCommerce)
// e a sacola ainda mostra zero, pede os fragments na hora.
add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	?>
<script id="dcmc-topo">(function(){function pedir(){if(!/woocommerce_items_in_cart=1/.test(document.cookie)||!window.jQuery)return;var z=false;document.querySelectorAll('.pa-woo-mc__subtotal').forEach(function(s){if(/^\s*R\$\s?0[.,]00\s*$/.test(s.textContent))z=true;});if(z)jQuery(document.body).trigger('wc_fragment_refresh');}
if(document.readyState!=='loading'){setTimeout(pedir,600);}else{document.addEventListener('DOMContentLoaded',function(){setTimeout(pedir,600);});}})();</script>
	<?php
}, 99 );
