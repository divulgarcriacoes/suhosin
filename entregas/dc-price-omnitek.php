<?php
/**
 * Módulo XBZ / Asia Import / Spot — custo do fornecedor + gravação pela tabela
 * Omnitek (percentual sobre o custo) para as categorias do catálogo. Produtos
 * fora do mapeamento abaixo usam a técnica genérica (estimada) ou ficam só orçamento.
 *
 * Fórmula: preço unitário = (custo + gravação_unitária) × margem × imposto
 * gravação = custo × % da técnica (Tampografia 25%, Silk 22%, demais 20%).
 * Produtos Spot: se a gravação real da Spot (× acréscimo) for MENOR que a do
 * percentual, vale a da Spot — o percentual é sempre o teto.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Tabela Omnitek: cada linha = { limite (peças até onde vale o mínimo),
 * minimo (R$ fixo do pedido até o limite), preco_unit (R$/und acima do
 * limite) }. Vem direto do "Catálogo de Preços - 2025.pdf" da Omnitek.
 * ---------------------------------------------------------------------- */
function dcprice_omnitek_tabela() {
	return array(
		'sublimacao_chinelo'          => array( 'label' => 'Sublimação', 'limite' => 28, 'minimo' => 110.00, 'preco_unit' => 3.85 ),
		'laser_canetas_lapiseiras'    => array( 'label' => 'Laser', 'limite' => 100, 'minimo' => 80.00, 'preco_unit' => 0.55 ),
		'laser_chaveiro'              => array( 'label' => 'Laser', 'limite' => 100, 'minimo' => 100.00, 'preco_unit' => 0.90 ),
		'silk_mochila_mala'           => array( 'label' => 'Silk', 'limite' => 100, 'minimo' => 260.00, 'preco_unit' => 2.40 ),
		'silk_bloco_agenda'           => array( 'label' => 'Silk', 'limite' => 100, 'minimo' => 200.00, 'preco_unit' => 0.70 ),
		'silk_garrafa_copo_180'       => array( 'label' => 'Silk', 'limite' => 100, 'minimo' => 200.00, 'preco_unit' => 0.90 ),
		'sublimacao_caneca_squeeze'   => array( 'label' => 'Sublimação', 'limite' => 28, 'minimo' => 110.00, 'preco_unit' => 3.85 ),
		'digitaluv_power_bloco'       => array( 'label' => 'Digital UV', 'limite' => 49, 'minimo' => 143.00, 'preco_unit' => 2.85 ),
		'digitaluv_caixa_som'         => array( 'label' => 'Digital UV', 'limite' => 50, 'minimo' => 160.00, 'preco_unit' => 1.50 ),
		'tampografia_caneta_plastica' => array( 'label' => 'Tampografia', 'limite' => 1000, 'minimo' => 130.00, 'preco_unit' => 0.13 ), // R$130/milheiro = R$0,13/und
		'digitaluv_caneta_plastica'   => array( 'label' => 'Digital UV', 'limite' => 399, 'minimo' => 110.00, 'preco_unit' => 0.28 ),
		// --- novas linhas (mesmo catálogo Omnitek, páginas extras) ---
		'silk_necessaire_squeeze_dobravel' => array( 'label' => 'Silk', 'limite' => 150, 'minimo' => 230.00, 'preco_unit' => 1.45 ),
		'silk_sacola_ecobag_tecido'        => array( 'label' => 'Silk', 'limite' => 100, 'minimo' => 187.00, 'preco_unit' => 1.65 ),
		'silk_bolsa_termica_guardachuva'   => array( 'label' => 'Silk', 'limite' => 100, 'minimo' => 220.00, 'preco_unit' => 2.10 ),
		'digitaluv_pequenos_eletronicos'   => array( 'label' => 'Digital UV', 'limite' => 149, 'minimo' => 148.50, 'preco_unit' => 1.00 ), // porta cartão, carregador, régua 20cm, chaveiro, fone de ouvido
		'digitaluv_bloco_caixasom_p'       => array( 'label' => 'Digital UV', 'limite' => 49, 'minimo' => 143.00, 'preco_unit' => 2.85 ), // bloco P, caixa de som P, carregador G, régua 30cm, estojo caneta
		'digitaluv_kit_vinho_porta_doc'    => array( 'label' => 'Digital UV', 'limite' => 49, 'minimo' => 165.00, 'preco_unit' => 3.20 ), // kit vinho, porta documento, caderno P, agenda, bloco G
	);
}

/* -------------------------------------------------------------------------
 * Mapa: categoria do site (slug de product_cat) => LISTA de linhas da
 * tabela Omnitek que se aplicam (cliente escolhe entre elas). Só as
 * categorias de maior volume por enquanto — o que não está aqui não
 * recebe preço automático (continua orçamento normal).
 * ---------------------------------------------------------------------- */
function dcprice_categoria_map() {
	return array(
		'chinelos'                        => array( 'sublimacao_chinelo' ),
		'aluminio-canetas-brindes'        => array( 'laser_canetas_lapiseiras' ), // canetas de metal
		'canetas-brindes'                 => array( 'tampografia_caneta_plastica', 'digitaluv_caneta_plastica' ), // resto das canetas (plástico)
		'mochilas-e-malas-brindes'        => array( 'silk_mochila_mala' ),
		'blocos-e-cadernetas-brindes'     => array( 'silk_bloco_agenda' ),
		'copos'                           => array( 'silk_garrafa_copo_180' ),
		'chaveiros-brindes'               => array( 'laser_chaveiro' ),
		'canecas'                         => array( 'sublimacao_caneca_squeeze' ),
		'carregadores-power-bank-brindes' => array( 'digitaluv_power_bloco' ),
		'caixas-de-som-brindes'           => array( 'digitaluv_caixa_som' ),
		// --- expansão (páginas extras do mesmo catálogo Omnitek) ---
		'necessaires'         => array( 'silk_necessaire_squeeze_dobravel' ),
		'sacolas'              => array( 'silk_sacola_ecobag_tecido' ),
		'bolsa-termica'        => array( 'silk_bolsa_termica_guardachuva' ),
		'guarda-chuva-brindes' => array( 'silk_bolsa_termica_guardachuva' ),
		'fones-de-ouvido'      => array( 'digitaluv_pequenos_eletronicos' ),
		'acessorios-p-celular' => array( 'digitaluv_pequenos_eletronicos' ),
		'kit-vinho'            => array( 'digitaluv_kit_vinho_porta_doc' ),
		'agenda-2027'          => array( 'digitaluv_kit_vinho_porta_doc' ),
		// mesma técnica de "mochilas-e-malas-brindes" (bolsas/malas em geral)
		'mochilas'             => array( 'silk_mochila_mala' ),
		'malas'                => array( 'silk_mochila_mala' ),
		'mochilas-malas'       => array( 'silk_mochila_mala' ),
		'capa-para-notebook'   => array( 'silk_mochila_mala' ),
		'p-notebooks'          => array( 'silk_mochila_mala' ),
		// mesma técnica de "copos" (garrafas/squeezes/térmicas usam a mesma silk 1 cor)
		'squeezes-e-garrafas'  => array( 'silk_garrafa_copo_180' ),
		'garrafastermicas'     => array( 'silk_garrafa_copo_180' ),
		'termicos'             => array( 'silk_garrafa_copo_180' ),
		'termico'              => array( 'silk_garrafa_copo_180' ),
		'inox-copos'           => array( 'silk_garrafa_copo_180' ),
		'vidro-2'              => array( 'silk_garrafa_copo_180' ),
		'plastico'             => array( 'silk_garrafa_copo_180' ),
		'inox'                 => array( 'silk_garrafa_copo_180' ),
	);
}

