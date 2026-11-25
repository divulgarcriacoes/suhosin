<?php
/**
 * Imagens por franquia: no cadastro do produto, a Matriz escolhe a franquia e a imagem com a arte da escola
 * (gerada no Gerador de artes e salva na Midia). Na Loja do franqueado o produto aparece com essa imagem.
 * Guardado no produto em _dcom_fr_img = array( id da franquia => id da imagem ). O produto entra na categoria da franquia.
 * A pagina do produto e o carrinho usam a imagem da franquia cuja loja foi visitada por ultimo (cookie dcom_fr).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Franquias aprovadas (unidade principal), por nome. */
function dcom_fr_lista() {
	static $l = null;
	if ( null !== $l ) {
		return $l;
	}
	$l   = array();
	$ids = get_users( array( 'role' => 'escola', 'meta_key' => 'omx_status', 'meta_value' => 'aprovada', 'fields' => 'ID', 'number' => -1 ) ); // phpcs:ignore
	if ( $ids ) {
		cache_users( $ids );
	}
	foreach ( (array) $ids as $id ) {
		$id = (int) $id;
		if ( (int) get_user_meta( $id, 'omx_parent', true ) > 0 ) {
			continue;
		}
		$n = (string) get_user_meta( $id, 'omx_escola', true );
		$c = (string) get_user_meta( $id, 'omx_cidade', true );
		$l[ $id ] = ( '' !== $n ? $n : 'Franquia #' . $id ) . ( '' !== $c ? ' (' . $c . ')' : '' );
	}
	asort( $l, SORT_NATURAL | SORT_FLAG_CASE );
	return $l;
}

function dcom_fr_mapa( $pid ) {
	$m = get_post_meta( (int) $pid, '_dcom_fr_img', true );
	return is_array( $m ) ? array_map( 'intval', $m ) : array();
}

/* Aba "Franquias" em Dados do produto (junto de Geral, Estoque, Entrega...). */
add_filter(
	'woocommerce_product_data_tabs',
	function ( $tabs ) {
		$tabs['dcom_fr'] = array(
			'label'    => 'Franquias',
			'target'   => 'dcom_fr_panel',
			'class'    => array(),
			'priority' => 15,
		);
		return $tabs;
	}
);
add_action(
	'woocommerce_product_data_panels',
	function () {
		global $post;
		echo '<div id="dcom_fr_panel" class="panel woocommerce_options_panel hidden" style="padding:12px 14px">';
		echo '<h3 style="margin:4px 0 8px">Imagens por franquia (Ouro Moderno)</h3>';
		dcom_fr_metabox( $post );
		echo '</div>';
	}
);

function dcom_fr_metabox( $post ) {
	wp_enqueue_media();
	$mapa = dcom_fr_mapa( $post->ID );
	$frs  = dcom_fr_lista();
	wp_nonce_field( 'dcom_fr_img', 'dcom_fr_img_n' );
	echo '<p style="margin-top:0">Escolha a franquia e a imagem com a arte da escola (a que voc&ecirc; gerou no Gerador de artes). Na loja daquela franquia o produto aparece com essa imagem, e o produto entra na categoria dela. As outras lojas n&atilde;o mudam.</p>';
	if ( $mapa ) {
		echo '<table class="widefat striped" style="margin:0 0 14px"><thead><tr><th>Franquia</th><th>Imagem</th><th>Tirar</th></tr></thead><tbody>';
		foreach ( $mapa as $fid => $img ) {
			$th = wp_get_attachment_image( $img, array( 70, 70 ) );
			echo '<tr><td>' . esc_html( isset( $frs[ $fid ] ) ? $frs[ $fid ] : 'Franquia #' . $fid ) . '</td><td>' . ( $th ? $th : '&ndash;' ) . '</td><td><label><input type="checkbox" name="dcom_fr_tirar[]" value="' . (int) $fid . '"> tirar</label></td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table>';
	}
	echo '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><select name="dcom_fr_add" style="min-width:280px"><option value="">Escolha a loja (franquia / categoria)...</option>';
	foreach ( $frs as $fid => $nome ) {
		echo '<option value="' . (int) $fid . '">' . esc_html( $nome ) . '</option>';
	}
	// Ultima imagem gerada/enviada para este produto (o Gerador de artes salva na Midia): atalho "Usar esta".
	$ult = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 1, 'orderby' => 'date', 'order' => 'DESC', 'post_parent' => (int) $post->ID, 'fields' => 'ids' ) );
	if ( ! $ult ) {
		$ult = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 1, 'orderby' => 'date', 'order' => 'DESC', 'author' => get_current_user_id(), 'date_query' => array( array( 'after' => '2 hours ago' ) ), 'fields' => 'ids' ) );
	}
	$ult_html = '';
	if ( $ult ) {
		$uid_img  = (int) $ult[0];
		$ult_url  = (string) wp_get_attachment_image_url( $uid_img, 'thumbnail' );
		$ult_html = '<span style="display:inline-flex;gap:8px;align-items:center;border:1px solid #dcdcde;border-radius:8px;padding:4px 8px;background:#fff"><img src="' . esc_url( $ult_url ) . '" style="width:44px;height:44px;object-fit:cover;border-radius:4px" alt=""><span>&Uacute;ltima imagem gerada</span><button type="button" class="button button-primary" id="dcom_fr_ult" data-id="' . $uid_img . '" data-url="' . esc_url( $ult_url ) . '">Usar esta</button></span>';
	}
	echo '</select><input type="hidden" name="dcom_fr_add_img" id="dcom_fr_add_img" value="">' . $ult_html . '<button type="button" class="button" id="dcom_fr_btn">Escolher outra imagem</button><span id="dcom_fr_pv"></span></div>' // phpcs:ignore
		. '<p class="description">Depois de escolher, clique em <b>Atualizar</b> no produto. Para v&aacute;rias franquias, adicione uma por vez.</p>'
		. '<script>jQuery(function($){$("#dcom_fr_ult").on("click",function(e){e.preventDefault();$("#dcom_fr_add_img").val($(this).data("id"));$("#dcom_fr_pv").html("<img src=\""+$(this).data("url")+"\" style=\"width:60px;height:60px;object-fit:cover;border-radius:6px;vertical-align:middle;outline:3px solid #2271b1\"> escolhida");});var f;$("#dcom_fr_btn").on("click",function(e){e.preventDefault();if(f){f.open();return;}f=wp.media({title:"Imagem da franquia",button:{text:"Usar esta imagem"},library:{type:"image"},multiple:false});f.on("select",function(){var a=f.state().get("selection").first().toJSON();$("#dcom_fr_add_img").val(a.id);var u=(a.sizes&&a.sizes.thumbnail)?a.sizes.thumbnail.url:a.url;$("#dcom_fr_pv").html("<img src=\""+u+"\" style=\"width:60px;height:60px;object-fit:cover;border-radius:6px;vertical-align:middle\">");});f.open();});});</script>';
}

