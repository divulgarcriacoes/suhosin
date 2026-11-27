<?php
/**
 * Plugin Name: DC · Motor de Preços Spot
 * Description: Calcula preço real de venda (custo do fornecedor + gravação real + margem + imposto), com seletor de técnica (ícones) e tabela de preço por faixa de quantidade na página do produto. Spot usa a tabela real dela (sempre 1 cor); XBZ/Asia usa a tabela Omnitek. Painel em WooCommerce → Precificação Spot.
 * Version: 6.8.0
 * Author: Divulgar Criações
 * Text Domain: dc-price-engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/dc-price-omnitek.php';
require_once __DIR__ . '/dc-price-regras.php';
require_once __DIR__ . '/dc-price-aumentos.php';

define( 'DCPRICE_OPT', 'dcprice_settings' );
define( 'DCPRICE_CACHE_META_OPT', 'dcprice_cache_meta' );
define( 'DCPRICE_WRITE_QUEUE_OPT', 'dcprice_write_queue' );
define( 'DCPRICE_WRITE_REPORT_OPT', 'dcprice_write_report' );
define( 'DCPRICE_VERSION', '6.8.0' );

/* -------------------------------------------------------------------------
 * Configurações
 * Fórmula: preço unitário = (custo do produto + gravação) × margem × imposto
 *          gravação = custo do produto × gravacao_pct
 * ---------------------------------------------------------------------- */
function dcprice_defaults() {
	return array(
		'margem_pct'    => 45,  // margem de lucro, sobre (custo + gravação)
		'imposto_pct'   => 4,   // Simples Nacional, alíquota efetiva
		'gravacao_pct'  => 15,  // acréscimo sobre o valor REAL de gravação da Spot (custo de buscar/gerenciar a gravação)
	);
}

function dcprice_settings() {
	$saved = get_option( DCPRICE_OPT, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, dcprice_defaults() );
}

/* -------------------------------------------------------------------------
 * Acesso à chave da Spot já configurada no plugin de sincronização de estoque
 * (não duplicamos a credencial; se o plugin dcspotstk não estiver ativo, o
 * cache simplesmente não pode ser atualizado e a tela avisa isso).
 * ---------------------------------------------------------------------- */
function dcprice_spot_access_key() {
	$spot = get_option( 'dcspotstk_settings', array() );
	if ( is_array( $spot ) && ! empty( $spot['api_key'] ) ) {
		return $spot['api_key'];
	}
	return '';
}

function dcprice_cache_dir() {
	$upload = wp_upload_dir();
	return trailingslashit( $upload['basedir'] ) . 'dc-price-cache/';
}

/* -------------------------------------------------------------------------
 * Baixa o feed de preço da Spot (endpoint de download direto, sem sessão) e
 * decodifica. O feed vem em ISO-8859-1 (Latin1) — convertemos pra UTF-8.
 * ---------------------------------------------------------------------- */
function dcprice_fetch_spot_feed( $data_name ) {
	$key = dcprice_spot_access_key();
	if ( empty( $key ) ) {
		return new WP_Error( 'dcprice_no_key', 'Chave da Spot não encontrada (plugin de sincronização de estoque precisa estar ativo e configurado).' );
	}
	$url = add_query_arg(
		array(
			'AccessKey' => $key,
			'data'      => $data_name,
			'lang'      => 'PT',
			'extension' => 'json',
		),
		'https://ws.spotgifts.com.br/downloads/v1SSL/file'
	);
	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 120,
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== (int) $code ) {
		return new WP_Error( 'dcprice_http_' . $code, 'Spot respondeu HTTP ' . $code . ' pro feed ' . $data_name );
	}
	$body = wp_remote_retrieve_body( $response );
	if ( empty( $body ) ) {
		return new WP_Error( 'dcprice_empty', 'Feed ' . $data_name . ' voltou vazio.' );
	}
	if ( ! mb_check_encoding( $body, 'UTF-8' ) ) {
		$body = mb_convert_encoding( $body, 'UTF-8', 'ISO-8859-1' );
	}
	$decoded = json_decode( $body, true );
	if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
		return new WP_Error( 'dcprice_json', 'Não consegui decodificar o feed ' . $data_name . ': ' . json_last_error_msg() );
	}
	return $decoded;
}

/* -------------------------------------------------------------------------
 * Referências Spot (ProdReference) usadas no catálogo do site, via meta
 * _dcspot_ref (gravada pelo plugin de sincronização/criação de produtos).
 * ---------------------------------------------------------------------- */
function dcprice_site_spot_refs() {
	global $wpdb;
	$rows = $wpdb->get_col(
		"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_dcspot_ref' AND meta_value != ''"
	);
	return array_values( array_unique( array_filter( array_map( 'strval', (array) $rows ) ) ) );
}

/* -------------------------------------------------------------------------
 * Lê a tabela MinQt1..15/Price1..15 de um item e devolve o preço aplicável
 * pra uma quantidade (o maior MinQt que ainda é <= $qty).
 * ---------------------------------------------------------------------- */