/* -------------------------------------------------------------------------
 * Todas as opções de gravação Omnitek disponíveis pra um produto, olhando
 * as categorias dele (a mais específica primeiro — ex: "Alumínio" antes de
 * "Canetas" genérico). Cada opção: { key, label, row }.
 * ---------------------------------------------------------------------- */
function dcprice_omnitek_rows_for_product( $product_id ) {
	// Override manual (plugin "DC · Origem e Técnica de Gravação"): se o
	// admin marcou técnica(s) direto no produto, usa isso e ignora a
	// categoria por completo — resolve casos como categorias com produtos
	// de materiais diferentes (ex: Kit Viagem em cortiça x em couro).
	$manual = get_post_meta( $product_id, '_dc_technique_override', true );
	if ( is_array( $manual ) && ! empty( $manual ) ) {
		$tabela_generica = dcprice_tecnica_generica_tabela();
		$options = array();
		foreach ( $manual as $tecnica_key ) {
			if ( ! isset( $tabela_generica[ $tecnica_key ] ) ) {
				continue;
			}
			$tiers = $tabela_generica[ $tecnica_key ]['tiers'];
			$options[] = array(
				'key'   => 'manual:' . $tecnica_key,
				'label' => $tabela_generica[ $tecnica_key ]['label'],
				'row'   => array(
					'tiers'  => $tiers,
					'minimo' => $tiers[0]['price'],
				),
			);
		}
		if ( ! empty( $options ) ) {
			return $options;
		}
	}

	$map   = dcprice_categoria_map();
	$terms = get_the_terms( $product_id, 'product_cat' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return array();
	}
	$slugs = wp_list_pluck( $terms, 'slug' );

	// Sobe na árvore: produto só marcado numa subcategoria (ex.: "Metal
	// Esfero" dentro de "Canetas") também herda o mapeamento da categoria-mãe.
	foreach ( $terms as $term ) {
		foreach ( get_ancestors( $term->term_id, 'product_cat' ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'product_cat' );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$slugs[] = $ancestor->slug;
			}
		}
	}
	$slugs  = array_unique( $slugs );
	$tabela = dcprice_omnitek_tabela();

	foreach ( $map as $slug => $tabela_keys ) {
		if ( ! in_array( $slug, $slugs, true ) ) {
			continue;
		}
		$options = array();
		foreach ( (array) $tabela_keys as $key ) {
			if ( isset( $tabela[ $key ] ) ) {
				$options[] = array(
					'key'   => $key,
					'label' => $tabela[ $key ]['label'],
					'row'   => $tabela[ $key ],
				);
			}
		}
		if ( ! empty( $options ) ) {
			return $options; // primeira categoria (mais específica) que bater, já retorna
		}
	}

	// Nada na tabela curada da Omnitek pra essa categoria: cai pra técnica
	// genérica (dado real da Spot, sem ser específico do produto), pra não
	// deixar a categoria inteira sem preço automático.
	$generic = dcprice_tecnica_generica_for_product_by_slugs( $slugs );
	if ( null !== $generic ) {
		return array( $generic );
	}
	return array();
}

/* -------------------------------------------------------------------------
 * Fallback: técnica genérica por categoria (sem tabela Omnitek própria),
 * usando a curva REAL de preço por técnica da Spot (customizationTables,
 * opção 1 cor mais comum de cada técnica) — só pra não deixar a categoria
 * inteira sem preço automático. Menos preciso que a Omnitek/Spot real do
 * produto, mas evita "sem preço" pro resto do catálogo.
 * ---------------------------------------------------------------------- */
