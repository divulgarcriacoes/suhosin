<?php
/**
 * Kit Matrícula: produto KITMAT na vitrine das escolas.
 * A bolsa é escolhida pelo cliente (sacola TNT ou mochila saco). Squeeze dobrável e bloco com caneta são fixos.
 * Preço do kit = soma dos itens na quantidade pedida, pelo motor de preços (dcprice_calculate_with_curve).
 * O logo de cada escola entra pelo molde do Corel enviado para o SKU KITMAT (uma área vermelha em cada item).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DCKIT_SKU = 'KITMAT';
const DCKIT_MIN = 250;

function dckit_opcoes() {
	return array(
		'sacola'  => array( 'nome' => 'Sacola TNT', 'itens' => array( 40853, 5909, 6753 ) ),
		'mochila' => array( 'nome' => 'Mochila saco TNT', 'itens' => array( 80545, 5909, 6753 ) ),
	);
}

function dckit_qtds() {
	return array( 250, 500, 1000, 2500, 5000 );
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

/** Preço unitário do kit (opção + quantidade). null se algum item não tiver preço. */
function dckit_unit( $op, $qty ) {
	$ops = dckit_opcoes();
	if ( ! isset( $ops[ $op ] ) || ! function_exists( 'dcprice_calculate_with_curve' ) ) {
		return null;
	}
	$qty = max( DCKIT_MIN, (int) $qty );
	$par = function_exists( 'dcprice_partner_ctx' ) && dcprice_partner_ctx() ? 'p' : 'd';
	$ck  = 'dckit_' . $op . '_' . $qty . '_' . $par;
	$c   = get_transient( $ck );
	if ( false !== $c ) {
		return '' === $c ? null : (float) $c;
	}
	$soma = 0.0;
	foreach ( $ops[ $op ]['itens'] as $item ) {
		$r = dcprice_calculate_with_curve( $item, $qty );
		if ( is_wp_error( $r ) || empty( $r['unit_price'] ) ) {
			set_transient( $ck, '', HOUR_IN_SECONDS );
			return null;
		}
		$soma += (float) $r['unit_price'];
	}
	$soma = round( $soma, 2 );
	set_transient( $ck, $soma, 6 * HOUR_IN_SECONDS );
	return $soma;
}

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
	$p->set_short_description( 'Kit de boas-vindas para os alunos novos, com o logo da sua escola: bolsa (sacola TNT ou mochila saco), squeeze dobrável 480 ml e bloco de anotações com caneta em kraft.' );
	$p->set_description( '<p><strong>Kit Matrícula personalizado com o logo da sua escola.</strong></p><ul><li>Bolsa: sacola TNT ou mochila saco TNT (você escolhe)</li><li>Squeeze dobrável 480 ml</li><li>Bloco de anotações com autoadesivos e caneta em kraft</li></ul><p>Pedido mínimo: ' . DCKIT_MIN . ' kits. O preço por kit diminui conforme a quantidade.</p>' );
	$p->set_category_ids( array( 1456, 1457, 1458, 1469, 1471 ) );
	$img = (int) get_post_thumbnail_id( 40853 );
	if ( $img ) {
		$p->set_image_id( $img );
	}
	$u = dckit_unit( 'sacola', DCKIT_MIN );
	if ( $u ) {
		$p->set_regular_price( (string) $u );
	}
	$p->save();
}, 40 );