function dcprice_tier_price( $item, $qty ) {
	$qty   = max( 1, (int) $qty );
	$price = null;
	$best_minqt = -1;
	for ( $i = 1; $i <= 15; $i++ ) {
		$minqt_key = 'MinQt' . $i;
		$price_key = 'Price' . $i;
		if ( ! isset( $item[ $minqt_key ] ) || null === $item[ $minqt_key ] ) {
			continue;
		}
		if ( ! isset( $item[ $price_key ] ) || null === $item[ $price_key ] ) {
			continue;
		}
		$minqt = (int) $item[ $minqt_key ];
		if ( $minqt <= $qty && $minqt >= $best_minqt ) {
			$best_minqt = $minqt;
			$price      = (float) $item[ $price_key ];
		}
	}
	// Se a quantidade for menor que o menor MinQt disponível, usa a menor faixa mesmo assim.
	if ( null === $price ) {
		for ( $i = 1; $i <= 15; $i++ ) {
			$price_key = 'Price' . $i;
			if ( isset( $item[ $price_key ] ) && null !== $item[ $price_key ] ) {
				$price = (float) $item[ $price_key ];
				break;
			}
		}
	}
	return $price;
}

/* -------------------------------------------------------------------------
 * Atualiza o cache local: baixa optionalsPrice (custo) + customizationOptions
 * (gravação real por técnica/quantidade) da Spot, filtra pelas referências
 * que existem no site, e grava dois JSONs em wp-content/uploads/dc-price-cache/.
 * ---------------------------------------------------------------------- */
function dcprice_refresh_cache() {
	$refs = dcprice_site_spot_refs();
	if ( empty( $refs ) ) {
		return new WP_Error( 'dcprice_no_refs', 'Nenhum produto do site tem meta _dcspot_ref (nenhum produto Spot encontrado).' );
	}
	$refs_flip = array_flip( $refs );

	$optionals = dcprice_fetch_spot_feed( 'optionalsPrice' );
	if ( is_wp_error( $optionals ) ) {
		return $optionals;
	}
	$customization = dcprice_fetch_spot_feed( 'customizationOptions' );
	if ( is_wp_error( $customization ) ) {
		return $customization;
	}
	$tables = dcprice_fetch_spot_feed( 'customizationTables' );
	if ( is_wp_error( $tables ) ) {
		return $tables;
	}
	// O preço de verdade da gravação mora em "customizationTables" (tabela
	// genérica indexada por TableCodeOption — cada variante de área/nº de
	// cores tem sua PRÓPRIA linha de preço, ex: "PDP1-01-01" 1 cor custa
	// diferente de "PDP1-01-02" 2 cores). O que vem dentro de
	// "customizationOptions" (Price1..15 na própria linha do produto) é
	// OUTRO valor, diferente do preço real cobrado — não usar.
	$tiers_by_option_code = array();
	foreach ( (array) $tables as $row ) {
		$code = isset( $row['TableCodeOption'] ) ? (string) $row['TableCodeOption'] : '';
		if ( '' === $code || isset( $tiers_by_option_code[ $code ] ) ) {
			continue;
		}
		$tiers_by_option_code[ $code ] = dcprice_extract_tiers( $row );
	}

	// Custo do produto: por ProdReference, guarda a variação (Sku) de menor
	// custo na faixa 1 como referência de "custo base".
	$cost_by_ref = array();
	foreach ( (array) $optionals as $row ) {
		$ref = isset( $row['ProdReference'] ) ? (string) $row['ProdReference'] : '';
		if ( '' === $ref || ! isset( $refs_flip[ $ref ] ) ) {
			continue;
		}
		if ( ! isset( $cost_by_ref[ $ref ] ) ) {
			$cost_by_ref[ $ref ] = $row;
			continue;
		}
		$current = isset( $cost_by_ref[ $ref ]['Price1'] ) ? (float) $cost_by_ref[ $ref ]['Price1'] : PHP_FLOAT_MAX;
		$new     = isset( $row['Price1'] ) ? (float) $row['Price1'] : PHP_FLOAT_MAX;
		if ( $new < $current ) {
			$cost_by_ref[ $ref ] = $row;
		}
	}

	// Gravação: agrupa por ProdReference + TÉCNICA (ex: "Laser", "Transfer").
	// Cada técnica tem várias variantes de área/nº de cores — sempre usa a
	// de 1 COR (a mais simples/padrão de brinde); só cai pra mais colorida
	// se aquela técnica não tiver opção de 1 cor nesse produto.
	$by_technique = array();
	foreach ( (array) $customization as $row ) {
		$ref = isset( $row['ProdReference'] ) ? (string) $row['ProdReference'] : '';
		if ( '' === $ref || ! isset( $refs_flip[ $ref ] ) ) {
			continue;
		}
		$option_code = isset( $row['TableCodeOption'] ) ? (string) $row['TableCodeOption'] : '';
		$tiers       = isset( $tiers_by_option_code[ $option_code ] ) ? $tiers_by_option_code[ $option_code ] : array();
		if ( empty( $tiers ) ) {
			continue;
		}
		$technique = isset( $row['CustomizationTypeName'] ) ? trim( $row['CustomizationTypeName'] ) : '';
		if ( '' === $technique ) {
			continue;
		}
		$max_colors = isset( $row['MaxColors'] ) ? (int) $row['MaxColors'] : 99;
		$cheapest   = min( wp_list_pluck( $tiers, 'price' ) );

		if ( ! isset( $by_technique[ $ref ] ) ) {
			$by_technique[ $ref ] = array();
		}
		$current = isset( $by_technique[ $ref ][ $technique ] ) ? $by_technique[ $ref ][ $technique ] : null;
		// Prioridade: 1) menos cores vence sempre mais cores (queremos a de
		// 1 cor); 2) entre duas com o mesmo nº de cores, fica a mais barata.
		$wins = false;
		if ( null === $current ) {
			$wins = true;
		} elseif ( $max_colors < $current['max_colors'] ) {
			$wins = true;
		} elseif ( $max_colors === $current['max_colors'] && $cheapest < $current['cheapest'] ) {
			$wins = true;
		}
		if ( $wins ) {
			$by_technique[ $ref ][ $technique ] = array(
				'service_code' => isset( $row['ServiceCode'] ) ? $row['ServiceCode'] : '',
				'label'        => $technique,
				'tiers'        => $tiers,
				'cheapest'     => $cheapest,
				'max_colors'   => $max_colors,
			);
		}
	}
	$custom_by_ref = array();
	foreach ( $by_technique as $ref => $techniques ) {
		$custom_by_ref[ $ref ] = array_values( $techniques );
	}

	$dir = dcprice_cache_dir();
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	file_put_contents( $dir . 'cost-by-ref.json', wp_json_encode( $cost_by_ref ) );
	file_put_contents( $dir . 'customization-by-ref.json', wp_json_encode( $custom_by_ref ) );

	$meta = array(
		'updated'          => current_time( 'mysql' ),
		'refs_no_site'     => count( $refs ),
		'refs_with_cost'   => count( $cost_by_ref ),
		'refs_with_custom' => count( $custom_by_ref ),
	);
	update_option( DCPRICE_CACHE_META_OPT, $meta, false );

	return $meta;
}