function dcprice_tecnica_generica_tabela() {
	return array(
		'laser'        => array(
			'label' => 'Laser',
			'tiers' => array(
				array( 'min_qty' => 1, 'price' => 63.0 ), array( 'min_qty' => 2, 'price' => 31.5 ),
				array( 'min_qty' => 3, 'price' => 20.97 ), array( 'min_qty' => 4, 'price' => 15.75 ),
				array( 'min_qty' => 5, 'price' => 11.87 ), array( 'min_qty' => 10, 'price' => 6.02 ),
				array( 'min_qty' => 25, 'price' => 2.99 ), array( 'min_qty' => 50, 'price' => 1.28 ),
				array( 'min_qty' => 100, 'price' => 0.86 ), array( 'min_qty' => 250, 'price' => 0.57 ),
				array( 'min_qty' => 500, 'price' => 0.51 ), array( 'min_qty' => 1000, 'price' => 0.46 ),
				array( 'min_qty' => 2500, 'price' => 0.45 ), array( 'min_qty' => 5000, 'price' => 0.43 ),
			),
		),
		'tampografia'  => array(
			'label' => 'Tampografia',
			'tiers' => array(
				array( 'min_qty' => 1, 'price' => 95.0 ), array( 'min_qty' => 2, 'price' => 52.5 ),
				array( 'min_qty' => 3, 'price' => 36.75 ), array( 'min_qty' => 4, 'price' => 26.25 ),
				array( 'min_qty' => 5, 'price' => 18.9 ), array( 'min_qty' => 10, 'price' => 9.45 ),
				array( 'min_qty' => 25, 'price' => 4.2 ), array( 'min_qty' => 50, 'price' => 2.1 ),
				array( 'min_qty' => 100, 'price' => 1.05 ), array( 'min_qty' => 250, 'price' => 0.53 ),
				array( 'min_qty' => 500, 'price' => 0.29 ), array( 'min_qty' => 1000, 'price' => 0.19 ),
				array( 'min_qty' => 2500, 'price' => 0.15 ), array( 'min_qty' => 5000, 'price' => 0.14 ),
			),
		),
		'silk'         => array(
			'label' => 'Silk',
			'tiers' => array(
				array( 'min_qty' => 1, 'price' => 105.0 ), array( 'min_qty' => 2, 'price' => 52.5 ),
				array( 'min_qty' => 3, 'price' => 35.18 ), array( 'min_qty' => 4, 'price' => 26.25 ),
				array( 'min_qty' => 5, 'price' => 21.0 ), array( 'min_qty' => 10, 'price' => 10.5 ),
				array( 'min_qty' => 25, 'price' => 4.62 ), array( 'min_qty' => 50, 'price' => 2.42 ),
				array( 'min_qty' => 100, 'price' => 1.21 ), array( 'min_qty' => 250, 'price' => 0.79 ),
				array( 'min_qty' => 500, 'price' => 0.62 ), array( 'min_qty' => 1000, 'price' => 0.58 ),
				array( 'min_qty' => 2500, 'price' => 0.49 ), array( 'min_qty' => 5000, 'price' => 0.48 ),
			),
		),
		'silk_textil'  => array(
			'label' => 'Silk',
			'tiers' => array(
				array( 'min_qty' => 1, 'price' => 100.0 ), array( 'min_qty' => 2, 'price' => 50.18 ),
				array( 'min_qty' => 3, 'price' => 33.45 ), array( 'min_qty' => 4, 'price' => 25.09 ),
				array( 'min_qty' => 5, 'price' => 20.07 ), array( 'min_qty' => 10, 'price' => 11.15 ),
				array( 'min_qty' => 25, 'price' => 6.2 ), array( 'min_qty' => 50, 'price' => 3.15 ),
				array( 'min_qty' => 100, 'price' => 1.68 ), array( 'min_qty' => 250, 'price' => 0.89 ),
				array( 'min_qty' => 500, 'price' => 0.65 ), array( 'min_qty' => 1000, 'price' => 0.48 ),
				array( 'min_qty' => 2500, 'price' => 0.42 ), array( 'min_qty' => 5000, 'price' => 0.4 ),
			),
		),
		'transfer'     => array(
			'label' => 'DTF',
			'tiers' => array(
				array( 'min_qty' => 1, 'price' => 95.0 ), array( 'min_qty' => 2, 'price' => 47.25 ),
				array( 'min_qty' => 3, 'price' => 31.5 ), array( 'min_qty' => 4, 'price' => 23.63 ),
				array( 'min_qty' => 5, 'price' => 18.9 ), array( 'min_qty' => 10, 'price' => 9.45 ),
				array( 'min_qty' => 25, 'price' => 4.41 ), array( 'min_qty' => 50, 'price' => 3.05 ),
				array( 'min_qty' => 100, 'price' => 1.7 ), array( 'min_qty' => 250, 'price' => 0.88 ),
				array( 'min_qty' => 500, 'price' => 0.58 ), array( 'min_qty' => 1000, 'price' => 0.44 ),
				array( 'min_qty' => 2500, 'price' => 0.36 ), array( 'min_qty' => 5000, 'price' => 0.32 ),
			),
		),
		'hot_stamping' => array(
			'label' => 'Hot stamping',
			'tiers' => array(
				array( 'min_qty' => 1, 'price' => 227.0 ), array( 'min_qty' => 2, 'price' => 113.4 ),
				array( 'min_qty' => 3, 'price' => 75.6 ), array( 'min_qty' => 4, 'price' => 56.7 ),
				array( 'min_qty' => 5, 'price' => 45.36 ), array( 'min_qty' => 10, 'price' => 22.68 ),
				array( 'min_qty' => 25, 'price' => 9.07 ), array( 'min_qty' => 50, 'price' => 4.54 ),
				array( 'min_qty' => 100, 'price' => 2.52 ), array( 'min_qty' => 250, 'price' => 2.27 ),
				array( 'min_qty' => 500, 'price' => 1.58 ), array( 'min_qty' => 1000, 'price' => 1.26 ),
				array( 'min_qty' => 2500, 'price' => 1.2 ), array( 'min_qty' => 5000, 'price' => 1.13 ),
			),
		),
	);
}

/* -------------------------------------------------------------------------
 * Categoria (site) => técnica genérica. Baseado no material típico de cada
 * categoria (metal → Laser, têxtil → Silk, madeira → Laser, etc.). Chute
 * educado, sem dado real do fornecedor pra essa categoria — REVISAR se
 * algum preço sair estranho.
 * ---------------------------------------------------------------------- */
function dcprice_tecnica_generica_map() {
	return array(
		'camisetas'                      => 'silk_textil',
		'masculino'                      => 'silk_textil',
		'feminino'                       => 'silk_textil',
		'unissex'                        => 'silk_textil',
		'linha-ecologica'                => 'silk',
		'kit-churrasco'                  => 'laser',
		'artigos-para-vinho'             => 'laser',
		'kit-queijo'                     => 'laser',
		'pen-drives'                     => 'laser',
		'ferramentas-brindes'            => 'laser',
		'bar-e-bebidas-brindes'          => 'laser',
		'cozinha-brindes'                => 'laser',
		'metalicas'                      => 'laser',
		'eletronico'                     => 'tampografia',
		'tabuas'                         => 'laser',
		'lanternas-e-luminarias-brindes' => 'laser',
		'canivetes'                      => 'laser',
		'acessorios-para-carros'         => 'laser',
		'dia-dos-professores'            => 'tampografia',
		'kit-escritorio'                 => 'tampografia',
		'cadernos'                       => 'silk',
		'estojos'                        => 'silk',
		'conjuntos-executivos-brindes'   => 'tampografia',
		'brindes'                        => 'tampografia',
		'diversos'                       => 'tampografia',
		'escritorio-brindes'             => 'tampografia',
		'kit-viagem'                     => 'laser',
		'frascos'                        => 'silk',
		'lancheiras'                     => 'silk_textil',
		'anti-estresse'                  => 'tampografia',
		'pastas'                         => 'silk',
		'canudos'                        => 'laser',
		'embalagens'                     => 'tampografia',
		'oculos-de-sol'                  => 'tampografia',
		'chapeus'                        => 'silk_textil',
		'cuidados-pessoais-brindes'      => 'tampografia',
		'capas-de-chuva'                 => 'silk_textil',
		'artigos-para-cafe'              => 'tampografia',
		'luvas'                          => 'silk_textil',
		'linha-fitness'                  => 'tampografia',
		'esportes'                       => 'tampografia',
		'porta-cartoes'                  => 'laser',
		'etiquetas'                      => 'tampografia',
		'lapis-e-lapiseiras'             => 'laser',
		'jogos-de-cartas'                => 'tampografia',
		'jogos-de-praia'                 => 'laser',
		'cadeiras'                       => 'silk_textil',
		'manicure'                       => 'laser',
		'kit-coqueteleira'               => 'laser',
		'criancas'                       => 'tampografia',
		'calendario'                     => 'tampografia',
		'xicaras'                        => 'silk',
		'chaleira'                       => 'laser',
		'toalhas'                        => 'silk_textil',
	);
}

function dcprice_tecnica_generica_for_product_by_slugs( $slugs ) {
	$map    = dcprice_tecnica_generica_map();
	$tabela = dcprice_tecnica_generica_tabela();
	foreach ( $map as $slug => $tecnica_key ) {
		if ( in_array( $slug, $slugs, true ) && isset( $tabela[ $tecnica_key ] ) ) {
			$tiers = $tabela[ $tecnica_key ]['tiers'];
			return array(
				'key'   => 'generico:' . $tecnica_key,
				'label' => $tabela[ $tecnica_key ]['label'] . ' (estimado)',
				'row'   => array(
					'tiers'  => $tiers,
					'minimo' => $tiers[0]['price'], // só pra ordenação no usort de dcprice_omnitek_row_for_product
				),
			);
		}
	}
	return null;
}

