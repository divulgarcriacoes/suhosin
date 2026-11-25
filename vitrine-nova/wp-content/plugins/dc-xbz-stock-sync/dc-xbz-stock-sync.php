<?php
/**
 * Plugin Name: DC · Sincronização de Estoque XBZ
 * Description: Sincroniza o estoque dos produtos WooCommerce a partir da API da XBZ (GetListaDeProdutos). Somente estoque — não altera preços. XBZ é a fonte primária; produtos sem correspondência na XBZ nunca são alterados.
 * Version: 1.3.0
 * Author: Divulgar Criações
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * Text Domain: dc-xbz-stock-sync
 *
 * Estratégia (decidida com o cliente):
 *  - Escopo: SOMENTE estoque (o catálogo não trabalha com preço).
 *  - Fornecedor: XBZ é primária. Só mexe em produtos cujo SKU casa com o catálogo XBZ.
 *  - Cor/variação: o estoque do produto do site = SOMA do estoque de todas as cores
 *    daquela família na XBZ (agrupado por CodigoAmigavel / CodigoComposto sem a cor).
 *  - Segurança: produto que não casa com nenhum item XBZ NUNCA é tocado (pode ser
 *    abastecido por outro fornecedor). Estoque negativo na XBZ conta como 0.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DCXBZ_VERSION', '1.3.0' );
define( 'DCXBZ_OPT', 'dcxbz_settings' );
define( 'DCXBZ_REPORT_OPT', 'dcxbz_last_report' );
define( 'DCXBZ_LOG_OPT', 'dcxbz_log' );          // array rolante dos últimos relatórios
define( 'DCXBZ_PLAN_OPT', 'dcxbz_pending_plan' ); // plano de atualização entre lotes
define( 'DCXBZ_CRON_HOOK', 'dcxbz_cron_sync' );
define( 'DCXBZ_CHUNK_HOOK', 'dcxbz_process_chunk' );
define( 'DCXBZ_LOG_KEEP', 30 );
define( 'DCXBZ_CHUNK_SIZE', 100 );
define( 'DCXBZ_CREATE_QUEUE_OPT', 'dcxbz_create_queue' );   // fila de produtos a criar
define( 'DCXBZ_CREATE_REPORT_OPT', 'dcxbz_create_report' ); // relatório da criação
define( 'DCXBZ_IMG_QUEUE_OPT', 'dcxbz_img_queue' );         // fila de backfill de imagem (via URL)
define( 'DCXBZ_IMG_REPORT_OPT', 'dcxbz_img_report' );       // relatório do backfill de imagem
define( 'DCXBZ_IMPORT_QUEUE_OPT', 'dcxbz_import_queue' );   // fila de import de imagem via ZIP local
define( 'DCXBZ_IMPORT_REPORT_OPT', 'dcxbz_import_report' ); // relatório do import via ZIP
define( 'DCXBZ_MAINT_QUEUE_OPT', 'dcxbz_maint_queue' );     // fila de manutenção (preço 0 / lixeira)
define( 'DCXBZ_MAINT_REPORT_OPT', 'dcxbz_maint_report' );

/* -------------------------------------------------------------------------
 * Configurações
 * ---------------------------------------------------------------------- */

function dcxbz_defaults() {
	return array(
		'base_url'          => 'https://api.minhaxbz.com.br:5001/api/clientes',
		'cnpj'              => '',
		'token'             => '',
		'frequency'         => 'twicedaily', // hourly | twicedaily | daily
		'set_out_of_stock'  => 0,   // marcar "fora de estoque" quando soma <= limite (começa DESLIGADO: ver nota no painel)
		'oos_threshold'     => 0,   // limite: soma <= isto => fora de estoque
		'manage_stock'      => 1,   // liga o controle de estoque do produto ao atualizar
		'match_amigavel'    => 1,   // casar por CodigoAmigavel
		'match_composto'    => 1,   // casar por CodigoComposto sem a cor
		'dry_run'           => 1,   // começa em simulação — nada é gravado
		'enabled'           => 0,   // agendamento automático desligado até o cliente revisar

		// --- Criação de produtos novos (famílias XBZ que não existem no site) ---
		'create_new'        => 0,   // MASTER: criar produtos novos (começa DESLIGADO)
		'create_publish'    => 1,   // publicar direto (senão fica rascunho)
		'create_image'      => 1,   // baixar a imagem principal da XBZ
		'create_min_stock'  => 1,   // só cria família com soma de estoque >= isto
		'create_batch'      => 15,  // quantos criar por passada (imagem é lenta)
		'create_cat_default' => 'Novidades',
		'create_cat_rules'  => "caneta => CANETAS\ncaneca => CANECAS\nsqueeze => SQUEEZES E GARRAFAS\ngarrafa => SQUEEZES E GARRAFAS\ngalão => SQUEEZES E GARRAFAS\ngalao => SQUEEZES E GARRAFAS\ncopo => COPOS\nmochila => BOLSAS\nsacola => BOLSAS\nbolsa => BOLSAS\nnecessaire => BOLSAS\nmala => BOLSAS\npochete => BOLSAS\nchaveiro => CHAVEIROS\ncaderno => BLOCOS E CADERNETAS\ncaderneta => BLOCOS E CADERNETAS\nbloco => BLOCOS E CADERNETAS\nagenda => BLOCOS E CADERNETAS\npower bank => CARREGADORES POWER BANK\ncarregador => CARREGADORES POWER BANK\nfone => FONES DE OUVIDO\ncaixa de som => CAIXAS DE SOM\npen drive => PEN DRIVES\nmouse pad => Informática\nmousepad => Informática\nlanterna => LANTERNAS E LUMINÁRIAS\nespelho => Linha Feminina\nescova => Linha Feminina\ntermômetro => CUIDADOS PESSOAIS\ntermometro => CUIDADOS PESSOAIS\nfita métrica => FERRAMENTAS\nfita metrica => FERRAMENTAS\ntrena => FERRAMENTAS\ncanivete => FERRAMENTAS\nporta retrato => Brindes\nporta-retrato => Brindes\nkit churrasco => KIT CHURRASCO\nkit vinho => Kit Vinho\nkit => Brindes\ntabua => COZINHA\nlápis => Lápis e Lapiseiras\nlapiseira => Lápis e Lapiseiras\necológic => Linha Ecológica\necologic => Linha Ecológica\nsombrinha => Brindes\nguarda-chuva => Brindes",
	);
}

function dcxbz_get_settings() {
	$s = get_option( DCXBZ_OPT, array() );
	if ( ! is_array( $s ) ) {
		$s = array();
	}
	return wp_parse_args( $s, dcxbz_defaults() );
}

/* -------------------------------------------------------------------------
 * Ativação / Desativação
 * ---------------------------------------------------------------------- */

register_activation_hook( __FILE__, 'dcxbz_activate' );
function dcxbz_activate() {
	add_option( DCXBZ_OPT, dcxbz_defaults() );
	dcxbz_reschedule();
}

register_deactivation_hook( __FILE__, 'dcxbz_deactivate' );
function dcxbz_deactivate() {
	wp_clear_scheduled_hook( DCXBZ_CRON_HOOK );
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( DCXBZ_CRON_HOOK );
		as_unschedule_all_actions( DCXBZ_CHUNK_HOOK );
	}
}

/**
 * (Re)agenda o evento recorrente conforme a configuração.
 */
function dcxbz_reschedule() {
	$s = dcxbz_get_settings();
	wp_clear_scheduled_hook( DCXBZ_CRON_HOOK );
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( DCXBZ_CRON_HOOK );
	}
	if ( empty( $s['enabled'] ) ) {
		return;
	}
	$freq      = in_array( $s['frequency'], array( 'hourly', 'twicedaily', 'daily' ), true ) ? $s['frequency'] : 'twicedaily';
	$intervals = array( 'hourly' => HOUR_IN_SECONDS, 'twicedaily' => 12 * HOUR_IN_SECONDS, 'daily' => DAY_IN_SECONDS );

	if ( function_exists( 'as_schedule_recurring_action' ) ) {
		as_schedule_recurring_action( time() + 300, $intervals[ $freq ], DCXBZ_CRON_HOOK, array(), 'dcxbz' );
	} else {
		wp_schedule_event( time() + 300, $freq, DCXBZ_CRON_HOOK );
	}
}

/* -------------------------------------------------------------------------
 * Cron -> sincronização
 * ---------------------------------------------------------------------- */

add_action( DCXBZ_CRON_HOOK, 'dcxbz_run_sync' );

/**
 * Executa uma sincronização completa.
 *
 * @param bool|null $force_dry Se null usa a config; senão força simulação (true) ou gravação (false).
 * @return array Relatório.
 */
function dcxbz_run_sync( $force_dry = null ) {
	@set_time_limit( 0 );
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}

	$s      = dcxbz_get_settings();
	$dry    = is_null( $force_dry ) ? ! empty( $s['dry_run'] ) : (bool) $force_dry;
	$report = array(
		'started'      => current_time( 'mysql' ),
		'finished'     => '',
		'dry_run'      => $dry,
		'ok'           => false,
		'message'      => '',
		'xbz_items'    => 0,
		'xbz_families' => 0,
		'sku_products' => 0,
		'matched'      => 0,
		'changed'      => 0,
		'applied'      => 0,
		'unmatched'    => 0,
		'samples'      => array(), // amostras de mudanças previstas
	);

	if ( ! function_exists( 'wc_get_product' ) ) {
		$report['message'] = 'WooCommerce não está ativo.';
		return dcxbz_store_report( $report );
	}
	if ( empty( $s['cnpj'] ) || empty( $s['token'] ) ) {
		$report['message'] = 'CNPJ ou token não configurados.';
		return dcxbz_store_report( $report );
	}

	$catalog = dcxbz_fetch_catalog( $s );
	if ( is_wp_error( $catalog ) ) {
		$report['message'] = 'Falha ao buscar catálogo XBZ: ' . $catalog->get_error_message();
		return dcxbz_store_report( $report );
	}
	$report['xbz_items'] = count( $catalog );

	$index = dcxbz_build_index( $catalog, $s );
	// Tabela "Cores disponíveis": estoque cor por cor (mu-plugin dc-cores-estoque-sync).
	$report['cores'] = function_exists( 'dc_cores_atualizar' ) ? dc_cores_atualizar( dcce_mapa_xbz( $catalog ), '_dcxbz_source', $dry ) : array();
	// Data em que cada produto foi visto no catálogo (mu-plugin dc-estoque-sob-consulta).
	if ( ! $dry && function_exists( 'dc_forn_marcar_vistos' ) && count( $index ) > 1000 ) {
		$report['vistos'] = dc_forn_marcar_vistos( 'xbz', function ( $c ) use ( $index, $s ) {
			return null !== dcxbz_lookup( $c, $index, $s );
		} );
	}
	// Metadados por família só quando a criação de produtos novos está ligada.
	$fam_meta = ! empty( $s['create_new'] ) ? dcxbz_build_family_meta( $catalog ) : array();
	unset( $catalog );
	$report['xbz_families'] = count( $index );

	// SKU + estoque atual numa consulta só (sem carregar objetos de produto na
	// fase de leitura — só o lote de gravação carrega os produtos).
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT pm.post_id                         AS id,
		        pm.meta_value                      AS sku,
		        COALESCE( ms.meta_value, '' )       AS manage_stock,
		        st.meta_value                      AS stock,
		        COALESCE( ss.meta_value, 'instock' ) AS stock_status
		   FROM {$wpdb->postmeta} pm
		   JOIN {$wpdb->posts} p     ON p.ID = pm.post_id
		   LEFT JOIN {$wpdb->postmeta} ms ON ms.post_id = pm.post_id AND ms.meta_key = '_manage_stock'
		   LEFT JOIN {$wpdb->postmeta} st ON st.post_id = pm.post_id AND st.meta_key = '_stock'
		   LEFT JOIN {$wpdb->postmeta} ss ON ss.post_id = pm.post_id AND ss.meta_key = '_stock_status'
		  WHERE pm.meta_key = '_sku'
		    AND pm.meta_value <> ''
		    AND p.post_type IN ('product','product_variation')
		    AND p.post_status = 'publish'"
	);
	$report['sku_products'] = count( $rows );

	$plan      = array();
	$site_keys = array(); // p/ criação: SKUs do site normalizados + versão sem cor
	foreach ( $rows as $r ) {
		if ( ! empty( $s['create_new'] ) ) {
			$nk = dcxbz_norm( $r->sku );
			if ( '' !== $nk ) {
				$site_keys[ $nk ] = true;
				$site_keys[ preg_replace( '#-[A-Z]{2,4}(?:/[A-Z]{2,4})?$#', '', $nk ) ] = true;
			}
		}
		$hit = dcxbz_lookup( $r->sku, $index, $s );
		if ( null === $hit ) {
			$report['unmatched']++;
			continue;
		}
		$report['matched']++;

		$new_qty     = max( 0, (int) $hit['qty'] );
		$cur_managed = ( 'yes' === $r->manage_stock );
		$cur_qty     = ( null === $r->stock || '' === $r->stock ) ? null : (int) $r->stock;
		$cur_status  = $r->stock_status ? $r->stock_status : 'instock';
		$keep_avail  = empty( $s['set_out_of_stock'] );

		if ( $keep_avail && $new_qty <= 0 ) {
			// Regra: XBZ zerado NÃO derruba o produto — desliga controle, força instock.
			$want_status = 'instock';
			$needs       = $cur_managed || ( 'instock' !== $cur_status );
		} elseif ( ! empty( $s['set_out_of_stock'] ) ) {
			$want_status = ( $new_qty <= (int) $s['oos_threshold'] ) ? 'outofstock' : 'instock';
			$needs       = ( $cur_qty !== $new_qty )
				|| ( ! empty( $s['manage_stock'] ) && ! $cur_managed )
				|| ( $cur_status !== $want_status );
		} else {
			$want_status = 'instock'; // opção desligada => sempre instock
			$needs       = ( $cur_qty !== $new_qty )
				|| ( ! empty( $s['manage_stock'] ) && ! $cur_managed )
				|| ( 'instock' !== $cur_status );
		}

		if ( ! $needs ) {
			continue;
		}
		$report['changed']++;
		$plan[] = array(
			'id'     => (int) $r->id,
			'sku'    => $r->sku,
			'from'   => is_null( $cur_qty ) ? '—' : (int) $cur_qty,
			'to'     => $new_qty,
			'status' => $want_status,
			'key'    => $hit['key'],
			'colors' => $hit['colors'],
		);
		if ( count( $report['samples'] ) < 40 ) {
			$report['samples'][] = $plan[ count( $plan ) - 1 ];
		}
	}

	// --- Produtos novos: monta a fila (famílias XBZ ausentes no site) ---
	$report['create_candidates'] = 0;
	$report['create_by_cat']     = array();
	if ( ! empty( $s['create_new'] ) && $fam_meta ) {
		$cqueue = dcxbz_build_create_queue( $fam_meta, $site_keys, $s );
		$report['create_candidates'] = count( $cqueue );
		foreach ( $cqueue as $c ) {
			$report['create_by_cat'][ $c['cat'] ] = ( isset( $report['create_by_cat'][ $c['cat'] ] ) ? $report['create_by_cat'][ $c['cat'] ] : 0 ) + 1;
		}
		arsort( $report['create_by_cat'] );
		if ( ! $dry ) {
			update_option( DCXBZ_CREATE_QUEUE_OPT, $cqueue, false );
			update_option( DCXBZ_CREATE_REPORT_OPT, array( 'created' => 0, 'skipped' => 0, 'errors' => 0, 'last' => array(), 'when' => current_time( 'mysql' ) ), false );
		}
	}

	$report['ok'] = true;

	if ( $dry ) {
		$report['message']  = 'Simulação concluída. Nenhuma alteração gravada.';
		$report['finished'] = current_time( 'mysql' );
		return dcxbz_store_report( $report );
	}

	// Gravação: aplica AGORA, no mesmo request, em loop com orçamento de tempo.
	// O que não couber fica em DCXBZ_PLAN_OPT e é drenado nos próximos
	// carregamentos de tela (admin_init) — sem depender do Action Scheduler.
	update_option( DCXBZ_PLAN_OPT, $plan, false );
	$report['finished'] = current_time( 'mysql' );
	dcxbz_store_report( $report );

	$applied = dcxbz_drain_plan( 40 );
	$rest    = count( get_option( DCXBZ_PLAN_OPT, array() ) );

	// Criação de produtos novos (se ligada) — primeira leva agora, resto no admin_init.
	$created = 0;
	$crest   = 0;
	if ( ! empty( $s['create_new'] ) && get_option( DCXBZ_CREATE_QUEUE_OPT ) ) {
		$created = dcxbz_drain_create_queue( 40 );
		$crest   = count( get_option( DCXBZ_CREATE_QUEUE_OPT, array() ) );
	}

	// Auto-recuperação de imagens: se há produtos do plugin sem foto e nenhuma fila
	// ativa, reconstrói a fila de backfill via URL e tenta um lote. Assim, quando o
	// bloqueio do CDN da XBZ expirar, o cron sozinho vai preenchendo as imagens e
	// republicando os rascunhos, sem intervenção manual.
	if ( ! get_option( DCXBZ_IMG_QUEUE_OPT ) && ! get_option( DCXBZ_IMPORT_QUEUE_OPT ) ) {
		global $wpdb;
		$pending_img = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			   FROM {$wpdb->posts} p
			   JOIN {$wpdb->postmeta} src ON src.post_id = p.ID AND src.meta_key = '_dcxbz_source'
			   LEFT JOIN {$wpdb->postmeta} th ON th.post_id = p.ID AND th.meta_key = '_thumbnail_id'
			  WHERE p.post_type = 'product' AND p.post_status IN ('publish','draft') AND th.meta_id IS NULL"
		);
		if ( $pending_img > 0 ) {
			dcxbz_build_img_queue();
			dcxbz_drain_img_queue( 30 );
		}
	} elseif ( get_option( DCXBZ_IMG_QUEUE_OPT ) ) {
		dcxbz_drain_img_queue( 30 );
	}

	$report            = get_option( DCXBZ_REPORT_OPT, array() );
	$report['applied'] = ( isset( $report['applied'] ) ? (int) $report['applied'] : 0 );
	$msg               = $rest > 0
		? sprintf( '%d produto(s) de estoque atualizado(s); faltam %d.', $report['applied'], $rest )
		: sprintf( '%d produto(s) de estoque atualizado(s).', $report['applied'] );
	if ( ! empty( $s['create_new'] ) ) {
		$cr  = get_option( DCXBZ_CREATE_REPORT_OPT, array() );
		$crd = isset( $cr['created'] ) ? (int) $cr['created'] : 0;
		$msg .= $crest > 0
			? sprintf( ' Novos: %d criado(s), faltam %d — continuam ao abrir o painel.', $crd, $crest )
			: sprintf( ' Novos: %d criado(s). Concluído.', $crd );
	} else {
		$msg .= $rest > 0 ? ' Reabra o painel para concluir.' : ' Concluído.';
	}
	$report['message'] = $msg;
	update_option( DCXBZ_REPORT_OPT, $report, false );

	return $report;
}

/**
 * Aplica o plano pendente (DCXBZ_PLAN_OPT) até acabar o tempo ou a lista.
 * Idempotente: pode ser chamada várias vezes até zerar o plano.
 *
 * @param int $budget_seconds Tempo máximo nesta passada.
 * @return int Quantidade aplicada nesta passada.
 */
