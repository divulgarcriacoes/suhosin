<?php
/**
 * Plugin Name: DC - Pedido Mínimo por Categoria
 * Description: Define quantidade mínima de pedido no WooCommerce: 25 unidades por padrão, 100 unidades para Canetas e Chaveiros (e subcategorias). Aplica no campo de quantidade do produto e bloqueia tentativas de burlar via carrinho/checkout.
 * Version: 1.5.0
 * Author: Divulgar Criações
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'DC_Min_Order_Qty' ) ) :

class DC_Min_Order_Qty {

	const OPTION_KEY = 'dc_moq_settings';

	public static function init() {
		add_filter( 'woocommerce_quantity_input_args', array( __CLASS__, 'quantity_input_args' ), 20, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'add_to_cart_validation' ), 20, 3 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_cart_items' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_min_qty_notice' ), 5 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'wp_footer', array( __CLASS__, 'print_variation_reset_script' ) );
		add_filter( 'woocommerce_variation_is_purchasable', array( __CLASS__, 'variation_is_purchasable' ), 20, 2 );
		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'product_is_purchasable' ), 20, 2 );
		add_filter( 'woocommerce_product_is_in_stock', array( __CLASS__, 'product_is_in_stock' ), 20, 2 );
	}

	/**
	 * Estoque menor que o pedido mínimo = na prática indisponível pra venda
	 * (não dá pra vender abaixo do mínimo mesmo). Trata como fora de estoque
	 * em vez de deixar comprar uma quantidade menor que o permitido.
	 */
	protected static function below_min_stock( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		if ( ! $product->managing_stock() ) {
			return false;
		}
		$stock = $product->get_stock_quantity();
		if ( null === $stock ) {
			return false;
		}
		$product_id_for_rule = $product->get_type() === 'variation' ? $product->get_parent_id() : $product->get_id();
		$min = self::min_qty_for_product( $product_id_for_rule );
		return $stock < $min;
	}

	public static function variation_is_purchasable( $purchasable, $variation ) {
		if ( $purchasable && self::below_min_stock( $variation ) ) {
			return false;
		}
		return $purchasable;
	}

	public static function product_is_purchasable( $purchasable, $product ) {
		if ( $purchasable && self::below_min_stock( $product ) ) {
			return false;
		}
		return $purchasable;
	}

	public static function product_is_in_stock( $in_stock, $product ) {
		if ( $in_stock && self::below_min_stock( $product ) ) {
			return false;
		}
		return $in_stock;
	}

	/**
	 * Ao trocar de variação (cor), o WooCommerce atualiza o atributo "min" do
	 * campo de quantidade mas NÃO corrige o valor já digitado — então quem
	 * escolhe uma cor com pouco estoque (campo cai pro estoque disponível,
	 * ex: 5) e depois troca pra uma cor com estoque de sobra, o campo fica
	 * preso no valor antigo (5) em vez de voltar pro mínimo da categoria.
	 * Este script corrige isso: sempre que uma variação é selecionada, se o
	 * valor atual for menor que o novo mínimo, sobe pro mínimo.
	 */
	public static function print_variation_reset_script() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		?>
		<script>
		(function ($) {
			if (typeof $ === 'undefined') {
				return;
			}
			$(document).on('found_variation', 'form.variations_form', function (event, variation) {
				var $qty = $(this).find('input.qty[name="quantity"]');
				if (!$qty.length) {
					return;
				}
				var min = parseInt($qty.attr('min'), 10) || 1;
				var current = parseInt($qty.val(), 10) || 0;
				if (current < min) {
					$qty.val(min).trigger('change');
				}
			});
		})(window.jQuery);
		</script>
		<?php
	}

	protected static function get_settings() {
		$defaults = array(
			'default_min' => 15,
			'category_overrides' => array(
				'canetas-brindes'   => 50,
				'chaveiros-brindes' => 50,
			),
		);
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		// Migração pontual: baixa o mínimo padrão de 25→15 e canetas/chaveiros
		// de 100→50, só na primeira vez (não sobrescreve se você já mudou
		// esses valores manualmente na tela de configurações depois).
		if ( empty( $saved['migrated_min_v2'] ) ) {
			$saved['default_min']       = 15;
			$saved['category_overrides'] = array(
				'canetas-brindes'   => 50,
				'chaveiros-brindes' => 50,
			);
			$saved['migrated_min_v2']   = true;
			update_option( self::OPTION_KEY, $saved );
		}
		return wp_parse_args( $saved, $defaults );
	}

	protected static function category_term_ids_with_descendants( $slug ) {
		static $cache = array();
		if ( isset( $cache[ $slug ] ) ) {
			return $cache[ $slug ];
		}
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return $cache[ $slug ] = array();
		}
		$ids = array( (int) $term->term_id );
		$children = get_term_children( $term->term_id, 'product_cat' );
		if ( ! is_wp_error( $children ) ) {
			foreach ( $children as $child_id ) {
				$ids[] = (int) $child_id;
			}
		}
		return $cache[ $slug ] = $ids;
	}

	protected static function supplier_unit_cost( $product_id ) {
		if ( 'spot' === get_post_meta( $product_id, '_dcspot_source', true ) ) {
			if ( function_exists( 'dcprice_load_cache' ) && function_exists( 'dcprice_tier_price' ) ) {
				$ref   = get_post_meta( $product_id, '_dcspot_ref', true );
				$cache = dcprice_load_cache();
				if ( $ref && ! empty( $cache['cost'][ $ref ] ) ) {
					return (float) dcprice_tier_price( $cache['cost'][ $ref ], 1 );
				}
			}
			return null;
		}
		if ( function_exists( 'dcprice_load_xbz_asia_cache' ) ) {
			$xa  = dcprice_load_xbz_asia_cache();
			$sku = (string) get_post_meta( $product_id, '_sku', true );
			if ( 'xbz' === get_post_meta( $product_id, '_dcxbz_source', true ) && isset( $xa['xbz'][ $sku ] ) ) {
				return (float) $xa['xbz'][ $sku ];
			}
			if ( 'asia' === get_post_meta( $product_id, '_dcasia_source', true ) && isset( $xa['asia'][ $sku ] ) ) {
				return (float) $xa['asia'][ $sku ];
			}
		}
		return null;
	}

	public static function min_qty_for_product( $product_id ) {
		static $cache = array();
		$product_id = (int) $product_id;
		if ( isset( $cache[ $product_id ] ) ) {
			return $cache[ $product_id ];
		}
		$settings = self::get_settings();
		$min = (int) $settings['default_min'];
		foreach ( (array) $settings['category_overrides'] as $slug => $qty ) {
			$ids = self::category_term_ids_with_descendants( $slug );
			if ( ! empty( $ids ) && has_term( $ids, 'product_cat', $product_id ) ) {
				$min = max( $min, (int) $qty );
			}
		}

		// Custo do fornecedor < R$1,50 (qualquer fornecedor): mínimo 250.
		// (A regra antiga "Spot < R$6,00 → 100" foi removida: o preço Spot
		// agora segue o % da Omnitek, sem a distorção por faixa.)
		$low_cost = self::supplier_unit_cost( $product_id );
		if ( null !== $low_cost && $low_cost < 1.50 ) {
			$min = max( $min, 250 );
		}

		return $cache[ $product_id ] = $min;
	}

	public static function quantity_input_args( $args, $product ) {
		if ( ! $product ) {
			return $args;
		}
		$min = self::min_qty_for_product( $product->get_id() );
		$args['min_value'] = max( (int) $args['min_value'], $min );
		if ( (int) $args['input_value'] < $min ) {
			$args['input_value'] = $min;
		}
		if ( empty( $args['step'] ) ) {
			$args['step'] = 1;
		}
		return $args;
	}

	public static function render_min_qty_notice() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		// A calculadora de preço (dc-price-engine) já mostra a quantidade
		// mínima como a primeira linha da tabela de faixas — não repete aqui.
		if ( function_exists( 'dcprice_product_is_priceable' ) && dcprice_product_is_priceable( $product->get_id() ) ) {
			return;
		}
		static $printed = array();
		$product_id = $product->get_id();
		if ( ! empty( $printed[ $product_id ] ) ) {
			return;
		}
		$printed[ $product_id ] = true;
		$min = self::min_qty_for_product( $product_id );
		printf(
			'<p class="dc-moq-notice" style="margin:6px 0 12px;font-weight:600;">%s</p>',
			esc_html( sprintf( __( 'QUANTIDADE MÍNIMA: %d unidades', 'dc-moq' ), $min ) )
		);
	}

	public static function add_to_cart_validation( $passed, $product_id, $quantity ) {
		$min = self::min_qty_for_product( $product_id );
		if ( $quantity < $min ) {
			wc_add_notice(
				sprintf( __( 'Quantidade mínima para este produto: %d unidades.', 'dc-moq' ), $min ),
				'error'
			);
			return false;
		}
		return $passed;
	}

	public static function check_cart_items() {
		if ( ! WC()->cart ) {
			return;
		}
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = $cart_item['product_id'];
			$min = self::min_qty_for_product( $product_id );
			if ( $cart_item['quantity'] < $min ) {
				$product = wc_get_product( $product_id );
				$name = $product ? $product->get_name() : '';
				wc_add_notice(
					sprintf(
						__( 'A quantidade mínima para "%1$s" é %2$d unidades. Ajuste a quantidade para continuar.', 'dc-moq' ),
						$name,
						$min
					),
					'error'
				);
			}
		}
	}

	public static function admin_menu() {
		add_submenu_page(
			'woocommerce',
			'Pedido Mínimo por Categoria',
			'Pedido Mínimo',
			'manage_woocommerce',
			'dc-moq-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = self::get_settings();

		if ( isset( $_POST['dc_moq_save'] ) && check_admin_referer( 'dc_moq_save_settings' ) ) {
			$default_min = max( 1, (int) $_POST['dc_moq_default_min'] );

			$overrides = array();
			if ( ! empty( $_POST['dc_moq_cat_slug'] ) && is_array( $_POST['dc_moq_cat_slug'] ) ) {
				$slugs = wp_unslash( $_POST['dc_moq_cat_slug'] );
				$qtys  = wp_unslash( $_POST['dc_moq_cat_qty'] );
				foreach ( $slugs as $i => $slug ) {
					$slug = sanitize_title( $slug );
					$qty  = isset( $qtys[ $i ] ) ? (int) $qtys[ $i ] : 0;
					if ( $slug && $qty > 0 ) {
						$overrides[ $slug ] = $qty;
					}
				}
			}

			update_option( self::OPTION_KEY, array(
				'default_min' => $default_min,
				'category_overrides' => $overrides,
			) );

			echo '<div class="updated notice"><p>Configurações salvas.</p></div>';
			$settings = self::get_settings();
		}

		$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		?>
		<div class="wrap">
			<h1>Pedido Mínimo por Categoria</h1>
			<form method="post">
				<?php wp_nonce_field( 'dc_moq_save_settings' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="dc_moq_default_min">Mínimo padrão (todas as categorias)</label></th>
						<td><input type="number" min="1" id="dc_moq_default_min" name="dc_moq_default_min" value="<?php echo esc_attr( $settings['default_min'] ); ?>" class="small-text" /> unidades</td>
					</tr>
				</table>

				<h2>Exceções por categoria</h2>
				<p>Categoria (slug) → quantidade mínima. Ex: <code>canetas-brindes</code> → <code>100</code>. Vale também para as subcategorias.</p>
				<table class="widefat" id="dc-moq-overrides" style="max-width:600px;">
					<thead><tr><th>Categoria</th><th>Mínimo</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $settings['category_overrides'] as $slug => $qty ) : ?>
						<tr>
							<td>
								<select name="dc_moq_cat_slug[]">
									<?php foreach ( $cats as $cat ) : ?>
										<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( $cat->slug, $slug ); ?>><?php echo esc_html( $cat->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><input type="number" min="1" name="dc_moq_cat_qty[]" value="<?php echo esc_attr( $qty ); ?>" class="small-text" /></td>
							<td><button type="button" class="button dc-moq-remove-row">Remover</button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="dc-moq-add-row">+ Adicionar categoria</button></p>

				<?php submit_button( 'Salvar', 'primary', 'dc_moq_save' ); ?>
			</form>
		</div>
		<script>
		(function(){
			var tbody = document.querySelector('#dc-moq-overrides tbody');
			var catOptions = <?php echo wp_json_encode( array_map( function( $c ) { return array( 'slug' => $c->slug, 'name' => $c->name ); }, $cats ) ); ?>;

			document.getElementById('dc-moq-add-row').addEventListener('click', function(){
				var tr = document.createElement('tr');
				var opts = catOptions.map(function(c){ return '<option value="'+c.slug+'">'+c.name+'</option>'; }).join('');
				tr.innerHTML = '<td><select name="dc_moq_cat_slug[]">'+opts+'</select></td>'+
					'<td><input type="number" min="1" name="dc_moq_cat_qty[]" value="100" class="small-text" /></td>'+
					'<td><button type="button" class="button dc-moq-remove-row">Remover</button></td>';
				tbody.appendChild(tr);
			});

			tbody.addEventListener('click', function(e){
				if (e.target.classList.contains('dc-moq-remove-row')) {
					e.target.closest('tr').remove();
				}
			});
		})();
		</script>
		<?php
	}
}

DC_Min_Order_Qty::init();

endif;