/* -------------------------------------------------------------------------
 * Compat: uma única linha (a mais barata) pra checagens rápidas de
 * elegibilidade / gravação em lote.
 * ---------------------------------------------------------------------- */
function dcprice_omnitek_row_for_product( $product_id ) {
	$options = dcprice_omnitek_rows_for_product( $product_id );
	if ( empty( $options ) ) {
		return null;
	}
	usort(
		$options,
		function ( $a, $b ) {
			return $a['row']['minimo'] <=> $b['row']['minimo'];
		}
	);
	return $options[0]['row'];
}

/* -------------------------------------------------------------------------
 * Percentual de gravação sobre o custo, por técnica (substitui a lógica
 * antiga de "mínimo do lote ÷ quantidade" / "preço fixo acima do limite").
 * Casa por SUBSTRING na chave da técnica (ex.: 'tampografia_caneta_plastica',
 * 'manual:tampografia', 'generico:tampografia' — todas contêm "tampografia").
 * ---------------------------------------------------------------------- */
function dcprice_omnitek_gravacao_pct( $tecnica_key ) {
	$tecnica_key = (string) $tecnica_key;
	if ( false !== strpos( $tecnica_key, 'tampografia' ) ) {
		return 0.25; // Tampografia: 25% do custo
	}
	if ( false !== strpos( $tecnica_key, 'silk' ) ) {
		return 0.22; // Silk (silk-screen / silk têxtil): 22% do custo
	}
	if ( false !== strpos( $tecnica_key, 'laser' ) ) {
		return 0.20; // Laser: 20% do custo (igual ao default, mas explícito)
	}
	return 0.20; // Default: Digital UV, Sublimação, Hot Stamping, Transfer, etc.
}

/* -------------------------------------------------------------------------
 * Gravação XBZ/Asia/Spot: percentual flat sobre o custo, conforme a técnica.
 * (Antiga lógica de "mínimo do lote ÷ qty" / "preço fixo acima do limite"
 * foi REMOVIDA daqui — os campos limite/minimo/preco_unit continuam
 * existindo em dcprice_omnitek_tabela() pra outros usos, só não entram
 * mais nessa conta.)
 * ---------------------------------------------------------------------- */
function dcprice_omnitek_gravacao_unit( $tecnica_key, $custo ) {
	return (float) $custo * dcprice_omnitek_gravacao_pct( $tecnica_key );
}

/* -------------------------------------------------------------------------
 * Credenciais reaproveitadas dos plugins de sincronização (não duplicamos).
 * ---------------------------------------------------------------------- */
function dcprice_xbz_credentials() {
	$s = get_option( 'dcxbz_settings', array() );
	return array(
		'cnpj'  => is_array( $s ) && ! empty( $s['cnpj'] ) ? $s['cnpj'] : '',
		'token' => is_array( $s ) && ! empty( $s['token'] ) ? $s['token'] : '',
	);
}

function dcprice_asia_credentials() {
	$s = get_option( 'dcasia_settings', array() );
	return array(
		'api_key'    => is_array( $s ) && ! empty( $s['api_key'] ) ? $s['api_key'] : '',
		'secret_key' => is_array( $s ) && ! empty( $s['secret_key'] ) ? $s['secret_key'] : '',
	);
}

/* -------------------------------------------------------------------------
 * Cache de custo XBZ: por SKU (CodigoAmigavel), o PrecoVenda. Só grava os
 * SKUs que já existem no site (casa por _sku do WooCommerce).
 * ---------------------------------------------------------------------- */
function dcprice_site_skus() {
	global $wpdb;
	$rows = $wpdb->get_col(
		"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value != ''"
	);
	return array_values( array_unique( array_filter( array_map( 'strval', (array) $rows ) ) ) );
}

function dcprice_refresh_xbz_cache() {
	$cred = dcprice_xbz_credentials();
	if ( empty( $cred['cnpj'] ) || empty( $cred['token'] ) ) {
		return new WP_Error( 'dcprice_xbz_no_cred', 'Credenciais da XBZ não encontradas (plugin "DC · Sincronização de Estoque XBZ" precisa estar ativo e configurado).' );
	}
	$url = add_query_arg(
		array(
			'cnpj'  => preg_replace( '/\D/', '', $cred['cnpj'] ),
			'token' => $cred['token'],
		),
		'https://api.minhaxbz.com.br:5001/api/clientes/GetListaDeProdutos'
	);
	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 180,
			'headers' => array( 'Accept' => '*/*' ), // com Accept: application/json a API dobra a codificação JSON
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return new WP_Error( 'dcprice_xbz_http_' . $code, 'XBZ respondeu HTTP ' . $code );
	}
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	// quirk conhecido: às vezes vem uma string JSON dentro da string JSON.
	if ( is_string( $data ) ) {
		$data = json_decode( $data, true );
	}
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'dcprice_xbz_json', 'Não consegui decodificar a resposta da XBZ.' );
	}

	$site_skus = array_flip( dcprice_site_skus() );
	$cost_by_sku = array();
	foreach ( $data as $row ) {
		$sku = isset( $row['CodigoAmigavel'] ) ? (string) $row['CodigoAmigavel'] : '';
		if ( '' === $sku || ! isset( $site_skus[ $sku ] ) ) {
			continue;
		}
		if ( ! isset( $row['PrecoVenda'] ) ) {
			continue;
		}
		$preco = (float) $row['PrecoVenda'];
		// mantém o menor preço entre as variações do mesmo SKU-família, se repetir
		if ( ! isset( $cost_by_sku[ $sku ] ) || $preco < $cost_by_sku[ $sku ] ) {
			$cost_by_sku[ $sku ] = $preco;
		}
	}

	$dir = dcprice_cache_dir();
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	file_put_contents( $dir . 'cost-xbz.json', wp_json_encode( $cost_by_sku ) );

	return array(
		'refs_no_site'   => count( $site_skus ),
		'refs_with_cost' => count( $cost_by_sku ),
	);
}

/* -------------------------------------------------------------------------
 * Cache de custo Asia Import: por referência (SKU), o preço. API pagina
 * (max 100 por página) — percorre até acabar.
 * ---------------------------------------------------------------------- */
