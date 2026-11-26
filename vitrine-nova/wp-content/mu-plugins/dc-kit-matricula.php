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

/* ------------------------------------------------------------ Personalize seu produto (painel da escola) */

/* Dados do pedido do kit para o criador (wp-content/uploads/dcvx-criador.js): faixas, mínimo 5 e a ação dckit_pedir. */
add_action( 'woocommerce_account_dcomp-artes_endpoint', function () {
	if ( ! function_exists( 'dcomp_fr' ) || ! dcomp_fr() ) {
		return;
	}
	$pid = dckit_pid();
	$pr  = $pid ? wc_get_product( $pid ) : null;
	$out = array( 'ok' => false );
	if ( $pr && $pr->is_purchasable() && function_exists( 'dcvx_png_salvar' ) ) {
		$fx = array();
		foreach ( dckit_tabela() as $q => $p ) {
			$fx[] = array( 'q' => (int) $q, 'p' => (float) $p );
		}
		$out = array(
			'ok'     => true,
			'url'    => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( 'dckit_pedir' ),
			'action' => 'dckit_pedir',
			'preco'  => (float) dckit_unit( DCKIT_MIN ),
			'min'    => DCKIT_MIN,
			'faixas' => $fx,
		);
	}
	echo '<script>window.DCVX_PEDIR_KIT=' . wp_json_encode( $out, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';</script>'; // phpcs:ignore
}, 9 );

/* Imagem enviada pela escola (JPG, PNG ou WEBP, até 15 MB e 12000 px). Sem arquivo ou arquivo inválido: 0. */
function dckit_img_salvar( $campo, $nome ) {
	if ( empty( $_FILES[ $campo ] ) || ! empty( $_FILES[ $campo ]['error'] ) ) { // phpcs:ignore
		return 0;
	}
	$f = $_FILES[ $campo ]; // phpcs:ignore
	if ( (int) $f['size'] < 100 || (int) $f['size'] > 15 * 1024 * 1024 ) {
		return 0;
	}
	$info = getimagesize( $f['tmp_name'] );
	$ext  = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' );
	if ( ! $info || ! isset( $ext[ $info['mime'] ] ) || $info[0] > 12000 || $info[1] > 12000 ) {
		return 0;
	}
	$_FILES[ $campo ]['name'] = $nome . '-' . wp_generate_password( 12, false, false ) . '.' . $ext[ $info['mime'] ]; // phpcs:ignore
	// Rápido: guarda só o arquivo original, sem gerar as miniaturas do WordPress.
	add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array', 999 );
	add_filter( 'big_image_size_threshold', '__return_false', 999 );
	$id = media_handle_upload( $campo, 0 );
	remove_filter( 'intermediate_image_sizes_advanced', '__return_empty_array', 999 );
	remove_filter( 'big_image_size_threshold', '__return_false', 999 );
	return is_wp_error( $id ) ? 0 : (int) $id;
}

/* Botão "Pedir estes kits": guarda a arte da pasta e a prévia do kit e coloca o KITMAT no carrinho (mesmo formato dos outros pedidos do criador). */
add_action( 'wp_ajax_dckit_pedir', function () {
	if ( ! isset( $_POST['n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['n'] ) ), 'dckit_pedir' ) ) { // phpcs:ignore
		wp_send_json_error( array( 'msg' => 'Sessão expirada. Recarregue a página e tente de novo.' ), 403 );
	}
	if ( ! is_user_logged_in() || ! function_exists( 'dcvx_png_salvar' ) ) {
		wp_send_json_error( array( 'msg' => 'Entre na sua conta para pedir os kits.' ), 403 );
	}
	$uid = get_current_user_id();
	if ( get_transient( 'dcvx_p_' . $uid ) ) {
		wp_send_json_error( array( 'msg' => 'Aguarde alguns segundos e tente de novo.' ), 429 );
	}
	set_transient( 'dcvx_p_' . $uid, 1, 5 );
	$qtd = isset( $_POST['qtd'] ) ? absint( $_POST['qtd'] ) : DCKIT_MIN; // phpcs:ignore
	$qtd = max( DCKIT_MIN, min( 5000, $qtd ) );
	$curso = isset( $_POST['curso'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['curso'] ) ), 0, 120 ) : ''; // phpcs:ignore
	$pid = dckit_pid();
	$pr  = $pid ? wc_get_product( $pid ) : null;
	if ( ! $pr || ! $pr->is_purchasable() ) {
		wp_send_json_error( array( 'msg' => 'O Kit Matrícula ainda não está disponível para pedido.' ), 400 );
	}
	$arte = dckit_img_salvar( 'arte', 'arte-kit-pasta' );
	if ( ! $arte ) {
		wp_send_json_error( array( 'msg' => 'Não foi possível receber a arte. Tente de novo.' ), 400 );
	}
	$previa = dckit_img_salvar( 'previa', 'previa-kit' );
	if ( ! $previa ) {
		wp_delete_attachment( $arte, true );
		wp_send_json_error( array( 'msg' => 'Não foi possível receber a prévia. Tente de novo.' ), 400 );
	}
	// Arquivos que a escola importou no montador (imagem de fundo e logo), no tamanho original.
	$fundo = dckit_img_salvar( 'fundo', 'kit-fundo-enviado' );
	$logo  = dckit_img_salvar( 'logo_env', 'kit-logo-enviado' );
	if ( null === WC()->cart ) {
		wc_load_cart();
	}
	$key = WC()->cart->add_to_cart( $pid, $qtd, 0, array(), array( 'dcvx' => array( 'arte' => $arte, 'previa' => $previa, 'curso' => $curso, 'prod' => 'kit', 'fundo' => $fundo, 'logo' => $logo ) ) );
	if ( ! $key ) {
		wp_delete_attachment( $arte, true );
		wp_delete_attachment( $previa, true );
		wp_send_json_error( array( 'msg' => 'Não foi possível colocar o kit no carrinho.' ), 400 );
	}
	wp_send_json_success( array( 'url' => wc_get_cart_url() ) );
} );

/* Na página do kit, a escola logada vai direto para o criador já com o kit aberto. */
add_action( 'woocommerce_before_add_to_cart_form', function () {
	global $product;
	if ( ! $product instanceof WC_Product || ! dckit_eh_kit( $product->get_id() ) || ! function_exists( 'dcvx_url' ) || ! function_exists( 'dcomp_fr' ) || ! dcomp_fr() ) {
		return;
	}
	echo '<p style="margin:8px 0 14px"><a class="button" style="background:#C9A24A;color:#0B1F3F;font-weight:800;border-radius:10px;padding:12px 18px;display:inline-block" href="' . esc_url( add_query_arg( 'prod', 'kit', dcvx_url( 'dcomp-artes' ) ) ) . '">Monte a arte do seu kit em tempo real</a></p>';
}, 5 );

/* ------------------------------------------------------------ vitrine das escolas */

/* Uma vez: foto do kit (121613) como imagem do produto e o molde do logo nas 3 peças (vitrine com o logo da escola).
   Pasta: logo colorido seguindo as dobras do tecido. Squeeze: logo colorido. Bloco (capa azul): logo branco.
   Coordenadas em % da foto quadrada (cantos: sup. esq., sup. dir., inf. dir., inf. esq.). Um molde do Corel enviado depois substitui este. */
add_action( 'init', function () {
	if ( get_option( 'dckit_vitrine_v3' ) || ! dckit_pid() ) {
		return;
	}
	update_option( 'dckit_vitrine_v3', time(), false );
	$pid = dckit_pid();
	if ( wp_attachment_is_image( 121613 ) ) {
		set_post_thumbnail( $pid, 121613 );
		delete_post_meta( $pid, '_dcmk_foto' );
	}
	update_post_meta(
		$pid,
		'_dcmk',
		array(
			'x'  => 40.6,
			'y'  => 61.2,
			'w'  => 80,
			'r'  => 0,
			'm'  => 'cor',
			'd'  => 1,
			'q'  => array( 13.8, 49.4, 67.5, 49.4, 67.5, 73.1, 13.8, 73.1 ),
			'qx' => array(
				array( 'q' => array( 78.5, 56.1, 88.1, 56.1, 88.1, 69.0, 78.5, 69.0 ), 'm' => 'cor', 'l' => 'v', 't' => 'claro', 'd' => 0 ),
				array( 'q' => array( 64.6, 80.8, 75.0, 81.1, 74.4, 88.0, 62.9, 88.1 ), 'm' => 'cor', 'l' => 'v', 't' => 'escuro', 'd' => 0 ),
			),
		)
	);
}, 45 );

/* Destaque do kit no topo da vitrine da escola (chamado por dcom_vitrine_html). A foto recebe o logo da escola pelo mockup,
   e o botão "Monte a arte do seu kit" abre ali mesmo o montador simples (wp-content/uploads/dckit-editor.js, fonte kit-editor/ no repositório):
   1 fundo da pasta (cor, hexadecimal ou imagem), 2 frase (opcional), 3 quantidade, e "Pedir meus kits". Logos: pasta = arte completa,
   squeeze = logo vertical colorido (fundo claro), caderno = logo vertical colorido para fundo escuro. Impressão da pasta: 45 x 25 cm. */
function dckit_vitrine_destaque( $lf, $cor = '' ) {
	$pid = dckit_pid();
	$p   = $pid ? wc_get_product( $pid ) : null;
	if ( ! $p || ! $p->is_visible() ) {
		return '';
	}
	$f    = function ( $v ) {
		return 'R$ ' . number_format( (float) $v, 2, ',', '.' );
	};
	$rel  = function ( $u ) {
		return $u ? wp_make_link_relative( (string) $u ) : '';
	};
	$tab  = dckit_tabela();
	$link = add_query_arg( 'lf', $lf, get_permalink( $pid ) );
	$fx   = '';
	$fxj  = array();
	foreach ( $tab as $q => $u ) {
		$fx   .= '<li><b>' . (int) $q . ' kits</b><span>' . esc_html( $f( $u ) ) . '/kit</span></li>';
		$fxj[] = array( 'q' => (int) $q, 'p' => (float) $u );
	}
	$fr = ! empty( $GLOBALS['dcom_fr_atual'] ) ? (int) $GLOBALS['dcom_fr_atual'] : 0;
	$L  = ( $fr && function_exists( 'dcmk_logos' ) ) ? dcmk_logos( $fr ) : array();
	$lg = array();
	foreach ( array( 'cor', 'branco', 'ce', 'v', 'vb', 've' ) as $k ) {
		$lg[ $k ] = isset( $L[ $k ] ) ? $rel( $L[ $k ] ) : '';
	}
	$aqui = home_url( add_query_arg( array() ) );
	$up   = wp_upload_dir();
	$js   = trailingslashit( $up['basedir'] ) . 'dckit-editor.js';
	$dado = array(
		'foto'   => '/wp-content/uploads/2026/11/KIT-MATRICULA.jpg',
		'mask'   => '/wp-content/uploads/2026/11/KIT-MATRICULA-MASCARA-BOLSA.png',
		'sq'     => '/wp-content/uploads/2026/11/MASCARA-SQUEEZE.png',
		'bl'     => '/wp-content/uploads/2026/11/MASCARA-BLOCO.png',
		'logo'   => $lg,
		'cor'    => preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $cor ) ? strtoupper( $cor ) : '#0B2A5B',
		'min'    => DCKIT_MIN,
		'faixas' => $fxj,
		'log'    => is_user_logged_in(),
		'nonce'  => is_user_logged_in() ? wp_create_nonce( 'dckit_pedir' ) : '',
		'url'    => $rel( admin_url( 'admin-ajax.php' ) ),
		'entrar' => wp_login_url( $aqui . '#montar-kit' ),
	);
	$js_tag = file_exists( $js ) ? '<script>window.DCKIT=' . wp_json_encode( $dado, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';</script><script src="' . esc_url( $rel( trailingslashit( $up['baseurl'] ) . 'dckit-editor.js' ) . '?v=' . (int) filemtime( $js ) ) . '" defer></script>' : '';
	return '<style id="dckv">.dckv{display:grid;grid-template-columns:1fr 1.05fr;gap:26px;align-items:center;background:linear-gradient(120deg,var(--az,#0B2A5B),#07142b);color:#fff;border-radius:24px;padding:30px 32px;margin:0 0 18px;overflow:hidden;box-shadow:0 12px 30px rgba(11,42,91,.18)}'
		. '.dckv small{display:inline-block;background:var(--c,#F5B400);color:var(--ct,#0B2A5B);font-weight:800;font-size:12px;letter-spacing:.06em;text-transform:uppercase;border-radius:999px;padding:6px 12px}'
		. '.dckv h2{font-size:34px;line-height:1.1;margin:12px 0 8px;color:#fff}.dckv p{margin:0 0 14px;color:#C9D3E8;font-size:15px}'
		. '.dckv ul.it{list-style:none;margin:0 0 14px;padding:0;display:grid;gap:6px;font-size:15px}.dckv ul.it li:before{content:"\2713";color:var(--c,#F5B400);font-weight:900;margin-right:8px}'
		. '.dckv ul.fx{list-style:none;margin:0 0 18px;padding:0;display:flex;flex-wrap:wrap;gap:8px}.dckv ul.fx li{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:12px;padding:7px 11px;font-size:12.5px;line-height:1.25}.dckv ul.fx b{display:block;font-size:13px}'
		. '.dckv .bts{display:flex;gap:10px;flex-wrap:wrap}.dckv .bts a{border-radius:12px;padding:13px 20px;font-weight:800;text-decoration:none!important;font-size:15px}.dckv .b1{background:var(--c,#F5B400);color:var(--ct,#0B2A5B)!important}.dckv .b2{background:#fff;color:var(--az,#0B2A5B)!important}'
		. '.dckv a.dcv-card{padding:8px;border:0;border-radius:20px}.dckv a.dcv-card:hover{transform:none}.dckv .dcv-img{margin:0;border-radius:14px;background:#fff}.dckv .dcv-img img{max-width:100%;max-height:100%}'
		. '@media(max-width:860px){.dckv{grid-template-columns:1fr;padding:22px 18px}.dckv h2{font-size:28px}}</style>'
		. '<section class="dckv" id="dcv-kit"><div><small>Novidade &middot; Matrículas</small><h2>Kit Matrícula com a marca da sua escola</h2>'
		. '<p>Receba os alunos novos com um kit de boas-vindas personalizado. Monte a arte aqui mesmo, em 3 passos, e veja como fica antes de pedir.</p>'
		. '<ul class="it"><li>Pasta escolar sublimada com a arte da escola</li><li>Squeeze com o logo</li><li>Bloco de anotações com autoadesivos</li></ul>'
		. '<ul class="fx">' . $fx . '</ul>'
		. '<div class="bts"><a class="b1" href="#montar-kit" data-dckit-abrir="1">Monte a arte do seu kit</a><a class="b2" href="' . esc_url( $link ) . '">Ver o kit</a></div></div>'
		. '<a class="dcv-card" href="' . esc_url( $link ) . '" aria-label="Kit Matrícula"><span class="dcv-img">' . $p->get_image( 'woocommerce_single' ) . '</span></a></section>'
		. $js_tag;
}

/* ------------------------------------------------------------ arte do kit no carrinho, no pagamento e no pedido */

function dckit_item_arte( $item ) {
	if ( empty( $item['dcvx'] ) || empty( $item['product_id'] ) || ! dckit_eh_kit( $item['product_id'] ) ) {
		return null;
	}
	return $item['dcvx'];
}

// Foto do item = a prévia do kit que a escola montou (carrinho).
add_filter( 'woocommerce_cart_item_thumbnail', function ( $thumb, $item ) {
	$d = dckit_item_arte( $item );
	if ( $d && ! empty( $d['previa'] ) && wp_attachment_is_image( (int) $d['previa'] ) ) {
		return wp_get_attachment_image( (int) $d['previa'], 'woocommerce_thumbnail' );
	}
	return $thumb;
}, 99, 2 );

// Resumo do pagamento (checkout rápido monta a foto no nome do item): troca a foto do produto pela prévia.
add_filter( 'woocommerce_cart_item_name', function ( $name, $item ) {
	$d = dckit_item_arte( $item );
	if ( $d && ! empty( $d['previa'] ) && false !== strpos( $name, '<img' ) && wp_attachment_is_image( (int) $d['previa'] ) ) {
		$nova = wp_get_attachment_image( (int) $d['previa'], array( 112, 112 ) );
		$name = preg_replace( '/<img[^>]*>/', $nova, $name, 1 );
	}
	return $name;
}, 100, 2 );

// Linhas do item: frase, arte da pasta e prévia (links para abrir).
add_filter( 'woocommerce_get_item_data', function ( $data, $item ) {
	$d = dckit_item_arte( $item );
	if ( ! $d ) {
		return $data;
	}
	if ( ! empty( $d['curso'] ) && 'Kit Matrícula' !== $d['curso'] ) {
		$data[] = array( 'name' => 'Frase', 'value' => esc_html( $d['curso'] ) );
	}
	if ( ! empty( $d['arte'] ) && ( $u = wp_get_attachment_url( (int) $d['arte'] ) ) ) {
		$data[] = array( 'name' => 'Arte da pasta', 'value' => '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener">ver arte (45 x 25 cm)</a>' );
	}
	if ( ! empty( $d['previa'] ) && ( $u = wp_get_attachment_url( (int) $d['previa'] ) ) ) {
		$data[] = array( 'name' => 'Prévia do kit', 'value' => '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener">ver prévia</a>' );
	}
	if ( ! empty( $d['fundo'] ) && ( $u = wp_get_attachment_url( (int) $d['fundo'] ) ) ) {
		$data[] = array( 'name' => 'Fundo enviado', 'value' => '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener">' . esc_html( wp_basename( $u ) ) . '</a>' );
	}
	if ( ! empty( $d['logo'] ) && ( $u = wp_get_attachment_url( (int) $d['logo'] ) ) ) {
		$data[] = array( 'name' => 'Logo enviado', 'value' => '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener">' . esc_html( wp_basename( $u ) ) . '</a>' );
	}
	return $data;
}, 20, 2 );

// Pedido: grava a arte e a prévia no item (para a produção e para as telas de Pedidos de Arte).
add_action( 'woocommerce_checkout_create_order_line_item', function ( $oi, $key, $values ) {
	$d = dckit_item_arte( $values );
	if ( ! $d ) {
		return;
	}
	if ( ! $oi->get_meta( '_dcvx_arte' ) && ! empty( $d['arte'] ) ) {
		$oi->add_meta_data( '_dcvx_arte', (int) $d['arte'], true );
	}
	if ( ! $oi->get_meta( '_dcvx_previa' ) && ! empty( $d['previa'] ) ) {
		$oi->add_meta_data( '_dcvx_previa', (int) $d['previa'], true );
	}
	if ( ! $oi->get_meta( '_dcvx_curso' ) && isset( $d['curso'] ) ) {
		$oi->add_meta_data( '_dcvx_curso', (string) $d['curso'], true );
	}
	if ( ! empty( $d['arte'] ) && ( $u = wp_get_attachment_url( (int) $d['arte'] ) ) ) {
		$oi->add_meta_data( 'Arte da pasta', $u, true );
	}
	if ( ! empty( $d['previa'] ) && ( $u = wp_get_attachment_url( (int) $d['previa'] ) ) ) {
		$oi->add_meta_data( 'Prévia do kit', $u, true );
	}
	if ( ! empty( $d['fundo'] ) && ( $u = wp_get_attachment_url( (int) $d['fundo'] ) ) ) {
		$oi->add_meta_data( '_dckit_fundo', (int) $d['fundo'], true );
		$oi->add_meta_data( 'Fundo enviado', $u, true );
	}
	if ( ! empty( $d['logo'] ) && ( $u = wp_get_attachment_url( (int) $d['logo'] ) ) ) {
		$oi->add_meta_data( '_dckit_logo', (int) $d['logo'], true );
		$oi->add_meta_data( 'Logo enviado', $u, true );
	}
}, 30, 3 );