function dcxbz_drain_plan( $budget_seconds = 30 ) {
	$plan = get_option( DCXBZ_PLAN_OPT, array() );
	if ( ! is_array( $plan ) || empty( $plan ) || ! function_exists( 'wc_update_product_stock' ) ) {
		return 0;
	}
	@set_time_limit( 0 );
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}

	$s       = dcxbz_get_settings();
	$start   = microtime( true );
	$done    = 0;
	$manage  = ! empty( $s['manage_stock'] );

	$keep_available = empty( $s['set_out_of_stock'] ); // regra: XBZ zerado NÃO derruba o produto

	while ( ! empty( $plan ) && ( microtime( true ) - $start ) < $budget_seconds ) {
		$row     = array_shift( $plan );
		$id      = (int) $row['id'];
		$new_qty = (int) $row['to'];

		if ( $keep_available && $new_qty <= 0 ) {
			// Sem estoque na XBZ e não queremos marcar indisponível:
			// desliga o controle de estoque e força "em estoque" via meta direto
			// (assim o WooCommerce não recalcula o status a partir da quantidade 0).
			update_post_meta( $id, '_manage_stock', 'no' );
			update_post_meta( $id, '_stock', '' );
			update_post_meta( $id, '_stock_status', 'instock' );
			delete_post_meta( $id, '_low_stock_amount' );
		} else {
			if ( $manage && 'yes' !== get_post_meta( $id, '_manage_stock', true ) ) {
				update_post_meta( $id, '_manage_stock', 'yes' );
			}
			if ( 'yes' === get_post_meta( $id, '_manage_stock', true ) ) {
				wc_update_product_stock( $id, $new_qty, 'set' );
			}
			// Reconcilia o status: opção LIGADA usa o calculado; DESLIGADA => instock.
			$desired = empty( $s['set_out_of_stock'] ) ? 'instock' : $row['status'];
			$cur     = get_post_meta( $id, '_stock_status', true );
			if ( $cur !== $desired ) {
				update_post_meta( $id, '_stock_status', $desired );
			}
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $id );
		}
		$done++;

		// Persiste o restante a cada 25 itens (resiliência a timeout).
		if ( 0 === $done % 25 ) {
			update_option( DCXBZ_PLAN_OPT, array_values( $plan ), false );
		}
	}

	if ( empty( $plan ) ) {
		delete_option( DCXBZ_PLAN_OPT );
	} else {
		update_option( DCXBZ_PLAN_OPT, array_values( $plan ), false );
	}

	$rep = get_option( DCXBZ_REPORT_OPT, array() );
	if ( is_array( $rep ) ) {
		$rep['applied'] = ( isset( $rep['applied'] ) ? (int) $rep['applied'] : 0 ) + $done;
		update_option( DCXBZ_REPORT_OPT, $rep, false );
	}
	return $done;
}

// Drena o que sobrou sempre que um admin abre uma tela (sem Action Scheduler).
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	if ( get_option( DCXBZ_PLAN_OPT ) ) {
		dcxbz_drain_plan( 20 );
	}
	if ( get_option( DCXBZ_CREATE_QUEUE_OPT ) ) {
		dcxbz_drain_create_queue( 20 );
	}
	if ( get_option( DCXBZ_IMG_QUEUE_OPT ) ) {
		dcxbz_drain_img_queue( 20 );
	}
	if ( get_option( DCXBZ_IMPORT_QUEUE_OPT ) ) {
		dcxbz_drain_import_queue( 20 );
	}
	if ( get_option( DCXBZ_MAINT_QUEUE_OPT ) ) {
		dcxbz_drain_maint_queue( 20 );
	}
} );

/* =========================================================================
 * CRIAÇÃO DE PRODUTOS NOVOS (famílias XBZ ausentes no site)
 * ====================================================================== */

/**
 * Metadados por família (CodigoAmigavel) para criar produtos.
 * @return array [ famKey => {name,desc,image,site,weight,h,w,d,ncm,qty,colors} ]
 */
function dcxbz_build_family_meta( $catalog ) {
	$fam = array();
	foreach ( $catalog as $p ) {
		$key = dcxbz_norm( isset( $p['CodigoAmigavel'] ) ? $p['CodigoAmigavel'] : '' );
		if ( '' === $key ) {
			continue;
		}
		$qty = isset( $p['QuantidadeDisponivel'] ) ? (int) $p['QuantidadeDisponivel'] : 0;
		if ( $qty < 0 ) {
			$qty = 0;
		}
		if ( ! isset( $fam[ $key ] ) ) {
			$fam[ $key ] = array(
				'name'   => trim( (string) ( isset( $p['Nome'] ) ? $p['Nome'] : '' ) ),
				'desc'   => trim( (string) ( isset( $p['Descricao'] ) ? $p['Descricao'] : '' ) ),
				'image'  => (string) ( isset( $p['ImageLink'] ) ? $p['ImageLink'] : '' ),
				'site'   => (string) ( isset( $p['SiteLink'] ) ? $p['SiteLink'] : '' ),
				'weight' => (float) ( isset( $p['Peso'] ) ? $p['Peso'] : 0 ),
				'h'      => (float) ( isset( $p['Altura'] ) ? $p['Altura'] : 0 ),
				'w'      => (float) ( isset( $p['Largura'] ) ? $p['Largura'] : 0 ),
				'd'      => (float) ( isset( $p['Profundidade'] ) ? $p['Profundidade'] : 0 ),
				'ncm'    => (string) ( isset( $p['Ncm'] ) ? $p['Ncm'] : '' ),
				'qty'    => 0,
				'colors' => 0,
			);
		}
		$fam[ $key ]['qty']    += $qty;
		$fam[ $key ]['colors'] += 1;
		if ( '' === $fam[ $key ]['image'] && ! empty( $p['ImageLink'] ) ) {
			$fam[ $key ]['image'] = (string) $p['ImageLink'];
		}
		if ( '' === $fam[ $key ]['desc'] && ! empty( $p['Descricao'] ) ) {
			$fam[ $key ]['desc'] = trim( (string) $p['Descricao'] );
		}
	}
	return $fam;
}

/** "MOCHILA POLIÉSTER 23L" -> "Mochila Poliéster 23L" */
function dcxbz_title_case( $str ) {
	$str = mb_convert_case( mb_strtolower( trim( $str ), 'UTF-8' ), MB_CASE_TITLE, 'UTF-8' );
	return preg_replace_callback(
		'/\b(De|Com|E|Para|Da|Do|Das|Dos|Em|A|O)\b/u',
		function ( $m ) {
			return mb_strtolower( $m[1], 'UTF-8' );
		},
		$str
	);
}

/** Converte o textarea "termo => Categoria" em pares [termo_lower, Categoria]. */
function dcxbz_parse_cat_rules( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || 0 === strpos( $line, '#' ) ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '=>', $line, 2 ) );
		if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
			$out[] = array( mb_strtolower( $parts[0], 'UTF-8' ), $parts[1] );
		}
	}
	return $out;
}

function dcxbz_match_category( $name, $rules, $default ) {
	$n = mb_strtolower( (string) $name, 'UTF-8' );
	foreach ( $rules as $r ) {
		if ( '' !== $r[0] && false !== mb_strpos( $n, $r[0] ) ) {
			return $r[1];
		}
	}
	return $default;
}

function dcxbz_get_or_create_term( $name ) {
	$name = trim( (string) $name );
	if ( '' === $name ) {
		return 0;
	}
	$t = get_term_by( 'name', $name, 'product_cat' );
	if ( $t ) {
		return (int) $t->term_id;
	}
	$t = get_term_by( 'slug', sanitize_title( $name ), 'product_cat' );
	if ( $t ) {
		return (int) $t->term_id;
	}
	$r = wp_insert_term( $name, 'product_cat' );
	return is_wp_error( $r ) ? 0 : (int) $r['term_id'];
}

/**
 * Monta a fila de criação: famílias XBZ (CodigoAmigavel) sem SKU correspondente
 * no site e com estoque >= create_min_stock.
 *
 * @param array $fam_meta  saída de dcxbz_build_family_meta()
 * @param array $site_keys  conjunto (chave => true) de SKUs do site normalizados + sem cor
 * @param array $s          settings
 * @return array            fila (lista de itens prontos p/ criar)
 */
function dcxbz_build_create_queue( $fam_meta, $site_keys, $s ) {
	$rules   = dcxbz_parse_cat_rules( $s['create_cat_rules'] );
	$default = $s['create_cat_default'] ? $s['create_cat_default'] : 'Novidades';
	$min     = max( 0, (int) $s['create_min_stock'] );
	$queue   = array();

	foreach ( $fam_meta as $key => $m ) {
		if ( isset( $site_keys[ $key ] ) || '' === $m['name'] ) {
			continue;
		}
		if ( $m['qty'] < $min ) {
			continue;
		}
		$queue[] = array(
			'key'    => $key,
			'name'   => $m['name'],
			'desc'   => $m['desc'],
			'image'  => $m['image'],
			'site'   => $m['site'],
			'weight' => $m['weight'],
			'h'      => $m['h'],
			'w'      => $m['w'],
			'd'      => $m['d'],
			'ncm'    => $m['ncm'],
			'qty'    => $m['qty'],
			'colors' => $m['colors'],
			'cat'    => dcxbz_match_category( $m['name'], $rules, $default ),
		);
	}
	return $queue;
}

/**
 * Processa a fila de criação (DCXBZ_CREATE_QUEUE_OPT) dentro de um orçamento de tempo.
 * Idempotente e resumível (o hook admin_init chama de novo).
 *
 * @return int criados/pulados nesta passada
 */
function dcxbz_drain_create_queue( $budget_seconds = 40 ) {
	$q = get_option( DCXBZ_CREATE_QUEUE_OPT, array() );
	if ( ! is_array( $q ) || empty( $q ) || ! class_exists( 'WC_Product_Simple' ) ) {
		return 0;
	}
	@set_time_limit( 0 );
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$s        = dcxbz_get_settings();
	$publish  = ! empty( $s['create_publish'] );
	$with_img = ! empty( $s['create_image'] );
	$start    = microtime( true );
	$done     = 0;
	$rep      = get_option( DCXBZ_CREATE_REPORT_OPT, array() );
	if ( ! is_array( $rep ) ) {
		$rep = array();
	}
	$rep += array( 'created' => 0, 'skipped' => 0, 'errors' => 0, 'last' => array(), 'when' => current_time( 'mysql' ) );

	while ( ! empty( $q ) && ( microtime( true ) - $start ) < $budget_seconds ) {
		$c   = array_shift( $q );
		$sku = (string) $c['key'];

		if ( '' === $sku || wc_get_product_id_by_sku( $sku ) || ( function_exists( 'dc_sku_bloqueado' ) && dc_sku_bloqueado( $sku ) ) ) {
			$rep['skipped']++;
			$done++;
			continue;
		}

		try {
			$p = new WC_Product_Simple();
			$p->set_name( dcxbz_title_case( $c['name'] ) );
			$p->set_sku( $sku );
			$p->set_status( $publish ? 'publish' : 'draft' );
			$p->set_catalog_visibility( 'visible' );
			if ( ! empty( $c['desc'] ) ) {
				$p->set_description( $c['desc'] );
			}

			$qty = max( 0, (int) $c['qty'] );
			if ( $qty > 0 ) {
				$p->set_manage_stock( true );
				$p->set_stock_quantity( $qty );
			} else {
				$p->set_manage_stock( false );
			}
			$p->set_stock_status( 'instock' ); // regra do projeto: não derruba produto

			if ( $c['weight'] > 0 ) {
				$p->set_weight( round( $c['weight'] / 1000, 3 ) ); // XBZ Peso em gramas -> kg
			}
			if ( $c['h'] > 0 ) {
				$p->set_height( $c['h'] );
			}
			if ( $c['w'] > 0 ) {
				$p->set_width( $c['w'] );
			}
			if ( $c['d'] > 0 ) {
				$p->set_length( $c['d'] );
			}

			$term_id = dcxbz_get_or_create_term( $c['cat'] );
			if ( $term_id ) {
				$p->set_category_ids( array( $term_id ) );
			}

			$id = $p->save();
		} catch ( Exception $e ) {
			$rep['errors']++;
			$done++;
			continue;
		}

		if ( ! $id ) {
			$rep['errors']++;
			$done++;
			continue;
		}

		update_post_meta( $id, '_dcxbz_source', 'xbz' );
		update_post_meta( $id, '_dcxbz_created', current_time( 'mysql' ) );
		if ( ! empty( $c['ncm'] ) ) {
			update_post_meta( $id, '_dcxbz_ncm', $c['ncm'] );
		}
		if ( ! empty( $c['site'] ) ) {
			update_post_meta( $id, '_dcxbz_site_link', esc_url_raw( $c['site'] ) );
		}

		$got_img = false;
		if ( $with_img && ! empty( $c['image'] ) ) {
			$att = media_sideload_image( $c['image'], $id, $c['name'], 'id' );
			if ( ! is_wp_error( $att ) ) {
				set_post_thumbnail( $id, $att );
				$got_img = true;
			}
		}
		// Guarda a URL da imagem e marca para backfill se não baixou agora.
		if ( ! empty( $c['image'] ) ) {
			update_post_meta( $id, '_dcxbz_image_url', esc_url_raw( $c['image'] ) );
		}
		if ( $with_img && ! empty( $c['image'] ) && ! $got_img ) {
			update_post_meta( $id, '_dcxbz_needs_image', 1 );
			// Sem imagem: não deixa publicado — vira rascunho até ter foto.
			if ( $publish ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
			}
		}
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $id );
		}

		$rep['created']++;
		array_unshift(
			$rep['last'],
			array( 'sku' => $sku, 'name' => dcxbz_title_case( $c['name'] ), 'cat' => $c['cat'], 'id' => (int) $id, 'qty' => $qty )
		);
		$rep['last'] = array_slice( $rep['last'], 0, 25 );
		$done++;

		if ( 0 === $done % 10 ) {
			update_option( DCXBZ_CREATE_QUEUE_OPT, array_values( $q ), false );
		}
	}

	if ( empty( $q ) ) {
		delete_option( DCXBZ_CREATE_QUEUE_OPT );
	} else {
		update_option( DCXBZ_CREATE_QUEUE_OPT, array_values( $q ), false );
	}
	update_option( DCXBZ_CREATE_REPORT_OPT, $rep, false );
	return $done;
}

/* -------------------------------------------------------------------------
 * Backfill de imagens (produtos _dcxbz_source=xbz sem imagem destacada)
 * ---------------------------------------------------------------------- */

/**
 * Monta a fila de backfill: produtos criados pelo plugin, sem _thumbnail_id.
 * A URL vem do meta _dcxbz_image_url; se faltar, casa pelo _sku no catálogo XBZ.
 *
 * @return int quantos entraram na fila
 */
function dcxbz_build_img_queue() {
	global $wpdb;
	@ini_set( 'memory_limit', '512M' );

	$ids = $wpdb->get_col(
		"SELECT p.ID
		   FROM {$wpdb->posts} p
		   JOIN {$wpdb->postmeta} src ON src.post_id = p.ID AND src.meta_key = '_dcxbz_source'
		   LEFT JOIN {$wpdb->postmeta} th ON th.post_id = p.ID AND th.meta_key = '_thumbnail_id'
		  WHERE p.post_type = 'product'
		    AND p.post_status IN ('publish','draft')
		    AND th.meta_id IS NULL"
	);
	if ( ! $ids ) {
		update_option( DCXBZ_IMG_QUEUE_OPT, array(), false );
		return 0;
	}

	// URLs já guardadas.
	$queue    = array();
	$need_sku = array();
	foreach ( $ids as $id ) {
		$id  = (int) $id;
		$url = get_post_meta( $id, '_dcxbz_image_url', true );
		if ( $url ) {
			$queue[] = array( 'id' => $id, 'url' => $url );
		} else {
			$need_sku[ $id ] = get_post_meta( $id, '_sku', true );
		}
	}

	// Para os sem URL, busca o catálogo e casa pelo CodigoAmigavel.
	if ( $need_sku ) {
		$s = dcxbz_get_settings();
		$c = dcxbz_fetch_catalog( $s );
		if ( ! is_wp_error( $c ) ) {
			$fam = dcxbz_build_family_meta( $c );
			unset( $c );
			foreach ( $need_sku as $id => $sku ) {
				$k = dcxbz_norm( $sku );
				if ( '' !== $k && ! empty( $fam[ $k ]['image'] ) ) {
					$queue[] = array( 'id' => (int) $id, 'url' => $fam[ $k ]['image'] );
				}
			}
		}
	}

	update_option( DCXBZ_IMG_QUEUE_OPT, array_values( $queue ), false );
	update_option( DCXBZ_IMG_REPORT_OPT, array( 'ok' => 0, 'fail' => 0, 'republished' => 0, 'when' => current_time( 'mysql' ) ), false );
	return count( $queue );
}

/**
 * Processa a fila de backfill dentro de um orçamento de tempo. Resumível.
 * Ao conseguir a imagem: define a destacada, limpa _dcxbz_needs_image e, se o
 * produto estiver em rascunho por falta de foto, republica.
 *
 * @return int processados nesta passada
 */
function dcxbz_drain_img_queue( $budget_seconds = 40 ) {
	$q = get_option( DCXBZ_IMG_QUEUE_OPT, array() );
	if ( ! is_array( $q ) || empty( $q ) ) {
		return 0;
	}
	@set_time_limit( 0 );
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$s     = dcxbz_get_settings();
	$pub   = ! empty( $s['create_publish'] );
	$start = microtime( true );
	$done  = 0;
	$retry = array();
	$rep   = get_option( DCXBZ_IMG_REPORT_OPT, array() );
	if ( ! is_array( $rep ) ) {
		$rep = array();
	}
	$rep += array( 'ok' => 0, 'fail' => 0, 'republished' => 0, 'dropped' => 0, 'errors_sample' => array(), 'when' => current_time( 'mysql' ) );

	// User-agent de navegador para o download da imagem (CDN/Cloudflare costuma
	// bloquear UA vazio ou "WordPress").
	$ua = function () {
		return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
	};
	add_filter( 'http_headers_useragent', $ua, 99 );

	while ( ! empty( $q ) && ( microtime( true ) - $start ) < $budget_seconds ) {
		$row      = array_shift( $q );
		$id       = (int) $row['id'];
		$url      = (string) $row['url'];
		$attempts = isset( $row['attempts'] ) ? (int) $row['attempts'] : 0;

		if ( get_post_thumbnail_id( $id ) ) { // já tem
			$done++;
			continue;
		}
		$att = '' !== $url ? media_sideload_image( $url, $id, null, 'id' ) : new WP_Error( 'no_url', 'sem url' );
		if ( is_wp_error( $att ) ) {
			$rep['fail']++;
			$msg = $att->get_error_code() . ': ' . $att->get_error_message();
			if ( count( $rep['errors_sample'] ) < 8 && ! in_array( $msg, $rep['errors_sample'], true ) ) {
				$rep['errors_sample'][] = $msg;
			}
			if ( $attempts < 3 ) {
				$row['attempts'] = $attempts + 1;
				$retry[]         = $row;
			} else {
				$rep['dropped']++;
			}
			$done++;
			usleep( 300000 ); // 0,3s — não martelar o CDN
			continue;
		}
		set_post_thumbnail( $id, $att );
		delete_post_meta( $id, '_dcxbz_needs_image' );
		$rep['ok']++;

		if ( $pub && 'draft' === get_post_status( $id ) ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
			$rep['republished']++;
		}
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $id );
		}
		$done++;
		usleep( 150000 ); // 0,15s

		if ( 0 === $done % 10 ) {
			update_option( DCXBZ_IMG_QUEUE_OPT, array_merge( array_values( $q ), $retry ), false );
		}
	}

	remove_filter( 'http_headers_useragent', $ua, 99 );

	$q = array_merge( array_values( $q ), $retry ); // falhas voltam pro fim da fila
	if ( empty( $q ) ) {
		delete_option( DCXBZ_IMG_QUEUE_OPT );
	} else {
		update_option( DCXBZ_IMG_QUEUE_OPT, $q, false );
	}
	update_option( DCXBZ_IMG_REPORT_OPT, $rep, false );
	return $done;
}