function dcprice_refresh_asia_cache() {
	$cred = dcprice_asia_credentials();
	if ( empty( $cred['api_key'] ) || empty( $cred['secret_key'] ) ) {
		return new WP_Error( 'dcprice_asia_no_cred', 'Credenciais da Asia Import não encontradas (plugin "DC · Sincronização de Estoque XBZ" — aba Asia — precisa estar configurado).' );
	}

	$site_skus   = array_flip( dcprice_site_skus() );
	$cost_by_ref = array();
	$pagina      = 1;
	$total_paginas = 1;

	do {
		$response = wp_remote_post(
			'https://api.asiaimport.com.br/',
			array(
				'timeout' => 60,
				'body'    => array(
					'api_key'    => $cred['api_key'],
					'secret_key' => $cred['secret_key'],
					'funcao'     => 'listarProdutos2',
					'pagina'     => $pagina,
					'por_pagina' => 100,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['produtos'] ) ) {
			break;
		}
		$total_paginas = isset( $body['total_paginas'] ) ? (int) $body['total_paginas'] : 1;

		foreach ( $body['produtos'] as $produto ) {
			$ref = isset( $produto['referencia'] ) ? (string) $produto['referencia'] : '';
			// casa pela referência do produto pai ou de cada variação (com/sem "P" no final, ver [[asiaimport-api]])
			$candidatos = array( $ref, $ref . 'P', rtrim( $ref, 'P' ) );
			foreach ( (array) $produto['variacoes'] as $variacao ) {
				if ( isset( $variacao['referencia'] ) ) {
					$candidatos[] = (string) $variacao['referencia'];
				}
				$preco = isset( $variacao['preco'] ) ? (float) $variacao['preco'] : ( isset( $produto['preco'] ) ? (float) $produto['preco'] : 0 );
				foreach ( $candidatos as $cand ) {
					if ( '' === $cand || ! isset( $site_skus[ $cand ] ) ) {
						continue;
					}
					if ( $preco > 0 && ( ! isset( $cost_by_ref[ $cand ] ) || $preco < $cost_by_ref[ $cand ] ) ) {
						$cost_by_ref[ $cand ] = $preco;
					}
				}
			}
		}
		$pagina++;
	} while ( $pagina <= $total_paginas );

	$dir = dcprice_cache_dir();
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	file_put_contents( $dir . 'cost-asia.json', wp_json_encode( $cost_by_ref ) );

	return array(
		'refs_no_site'   => count( $site_skus ),
		'refs_with_cost' => count( $cost_by_ref ),
	);
}

/* -------------------------------------------------------------------------
 * Leitura do cache XBZ/Asia (arquivo -> memória, request-scoped).
 * ---------------------------------------------------------------------- */
function dcprice_load_xbz_asia_cache() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$dir = dcprice_cache_dir();
	$xbz = array();
	$asia = array();
	if ( file_exists( $dir . 'cost-xbz.json' ) ) {
		$xbz = json_decode( file_get_contents( $dir . 'cost-xbz.json' ), true );
		$xbz = is_array( $xbz ) ? $xbz : array();
	}
	if ( file_exists( $dir . 'cost-asia.json' ) ) {
		$asia = json_decode( file_get_contents( $dir . 'cost-asia.json' ), true );
		$asia = is_array( $asia ) ? $asia : array();
	}
	$cache = array( 'xbz' => $xbz, 'asia' => $asia );
	return $cache;
}

/* -------------------------------------------------------------------------
 * Detecta fornecedor + custo de um produto (XBZ ou Asia) e calcula o preço
 * usando a tabela Omnitek da categoria dele. Devolve WP_Error se o produto
 * não tem fornecedor/categoria mapeados (fica só orçamento, sem mexer).
 * ---------------------------------------------------------------------- */
function dcprice_calculate_xbz_asia( $product_id, $qty, $technique_key = null ) {
	$source = get_post_meta( $product_id, '_dcxbz_source', true );
	$is_xbz = ( 'xbz' === $source );
	$is_asia = ! $is_xbz && ( 'asia' === get_post_meta( $product_id, '_dcasia_source', true ) );
	if ( ! $is_xbz && ! $is_asia ) {
		return new WP_Error( 'dcprice_no_supplier', 'Produto não é XBZ nem Asia Import.' );
	}

	$row = null;
	$tecnica_key = null;
	if ( null !== $technique_key ) {
		foreach ( dcprice_omnitek_rows_for_product( $product_id ) as $opt ) {
			if ( $opt['key'] === $technique_key ) {
				$row = $opt['row'];
				$tecnica_key = $opt['key'];
				break;
			}
		}
	}
	if ( null === $row ) {
		// mais barata: menor % de gravação (desempate pelo campo 'minimo'),
		// se não escolheu técnica.
		$options = dcprice_omnitek_rows_for_product( $product_id );
		if ( ! empty( $options ) ) {
			usort(
				$options,
				function ( $a, $b ) {
					$pa = dcprice_omnitek_gravacao_pct( $a['key'] );
					$pb = dcprice_omnitek_gravacao_pct( $b['key'] );
					return $pa <=> $pb ?: $a['row']['minimo'] <=> $b['row']['minimo'];
				}
			);
			$row = $options[0]['row'];
			$tecnica_key = $options[0]['key'];
		}
	}
	if ( null === $row ) {
		return new WP_Error( 'dcprice_no_categoria', 'Categoria do produto ainda não está no mapeamento de gravação.' );
	}

	$cache = dcprice_load_xbz_asia_cache();
	$product = wc_get_product( $product_id );
	$sku = $product ? $product->get_sku() : '';
	if ( '' === $sku ) {
		return new WP_Error( 'dcprice_no_sku', 'Produto sem SKU.' );
	}
	$cost_unit = null;
	if ( $is_xbz && isset( $cache['xbz'][ $sku ] ) ) {
		$cost_unit = (float) $cache['xbz'][ $sku ];
	} elseif ( $is_asia && isset( $cache['asia'][ $sku ] ) ) {
		$cost_unit = (float) $cache['asia'][ $sku ];
	}
	if ( null === $cost_unit ) {
		return new WP_Error( 'dcprice_no_cost', 'Sem custo cadastrado no cache pra esse SKU.' );
	}

	$gravacao_unit = dcprice_omnitek_gravacao_unit( $tecnica_key, $cost_unit );

	$settings     = dcprice_settings();
	$margem_mult  = 1 + ( (float) $settings['margem_pct'] / 100 );
	$imposto_mult = 1 + ( (float) $settings['imposto_pct'] / 100 );

	$unit_price_raw = ( $cost_unit + $gravacao_unit ) * $margem_mult * $imposto_mult;
	$unit_price     = round( $unit_price_raw, 2 );
	$total          = round( $unit_price * max( 1, (int) $qty ), 2 );

	return array(
		'cost_unit'     => round( $cost_unit, 4 ),
		'gravacao_unit' => round( $gravacao_unit, 4 ),
		'unit_price'    => $unit_price,
		'total'         => $total,
		'qty'           => (int) $qty,
	);
}

/* -------------------------------------------------------------------------
 * Produtos Spot: gravação pela tabela Omnitek (% sobre o custo) — o custo
 * vem do cache da Spot. Se a gravação REAL da Spot (× acréscimo) pra mesma
 * técnica nessa quantidade for MENOR, vale a da Spot; o valor dos
 * percentuais é sempre o teto.
 * ---------------------------------------------------------------------- */