function dcprice_extract_tiers( $row ) {
	$tiers = array();
	for ( $i = 1; $i <= 15; $i++ ) {
		$minqt_key = 'MinQt' . $i;
		$price_key = 'Price' . $i;
		if ( isset( $row[ $minqt_key ] ) && null !== $row[ $minqt_key ] && isset( $row[ $price_key ] ) && null !== $row[ $price_key ] ) {
			$tiers[] = array( 'min_qty' => (int) $row[ $minqt_key ], 'price' => (float) $row[ $price_key ] );
		}
	}
	return $tiers;
}

function dcprice_tier_price_from_tiers( $tiers, $qty ) {
	$qty   = max( 1, (int) $qty );
	$price = null;
	$best_minqt = -1;
	foreach ( (array) $tiers as $tier ) {
		if ( $tier['min_qty'] <= $qty && $tier['min_qty'] >= $best_minqt ) {
			$best_minqt = $tier['min_qty'];
			$price      = $tier['price'];
		}
	}
	if ( null === $price && ! empty( $tiers ) ) {
		$price = $tiers[0]['price'];
	}
	return $price;
}

/* -------------------------------------------------------------------------
 * A técnica mais barata pode MUDAR conforme a quantidade (custo fixo de
 * setup dilui diferente pra cada técnica) — comparamos todas na quantidade
 * pedida, em vez de fixar uma só por produto.
 * ---------------------------------------------------------------------- */
function dcprice_cheapest_technique_at_qty( $options, $qty ) {
	$best = null;
	$best_price = null;
	foreach ( (array) $options as $option ) {
		$price = dcprice_tier_price_from_tiers( $option['tiers'], $qty );
		if ( null === $price ) {
			continue;
		}
		if ( null === $best_price || $price < $best_price ) {
			$best_price = $price;
			$best       = $option;
		}
	}
	return $best;
}

/* -------------------------------------------------------------------------
 * Leitura do cache (arquivo -> array em memória, request-scoped).
 * ---------------------------------------------------------------------- */
function dcprice_load_cache() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$dir    = dcprice_cache_dir();
	$cost   = array();
	$custom = array();
	if ( file_exists( $dir . 'cost-by-ref.json' ) ) {
		$cost = json_decode( file_get_contents( $dir . 'cost-by-ref.json' ), true );
		$cost = is_array( $cost ) ? $cost : array();
	}
	if ( file_exists( $dir . 'customization-by-ref.json' ) ) {
		$custom = json_decode( file_get_contents( $dir . 'customization-by-ref.json' ), true );
		$custom = is_array( $custom ) ? $custom : array();
	}
	$cache = array( 'cost' => $cost, 'custom' => $custom );
	return $cache;
}

function dcprice_ref_for_product( $product_id ) {
	return get_post_meta( $product_id, '_dcspot_ref', true );
}

/* -------------------------------------------------------------------------
 * Cálculo: preço unitário = (custo + gravação) × margem × imposto, onde
 * gravação = preço REAL da Spot (técnica mais barata na quantidade pedida)
 * + um acréscimo (gravacao_pct) pelo custo de gerenciar/buscar a gravação.
 * ---------------------------------------------------------------------- */
