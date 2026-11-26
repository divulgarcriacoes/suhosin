<?php
/**
 * Checkout: botão X em cada linha do "Seu pedido" para tirar o produto sem sair da página.
 * Tira o item pelo link de remoção do WooCommerce (com nonce) e atualiza o resumo e o total.
 * Se o carrinho ficar vazio, vai para o carrinho.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'woocommerce_cart_item_subtotal',
	function ( $html, $item, $key ) {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
			return $html;
		}
		$url = wc_get_cart_remove_url( $key );
		return '<span class="dc-rm-linha">' . $html . '<a href="' . esc_url( $url ) . '" class="dc-rm" role="button" aria-label="Tirar este produto do pedido" title="Tirar este produto">&times;</a></span>';
	},
	99,
	3
);

add_action(
	'wp_head',
	function () {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		echo '<style id="dc-rm-css">.dc-rm-linha{display:inline-flex;align-items:center;gap:10px;white-space:nowrap}'
			. '.dc-rm{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:#f1f1ee;color:#6b6b64!important;font:700 17px/1 Arial,sans-serif;text-decoration:none!important;flex:0 0 26px;transition:background .15s,color .15s}'
			. '.dc-rm:hover{background:#e8412c;color:#fff!important}.dc-rm.dc-rm-on{opacity:.4;pointer-events:none}</style>';
	},
	131
);

add_action(
	'wp_footer',
	function () {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		?>
<script id="dc-rm-js">(function(){document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a.dc-rm');if(!a)return;e.preventDefault();if(a.classList.contains('dc-rm-on'))return;a.classList.add('dc-rm-on');
fetch(a.href,{credentials:'same-origin',redirect:'follow'}).then(function(){var restam=document.querySelectorAll('#order_review tr.cart_item').length-1;if(restam<1){location.href=<?php echo wp_json_encode( wc_get_cart_url() ); ?>;return;}
if(window.jQuery){jQuery(document.body).trigger('update_checkout');jQuery(document.body).trigger('wc_fragment_refresh');}else{location.reload();}}).catch(function(){location.href=a.href;});});})();</script>
		<?php
	},
	99
);
