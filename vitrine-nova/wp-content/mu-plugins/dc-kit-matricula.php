<?php
/**
 * Kit Matrícula: produto KITMAT na vitrine das escolas (pasta escolar sublimada + squeeze + bloco com autoadesivos).
 * O logo/arte de cada escola entra pelo molde do Corel enviado para o SKU KITMAT (uma área vermelha em cada item).
 *
 * Preço fixo por faixa de quantidade (26/11/2026, combinado com o Walter):
 *   custo por kit = squeeze 1,98 + bloco 2,80 + pasta 6,00 = 10,78, + 12% de gravação = 12,07
 *   preço base (25 kits) = custo x 1,5 (50% Divulgar) / (1 - 20% parceira - 8% imposto) = 25,15
 *   5 kits +30% · 10 kits +20% · 15 kits +10% · 25 kits base · 50 kits -5%
 * Regra geral: o imposto (8%) vai sempre embutido no preço.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DCKIT_SKU = 'KITMAT';
const DCKIT_MIN = 5;

/** Faixas: quantidade mínima da faixa => preço por kit. */
function dckit_tabela() {
	return array(
		5  => 32.70,
		10 => 30.18,
		15 => 27.67,
		25 => 25.15,
		50 => 23.90,
	);
}

function dckit_pid() {
	static $id = null;
	if ( null === $id ) {
		$id = function_exists( 'wc_get_product_id_by_sku' ) ? (int) wc_get_product_id_by_sku( DCKIT_SKU ) : 0;
	}
	return $id;
}

function dckit_eh_kit( $pid ) {
	return $pid && (int) $pid === dckit_pid();
}

/** Preço por kit na quantidade pedida (faixa mais alta que a quantidade alcança). */
function dckit_unit( $qty ) {
	$qty = (int) $qty;
	$u   = null;
	foreach ( dckit_tabela() as $min => $preco ) {
		if ( $qty >= $min ) {
			$u = $preco;
		}
	}
	return $u;
}

/* Pedido mínimo do kit = 5: entra como regra própria (SKU KITMAT) na frente das "Regras de preço" da loja,
   que é de onde o plugin de pedido mínimo tira o mínimo de cada produto. Não muda nenhum outro produto. */
add_filter( 'option_dcprr_regras', function ( $r ) {
	$r = is_array( $r ) ? $r : array();
	foreach ( $r as $x ) {
		if ( isset( $x['skus'] ) && false !== stripos( (string) $x['skus'], DCKIT_SKU ) ) {
			return $r;
		}
	}
	array_unshift( $r, array( 'ativo' => 1, 'nome' => 'Kit Matrícula', 'skus' => DCKIT_SKU, 'cats' => array(), 'minimo' => (string) DCKIT_MIN ) );
	return $r;
} );

/* ------------------------------------------------------------ produto */

// Cria o produto uma vez (o SKU e o preço não podem ser gravados pelas ferramentas de post).
add_action( 'init', function () {
	if ( ! function_exists( 'wc_get_product_id_by_sku' ) || get_option( 'dckit_criado' ) || dckit_pid() ) {
		return;
	}
	update_option( 'dckit_criado', time(), false );
	$p = new WC_Product_Simple();
	$p->set_name( 'Kit Matrícula' );
	$p->set_status( 'publish' );
	$p->set_sku( DCKIT_SKU );
	$p->set_catalog_visibility( 'visible' );
	$p->set_short_description( 'Kit de boas-vindas para os alunos novos, com a marca da sua escola: pasta escolar sublimada, squeeze e bloco de anotações com autoadesivos.' );
	$p->set_description( '<p><strong>Kit Matrícula personalizado com a marca da sua escola.</strong></p><ul><li>Pasta escolar sublimada com a arte da escola</li><li>Squeeze com o logo</li><li>Bloco de anotações com autoadesivos com o logo</li></ul><p>A partir de ' . DCKIT_MIN . ' kits. Quanto mais kits, menor o preço por kit.</p>' );
	$p->set_category_ids( array( 1456, 1457, 1458, 1469, 1471 ) );
	$img = (int) get_post_thumbnail_id( 6753 );
	if ( $img ) {
		$p->set_image_id( $img );
	}
	$p->set_regular_price( (string) dckit_unit( DCKIT_MIN ) );
	$p->save();
}, 40 );

// A calculadora padrão do motor de preços não serve para o kit: usamos a nossa tabela.
add_action( 'wp', function () {
	if ( function_exists( 'is_product' ) && is_product() && dckit_eh_kit( get_queried_object_id() ) ) {
		remove_action( 'woocommerce_before_add_to_cart_button', 'dcprice_render_calculator', 15 );
	}
} );