add_action(
	'save_post_product',
	function ( $pid ) {
		if ( ! isset( $_POST['dcom_fr_img_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcom_fr_img_n'] ) ), 'dcom_fr_img' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $pid ) ) {
			return;
		}
		$mapa = dcom_fr_mapa( $pid );
		foreach ( (array) ( isset( $_POST['dcom_fr_tirar'] ) ? wp_unslash( $_POST['dcom_fr_tirar'] ) : array() ) as $fid ) { // phpcs:ignore
			unset( $mapa[ (int) $fid ] );
		}
		$fid = isset( $_POST['dcom_fr_add'] ) ? absint( $_POST['dcom_fr_add'] ) : 0;
		$img = isset( $_POST['dcom_fr_add_img'] ) ? absint( $_POST['dcom_fr_add_img'] ) : 0;
		if ( $fid && $img && wp_attachment_is_image( $img ) ) {
			$mapa[ $fid ] = $img;
			if ( function_exists( 'dcom_loja_garantir_cat' ) ) {
				$tid = dcom_loja_garantir_cat( $fid );
				if ( $tid ) {
					wp_set_object_terms( (int) $pid, (int) $tid, 'product_cat', true );
				}
			}
		}
		if ( $mapa ) {
			update_post_meta( $pid, '_dcom_fr_img', $mapa );
		} else {
			delete_post_meta( $pid, '_dcom_fr_img' );
		}
	},
	30
);

/* Franquia da loja em uso: a da pagina /<unidade>/ que esta sendo montada, ou a ultima visitada (cookie). */
function dcom_fr_atual() {
	if ( ! empty( $GLOBALS['dcom_fr_atual'] ) ) {
		return (int) $GLOBALS['dcom_fr_atual'];
	}
	// Fora da loja da franquia, a arte dela so aparece na pagina do produto, no carrinho e no checkout
	// (a Loja Ouro Moderno e a Divulgar continuam com a arte padrao).
	$ctx = ( function_exists( 'is_product' ) && is_product() ) || ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) || wp_doing_ajax();
	return ( $ctx && isset( $_COOKIE['dcom_fr'] ) ) ? absint( $_COOKIE['dcom_fr'] ) : 0; // phpcs:ignore
}

/* Imagem do produto na loja da franquia. */
function dcom_fr_imagem( $img_id, $product ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $img_id;
	}
	$fr = dcom_fr_atual();
	if ( ! $fr || ! is_object( $product ) ) {
		return $img_id;
	}
	$pid  = $product->is_type( 'variation' ) ? (int) $product->get_parent_id() : (int) $product->get_id();
	$mapa = dcom_fr_mapa( $pid );
	return ! empty( $mapa[ $fr ] ) ? (int) $mapa[ $fr ] : $img_id;
}
add_filter( 'woocommerce_product_get_image_id', 'dcom_fr_imagem', 30, 2 );
add_filter( 'woocommerce_product_variation_get_image_id', 'dcom_fr_imagem', 30, 2 );