function dcprice_calculate( $ref, $qty, $technique_key = null ) {
	$cache = dcprice_load_cache();
	if ( empty( $cache['cost'][ $ref ] ) ) {
		return new WP_Error( 'dcprice_no_cost', 'Sem custo cadastrado no cache pra essa referência.' );
	}
	$cost_row  = $cache['cost'][ $ref ];
	$cost_unit = dcprice_tier_price( $cost_row, $qty );
	if ( null === $cost_unit ) {
		return new WP_Error( 'dcprice_no_cost_tier', 'Sem faixa de preço de custo compatível com essa quantidade.' );
	}

	$gravacao_real = 0.0;
	$technique_label = '';
	if ( ! empty( $cache['custom'][ $ref ] ) ) {
		$chosen = null;
		if ( null !== $technique_key ) {
			foreach ( $cache['custom'][ $ref ] as $option ) {
				if ( $option['service_code'] === $technique_key ) {
					$chosen = $option;
					break;
				}
			}
		}
		if ( null === $chosen ) {
			$chosen = dcprice_cheapest_technique_at_qty( $cache['custom'][ $ref ], $qty );
		}
		if ( $chosen ) {
			$gravacao_real   = (float) dcprice_tier_price_from_tiers( $chosen['tiers'], $qty );
			$technique_label = $chosen['label'];
		}
	}

	$settings       = dcprice_settings();
	$gravacao_mult  = 1 + ( (float) $settings['gravacao_pct'] / 100 ); // acréscimo sobre a gravação real
	$margem_mult    = 1 + ( (float) $settings['margem_pct'] / 100 );
	$imposto_mult   = 1 + ( (float) $settings['imposto_pct'] / 100 );

	$gravacao_unit    = $gravacao_real * $gravacao_mult;
	$soma             = $cost_unit + $gravacao_unit;
	$unit_price_raw   = $soma * $margem_mult * $imposto_mult;
	// Arredonda o preço unitário PRIMEIRO e calcula o total em cima do valor
	// já arredondado — senão o total mostrado não bate com unidade × quantidade
	// (ex: R$8,16/un. × 100 tinha que dar R$816,00, não R$815,83).
	$unit_price = round( $unit_price_raw, 2 );
	$total      = round( $unit_price * max( 1, (int) $qty ), 2 );

	return array(
		'cost_unit'       => round( $cost_unit, 4 ),
		'gravacao_unit'   => round( $gravacao_unit, 4 ),
		'technique_label' => $technique_label,
		'unit_price'      => $unit_price,
		'total'           => $total,
		'qty'             => (int) $qty,
	);
}

/* -------------------------------------------------------------------------
 * Gravação do preço calculado como preço REAL do produto (_regular_price /
 * _price), na quantidade mínima do produto. Fila + drenagem no admin_init
 * (mesmo padrão dos outros plugins DC, porque o Action Scheduler não roda
 * direito nesse host).
 * ---------------------------------------------------------------------- */
function dcprice_products_with_cache() {
	global $wpdb;
	$map   = array();
	$cache = dcprice_load_cache();
	$refs  = array_keys( $cache['cost'] );
	if ( ! empty( $refs ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $refs ), '%s' ) );
		$sql          = "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_dcspot_ref' AND meta_value IN ($placeholders)";
		$rows         = $wpdb->get_results( $wpdb->prepare( $sql, $refs ), ARRAY_A ); // phpcs:ignore
		foreach ( (array) $rows as $row ) {
			$map[ (int) $row['post_id'] ] = 'spot';
		}
	}

	// XBZ/Asia: QUALQUER produto com origem marcada que tenha custo no cache
	// E seja "priceable" (categoria curada, fallback genérico por categoria,
	// OU técnica marcada manualmente) — não depende mais só da tabela curada.
	$xa_cache = dcprice_load_xbz_asia_cache();
	if ( ! empty( $xa_cache['xbz'] ) || ! empty( $xa_cache['asia'] ) ) {
		$xbz_asia_ids = $wpdb->get_col(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_dcxbz_source','_dcasia_source')"
		);
		foreach ( $xbz_asia_ids as $pid ) {
			$pid     = (int) $pid;
			$source  = get_post_meta( $pid, '_dcxbz_source', true );
			$is_xbz  = 'xbz' === $source;
			$is_asia = ! $is_xbz && 'asia' === get_post_meta( $pid, '_dcasia_source', true );
			if ( ! $is_xbz && ! $is_asia ) {
				continue;
			}
			$product = wc_get_product( $pid );
			$sku     = $product ? $product->get_sku() : '';
			if ( '' === $sku ) {
				continue;
			}
			$has_cost = ( $is_xbz && isset( $xa_cache['xbz'][ $sku ] ) ) || ( $is_asia && isset( $xa_cache['asia'][ $sku ] ) );
			if ( ! $has_cost ) {
				continue;
			}
			if ( ! dcprice_product_is_priceable( $pid ) ) {
				continue;
			}
			$map[ $pid ] = $is_xbz ? 'xbz' : 'asia';
		}
	}

	return $map; // product_id => 'spot' | 'xbz' | 'asia'
}

function dcprice_start_price_write() {
	$map = dcprice_products_with_cache();
	update_option( DCPRICE_WRITE_QUEUE_OPT, $map, false );
	update_option(
		DCPRICE_WRITE_REPORT_OPT,
		array(
			'total'    => count( $map ),
			'done'     => 0,
			'skipped'  => 0,
			'started'  => current_time( 'mysql' ),
			'finished' => null,
		),
		false
	);
}