/**
 * Rebaixa para rascunho todo produto _dcxbz_source=xbz publicado e sem imagem.
 * Válvula de segurança para tirar do ar produtos sem foto.
 *
 * @return int quantos foram para rascunho
 */
function dcxbz_draft_imageless() {
	global $wpdb;
	$ids = $wpdb->get_col(
		"SELECT p.ID
		   FROM {$wpdb->posts} p
		   JOIN {$wpdb->postmeta} src ON src.post_id = p.ID AND src.meta_key = '_dcxbz_source'
		   LEFT JOIN {$wpdb->postmeta} th ON th.post_id = p.ID AND th.meta_key = '_thumbnail_id'
		  WHERE p.post_type = 'product'
		    AND p.post_status = 'publish'
		    AND th.meta_id IS NULL"
	);
	$n = 0;
	foreach ( (array) $ids as $id ) {
		wp_update_post( array( 'ID' => (int) $id, 'post_status' => 'draft' ) );
		update_post_meta( (int) $id, '_dcxbz_needs_image', 1 );
		$n++;
	}
	return $n;
}

/* -------------------------------------------------------------------------
 * Manutenção: zerar preços e limpar rascunhos duplicados
 * ---------------------------------------------------------------------- */

/**
 * Enfileira produtos cujo preço não é 0 (ou não existe) para receber
 * _price = _regular_price = 0 (necessário p/ o botão de orçamento aparecer).
 *
 * @return int quantos entraram na fila
 */
function dcxbz_build_zero_price_queue() {
	global $wpdb;
	$ids = $wpdb->get_col(
		"SELECT p.ID
		   FROM {$wpdb->posts} p
		   LEFT JOIN {$wpdb->postmeta} rp ON rp.post_id = p.ID AND rp.meta_key = '_regular_price'
		   LEFT JOIN {$wpdb->postmeta} pr ON pr.post_id = p.ID AND pr.meta_key = '_price'
		  WHERE p.post_type = 'product'
		    AND p.post_status IN ('publish','draft')
		    AND (
		          rp.meta_id IS NULL OR rp.meta_value NOT IN ('0','0.0','0.00')
		       OR pr.meta_id IS NULL OR pr.meta_value NOT IN ('0','0.0','0.00')
		    )"
	);
	$queue = array();
	foreach ( (array) $ids as $id ) {
		$queue[] = array( 'op' => 'zero_price', 'id' => (int) $id );
	}
	update_option( DCXBZ_MAINT_QUEUE_OPT, $queue, false );
	update_option( DCXBZ_MAINT_REPORT_OPT, array( 'done' => 0, 'kind' => 'zero_price', 'when' => current_time( 'mysql' ) ), false );
	return count( $queue );
}

/**
 * Enfileira para a LIXEIRA os rascunhos criados pelo plugin cujo título é
 * idêntico ao de um produto já publicado (= cópia de item que já existe).
 *
 * @return int quantos entraram na fila
 */
function dcxbz_build_dup_drafts_queue() {
	global $wpdb;
	$ids = $wpdb->get_col(
		"SELECT d.ID
		   FROM {$wpdb->posts} d
		   JOIN {$wpdb->postmeta} src ON src.post_id = d.ID AND src.meta_key = '_dcxbz_source'
		  WHERE d.post_type = 'product'
		    AND d.post_status = 'draft'
		    AND d.post_title <> ''
		    AND EXISTS (
		      SELECT 1 FROM {$wpdb->posts} pub
		       WHERE pub.post_type = 'product'
		         AND pub.post_status = 'publish'
		         AND pub.post_title = d.post_title
		    )"
	);
	$queue = array();
	foreach ( (array) $ids as $id ) {
		$queue[] = array( 'op' => 'trash', 'id' => (int) $id );
	}
	update_option( DCXBZ_MAINT_QUEUE_OPT, $queue, false );
	update_option( DCXBZ_MAINT_REPORT_OPT, array( 'done' => 0, 'kind' => 'trash_dup', 'when' => current_time( 'mysql' ) ), false );
	return count( $queue );
}

/**
 * Enfileira produtos p/ receber (ou perder) uma tag de campanha, com base na
 * cor principal no catálogo XBZ (ex.: "ROSA" → tag "outubro-rosa").
 *
 * @param string $tag_slug  slug/nome da product_tag
 * @param string $color      cor a casar em CorWebPrincipal (substring, maiúsculas)
 * @param bool   $remove     true = remover a tag em vez de adicionar
 * @return int|WP_Error      quantos entraram na fila
 */
function dcxbz_build_color_tag_queue( $tag_slug, $color, $remove = false ) {
	$tag_slug = trim( (string) $tag_slug );
	if ( '' === $tag_slug ) {
		return new WP_Error( 'dcxbz_tag', 'Informe o nome da tag.' );
	}
	$s       = dcxbz_get_settings();
	$catalog = dcxbz_fetch_catalog( $s );
	if ( is_wp_error( $catalog ) ) {
		return new WP_Error( 'dcxbz_cat', 'Falha ao buscar catálogo: ' . $catalog->get_error_message() );
	}

	$color = mb_strtoupper( trim( (string) $color ), 'UTF-8' );
	$keys  = array();
	foreach ( $catalog as $p ) {
		$c = mb_strtoupper( trim( (string) ( isset( $p['CorWebPrincipal'] ) ? $p['CorWebPrincipal'] : '' ) ), 'UTF-8' );
		if ( '' === $color || '' === $c || false === mb_strpos( $c, $color ) ) {
			continue;
		}
		$comp = isset( $p['CodigoComposto'] ) ? $p['CodigoComposto'] : '';
		$keys[ dcxbz_norm( $comp ) ]           = true;
		$keys[ dcxbz_strip_color( $comp ) ]    = true;
		$keys[ dcxbz_norm( isset( $p['CodigoAmigavel'] ) ? $p['CodigoAmigavel'] : '' ) ] = true;
	}
	unset( $catalog, $keys[''] );
	if ( empty( $keys ) ) {
		return new WP_Error( 'dcxbz_color', 'Nenhum produto do catálogo com a cor "' . esc_html( $color ) . '".' );
	}

	$term = get_term_by( 'slug', sanitize_title( $tag_slug ), 'product_tag' );
	if ( ! $term ) {
		$term = get_term_by( 'name', $tag_slug, 'product_tag' );
	}
	if ( $term ) {
		$term_id = (int) $term->term_id;
	} else {
		$r       = wp_insert_term( $tag_slug, 'product_tag', array( 'slug' => sanitize_title( $tag_slug ) ) );
		$term_id = is_wp_error( $r ) ? 0 : (int) $r['term_id'];
	}
	if ( ! $term_id ) {
		return new WP_Error( 'dcxbz_term', 'Não foi possível criar/achar a tag.' );
	}

	global $wpdb;
	$rows  = $wpdb->get_results(
		"SELECT pm.post_id AS id, pm.meta_value AS sku
		   FROM {$wpdb->postmeta} pm
		   JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		  WHERE pm.meta_key = '_sku' AND pm.meta_value <> ''
		    AND p.post_type = 'product' AND p.post_status IN ('publish','draft')"
	);
	$queue = array();
	foreach ( $rows as $r ) {
		$n   = dcxbz_norm( $r->sku );
		$fam = preg_replace( '#-[A-Z]{2,4}(?:/[A-Z]{2,4})?$#', '', $n );
		if ( isset( $keys[ $n ] ) || ( $fam !== $n && isset( $keys[ $fam ] ) ) ) {
			$queue[] = array( 'op' => $remove ? 'untag' : 'tag', 'id' => (int) $r->id, 'term' => $term_id );
		}
	}
	update_option( DCXBZ_MAINT_QUEUE_OPT, $queue, false );
	update_option(
		DCXBZ_MAINT_REPORT_OPT,
		array( 'done' => 0, 'kind' => ( $remove ? 'untag:' : 'tag:' ) . sanitize_title( $tag_slug ), 'when' => current_time( 'mysql' ) ),
		false
	);
	return count( $queue );
}

/**
 * Drena a fila de manutenção (DCXBZ_MAINT_QUEUE_OPT). Resumível.
 *
 * @return int processados nesta passada
 */
function dcxbz_drain_maint_queue( $budget_seconds = 40 ) {
	$q = get_option( DCXBZ_MAINT_QUEUE_OPT, array() );
	if ( ! is_array( $q ) || empty( $q ) ) {
		return 0;
	}
	@set_time_limit( 0 );
	$start = microtime( true );
	$done  = 0;
	$rep   = get_option( DCXBZ_MAINT_REPORT_OPT, array() );
	if ( ! is_array( $rep ) ) {
		$rep = array();
	}
	$rep += array( 'done' => 0, 'kind' => '', 'when' => current_time( 'mysql' ) );

	while ( ! empty( $q ) && ( microtime( true ) - $start ) < $budget_seconds ) {
		$row = array_shift( $q );
		$id  = (int) $row['id'];

		if ( 'zero_price' === $row['op'] ) {
			update_post_meta( $id, '_price', '0' );
			update_post_meta( $id, '_regular_price', '0' );
			delete_post_meta( $id, '_sale_price' );
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $id );
			}
		} elseif ( 'trash' === $row['op'] ) {
			if ( 'draft' === get_post_status( $id ) ) { // segurança: nunca publicado
				wp_trash_post( $id );
			}
		} elseif ( 'tag' === $row['op'] ) {
			wp_set_object_terms( $id, array( (int) $row['term'] ), 'product_tag', true );
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $id );
			}
		} elseif ( 'untag' === $row['op'] ) {
			wp_remove_object_terms( $id, array( (int) $row['term'] ), 'product_tag' );
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $id );
			}
		}
		$rep['done']++;
		$done++;
		if ( 0 === $done % 50 ) {
			update_option( DCXBZ_MAINT_QUEUE_OPT, array_values( $q ), false );
		}
	}

	if ( empty( $q ) ) {
		delete_option( DCXBZ_MAINT_QUEUE_OPT );
	} else {
		update_option( DCXBZ_MAINT_QUEUE_OPT, array_values( $q ), false );
	}
	update_option( DCXBZ_MAINT_REPORT_OPT, $rep, false );
	return $done;
}

/* -------------------------------------------------------------------------
 * Importar imagens de um ZIP local (contorna o bloqueio do CDN da XBZ).
 * Cada arquivo do ZIP deve se chamar <SKU>.<ext> (SKU = CodigoAmigavel).
 * ---------------------------------------------------------------------- */

function dcxbz_import_dir() {
	$u = wp_upload_dir();
	return trailingslashit( $u['basedir'] ) . 'dcxbz-img-import';
}

/**
 * Recebe o ZIP enviado ($_FILES['dcxbz_img_zip']), extrai e adiciona os arquivos
 * à fila de importação.
 *
 * @return array [ 'ok' => bool, 'msg' => string, 'added' => int ]
 */
function dcxbz_ingest_zip() {
	if ( empty( $_FILES['dcxbz_img_zip']['name'] ) ) {
		return array( 'ok' => false, 'msg' => 'Nenhum arquivo enviado.', 'added' => 0 );
	}
	$f = $_FILES['dcxbz_img_zip'];
	if ( ! empty( $f['error'] ) ) {
		return array( 'ok' => false, 'msg' => 'Erro no upload (código ' . (int) $f['error'] . '). Verifique upload_max_filesize/post_max_size.', 'added' => 0 );
	}
	if ( ! preg_match( '/\.zip$/i', $f['name'] ) ) {
		return array( 'ok' => false, 'msg' => 'Envie um arquivo .zip.', 'added' => 0 );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	$dir = dcxbz_import_dir();
	wp_mkdir_p( $dir );

	$tmp_zip = $dir . '/_upload_' . wp_generate_password( 8, false ) . '.zip';
	if ( ! move_uploaded_file( $f['tmp_name'], $tmp_zip ) ) {
		return array( 'ok' => false, 'msg' => 'Não foi possível mover o arquivo enviado.', 'added' => 0 );
	}
	$r = dcxbz_extract_zip_to_queue( $tmp_zip, true );
	@unlink( $tmp_zip );
	return $r;
}

/**
 * Importa a partir de um ZIP já presente no servidor (ex.: public_html/xbz-images.zip),
 * enviado por outro meio. Não apaga o ZIP.
 *
 * @param string $rel Caminho relativo ao ABSPATH (raiz do WP).
 * @return array
 */
function dcxbz_ingest_server_zip( $rel = 'xbz-images.zip' ) {
	$rel = ltrim( str_replace( array( '..', "\0" ), '', (string) $rel ), '/\\' );

	// Aceita curinga (ex.: "xbz-img-*.zip") — processa todos os ZIPs que casarem.
	if ( false !== strpos( $rel, '*' ) ) {
		$list = glob( ABSPATH . $rel );
		if ( empty( $list ) ) {
			return array( 'ok' => false, 'msg' => 'Nenhum arquivo casou com ' . esc_html( ABSPATH . $rel ) . '.', 'added' => 0 );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		wp_mkdir_p( dcxbz_import_dir() );
		$added = 0;
		$errs  = array();
		foreach ( $list as $p ) {
			$r = dcxbz_extract_zip_to_queue( $p, false );
			if ( $r['ok'] ) {
				$added += (int) $r['added'];
			} else {
				$errs[] = basename( $p ) . ': ' . $r['msg'];
			}
		}
		$iq = count( get_option( DCXBZ_IMPORT_QUEUE_OPT, array() ) );
		return array(
			'ok'    => $added > 0 || empty( $errs ),
			'msg'   => sprintf( '%d ZIP(s), %d imagem(ns) enfileirada(s). Fila: %d.', count( $list ), $added, $iq ) . ( $errs ? ' Erros: ' . implode( ' | ', $errs ) : '' ),
			'added' => $added,
		);
	}

	$path = ABSPATH . $rel;
	if ( ! is_readable( $path ) ) {
		return array( 'ok' => false, 'msg' => 'Não achei o arquivo em ' . esc_html( $path ) . '. Faça o upload dele para a raiz do site.', 'added' => 0 );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	wp_mkdir_p( dcxbz_import_dir() );
	return dcxbz_extract_zip_to_queue( $path, false );
}

/**
 * Extrai um ZIP para a pasta de import e enfileira as imagens.
 *
 * @param string $zip_path
 * @param bool   $delete_after
 * @return array [ 'ok'=>bool, 'msg'=>string, 'added'=>int ]
 */
function dcxbz_extract_zip_to_queue( $zip_path, $delete_after = false ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	$dir = dcxbz_import_dir();
	wp_mkdir_p( $dir );

	$unz = unzip_file( $zip_path, $dir );
	if ( $delete_after ) {
		@unlink( $zip_path );
	}
	if ( is_wp_error( $unz ) ) {
		return array( 'ok' => false, 'msg' => 'Falha ao descompactar: ' . $unz->get_error_message(), 'added' => 0 );
	}

	// Varre os arquivos de imagem extraídos e monta/atualiza a fila.
	$queue = get_option( DCXBZ_IMPORT_QUEUE_OPT, array() );
	if ( ! is_array( $queue ) ) {
		$queue = array();
	}
	$have  = array_flip( wp_list_pluck( $queue, 'file' ) );
	$added = 0;
	$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$path = $file->getPathname();
		if ( ! preg_match( '/\.(jpe?g|png|webp|gif)$/i', $path ) ) {
			continue;
		}
		if ( isset( $have[ $path ] ) ) {
			continue;
		}
		$sku = preg_replace( '/\.[^.]+$/', '', basename( $path ) );
		$queue[] = array( 'file' => $path, 'sku' => $sku );
		$added++;
	}
	update_option( DCXBZ_IMPORT_QUEUE_OPT, array_values( $queue ), false );
	update_option(
		DCXBZ_IMPORT_REPORT_OPT,
		wp_parse_args(
			get_option( DCXBZ_IMPORT_REPORT_OPT, array() ),
			array( 'imported' => 0, 'republished' => 0, 'notfound' => 0, 'skipped' => 0, 'errors' => 0, 'when' => current_time( 'mysql' ) )
		),
		false
	);
	return array( 'ok' => true, 'msg' => sprintf( '%d imagem(ns) adicionada(s) à fila. Total na fila: %d.', $added, count( $queue ) ), 'added' => $added );
}

/**
 * Processa a fila de importação local (sem HTTP). Resumível.
 *
 * @return int processados nesta passada
 */
function dcxbz_drain_import_queue( $budget_seconds = 40 ) {
	$q = get_option( DCXBZ_IMPORT_QUEUE_OPT, array() );
	if ( ! is_array( $q ) || empty( $q ) || ! function_exists( 'wc_get_product_id_by_sku' ) ) {
		return 0;
	}
	@set_time_limit( 0 );
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$s     = dcxbz_get_settings();
	$pub   = ! empty( $s['create_publish'] );
	$start = microtime( true );
	$done  = 0;
	$rep   = get_option( DCXBZ_IMPORT_REPORT_OPT, array() );
	if ( ! is_array( $rep ) ) {
		$rep = array();
	}
	$rep += array( 'imported' => 0, 'republished' => 0, 'notfound' => 0, 'skipped' => 0, 'errors' => 0, 'when' => current_time( 'mysql' ) );

	while ( ! empty( $q ) && ( microtime( true ) - $start ) < $budget_seconds ) {
		$row  = array_shift( $q );
		$path = $row['file'];
		$sku  = (string) $row['sku'];

		if ( ! is_readable( $path ) ) {
			$rep['errors']++;
			$done++;
			continue;
		}
				$is_gallery = false;
		$pid = wc_get_product_id_by_sku( $sku );
		if ( ! $pid ) {
			// tenta normalizado (sem prefixo/sufixo)
			$pid = wc_get_product_id_by_sku( dcxbz_norm( $sku ) );
		}
		if ( ! $pid && preg_match( '/^(.+?)d([1-9]\d*)$/i', $sku, $m ) ) {
			// não achou o SKU literal: tenta como foto EXTRA de galeria
			// (ex: "17772d1.jpg" -> foto extra do produto de SKU "17772").
			$base_sku = $m[1];
			$pid      = wc_get_product_id_by_sku( $base_sku );
			if ( ! $pid ) {
				$pid = wc_get_product_id_by_sku( dcxbz_norm( $base_sku ) );
			}
			if ( $pid ) {
				$is_gallery = true;
			}
		}
		if ( ! $pid ) {
			$rep['notfound']++;
			$done++;
			@unlink( $path );
			continue;
		}
		if ( ! $is_gallery && get_post_thumbnail_id( $pid ) ) {
			$rep['skipped']++;
			$done++;
			@unlink( $path );
			continue;
		}

		// Copia p/ tmp (media_handle_sideload move/apaga o arquivo).
		$tmp = wp_tempnam( basename( $path ) );
		@copy( $path, $tmp );
		$file_array = array( 'name' => basename( $path ), 'tmp_name' => $tmp );
		$att        = media_handle_sideload( $file_array, $pid );
		if ( is_wp_error( $att ) ) {
			@unlink( $tmp );
			$rep['errors']++;
			$done++;
			continue;
		}
		if ( $is_gallery ) {
			$gallery     = get_post_meta( $pid, '_product_image_gallery', true );
			$gallery_ids = array_filter( array_map( 'trim', explode( ',', (string) $gallery ) ) );
			$gallery_ids[] = (string) $att;
			update_post_meta( $pid, '_product_image_gallery', implode( ',', array_unique( $gallery_ids ) ) );
		} else {
			set_post_thumbnail( $pid, $att );
		}
		delete_post_meta( $pid, '_dcxbz_needs_image' );
		$rep['imported']++;
		if ( $pub && 'draft' === get_post_status( $pid ) ) {
			wp_update_post( array( 'ID' => $pid, 'post_status' => 'publish' ) );
			$rep['republished']++;
		}
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $pid );
		}
		@unlink( $path );
		$done++;

		if ( 0 === $done % 20 ) {
			update_option( DCXBZ_IMPORT_QUEUE_OPT, array_values( $q ), false );
		}
	}

	if ( empty( $q ) ) {
		delete_option( DCXBZ_IMPORT_QUEUE_OPT );
	} else {
		update_option( DCXBZ_IMPORT_QUEUE_OPT, array_values( $q ), false );
	}
	update_option( DCXBZ_IMPORT_REPORT_OPT, $rep, false );
	return $done;
}