function dcprice_calculate_spot_omnitek( $product_id, $qty, $technique_key = null ) {
	$ref = dcprice_ref_for_product( $product_id );
	if ( empty( $ref ) ) {
		return new WP_Error( 'dcprice_no_ref', 'Produto Spot sem referência.' );
	}
	$cache = dcprice_load_cache();
	if ( empty( $cache['cost'][ $ref ] ) ) {
		return new WP_Error( 'dcprice_no_cost', 'Sem custo cadastrado no cache pra essa referência.' );
	}
	$cost_unit = dcprice_tier_price( $cache['cost'][ $ref ], $qty );
	if ( null === $cost_unit ) {
		return new WP_Error( 'dcprice_no_cost_tier', 'Sem faixa de custo compatível com essa quantidade.' );
	}

	$options = dcprice_omnitek_rows_for_product( $product_id );
	if ( empty( $options ) ) {
		return new WP_Error( 'dcprice_no_categoria', 'Categoria do produto ainda não está no mapeamento de gravação.' );
	}
	$chosen = null;
	if ( null !== $technique_key ) {
		foreach ( $options as $opt ) {
			if ( $opt['key'] === $technique_key ) {
				$chosen = $opt;
				break;
			}
		}
	}
	if ( null === $chosen ) {
		usort(
			$options,
			function ( $a, $b ) {
				$pa = dcprice_omnitek_gravacao_pct( $a['key'] );
				$pb = dcprice_omnitek_gravacao_pct( $b['key'] );
				return $pa <=> $pb ?: $a['row']['minimo'] <=> $b['row']['minimo'];
			}
		);
		$chosen = $options[0];
	}

	$settings = dcprice_settings();

	// Teto: gravação pelo percentual da técnica.
	$gravacao_unit = dcprice_omnitek_gravacao_unit( $chosen['key'], $cost_unit );

	// Preço real da Spot pra mesma técnica nessa quantidade (se existir e for
	// menor que o teto, vale ele; nunca passa do valor dos percentuais).
	if ( ! empty( $cache['custom'][ $ref ] ) ) {
		$want  = mb_strtolower( $chosen['label'] );
		$cands = array();
		foreach ( $cache['custom'][ $ref ] as $so ) {
			$sl = mb_strtolower( $so['label'] );
			if ( false !== strpos( $sl, $want ) || false !== strpos( $want, $sl ) ) {
				$cands[] = $so;
			}
		}
		if ( ! empty( $cands ) ) {
			$best = dcprice_cheapest_technique_at_qty( $cands, $qty );
			if ( $best ) {
				$spot_real = (float) dcprice_tier_price_from_tiers( $best['tiers'], $qty );
				$spot_grav = $spot_real * ( 1 + ( (float) $settings['gravacao_pct'] / 100 ) );
				if ( $spot_grav > 0 && $spot_grav < $gravacao_unit ) {
					$gravacao_unit = $spot_grav;
				}
			}
		}
	}

	$margem_mult  = 1 + ( (float) $settings['margem_pct'] / 100 );
	$imposto_mult = 1 + ( (float) $settings['imposto_pct'] / 100 );

	$unit_price = round( ( $cost_unit + $gravacao_unit ) * $margem_mult * $imposto_mult, 2 );
	$total      = round( $unit_price * max( 1, (int) $qty ), 2 );

	return array(
		'cost_unit'       => round( $cost_unit, 4 ),
		'gravacao_unit'   => round( $gravacao_unit, 4 ),
		'technique_label' => $chosen['label'],
		'unit_price'      => $unit_price,
		'total'           => $total,
		'qty'             => (int) $qty,
	);
}

function dcprice_product_is_priceable( $product_id ) {
	$is_spot = 'spot' === get_post_meta( $product_id, '_dcspot_source', true );
	$is_xa   = 'xbz' === get_post_meta( $product_id, '_dcxbz_source', true ) || 'asia' === get_post_meta( $product_id, '_dcasia_source', true );
	if ( $is_spot || $is_xa ) {
		return null !== dcprice_omnitek_row_for_product( $product_id );
	}
	return false;
}

/* -------------------------------------------------------------------------
 * Cálculo genérico: escolhe Spot (Omnitek + teto pelos %) ou XBZ/Asia
 * (tabela Omnitek) conforme a origem do produto.
 * ---------------------------------------------------------------------- */
function dcprice_calculate_for_product( $product_id, $qty, $technique_key = null ) {
	if ( 'spot' === get_post_meta( $product_id, '_dcspot_source', true ) ) {
		return dcprice_calculate_spot_omnitek( $product_id, $qty, $technique_key );
	}
	return dcprice_calculate_xbz_asia( $product_id, $qty, $technique_key );
}

/* -------------------------------------------------------------------------
 * Ícone (attachment já salvo na Media Library) e descrição curta por
 * técnica, pra montar o seletor visual na página do produto.
 * ---------------------------------------------------------------------- */
function dcprice_technique_icon_url( $label ) {
	$label = mb_strtolower( $label );
	$map = array(
		'digital uv'   => 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/09/icone-gravacao-uv-digital.svg',
		'uv'           => 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/09/icone-gravacao-uv-digital.svg',
		'tampografia'  => 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/09/icone-gravacao-tampografia.svg',
		'silk'         => 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/09/icone-gravacao-silk-screen.svg',
		'sublima'      => 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/09/icone-gravacao-sublimacao.svg',
		'transfer digital' => 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/09/icone-gravacao-impressao-digital.svg',
		'dtf'          => 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/09/icone-gravacao-transfer.svg',
		'transfer'     => 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/09/icone-gravacao-transfer.svg',
		'hot stamping' => 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/09/icone-gravacao-hot-stamping.svg',
		'etiqueta'     => 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/09/icone-gravacao-etiquetas.svg',
		'laser'        => 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/09/icone-gravacao-laser.svg',
	);
	foreach ( $map as $needle => $url ) {
		if ( false !== strpos( $label, $needle ) ) {
			return $url;
		}
	}
	return '';
}
function dcprice_technique_description( $label ) {
	$label = mb_strtolower( $label );
	$textos = array(
		'laser'        => 'A gravação a laser é o método mais preciso e duradouro de impressão. Além de utilizada no metal, pode ser também aplicada em madeira.',
		'tampografia'  => 'A tampografia transfere a arte por meio de um carimbo de silicone — boa opção pra superfícies curvas ou irregulares, em 1 cor.',
		'digital uv'   => 'A impressão Digital UV permite arte colorida (até 4 cores), com boa definição mesmo em pequenos detalhes.',
		'silk'         => 'O silk screen aplica a tinta através de uma tela, ideal pra áreas maiores em 1 cor por vez.',
		'sublima'      => 'A sublimação transfere a tinta direto pra estrutura do material com calor — cores vivas, indicada pra canecas e superfícies próprias.',
		'dtf'          => 'O DTF aplica a arte por decalque com calor (Direct to Film), permitindo detalhes e cores variadas.',
		'hot stamping' => 'O hot stamping aplica a arte em relevo ou com brilho metálico, usando calor e pressão. Muito usado em couro e materiais sintéticos.',
		'transfer'     => 'O transfer aplica a arte por decalque com calor, permitindo detalhes e cores variadas.',
	);
	foreach ( $textos as $needle => $texto ) {
		if ( false !== strpos( $label, $needle ) ) {
			return $texto;
		}
	}
	return '';
}