function dcprice_drain_price_write_queue( $budget_seconds = 20 ) {
	$queue = get_option( DCPRICE_WRITE_QUEUE_OPT, array() );
	if ( empty( $queue ) || ! is_array( $queue ) ) {
		return;
	}
	$report = get_option(
		DCPRICE_WRITE_REPORT_OPT,
		array( 'total' => count( $queue ), 'done' => 0, 'skipped' => 0, 'started' => current_time( 'mysql' ), 'finished' => null )
	);

	$start = microtime( true );
	foreach ( $queue as $product_id => $supplier ) {
		if ( microtime( true ) - $start > $budget_seconds ) {
			break;
		}
		unset( $queue[ $product_id ] );

		$product = wc_get_product( (int) $product_id );
		if ( ! $product ) {
			$report['skipped']++;
			continue;
		}
		$min_qty = 1;
		if ( class_exists( 'DC_Min_Order_Qty' ) ) {
			$min_qty = DC_Min_Order_Qty::min_qty_for_product( $product_id );
		}
		// Faixa A (regra escalonada A-F): com a regra desligada o valor e identico ao de antes. Somarcas segue o caminho antigo.
		$result = ( function_exists( 'dcprice_somarcas_has_tiers' ) && dcprice_somarcas_has_tiers( (int) $product_id ) )
			? dcprice_calculate_for_product( (int) $product_id, $min_qty )
			: dcprice_calculate_with_curve( (int) $product_id, $min_qty );
		if ( is_wp_error( $result ) ) {
			$report['skipped']++;
			continue;
		}
		$product->set_regular_price( (string) $result['unit_price'] );
		$product->set_price( (string) $result['unit_price'] );
		$product->update_meta_data( '_dcprice_calc_qty', $min_qty );
		$product->update_meta_data( '_dcprice_calc_at', current_time( 'mysql' ) );
		$product->save();
		$report['done']++;
	}

	update_option( DCPRICE_WRITE_QUEUE_OPT, $queue, false );
	if ( empty( $queue ) ) {
		$report['finished'] = current_time( 'mysql' );
	}
	update_option( DCPRICE_WRITE_REPORT_OPT, $report, false );
}

add_action( 'admin_init', 'dcprice_admin_init_drain' );
function dcprice_admin_init_drain() {
	$queue = get_option( DCPRICE_WRITE_QUEUE_OPT, array() );
	if ( ! empty( $queue ) ) {
		dcprice_drain_price_write_queue( 15 );
	}
}

/* -------------------------------------------------------------------------
 * Preço real no carrinho: recalcula pela quantidade de verdade (não fica
 * travado no valor gravado na quantidade mínima). Assim o campo único de
 * quantidade do "Adicionar ao carrinho" já reflete a faixa certa.
 * ---------------------------------------------------------------------- */
add_action( 'woocommerce_before_calculate_totals', 'dcprice_recalc_cart_prices', 20, 1 );
function dcprice_recalc_cart_prices( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	if ( ! $cart instanceof WC_Cart ) {
		return;
	}
	foreach ( $cart->get_cart() as $cart_item ) {
		$product_id = $cart_item['product_id'];
		if ( ! dcprice_product_is_priceable( $product_id ) ) {
			continue;
		}
		// Quantidade fora da tabela (ex.: 46): vale a maior faixa que cabe nela (30), a mesma regra da página do produto.
		$qtd = (int) $cart_item['quantity'];
		if ( function_exists( 'dcprice_qty_tiers_for_product' ) ) {
			$faixas = array_map( 'intval', (array) dcprice_qty_tiers_for_product( $product_id ) );
			sort( $faixas );
			$usar = $faixas ? $faixas[0] : $qtd;
			foreach ( $faixas as $f ) {
				if ( $f > 0 && $f <= $qtd ) {
					$usar = $f;
				}
			}
			$qtd = max( $usar, 1 );
		}
		$result = dcprice_calculate_with_curve( $product_id, $qtd );
		if ( is_wp_error( $result ) ) {
			continue;
		}
		$cart_item['data']->set_price( (string) $result['unit_price'] );
	}
}

/* -------------------------------------------------------------------------
 * Front-end: calculadora na página do produto — seletor de técnica (ícones)
 * + tabela de preço por faixa de quantidade, tudo pré-calculado no servidor
 * (sem ida e volta de AJAX pra cada clique). Um único campo de quantidade
 * (o nativo do "Adicionar ao carrinho"), sincronizado com a tabela.
 * ---------------------------------------------------------------------- */