/* -------------------------------------------------------------------------
 * Busca do catálogo XBZ
 * ---------------------------------------------------------------------- */

function dcxbz_fetch_catalog( $s ) {
	@ini_set( 'memory_limit', '512M' ); // o catálogo XBZ vem inteiro (~14 MB) numa resposta só

	$cnpj = preg_replace( '/\D/', '', $s['cnpj'] );
	// add_query_arg() já faz o urlencode dos valores — passar cru para não codificar 2×.
	$url = add_query_arg(
		array(
			'cnpj'  => $cnpj,
			'token' => $s['token'],
		),
		trailingslashit( $s['base_url'] ) . 'GetListaDeProdutos'
	);

	// Autenticação SÓ na query string (foi o que funcionou nos testes). Enviar
	// também nos headers fez a API responder "null" com HTTP 200.
	$args = array(
		'timeout'     => 120,
		'redirection' => 2,
		'sslverify'   => true,
		'headers'     => array( 'Accept' => '*/*' ),
		'user-agent'  => 'Mozilla/5.0 (compatible; DC-XBZ-StockSync/' . DCXBZ_VERSION . ')',
	);

	$res = wp_remote_get( $url, $args );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'dcxbz_wp', 'Erro de rede: ' . $res->get_error_message() . ' (o servidor consegue sair na porta 5001?)' );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$ctype = wp_remote_retrieve_header( $res, 'content-type' );
	$body  = wp_remote_retrieve_body( $res );
	$len   = strlen( $body );
	unset( $res );

	if ( 200 !== $code ) {
		return new WP_Error( 'dcxbz_http', sprintf( 'HTTP %d. Content-Type: %s. Corpo (%d bytes): %s', $code, $ctype, $len, dcxbz_snippet( $body ) ) );
	}
	if ( '' === trim( $body ) ) {
		return new WP_Error( 'dcxbz_empty', sprintf( 'Resposta vazia (0 bytes úteis). Content-Type: %s.', $ctype ) );
	}

	// Remove BOM se houver.
	$body = preg_replace( '/^\xEF\xBB\xBF/', '', $body );
	$data = json_decode( $body, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		return new WP_Error( 'dcxbz_json', sprintf( 'JSON inválido (%s). Content-Type: %s. Corpo (%d bytes): %s', json_last_error_msg(), $ctype, $len, dcxbz_snippet( $body ) ) );
	}
	// A XBZ às vezes devolve o JSON dentro de uma string JSON (dupla codificação).
	// Decodifica de novo até virar array (no máx. 2×).
	$tries = 0;
	while ( is_string( $data ) && $tries < 2 ) {
		$again = json_decode( $data, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			break;
		}
		$data = $again;
		$tries++;
	}
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'dcxbz_shape', sprintf( 'A XBZ respondeu, mas não uma lista. Tipo: %s. Content-Type: %s. Corpo: %s', gettype( $data ), $ctype, dcxbz_snippet( $body ) ) );
	}
	// Alguns erros da API vêm como objeto {"Message":"..."} (chave 0 ausente).
	if ( ! isset( $data[0] ) && ( isset( $data['Message'] ) || isset( $data['message'] ) || isset( $data['erro'] ) ) ) {
		$msg = $data['Message'] ?? ( $data['message'] ?? $data['erro'] );
		return new WP_Error( 'dcxbz_api', 'A XBZ retornou um erro: ' . wp_strip_all_tags( (string) $msg ) );
	}
	return $data;
}

/**
 * Primeiros 500 caracteres de um texto, para mensagens de diagnóstico.
 */
function dcxbz_snippet( $s ) {
	$s = trim( wp_strip_all_tags( (string) $s ) );
	$s = preg_replace( '/\s+/', ' ', $s );
	return '' === $s ? '(vazio)' : mb_substr( $s, 0, 500 );
}

/* -------------------------------------------------------------------------
 * Índice XBZ: família -> soma de estoque
 * ---------------------------------------------------------------------- */

/**
 * Normaliza um código para comparação: MAIÚSCULAS, sem prefixo "XX@",
 * sem sufixo "*XX*", sem espaços.
 */
function dcxbz_norm( $code ) {
	$code = strtoupper( trim( (string) $code ) );
	$code = preg_replace( '/^[A-Z]{1,3}@/', '', $code );   // P@ E@ O@ DC@ ...
	$code = preg_replace( '/\*[A-Z]+\*$/', '', $code );     // *CP*
	$code = preg_replace( '/\s+/', '', $code );
	return $code;
}

/**
 * Remove um sufixo de COR do CodigoComposto (ex.: "-AZU", "-VD/COB").
 * Mantém sufixos numéricos (ex.: "-100", "-80" = tamanho/pacote).
 */
function dcxbz_strip_color( $composto ) {
	$c = dcxbz_norm( $composto );
	return preg_replace( '#-[A-Z]{2,4}(?:/[A-Z]{2,4})?$#', '', $c );
}

/**
 * Constrói o índice: chave-de-família => [ 'qty' => soma, 'colors' => n, 'key' => chave ].
 * Uma mesma linha XBZ alimenta até 3 chaves: CodigoComposto exato, CodigoComposto
 * sem cor, e CodigoAmigavel.
 */
function dcxbz_build_index( $catalog, $s ) {
	$idx = array();

	foreach ( $catalog as $p ) {
		$qty = isset( $p['QuantidadeDisponivel'] ) ? (int) $p['QuantidadeDisponivel'] : 0;
		if ( $qty < 0 ) {
			$qty = 0;
		}
		$composto = isset( $p['CodigoComposto'] ) ? $p['CodigoComposto'] : '';
		$amigavel = isset( $p['CodigoAmigavel'] ) ? $p['CodigoAmigavel'] : '';

		// Chaves que esta linha alimenta — cada chave distinta recebe a quantidade
		// UMA única vez (composto exato, composto sem cor e amigável podem coincidir).
		$keys = array();
		$keys[] = dcxbz_norm( $composto );
		if ( ! empty( $s['match_composto'] ) ) {
			$keys[] = dcxbz_strip_color( $composto );
		}
		if ( ! empty( $s['match_amigavel'] ) ) {
			$keys[] = dcxbz_norm( $amigavel );
		}
		foreach ( array_unique( array_filter( $keys ) ) as $key ) {
			if ( ! isset( $idx[ $key ] ) ) {
				$idx[ $key ] = array( 'qty' => 0, 'colors' => 0, 'key' => $key );
			}
			$idx[ $key ]['qty']    += $qty;
			$idx[ $key ]['colors'] += 1;
		}
	}
	return $idx;
}

/**
 * Procura o SKU de um produto no índice XBZ.
 * Ordem: match exato do composto normalizado -> família (composto sem cor / amigável).
 *
 * @return array|null [ 'qty' => int, 'colors' => int, 'key' => string ]
 */
function dcxbz_lookup( $sku, $index, $s ) {
	$n = dcxbz_norm( $sku );
	if ( '' === $n ) {
		return null;
	}
	if ( isset( $index[ $n ] ) ) {
		return $index[ $n ];
	}
	$fam = preg_replace( '#-[A-Z]{2,4}(?:/[A-Z]{2,4})?$#', '', $n );
	if ( $fam !== $n && isset( $index[ $fam ] ) ) {
		return $index[ $fam ];
	}
	return null;
}

/* -------------------------------------------------------------------------
 * Compat: se o Action Scheduler disparar ações antigas, apenas drena o plano.
 * ---------------------------------------------------------------------- */
add_action( DCXBZ_CHUNK_HOOK, function () {
	dcxbz_drain_plan( 30 );
}, 10, 0 );

/* -------------------------------------------------------------------------
 * Relatórios / log
 * ---------------------------------------------------------------------- */

