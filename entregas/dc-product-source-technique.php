<?php
/**
 * Plugin Name: DC · Origem e Técnica de Gravação (manual)
 * Description: Adiciona campos no produto pra marcar manualmente a origem do fornecedor (XBZ/Asia/Spot) e a(s) técnica(s) de gravação disponíveis — sobrescrevendo a detecção automática por categoria do motor de preço. Aparece na aba "Geral" dos Dados do Produto.
 * Version: 1.1.0
 * Author: Divulgar Criações
 * Text Domain: dc-product-source-technique
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Técnicas que o motor de preço sabe calcular: as 6 genéricas originais
 * (dcprice_tecnica_generica_tabela em dc-price-omnitek.php) mais as 7 novas
 * da lista por material (preço = 22% do custo). Se o motor mudar essa
 * lista, atualiza aqui também.
 */
function dcpst_technique_options() {
	return array(
		'laser'        => 'Laser',
		'tampografia'  => 'Tampografia',
		'silk'         => 'Silk',
		'silk_textil'  => 'Silk (têxtil)',
		'transfer'     => 'Transfer',
		'hot_stamping' => 'Hot stamping',
		'uv_direto'    => 'UV direto',
		'dtf_uv'       => 'DTF UV',
		'dtf_textil'   => 'DTF têxtil',
		'sublimacao'   => 'Sublimação',
		'bordado'      => 'Bordado',
		'baixo_relevo' => 'Baixo-relevo',
		'impressao'    => 'Impressão',
	);
}

/**
 * Campos na aba "Geral" dos Dados do Produto (abaixo do SKU).
 */
add_action( 'woocommerce_product_options_sku', 'dcpst_render_fields' );
function dcpst_render_fields() {
	global $post;
	$product_id = $post->ID;

	$current_source = '';
	if ( 'xbz' === get_post_meta( $product_id, '_dcxbz_source', true ) ) {
		$current_source = 'xbz';
	} elseif ( 'asia' === get_post_meta( $product_id, '_dcasia_source', true ) ) {
		$current_source = 'asia';
	} elseif ( 'spot' === get_post_meta( $product_id, '_dcspot_source', true ) ) {
		$current_source = 'spot';
	}

	$current_techniques = get_post_meta( $product_id, '_dc_technique_override', true );
	$current_techniques = is_array( $current_techniques ) ? $current_techniques : array();

	echo '<div class="options_group" style="border-top:1px solid #eee;padding-top:10px;">';
	echo '<p style="padding:0 12px;margin:0 0 8px;font-weight:600;">Origem do fornecedor e técnica de gravação (manual)</p>';

	woocommerce_wp_select(
		array(
			'id'          => '_dcpst_source',
			'label'       => 'Origem do fornecedor',
			'value'       => $current_source,
			'options'     => array(
				''     => '— Detectar automaticamente (não mexer) —',
				'xbz'  => 'XBZ',
				'asia' => 'Asia Import',
				'spot' => 'Spot / Stricker',
			),
			'desc_tip'    => true,
			'description' => 'Deixe em "Detectar automaticamente" se já estiver certo. Só mude se o produto estiver com a origem errada ou sem nenhuma.',
		)
	);

	echo '<div style="padding:8px 12px;clear:both;overflow:hidden;">';

	if ( 'spot' === $current_source && function_exists( 'dcprice_technique_options_for_product' ) ) {
		$real   = dcprice_technique_options_for_product( $product_id );
		$labels = wp_list_pluck( $real, 'label' );
		echo '<p style="margin:0 0 8px;color:#3c434a;">Detectada automaticamente pela Spot: <strong>' . esc_html( implode( ', ', $labels ) ?: 'nenhuma técnica encontrada' ) . '</strong> (preço real da Spot pra essas).</p>';
	}

	echo '<div style="font-weight:600;margin-bottom:6px;float:none;width:auto;position:static;">Adicionar técnica extra (opcional)</div>';
	echo '<div style="display:flex;flex-wrap:wrap;gap:4px 18px;">';
	foreach ( dcpst_technique_options() as $key => $label ) {
		$checked = in_array( $key, $current_techniques, true ) ? 'checked="checked"' : '';
		echo '<span style="display:inline-flex;align-items:center;gap:5px;float:none;width:auto;position:static;">';
		echo '<input type="checkbox" name="_dcpst_technique[]" id="_dcpst_technique_' . esc_attr( $key ) . '" value="' . esc_attr( $key ) . '" ' . $checked . ' style="margin:0;float:none;width:auto;position:static;" />';
		echo '<label for="_dcpst_technique_' . esc_attr( $key ) . '" style="display:inline;margin:0;font-weight:normal;float:none;width:auto;position:static;">' . esc_html( $label ) . '</label>';
		echo '</span>';
	}
	echo '</div>';
	if ( 'spot' === $current_source ) {
		echo '<p class="description" style="margin:6px 0 0;">Marcar aqui ADICIONA uma técnica além das reais da Spot acima, usando preço genérico estimado (não é o preço real da Spot pra essa técnica).</p>';
	} else {
		echo '<p class="description" style="margin:6px 0 0;">Marque as técnicas que esse produto (pelo material/corpo dele) realmente aceita. Deixe tudo desmarcado pra manter a detecção automática por categoria.</p>';
	}

	echo '</div>';

	echo '</div>';
}

/**
 * Salva: origem sobrescreve as metas padrão do motor de preço (limpa as
 * outras 2 pra não ficar duas origens marcadas ao mesmo tempo); técnica
 * salva a lista escolhida.
 */
add_action( 'woocommerce_process_product_meta', 'dcpst_save_fields' );
function dcpst_save_fields( $product_id ) {
	if ( isset( $_POST['_dcpst_source'] ) ) {
		$source = sanitize_text_field( wp_unslash( $_POST['_dcpst_source'] ) );
		if ( in_array( $source, array( 'xbz', 'asia', 'spot' ), true ) ) {
			delete_post_meta( $product_id, '_dcxbz_source' );
			delete_post_meta( $product_id, '_dcasia_source' );
			delete_post_meta( $product_id, '_dcspot_source' );
			update_post_meta( $product_id, '_dc' . $source . '_source', $source );
		}
		// vazio = "detectar automaticamente": não mexe no que já estava.
	}

	$valid_keys = array_keys( dcpst_technique_options() );
	$chosen     = array();
	if ( isset( $_POST['_dcpst_technique'] ) && is_array( $_POST['_dcpst_technique'] ) ) {
		foreach ( wp_unslash( $_POST['_dcpst_technique'] ) as $key ) {
			$key = sanitize_text_field( $key );
			if ( in_array( $key, $valid_keys, true ) ) {
				$chosen[] = $key;
			}
		}
	}
	if ( ! empty( $chosen ) ) {
		update_post_meta( $product_id, '_dc_technique_override', $chosen );
	} else {
		delete_post_meta( $product_id, '_dc_technique_override' );
	}
}