add_action( 'woocommerce_before_add_to_cart_button', function () {
	global $product;
	if ( ! $product instanceof WC_Product || ! dckit_eh_kit( $product->get_id() ) ) {
		return;
	}
	$f = function ( $v ) {
		return 'R$ ' . number_format( (float) $v, 2, ',', '.' );
	};
	?>
	<div class="dckit" style="border:1px solid #E4E4E0;border-radius:10px;padding:18px 20px;margin:12px 0 18px;background:#fff">
		<p style="margin:0 0 8px;font-weight:700;color:#1C1C1A;font-size:15px">Escolha a quantidade de kits</p>
		<table style="width:100%;border-collapse:collapse;font-size:14px">
			<thead><tr style="background:#F4F4F2"><th style="padding:8px;width:36px"></th><th style="padding:8px;text-align:left">Kits</th><th style="padding:8px;text-align:right">Valor por kit</th><th style="padding:8px;text-align:right">Valor total</th></tr></thead>
			<tbody>
			<?php $k = 0; foreach ( dckit_tabela() as $q => $u ) : ?>
				<tr data-q="<?php echo (int) $q; ?>" style="border-top:1px solid #E4E4E0;cursor:pointer">
					<td style="padding:8px;text-align:center"><input type="radio" name="dckit_q" value="<?php echo (int) $q; ?>" <?php checked( 0, $k ); ?>></td>
					<td style="padding:8px"><?php echo (int) $q; ?> kits</td>
					<td style="padding:8px;text-align:right"><?php echo esc_html( $f( $u ) ); ?></td>
					<td style="padding:8px;text-align:right"><?php echo esc_html( $f( $u * $q ) ); ?></td>
				</tr>
			<?php $k++; endforeach; ?>
			</tbody>
		</table>
		<p style="font-size:12px;color:#6B6B64;margin:10px 0 0">Pedido mínimo: <?php echo (int) DCKIT_MIN; ?> kits. Outras quantidades: digite no campo abaixo (vale o preço da faixa alcançada).</p>
	</div>
	<script>
	document.addEventListener("DOMContentLoaded",function(){
		var box=document.querySelector(".dckit");if(!box)return;
		var qtd=document.querySelector("form.cart input.qty")||document.querySelector("input.qty"),MIN=<?php echo (int) DCKIT_MIN; ?>;
		if(qtd){qtd.min=String(MIN);if(parseInt(qtd.value||"0",10)<MIN)qtd.value=String(MIN);}
		var trs=box.querySelectorAll("tr[data-q]");function marca(){if(!qtd)return;var v=parseInt(qtd.value||"0",10),m=null;trs.forEach(function(tr){if(v>=parseInt(tr.getAttribute("data-q"),10))m=tr;});trs.forEach(function(tr){tr.querySelector("input").checked=(tr===m);tr.style.background=tr===m?"#FFF4E8":"";});}trs.forEach(function(tr){tr.addEventListener("click",function(){if(qtd){qtd.value=tr.getAttribute("data-q");qtd.dispatchEvent(new Event("input",{bubbles:true}));qtd.dispatchEvent(new Event("change",{bubbles:true}));}marca();});});if(qtd){qtd.addEventListener("input",marca);qtd.addEventListener("change",marca);}marca();
	});
	</script>
	<?php
}, 15 );

/* ------------------------------------------------------------ carrinho */

add_filter( 'woocommerce_add_to_cart_validation', function ( $ok, $pid, $qty ) {
	if ( dckit_eh_kit( $pid ) && (int) $qty < DCKIT_MIN ) {
		wc_add_notice( 'O Kit Matrícula tem pedido mínimo de ' . DCKIT_MIN . ' kits.', 'error' );
		return false;
	}
	return $ok;
}, 20, 3 );

add_filter( 'woocommerce_update_cart_validation', function ( $ok, $key, $values, $qty ) {
	if ( isset( $values['product_id'] ) && dckit_eh_kit( $values['product_id'] ) && (int) $qty < DCKIT_MIN ) {
		wc_add_notice( 'O Kit Matrícula tem pedido mínimo de ' . DCKIT_MIN . ' kits.', 'error' );
		return false;
	}
	return $ok;
}, 20, 4 );

add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	foreach ( $cart->get_cart() as $item ) {
		if ( empty( $item['product_id'] ) || ! dckit_eh_kit( $item['product_id'] ) ) {
			continue;
		}
		$u = dckit_unit( (int) $item['quantity'] );
		if ( $u ) {
			$item['data']->set_price( (string) $u );
		}
	}
}, 99 );

// Mini-carrinho e carrinho: mostra o preço por kit da faixa (e não o preço de vitrine).
add_filter( 'woocommerce_cart_item_price', function ( $html, $item ) {
	if ( ! empty( $item['product_id'] ) && dckit_eh_kit( $item['product_id'] ) ) {
		$u = dckit_unit( (int) $item['quantity'] );
		if ( $u ) {
			return wc_price( $u );
		}
	}
	return $html;
}, 99, 2 );