function dcxbz_store_report( $report ) {
	update_option( DCXBZ_REPORT_OPT, $report, false );
	$log = get_option( DCXBZ_LOG_OPT, array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	array_unshift(
		$log,
		array(
			'when'     => $report['started'],
			'dry_run'  => $report['dry_run'],
			'ok'       => $report['ok'],
			'message'  => $report['message'],
			'matched'  => $report['matched'],
			'changed'  => $report['changed'],
			'unmatched'=> $report['unmatched'],
		)
	);
	$log = array_slice( $log, 0, DCXBZ_LOG_KEEP );
	update_option( DCXBZ_LOG_OPT, $log, false );
	return $report;
}

/* -------------------------------------------------------------------------
 * Admin UI
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'dcxbz_admin_menu' );
function dcxbz_admin_menu() {
	add_submenu_page(
		'woocommerce',
		'Sincronização XBZ',
		'Sincronização XBZ',
		'manage_woocommerce',
		'dcxbz',
		'dcxbz_admin_page'
	);
}

add_action( 'admin_init', 'dcxbz_admin_init' );
function dcxbz_admin_init() {
	register_setting( 'dcxbz_group', DCXBZ_OPT, 'dcxbz_sanitize' );
}

function dcxbz_sanitize( $in ) {
	$d   = dcxbz_defaults();
	$out = array();
	$out['base_url']         = esc_url_raw( isset( $in['base_url'] ) ? $in['base_url'] : $d['base_url'] );
	$out['cnpj']             = preg_replace( '/\D/', '', isset( $in['cnpj'] ) ? $in['cnpj'] : '' );
	$out['token']            = sanitize_text_field( isset( $in['token'] ) ? $in['token'] : '' );
	$out['frequency']        = in_array( ( $in['frequency'] ?? '' ), array( 'hourly', 'twicedaily', 'daily' ), true ) ? $in['frequency'] : $d['frequency'];
	$out['oos_threshold']    = max( 0, (int) ( $in['oos_threshold'] ?? 0 ) );
	$out['set_out_of_stock'] = empty( $in['set_out_of_stock'] ) ? 0 : 1;
	$out['manage_stock']     = empty( $in['manage_stock'] ) ? 0 : 1;
	$out['match_amigavel']   = empty( $in['match_amigavel'] ) ? 0 : 1;
	$out['match_composto']   = empty( $in['match_composto'] ) ? 0 : 1;
	$out['dry_run']          = empty( $in['dry_run'] ) ? 0 : 1;
	$out['enabled']          = empty( $in['enabled'] ) ? 0 : 1;

	$out['create_new']         = empty( $in['create_new'] ) ? 0 : 1;
	$out['create_publish']     = empty( $in['create_publish'] ) ? 0 : 1;
	$out['create_image']       = empty( $in['create_image'] ) ? 0 : 1;
	$out['create_min_stock']   = max( 0, (int) ( $in['create_min_stock'] ?? $d['create_min_stock'] ) );
	$out['create_batch']       = min( 100, max( 1, (int) ( $in['create_batch'] ?? $d['create_batch'] ) ) );
	$out['create_cat_default'] = sanitize_text_field( $in['create_cat_default'] ?? $d['create_cat_default'] );
	$out['create_cat_rules']   = sanitize_textarea_field( $in['create_cat_rules'] ?? $d['create_cat_rules'] );

	return $out;
}

// Reagenda sempre que as configurações forem gravadas.
add_action( 'update_option_' . DCXBZ_OPT, 'dcxbz_reschedule', 20 );
add_action( 'add_option_' . DCXBZ_OPT, 'dcxbz_reschedule', 20 );

function dcxbz_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	// Aba "Asia Import" (fornecedor secundário) — módulo próprio, mais abaixo neste arquivo.
	$dcxbz_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'xbz';
	if ( 'asia' === $dcxbz_tab && function_exists( 'dcasia_admin_tab' ) ) {
		dcasia_admin_tab();
		return;
	}

	// Ações manuais.
	if ( isset( $_POST['dcxbz_action'] ) && check_admin_referer( 'dcxbz_run' ) ) {
		$action = sanitize_text_field( wp_unslash( $_POST['dcxbz_action'] ) );
		if ( 'dry' === $action ) {
			$r = dcxbz_run_sync( true );
			echo '<div class="notice notice-info"><p><strong>Simulação:</strong> ' . esc_html( $r['message'] ) . '</p></div>';
		} elseif ( 'live' === $action ) {
			// Se já há um plano pendente, apenas continua drenando (não rebusca o catálogo).
			if ( get_option( DCXBZ_PLAN_OPT ) ) {
				$n    = dcxbz_drain_plan( 45 );
				$rest = count( get_option( DCXBZ_PLAN_OPT, array() ) );
				echo '<div class="notice notice-success"><p><strong>Execução:</strong> ' . (int) $n . ' aplicado(s) nesta passada. ' . ( $rest ? (int) $rest . ' restante(s).' : 'Concluído.' ) . '</p></div>';
			} else {
				$r = dcxbz_run_sync( false );
				echo '<div class="notice notice-success"><p><strong>Execução:</strong> ' . esc_html( $r['message'] ) . '</p></div>';
			}
		} elseif ( 'continue' === $action ) {
			$n    = dcxbz_drain_plan( 45 );
			$rest = count( get_option( DCXBZ_PLAN_OPT, array() ) );
			echo '<div class="notice notice-success"><p><strong>Continuar:</strong> ' . (int) $n . ' aplicado(s). ' . ( $rest ? (int) $rest . ' restante(s).' : 'Concluído.' ) . '</p></div>';
		} elseif ( 'test' === $action ) {
			$s = dcxbz_get_settings();
			$c = dcxbz_fetch_catalog( $s );
			if ( is_wp_error( $c ) ) {
				echo '<div class="notice notice-error"><p>Falha na conexão: ' . esc_html( $c->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>Conexão OK — ' . count( $c ) . ' itens recebidos da XBZ.</p></div>';
			}
		} elseif ( 'preview_create' === $action ) {
			$r = dcxbz_run_sync( true ); // simulação já calcula os candidatos a criação
			echo '<div class="notice notice-info"><p><strong>Prévia de criação:</strong> ' . (int) ( $r['create_candidates'] ?? 0 ) . ' produto(s) seriam criados. Veja a distribuição por categoria abaixo.</p></div>';
		} elseif ( 'create_batch' === $action || 'create_continue' === $action ) {
			$s = dcxbz_get_settings();
			if ( empty( $s['create_new'] ) ) {
				echo '<div class="notice notice-error"><p>Ative "Criar produtos novos" e salve antes de rodar.</p></div>';
			} else {
				if ( ! get_option( DCXBZ_CREATE_QUEUE_OPT ) && 'create_batch' === $action ) {
					dcxbz_run_sync( false ); // monta a fila (e roda estoque também)
				}
				$n     = dcxbz_drain_create_queue( 45 );
				$crest = count( get_option( DCXBZ_CREATE_QUEUE_OPT, array() ) );
				$cr    = get_option( DCXBZ_CREATE_REPORT_OPT, array() );
				echo '<div class="notice notice-success"><p><strong>Criação:</strong> ' . (int) ( $cr['created'] ?? 0 ) . ' criados no total · ' . (int) ( $cr['skipped'] ?? 0 ) . ' pulados · ' . (int) ( $cr['errors'] ?? 0 ) . ' erros. ' . ( $crest ? (int) $crest . ' na fila.' : 'Fila concluída.' ) . '</p></div>';
			}
		} elseif ( 'fix_images' === $action ) {
			$total = dcxbz_build_img_queue(); // re-varre todos os _dcxbz_source=xbz sem imagem
			echo '<div class="notice notice-info"><p>' . (int) $total . ' produto(s) sem imagem na fila de backfill.</p></div>';
			$n     = dcxbz_drain_img_queue( 45 );
			$irest = count( get_option( DCXBZ_IMG_QUEUE_OPT, array() ) );
			$ir    = get_option( DCXBZ_IMG_REPORT_OPT, array() );
			echo '<div class="notice notice-success"><p><strong>Imagens:</strong> ' . (int) ( $ir['ok'] ?? 0 ) . ' baixadas · ' . (int) ( $ir['republished'] ?? 0 ) . ' republicadas · ' . (int) ( $ir['fail'] ?? 0 ) . ' falhas. ' . ( $irest ? (int) $irest . ' na fila.' : 'Fila concluída.' ) . '</p></div>';
		} elseif ( 'img_continue' === $action ) {
			$n     = dcxbz_drain_img_queue( 45 );
			$irest = count( get_option( DCXBZ_IMG_QUEUE_OPT, array() ) );
			$ir    = get_option( DCXBZ_IMG_REPORT_OPT, array() );
			echo '<div class="notice notice-success"><p><strong>Imagens:</strong> ' . (int) ( $ir['ok'] ?? 0 ) . ' baixadas · ' . (int) ( $ir['republished'] ?? 0 ) . ' republicadas · ' . (int) ( $ir['fail'] ?? 0 ) . ' falhas. ' . ( $irest ? (int) $irest . ' na fila.' : 'Fila concluída.' ) . '</p></div>';
		} elseif ( 'draft_imageless' === $action ) {
			$n = dcxbz_draft_imageless();
			echo '<div class="notice notice-warning"><p><strong>' . (int) $n . '</strong> produto(s) sem imagem foram para rascunho. Rode "Baixar imagens que faltam" para trazê-los de volta ao ar com foto.</p></div>';
		} elseif ( 'import_zip' === $action ) {
			$r = dcxbz_ingest_zip();
			echo '<div class="notice notice-' . ( $r['ok'] ? 'info' : 'error' ) . '"><p>' . esc_html( $r['msg'] ) . '</p></div>';
			if ( $r['ok'] && $r['added'] ) {
				dcxbz_drain_import_queue( 45 );
				$ir = get_option( DCXBZ_IMPORT_REPORT_OPT, array() );
				$iq = count( get_option( DCXBZ_IMPORT_QUEUE_OPT, array() ) );
				echo '<div class="notice notice-success"><p><strong>Importação:</strong> ' . (int) ( $ir['imported'] ?? 0 ) . ' importadas · ' . (int) ( $ir['republished'] ?? 0 ) . ' republicadas · ' . (int) ( $ir['notfound'] ?? 0 ) . ' sem produto · ' . (int) ( $ir['skipped'] ?? 0 ) . ' já tinham · ' . (int) ( $ir['errors'] ?? 0 ) . ' erros. ' . ( $iq ? (int) $iq . ' na fila.' : 'Fila concluída.' ) . '</p></div>';
			}
		} elseif ( 'import_continue' === $action ) {
			dcxbz_drain_import_queue( 45 );
			$ir = get_option( DCXBZ_IMPORT_REPORT_OPT, array() );
			$iq = count( get_option( DCXBZ_IMPORT_QUEUE_OPT, array() ) );
			echo '<div class="notice notice-success"><p><strong>Importação:</strong> ' . (int) ( $ir['imported'] ?? 0 ) . ' importadas · ' . (int) ( $ir['republished'] ?? 0 ) . ' republicadas · ' . (int) ( $ir['notfound'] ?? 0 ) . ' sem produto · ' . (int) ( $ir['skipped'] ?? 0 ) . ' já tinham · ' . (int) ( $ir['errors'] ?? 0 ) . ' erros. ' . ( $iq ? (int) $iq . ' na fila.' : 'Fila concluída.' ) . '</p></div>';
		} elseif ( 'import_server' === $action ) {
			$rel = ! empty( $_POST['dcxbz_server_zip'] ) ? sanitize_text_field( wp_unslash( $_POST['dcxbz_server_zip'] ) ) : 'xbz-images.zip';
			$r   = dcxbz_ingest_server_zip( $rel );
			echo '<div class="notice notice-' . ( $r['ok'] ? 'info' : 'error' ) . '"><p>' . esc_html( $r['msg'] ) . '</p></div>';
			if ( $r['ok'] ) {
				dcxbz_drain_import_queue( 45 );
				$ir = get_option( DCXBZ_IMPORT_REPORT_OPT, array() );
				$iq = count( get_option( DCXBZ_IMPORT_QUEUE_OPT, array() ) );
				echo '<div class="notice notice-success"><p><strong>Importação:</strong> ' . (int) ( $ir['imported'] ?? 0 ) . ' importadas · ' . (int) ( $ir['republished'] ?? 0 ) . ' republicadas · ' . (int) ( $ir['notfound'] ?? 0 ) . ' sem produto · ' . (int) ( $ir['skipped'] ?? 0 ) . ' já tinham · ' . (int) ( $ir['errors'] ?? 0 ) . ' erros. ' . ( $iq ? (int) $iq . ' na fila.' : 'Fila concluída.' ) . '</p></div>';
			}
		} elseif ( 'zero_prices_preview' === $action ) {
			$n = dcxbz_build_zero_price_queue();
			echo '<div class="notice notice-info"><p><strong>Preço 0:</strong> ' . (int) $n . ' produto(s) receberiam <code>_price = _regular_price = 0</code>. Clique em "Zerar preços agora" para aplicar.</p></div>';
		} elseif ( 'zero_prices_run' === $action ) {
			if ( ! get_option( DCXBZ_MAINT_QUEUE_OPT ) ) {
				dcxbz_build_zero_price_queue();
			}
			$n  = dcxbz_drain_maint_queue( 45 );
			$mq = count( get_option( DCXBZ_MAINT_QUEUE_OPT, array() ) );
			$mr = get_option( DCXBZ_MAINT_REPORT_OPT, array() );
			echo '<div class="notice notice-success"><p><strong>Preço 0:</strong> ' . (int) ( $mr['done'] ?? 0 ) . ' produto(s) zerado(s). ' . ( $mq ? (int) $mq . ' na fila — clique em "Continuar manutenção".' : 'Concluído.' ) . '</p></div>';
		} elseif ( 'dup_drafts_preview' === $action ) {
			$n = dcxbz_build_dup_drafts_queue();
			echo '<div class="notice notice-warning"><p><strong>Rascunhos duplicados:</strong> ' . (int) $n . ' rascunho(s) criado(s) pelo plugin têm título igual ao de um produto publicado. "Excluir duplicados" move esses para a <em>lixeira</em> (reversível).</p></div>';
		} elseif ( 'dup_drafts_run' === $action ) {
			if ( ! get_option( DCXBZ_MAINT_QUEUE_OPT ) ) {
				dcxbz_build_dup_drafts_queue();
			}
			$n  = dcxbz_drain_maint_queue( 45 );
			$mq = count( get_option( DCXBZ_MAINT_QUEUE_OPT, array() ) );
			$mr = get_option( DCXBZ_MAINT_REPORT_OPT, array() );
			echo '<div class="notice notice-success"><p><strong>Duplicados:</strong> ' . (int) ( $mr['done'] ?? 0 ) . ' movido(s) para a lixeira. ' . ( $mq ? (int) $mq . ' na fila — clique em "Continuar manutenção".' : 'Concluído.' ) . '</p></div>';
		} elseif ( 'campaign_tag_preview' === $action || 'campaign_tag_run' === $action || 'campaign_untag_run' === $action ) {
			$slug   = sanitize_text_field( wp_unslash( $_POST['dcxbz_tag_slug'] ?? 'outubro-rosa' ) );
			$color  = sanitize_text_field( wp_unslash( $_POST['dcxbz_tag_color'] ?? 'ROSA' ) );
			$remove = ( 'campaign_untag_run' === $action );
			$n      = dcxbz_build_color_tag_queue( $slug, $color, $remove );
			if ( is_wp_error( $n ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $n->get_error_message() ) . '</p></div>';
			} elseif ( 'campaign_tag_preview' === $action ) {
				echo '<div class="notice notice-info"><p><strong>Tag "' . esc_html( $slug ) . '":</strong> ' . (int) $n . ' produto(s) do site casam com a cor "' . esc_html( $color ) . '" no catálogo XBZ. Clique em "Aplicar tag" para marcar.</p></div>';
			} else {
				dcxbz_drain_maint_queue( 45 );
				$mq = count( get_option( DCXBZ_MAINT_QUEUE_OPT, array() ) );
				$mr = get_option( DCXBZ_MAINT_REPORT_OPT, array() );
				echo '<div class="notice notice-success"><p><strong>' . ( $remove ? 'Remover tag' : 'Aplicar tag' ) . ' (' . esc_html( $slug ) . '):</strong> ' . (int) ( $mr['done'] ?? 0 ) . ' produto(s). ' . ( $mq ? (int) $mq . ' na fila — "Continuar manutenção".' : 'Concluído.' ) . '</p></div>';
			}
		} elseif ( 'maint_continue' === $action ) {
			$n  = dcxbz_drain_maint_queue( 45 );
			$mq = count( get_option( DCXBZ_MAINT_QUEUE_OPT, array() ) );
			$mr = get_option( DCXBZ_MAINT_REPORT_OPT, array() );
			echo '<div class="notice notice-success"><p><strong>Manutenção (' . esc_html( $mr['kind'] ?? '' ) . '):</strong> ' . (int) ( $mr['done'] ?? 0 ) . ' feito(s). ' . ( $mq ? (int) $mq . ' na fila.' : 'Concluído.' ) . '</p></div>';
		}
	}

	$s      = dcxbz_get_settings();
	$report = get_option( DCXBZ_REPORT_OPT, array() );
	$log    = get_option( DCXBZ_LOG_OPT, array() );
	?>
	<div class="wrap">
		<h1>Sincronização de Estoque — XBZ</h1>
		<?php dcxbz_render_supplier_tabs( 'xbz' ); ?>
		<p>Somente <strong>estoque</strong>. Produtos cujo SKU não casa com o catálogo XBZ <strong>nunca</strong> são alterados. Estoque do produto = soma de todas as cores da família na XBZ. Estoque negativo na XBZ conta como 0.</p>
		<p class="description" style="max-width:820px">
			<strong>Sobre “fora de estoque”:</strong> deixe essa opção <em>desligada</em> enquanto a sincronização da Asia Import não existir.
			Hoje ~100 produtos do site zerariam na XBZ e seriam marcados como indisponíveis — mas alguns podem ser fornecidos pela Asia.
			Com a opção desligada, o plugin só grava as quantidades e mantém tudo “em estoque”. Ligue depois, quando a Asia entrar.
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'dcxbz_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Base URL</th>
					<td><input type="text" class="regular-text" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[base_url]" value="<?php echo esc_attr( $s['base_url'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row">CNPJ</th>
					<td><input type="text" class="regular-text" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[cnpj]" value="<?php echo esc_attr( $s['cnpj'] ); ?>" placeholder="somente números"></td>
				</tr>
				<tr>
					<th scope="row">Token</th>
					<td><input type="text" class="regular-text" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[token]" value="<?php echo esc_attr( $s['token'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row">Frequência</th>
					<td>
						<select name="<?php echo esc_attr( DCXBZ_OPT ); ?>[frequency]">
							<option value="hourly"     <?php selected( $s['frequency'], 'hourly' ); ?>>A cada hora</option>
							<option value="twicedaily" <?php selected( $s['frequency'], 'twicedaily' ); ?>>2× ao dia</option>
							<option value="daily"      <?php selected( $s['frequency'], 'daily' ); ?>>1× ao dia</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">Regras</th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[manage_stock]" value="1" <?php checked( $s['manage_stock'], 1 ); ?>> Ligar o “gerenciar estoque” do produto ao atualizar</label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[set_out_of_stock]" value="1" <?php checked( $s['set_out_of_stock'], 1 ); ?>> Marcar “fora de estoque” quando a soma for &le;
							<input type="number" min="0" style="width:70px" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[oos_threshold]" value="<?php echo esc_attr( $s['oos_threshold'] ); ?>"></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[match_amigavel]" value="1" <?php checked( $s['match_amigavel'], 1 ); ?>> Casar por <code>CodigoAmigavel</code> (família)</label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[match_composto]" value="1" <?php checked( $s['match_composto'], 1 ); ?>> Casar por <code>CodigoComposto</code> sem a cor</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Modo</th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[dry_run]" value="1" <?php checked( $s['dry_run'], 1 ); ?>> <strong>Simulação</strong> — calcula tudo mas não grava</label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[enabled]" value="1" <?php checked( $s['enabled'], 1 ); ?>> <strong>Ativar sincronização automática</strong> (cron)</label>
					</td>
				</tr>

				<tr><th colspan="2"><hr><h2 style="margin:.4em 0">Produtos novos (XBZ)</h2>
					<p class="description" style="max-width:820px;font-weight:400">
						Cria no site as famílias da XBZ (por <code>CodigoAmigavel</code>) que ainda não têm SKU aqui.
						Estoque = soma das cores. Categoria por palavra-chave no nome (regras abaixo); sem regra ⇒ categoria padrão.
						Preço não é definido (fluxo de orçamento). Use <em>Prever criação</em> antes de ligar.
					</p></th></tr>
				<tr>
					<th scope="row">Criar</th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[create_new]" value="1" <?php checked( $s['create_new'], 1 ); ?>> <strong>Criar produtos novos</strong> (mestre)</label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[create_publish]" value="1" <?php checked( $s['create_publish'], 1 ); ?>> Publicar direto (desmarque para criar como rascunho)</label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[create_image]" value="1" <?php checked( $s['create_image'], 1 ); ?>> Baixar a imagem principal da XBZ</label><br>
						<label>Só criar se a soma de estoque for &ge;
							<input type="number" min="0" style="width:70px" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[create_min_stock]" value="<?php echo esc_attr( $s['create_min_stock'] ); ?>"></label><br>
						<label>Criar por passada:
							<input type="number" min="1" max="100" style="width:70px" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[create_batch]" value="<?php echo esc_attr( $s['create_batch'] ); ?>"></label>
					</td>
				</tr>
				<tr>
					<th scope="row">Categoria padrão</th>
					<td><input type="text" class="regular-text" name="<?php echo esc_attr( DCXBZ_OPT ); ?>[create_cat_default]" value="<?php echo esc_attr( $s['create_cat_default'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row">Regras de categoria</th>
					<td>
						<textarea name="<?php echo esc_attr( DCXBZ_OPT ); ?>[create_cat_rules]" rows="10" class="large-text code" spellcheck="false"><?php echo esc_textarea( $s['create_cat_rules'] ); ?></textarea>
						<p class="description">Uma por linha: <code>palavra-chave =&gt; Nome da Categoria</code>. Primeira que casar vence. Categoria criada se não existir.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Salvar configurações' ); ?>
		</form>

		<hr>
		<h2>Rodar agora</h2>
		<form method="post">
			<?php wp_nonce_field( 'dcxbz_run' ); ?>
			<p>
				<button class="button" name="dcxbz_action" value="test">Testar conexão</button>
				<button class="button button-secondary" name="dcxbz_action" value="dry">Rodar simulação</button>
				<button class="button button-primary" name="dcxbz_action" value="live" onclick="return confirm('Gravar as alterações de estoque agora?');">Rodar e gravar (estoque)</button>
			</p>
			<?php if ( ! empty( $s['create_new'] ) ) : ?>
				<p>
					<button class="button button-secondary" name="dcxbz_action" value="preview_create">Prever criação</button>
					<button class="button button-primary" name="dcxbz_action" value="create_batch" onclick="return confirm('Criar produtos novos no site agora? (em lotes)');">Criar produtos novos</button>
					<?php if ( get_option( DCXBZ_CREATE_QUEUE_OPT ) ) : ?>
						<button class="button" name="dcxbz_action" value="create_continue">Continuar criação (fila: <?php echo (int) count( get_option( DCXBZ_CREATE_QUEUE_OPT, array() ) ); ?>)</button>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<p>
				<button class="button" name="dcxbz_action" value="fix_images">Baixar imagens que faltam (via XBZ)</button>
				<?php if ( get_option( DCXBZ_IMG_QUEUE_OPT ) ) : ?>
					<button class="button" name="dcxbz_action" value="img_continue">Continuar imagens (fila: <?php echo (int) count( get_option( DCXBZ_IMG_QUEUE_OPT, array() ) ); ?>)</button>
				<?php endif; ?>
				<button class="button" name="dcxbz_action" value="draft_imageless" onclick="return confirm('Tirar do ar (rascunho) todos os produtos criados que ainda estão sem imagem?');">Rebaixar sem-imagem p/ rascunho</button>
			</p>
		</form>

		<hr>
		<h3>Importar imagens de um ZIP</h3>
		<p class="description" style="max-width:820px">
			Para contornar o bloqueio do CDN da XBZ: envie um <code>.zip</code> com as imagens
			nomeadas <code>&lt;SKU&gt;.jpg</code> (o SKU é o <code>CodigoAmigavel</code>). Cada arquivo é
			casado com o produto pelo SKU, definido como imagem destacada, e o produto é
			republicado se estiver em rascunho. Pode enviar vários ZIPs (um de cada vez).
		</p>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'dcxbz_run' ); ?>
			<p>
				<input type="file" name="dcxbz_img_zip" accept=".zip">
				<button class="button button-primary" name="dcxbz_action" value="import_zip">Enviar e importar</button>
				<?php if ( get_option( DCXBZ_IMPORT_QUEUE_OPT ) ) : ?>
					<button class="button" name="dcxbz_action" value="import_continue">Continuar importação (fila: <?php echo (int) count( get_option( DCXBZ_IMPORT_QUEUE_OPT, array() ) ); ?>)</button>
				<?php endif; ?>
			</p>
			<p class="description">
				Ou, se o(s) ZIP(s) já está(ão) na raiz do site (<code><?php echo esc_html( ABSPATH ); ?></code>) — aceita curinga <code>*</code>:
				<input type="text" name="dcxbz_server_zip" value="xbz-img-*.zip" style="width:220px">
				<button class="button" name="dcxbz_action" value="import_server">Importar do(s) ZIP(s) no servidor</button>
			</p>
		</form>
		<?php
		$mrep = get_option( DCXBZ_IMPORT_REPORT_OPT, array() );
		$mqc  = (int) count( get_option( DCXBZ_IMPORT_QUEUE_OPT, array() ) );
		if ( $mrep || $mqc ) :
			?>
			<p>
				Importadas: <strong><?php echo (int) ( $mrep['imported'] ?? 0 ); ?></strong> ·
				Republicadas: <strong><?php echo (int) ( $mrep['republished'] ?? 0 ); ?></strong> ·
				Sem produto p/ o SKU: <strong><?php echo (int) ( $mrep['notfound'] ?? 0 ); ?></strong> ·
				Já tinham imagem: <strong><?php echo (int) ( $mrep['skipped'] ?? 0 ); ?></strong> ·
				Erros: <strong><?php echo (int) ( $mrep['errors'] ?? 0 ); ?></strong> ·
				Na fila: <strong><?php echo $mqc; ?></strong>
			</p>
		<?php endif; ?>

		<hr>
		<h3>Manutenção do catálogo</h3>
		<form method="post">
			<?php wp_nonce_field( 'dcxbz_run' ); ?>
			<p class="description" style="max-width:820px">
				<strong>Zerar preços:</strong> grava <code>_price = _regular_price = 0</code> em todo produto
				que ainda não está em 0 (necessário para o botão "Orçar item" aparecer).<br>
				<strong>Excluir rascunhos duplicados:</strong> manda para a <em>lixeira</em> os rascunhos
				criados pelo plugin cujo título é idêntico ao de um produto já publicado (mesmo item, código diferente).
			</p>
			<p>
				<button class="button" name="dcxbz_action" value="zero_prices_preview">Prever “zerar preços”</button>
				<button class="button button-primary" name="dcxbz_action" value="zero_prices_run" onclick="return confirm('Gravar preço 0 em todos os produtos que não estão em 0?');">Zerar preços agora</button>
			</p>
			<p>
				<button class="button" name="dcxbz_action" value="dup_drafts_preview">Prever duplicados</button>
				<button class="button" name="dcxbz_action" value="dup_drafts_run" onclick="return confirm('Mover para a LIXEIRA os rascunhos duplicados (título igual a um publicado)?');">Excluir duplicados (lixeira)</button>
			</p>
			<p class="description" style="max-width:820px;margin-top:14px">
				<strong>Tag de campanha por cor:</strong> marca com uma tag todos os produtos do site cuja cor principal
				no catálogo XBZ contém o termo abaixo. Ex.: cor <code>ROSA</code> → tag <code>outubro-rosa</code>.
			</p>
			<p>
				Tag: <input type="text" name="dcxbz_tag_slug" value="outubro-rosa" style="width:160px">
				Cor: <input type="text" name="dcxbz_tag_color" value="ROSA" style="width:110px">
				<button class="button" name="dcxbz_action" value="campaign_tag_preview">Prever</button>
				<button class="button button-primary" name="dcxbz_action" value="campaign_tag_run" onclick="return confirm('Aplicar a tag nos produtos dessa cor?');">Aplicar tag</button>
				<button class="button" name="dcxbz_action" value="campaign_untag_run" onclick="return confirm('Remover a tag desses produtos?');">Remover tag</button>
			</p>
			<?php if ( get_option( DCXBZ_MAINT_QUEUE_OPT ) ) : ?>
				<p><button class="button button-primary" name="dcxbz_action" value="maint_continue">Continuar manutenção (fila: <?php echo (int) count( get_option( DCXBZ_MAINT_QUEUE_OPT, array() ) ); ?>)</button></p>
			<?php endif; ?>
			<?php
			$mtr = get_option( DCXBZ_MAINT_REPORT_OPT, array() );
			if ( $mtr ) :
				?>
				<p>Última manutenção — <strong><?php echo esc_html( $mtr['kind'] ?? '' ); ?></strong>: <strong><?php echo (int) ( $mtr['done'] ?? 0 ); ?></strong> processado(s) (<?php echo esc_html( $mtr['when'] ?? '' ); ?>).</p>
			<?php endif; ?>
		</form>

		<?php
		$crep  = get_option( DCXBZ_CREATE_REPORT_OPT, array() );
		$cqcnt = (int) count( get_option( DCXBZ_CREATE_QUEUE_OPT, array() ) );
		if ( $crep || $cqcnt ) :
			?>
			<h3>Criação de produtos novos</h3>
			<p>
				Criados: <strong><?php echo (int) ( $crep['created'] ?? 0 ); ?></strong> ·
				Pulados (já existiam): <strong><?php echo (int) ( $crep['skipped'] ?? 0 ); ?></strong> ·
				Erros: <strong><?php echo (int) ( $crep['errors'] ?? 0 ); ?></strong> ·
				Na fila: <strong><?php echo $cqcnt; ?></strong>
			</p>
			<?php if ( ! empty( $report['create_by_cat'] ) ) : ?>
				<p><strong>Prévia por categoria:</strong>
					<?php
					$parts = array();
					foreach ( $report['create_by_cat'] as $cat => $n ) {
						$parts[] = esc_html( $cat ) . ' (' . (int) $n . ')';
					}
					echo wp_kses_post( implode( ' · ', $parts ) );
					?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $crep['last'] ) ) : ?>
				<table class="widefat striped" style="max-width:900px">
					<thead><tr><th>Produto criado</th><th>SKU</th><th>Categoria</th><th>Estoque</th><th>ID</th></tr></thead>
					<tbody>
					<?php foreach ( $crep['last'] as $c ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( (int) $c['id'] ) ); ?>"><?php echo esc_html( $c['name'] ); ?></a></td>
							<td><code><?php echo esc_html( $c['sku'] ); ?></code></td>
							<td><?php echo esc_html( $c['cat'] ); ?></td>
							<td><?php echo (int) $c['qty']; ?></td>
							<td><?php echo (int) $c['id']; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>

		<?php
		$irep  = get_option( DCXBZ_IMG_REPORT_OPT, array() );
		$iqcnt = (int) count( get_option( DCXBZ_IMG_QUEUE_OPT, array() ) );
		if ( $irep || $iqcnt ) :
			?>
			<h3>Backfill de imagens</h3>
			<p>
				Baixadas: <strong><?php echo (int) ( $irep['ok'] ?? 0 ); ?></strong> ·
				Republicadas: <strong><?php echo (int) ( $irep['republished'] ?? 0 ); ?></strong> ·
				Falhas: <strong><?php echo (int) ( $irep['fail'] ?? 0 ); ?></strong> ·
				Descartadas (3 tentativas): <strong><?php echo (int) ( $irep['dropped'] ?? 0 ); ?></strong> ·
				Na fila: <strong><?php echo $iqcnt; ?></strong>
			</p>
			<?php if ( ! empty( $irep['errors_sample'] ) ) : ?>
				<p><strong>Erros:</strong></p>
				<ul style="list-style:disc;margin-left:20px">
					<?php foreach ( $irep['errors_sample'] as $e ) : ?>
						<li><code><?php echo esc_html( $e ); ?></code></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $report ) : ?>
			<hr>
			<h2>Último resultado — <?php echo esc_html( $report['started'] ?? '' ); ?> <?php echo ! empty( $report['dry_run'] ) ? '<em>(simulação)</em>' : ''; ?></h2>
			<p><?php echo esc_html( $report['message'] ?? '' ); ?></p>
			<ul style="list-style:disc;margin-left:20px">
				<li>Itens recebidos da XBZ: <strong><?php echo (int) ( $report['xbz_items'] ?? 0 ); ?></strong> · chaves de família: <strong><?php echo (int) ( $report['xbz_families'] ?? 0 ); ?></strong></li>
				<li>Produtos do site com SKU: <strong><?php echo (int) ( $report['sku_products'] ?? 0 ); ?></strong></li>
				<li>Casaram com a XBZ: <strong><?php echo (int) ( $report['matched'] ?? 0 ); ?></strong> · sem correspondência (intocados): <strong><?php echo (int) ( $report['unmatched'] ?? 0 ); ?></strong></li>
				<li>Com estoque diferente: <strong><?php echo (int) ( $report['changed'] ?? 0 ); ?></strong> · já gravados: <strong><?php echo (int) ( $report['applied'] ?? 0 ); ?></strong></li>
			</ul>

			<?php if ( ! empty( $report['samples'] ) ) : ?>
				<h3>Amostra das alterações previstas (até 40)</h3>
				<table class="widefat striped" style="max-width:900px">
					<thead><tr><th>Produto</th><th>SKU</th><th>De</th><th>Para</th><th>Status</th><th>Chave XBZ</th><th>Cores somadas</th></tr></thead>
					<tbody>
					<?php foreach ( $report['samples'] as $row ) :
						$p = wc_get_product( (int) $row['id'] ); ?>
						<tr>
							<td><?php echo $p ? esc_html( $p->get_name() ) : ( '#' . (int) $row['id'] ); ?></td>
							<td><code><?php echo esc_html( $row['sku'] ); ?></code></td>
							<td><?php echo esc_html( $row['from'] ); ?></td>
							<td><strong><?php echo (int) $row['to']; ?></strong></td>
							<td><?php echo esc_html( $row['status'] ); ?></td>
							<td><code><?php echo esc_html( $row['key'] ); ?></code></td>
							<td><?php echo (int) $row['colors']; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $log ) : ?>
			<hr>
			<h2>Histórico</h2>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th>Quando</th><th>Modo</th><th>OK</th><th>Casaram</th><th>Alteraram</th><th>Sem match</th><th>Mensagem</th></tr></thead>
				<tbody>
				<?php foreach ( $log as $l ) : ?>
					<tr>
						<td><?php echo esc_html( $l['when'] ); ?></td>
						<td><?php echo ! empty( $l['dry_run'] ) ? 'simulação' : 'gravação'; ?></td>
						<td><?php echo ! empty( $l['ok'] ) ? '✓' : '✗'; ?></td>
						<td><?php echo (int) $l['matched']; ?></td>
						<td><?php echo (int) $l['changed']; ?></td>
						<td><?php echo (int) $l['unmatched']; ?></td>
						<td><?php echo esc_html( $l['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<hr>
		<p style="color:#666">Próxima execução automática:
			<?php
			$next = wp_next_scheduled( DCXBZ_CRON_HOOK );
			if ( ! $next && function_exists( 'as_next_scheduled_action' ) ) {
				$n = as_next_scheduled_action( DCXBZ_CRON_HOOK );
				$next = is_numeric( $n ) ? $n : 0;
			}
			echo $next ? esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ), 'd/m/Y H:i' ) ) : '— (automático desligado)';
			?>
		</p>
	</div>
	<?php
}

/* =========================================================================
 * =========================================================================
 *  MÓDULO ASIA IMPORT  (fornecedor SECUNDÁRIO)
 *  - Só cobre produtos que a XBZ NÃO fornece.
 *  - Sincroniza estoque (soma das variações) e, opcionalmente, cria produtos novos.
 *  - Casamento por `referencia` (com/sem "P" final) + `referencia` de cada variação.
 *  - Aba própria no mesmo painel (WooCommerce › Sincronização XBZ › Asia Import).
 *  - Opções, cron e filas próprios (prefixo dcasia_). Reaproveita helpers genéricos
 *    do módulo XBZ (title_case, parse_cat_rules, match_category, get_or_create_term, snippet).
 * =========================================================================
 * ====================================================================== */

define( 'DCASIA_OPT', 'dcasia_settings' );
define( 'DCASIA_REPORT_OPT', 'dcasia_last_report' );
define( 'DCASIA_LOG_OPT', 'dcasia_log' );
define( 'DCASIA_PLAN_OPT', 'dcasia_pending_plan' );
define( 'DCASIA_CREATE_QUEUE_OPT', 'dcasia_create_queue' );
define( 'DCASIA_CREATE_REPORT_OPT', 'dcasia_create_report' );
define( 'DCASIA_CRON_HOOK', 'dcasia_cron_sync' );
define( 'DCASIA_LOG_KEEP', 30 );

/* -------------------------------------------------------------------------
 * Configurações
 * ---------------------------------------------------------------------- */

function dcasia_defaults() {
	$xbz = dcxbz_defaults();
	return array(
		'base_url'           => 'https://api.asiaimport.com.br/',
		'api_key'            => '',
		'secret_key'         => '',
		'per_page'           => 100,               // máx. 100 no listarProdutos2
		'frequency'          => 'daily',           // hourly | twicedaily | daily
		'set_out_of_stock'   => 0,                 // idem XBZ: começa DESLIGADO
		'oos_threshold'      => 0,
		'manage_stock'       => 1,
		'match_trailing_p'   => 1,                 // casar referência com/sem "P" final
		'skip_xbz'           => 1,                 // NÃO tocar em produtos que a XBZ já cobre
		'dry_run'            => 1,
		'enabled'            => 0,

		// --- Criação de produtos novos (referências Asia ausentes no site e na XBZ) ---
		'create_new'         => 0,
		'create_publish'     => 1,
		'create_image'       => 1,
		'create_min_stock'   => 1,
		'create_batch'       => 15,
		'create_cat_default' => 'Novidades',
		'create_cat_rules'   => $xbz['create_cat_rules'], // reaproveita o mapa palavra-chave => categoria
	);
}

function dcasia_get_settings() {
	$s = get_option( DCASIA_OPT, array() );
	if ( ! is_array( $s ) ) {
		$s = array();
	}
	return wp_parse_args( $s, dcasia_defaults() );
}

/* -------------------------------------------------------------------------
 * Ativação / agendamento
 * ---------------------------------------------------------------------- */

register_activation_hook( __FILE__, 'dcasia_activate' );
function dcasia_activate() {
	add_option( DCASIA_OPT, dcasia_defaults() );
	dcasia_reschedule();
}

register_deactivation_hook( __FILE__, 'dcasia_deactivate' );
function dcasia_deactivate() {
	wp_clear_scheduled_hook( DCASIA_CRON_HOOK );
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( DCASIA_CRON_HOOK );
	}
}