// A calculadora padrão do motor de preços não serve para o kit: usamos a nossa.
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
	$tab = array();
	foreach ( dckit_opcoes() as $op => $o ) {
		foreach ( dckit_qtds() as $q ) {
			$tab[ $op ][ $q ] = dckit_unit( $op, $q );
		}
	}
	$f = function ( $v ) {
		return null === $v ? '—' : 'R$ ' . number_format( (float) $v, 2, ',', '.' );
	};
	?>
	<div class="dckit" style="border:1px solid #E4E4E0;border-radius:10px;padding:18px 20px;margin:12px 0 18px;background:#fff">
		<p style="margin:0 0 10px;font-weight:700;color:#1C1C1A;font-size:15px">Escolha a bolsa do kit</p>
		<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
			<?php $i = 0; foreach ( dckit_opcoes() as $op => $o ) : ?>
				<label style="display:flex;align-items:center;gap:8px;border:1.5px solid #E4E4E0;border-radius:10px;padding:10px 14px;cursor:pointer">
					<input type="radio" name="dckit_op" value="<?php echo esc_attr( $op ); ?>" <?php checked( 0, $i ); ?>>
					<span style="font-weight:600"><?php echo esc_html( $o['nome'] ); ?></span>
				</label>
			<?php $i++; endforeach; ?>
		</div>
		<p style="margin:0 0 8px;font-weight:700;color:#1C1C1A;font-size:15px">Escolha a quantidade de kits</p>
		<table class="dckit-tab" style="width:100%;border-collapse:collapse;font-size:14px">
			<thead><tr style="background:#F4F4F2"><th style="padding:8px;width:36px"></th><th style="padding:8px;text-align:left">Kits</th><th style="padding:8px;text-align:right">Valor por kit</th><th style="padding:8px;text-align:right">Valor total</th></tr></thead>
			<tbody>
			<?php foreach ( dckit_qtds() as $k => $q ) : ?>
				<tr data-q="<?php echo (int) $q; ?>" style="border-top:1px solid #E4E4E0;cursor:pointer">
					<td style="padding:8px;text-align:center"><input type="radio" name="dckit_q" value="<?php echo (int) $q; ?>" <?php checked( 0, $k ); ?>></td>
					<td style="padding:8px"><?php echo esc_html( number_format( $q, 0, ',', '.' ) ); ?> kits</td>
					<?php foreach ( dckit_opcoes() as $op => $o ) : ?>
						<td class="dckit-u" data-op="<?php echo esc_attr( $op ); ?>" style="padding:8px;text-align:right"><?php echo esc_html( $f( $tab[ $op ][ $q ] ) ); ?></td>
						<td class="dckit-t" data-op="<?php echo esc_attr( $op ); ?>" style="padding:8px;text-align:right"><?php echo esc_html( null === $tab[ $op ][ $q ] ? '—' : $f( $tab[ $op ][ $q ] * $q ) ); ?></td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p style="font-size:12px;color:#6B6B64;margin:10px 0 0">Pedido mínimo: <?php echo (int) DCKIT_MIN; ?> kits. Outras quantidades: digite no campo abaixo.</p>
	</div>
	<script>
	(function(){
		var box=document.querySelector(".dckit");if(!box)return;
		var form=box.closest("form"),qtd=form?form.querySelector("input.qty"):null;
		function op(){var r=box.querySelector("input[name=dckit_op]:checked");return r?r.value:"sacola";}
		function mostra(){var o=op();box.querySelectorAll(".dckit-u,.dckit-t").forEach(function(td){td.style.display=td.getAttribute("data-op")===o?"":"none";});}
		if(qtd){qtd.min="<?php echo (int) DCKIT_MIN; ?>";if(parseInt(qtd.value||"0",10)<<?php echo (int) DCKIT_MIN; ?>)qtd.value="<?php echo (int) DCKIT_MIN; ?>";}
		box.querySelectorAll("input[name=dckit_op]").forEach(function(r){r.addEventListener("change",mostra);});
		box.querySelectorAll("tr[data-q]").forEach(function(tr){tr.addEventListener("click",function(){var r=tr.querySelector("input");r.checked=true;if(qtd){qtd.value=tr.getAttribute("data-q");qtd.dispatchEvent(new Event("change",{bubbles:true}));}});});
		box.querySelectorAll("thead th").forEach(function(th,i){if(i>3)th.style.display="none";});
		mostra();
	})();
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

add_filter( 'woocommerce_add_cart_item_data', function ( $data, $pid ) {
	if ( dckit_eh_kit( $pid ) ) {
		$op              = isset( $_POST['dckit_op'] ) ? sanitize_key( wp_unslash( $_POST['dckit_op'] ) ) : 'sacola';
		$data['dckit_op'] = array_key_exists( $op, dckit_opcoes() ) ? $op : 'sacola';
	}
	return $data;
}, 10, 2 );

add_filter( 'woocommerce_get_item_data', function ( $out, $item ) {
	if ( ! empty( $item['dckit_op'] ) ) {
		$ops   = dckit_opcoes();
		$out[] = array( 'name' => 'Bolsa', 'value' => $ops[ $item['dckit_op'] ]['nome'] ?? $item['dckit_op'] );
	}
	return $out;
}, 10, 2 );

add_action( 'woocommerce_checkout_create_order_line_item', function ( $line, $key, $values ) {
	if ( ! empty( $values['dckit_op'] ) ) {
		$ops = dckit_opcoes();
		$line->add_meta_data( 'Bolsa', $ops[ $values['dckit_op'] ]['nome'] ?? $values['dckit_op'], true );
	}
}, 10, 3 );

add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	foreach ( $cart->get_cart() as $item ) {
		if ( empty( $item['product_id'] ) || ! dckit_eh_kit( $item['product_id'] ) ) {
			continue;
		}
		$u = dckit_unit( $item['dckit_op'] ?? 'sacola', (int) $item['quantity'] );
		if ( $u ) {
			$item['data']->set_price( (string) $u );
		}
	}
}, 99 );