/* -------------------------------------------------------------------------
 * Faixas de quantidade pra tabela de preço: a partir do mínimo do produto,
 * multiplicadores ×1, ×2, ×5, ×7, ×10, ×20 (ex.: mínimo 50 → 50/100/250/
 * 350/500/1000 — o mesmo padrão usado no exemplo aprovado).
 * ---------------------------------------------------------------------- */
function dcprice_qty_tiers_for_product( $product_id ) {
	$min = 1;
	if ( class_exists( 'DC_Min_Order_Qty' ) ) {
		$min = DC_Min_Order_Qty::min_qty_for_product( $product_id );
	}
	$tiers = array();
	foreach ( array_keys( dcprice_discount_curve() ) as $mult ) {
		$tiers[] = $min * $mult;
	}
	return array_values( array_unique( $tiers ) );
}

/* -------------------------------------------------------------------------
 * Curva de desconto por múltiplo da quantidade mínima — calculada a partir
 * do exemplo aprovado (50→R$5,80 / 100→R$4,49 / 250→R$4,19 / 350→R$4,05 /
 * 500→R$3,85 / 1000→R$3,68, ou seja múltiplos ×1/2/5/7/10/20 do mínimo).
 * Aplicada sobre o preço unitário REAL na quantidade mínima — não usa mais
 * o preço real de cada faixa (que às vezes repetia de uma faixa pra outra).
 * ---------------------------------------------------------------------- */
function dcprice_discount_curve() {
	return array(
		1  => 0.0,
		2  => 0.1000,
		5  => 0.1450,
		7  => 0.1707,
		10 => 0.1955,
		20 => 0.2197,
	);
}

/* -------------------------------------------------------------------------
 * Desconto pra qualquer quantidade (não só os 6 múltiplos exatos da tabela):
 * usa o degrau da curva cujo múltiplo é o maior <= o múltiplo pedido — a
 * mesma lógica de "faixa de quantidade" já usada em todo o resto do preço.
 * ---------------------------------------------------------------------- */
function dcprice_discount_for_multiplier( $mult ) {
	$curve = dcprice_discount_curve();
	$best  = 0.0;
	$best_mult = 0;
	foreach ( $curve as $tier_mult => $discount ) {
		if ( $tier_mult <= $mult && $tier_mult >= $best_mult ) {
			$best_mult = $tier_mult;
			$best      = $discount;
		}
	}
	return $best;
}

/* -------------------------------------------------------------------------
 * Preço final de um produto numa quantidade qualquer: preço real na
 * quantidade mínima × curva de desconto pro múltiplo daquela quantidade.
 * Usado tanto na tabela da página quanto no recálculo do carrinho, pra
 * nunca cobrar diferente do que foi mostrado.
 * ---------------------------------------------------------------------- */
function dcprice_calculate_with_curve( $product_id, $qty, $technique_key = null ) {
	$min_qty = 1;
	if ( class_exists( 'DC_Min_Order_Qty' ) ) {
		$min_qty = DC_Min_Order_Qty::min_qty_for_product( $product_id );
	}
	$base = dcprice_calculate_for_product( $product_id, $min_qty, $technique_key );
	if ( is_wp_error( $base ) ) {
		return $base;
	}
	$mult     = $min_qty > 0 ? $qty / $min_qty : 1;
	$discount = dcprice_discount_for_multiplier( $mult );
	$unit     = round( $base['unit_price'] * ( 1 - $discount ), 2 );
	return array(
		'unit_price' => $unit,
		'total'      => round( $unit * max( 1, (int) $qty ), 2 ),
		'qty'        => (int) $qty,
	);
}

/* -------------------------------------------------------------------------
 * Opções de técnica de gravação disponíveis pra um produto, já com
 * ícone/descrição, para todos os fornecedores (Spot, XBZ e Asia usam a
 * tabela Omnitek da categoria, ou a marcação manual do produto).
 * ---------------------------------------------------------------------- */
function dcprice_technique_options_for_product( $product_id ) {
	$options = array();
	foreach ( dcprice_omnitek_rows_for_product( $product_id ) as $opt ) {
		$options[] = array(
			'key'   => $opt['key'],
			'label' => $opt['label'],
		);
	}

	// Uma opção por LABEL só (evita repetir "Laser" duas vezes).
	$seen   = array();
	$unique = array();
	foreach ( $options as $opt ) {
		if ( isset( $seen[ $opt['label'] ] ) ) {
			continue;
		}
		$seen[ $opt['label'] ] = true;
		$opt['icon']           = dcprice_technique_icon_url( $opt['label'] );
		$opt['description']    = dcprice_technique_description( $opt['label'] );
		$unique[]              = $opt;
	}
	return $unique;
}

/* -------------------------------------------------------------------------
 * Backfill: produtos sem NENHUMA marcação de origem (_dcxbz_source /
 * _dcasia_source / _dcspot_source) — o sync nunca rodou neles — mas cujo
 * SKU já bate com o cache de custo da XBZ, da Asia OU da Spot. Marca a
 * origem pelo SKU, sem precisar esperar o próximo sync completo dessas
 * outras plugins.
 * ---------------------------------------------------------------------- */
function dcprice_backfill_supplier_source() {
	global $wpdb;
	$sem_origem = $wpdb->get_col(
		"SELECT p.ID FROM {$wpdb->posts} p
		WHERE p.post_type = 'product' AND p.post_status = 'publish'
		AND p.ID NOT IN (
			SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key IN ('_dcxbz_source','_dcasia_source','_dcspot_source')
		)"
	);
	if ( empty( $sem_origem ) ) {
		return array( 'marcados_xbz' => 0, 'marcados_asia' => 0, 'marcados_spot' => 0, 'sem_sku_ou_sem_match' => 0 );
	}

	$cache = dcprice_load_xbz_asia_cache();

	// Índice SKU/WebSku -> ref da Spot: busca do FEED CRU da Spot (todo o
	// catálogo dela), não do nosso cache local — o cache local só tem os
	// refs que JÁ tinham _dcspot_ref marcado, então um produto migrado sem
	// essa marcação nunca apareceria ali (é exatamente o caso que estamos
	// tentando resolver aqui).
	$spot_sku_to_ref = array();
	$optionals       = dcprice_fetch_spot_feed( 'optionalsPrice' );
	if ( ! is_wp_error( $optionals ) ) {
		foreach ( (array) $optionals as $row ) {
			$ref = isset( $row['ProdReference'] ) ? (string) $row['ProdReference'] : '';
			if ( '' === $ref ) {
				continue;
			}
			if ( ! empty( $row['Sku'] ) ) {
				$spot_sku_to_ref[ $row['Sku'] ] = $ref;
			}
			if ( ! empty( $row['WebSku'] ) ) {
				$spot_sku_to_ref[ $row['WebSku'] ] = $ref;
			}
		}
	}

	$marcados_xbz  = 0;
	$marcados_asia = 0;
	$marcados_spot = 0;
	$sem_match     = 0;

	foreach ( $sem_origem as $post_id ) {
		$product = wc_get_product( (int) $post_id );
		$sku     = $product ? $product->get_sku() : '';
		if ( '' === $sku ) {
			$sem_match++;
			continue;
		}
		if ( isset( $cache['xbz'][ $sku ] ) ) {
			update_post_meta( $post_id, '_dcxbz_source', 'xbz' );
			$marcados_xbz++;
		} elseif ( isset( $cache['asia'][ $sku ] ) ) {
			update_post_meta( $post_id, '_dcasia_source', 'asia' );
			$marcados_asia++;
		} elseif ( isset( $spot_sku_to_ref[ $sku ] ) ) {
			update_post_meta( $post_id, '_dcspot_ref', $spot_sku_to_ref[ $sku ] );
			update_post_meta( $post_id, '_dcspot_source', 'spot' );
			$marcados_spot++;
		} else {
			$sem_match++;
		}
	}

	return array(
		'marcados_xbz'         => $marcados_xbz,
		'marcados_asia'        => $marcados_asia,
		'marcados_spot'        => $marcados_spot,
		'sem_sku_ou_sem_match' => $sem_match,
	);
}