function dcasia_reschedule() {
	$s = dcasia_get_settings();
	wp_clear_scheduled_hook( DCASIA_CRON_HOOK );
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( DCASIA_CRON_HOOK );
	}
	if ( empty( $s['enabled'] ) ) {
		return;
	}
	$freq      = in_array( $s['frequency'], array( 'hourly', 'twicedaily', 'daily' ), true ) ? $s['frequency'] : 'daily';
	$intervals = array( 'hourly' => HOUR_IN_SECONDS, 'twicedaily' => 12 * HOUR_IN_SECONDS, 'daily' => DAY_IN_SECONDS );
	if ( function_exists( 'as_schedule_recurring_action' ) ) {
		as_schedule_recurring_action( time() + 600, $intervals[ $freq ], DCASIA_CRON_HOOK, array(), 'dcasia' );
	} else {
		wp_schedule_event( time() + 600, $freq, DCASIA_CRON_HOOK );
	}
}
add_action( 'update_option_' . DCASIA_OPT, 'dcasia_reschedule', 20 );
add_action( 'add_option_' . DCASIA_OPT, 'dcasia_reschedule', 20 );

add_action( DCASIA_CRON_HOOK, 'dcasia_run_sync' );

/* -------------------------------------------------------------------------
 * API Asia Import — POST form-urlencoded, paginado (funcao=listarProdutos2)
 * ---------------------------------------------------------------------- */