add_action( 'woocommerce_before_add_to_cart_button', 'dcprice_render_calculator', 15 );
function dcprice_render_calculator() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	// Produtos com variação (cor, tamanho...) guardam o SKU em cada variação,
	// não no produto pai — e a calculadora ainda não sabe qual variação foi
	// escolhida. Por enquanto só aparece em produto simples, pra não quebrar
	// o formulário de variação nem mostrar "sem SKU".
	if ( $product->is_type( 'variable' ) ) {
		return;
	}
	$product_id = $product->get_id();
	if ( ! dcprice_product_is_priceable( $product_id ) ) {
		return;
	}

	$tiers   = dcprice_qty_tiers_for_product( $product_id );
	$options = dcprice_technique_options_for_product( $product_id );
	if ( empty( $tiers ) ) {
		return;
	}
	// Sem técnica cadastrada: usa uma opção "única" (gravação já embutida no
	// cálculo padrão, sem escolha) — a tabela de quantidade ainda aparece.
	if ( empty( $options ) ) {
		$options = array( array( 'key' => '', 'label' => '', 'icon' => '', 'description' => '' ) );
	}

	// Matriz preço[técnica][quantidade], toda calculada aqui — o JS só troca
	// o que já está pronto, sem chamada nenhuma ao servidor depois disso.
	// O preço REAL só é calculado uma vez (na quantidade mínima); as demais
	// faixas aplicam a curva de desconto fixa por múltiplo (dcprice_discount_curve),
	// pra nunca repetir valor de uma faixa pra outra.
	$min_qty = $tiers[0];
	$curve   = dcprice_discount_curve( $product_id );
	$matrix  = array();
	foreach ( $options as $opt ) {
		$base = dcprice_calculate_for_product( $product_id, $min_qty, '' === $opt['key'] ? null : $opt['key'] );
		$row  = array();
		if ( is_wp_error( $base ) ) {
			foreach ( $tiers as $qty ) {
				$row[ $qty ] = null;
			}
			$matrix[ $opt['key'] ] = $row;
			continue;
		}
		foreach ( $tiers as $qty ) {
			$mult      = $min_qty > 0 ? (int) round( $qty / $min_qty ) : 1;
			$discount  = isset( $curve[ $mult ] ) ? $curve[ $mult ] : 0.0;
			$unit      = round( $base['unit_price'] * ( 1 - $discount ), 2 );
			$row[ $qty ] = array(
				'unit_price' => $unit,
				'total'      => round( $unit * $qty, 2 ),
			);
		}
		$matrix[ $opt['key'] ] = $row;
	}

	// Ordena as técnicas da mais barata pra mais cara (na 1ª faixa de qtd).
	usort(
		$options,
		function ( $a, $b ) use ( $matrix, $tiers ) {
			$pa = $matrix[ $a['key'] ][ $tiers[0] ]['unit_price'] ?? PHP_FLOAT_MAX;
			$pb = $matrix[ $b['key'] ][ $tiers[0] ]['unit_price'] ?? PHP_FLOAT_MAX;
			return $pa <=> $pb;
		}
	);
	$default_technique = $options[0]['key'];
	$default_qty        = $tiers[0];

	wp_enqueue_script( 'dcprice-calc', plugins_url( 'dcprice-calc.js', __FILE__ ), array( 'jquery' ), DCPRICE_VERSION . '.' . (int) @filemtime( __DIR__ . '/dcprice-calc.js' ), true ); // phpcs:ignore -- versão muda a cada edição do JS (CDN guarda 7 dias)
	wp_localize_script(
		'dcprice-calc',
		'dcpriceVars',
		array(
			'matrix'            => $matrix,
			'defaultTechnique'  => $default_technique,
			'defaultQty'        => $default_qty,
		)
	);
	$show_techniques = count( $options ) > 1;
	?>
	<style>
		.dcprice-step-value::-webkit-outer-spin-button,
		.dcprice-step-value::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
		.dcprice-step-value { -moz-appearance: textfield; appearance: textfield; }
	</style>
	<div class="dcprice-box" style="border:1px solid #E4E4E0;border-radius:10px;padding:18px 20px;margin:12px 0 18px;background:#fff;">
		<?php if ( $show_techniques ) : ?>
			<p style="margin:0 0 10px;font-weight:700;color:#1C1C1A;font-size:15px;">Gravação</p>
			<div class="dcprice-techniques" style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:10px;">
				<?php foreach ( $options as $opt ) : ?>
					<label style="text-align:center;cursor:pointer;width:78px;">
						<span style="display:flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:8px;background:#F4F4F2;margin:0 auto 4px;<?php echo $opt['key'] === $default_technique ? 'outline:2px solid #F5821F;' : ''; ?>">
							<?php if ( $opt['icon'] ) : ?>
								<img src="<?php echo esc_url( $opt['icon'] ); ?>" alt="<?php echo esc_attr( $opt['label'] ); ?>" width="34" height="34" />
							<?php endif; ?>
						</span>
						<input type="radio" name="dcprice_technique" value="<?php echo esc_attr( $opt['key'] ); ?>" data-description="<?php echo esc_attr( $opt['description'] ); ?>" <?php checked( $opt['key'], $default_technique ); ?> style="display:block;margin:0 auto;" />
						<span style="display:block;font-size:11px;color:#6B6B64;margin-top:2px;"><?php echo esc_html( $opt['label'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<p class="dcprice-technique-desc" style="font-size:12px;color:#6B6B64;margin:0 0 16px;">
				<?php echo esc_html( $options[0]['description'] ); ?>
			</p>
		<?php elseif ( ! empty( $options ) ) : ?>
			<?php // Só 1 técnica: não dá opção de escolha, mas mostra qual é (o cliente precisa saber que a peça vem gravada, e com quê). ?>
			<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;padding:8px 10px;background:#F4F4F2;border-radius:8px;">
				<?php if ( $options[0]['icon'] ) : ?>
					<img src="<?php echo esc_url( $options[0]['icon'] ); ?>" alt="<?php echo esc_attr( $options[0]['label'] ); ?>" width="28" height="28" />
				<?php endif; ?>
				<div>
					<span style="display:block;font-weight:700;color:#1C1C1A;font-size:13px;">Gravação: <?php echo esc_html( $options[0]['label'] ); ?></span>
					<span style="display:block;font-size:11px;color:#6B6B64;"><?php echo esc_html( $options[0]['description'] ); ?></span>
				</div>
			</div>
		<?php endif; ?>

		<p style="margin:0 0 8px;font-weight:700;color:#1C1C1A;font-size:15px;">Escolha a quantidade</p>
		<table class="dcprice-table" style="width:100%;border-collapse:collapse;margin-bottom:14px;">
			<thead>
				<tr style="background:#F4F4F2;">
					<th style="text-align:left;padding:8px 10px;font-size:12px;color:#6B6B64;font-weight:600;"></th>
					<th style="text-align:left;padding:8px 10px;font-size:12px;color:#6B6B64;font-weight:600;">Quantidade</th>
					<th style="text-align:right;padding:8px 10px;font-size:12px;color:#6B6B64;font-weight:600;">Valor unitário</th>
					<th style="text-align:right;padding:8px 10px;font-size:12px;color:#6B6B64;font-weight:600;">Valor total</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tiers as $qty ) : ?>
					<tr style="border-bottom:1px solid #E4E4E0;">
						<td style="padding:8px 10px;"><input type="radio" name="dcprice_qty" value="<?php echo esc_attr( $qty ); ?>" <?php checked( $qty, $default_qty ); ?> /></td>
						<td style="padding:8px 10px;font-size:13px;color:#1C1C1A;"><?php echo esc_html( $qty ); ?> unidades</td>
						<td class="dcprice-cell-unit" data-qty="<?php echo esc_attr( $qty ); ?>" style="text-align:right;padding:8px 10px;font-size:13px;color:#1C1C1A;"></td>
						<td class="dcprice-cell-total" data-qty="<?php echo esc_attr( $qty ); ?>" style="text-align:right;padding:8px 10px;font-size:13px;color:#1C1C1A;"></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
			<div>
				<div class="dcprice-big-total" style="font-size:30px;font-weight:700;color:#1C1C1A;"></div>
				<div class="dcprice-total-note" style="font-size:11px;color:#8A8A84;"></div>
			</div>
			<div style="width:1px;height:34px;background:#E4E4E0;"></div>
			<div style="display:flex;align-items:center;gap:10px;">
				<span style="font-size:12px;color:#6B6B64;">Pacotes<br />(de <span class="dcprice-pack-size"></span> un.)</span>
				<div style="display:flex;align-items:stretch;gap:0;border:1px solid #E4E4E0;border-radius:999px;overflow:hidden;">
					<button type="button" class="dcprice-step-minus" style="width:32px;height:32px;border:0;background:#F5821F;color:#fff;font-size:16px;line-height:1;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;">−</button>
					<input type="number" class="dcprice-step-value" min="1" step="1" value="1" style="width:44px;height:32px;text-align:center;text-align-last:center;font-size:14px;font-weight:600;color:#1C1C1A;border:0;padding:0;margin:0;box-sizing:border-box;-moz-appearance:textfield;" />
					<button type="button" class="dcprice-step-plus" style="width:32px;height:32px;border:0;background:#F5821F;color:#fff;font-size:16px;line-height:1;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;">+</button>
				</div>
			</div>
			<div class="dcprice-addtocart-slot" style="display:flex;align-items:center;"></div>
		</div>
		<div class="dcprice-stock-slot"></div>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Painel admin
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', 'dcprice_admin_menu' );
function dcprice_admin_menu() {
	add_submenu_page(
		'woocommerce',
		'Precificação Spot',
		'Precificação Spot',
		'manage_woocommerce',
		'dcprice',
		'dcprice_admin_page'
	);
}

function dcprice_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$notice = '';

	if ( isset( $_POST['dcprice_action'] ) && check_admin_referer( 'dcprice_admin' ) ) {
		if ( 'save_settings' === $_POST['dcprice_action'] ) {
			$settings = array(
				'margem_pct'   => isset( $_POST['margem_pct'] ) ? (float) $_POST['margem_pct'] : 45,
				'imposto_pct'  => isset( $_POST['imposto_pct'] ) ? (float) $_POST['imposto_pct'] : 4,
				'gravacao_pct' => isset( $_POST['gravacao_pct'] ) ? (float) $_POST['gravacao_pct'] : 20,
			);
			update_option( DCPRICE_OPT, $settings, false );
			$notice = '<div class="notice notice-success"><p>Configurações salvas.</p></div>';
		} elseif ( 'refresh_cache' === $_POST['dcprice_action'] ) {
			$result = dcprice_refresh_cache();
			if ( is_wp_error( $result ) ) {
				$notice = '<div class="notice notice-error"><p>Erro: ' . esc_html( $result->get_error_message() ) . '</p></div>';
			} else {
				$notice = '<div class="notice notice-success"><p>Cache atualizado: '
					. (int) $result['refs_with_cost'] . ' referências com custo, '
					. (int) $result['refs_with_custom'] . ' com gravação real (de '
					. (int) $result['refs_no_site'] . ' produtos Spot no site).</p></div>';
			}
		} elseif ( 'write_prices_start' === $_POST['dcprice_action'] ) {
			dcprice_start_price_write();
			dcprice_drain_price_write_queue( 20 );
			$notice = '<div class="notice notice-success"><p>Gravação de preços iniciada.</p></div>';
		} elseif ( 'write_prices_continue' === $_POST['dcprice_action'] ) {
			dcprice_drain_price_write_queue( 20 );
			$notice = '<div class="notice notice-success"><p>Continuando a gravação de preços.</p></div>';
		} else {
			ob_start();
			do_action( 'dcprice_admin_handle_action', $_POST['dcprice_action'] );
			$notice = ob_get_clean();
		}
	}

	$settings   = dcprice_settings();
	$cache_meta = get_option( DCPRICE_CACHE_META_OPT, array() );
	$has_key    = dcprice_spot_access_key() !== '';

	// Exemplo ao vivo (laser em 100 un.: R$0,86/un. na Spot) com os valores atuais.
	$example_cost    = 24.00;
	$example_grav_sp = 0.86;
	$example_g       = $example_grav_sp * ( 1 + $settings['gravacao_pct'] / 100 );
	$example_unit    = ( $example_cost + $example_g ) * ( 1 + $settings['margem_pct'] / 100 ) * ( 1 + $settings['imposto_pct'] / 100 );
	?>
	<div class="wrap">
		<h1>Precificação Spot</h1>
		<?php echo $notice; // phpcs:ignore ?>

		<h2>Fórmula</h2>
		<p>
			Gravação = preço real da Spot (técnica mais barata pra quantidade pedida) × (1 + acréscimo).<br>
			Preço unitário = (custo + gravação) × (1 + margem) × (1 + imposto).
		</p>
		<p class="description">
			Exemplo (custo R$ <?php echo number_format( $example_cost, 2, ',', '.' ); ?>, gravação real da Spot R$ <?php echo number_format( $example_grav_sp, 2, ',', '.' ); ?> em 100 un.):
			gravação com acréscimo R$ <?php echo number_format( $example_g, 2, ',', '.' ); ?>,
			preço final <strong>R$ <?php echo number_format( $example_unit, 2, ',', '.' ); ?></strong>.
		</p>

		<form method="post">
			<?php wp_nonce_field( 'dcprice_admin' ); ?>
			<input type="hidden" name="dcprice_action" value="save_settings" />
			<table class="form-table">
				<tr>
					<th><label for="gravacao_pct">Acréscimo sobre a gravação real da Spot (%)</label></th>
					<td><input type="number" step="0.01" name="gravacao_pct" id="gravacao_pct" value="<?php echo esc_attr( $settings['gravacao_pct'] ); ?>" class="small-text" /> <span class="description">Cobre o custo de buscar/gerenciar a gravação.</span></td>
				</tr>
				<tr>
					<th><label for="margem_pct">Margem de lucro (%)</label></th>
					<td><input type="number" step="0.01" name="margem_pct" id="margem_pct" value="<?php echo esc_attr( $settings['margem_pct'] ); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th><label for="imposto_pct">Imposto — Simples Nacional (%)</label></th>
					<td><input type="number" step="0.01" name="imposto_pct" id="imposto_pct" value="<?php echo esc_attr( $settings['imposto_pct'] ); ?>" class="small-text" /></td>
				</tr>
			</table>
			<?php submit_button( 'Salvar configurações' ); ?>
		</form>

		<hr />

		<h2>Cache de custo da Spot</h2>
		<?php if ( ! $has_key ) : ?>
			<p style="color:#b32d2e;">Chave de acesso da Spot não encontrada — o plugin "DC · Sincronização de Estoque Spot" precisa estar ativo e configurado.</p>
		<?php endif; ?>
		<?php if ( ! empty( $cache_meta ) ) : ?>
			<p>Última atualização: <strong><?php echo esc_html( $cache_meta['updated'] ); ?></strong> —
				<?php echo (int) $cache_meta['refs_with_cost']; ?> referências com custo,
				<?php echo (int) ( $cache_meta['refs_with_custom'] ?? 0 ); ?> com gravação real
				(de <?php echo (int) $cache_meta['refs_no_site']; ?> produtos Spot cadastrados no site).</p>
		<?php else : ?>
			<p>Cache ainda não foi gerado.</p>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'dcprice_admin' ); ?>
			<input type="hidden" name="dcprice_action" value="refresh_cache" />
			<?php submit_button( 'Atualizar cache agora', 'primary', 'submit', false, $has_key ? array() : array( 'disabled' => 'disabled' ) ); ?>
			<p class="description">Baixa o custo do produto + a tabela real de gravação da Spot (pode levar alguns minutos). Rode de novo sempre que a Spot atualizar preços, ou quando novos produtos Spot forem criados no site.</p>
		</form>

		<hr />

		<h2>Gravar preços reais nos produtos</h2>
		<p>Escreve o preço calculado (custo + gravação, na quantidade mínima do produto) direto em <code>_regular_price</code>/<code>_price</code> — deixa de ser só estimativa.</p>
		<?php
		$write_report = get_option( DCPRICE_WRITE_REPORT_OPT, array() );
		$write_queue  = get_option( DCPRICE_WRITE_QUEUE_OPT, array() );
		?>
		<?php if ( ! empty( $write_report ) ) : ?>
			<p>
				<?php echo (int) $write_report['done']; ?> gravados,
				<?php echo (int) $write_report['skipped']; ?> pulados (sem faixa de preço),
				de <?php echo (int) $write_report['total']; ?> produtos no total.
				<?php if ( ! empty( $write_queue ) ) : ?>
					<strong>Ainda faltam <?php echo count( $write_queue ); ?> — clique em "Continuar" pra prosseguir.</strong>
				<?php elseif ( ! empty( $write_report['finished'] ) ) : ?>
					<strong>Concluído em <?php echo esc_html( $write_report['finished'] ); ?>.</strong>
				<?php endif; ?>
			</p>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'dcprice_admin' ); ?>
			<?php if ( ! empty( $write_queue ) ) : ?>
				<input type="hidden" name="dcprice_action" value="write_prices_continue" />
				<?php submit_button( 'Continuar gravação', 'secondary', 'submit', false ); ?>
			<?php else : ?>
				<input type="hidden" name="dcprice_action" value="write_prices_start" />
				<?php submit_button( 'Gravar preços calculados agora', 'primary', 'submit', false, empty( $cache_meta ) ? array( 'disabled' => 'disabled' ) : array() ); ?>
			<?php endif; ?>
			<p class="description">Roda em lotes (a página recarrega e continua sozinha enquanto você estiver no wp-admin). Rode de novo sempre que atualizar o cache ou mudar as porcentagens acima.</p>
		</form>

		<?php do_action( 'dcprice_admin_page_xbz_asia_section' ); ?>

		<hr />
		<p class="description">A calculadora de preço estimado aparece automaticamente na página de cada produto de origem Spot, XBZ ou Asia Import que tiver custo cadastrado (Spot: todas; XBZ/Asia: só nas categorias mapeadas). Ela não altera o fluxo de "Orçar item" sozinha — isso é feito por uma regra separada no plugin de orçamento (Addify).</p>
	</div>
	<?php
}