/* -------------------------------------------------------------------------
 * Painel: seção extra em Precificação Spot pra atualizar o cache de custo
 * XBZ/Asia.
 * ---------------------------------------------------------------------- */
add_action( 'dcprice_admin_page_xbz_asia_section', 'dcprice_render_xbz_asia_admin_section' );
function dcprice_render_xbz_asia_admin_section() {
	$xbz_meta  = get_option( 'dcprice_xbz_cache_meta', array() );
	$asia_meta = get_option( 'dcprice_asia_cache_meta', array() );
	$map       = dcprice_categoria_map();
	?>
	<hr />
	<h2>XBZ / Asia Import — gravação pela tabela Omnitek</h2>
	<p class="description">
		Categorias com preço automático hoje:
		<?php
		$cats = array();
		foreach ( array_keys( $map ) as $slug ) {
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			$cats[] = $term ? $term->name : $slug;
		}
		echo esc_html( implode( ', ', $cats ) );
		?>
		. O resto do catálogo XBZ/Asia continua só orçamento.
	</p>
	<?php if ( ! empty( $xbz_meta ) ) : ?>
		<p>XBZ: <?php echo (int) $xbz_meta['refs_with_cost']; ?> SKUs com custo (de <?php echo (int) $xbz_meta['refs_no_site']; ?> SKUs no site).</p>
	<?php endif; ?>
	<?php if ( ! empty( $asia_meta ) ) : ?>
		<p>Asia Import: <?php echo (int) $asia_meta['refs_with_cost']; ?> SKUs com custo (de <?php echo (int) $asia_meta['refs_no_site']; ?> SKUs no site).</p>
	<?php endif; ?>
	<form method="post" style="display:inline-block;margin-right:10px;">
		<?php wp_nonce_field( 'dcprice_admin' ); ?>
		<input type="hidden" name="dcprice_action" value="refresh_xbz_cache" />
		<?php submit_button( 'Atualizar cache XBZ', 'secondary', 'submit', false ); ?>
	</form>
	<form method="post" style="display:inline-block;">
		<?php wp_nonce_field( 'dcprice_admin' ); ?>
		<input type="hidden" name="dcprice_action" value="refresh_asia_cache" />
		<?php submit_button( 'Atualizar cache Asia Import', 'secondary', 'submit', false ); ?>
	</form>
	<hr />
	<h3>Produtos sem origem marcada</h3>
	<p class="description">
		Produto sem "origem" (XBZ/Asia/Spot) marcada nunca entra no motor de preço, mesmo com custo cadastrado.
		Esse botão cruza o SKU dos produtos sem marcação com o cache da XBZ/Asia e com o CATÁLOGO CRU da Spot (busca online, pode levar um instante) e marca a origem quando bater.
		Depois de marcar como Spot, clique em "Atualizar cache agora" (na seção Spot, acima) pra esses novos entrarem no cache de custo.
	</p>
	<?php
	$backfill_result = get_option( 'dcprice_backfill_result', array() );
	if ( ! empty( $backfill_result ) ) :
		?>
		<p>Última rodada: <?php echo (int) $backfill_result['marcados_xbz']; ?> marcados como XBZ, <?php echo (int) $backfill_result['marcados_asia']; ?> marcados como Asia, <?php echo (int) ( $backfill_result['marcados_spot'] ?? 0 ); ?> marcados como Spot. <?php echo (int) $backfill_result['sem_sku_ou_sem_match']; ?> continuam sem bater com nenhum custo.</p>
		<?php
	endif;
	?>
	<form method="post">
		<?php wp_nonce_field( 'dcprice_admin' ); ?>
		<input type="hidden" name="dcprice_action" value="backfill_supplier_source" />
		<?php submit_button( 'Marcar origem por SKU (produtos sem marcação)', 'secondary', 'submit', false ); ?>
	</form>
	<?php
}

add_action( 'dcprice_admin_handle_action', 'dcprice_handle_xbz_asia_admin_action' );
function dcprice_handle_xbz_asia_admin_action( $action ) {
	if ( 'refresh_xbz_cache' === $action ) {
		$result = dcprice_refresh_xbz_cache();
		if ( is_wp_error( $result ) ) {
			echo '<div class="notice notice-error"><p>Erro XBZ: ' . esc_html( $result->get_error_message() ) . '</p></div>';
		} else {
			update_option( 'dcprice_xbz_cache_meta', $result, false );
			echo '<div class="notice notice-success"><p>XBZ: ' . (int) $result['refs_with_cost'] . ' SKUs com custo atualizado.</p></div>';
		}
	} elseif ( 'refresh_asia_cache' === $action ) {
		$result = dcprice_refresh_asia_cache();
		if ( is_wp_error( $result ) ) {
			echo '<div class="notice notice-error"><p>Erro Asia: ' . esc_html( $result->get_error_message() ) . '</p></div>';
		} else {
			update_option( 'dcprice_asia_cache_meta', $result, false );
			echo '<div class="notice notice-success"><p>Asia Import: ' . (int) $result['refs_with_cost'] . ' SKUs com custo atualizado.</p></div>';
		}
	} elseif ( 'backfill_supplier_source' === $action ) {
		$result = dcprice_backfill_supplier_source();
		update_option( 'dcprice_backfill_result', $result, false );
		echo '<div class="notice notice-success"><p>Origem marcada: ' . (int) $result['marcados_xbz'] . ' XBZ, ' . (int) $result['marcados_asia'] . ' Asia, ' . (int) ( $result['marcados_spot'] ?? 0 ) . ' Spot. ' . (int) $result['sem_sku_ou_sem_match'] . ' continuam sem custo cadastrado (nem XBZ, nem Asia, nem Spot têm esse SKU).</p></div>';
	}
}