function dcasia_fetch_catalog( $s ) {
	@ini_set( 'memory_limit', '512M' );

	$key = trim( (string) $s['api_key'] );
	$sec = trim( (string) $s['secret_key'] );
	if ( '' === $key || '' === $sec ) {
		return new WP_Error( 'dcasia_cfg', 'Chave e/ou Segredo da API Asia não configurados.' );
	}

	$url      = trailingslashit( $s['base_url'] );
	$per_page = min( 100, max( 1, (int) $s['per_page'] ) );
	$page     = 1;
	$guard    = 0;
	$all      = array();

	do {
		$guard++;
		$res = wp_remote_post(
			$url,
			array(
				'timeout'     => 120,
				'redirection' => 2,
				'sslverify'   => true,
				'headers'     => array( 'Accept' => 'application/json' ),
				'user-agent'  => 'DC-Asia-StockSync/' . DCXBZ_VERSION,
				'body'        => array(
					'api_key'    => $key,
					'secret_key' => $sec,
					'funcao'     => 'listarProdutos2',
					'pagina'     => $page,
					'por_pagina' => $per_page,
					'status'     => 'all',
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'dcasia_wp', 'Erro de rede: ' . $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		if ( 200 !== $code ) {
			return new WP_Error( 'dcasia_http', sprintf( 'HTTP %d na página %d. Corpo: %s', $code, $page, dcxbz_snippet( $body ) ) );
		}
		$body = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $body );
		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error( 'dcasia_json', sprintf( 'JSON inválido na página %d (%s): %s', $page, json_last_error_msg(), dcxbz_snippet( $body ) ) );
		}
		// Erros da API costumam vir sem a chave "produtos".
		if ( ! isset( $data['produtos'] ) ) {
			$msg = $data['erro'] ?? ( $data['error'] ?? ( $data['message'] ?? wp_json_encode( $data ) ) );
			return new WP_Error( 'dcasia_api', 'A Asia retornou um erro: ' . wp_strip_all_tags( (string) $msg ) );
		}
		$produtos = is_array( $data['produtos'] ) ? $data['produtos'] : array();
		foreach ( $produtos as $p ) {
			$all[] = $p;
		}
		$total_pages = isset( $data['total_paginas'] ) ? max( 1, (int) $data['total_paginas'] ) : 1;
		$page++;
	} while ( $page <= $total_pages && $guard < 2000 && ! empty( $produtos ) );

	return $all;
}

/* -------------------------------------------------------------------------
 * Normalização / índice / lookup
 * ---------------------------------------------------------------------- */

function dcasia_norm( $code ) {
	$code = strtoupper( trim( (string) $code ) );
	$code = preg_replace( '/\s+/', '', $code );
	return $code;
}

/** Estoque somado (variações; negativo conta 0). Sem variações usa o estoque do produto. */
function dcasia_family_stock( $p ) {
	$vars = ( isset( $p['variacoes'] ) && is_array( $p['variacoes'] ) ) ? $p['variacoes'] : array();
	$sum  = 0;
	$cols = 0;
	foreach ( $vars as $v ) {
		$q = isset( $v['qtd_estoque'] ) ? (int) $v['qtd_estoque'] : 0;
		$sum += ( $q < 0 ) ? 0 : $q;
		$cols++;
	}
	if ( ! $cols ) {
		$q    = isset( $p['qtd_estoque'] ) ? (int) $p['qtd_estoque'] : 0;
		$sum  = ( $q < 0 ) ? 0 : $q;
		$cols = 1;
	}
	return array( $sum, $cols );
}

/**
 * Índice Asia: chave => [ 'qty' => soma, 'colors' => n, 'key' => referência-pai ].
 * Cada produto alimenta: referência-pai (norm), com/sem "P" final e a referência
 * normalizada de cada variação.
 */
function dcasia_build_index( $catalog, $s ) {
	$idx = array();
	foreach ( $catalog as $p ) {
		$ref = dcasia_norm( isset( $p['referencia'] ) ? $p['referencia'] : '' );
		if ( '' === $ref ) {
			continue;
		}
		list( $sum, $cols ) = dcasia_family_stock( $p );

		$keys = array( $ref );
		if ( ! empty( $s['match_trailing_p'] ) ) {
			$keys[] = preg_replace( '/P$/', '', $ref );
			$keys[] = $ref . 'P';
		}
		if ( isset( $p['variacoes'] ) && is_array( $p['variacoes'] ) ) {
			foreach ( $p['variacoes'] as $v ) {
				if ( ! empty( $v['referencia'] ) ) {
					$keys[] = dcasia_norm( $v['referencia'] );
				}
			}
		}
		foreach ( array_unique( array_filter( $keys ) ) as $k ) {
			if ( ! isset( $idx[ $k ] ) ) {
				$idx[ $k ] = array( 'qty' => $sum, 'colors' => $cols, 'key' => $ref );
			}
		}
	}
	return $idx;
}

function dcasia_lookup( $sku, $index, $s ) {
	$n = dcasia_norm( $sku );
	if ( '' === $n ) {
		return null;
	}
	if ( isset( $index[ $n ] ) ) {
		return $index[ $n ];
	}
	if ( ! empty( $s['match_trailing_p'] ) ) {
		$np = preg_replace( '/P$/', '', $n );
		if ( $np !== $n && isset( $index[ $np ] ) ) {
			return $index[ $np ];
		}
		if ( isset( $index[ $n . 'P' ] ) ) {
			return $index[ $n . 'P' ];
		}
	}
	return null;
}

/**
 * Índice XBZ vivo (para a regra "Asia só cobre o que a XBZ não tem").
 * Retorna [] silenciosamente se as credenciais XBZ faltarem ou a busca falhar.
 */
function dcasia_xbz_index() {
	if ( ! function_exists( 'dcxbz_fetch_catalog' ) ) {
		return array();
	}
	$xs = dcxbz_get_settings();
	if ( empty( $xs['cnpj'] ) || empty( $xs['token'] ) ) {
		return array();
	}
	$cat = dcxbz_fetch_catalog( $xs );
	if ( is_wp_error( $cat ) || ! is_array( $cat ) ) {
		return array();
	}
	return dcxbz_build_index( $cat, $xs );
}

/* -------------------------------------------------------------------------
 * Metadados por referência-pai (p/ criação de produtos novos)
 * ---------------------------------------------------------------------- */

function dcasia_build_family_meta( $catalog ) {
	$fam = array();
	foreach ( $catalog as $p ) {
		$ref = dcasia_norm( isset( $p['referencia'] ) ? $p['referencia'] : '' );
		if ( '' === $ref ) {
			continue;
		}
		list( $qty, $cols ) = dcasia_family_stock( $p );

		$cat_names = array();
		if ( isset( $p['categorias'] ) && is_array( $p['categorias'] ) ) {
			foreach ( $p['categorias'] as $cn ) {
				$cn = trim( (string) $cn );
				if ( '' !== $cn ) {
					$cat_names[] = $cn;
				}
			}
		}
		$ncm = '';
		if ( isset( $p['variacoes'][0]['ncm'] ) ) {
			$ncm = (string) $p['variacoes'][0]['ncm'];
		}

		$fam[ $ref ] = array(
			'name'      => trim( (string) ( $p['nome'] ?? '' ) ),
			'desc'      => trim( wp_strip_all_tags( (string) ( $p['descricao'] ?? '' ) ) ),
			'image'     => (string) ( $p['imagem'] ?? '' ),
			'weight'    => (float) ( $p['peso'] ?? 0 ),          // Asia: kg
			'h'         => (float) ( $p['altura'] ?? 0 ),        // cm
			'w'         => (float) ( $p['largura'] ?? 0 ),       // cm
			'd'         => (float) ( $p['comprimento'] ?? 0 ),   // cm
			'ncm'       => $ncm,
			'qty'       => $qty,
			'colors'    => $cols,
			'cat_names' => $cat_names,
		);
	}
	return $fam;
}

function dcasia_build_create_queue( $fam_meta, $site_keys, $xbz_index, $s ) {
	$rules   = dcxbz_parse_cat_rules( $s['create_cat_rules'] );
	$default = $s['create_cat_default'] ? $s['create_cat_default'] : 'Novidades';
	$min     = max( 0, (int) $s['create_min_stock'] );
	$queue   = array();

	foreach ( $fam_meta as $key => $m ) {
		if ( '' === $m['name'] ) {
			continue;
		}
		$noP = preg_replace( '/P$/', '', $key );
		if ( isset( $site_keys[ $key ] ) || isset( $site_keys[ $noP ] ) || isset( $site_keys[ $key . 'P' ] ) ) {
			continue; // já existe no site
		}
		if ( ! empty( $s['skip_xbz'] ) && $xbz_index && ( isset( $xbz_index[ $key ] ) || isset( $xbz_index[ $noP ] ) ) ) {
			continue; // a XBZ já fornece
		}
		if ( $m['qty'] < $min ) {
			continue;
		}
		$cat = '';
		if ( ! empty( $m['cat_names'] ) ) {
			$cat = trim( (string) end( $m['cat_names'] ) ); // mais específica
		}
		if ( '' === $cat ) {
			$cat = dcxbz_match_category( $m['name'], $rules, $default );
		}
		$queue[] = array(
			'key'    => $key,
			'name'   => $m['name'],
			'desc'   => $m['desc'],
			'image'  => $m['image'],
			'weight' => $m['weight'],
			'h'      => $m['h'],
			'w'      => $m['w'],
			'd'      => $m['d'],
			'ncm'    => $m['ncm'],
			'qty'    => $m['qty'],
			'colors' => $m['colors'],
			'cat'    => $cat,
		);
	}
	return $queue;
}

/* -------------------------------------------------------------------------
 * Sincronização Asia
 * ---------------------------------------------------------------------- */

function dcasia_run_sync( $force_dry = null ) {
	@set_time_limit( 0 );
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}

	$s     = dcasia_get_settings();
	$dry   = is_null( $force_dry ) ? ! empty( $s['dry_run'] ) : (bool) $force_dry;
	$report = array(
		'started'      => current_time( 'mysql' ),
		'finished'     => '',
		'dry_run'      => $dry,
		'ok'           => false,
		'message'      => '',
		'asia_items'   => 0,
		'asia_keys'    => 0,
		'sku_products' => 0,
		'matched'      => 0,
		'changed'      => 0,
		'applied'      => 0,
		'unmatched'    => 0,
		'skipped_xbz'  => 0,
		'skipped_src'  => 0,
		'samples'      => array(),
		'create_candidates' => 0,
		'create_by_cat'     => array(),
	);

	if ( ! function_exists( 'wc_get_product' ) ) {
		$report['message'] = 'WooCommerce não está ativo.';
		return dcasia_store_report( $report );
	}
	if ( empty( $s['api_key'] ) || empty( $s['secret_key'] ) ) {
		$report['message'] = 'Chave/Segredo da API Asia não configurados.';
		return dcasia_store_report( $report );
	}

	$catalog = dcasia_fetch_catalog( $s );
	if ( is_wp_error( $catalog ) ) {
		$report['message'] = 'Falha ao buscar catálogo Asia: ' . $catalog->get_error_message();
		return dcasia_store_report( $report );
	}
	$report['asia_items'] = count( $catalog );

	$index     = dcasia_build_index( $catalog, $s );
	// Tabela "Cores disponíveis": estoque cor por cor (mu-plugin dc-cores-estoque-sync).
	$report['cores'] = function_exists( 'dc_cores_atualizar' ) ? dc_cores_atualizar( dcce_mapa_asia( $catalog ), '_dcasia_source', $dry ) : array();
	// Data em que cada produto foi visto no catálogo (mu-plugin dc-estoque-sob-consulta).
	if ( ! $dry && function_exists( 'dc_forn_marcar_vistos' ) && count( $catalog ) > 400 ) {
		$report['vistos'] = dc_forn_marcar_vistos( 'asia', function ( $c ) use ( $index, $s ) {
			return null !== dcasia_lookup( $c, $index, $s );
		} );
	}
	$fam_meta  = ! empty( $s['create_new'] ) ? dcasia_build_family_meta( $catalog ) : array();
	unset( $catalog );
	$report['asia_keys'] = count( $index );

	$xbz_index    = ! empty( $s['skip_xbz'] ) ? dcasia_xbz_index() : array();
	$xbz_settings = ( $xbz_index && function_exists( 'dcxbz_get_settings' ) ) ? dcxbz_get_settings() : array();

	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT pm.post_id                          AS id,
		        pm.meta_value                       AS sku,
		        COALESCE( ms.meta_value, '' )        AS manage_stock,
		        st.meta_value                       AS stock,
		        COALESCE( ss.meta_value, 'instock' ) AS stock_status,
		        COALESCE( src.meta_value, '' )        AS source
		   FROM {$wpdb->postmeta} pm
		   JOIN {$wpdb->posts} p     ON p.ID = pm.post_id
		   LEFT JOIN {$wpdb->postmeta} ms  ON ms.post_id  = pm.post_id AND ms.meta_key  = '_manage_stock'
		   LEFT JOIN {$wpdb->postmeta} st  ON st.post_id  = pm.post_id AND st.meta_key  = '_stock'
		   LEFT JOIN {$wpdb->postmeta} ss  ON ss.post_id  = pm.post_id AND ss.meta_key  = '_stock_status'
		   LEFT JOIN {$wpdb->postmeta} src ON src.post_id = pm.post_id AND src.meta_key = '_dcxbz_source'
		  WHERE pm.meta_key = '_sku'
		    AND pm.meta_value <> ''
		    AND p.post_type IN ('product','product_variation')
		    AND p.post_status = 'publish'"
	);
	$report['sku_products'] = count( $rows );

	$plan      = array();
	$site_keys = array();
	foreach ( $rows as $r ) {
		if ( ! empty( $s['create_new'] ) ) {
			$nk = dcasia_norm( $r->sku );
			if ( '' !== $nk ) {
				$site_keys[ $nk ] = true;
				$site_keys[ preg_replace( '/P$/', '', $nk ) ] = true;
			}
		}

		// Produto criado pelo módulo XBZ: não é da Asia.
		if ( 'xbz' === $r->source ) {
			$report['skipped_src']++;
			continue;
		}
		// Regra: a XBZ é primária. Se ela cobre este SKU, a Asia não encosta.
		if ( ! empty( $s['skip_xbz'] ) && $xbz_index && null !== dcxbz_lookup( $r->sku, $xbz_index, $xbz_settings ) ) {
			$report['skipped_xbz']++;
			continue;
		}

		$hit = dcasia_lookup( $r->sku, $index, $s );
		if ( null === $hit ) {
			$report['unmatched']++;
			continue;
		}
		$report['matched']++;

		$new_qty     = max( 0, (int) $hit['qty'] );
		$cur_managed = ( 'yes' === $r->manage_stock );
		$cur_qty     = ( null === $r->stock || '' === $r->stock ) ? null : (int) $r->stock;
		$cur_status  = $r->stock_status ? $r->stock_status : 'instock';
		$keep_avail  = empty( $s['set_out_of_stock'] );

		if ( $keep_avail && $new_qty <= 0 ) {
			$want_status = 'instock';
			$needs       = $cur_managed || ( 'instock' !== $cur_status );
		} elseif ( ! empty( $s['set_out_of_stock'] ) ) {
			$want_status = ( $new_qty <= (int) $s['oos_threshold'] ) ? 'outofstock' : 'instock';
			$needs       = ( $cur_qty !== $new_qty )
				|| ( ! empty( $s['manage_stock'] ) && ! $cur_managed )
				|| ( $cur_status !== $want_status );
		} else {
			$want_status = 'instock';
			$needs       = ( $cur_qty !== $new_qty )
				|| ( ! empty( $s['manage_stock'] ) && ! $cur_managed )
				|| ( 'instock' !== $cur_status );
		}

		if ( ! $needs ) {
			continue;
		}
		$report['changed']++;
		$plan[] = array(
			'id'     => (int) $r->id,
			'sku'    => $r->sku,
			'from'   => is_null( $cur_qty ) ? '—' : (int) $cur_qty,
			'to'     => $new_qty,
			'status' => $want_status,
			'key'    => $hit['key'],
			'colors' => $hit['colors'],
		);
		if ( count( $report['samples'] ) < 40 ) {
			$report['samples'][] = $plan[ count( $plan ) - 1 ];
		}
	}

	// --- Produtos novos: referências Asia ausentes no site e (opcionalmente) na XBZ ---
	if ( ! empty( $s['create_new'] ) && $fam_meta ) {
		$cqueue = dcasia_build_create_queue( $fam_meta, $site_keys, $xbz_index, $s );
		$report['create_candidates'] = count( $cqueue );
		foreach ( $cqueue as $c ) {
			$report['create_by_cat'][ $c['cat'] ] = ( $report['create_by_cat'][ $c['cat'] ] ?? 0 ) + 1;
		}
		arsort( $report['create_by_cat'] );
		if ( ! $dry ) {
			update_option( DCASIA_CREATE_QUEUE_OPT, $cqueue, false );
			update_option( DCASIA_CREATE_REPORT_OPT, array( 'created' => 0, 'skipped' => 0, 'errors' => 0, 'last' => array(), 'when' => current_time( 'mysql' ) ), false );
		}
	}

	$report['ok'] = true;

	if ( $dry ) {
		$report['message']  = sprintf(
			'Simulação concluída. %d casaram · %d sem correspondência · %d pulados (XBZ) · %d a criar. Nada gravado.',
			$report['matched'],
			$report['unmatched'],
			$report['skipped_xbz'],
			$report['create_candidates']
		);
		$report['finished'] = current_time( 'mysql' );
		return dcasia_store_report( $report );
	}

	update_option( DCASIA_PLAN_OPT, $plan, false );
	$report['finished'] = current_time( 'mysql' );
	dcasia_store_report( $report );

	dcasia_drain_plan( 40 );
	$rest = count( get_option( DCASIA_PLAN_OPT, array() ) );

	$crest = 0;
	if ( ! empty( $s['create_new'] ) && get_option( DCASIA_CREATE_QUEUE_OPT ) ) {
		dcasia_drain_create_queue( 40 );
		$crest = count( get_option( DCASIA_CREATE_QUEUE_OPT, array() ) );
	}

	$report            = get_option( DCASIA_REPORT_OPT, array() );
	$report['applied'] = isset( $report['applied'] ) ? (int) $report['applied'] : 0;
	$msg               = $rest > 0
		? sprintf( '%d estoque(s) atualizado(s); faltam %d.', $report['applied'], $rest )
		: sprintf( '%d estoque(s) atualizado(s).', $report['applied'] );
	if ( ! empty( $s['create_new'] ) ) {
		$cr  = get_option( DCASIA_CREATE_REPORT_OPT, array() );
		$crd = isset( $cr['created'] ) ? (int) $cr['created'] : 0;
		$msg .= $crest > 0
			? sprintf( ' Novos: %d criado(s), faltam %d — continuam ao abrir o painel.', $crd, $crest )
			: sprintf( ' Novos: %d criado(s). Concluído.', $crd );
	} else {
		$msg .= $rest > 0 ? ' Reabra o painel para concluir.' : ' Concluído.';
	}
	$report['message'] = $msg;
	update_option( DCASIA_REPORT_OPT, $report, false );

	return $report;
}

function dcasia_drain_plan( $budget_seconds = 30 ) {
	$plan = get_option( DCASIA_PLAN_OPT, array() );
	if ( ! is_array( $plan ) || empty( $plan ) || ! function_exists( 'wc_update_product_stock' ) ) {
		return 0;
	}
	@set_time_limit( 0 );
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}

	$s     = dcasia_get_settings();
	$start = microtime( true );
	$done  = 0;
	$manage = ! empty( $s['manage_stock'] );
	$keep_available = empty( $s['set_out_of_stock'] );

	while ( ! empty( $plan ) && ( microtime( true ) - $start ) < $budget_seconds ) {
		$row     = array_shift( $plan );
		$id      = (int) $row['id'];
		$new_qty = (int) $row['to'];

		if ( $keep_available && $new_qty <= 0 ) {
			update_post_meta( $id, '_manage_stock', 'no' );
			update_post_meta( $id, '_stock', '' );
			update_post_meta( $id, '_stock_status', 'instock' );
			delete_post_meta( $id, '_low_stock_amount' );
		} else {
			if ( $manage && 'yes' !== get_post_meta( $id, '_manage_stock', true ) ) {
				update_post_meta( $id, '_manage_stock', 'yes' );
			}
			if ( 'yes' === get_post_meta( $id, '_manage_stock', true ) ) {
				wc_update_product_stock( $id, $new_qty, 'set' );
			}
			$desired = empty( $s['set_out_of_stock'] ) ? 'instock' : $row['status'];
			if ( get_post_meta( $id, '_stock_status', true ) !== $desired ) {
				update_post_meta( $id, '_stock_status', $desired );
			}
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $id );
		}
		$done++;
		if ( 0 === $done % 25 ) {
			update_option( DCASIA_PLAN_OPT, array_values( $plan ), false );
		}
	}

	if ( empty( $plan ) ) {
		delete_option( DCASIA_PLAN_OPT );
	} else {
		update_option( DCASIA_PLAN_OPT, array_values( $plan ), false );
	}

	$rep = get_option( DCASIA_REPORT_OPT, array() );
	if ( is_array( $rep ) ) {
		$rep['applied'] = ( isset( $rep['applied'] ) ? (int) $rep['applied'] : 0 ) + $done;
		update_option( DCASIA_REPORT_OPT, $rep, false );
	}
	return $done;
}

function dcasia_drain_create_queue( $budget_seconds = 40 ) {
	$q = get_option( DCASIA_CREATE_QUEUE_OPT, array() );
	if ( ! is_array( $q ) || empty( $q ) || ! class_exists( 'WC_Product_Simple' ) ) {
		return 0;
	}
	@set_time_limit( 0 );
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$s        = dcasia_get_settings();
	$publish  = ! empty( $s['create_publish'] );
	$with_img = ! empty( $s['create_image'] );
	$start    = microtime( true );
	$done     = 0;
	$rep      = get_option( DCASIA_CREATE_REPORT_OPT, array() );
	if ( ! is_array( $rep ) ) {
		$rep = array();
	}
	$rep += array( 'created' => 0, 'skipped' => 0, 'errors' => 0, 'last' => array(), 'when' => current_time( 'mysql' ) );

	while ( ! empty( $q ) && ( microtime( true ) - $start ) < $budget_seconds ) {
		$c   = array_shift( $q );
		$sku = (string) $c['key'];

		if ( '' === $sku || wc_get_product_id_by_sku( $sku ) || ( function_exists( 'dc_sku_bloqueado' ) && dc_sku_bloqueado( $sku ) ) ) {
			$rep['skipped']++;
			$done++;
			continue;
		}

		try {
			$p = new WC_Product_Simple();
			$p->set_name( dcxbz_title_case( $c['name'] ) );
			$p->set_sku( $sku );
			$p->set_status( $publish ? 'publish' : 'draft' );
			$p->set_catalog_visibility( 'visible' );
			if ( ! empty( $c['desc'] ) ) {
				$p->set_description( $c['desc'] );
			}

			$qty = max( 0, (int) $c['qty'] );
			if ( $qty > 0 ) {
				$p->set_manage_stock( true );
				$p->set_stock_quantity( $qty );
			} else {
				$p->set_manage_stock( false );
			}
			$p->set_stock_status( 'instock' );

			if ( $c['weight'] > 0 && $c['weight'] < 200 ) {
				$p->set_weight( round( (float) $c['weight'], 3 ) ); // Asia: já em kg
			}
			if ( $c['h'] > 0 ) {
				$p->set_height( $c['h'] );
			}
			if ( $c['w'] > 0 ) {
				$p->set_width( $c['w'] );
			}
			if ( $c['d'] > 0 ) {
				$p->set_length( $c['d'] );
			}

			$term_id = dcxbz_get_or_create_term( $c['cat'] );
			if ( $term_id ) {
				$p->set_category_ids( array( $term_id ) );
			}

			$id = $p->save();
		} catch ( Exception $e ) {
			$rep['errors']++;
			$done++;
			continue;
		}

		if ( ! $id ) {
			$rep['errors']++;
			$done++;
			continue;
		}

		update_post_meta( $id, '_dcasia_source', 'asia' );
		update_post_meta( $id, '_dcasia_ref', $sku );
		update_post_meta( $id, '_dcasia_created', current_time( 'mysql' ) );
		if ( ! empty( $c['ncm'] ) ) {
			update_post_meta( $id, '_dcasia_ncm', $c['ncm'] );
		}

		$got_img = false;
		if ( $with_img && ! empty( $c['image'] ) ) {
			$att = media_sideload_image( $c['image'], $id, $c['name'], 'id' );
			if ( ! is_wp_error( $att ) ) {
				set_post_thumbnail( $id, $att );
				$got_img = true;
			}
		}
		if ( ! empty( $c['image'] ) ) {
			update_post_meta( $id, '_dcasia_image_url', esc_url_raw( $c['image'] ) );
		}
		if ( $with_img && ! empty( $c['image'] ) && ! $got_img ) {
			update_post_meta( $id, '_dcasia_needs_image', 1 );
			if ( $publish ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
			}
		}
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $id );
		}

		$rep['created']++;
		array_unshift(
			$rep['last'],
			array( 'sku' => $sku, 'name' => dcxbz_title_case( $c['name'] ), 'cat' => $c['cat'], 'id' => (int) $id, 'qty' => $qty )
		);
		$rep['last'] = array_slice( $rep['last'], 0, 25 );
		$done++;

		if ( 0 === $done % 10 ) {
			update_option( DCASIA_CREATE_QUEUE_OPT, array_values( $q ), false );
		}
	}

	if ( empty( $q ) ) {
		delete_option( DCASIA_CREATE_QUEUE_OPT );
	} else {
		update_option( DCASIA_CREATE_QUEUE_OPT, array_values( $q ), false );
	}
	update_option( DCASIA_CREATE_REPORT_OPT, $rep, false );
	return $done;
}

/* -------------------------------------------------------------------------
 * Relatório / log
 * ---------------------------------------------------------------------- */

function dcasia_store_report( $report ) {
	update_option( DCASIA_REPORT_OPT, $report, false );
	$log = get_option( DCASIA_LOG_OPT, array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	array_unshift(
		$log,
		array(
			'when'      => $report['started'],
			'dry_run'   => $report['dry_run'],
			'ok'        => $report['ok'],
			'message'   => $report['message'],
			'matched'   => $report['matched'],
			'changed'   => $report['changed'],
			'unmatched' => $report['unmatched'],
		)
	);
	$log = array_slice( $log, 0, DCASIA_LOG_KEEP );
	update_option( DCASIA_LOG_OPT, $log, false );
	return $report;
}

/* -------------------------------------------------------------------------
 * Drena filas Asia ao abrir qualquer tela do admin (sem Action Scheduler)
 * ---------------------------------------------------------------------- */

add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	if ( get_option( DCASIA_PLAN_OPT ) ) {
		dcasia_drain_plan( 20 );
	}
	if ( get_option( DCASIA_CREATE_QUEUE_OPT ) ) {
		dcasia_drain_create_queue( 20 );
	}
} );

/* -------------------------------------------------------------------------
 * Registro da configuração + navegação por abas
 * ---------------------------------------------------------------------- */

add_action( 'admin_init', function () {
	register_setting( 'dcasia_group', DCASIA_OPT, 'dcasia_sanitize' );
} );

function dcasia_sanitize( $in ) {
	$d   = dcasia_defaults();
	$out = array();
	$out['base_url']         = esc_url_raw( isset( $in['base_url'] ) ? $in['base_url'] : $d['base_url'] );
	$out['api_key']          = sanitize_text_field( $in['api_key'] ?? '' );
	$out['secret_key']       = sanitize_text_field( $in['secret_key'] ?? '' );
	$out['per_page']         = min( 100, max( 1, (int) ( $in['per_page'] ?? $d['per_page'] ) ) );
	$out['frequency']        = in_array( ( $in['frequency'] ?? '' ), array( 'hourly', 'twicedaily', 'daily' ), true ) ? $in['frequency'] : $d['frequency'];
	$out['set_out_of_stock'] = empty( $in['set_out_of_stock'] ) ? 0 : 1;
	$out['oos_threshold']    = max( 0, (int) ( $in['oos_threshold'] ?? 0 ) );
	$out['manage_stock']     = empty( $in['manage_stock'] ) ? 0 : 1;
	$out['match_trailing_p'] = empty( $in['match_trailing_p'] ) ? 0 : 1;
	$out['skip_xbz']         = empty( $in['skip_xbz'] ) ? 0 : 1;
	$out['dry_run']          = empty( $in['dry_run'] ) ? 0 : 1;
	$out['enabled']          = empty( $in['enabled'] ) ? 0 : 1;

	$out['create_new']         = empty( $in['create_new'] ) ? 0 : 1;
	$out['create_publish']     = empty( $in['create_publish'] ) ? 0 : 1;
	$out['create_image']       = empty( $in['create_image'] ) ? 0 : 1;
	$out['create_min_stock']   = max( 0, (int) ( $in['create_min_stock'] ?? $d['create_min_stock'] ) );
	$out['create_batch']       = min( 100, max( 1, (int) ( $in['create_batch'] ?? $d['create_batch'] ) ) );
	$out['create_cat_default'] = sanitize_text_field( $in['create_cat_default'] ?? $d['create_cat_default'] );
	$out['create_cat_rules']   = sanitize_textarea_field( $in['create_cat_rules'] ?? $d['create_cat_rules'] );

	return $out;
}

function dcxbz_render_supplier_tabs( $active ) {
	$base = admin_url( 'admin.php?page=dcxbz' );
	printf(
		'<h2 class="nav-tab-wrapper" style="margin:.5em 0 1em">
			<a href="%s" class="nav-tab %s">XBZ &middot; primária</a>
			<a href="%s" class="nav-tab %s">Asia Import &middot; secundária</a>
		</h2>',
		esc_url( $base ),
		'xbz' === $active ? 'nav-tab-active' : '',
		esc_url( add_query_arg( 'tab', 'asia', $base ) ),
		'asia' === $active ? 'nav-tab-active' : ''
	);
}

/* -------------------------------------------------------------------------
 * UI da aba Asia Import
 * ---------------------------------------------------------------------- */

function dcasia_admin_tab() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	if ( isset( $_POST['dcasia_action'] ) && check_admin_referer( 'dcasia_run' ) ) {
		$action = sanitize_text_field( wp_unslash( $_POST['dcasia_action'] ) );
		if ( 'test' === $action ) {
			$c = dcasia_fetch_catalog( dcasia_get_settings() );
			if ( is_wp_error( $c ) ) {
				echo '<div class="notice notice-error"><p>Falha na conexão: ' . esc_html( $c->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>Conexão OK — ' . (int) count( $c ) . ' produto(s) recebidos da Asia Import.</p></div>';
			}
		} elseif ( 'dry' === $action ) {
			$r = dcasia_run_sync( true );
			echo '<div class="notice notice-info"><p><strong>Simulação:</strong> ' . esc_html( $r['message'] ) . '</p></div>';
		} elseif ( 'live' === $action ) {
			if ( get_option( DCASIA_PLAN_OPT ) ) {
				$n    = dcasia_drain_plan( 45 );
				$rest = count( get_option( DCASIA_PLAN_OPT, array() ) );
				echo '<div class="notice notice-success"><p><strong>Execução:</strong> ' . (int) $n . ' aplicado(s) nesta passada. ' . ( $rest ? (int) $rest . ' restante(s).' : 'Concluído.' ) . '</p></div>';
			} else {
				$r = dcasia_run_sync( false );
				echo '<div class="notice notice-success"><p><strong>Execução:</strong> ' . esc_html( $r['message'] ) . '</p></div>';
			}
		} elseif ( 'continue' === $action ) {
			$n    = dcasia_drain_plan( 45 );
			$rest = count( get_option( DCASIA_PLAN_OPT, array() ) );
			echo '<div class="notice notice-success"><p><strong>Continuar:</strong> ' . (int) $n . ' aplicado(s). ' . ( $rest ? (int) $rest . ' restante(s).' : 'Concluído.' ) . '</p></div>';
		} elseif ( 'preview_create' === $action ) {
			$r = dcasia_run_sync( true );
			echo '<div class="notice notice-info"><p><strong>Prévia de criação:</strong> ' . (int) ( $r['create_candidates'] ?? 0 ) . ' produto(s) seriam criados.</p></div>';
		} elseif ( 'create_batch' === $action || 'create_continue' === $action ) {
			$s = dcasia_get_settings();
			if ( empty( $s['create_new'] ) ) {
				echo '<div class="notice notice-error"><p>Ative "Criar produtos novos" e salve antes de rodar.</p></div>';
			} else {
				if ( ! get_option( DCASIA_CREATE_QUEUE_OPT ) && 'create_batch' === $action ) {
					dcasia_run_sync( false );
				}
				dcasia_drain_create_queue( 45 );
				$crest = count( get_option( DCASIA_CREATE_QUEUE_OPT, array() ) );
				$cr    = get_option( DCASIA_CREATE_REPORT_OPT, array() );
				echo '<div class="notice notice-success"><p><strong>Criação:</strong> ' . (int) ( $cr['created'] ?? 0 ) . ' criados · ' . (int) ( $cr['skipped'] ?? 0 ) . ' pulados · ' . (int) ( $cr['errors'] ?? 0 ) . ' erros. ' . ( $crest ? (int) $crest . ' na fila.' : 'Fila concluída.' ) . '</p></div>';
			}
		}
	}

	$s      = dcasia_get_settings();
	$report = get_option( DCASIA_REPORT_OPT, array() );
	$log    = get_option( DCASIA_LOG_OPT, array() );
	$opt    = esc_attr( DCASIA_OPT );
	?>
	<div class="wrap">
		<h1>Sincronização de Estoque — Asia Import</h1>
		<?php dcxbz_render_supplier_tabs( 'asia' ); ?>
		<p style="max-width:860px">
			Fornecedor <strong>secundário</strong>. Casa produtos do site pela <code>referencia</code> da Asia
			(com ou sem o <code>P</code> final) e pela <code>referencia</code> de cada variação.
			Estoque do produto = <strong>soma de todas as variações</strong> da mesma referência
			(estoque negativo conta como 0). Produto sem correspondência na Asia <strong>nunca</strong> é alterado.
		</p>
		<p class="description" style="max-width:860px">
			Com <strong>“Respeitar a XBZ”</strong> ligado, todo produto que a XBZ já fornece é ignorado aqui —
			a Asia só mexe no que sobra. Isso exige as credenciais da XBZ preenchidas na aba XBZ
			(o catálogo XBZ é buscado junto a cada execução).
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'dcasia_group' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Base URL</th>
					<td><input type="text" class="regular-text" name="<?php echo $opt; ?>[base_url]" value="<?php echo esc_attr( $s['base_url'] ); ?>"></td></tr>
				<tr><th scope="row">API Key (Chave)</th>
					<td><input type="text" class="regular-text" name="<?php echo $opt; ?>[api_key]" value="<?php echo esc_attr( $s['api_key'] ); ?>" autocomplete="off"></td></tr>
				<tr><th scope="row">Secret Key (Senha)</th>
					<td><input type="password" class="regular-text" name="<?php echo $opt; ?>[secret_key]" value="<?php echo esc_attr( $s['secret_key'] ); ?>" autocomplete="off"></td></tr>
				<tr><th scope="row">Itens por página</th>
					<td><input type="number" min="1" max="100" style="width:80px" name="<?php echo $opt; ?>[per_page]" value="<?php echo esc_attr( $s['per_page'] ); ?>"> <span class="description">máx. 100</span></td></tr>
				<tr><th scope="row">Frequência</th>
					<td>
						<select name="<?php echo $opt; ?>[frequency]">
							<option value="hourly"     <?php selected( $s['frequency'], 'hourly' ); ?>>A cada hora</option>
							<option value="twicedaily" <?php selected( $s['frequency'], 'twicedaily' ); ?>>2× ao dia</option>
							<option value="daily"      <?php selected( $s['frequency'], 'daily' ); ?>>1× ao dia</option>
						</select>
					</td></tr>
				<tr><th scope="row">Regras</th>
					<td>
						<label><input type="checkbox" name="<?php echo $opt; ?>[skip_xbz]" value="1" <?php checked( $s['skip_xbz'], 1 ); ?>> <strong>Respeitar a XBZ</strong> — não tocar em produtos que a XBZ já fornece</label><br>
						<label><input type="checkbox" name="<?php echo $opt; ?>[match_trailing_p]" value="1" <?php checked( $s['match_trailing_p'], 1 ); ?>> Casar a referência com/sem o <code>P</code> final</label><br>
						<label><input type="checkbox" name="<?php echo $opt; ?>[manage_stock]" value="1" <?php checked( $s['manage_stock'], 1 ); ?>> Ligar o “gerenciar estoque” do produto ao atualizar</label><br>
						<label><input type="checkbox" name="<?php echo $opt; ?>[set_out_of_stock]" value="1" <?php checked( $s['set_out_of_stock'], 1 ); ?>> Marcar “fora de estoque” quando a soma for &le;
							<input type="number" min="0" style="width:70px" name="<?php echo $opt; ?>[oos_threshold]" value="<?php echo esc_attr( $s['oos_threshold'] ); ?>"></label>
					</td></tr>
				<tr><th scope="row">Modo</th>
					<td>
						<label><input type="checkbox" name="<?php echo $opt; ?>[dry_run]" value="1" <?php checked( $s['dry_run'], 1 ); ?>> <strong>Simulação</strong> — calcula tudo mas não grava</label><br>
						<label><input type="checkbox" name="<?php echo $opt; ?>[enabled]" value="1" <?php checked( $s['enabled'], 1 ); ?>> <strong>Ativar sincronização automática</strong> (cron)</label>
					</td></tr>

				<tr><th colspan="2"><hr><h2 style="margin:.4em 0">Produtos novos (Asia)</h2>
					<p class="description" style="max-width:860px;font-weight:400">
						Cria no site as referências da Asia que ainda não existem aqui — e que a XBZ também não fornece
						(quando “Respeitar a XBZ” está ligado). Estoque = soma das variações. Categoria: a mais específica
						vinda de <code>categorias</code> da Asia; sem categoria, cai nas regras por palavra-chave abaixo.
						Preço não é definido (fluxo de orçamento). Use <em>Prever criação</em> antes de ligar.
					</p></th></tr>
				<tr><th scope="row">Criar</th>
					<td>
						<label><input type="checkbox" name="<?php echo $opt; ?>[create_new]" value="1" <?php checked( $s['create_new'], 1 ); ?>> <strong>Criar produtos novos</strong> (mestre)</label><br>
						<label><input type="checkbox" name="<?php echo $opt; ?>[create_publish]" value="1" <?php checked( $s['create_publish'], 1 ); ?>> Publicar direto (desmarque para criar como rascunho)</label><br>
						<label><input type="checkbox" name="<?php echo $opt; ?>[create_image]" value="1" <?php checked( $s['create_image'], 1 ); ?>> Baixar a imagem principal da Asia</label><br>
						<label>Só criar se a soma de estoque for &ge;
							<input type="number" min="0" style="width:70px" name="<?php echo $opt; ?>[create_min_stock]" value="<?php echo esc_attr( $s['create_min_stock'] ); ?>"></label><br>
						<label>Criar por passada:
							<input type="number" min="1" max="100" style="width:70px" name="<?php echo $opt; ?>[create_batch]" value="<?php echo esc_attr( $s['create_batch'] ); ?>"></label>
					</td></tr>
				<tr><th scope="row">Categoria padrão</th>
					<td><input type="text" class="regular-text" name="<?php echo $opt; ?>[create_cat_default]" value="<?php echo esc_attr( $s['create_cat_default'] ); ?>"></td></tr>
				<tr><th scope="row">Regras de categoria</th>
					<td>
						<textarea name="<?php echo $opt; ?>[create_cat_rules]" rows="8" class="large-text code" spellcheck="false"><?php echo esc_textarea( $s['create_cat_rules'] ); ?></textarea>
						<p class="description">Usadas só quando a Asia não manda categoria. Uma por linha: <code>palavra-chave =&gt; Nome da Categoria</code>.</p>
					</td></tr>
			</table>
			<?php submit_button( 'Salvar configurações' ); ?>
		</form>

		<hr>
		<h2>Rodar agora</h2>
		<form method="post">
			<?php wp_nonce_field( 'dcasia_run' ); ?>
			<p>
				<button class="button" name="dcasia_action" value="test">Testar conexão</button>
				<button class="button button-secondary" name="dcasia_action" value="dry">Rodar simulação</button>
				<button class="button button-primary" name="dcasia_action" value="live" onclick="return confirm('Gravar as alterações de estoque da Asia agora?');">Rodar e gravar (estoque)</button>
				<?php if ( get_option( DCASIA_PLAN_OPT ) ) : ?>
					<button class="button" name="dcasia_action" value="continue">Continuar (fila: <?php echo (int) count( get_option( DCASIA_PLAN_OPT, array() ) ); ?>)</button>
				<?php endif; ?>
			</p>
			<?php if ( ! empty( $s['create_new'] ) ) : ?>
				<p>
					<button class="button button-secondary" name="dcasia_action" value="preview_create">Prever criação</button>
					<button class="button button-primary" name="dcasia_action" value="create_batch" onclick="return confirm('Criar produtos novos da Asia no site agora? (em lotes)');">Criar produtos novos</button>
					<?php if ( get_option( DCASIA_CREATE_QUEUE_OPT ) ) : ?>
						<button class="button" name="dcasia_action" value="create_continue">Continuar criação (fila: <?php echo (int) count( get_option( DCASIA_CREATE_QUEUE_OPT, array() ) ); ?>)</button>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</form>

		<?php
		$crep  = get_option( DCASIA_CREATE_REPORT_OPT, array() );
		$cqcnt = (int) count( get_option( DCASIA_CREATE_QUEUE_OPT, array() ) );
		if ( $crep || $cqcnt ) :
			?>
			<h3>Criação de produtos novos</h3>
			<p>
				Criados: <strong><?php echo (int) ( $crep['created'] ?? 0 ); ?></strong> ·
				Pulados (já existiam): <strong><?php echo (int) ( $crep['skipped'] ?? 0 ); ?></strong> ·
				Erros: <strong><?php echo (int) ( $crep['errors'] ?? 0 ); ?></strong> ·
				Na fila: <strong><?php echo $cqcnt; ?></strong>
			</p>
			<?php if ( ! empty( $report['create_by_cat'] ) ) : ?>
				<p><strong>Prévia por categoria:</strong>
					<?php
					$parts = array();
					foreach ( $report['create_by_cat'] as $cat => $n ) {
						$parts[] = esc_html( $cat ) . ' (' . (int) $n . ')';
					}
					echo wp_kses_post( implode( ' · ', $parts ) );
					?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $crep['last'] ) ) : ?>
				<table class="widefat striped" style="max-width:900px">
					<thead><tr><th>Produto criado</th><th>Ref.</th><th>Categoria</th><th>Estoque</th><th>ID</th></tr></thead>
					<tbody>
					<?php foreach ( $crep['last'] as $c ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( (int) $c['id'] ) ); ?>"><?php echo esc_html( $c['name'] ); ?></a></td>
							<td><code><?php echo esc_html( $c['sku'] ); ?></code></td>
							<td><?php echo esc_html( $c['cat'] ); ?></td>
							<td><?php echo (int) $c['qty']; ?></td>
							<td><?php echo (int) $c['id']; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $report ) : ?>
			<hr>
			<h2>Último resultado — <?php echo esc_html( $report['started'] ?? '' ); ?> <?php echo ! empty( $report['dry_run'] ) ? '<em>(simulação)</em>' : ''; ?></h2>
			<p><?php echo esc_html( $report['message'] ?? '' ); ?></p>
			<ul style="list-style:disc;margin-left:20px">
				<li>Produtos recebidos da Asia: <strong><?php echo (int) ( $report['asia_items'] ?? 0 ); ?></strong> · chaves de busca: <strong><?php echo (int) ( $report['asia_keys'] ?? 0 ); ?></strong></li>
				<li>Produtos do site com SKU: <strong><?php echo (int) ( $report['sku_products'] ?? 0 ); ?></strong></li>
				<li>Casaram com a Asia: <strong><?php echo (int) ( $report['matched'] ?? 0 ); ?></strong> · sem correspondência (intocados): <strong><?php echo (int) ( $report['unmatched'] ?? 0 ); ?></strong></li>
				<li>Pulados por já serem da XBZ: <strong><?php echo (int) ( $report['skipped_xbz'] ?? 0 ); ?></strong> · criados pelo módulo XBZ: <strong><?php echo (int) ( $report['skipped_src'] ?? 0 ); ?></strong></li>
				<li>Com estoque diferente: <strong><?php echo (int) ( $report['changed'] ?? 0 ); ?></strong> · já gravados: <strong><?php echo (int) ( $report['applied'] ?? 0 ); ?></strong></li>
			</ul>
			<?php if ( ! empty( $report['samples'] ) ) : ?>
				<h3>Amostra das alterações previstas (até 40)</h3>
				<table class="widefat striped" style="max-width:900px">
					<thead><tr><th>Produto</th><th>SKU</th><th>De</th><th>Para</th><th>Status</th><th>Ref. Asia</th><th>Variações somadas</th></tr></thead>
					<tbody>
					<?php
					foreach ( $report['samples'] as $row ) :
						$p = wc_get_product( (int) $row['id'] );
						?>
						<tr>
							<td><?php echo $p ? esc_html( $p->get_name() ) : ( '#' . (int) $row['id'] ); ?></td>
							<td><code><?php echo esc_html( $row['sku'] ); ?></code></td>
							<td><?php echo esc_html( $row['from'] ); ?></td>
							<td><strong><?php echo (int) $row['to']; ?></strong></td>
							<td><?php echo esc_html( $row['status'] ); ?></td>
							<td><code><?php echo esc_html( $row['key'] ); ?></code></td>
							<td><?php echo (int) $row['colors']; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $log ) : ?>
			<hr>
			<h2>Histórico</h2>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th>Quando</th><th>Modo</th><th>OK</th><th>Casaram</th><th>Alteraram</th><th>Sem match</th><th>Mensagem</th></tr></thead>
				<tbody>
				<?php foreach ( $log as $l ) : ?>
					<tr>
						<td><?php echo esc_html( $l['when'] ); ?></td>
						<td><?php echo ! empty( $l['dry_run'] ) ? 'simulação' : 'gravação'; ?></td>
						<td><?php echo ! empty( $l['ok'] ) ? '✓' : '✗'; ?></td>
						<td><?php echo (int) $l['matched']; ?></td>
						<td><?php echo (int) $l['changed']; ?></td>
						<td><?php echo (int) $l['unmatched']; ?></td>
						<td><?php echo esc_html( $l['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<hr>
		<p style="color:#666">Próxima execução automática:
			<?php
			$next = wp_next_scheduled( DCASIA_CRON_HOOK );
			if ( ! $next && function_exists( 'as_next_scheduled_action' ) ) {
				$n    = as_next_scheduled_action( DCASIA_CRON_HOOK );
				$next = is_numeric( $n ) ? $n : 0;
			}
			echo $next ? esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ), 'd/m/Y H:i' ) ) : '— (automático desligado)';
			?>
		</p>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * WP-CLI: wp dcxbz sync [--live]  ·  wp dcasia sync [--live]
 * ---------------------------------------------------------------------- */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'dcxbz sync',
		function ( $args, $assoc ) {
			$live = isset( $assoc['live'] );
			$r    = dcxbz_run_sync( ! $live );
			WP_CLI::line( wp_json_encode( $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			WP_CLI::success( $r['message'] );
		}
	);
	WP_CLI::add_command(
		'dcasia sync',
		function ( $args, $assoc ) {
			$live = isset( $assoc['live'] );
			$r    = dcasia_run_sync( ! $live );
			WP_CLI::line( wp_json_encode( $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			WP_CLI::success( $r['message'] );
		}
	);
}
