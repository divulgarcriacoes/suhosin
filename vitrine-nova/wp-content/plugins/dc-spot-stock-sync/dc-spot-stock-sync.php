<?php
/**
 * Plugin Name: DC · Sincronização de Estoque Spot
 * Description: Sincroniza estoque e categoriza automaticamente os produtos da fornecedora Spot, no mesmo padrão do módulo XBZ (cron, fila, prévia, histórico). Painel em WooCommerce → Sincronização Spot.
 * Version: 1.7.0
 * Author: Divulgar Criações
 * Text Domain: dc-spot-stock-sync
 *
 * =========================================================================
 * Conexão confirmada via documentação oficial (Manual Webservices BR v1.0 +
 * Postman collection + PHP REST client da Stricker Brasil/Spot Gifts):
 *   - Base: https://ws.spotgifts.com.br/api/v1SSL
 *   - Autenticação: AuthenticateClient?AccessKey=... → {"Token": "..."}
 *   - Estoque: Stocks?token=...&lang=PT → {"Stocks": [...], "ErrorCode": .., "ErrorMessage": ..}
 *   - Cada item do array Stocks tem (confirmado pelo diagrama de entidades
 *     do manual) os campos "Sku" e "WebSku".
 *
 * ÚNICA COISA AINDA NÃO CONFIRMADA: o nome exato do campo de QUANTIDADE
 * dentro de cada item de Stocks — o manual não mostra um exemplo de payload.
 * O padrão está em "Stock"; se estiver errado, o botão "Testar conexão"
 * mostra a lista real de campos que vieram no primeiro item, e você ajusta
 * "Campo do estoque" na tela (sem precisar editar código ou reinstalar).
 * =========================================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DCSPOTSTK_OPT', 'dcspotstk_settings' );
define( 'DCSPOTSTK_LOG_OPT', 'dcspotstk_log' );
define( 'DCSPOTSTK_REPORT_OPT', 'dcspotstk_last_report' );
define( 'DCSPOTSTK_CRON_HOOK', 'dcspotstk_cron_sync' );
define( 'DCSPOTSTK_CREATE_QUEUE_OPT', 'dcspotstk_create_queue' );
define( 'DCSPOTSTK_CREATE_REPORT_OPT', 'dcspotstk_create_report' );
define( 'DCSPOTSTK_CLEANUP_QUEUE_OPT', 'dcspotstk_cleanup_queue' );
define( 'DCSPOTSTK_CLEANUP_REPORT_OPT', 'dcspotstk_cleanup_report' );
define( 'DCSPOTSTK_PRICEFIX_QUEUE_OPT', 'dcspotstk_pricefix_queue' );
define( 'DCSPOTSTK_PRICEFIX_REPORT_OPT', 'dcspotstk_pricefix_report' );
define( 'DCSPOTSTK_VERSION', '1.7.0' );

/* -------------------------------------------------------------------------
 * Configurações padrão
 * ---------------------------------------------------------------------- */
function dcspotstk_defaults() {
	return array(
		'enabled'          => 0,        // começa DESLIGADO — ligar só depois de testar a conexão
		'frequency'        => 'twicedaily',
		'dry_run'          => 1,        // começa em modo simulação

		// --- Conexão real confirmada via documentação Stricker/Spot Gifts (webservice v1.0) ---
		'base_url'         => 'https://ws.spotgifts.com.br/api/v1SSL',
		'endpoint_path'    => 'Stocks',                // Stocks?token=...&lang=...
		'api_key'          => '', // chave da Spot: configurar no painel (removida do repositorio)      // AccessKey fornecida pela Spot/Stricker
		'auth_style'       => 'spotgifts',             // spotgifts (2 etapas: AuthenticateClient -> token) | query_key | bearer | header_apikey
		'auth_param_name'  => 'token',                 // nome do parâmetro de sessão nas chamadas seguintes
		'http_method'      => 'GET',                   // GET | POST
		'lang'             => 'PT',                    // idioma exigido pela API (PT)

		// --- Mapeamento de campos da resposta (confirmados na documentação; 'field_stock' é o único não 100% confirmado) ---
		'field_list_path'  => 'Stocks',        // a resposta vem como {"Stocks":[...], "Count":.., "ErrorCode":.., "ErrorMessage":..}
		'field_code'       => 'Sku',           // campo do SKU (confirmado no diagrama de entidades do manual)
		'field_name'       => 'WebSku',        // Stocks não traz nome de produto; usamos o título já cadastrado no site (ver lógica de categorização)
		'field_stock'      => 'Quantity',      // confirmado via teste de conexão real (campo "Stock" não existe; é "Quantity")
		'field_price'      => 'preco',         // não usado pela Spot (endpoint de estoque não traz preço)

		// --- Criação de produtos novos (mesmo padrão da XBZ/Asia) ---
		'create_new'         => 0,   // chave mestra — começa desligada até rodar a prévia
		'create_publish'     => 1,   // publicar direto (pedido pelo usuário)
		'create_image'       => 1,   // baixar a imagem principal da Spot
		'create_min_stock'   => 1,   // só cria SKU com estoque >= isto
		'create_batch'       => 15,  // quantos criar por passada (imagem é lenta)

		// Endpoint que traz nome/descrição/preço por SKU. A documentação da Spot
		// não mostra um exemplo de payload — "OptionalsComplete" é a escolha mais
		// provável (reúne produto + SKU + preço num só item), mas os nomes de
		// campo abaixo são o melhor palpite e se autocorrigem no "Testar produtos"
		// (mesmo mecanismo que corrigiu o campo de estoque).
		'products_endpoint'  => 'OptionalsComplete',
		'products_list_path' => 'OptionalsComplete',
		'pfield_sku'         => 'Sku',
		'pfield_ref'         => 'ProdReference',
		'pfield_name'        => 'Name',
		'pfield_desc'        => 'Description',
		'pfield_image'       => 'MainImage',

		// Endpoint separado só pra imagem (o OptionalsComplete não parece trazer
		// campo de imagem, conforme a documentação). ProductsTree é a "árvore
		// completa" do produto — palpite mais provável de ter MainImage.
		'images_endpoint'    => 'ProductsTree',
		'images_list_path'   => 'ProductsTree',
		// Confirmado navegando o site público da Spot logado (rede do navegador):
		// as imagens ficam em www.spotgifts.com.br/fotos/produtos/{arquivo}.jpg,
		// onde {arquivo} é exatamente o valor devolvido no campo de imagem
		// da API (ex: "30500_103_1.jpg"). Usado só quando o campo não já vier
		// como URL completa.
		'images_base_url'    => 'https://www.spotgifts.com.br/fotos/produtos/',

		// --- Regras de negócio de estoque (mesmo padrão da XBZ) ---
		'manage_stock'      => 1,
		'set_out_of_stock'  => 0,   // desligado por padrão — mesma cautela da XBZ
		'oos_threshold'     => 0,
		'update_price'      => 0,   // a XBZ nunca mexe em preço; Spot fica a seu critério

		// --- Categorização automática de produtos casados/novos (reaproveita as regras do dc-spot-categorize) ---
		'auto_categorize'   => 1,
		'cat_default'       => 'Novidades',
		'cat_rules'         => dcspotstk_default_cat_rules(),
	);
}

function dcspotstk_default_cat_rules() {
	// Ruleset ampliado com tudo aprendido categorizando manualmente os ~330
	// produtos que caíam em "Novidades" (sem regra correspondente). Ordem
	// importa: termos mais específicos vêm antes dos genéricos.
	return <<<TXT
caneta metálica com estojo => METAL ESFERO
caneta metal touch => TOUCH
caneta metálica => METAL ESFERO
caneta metal => METAL ESFERO
caneta roller => METAL ROLLER
caneta ecológica => ECOLÓGICAS
caneta de bambu => ECOLÓGICAS
caneta plástica => PLÁSTICA
caneta esferográfica => CANETAS
caneta => CANETAS
lápis apontado => Lápis e Lapiseiras
lápis em madeira => Lápis e Lapiseiras
lápis em resina => Lápis e Lapiseiras
mini lápis => Lápis e Lapiseiras
lápis => Lápis e Lapiseiras
lapiseira => Lápis e Lapiseiras
apontador => Lápis e Lapiseiras
borracha em tpr => Lápis e Lapiseiras
borracha branca => Lápis e Lapiseiras
caneca térmica => TÉRMICA
caneca plástica => CANECAS
caneca em aço inox => INOX
caneca de vidro => Vidro
caneca => CANECAS
copo térmico => COPOS
copo plástico => COPOS
copo em inox => COPOS
copo de parede dupla => COPOS
copo/taça => COPOS
copo => COPOS
garrafa térmica => SQUEEZES E GARRAFAS
garrafa plástica => SQUEEZES E GARRAFAS
garrafa pet => SQUEEZES E GARRAFAS
garrafa em alumínio => SQUEEZES E GARRAFAS
garrafa de vidro => SQUEEZES E GARRAFAS
garrafa aço inox => SQUEEZES E GARRAFAS
suporte para garrafas => SQUEEZES E GARRAFAS
tampa garrafa => SQUEEZES E GARRAFAS
squeeze => SQUEEZES E GARRAFAS
canudo => Canudos
bolsa térmica => Bolsas térmicas
mochila/bolsa térmica => Bolsas térmicas
sacola térmica => Bolsas térmicas
mochila para notebook => P/ Notebooks
mochila pasta => P/ Notebooks
mochila premium para notebook => P/ Notebooks
mala de viagem para notebook => P/ Notebooks
porta notebook => P/ Notebooks
porta-notebook => P/ Notebooks
pasta para notebook => P/ Notebooks
mochila com rodinhas => Trolley
mala de bordo => Trolley
mala de viagem => Malas
mala executiva => Malas
mala esportiva => Malas
mala de cosméticos => Malas
saco de viagem => Malas
mochila saco => Mochilas
mochila 20l => Mochilas
mochila 23l => Mochilas
mochila 24l => Mochilas
mochila 18l => Mochilas
mochila poliéster => Mochilas
mochila couro sintético => Mochilas
mochila multifuncional => Mochilas
mochila com compartimento => Mochilas
mochila pvc => Mochilas
mochila em 600d => Mochilas
mochila => Mochilas
sacola/mochila => SACOLAS
sacola tnt => SACOLAS
sacola de juta => SACOLAS
sacola em juta => SACOLAS
sacola em algodão => SACOLAS
sacola em poliéster => SACOLAS
sacola plástica => SACOLAS
sacola papel => SACOLAS
sacola algodão => SACOLAS
sacola metalizada => SACOLAS
saco em tnt => SACOLAS
sacola => SACOLAS
bolsa multifuncional => BOLSAS
bolsa tnt => BOLSAS
bolsa de feltro => BOLSAS
bolsa transversal => BOLSAS
bolsa para óculos => BOLSAS
bolsa herm => BOLSAS
bolsa de cosméticos => BOLSAS
necessaire => Necessaires
nécessaire => Necessaires
estojo para caneta => ESTOJOS
estojo organizador => ESTOJOS
estojo para óculos => ESTOJOS
estojo couro => ESTOJOS
estopjo => ESTOJOS
estojo => ESTOJOS
saquinho óculos => ESTOJOS
mosquetão => CHAVEIROS
porta objetos/smartphone => ACESSÓRIOS PARA CELULAR
suporte para celular => ACESSÓRIOS PARA CELULAR
suporte dobrável para celular => ACESSÓRIOS PARA CELULAR
porta celular => ACESSÓRIOS PARA CELULAR
bastão de selfie => ACESSÓRIOS PARA CELULAR
porta objetos => Porta Objetos
porta cartão para celular => Porta-Cartões
porta cartões com bloqueio => Porta-Cartões
porta cartões em pu => Porta-Cartões
porta cartão => Porta-Cartões
porta cartões => Porta-Cartões
porta crachá => Porta-Crachás
placa metal p/ mala => Placas para Mala
placa de metal => Placas para Mala
placa de inox => Placas para Mala
identificador de bagagem => Placas para Mala
tag identificador de bagagem => Placas para Mala
placa mercosul => ACESSÓRIOS PARA CARROS
adaptador de isqueiro => ACESSÓRIOS PARA CARROS
pasta capa => Pastas
pasta executiva => Pastas
pasta a4 => Pastas
pasta a5 => Pastas
pasta de congresso => Pastas
capa a5 => Pastas
capa para notebook => Pastas
porta lápis => ESCRITÓRIO
organizador portátil => ESCRITÓRIO
organizador de secretária => ESCRITÓRIO
mini caderno => BLOCOS E CADERNETAS
caderno a5 => BLOCOS E CADERNETAS
caderno a6 => BLOCOS E CADERNETAS
caderno b5 => BLOCOS E CADERNETAS
caderno b6 => BLOCOS E CADERNETAS
caderno em pu => BLOCOS E CADERNETAS
caderno capa => BLOCOS E CADERNETAS
caderno percalux => BLOCOS E CADERNETAS
caderno laminado => BLOCOS E CADERNETAS
caderno holográfico => BLOCOS E CADERNETAS
caderno de disco => BLOCOS E CADERNETAS
caderno emborrachado => BLOCOS E CADERNETAS
caderno capa dura => BLOCOS E CADERNETAS
caderno => BLOCOS E CADERNETAS
caderneta => BLOCOS E CADERNETAS
bloco de anotações => BLOCOS E CADERNETAS
blocos adesivados => BLOCOS E CADERNETAS
planner => BLOCOS E CADERNETAS
agenda diária => AGENDAS 2027
agenda emborrachada => AGENDAS 2027
agenda espiral => AGENDAS 2027
agenda couro => AGENDAS 2027
agenda => AGENDAS 2027
calendário => CALENDARIOS
chaveiro canivete => Canivetes
canivete => Canivetes
chaveiro => CHAVEIROS
mala/mochila esportiva => Malas
kit vinho => Kit Vinho
conjunto de vinho => Kit Vinho
conjunto para vinho => Kit Vinho
kit churrasco => KIT CHURRASCO
kit queijo => KIT QUEIJO
kit viagem => KIT VIAGEM
kit executivo => Kits executivos
kit escritório => Kit Escritório
conjunto para escritório => Kit Escritório
kit ferramenta => Kit Ferramenta
kit manicure => MANICURE
conjunto de manicure => MANICURE
kit costura => Kit Costura
kit coqueteleira => Kit Coqueteleira
kit faixas elásticas => Linha Fitness
kit prendedores => Utensilios de cozinha
kit => KITS
umidificador => Umidificadores
mini ventilador => ELÉTRONICO
mouse pad => ELÉTRONICO
mouse wireless => ELÉTRONICO
mouse para jogos => ELÉTRONICO
hub usb => ELÉTRONICO
hub em bambu => ELÉTRONICO
webcam => ELÉTRONICO
localizador sem fios => ELÉTRONICO
cabo usb => ELÉTRONICO
cabo retrátil => ELÉTRONICO
cabo espiral => ELÉTRONICO
conjunto de adaptadores => ELÉTRONICO
adaptador de corrente => ELÉTRONICO
cabo para carregamento => CARREGADORES POWER BANK
power bank => CARREGADORES POWER BANK
bateria portátil => CARREGADORES POWER BANK
carregador por indução => CARREGADORES POWER BANK
carregador magnético => CARREGADORES POWER BANK
carregador => CARREGADORES POWER BANK
fone de ouvido => FONES DE OUVIDO
fones de ouvido => FONES DE OUVIDO
caixa de som => CAIXAS DE SOM
coluna portátil => CAIXAS DE SOM
luminária => LANTERNAS E LUMINÁRIAS
lanterna => LANTERNAS E LUMINÁRIAS
guarda-sol => GUARDA-SOL
guarda-chuva => GUARDA-CHUVA
guarda chuva => GUARDA-CHUVA
capa de chuva => Capas de Chuva
boné => Bonés
chapéu => Chapéus
panamá => Chapéus
fita para chapéu => Chapéus
óculos de sol => Óculos de Sol
lancheira => Lancheiras
marmita => Lancheiras
kit sereia => Crianças
jogo tabuleiro => Crianças
lousa mágica => Crianças
lousa elefante => Crianças
lousa animais => Crianças
carrinho de controle remoto => Crianças
carro de controle remoto => Crianças
caminhão dinossauro => Crianças
celular infantil => Crianças
miniatura de empilhadeira => Crianças
drone => Crianças
livro inteligente => Crianças
cachorro de pelúcia => Crianças
tablet interativo => Crianças
tábua de corte => Tábuas
tábua de servir => Tábuas
tábua de queijos => Tábuas
tábua para pão => Tábuas
tábua em bambu => Tábuas
tábua de madeira => Tábuas
tábua => Tábuas
chaleira térmica => CHALEIRA
cafeteira => Artigos para café
bule => COZINHA
espremedor de frutas => COZINHA
galheteiro => COZINHA
saleiro => COZINHA
pimenteiro => COZINHA
saladeira => COZINHA
caixa de chás => COZINHA
suporte para facas => COZINHA
porta tachos => COZINHA
caixa hermética => COZINHA
conjunto de 3 talheres => COZINHA
esteira para grelha => COZINHA
jogo americano de feltro => Utensilios de cozinha
porta-copos => Utensilios de cozinha
descanso de panela => Utensilios de cozinha
home spray => Difusores e Aromas
difusor de aromas => Difusores e Aromas
escova com espelho => CUIDADOS PESSOAIS
espelho adesivo => CUIDADOS PESSOAIS
espelho de maquiagem => CUIDADOS PESSOAIS
máscara para dormir => CUIDADOS PESSOAIS
porta comprimidos => CUIDADOS PESSOAIS
termômetro digital => CUIDADOS PESSOAIS
elástico extensor => Linha Fitness
faixas elásticas => Linha Fitness
banda elástica => Linha Fitness
punho elástico => Linha Fitness
conjunto fitness => Linha Fitness
tapete de exercício => Linha Fitness
raquetes de praia => Jogos de Praia
régua plástica => ESCOLAR
pochete => pochete
kit sereia => Crianças
antiestresse => Anti-Estresse
anti-estresse => Anti-Estresse
antti-estresse => Anti-Estresse
saca-rolhas => Artigos para Vinho
rolha de vinho => Artigos para Vinho
conjunto de saca-rolhas => Artigos para Vinho
baralho de cartas => Jogos de Cartas
etiqueta em papel => Etiquetas
frasco => Frascos
caixa em cartão => Embalagens
toalha de praia => Toalhas
toalha esportiva => Toalhas
toalha para esporte => Toalhas
camiseta => CAMISETAS
chinelos => Chinelos
manta => Mantas
luvas => Luvas
colete de alta visibilidade => Coletes
cadeira dobrável => Cadeiras
TXT;
}

function dcspotstk_get_settings() {
	$s = get_option( DCSPOTSTK_OPT, array() );
	if ( ! is_array( $s ) ) {
		$s = array();
	}
	return wp_parse_args( $s, dcspotstk_defaults() );
}

/* -------------------------------------------------------------------------
 * Ativação / cron
 * ---------------------------------------------------------------------- */
register_activation_hook( __FILE__, function () {
	add_option( DCSPOTSTK_OPT, dcspotstk_defaults() );
	dcspotstk_reschedule();
} );

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( DCSPOTSTK_CRON_HOOK );
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( DCSPOTSTK_CRON_HOOK );
	}
} );

function dcspotstk_reschedule() {
	$s = dcspotstk_get_settings();
	wp_clear_scheduled_hook( DCSPOTSTK_CRON_HOOK );
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( DCSPOTSTK_CRON_HOOK );
	}
	if ( empty( $s['enabled'] ) ) {
		return;
	}
	$freq      = in_array( $s['frequency'], array( 'hourly', 'twicedaily', 'daily' ), true ) ? $s['frequency'] : 'twicedaily';
	$intervals = array( 'hourly' => HOUR_IN_SECONDS, 'twicedaily' => 12 * HOUR_IN_SECONDS, 'daily' => DAY_IN_SECONDS );
	if ( function_exists( 'as_schedule_recurring_action' ) ) {
		as_schedule_recurring_action( time() + 300, $intervals[ $freq ], DCSPOTSTK_CRON_HOOK, array(), 'dcspotstk' );
	} else {
		wp_schedule_event( time() + 300, $freq, DCSPOTSTK_CRON_HOOK );
	}
}
add_action( DCSPOTSTK_CRON_HOOK, 'dcspotstk_run_sync' );

// Continua drenando as filas (estoque e criação de produtos) a cada
// carregamento de tela admin, como a XBZ faz — assim uma fila grande
// termina sozinha em algumas visitas ao painel, sem travar o navegador.
add_action( 'admin_init', function () {
	$rep = get_option( DCSPOTSTK_REPORT_OPT, array() );
	if ( is_array( $rep ) && ! empty( $rep['running'] ) ) {
		dcspotstk_drain_queue( 20 );
	}
	$crep = get_option( DCSPOTSTK_CREATE_REPORT_OPT, array() );
	if ( is_array( $crep ) && ! empty( $crep['running'] ) ) {
		dcspotstk_drain_create_queue( 20 );
	}
	$xrep = get_option( DCSPOTSTK_CLEANUP_REPORT_OPT, array() );
	if ( is_array( $xrep ) && ! empty( $xrep['running'] ) ) {
		dcspotstk_drain_cleanup_queue( 20 );
	}
	$prep = get_option( DCSPOTSTK_PRICEFIX_REPORT_OPT, array() );
	if ( is_array( $prep ) && ! empty( $prep['running'] ) ) {
		dcspotstk_drain_pricefix_queue( 20 );
	}
} );

/* -------------------------------------------------------------------------
 * Busca o catálogo da Spot — ESTA é a função que precisa dos dados reais
 * da API (endpoint + nomes de campo). Está genérica e configurável pela
 * tela, mas só vai funcionar de verdade depois de testada contra a API
 * real da Spot.
 * ---------------------------------------------------------------------- */
/**
 * Autentica na Spot Gifts (Stricker Brasil): AuthenticateClient com AccessKey,
 * devolve o token de sessão. O manual diz que o token vale até 24h, então
 * fica em cache num transient para não reautenticar a cada chamada.
 */
function dcspotstk_get_session_token( $s, $force = false ) {
	$cache_key = 'dcspotstk_token';
	if ( ! $force ) {
		$cached = get_transient( $cache_key );
		if ( $cached ) {
			return $cached;
		}
	}

	$url = trailingslashit( $s['base_url'] ) . 'AuthenticateClient';
	$url = add_query_arg( array( 'AccessKey' => $s['api_key'] ), $url );

	$res = wp_remote_get( $url, array(
		'timeout'    => 60,
		'sslverify'  => true,
		'headers'    => array( 'Accept' => 'application/json' ),
		'user-agent' => 'Mozilla/5.0 (compatible; DC-Spot-StockSync/' . DCSPOTSTK_VERSION . ')',
	) );

	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'dcspotstk_auth_wp', 'Erro de rede ao autenticar na Spot: ' . $res->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = wp_remote_retrieve_body( $res );
	if ( 200 !== $code ) {
		return new WP_Error( 'dcspotstk_auth_http', sprintf( 'HTTP %d ao autenticar. Corpo: %s', $code, dcspotstk_snippet( $body ) ) );
	}
	$data = json_decode( $body, true );
	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
		return new WP_Error( 'dcspotstk_auth_json', 'Resposta de autenticação inválida. Corpo: ' . dcspotstk_snippet( $body ) );
	}
	if ( ! empty( $data['ErrorCode'] ) ) {
		return new WP_Error( 'dcspotstk_auth_api', 'Spot recusou a autenticação (código ' . $data['ErrorCode'] . '): ' . ( $data['ErrorMessage'] ?? '' ) );
	}
	$token = $data['Token'] ?? ( $data['token'] ?? '' );
	if ( '' === $token ) {
		return new WP_Error( 'dcspotstk_auth_notoken', 'Autenticação OK, mas não veio "Token" na resposta. Corpo: ' . dcspotstk_snippet( $body ) );
	}

	// Guarda por 23h (o manual fala em até 24h de validade) para reduzir chamadas de auth.
	set_transient( $cache_key, $token, 23 * HOUR_IN_SECONDS );
	return $token;
}

function dcspotstk_fetch_catalog( $s ) {
	if ( empty( $s['base_url'] ) ) {
		return new WP_Error( 'dcspotstk_no_url', 'URL da API da Spot ainda não configurada.' );
	}

	@ini_set( 'memory_limit', '512M' );

	$url  = trailingslashit( $s['base_url'] ) . ltrim( $s['endpoint_path'], '/' );
	$args = array(
		'timeout'     => 120,
		'redirection' => 2,
		'sslverify'   => true,
		'headers'     => array( 'Accept' => 'application/json' ),
		'user-agent'  => 'Mozilla/5.0 (compatible; DC-Spot-StockSync/' . DCSPOTSTK_VERSION . ')',
	);

	if ( 'spotgifts' === $s['auth_style'] ) {
		// --- Fluxo real da Spot Gifts/Stricker: autentica, depois chama o endpoint com token+lang ---
		$token = dcspotstk_get_session_token( $s );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$url = add_query_arg( array( 'token' => $token, 'lang' => $s['lang'] ?: 'PT' ), $url );
	} else {
		switch ( $s['auth_style'] ) {
			case 'bearer':
				$args['headers']['Authorization'] = 'Bearer ' . $s['api_key'];
				break;
			case 'header_apikey':
				$args['headers'][ $s['auth_param_name'] ?: 'apikey' ] = $s['api_key'];
				break;
			case 'query_key':
			default:
				$url = add_query_arg( array( ( $s['auth_param_name'] ?: 'chave' ) => $s['api_key'] ), $url );
				break;
		}
	}

	$res = ( 'POST' === strtoupper( $s['http_method'] ) )
		? wp_remote_post( $url, $args )
		: wp_remote_get( $url, $args );

	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'dcspotstk_wp', 'Erro de rede ao chamar a Spot: ' . $res->get_error_message() );
	}
	$code  = (int) wp_remote_retrieve_response_code( $res );
	$ctype = wp_remote_retrieve_header( $res, 'content-type' );
	$body  = wp_remote_retrieve_body( $res );
	unset( $res );

	// Sessão expirada no meio do caminho: reautentica uma vez e tenta de novo.
	if ( 'spotgifts' === $s['auth_style'] && 200 === $code ) {
		$peek = json_decode( $body, true );
		if ( is_array( $peek ) && ! empty( $peek['ErrorCode'] ) ) {
			// A Spot não documenta um código único para sessão inválida/expirada
			// (vimos 13 no manual e 22 na prática) — trata qualquer ErrorCode
			// na primeira tentativa como possível sessão vencida e renova o token.
			$token = dcspotstk_get_session_token( $s, true );
			if ( ! is_wp_error( $token ) ) {
				$url2 = add_query_arg( array( 'token' => $token, 'lang' => $s['lang'] ?: 'PT' ), trailingslashit( $s['base_url'] ) . ltrim( $s['endpoint_path'], '/' ) );
				$res2 = wp_remote_get( $url2, $args );
				if ( ! is_wp_error( $res2 ) ) {
					$code = (int) wp_remote_retrieve_response_code( $res2 );
					$body = wp_remote_retrieve_body( $res2 );
				}
			}
		}
	}

	if ( 200 !== $code ) {
		return new WP_Error( 'dcspotstk_http', sprintf( 'HTTP %d. Content-Type: %s. Corpo: %s', $code, $ctype, dcspotstk_snippet( $body ) ) );
	}
	if ( '' === trim( $body ) ) {
		return new WP_Error( 'dcspotstk_empty', 'Resposta vazia da Spot.' );
	}

	$body = preg_replace( '/^\xEF\xBB\xBF/', '', $body );
	$data = json_decode( $body, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		return new WP_Error( 'dcspotstk_json', sprintf( 'JSON inválido (%s). Corpo: %s', json_last_error_msg(), dcspotstk_snippet( $body ) ) );
	}

	if ( is_array( $data ) && ! empty( $data['ErrorCode'] ) ) {
		return new WP_Error( 'dcspotstk_api', 'Spot retornou erro (código ' . $data['ErrorCode'] . '): ' . ( $data['ErrorMessage'] ?? '' ) );
	}

	// Navega até o array usando 'field_list_path' (ex: "Stocks").
	$list_source = $data;
	if ( ! empty( $s['field_list_path'] ) ) {
		foreach ( explode( '.', $s['field_list_path'] ) as $key ) {
			if ( is_array( $list_source ) && isset( $list_source[ $key ] ) ) {
				$list_source = $list_source[ $key ];
			} else {
				return new WP_Error( 'dcspotstk_path', 'Caminho "' . esc_html( $s['field_list_path'] ) . '" não encontrado na resposta. Corpo: ' . dcspotstk_snippet( $body ) );
			}
		}
	}

	if ( ! is_array( $list_source ) ) {
		return new WP_Error( 'dcspotstk_shape', 'A Spot respondeu, mas não veio uma lista onde eu esperava. Ajuste "Caminho até a lista" nas configurações. Corpo: ' . dcspotstk_snippet( $body ) );
	}

	// Diagnóstico: se o primeiro item não tiver o campo de estoque configurado,
	// avisa quais campos realmente vieram (ajuda a corrigir "field_stock" na tela).
	if ( ! empty( $list_source ) ) {
		$first = reset( $list_source );
		if ( is_array( $first ) && ! isset( $first[ $s['field_stock'] ] ) ) {
			return new WP_Error(
				'dcspotstk_field_stock',
				'Conectou certinho, mas o campo de estoque "' . esc_html( $s['field_stock'] ) . '" não existe nos itens. '
				. 'Campos que realmente vieram no primeiro item: ' . implode( ', ', array_keys( $first ) ) . '. '
				. 'Ajuste "Campo do estoque" nas configurações para um desses.'
			);
		}
	}

	return $list_source;
}

function dcspotstk_snippet( $s ) {
	$s = trim( wp_strip_all_tags( (string) $s ) );
	$s = preg_replace( '/\s+/', ' ', $s );
	return '' === $s ? '(vazio)' : mb_substr( $s, 0, 500 );
}

function dcspotstk_norm( $sku ) {
	$sku = strtoupper( trim( (string) $sku ) );
	$sku = preg_replace( '/\s+/', '', $sku );
	return $sku;
}

/**
 * Monta um índice código-normalizado => {name, qty} a partir do catálogo bruto,
 * usando os nomes de campo configurados na tela.
 */
function dcspotstk_build_index( $catalog, $s ) {
	$idx = array();
	foreach ( $catalog as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$code = $row[ $s['field_code'] ] ?? '';
		if ( '' === $code ) {
			continue;
		}
		$key = dcspotstk_norm( $code );
		$qty = isset( $row[ $s['field_stock'] ] ) ? max( 0, (int) $row[ $s['field_stock'] ] ) : 0;
		$name = $row[ $s['field_name'] ] ?? '';

		if ( ! isset( $idx[ $key ] ) ) {
			$idx[ $key ] = array( 'qty' => 0, 'name' => $name );
		}
		$idx[ $key ]['qty'] += $qty; // soma se houver mais de uma linha com o mesmo código (variações)
		if ( '' === $idx[ $key ]['name'] && '' !== $name ) {
			$idx[ $key ]['name'] = $name;
		}
	}
	return $idx;
}

/* -------------------------------------------------------------------------
 * Categoria por palavra-chave (mesma lógica do dc-spot-categorize)
 * ---------------------------------------------------------------------- */
function dcspotstk_parse_rules( $raw ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
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

function dcspotstk_match_category( $name, $rules, $default ) {
	$n = mb_strtolower( (string) $name, 'UTF-8' );
	foreach ( $rules as $r ) {
		if ( '' !== $r[0] && false !== mb_strpos( $n, $r[0] ) ) {
			return $r[1];
		}
	}
	return $default;
}

function dcspotstk_get_or_create_term( $name ) {
	$name = trim( (string) $name );
	if ( '' === $name ) {
		return 0;
	}
	$t = get_term_by( 'name', $name, 'product_cat' );
	if ( $t ) {
		return (int) $t->term_id;
	}
	$r = wp_insert_term( $name, 'product_cat' );
	return is_wp_error( $r ) ? 0 : (int) $r['term_id'];
}

/* -------------------------------------------------------------------------
 * Execução principal — mesma estrutura de relatório/plano do módulo XBZ.
 * ---------------------------------------------------------------------- */
function dcspotstk_run_sync( $force_dry = null ) {
	@set_time_limit( 0 );
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}

	$s      = dcspotstk_get_settings();
	$dry    = is_null( $force_dry ) ? ! empty( $s['dry_run'] ) : (bool) $force_dry;
	$report = array(
		'started'   => current_time( 'mysql' ),
		'finished'  => '',
		'dry_run'   => $dry,
		'ok'        => false,
		'message'   => '',
		'spot_items'=> 0,
		'sku_products' => 0,
		'matched'   => 0,
		'changed'   => 0,
		'applied'   => 0,
		'categorized' => 0,
		'unmatched' => 0,
		'samples'   => array(),
	);

	if ( ! function_exists( 'wc_get_product' ) ) {
		$report['message'] = 'WooCommerce não está ativo.';
		return dcspotstk_store_report( $report );
	}
	if ( empty( $s['api_key'] ) || empty( $s['base_url'] ) ) {
		$report['message'] = 'Chave ou URL da Spot não configuradas.';
		return dcspotstk_store_report( $report );
	}

	$catalog = dcspotstk_fetch_catalog( $s );
	if ( is_wp_error( $catalog ) ) {
		$report['message'] = 'Falha ao buscar catálogo Spot: ' . $catalog->get_error_message();
		return dcspotstk_store_report( $report );
	}
	$report['spot_items'] = count( $catalog );

	$index = dcspotstk_build_index( $catalog, $s );
	unset( $catalog );

	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT pm.post_id AS id, pm.meta_value AS sku,
		        COALESCE( ms.meta_value, '' ) AS manage_stock,
		        st.meta_value AS stock,
		        COALESCE( ss.meta_value, 'instock' ) AS stock_status
		   FROM {$wpdb->postmeta} pm
		   JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		   LEFT JOIN {$wpdb->postmeta} ms ON ms.post_id = pm.post_id AND ms.meta_key = '_manage_stock'
		   LEFT JOIN {$wpdb->postmeta} st ON st.post_id = pm.post_id AND st.meta_key = '_stock'
		   LEFT JOIN {$wpdb->postmeta} ss ON ss.post_id = pm.post_id AND ss.meta_key = '_stock_status'
		  WHERE pm.meta_key = '_sku' AND pm.meta_value <> ''
		    AND p.post_type IN ('product','product_variation') AND p.post_status = 'publish'"
	);
	$report['sku_products'] = count( $rows );

	$rules   = dcspotstk_parse_rules( $s['cat_rules'] );
	$plan    = array();

	foreach ( $rows as $r ) {
		$key = dcspotstk_norm( $r->sku );
		if ( ! isset( $index[ $key ] ) ) {
			$report['unmatched']++;
			continue;
		}
		$report['matched']++;
		$hit = $index[ $key ];

		$new_qty     = max( 0, (int) $hit['qty'] );
		$cur_managed = ( 'yes' === $r->manage_stock );
		$cur_qty     = ( null === $r->stock || '' === $r->stock ) ? null : (int) $r->stock;
		$cur_status  = $r->stock_status ? $r->stock_status : 'instock';

		if ( empty( $s['set_out_of_stock'] ) && $new_qty <= 0 ) {
			$want_status = 'instock';
			$needs       = $cur_managed || ( 'instock' !== $cur_status );
		} elseif ( ! empty( $s['set_out_of_stock'] ) ) {
			$want_status = ( $new_qty <= (int) $s['oos_threshold'] ) ? 'outofstock' : 'instock';
			$needs       = ( $cur_qty !== $new_qty ) || ( ! empty( $s['manage_stock'] ) && ! $cur_managed ) || ( $cur_status !== $want_status );
		} else {
			$want_status = 'instock';
			$needs       = ( $cur_qty !== $new_qty ) || ( ! empty( $s['manage_stock'] ) && ! $cur_managed ) || ( 'instock' !== $cur_status );
		}

		$want_cat = 0;
		if ( ! empty( $s['auto_categorize'] ) ) {
			$has_cat = wp_get_object_terms( $r->id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( empty( $has_cat ) ) {
				$cat_name = dcspotstk_match_category( $hit['name'] ?: get_the_title( $r->id ), $rules, $s['cat_default'] );
				$want_cat = $cat_name;
				$needs    = true;
			}
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
			'cat'    => $want_cat,
		);
		if ( count( $report['samples'] ) < 40 ) {
			$report['samples'][] = end( $plan );
		}
	}

	if ( ! $dry ) {
		foreach ( $plan as $item ) {
			$p = wc_get_product( $item['id'] );
			if ( ! $p ) {
				continue;
			}
			if ( ! empty( $s['manage_stock'] ) ) {
				$p->set_manage_stock( true );
				$p->set_stock_quantity( $item['to'] );
			}
			$p->set_stock_status( $item['status'] );
			$p->save();
			$report['applied']++;

			if ( ! empty( $item['cat'] ) ) {
				$term_id = dcspotstk_get_or_create_term( $item['cat'] );
				if ( $term_id ) {
					wp_set_object_terms( $item['id'], array( $term_id ), 'product_cat', false );
					$report['categorized']++;
				}
			}
		}
	}

	// Tabela de cores ("Cores disponíveis"): atualiza o estoque de CADA COR pelo catálogo da Spot
	// e o estoque total do produto (soma das cores). Cor que saiu do catálogo fica com 0.
	$report['cores_produtos']  = 0;
	$report['cores_alteradas'] = 0;
	// Data em que cada produto foi visto no catálogo (mu-plugin dc-estoque-sob-consulta).
	// Famílias da Spot: código base (51716) => [ código da cor (51716-105) => qtd ].
	$fam_spot = array();
	foreach ( $index as $k => $hit ) {
		if ( preg_match( '/^(.+)-(\d{3})$/', $k, $mm ) ) {
			$fam_spot[ $mm[1] ][ $k ] = max( 0, (int) $hit['qty'] );
		}
	}
	// Produtos cadastrados só com o código base e sem tabela de cores: monta a tabela e o total pela família.
	$report['cores_criadas'] = 0;
	if ( count( $index ) > 500 ) {
		$cmap  = get_option( 'dcspotmig_last_colormap' );
		$cmap  = ( is_array( $cmap ) && ! empty( $cmap['map'] ) ) ? $cmap['map'] : array();
		$semtb = $wpdb->get_results(
			"SELECT p.ID AS id,
			        ( SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = '_dcspot_ref' LIMIT 1 ) AS ref,
			        ( SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = '_sku' LIMIT 1 ) AS sku
			   FROM {$wpdb->posts} p
			  WHERE p.post_type = 'product' AND p.post_status = 'publish'
			    AND EXISTS ( SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key = '_dcspot_ref' )
			    AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} t WHERE t.post_id = p.ID AND t.meta_key = '_dc_color_stock_table' AND t.meta_value <> '' )"
		);
		foreach ( $semtb as $r ) {
			$base = dcspotstk_norm( $r->ref ? $r->ref : $r->sku );
			if ( empty( $fam_spot[ $base ] ) || isset( $index[ dcspotstk_norm( $r->sku ) ] ) ) {
				continue; // sem família, ou o SKU do produto já é o de uma cor (o laço principal cuida)
			}
			$tab   = array();
			$total = 0;
			ksort( $fam_spot[ $base ] );
			foreach ( $fam_spot[ $base ] as $k => $q ) {
				$cod   = substr( $k, -3 );
				$tab[] = array( 'sku' => $k, 'label' => isset( $cmap[ $cod ] ) ? $cmap[ $cod ] : ( isset( $cmap[ (int) $cod ] ) ? $cmap[ (int) $cod ] : 'Cor ' . $cod ), 'stock' => $q );
				$total += $q;
			}
			$report['cores_criadas']++;
			if ( $dry ) {
				continue;
			}
			update_post_meta( (int) $r->id, '_dc_color_stock_table', wp_json_encode( $tab ) );
			$p = wc_get_product( (int) $r->id );
			if ( $p && ! $p->is_type( 'variable' ) ) {
				$p->set_manage_stock( true );
				$p->set_stock_quantity( $total );
				$p->set_stock_status( ( ! empty( $s['set_out_of_stock'] ) && $total <= (int) $s['oos_threshold'] ) ? 'outofstock' : 'instock' );
				$p->save();
			}
		}
	}
	if ( ! $dry && function_exists( 'dc_forn_marcar_vistos' ) && count( $index ) > 500 ) {
		$report['vistos'] = dc_forn_marcar_vistos( 'spot', function ( $c ) use ( $index, $fam_spot ) {
			$n = dcspotstk_norm( $c );
			return isset( $index[ $n ] ) || ! empty( $fam_spot[ $n ] );
		} );
	}
	if ( count( $index ) > 500 ) { // só confia no catálogo se ele veio completo
		$ct = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_dc_color_stock_table' AND meta_value <> ''" );
		foreach ( $ct as $c ) {
			$tab = json_decode( (string) $c->meta_value, true );
			if ( ! is_array( $tab ) || ! $tab ) {
				continue;
			}
			$achou = false;
			foreach ( $tab as $row ) {
				$k = dcspotstk_norm( is_array( $row ) && isset( $row['sku'] ) ? $row['sku'] : '' );
				if ( '' !== $k && isset( $index[ $k ] ) ) {
					$achou = true;
					break;
				}
			}
			if ( ! $achou ) {
				continue; // não é produto Spot (XBZ, Asia...): não mexe
			}
			$mudou = false;
			$total = 0;
			foreach ( $tab as $i => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$k    = dcspotstk_norm( isset( $row['sku'] ) ? $row['sku'] : '' );
				$novo = ( '' !== $k && isset( $index[ $k ] ) ) ? max( 0, (int) $index[ $k ]['qty'] ) : 0;
				if ( ! isset( $row['stock'] ) || (int) $row['stock'] !== $novo ) {
					$tab[ $i ]['stock'] = $novo;
					$mudou              = true;
				}
				$total += $novo;
			}
			$report['cores_produtos']++;
			if ( ! $mudou ) {
				continue;
			}
			$report['cores_alteradas']++;
			if ( $dry ) {
				continue;
			}
			update_post_meta( (int) $c->post_id, '_dc_color_stock_table', wp_json_encode( array_values( $tab ) ) );
			$p = wc_get_product( (int) $c->post_id );
			if ( $p && ! $p->is_type( 'variable' ) ) {
				$p->set_manage_stock( true );
				$p->set_stock_quantity( $total );
				$p->set_stock_status( ( ! empty( $s['set_out_of_stock'] ) && $total <= (int) $s['oos_threshold'] ) ? 'outofstock' : 'instock' );
				$p->save();
			}
		}
	}

	$report['ok']       = true;
	$report['finished']  = current_time( 'mysql' );
	return dcspotstk_store_report( $report );
}

function dcspotstk_store_report( $report ) {
	update_option( DCSPOTSTK_REPORT_OPT, $report, false );
	$log = get_option( DCSPOTSTK_LOG_OPT, array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	array_unshift( $log, $report );
	$log = array_slice( $log, 0, 30 );
	update_option( DCSPOTSTK_LOG_OPT, $log, false );
	return $report;
}

/* -------------------------------------------------------------------------
 * Limpeza: apaga TODOS os produtos criados por este plugin (marcados com
 * _dcspot_source=spot) e zera as filas de criação/prévia — usado pra
 * recomeçar do zero depois de uma execução com bug (ex: fila velha
 * reaproveitada por engano, causando duplicados em massa).
 * ---------------------------------------------------------------------- */
function dcspotstk_start_cleanup() {
	global $wpdb;
	$ids = $wpdb->get_col(
		"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
		 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = '_dcspot_source' AND pm.meta_value = 'spot' AND p.post_type = 'product'"
	);
	$ids = array_map( 'intval', $ids );

	// Zera qualquer fila/relatório de criação e prévia pendente — não faz
	// sentido continuar drenando uma fila que aponta pra produtos que
	// estamos prestes a apagar.
	delete_option( DCSPOTSTK_CREATE_QUEUE_OPT );
	delete_option( DCSPOTSTK_CREATE_REPORT_OPT );
	delete_transient( 'dcspotstk_preview' );

	update_option( DCSPOTSTK_CLEANUP_QUEUE_OPT, $ids, false );
	update_option(
		DCSPOTSTK_CLEANUP_REPORT_OPT,
		array( 'total' => count( $ids ), 'done' => 0, 'deleted' => 0, 'errors' => 0, 'when' => current_time( 'mysql' ), 'running' => true ),
		false
	);
	return count( $ids );
}

/**
 * Encontra SKUs duplicados entre os produtos criados por este plugin e
 * enfileira só os EXTRAS pra apagar, mantendo sempre o de ID mais baixo
 * (o mais antigo) de cada SKU. Usa a mesma fila/relatório da limpeza geral.
 */
function dcspotstk_start_dedup() {
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT pm.meta_value AS sku, GROUP_CONCAT(p.ID ORDER BY p.ID) AS ids
		   FROM {$wpdb->posts} p
		   JOIN {$wpdb->postmeta} pm  ON pm.post_id = p.ID AND pm.meta_key = '_sku' AND pm.meta_value <> ''
		   JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = p.ID AND pm2.meta_key = '_dcspot_source' AND pm2.meta_value = 'spot'
		  WHERE p.post_type = 'product'
		  GROUP BY pm.meta_value
		 HAVING COUNT(*) > 1"
	);

	$ids = array();
	foreach ( $rows as $row ) {
		$group = array_map( 'intval', explode( ',', $row->ids ) );
		array_shift( $group ); // mantém o primeiro (mais antigo), enfileira o resto
		foreach ( $group as $extra_id ) {
			$ids[] = $extra_id;
		}
	}

	delete_option( DCSPOTSTK_CREATE_QUEUE_OPT );
	delete_option( DCSPOTSTK_CREATE_REPORT_OPT );
	delete_transient( 'dcspotstk_preview' );

	update_option( DCSPOTSTK_CLEANUP_QUEUE_OPT, $ids, false );
	update_option(
		DCSPOTSTK_CLEANUP_REPORT_OPT,
		array( 'total' => count( $ids ), 'done' => 0, 'deleted' => 0, 'errors' => 0, 'when' => current_time( 'mysql' ), 'running' => true ),
		false
	);
	return count( $ids );
}

/**
 * Corrige o preço pra 0 (ativa "Orçar item") nos produtos criados ANTES da
 * v1.7.0, que não tinham preço fixado na criação.
 */
function dcspotstk_start_pricefix() {
	global $wpdb;
	$ids = $wpdb->get_col(
		"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
		 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 LEFT JOIN {$wpdb->postmeta} pmp ON pmp.post_id = p.ID AND pmp.meta_key = '_regular_price'
		 WHERE pm.meta_key = '_dcspot_source' AND pm.meta_value = 'spot' AND p.post_type = 'product'
		   AND ( pmp.meta_value IS NULL OR pmp.meta_value <> '0' )"
	);
	$ids = array_map( 'intval', $ids );

	update_option( DCSPOTSTK_PRICEFIX_QUEUE_OPT, $ids, false );
	update_option(
		DCSPOTSTK_PRICEFIX_REPORT_OPT,
		array( 'total' => count( $ids ), 'done' => 0, 'fixed' => 0, 'when' => current_time( 'mysql' ), 'running' => true ),
		false
	);
	return count( $ids );
}

function dcspotstk_drain_pricefix_queue( $budget_seconds = 25 ) {
	$q = get_option( DCSPOTSTK_PRICEFIX_QUEUE_OPT, array() );
	if ( ! is_array( $q ) || empty( $q ) ) {
		return 0;
	}
	@set_time_limit( 0 );
	$start = microtime( true );
	$rep   = get_option( DCSPOTSTK_PRICEFIX_REPORT_OPT, array() );
	if ( ! is_array( $rep ) ) {
		$rep = array();
	}
	$rep += array( 'total' => count( $q ), 'done' => 0, 'fixed' => 0, 'running' => true );

	$done_now = 0;
	while ( ! empty( $q ) && ( microtime( true ) - $start ) < $budget_seconds ) {
		$id = (int) array_shift( $q );
		$p  = $id ? wc_get_product( $id ) : false;
		if ( $p ) {
			$p->set_regular_price( '0' );
			$p->set_price( '0' );
			$p->save();
			$rep['fixed']++;
		}
		$rep['done']++;
		$done_now++;
	}

	update_option( DCSPOTSTK_PRICEFIX_QUEUE_OPT, $q, false );
	$rep['running'] = ! empty( $q );
	update_option( DCSPOTSTK_PRICEFIX_REPORT_OPT, $rep, false );
	return $done_now;
}
function dcspotstk_drain_cleanup_queue( $budget_seconds = 25 ) {
	$q = get_option( DCSPOTSTK_CLEANUP_QUEUE_OPT, array() );
	if ( ! is_array( $q ) || empty( $q ) ) {
		return 0;
	}
	@set_time_limit( 0 );
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}

	$rep = get_option( DCSPOTSTK_CLEANUP_REPORT_OPT, array() );
	if ( ! is_array( $rep ) ) {
		$rep = array();
	}
	$rep += array( 'total' => count( $q ), 'done' => 0, 'deleted' => 0, 'errors' => 0, 'running' => true );

	$start    = microtime( true );
	$done_now = 0;
	while ( ! empty( $q ) && ( microtime( true ) - $start ) < $budget_seconds ) {
		$id  = (int) array_shift( $q );
		$res = $id ? wp_delete_post( $id, true ) : false; // true = força exclusão definitiva, sem ir pra lixeira
		if ( $res ) {
			$rep['deleted']++;
		} else {
			$rep['errors']++;
		}
		$rep['done']++;
		$done_now++;
	}

	update_option( DCSPOTSTK_CLEANUP_QUEUE_OPT, $q, false );
	$rep['running'] = ! empty( $q );
	update_option( DCSPOTSTK_CLEANUP_REPORT_OPT, $rep, false );

	return $done_now;
}

/* -------------------------------------------------------------------------
 * Criação de produtos novos (mesmo padrão da XBZ): busca o feed de produtos
 * (nome/descrição/imagem por SKU), monta a fila dos que ainda não existem no
 * site (comparando com wc_get_product_id_by_sku), e processa em lotes.
 * ---------------------------------------------------------------------- */
function dcspotstk_fetch_products( $s ) {
	if ( empty( $s['base_url'] ) ) {
		return new WP_Error( 'dcspotstk_no_url', 'URL da API da Spot ainda não configurada.' );
	}
	@ini_set( 'memory_limit', '512M' );

	$base_url = trailingslashit( $s['base_url'] ) . ltrim( $s['products_endpoint'], '/' );
	$args     = array(
		'timeout'    => 180,
		'sslverify'  => true,
		'headers'    => array( 'Accept' => 'application/json' ),
		'user-agent' => 'Mozilla/5.0 (compatible; DC-Spot-StockSync/' . DCSPOTSTK_VERSION . ')',
	);

	$data = null;
	$body = '';
	// Até 2 tentativas: se a sessão estiver inválida/expirada (qualquer ErrorCode
	// retornado, já que a Spot não documentou um código único para isso),
	// força uma nova autenticação e tenta de novo uma vez.
	for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
		$token = dcspotstk_get_session_token( $s, 2 === $attempt );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$url = add_query_arg( array( 'token' => $token, 'lang' => $s['lang'] ?: 'PT' ), $base_url );
		$res = wp_remote_get( $url, $args );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'dcspotstk_prod_wp', 'Erro de rede ao buscar produtos da Spot: ' . $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		if ( 200 !== $code ) {
			return new WP_Error( 'dcspotstk_prod_http', sprintf( 'HTTP %d ao buscar produtos. Corpo: %s', $code, dcspotstk_snippet( $body ) ) );
		}
		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error( 'dcspotstk_prod_json', 'JSON de produtos inválido. Corpo: ' . dcspotstk_snippet( $body ) );
		}
		if ( empty( $data['ErrorCode'] ) ) {
			break; // sucesso
		}
		if ( 1 === $attempt ) {
			continue; // tenta de novo com token forçadamente renovado
		}
		return new WP_Error( 'dcspotstk_prod_api', 'Spot retornou erro (código ' . $data['ErrorCode'] . '): ' . ( $data['ErrorMessage'] ?? '' ) . ' — mesmo após renovar o token de sessão.' );
	}

	$list = $data;
	if ( ! empty( $s['products_list_path'] ) ) {
		foreach ( explode( '.', $s['products_list_path'] ) as $key ) {
			if ( is_array( $list ) && isset( $list[ $key ] ) ) {
				$list = $list[ $key ];
			} else {
				return new WP_Error( 'dcspotstk_prod_path', 'Caminho "' . esc_html( $s['products_list_path'] ) . '" não encontrado. Corpo: ' . dcspotstk_snippet( $body ) );
			}
		}
	}
	if ( ! is_array( $list ) ) {
		return new WP_Error( 'dcspotstk_prod_shape', 'A Spot respondeu, mas não veio uma lista de produtos onde eu esperava.' );
	}

	if ( ! empty( $list ) ) {
		$first  = reset( $list );
		$needed = array( $s['pfield_sku'], $s['pfield_name'] );
		if ( is_array( $first ) ) {
			$missing = array_diff( $needed, array_keys( $first ) );
			if ( ! empty( $missing ) ) {
				return new WP_Error(
					'dcspotstk_prod_fields',
					'Conectou certinho, mas os campos ' . implode( ', ', $missing ) . ' não existem nos itens. '
					. 'Campos que realmente vieram no primeiro item: ' . implode( ', ', array_keys( $first ) ) . '. '
					. 'Ajuste os nomes de campo em "Criação de produtos novos" nas configurações.'
				);
			}
		}
	}

	return $list;
}

/**
 * Agrupa o feed de produtos por FAMÍLIA (ProdReference) — o OptionalsComplete
 * traz uma linha por variação (cor/tamanho), então sem agrupar cada cor viraria
 * um produto duplicado no site. Uma família = um produto WooCommerce; guarda a
 * lista de SKUs da família para somar estoque de todas as variações.
 */
function dcspotstk_build_product_meta( $products, $s ) {
	$out = array();
	foreach ( $products as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$sku = $row[ $s['pfield_sku'] ] ?? '';
		if ( '' === $sku ) {
			continue;
		}
		$ref = $row[ $s['pfield_ref'] ] ?? '';
		$fam_key = dcspotstk_norm( '' !== $ref ? $ref : $sku );

		if ( ! isset( $out[ $fam_key ] ) ) {
			$out[ $fam_key ] = array(
				'sku'      => '' !== $ref ? $ref : $sku, // usado como SKU do produto no site
				'name'     => $row[ $s['pfield_name'] ] ?? '',
				'desc'     => $row[ $s['pfield_desc'] ] ?? '',
				'image'    => '', // preenchido depois em dcspotstk_build_create_queue()
				'ref'      => $ref,
				'skus'     => array(),
				'skus_raw' => array(), // sem normalizar — precisa do case/traços originais pra montar nome de arquivo de imagem
			);
		}
		$out[ $fam_key ]['skus'][]     = dcspotstk_norm( $sku );
		$out[ $fam_key ]['skus_raw'][] = (string) $sku;
		if ( '' === $out[ $fam_key ]['name'] && ! empty( $row[ $s['pfield_name'] ] ) ) {
			$out[ $fam_key ]['name'] = $row[ $s['pfield_name'] ];
		}
	}
	return $out;
}

/**
 * Busca imagens por família num endpoint separado (padrão: ProductsTree —
 * a "árvore completa" do produto, mais provável de trazer MainImage do que o
 * OptionalsComplete, que a documentação não lista com campo de imagem).
 * Falha aqui NÃO impede a criação dos produtos — só fica sem imagem.
 */
function dcspotstk_fetch_images( $s ) {
	$diag = dcspotstk_fetch_images_diag( $s );
	return is_wp_error( $diag ) ? array() : $diag;
}

/**
 * Mesma busca de imagens, mas devolve erro detalhado em vez de engolir tudo
 * em silêncio — usada pelo botão "Testar imagens" pra diagnosticar por que
 * nenhuma imagem está sendo anexada.
 */
function dcspotstk_fetch_images_diag( $s ) {
	if ( empty( $s['images_endpoint'] ) ) {
		return new WP_Error( 'dcspotstk_img_noendpoint', 'Nenhum "Endpoint de imagens" configurado.' );
	}
	// ProductsTree é a árvore completa do catálogo — pode ser um JSON bem
	// grande (a XBZ, por exemplo, chega a ~14MB numa resposta só). Sem isso,
	// o json_decode pode estourar o limite de memória padrão do PHP e derrubar
	// o site inteiro com um erro fatal.
	@ini_set( 'memory_limit', '1024M' );
	@set_time_limit( 0 );

	$url = trailingslashit( $s['base_url'] ) . ltrim( $s['images_endpoint'], '/' );

	$data = null;
	$body = '';
	// Mesmo mecanismo de retry das outras buscas: se a sessão estiver
	// inválida/expirada (qualquer ErrorCode), força um token novo e tenta
	// de novo uma vez antes de desistir.
	for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
		$token = dcspotstk_get_session_token( $s, 2 === $attempt );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$url_try = add_query_arg( array( 'token' => $token, 'lang' => $s['lang'] ?: 'PT' ), $url );
		$res     = wp_remote_get( $url_try, array(
			'timeout'    => 240,
			'sslverify'  => true,
			'headers'    => array( 'Accept' => 'application/json' ),
			'user-agent' => 'Mozilla/5.0 (compatible; DC-Spot-StockSync/' . DCSPOTSTK_VERSION . ')',
		) );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'dcspotstk_img_wp', 'Erro de rede ao buscar imagens: ' . $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		unset( $res );
		if ( 200 !== $code ) {
			return new WP_Error( 'dcspotstk_img_http', sprintf( 'HTTP %d ao buscar imagens ("%s"). Corpo: %s', $code, $s['images_endpoint'], dcspotstk_snippet( $body ) ) );
		}
		if ( strlen( $body ) > 150 * 1024 * 1024 ) {
			return new WP_Error( 'dcspotstk_img_huge', 'Resposta de imagens gigante demais (>150MB) — endpoint provavelmente errado para esse uso.' );
		}
		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error( 'dcspotstk_img_json', 'JSON de imagens inválido. Corpo: ' . dcspotstk_snippet( $body ) );
		}
		if ( empty( $data['ErrorCode'] ) ) {
			break; // sucesso
		}
		if ( 1 === $attempt ) {
			continue; // tenta de novo com token renovado
		}
		return new WP_Error( 'dcspotstk_img_api', 'Spot retornou erro (código ' . $data['ErrorCode'] . ') no endpoint de imagens: ' . ( $data['ErrorMessage'] ?? '' ) . ' — mesmo após renovar o token.' );
	}
	unset( $body );
	if ( ! empty( $s['images_list_path'] ) ) {
		foreach ( explode( '.', $s['images_list_path'] ) as $key ) {
			if ( is_array( $data ) && isset( $data[ $key ] ) ) {
				$data = $data[ $key ];
			} else {
				return new WP_Error( 'dcspotstk_img_path', 'Caminho "' . esc_html( $s['images_list_path'] ) . '" não encontrado na resposta de imagens. Chaves de topo recebidas: ' . ( is_array( $data ) ? implode( ', ', array_keys( $data ) ) : gettype( $data ) ) );
			}
		}
	}
	if ( ! is_array( $data ) || empty( $data ) ) {
		return new WP_Error( 'dcspotstk_img_empty', 'Lista de imagens veio vazia depois de navegar o caminho configurado.' );
	}

	$first = reset( $data );
	if ( is_array( $first ) ) {
		$has_ref = isset( $first[ $s['pfield_ref'] ] ) || isset( $first[ $s['pfield_sku'] ] );
		$has_img = isset( $first[ $s['pfield_image'] ] );
		if ( ! $has_ref || ! $has_img ) {
			return new WP_Error(
				'dcspotstk_img_fields',
				'Conectou, mas faltam campos no primeiro item: '
				. ( ! $has_ref ? 'referência/SKU ' : '' ) . ( ! $has_img ? 'imagem ' : '' )
				. '— campos que realmente vieram: ' . implode( ', ', array_keys( $first ) )
				. '. Ajuste "Campo da imagem principal" e/ou "Campo da referência" nas configurações.'
			);
		}
	}

	$out = array();
	foreach ( $data as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$ref = $row[ $s['pfield_ref'] ] ?? ( $row[ $s['pfield_sku'] ] ?? '' );
		$img = $row[ $s['pfield_image'] ] ?? '';
		// A API devolve só o nome do arquivo (ex: "30500_103_1.jpg"), não a
		// URL completa — completa com a base confirmada, a menos que já
		// venha como URL inteira.
		if ( '' !== $img && ! preg_match( '#^https?://#i', $img ) && ! empty( $s['images_base_url'] ) ) {
			$img = rtrim( $s['images_base_url'], '/' ) . '/' . ltrim( $img, '/' );
		}
		if ( '' !== $ref && '' !== $img && ! isset( $out[ dcspotstk_norm( $ref ) ] ) ) {
			$out[ dcspotstk_norm( $ref ) ] = $img;
		}
	}
	return $out;
}

/**
 * Deriva o nome de arquivo de imagem direto do SKU, sem precisar de nenhuma
 * busca extra na API. Confirmado navegando o site público da Spot logado:
 * o arquivo é o SKU com o sufixo de tamanho (P/M/G/GG/XG…) removido e os
 * traços trocados por underscore — ex: SKU "99970-103" -> "99970_103.jpg";
 * "30504-102-G" -> "30504_102.jpg". Mais confiável que o ProductsTree, que
 * só cobre uma fração do catálogo.
 */
function dcspotstk_guess_image_filename( $raw_sku ) {
	$sku = trim( (string) $raw_sku );
	if ( '' === $sku ) {
		return '';
	}
	// Remove um sufixo de tamanho no final (PP, P, M, G, GG, GGG, XG, XGG, XXG, U, UN, ÚNICO...).
	$sku = preg_replace( '/-(?:PP|P|M|GGG|GG|G|XXG|XGG|XG|UNICO|UN|U)$/i', '', $sku );
	$sku = str_replace( '-', '_', $sku );
	return $sku . '.jpg';
}

function dcspotstk_build_create_queue( $prod_meta, $stock_index, $images, $site_skus, $s, $site_refs = array() ) {
	$rules   = dcspotstk_parse_rules( $s['cat_rules'] );
	$default = $s['cat_default'] ? $s['cat_default'] : 'Novidades';
	$min     = max( 0, (int) $s['create_min_stock'] );
	$base    = rtrim( (string) ( $s['images_base_url'] ?? '' ), '/' );
	$queue   = array();
	$queued_skus = array();

	foreach ( $prod_meta as $fam_key => $m ) {
		// Verifica tanto pelo SKU atual quanto pela referência Spot original
		// (_dcspot_ref) — a migração por cor RENOMEIA o SKU do produto (de
		// "94393" para "94393-160"), então depois disso o SKU original nunca
		// mais bate em $site_skus e o produto seria recriado do zero a cada
		// sync. A referência (_dcspot_ref) nunca muda, então ela pega esse
		// caso mesmo com o SKU renomeado.
				$norm_sku = dcspotstk_norm( $m['sku'] );
		if ( isset( $site_skus[ $norm_sku ] ) || isset( $site_refs[ $norm_sku ] ) || isset( $queued_skus[ $norm_sku ] ) || '' === $m['name'] ) {
			continue;
		}
				$queued_skus[ $norm_sku ] = true;
		// Soma o estoque de todas as variações (SKUs) desta família.
		$qty = 0;
		foreach ( array_unique( $m['skus'] ) as $sk ) {
			if ( isset( $stock_index[ $sk ] ) ) {
				$qty += (int) $stock_index[ $sk ]['qty'];
			}
		}
		if ( $qty < $min ) {
			continue;
		}
		$m['qty'] = $qty;

		// Prioridade da imagem: primeiro tenta o ProductsTree (se a referência
		// estiver lá); senão deriva do primeiro SKU da família — cobre os
		// casos que o ProductsTree não lista.
		$img = $images[ $fam_key ] ?? '';
		if ( '' === $img && ! empty( $m['skus_raw'] ) && '' !== $base ) {
			$fname = dcspotstk_guess_image_filename( reset( $m['skus_raw'] ) );
			if ( '' !== $fname ) {
				$img = $base . '/' . $fname;
			}
		}
		$m['image'] = $img;
		$m['cat']   = dcspotstk_match_category( $m['name'], $rules, $default );
		$queue[]    = $m;
	}
	return $queue;
}

/**
 * Prévia: monta a fila e as estatísticas SEM tocar na fila/relatório da
 * execução real (DCSPOTSTK_CREATE_QUEUE_OPT / _REPORT_OPT) — fica isolada
 * num transient próprio. Isso evita que uma prévia (sem imagem, mais rápida)
 * seja reaproveitada por engano como se fosse a fila real de criação.
 */
function dcspotstk_preview_create( $s ) {
	$products = dcspotstk_fetch_products( $s );
	if ( is_wp_error( $products ) ) {
		return $products;
	}
	$catalog = dcspotstk_fetch_catalog( $s );
	if ( is_wp_error( $catalog ) ) {
		return $catalog;
	}
	$stock_index = dcspotstk_build_index( $catalog, $s );
	unset( $catalog );
	$prod_meta = dcspotstk_build_product_meta( $products, $s );
	unset( $products );

	global $wpdb;
	$existing  = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value <> ''" );
	$site_skus = array();
	foreach ( $existing as $sku ) {
		$site_skus[ dcspotstk_norm( $sku ) ] = true;
	}
	$existing_refs = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_dcspot_ref' AND meta_value <> ''" );
	$site_refs = array();
	foreach ( $existing_refs as $ref ) {
		$site_refs[ dcspotstk_norm( $ref ) ] = true;
	}

	// Prévia não busca imagem (ProductsTree é pesado e não muda a contagem).
	$queue = dcspotstk_build_create_queue( $prod_meta, $stock_index, array(), $site_skus, $s, $site_refs );

	$by_cat = array();
	foreach ( $queue as $item ) {
		if ( ! isset( $by_cat[ $item['cat'] ] ) ) {
			$by_cat[ $item['cat'] ] = 0;
		}
		$by_cat[ $item['cat'] ]++;
	}
	$preview = array( 'total' => count( $queue ), 'by_cat' => $by_cat, 'when' => current_time( 'mysql' ) );
	set_transient( 'dcspotstk_preview', $preview, HOUR_IN_SECONDS );
	return $preview;
}

function dcspotstk_start_create_run( $s, $skip_images = false ) {
	$products = dcspotstk_fetch_products( $s );
	if ( is_wp_error( $products ) ) {
		return $products;
	}
	$catalog = dcspotstk_fetch_catalog( $s );
	if ( is_wp_error( $catalog ) ) {
		return $catalog;
	}
	$stock_index = dcspotstk_build_index( $catalog, $s );
	unset( $catalog );
	$prod_meta = dcspotstk_build_product_meta( $products, $s );
	unset( $products );
	// Na prévia não precisa de imagem nenhuma — pula essa busca pesada
	// (ProductsTree) pra prévia ficar rápida e não arriscar memória à toa.
	$images = $skip_images ? array() : dcspotstk_fetch_images( $s );

	global $wpdb;
	$existing = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value <> ''" );
	$site_skus = array();
	foreach ( $existing as $sku ) {
		$site_skus[ dcspotstk_norm( $sku ) ] = true;
	}
	$existing_refs = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_dcspot_ref' AND meta_value <> ''" );
	$site_refs = array();
	foreach ( $existing_refs as $ref ) {
		$site_refs[ dcspotstk_norm( $ref ) ] = true;
	}

	$queue = dcspotstk_build_create_queue( $prod_meta, $stock_index, $images, $site_skus, $s, $site_refs );

	update_option( DCSPOTSTK_CREATE_QUEUE_OPT, $queue, false );
	$by_cat = array();
	foreach ( $queue as $item ) {
		if ( ! isset( $by_cat[ $item['cat'] ] ) ) {
			$by_cat[ $item['cat'] ] = 0;
		}
		$by_cat[ $item['cat'] ]++;
	}
	update_option(
		DCSPOTSTK_CREATE_REPORT_OPT,
		array(
			'total'   => count( $queue ),
			'done'    => 0,
			'created' => 0,
			'skipped' => 0,
			'errors'  => 0,
			'by_cat'  => $by_cat,
			'when'    => current_time( 'mysql' ),
			'running' => true,
		),
		false
	);
	return count( $queue );
}

function dcspotstk_drain_create_queue( $budget_seconds = 30 ) {
	$q = get_option( DCSPOTSTK_CREATE_QUEUE_OPT, array() );
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

	$s        = dcspotstk_get_settings();
	$publish  = ! empty( $s['create_publish'] );
	$with_img = ! empty( $s['create_image'] );
	$start    = microtime( true );

	$rep = get_option( DCSPOTSTK_CREATE_REPORT_OPT, array() );
	if ( ! is_array( $rep ) ) {
		$rep = array();
	}
	$rep += array( 'total' => count( $q ), 'done' => 0, 'created' => 0, 'skipped' => 0, 'errors' => 0, 'by_cat' => array(), 'running' => true );

	$done_now = 0;
	// Segunda trava de segurança: além do wc_get_product_id_by_sku (que usa uma
	// tabela de lookup que já vimos falhar em capturar duplicado entre
	// execuções diferentes), confere também direto na tabela de postmeta, e
	// registra todo SKU criado NESTA passada pra nunca repetir no mesmo lote.
	static $claimed_this_pass = array();
	global $wpdb;

	while ( ! empty( $q ) && ( microtime( true ) - $start ) < $budget_seconds ) {
		$c   = array_shift( $q );
		$sku = (string) $c['sku'];
		$sku_norm = dcspotstk_norm( $sku );

		$already_exists = ( '' === $sku )
			|| isset( $claimed_this_pass[ $sku_norm ] )
			|| wc_get_product_id_by_sku( $sku )
			|| (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s", $sku ) ) > 0;

		if ( $already_exists ) {
			$rep['skipped']++;
			$rep['done']++;
			$done_now++;
			continue;
		}
		$claimed_this_pass[ $sku_norm ] = true;

		try {
			$p = new WC_Product_Simple();
			$p->set_name( wp_strip_all_tags( $c['name'] ) );
			$p->set_sku( $sku );
			$p->set_status( $publish ? 'publish' : 'draft' );
			$p->set_catalog_visibility( 'visible' );
			if ( ! empty( $c['desc'] ) ) {
				$p->set_description( wp_kses_post( $c['desc'] ) );
			}

			// Preço fixo em 0 — o catálogo Spot trabalha por orçamento, então
			// isso é o que ativa o botão "Orçar item" (plugin Request a Quote)
			// em vez de mostrar preço de venda direta.
			$p->set_regular_price( '0' );
			$p->set_price( '0' );

			$qty = max( 0, (int) $c['qty'] );
			if ( $qty > 0 ) {
				$p->set_manage_stock( true );
				$p->set_stock_quantity( $qty );
			} else {
				$p->set_manage_stock( false );
			}
			$p->set_stock_status( 'instock' );

			$term_id = dcspotstk_get_or_create_term( $c['cat'] );
			if ( $term_id ) {
				$p->set_category_ids( array( $term_id ) );
			}

			$id = $p->save();


			if ( $id && $with_img && ! empty( $c['image'] ) ) {
				$img_id = media_sideload_image( $c['image'], $id, wp_strip_all_tags( $c['name'] ), 'id' );
				if ( ! is_wp_error( $img_id ) ) {
					set_post_thumbnail( $id, $img_id );
				}
			}
			if ( $id ) {
				update_post_meta( $id, '_dcspot_source', 'spot' );
				update_post_meta( $id, '_dcspot_created', current_time( 'mysql' ) );
				if ( ! empty( $c['ref'] ) ) {
					update_post_meta( $id, '_dcspot_ref', $c['ref'] );
				}
				$rep['created']++;
				if ( ! isset( $rep['by_cat'][ $c['cat'] ] ) ) {
					$rep['by_cat'][ $c['cat'] ] = 0;
				}
			}
		} catch ( Exception $e ) {
			$rep['errors']++;
		}
		$rep['done']++;
		$done_now++;
	}

	update_option( DCSPOTSTK_CREATE_QUEUE_OPT, $q, false );
	$rep['running'] = ! empty( $q );
	update_option( DCSPOTSTK_CREATE_REPORT_OPT, $rep, false );

	return $done_now;
}

/* -------------------------------------------------------------------------
 * WP-CLI
 * ---------------------------------------------------------------------- */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'dcspotstk sync', function ( $args, $assoc ) {
		$live = isset( $assoc['live'] );
		$r    = dcspotstk_run_sync( ! $live );
		WP_CLI::log( print_r( $r, true ) );
	} );
}

/* -------------------------------------------------------------------------
 * Admin UI
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'Sincronização Spot', 'Sincronização Spot', 'manage_woocommerce', 'dc-spot-stock-sync', 'dcspotstk_render_page' );
} );

function dcspotstk_render_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$s = dcspotstk_get_settings();
	$test_result = null;

	if ( isset( $_POST['dcspotstk_action'] ) && check_admin_referer( 'dcspotstk_action' ) ) {
		$s['enabled']         = ! empty( $_POST['enabled'] ) ? 1 : 0;
		$s['dry_run']         = ! empty( $_POST['dry_run'] ) ? 1 : 0;
		$s['frequency']       = sanitize_text_field( wp_unslash( $_POST['frequency'] ?? $s['frequency'] ) );
		$s['base_url']        = untrailingslashit( sanitize_text_field( wp_unslash( $_POST['base_url'] ?? $s['base_url'] ) ) );
		$s['endpoint_path']   = sanitize_text_field( wp_unslash( $_POST['endpoint_path'] ?? $s['endpoint_path'] ) );
		$s['api_key']         = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? $s['api_key'] ) );
		$s['auth_style']      = sanitize_text_field( wp_unslash( $_POST['auth_style'] ?? $s['auth_style'] ) );
		$s['auth_param_name'] = sanitize_text_field( wp_unslash( $_POST['auth_param_name'] ?? $s['auth_param_name'] ) );
		$s['http_method']     = sanitize_text_field( wp_unslash( $_POST['http_method'] ?? $s['http_method'] ) );
		$s['lang']            = sanitize_text_field( wp_unslash( $_POST['lang'] ?? $s['lang'] ) );
		$s['field_list_path'] = sanitize_text_field( wp_unslash( $_POST['field_list_path'] ?? $s['field_list_path'] ) );
		$s['field_code']      = sanitize_text_field( wp_unslash( $_POST['field_code'] ?? $s['field_code'] ) );
		$s['field_name']      = sanitize_text_field( wp_unslash( $_POST['field_name'] ?? $s['field_name'] ) );
		$s['field_stock']     = sanitize_text_field( wp_unslash( $_POST['field_stock'] ?? $s['field_stock'] ) );
		$s['manage_stock']    = ! empty( $_POST['manage_stock'] ) ? 1 : 0;
		$s['set_out_of_stock']= ! empty( $_POST['set_out_of_stock'] ) ? 1 : 0;
		$s['oos_threshold']   = (int) ( $_POST['oos_threshold'] ?? 0 );
		$s['auto_categorize'] = ! empty( $_POST['auto_categorize'] ) ? 1 : 0;
		$s['cat_default']     = sanitize_text_field( wp_unslash( $_POST['cat_default'] ?? $s['cat_default'] ) );
		$s['cat_rules']       = wp_unslash( $_POST['cat_rules'] ?? $s['cat_rules'] );

		// --- Criação de produtos novos ---
		$s['create_new']         = ! empty( $_POST['create_new'] ) ? 1 : 0;
		$s['create_publish']     = ! empty( $_POST['create_publish'] ) ? 1 : 0;
		$s['create_image']       = ! empty( $_POST['create_image'] ) ? 1 : 0;
		$s['create_min_stock']   = (int) ( $_POST['create_min_stock'] ?? $s['create_min_stock'] );
		$s['products_endpoint']  = sanitize_text_field( wp_unslash( $_POST['products_endpoint'] ?? $s['products_endpoint'] ) );
		$s['products_list_path'] = sanitize_text_field( wp_unslash( $_POST['products_list_path'] ?? $s['products_list_path'] ) );
		$s['pfield_sku']         = sanitize_text_field( wp_unslash( $_POST['pfield_sku'] ?? $s['pfield_sku'] ) );
		$s['pfield_ref']         = sanitize_text_field( wp_unslash( $_POST['pfield_ref'] ?? $s['pfield_ref'] ) );
		$s['pfield_name']        = sanitize_text_field( wp_unslash( $_POST['pfield_name'] ?? $s['pfield_name'] ) );
		$s['pfield_desc']        = sanitize_text_field( wp_unslash( $_POST['pfield_desc'] ?? $s['pfield_desc'] ) );
		$s['pfield_image']       = sanitize_text_field( wp_unslash( $_POST['pfield_image'] ?? $s['pfield_image'] ) );
		$s['images_endpoint']    = sanitize_text_field( wp_unslash( $_POST['images_endpoint'] ?? $s['images_endpoint'] ) );
		$s['images_list_path']   = sanitize_text_field( wp_unslash( $_POST['images_list_path'] ?? $s['images_list_path'] ) );
		$s['images_base_url']    = untrailingslashit( sanitize_text_field( wp_unslash( $_POST['images_base_url'] ?? $s['images_base_url'] ) ) );

		update_option( DCSPOTSTK_OPT, $s, false );
		dcspotstk_reschedule();

		if ( 'test' === $_POST['dcspotstk_action'] ) {
			$catalog = dcspotstk_fetch_catalog( $s );
			if ( is_wp_error( $catalog ) ) {
				$test_result = array( 'ok' => false, 'msg' => $catalog->get_error_message() );
			} else {
				$test_result = array( 'ok' => true, 'msg' => 'Conexão OK — ' . count( $catalog ) . ' item(ns) recebido(s).' );
			}
		} elseif ( 'sync_now' === $_POST['dcspotstk_action'] ) {
			dcspotstk_run_sync();
		} elseif ( 'test_products' === $_POST['dcspotstk_action'] ) {
			$products = dcspotstk_fetch_products( $s );
			if ( is_wp_error( $products ) ) {
				$test_result = array( 'ok' => false, 'msg' => $products->get_error_message() );
			} else {
				$test_result = array( 'ok' => true, 'msg' => 'Conexão OK (produtos) — ' . count( $products ) . ' item(ns) recebido(s).' );
			}
		} elseif ( 'preview_create' === $_POST['dcspotstk_action'] ) {
			$preview = dcspotstk_preview_create( $s );
			if ( is_wp_error( $preview ) ) {
				$test_result = array( 'ok' => false, 'msg' => $preview->get_error_message() );
			}
		} elseif ( 'create_now' === $_POST['dcspotstk_action'] ) {
			if ( empty( $s['create_new'] ) ) {
				$test_result = array( 'ok' => false, 'msg' => 'Marque "Criar produtos novos" (chave mestra) antes de rodar — nada foi criado.' );
			} else {
				// Se já existe uma fila de criação REAL em andamento (com imagem),
				// só continua drenando; senão monta agora (buscando imagem de
				// verdade — nunca reaproveita a fila leve da prévia).
				$existing_queue = get_option( DCSPOTSTK_CREATE_QUEUE_OPT, array() );
				if ( empty( $existing_queue ) ) {
					$n = dcspotstk_start_create_run( $s, false );
					if ( is_wp_error( $n ) ) {
						$test_result = array( 'ok' => false, 'msg' => $n->get_error_message() );
					}
				}
				dcspotstk_drain_create_queue( 25 );
			}
		} elseif ( 'test_images' === $_POST['dcspotstk_action'] ) {
			$imgs = dcspotstk_fetch_images_diag( $s );
			if ( is_wp_error( $imgs ) ) {
				$test_result = array( 'ok' => false, 'msg' => $imgs->get_error_message() );
			} else {
				$sample = array_slice( $imgs, 0, 1, true );
				$test_result = array( 'ok' => true, 'msg' => 'Conexão OK (imagens) — ' . count( $imgs ) . ' imagem(ns) mapeada(s) por referência. Exemplo: ' . ( $sample ? key( $sample ) . ' → ' . reset( $sample ) : '(nenhuma)' ) );
			}
		} elseif ( 'cleanup_now' === $_POST['dcspotstk_action'] ) {
			$existing_cleanup = get_option( DCSPOTSTK_CLEANUP_QUEUE_OPT, array() );
			if ( empty( $existing_cleanup ) ) {
				dcspotstk_start_cleanup();
			}
			dcspotstk_drain_cleanup_queue( 25 );
		} elseif ( 'dedup_now' === $_POST['dcspotstk_action'] ) {
			$existing_cleanup = get_option( DCSPOTSTK_CLEANUP_QUEUE_OPT, array() );
			if ( empty( $existing_cleanup ) ) {
				dcspotstk_start_dedup();
			}
			dcspotstk_drain_cleanup_queue( 25 );
		} elseif ( 'pricefix_now' === $_POST['dcspotstk_action'] ) {
			$existing_pricefix = get_option( DCSPOTSTK_PRICEFIX_QUEUE_OPT, array() );
			if ( empty( $existing_pricefix ) ) {
				dcspotstk_start_pricefix();
			}
			dcspotstk_drain_pricefix_queue( 25 );
		}
	}

	$report  = get_option( DCSPOTSTK_REPORT_OPT, array() );
	$creport = get_option( DCSPOTSTK_CREATE_REPORT_OPT, array() );
	$xreport = get_option( DCSPOTSTK_CLEANUP_REPORT_OPT, array() );
	$preport = get_option( DCSPOTSTK_PRICEFIX_REPORT_OPT, array() );
	$preview = get_transient( 'dcspotstk_preview' );
	?>
	<div class="wrap">
		<h1>Sincronização de Estoque — Spot <span style="font-size:13px;font-weight:normal;color:#888;">(v<?php echo esc_html( DCSPOTSTK_VERSION ); ?>)</span></h1>

		<div class="notice notice-warning">
			<p><strong>Antes de ligar de verdade:</strong> preencha "URL base" e confira o mapeamento de campos abaixo, depois clique em <em>Testar conexão</em>. Só ative a sincronização automática depois que o teste mostrar "Conexão OK".</p>
		</div>

		<?php if ( $test_result ) : ?>
			<div class="notice notice-<?php echo $test_result['ok'] ? 'success' : 'error'; ?>">
				<p><?php echo esc_html( $test_result['msg'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( is_array( $preview ) ) : ?>
			<div class="notice notice-info">
				<p><strong>Prévia de criação</strong> (<?php echo esc_html( $preview['when'] ); ?>): <?php echo (int) $preview['total']; ?> produto(s) novo(s) seriam criados. <em>Nada foi gravado ainda.</em></p>
				<?php if ( ! empty( $preview['by_cat'] ) ) : ?>
					<p><strong>Por categoria:</strong>
					<?php
					$by_cat = $preview['by_cat'];
					arsort( $by_cat );
					$parts = array();
					foreach ( $by_cat as $cat => $n ) {
						$parts[] = esc_html( $cat ) . ' (' . (int) $n . ')';
					}
					echo implode( ', ', $parts );
					?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( is_array( $xreport ) && ! empty( $xreport['total'] ) ) : ?>
			<div class="notice notice-<?php echo $xreport['running'] ? 'info' : 'success'; ?>">
				<p>
					<strong>Limpeza (apagando produtos duplicados):</strong>
					<?php echo (int) $xreport['done']; ?> / <?php echo (int) $xreport['total']; ?> processados —
					<?php echo (int) $xreport['deleted']; ?> apagados, <?php echo (int) $xreport['errors']; ?> erros.
					<?php echo $xreport['running'] ? ' <em>Ainda rodando — recarregue a página para continuar.</em>' : ' <strong>Concluído. Pode rodar a prévia e a criação de novo agora.</strong>'; ?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( is_array( $preport ) && ! empty( $preport['total'] ) ) : ?>
			<div class="notice notice-<?php echo $preport['running'] ? 'info' : 'success'; ?>">
				<p>
					<strong>Correção de preço (para 0 / "Orçar item"):</strong>
					<?php echo (int) $preport['done']; ?> / <?php echo (int) $preport['total']; ?> processados —
					<?php echo (int) $preport['fixed']; ?> corrigidos.
					<?php echo $preport['running'] ? ' <em>Ainda rodando — recarregue a página para continuar.</em>' : ' <strong>Concluído.</strong>'; ?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( is_array( $creport ) && ! empty( $creport['total'] ) ) : ?>
			<div class="notice notice-<?php echo $creport['running'] ? 'info' : 'success'; ?>">
				<p>
					<strong>Criação de produtos novos (execução real):</strong>
					<?php echo (int) $creport['done']; ?> / <?php echo (int) $creport['total']; ?> processados —
					<?php echo (int) $creport['created']; ?> criados, <?php echo (int) $creport['skipped']; ?> pulados (já existiam), <?php echo (int) $creport['errors']; ?> erros.
					<?php echo $creport['running'] ? ' <em>Ainda rodando — recarregue a página para continuar, ou clique em "Criar produtos novos" de novo.</em>' : ' <strong>Concluído.</strong>'; ?>
				</p>
				<?php if ( ! empty( $creport['by_cat'] ) ) : ?>
					<p><strong>Por categoria (criados até agora):</strong>
					<?php
					arsort( $creport['by_cat'] );
					$parts = array();
					foreach ( $creport['by_cat'] as $cat => $n ) {
						$parts[] = esc_html( $cat ) . ' (' . (int) $n . ')';
					}
					echo implode( ', ', $parts );
					?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( is_array( $report ) && ! empty( $report['started'] ) ) : ?>
			<div class="notice notice-info">
				<p><strong>Última execução</strong> (<?php echo esc_html( $report['started'] ); ?>, <?php echo $report['dry_run'] ? 'simulação' : 'gravação'; ?>):
				<?php if ( ! empty( $report['message'] ) ) : ?>
					<?php echo esc_html( $report['message'] ); ?>
				<?php else : ?>
					<?php echo (int) $report['spot_items']; ?> item(ns) na Spot, <?php echo (int) $report['matched']; ?> casado(s) por SKU,
					<?php echo (int) $report['changed']; ?> precisariam mudar, <?php echo (int) $report['applied']; ?> aplicado(s),
					<?php echo (int) $report['categorized']; ?> categorizado(s), <?php echo (int) $report['unmatched']; ?> sem correspondência.
				<?php endif; ?>
				</p>
			</div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'dcspotstk_action' ); ?>
			<h2>Conexão com a API da Spot</h2>
			<table class="form-table">
				<tr><th>URL base</th><td><input type="text" name="base_url" value="<?php echo esc_attr( $s['base_url'] ); ?>" class="regular-text" placeholder="https://api.spot.com.br"> <span class="description">Preencher quando tiver a URL confirmada.</span></td></tr>
				<tr><th>Caminho do endpoint</th><td><input type="text" name="endpoint_path" value="<?php echo esc_attr( $s['endpoint_path'] ); ?>" class="regular-text"></td></tr>
				<tr><th>Método HTTP</th><td>
					<select name="http_method">
						<option value="GET" <?php selected( $s['http_method'], 'GET' ); ?>>GET</option>
						<option value="POST" <?php selected( $s['http_method'], 'POST' ); ?>>POST</option>
					</select>
				</td></tr>
				<tr><th>Chave de acesso</th><td><input type="text" name="api_key" value="<?php echo esc_attr( $s['api_key'] ); ?>" class="regular-text"></td></tr>
				<tr><th>Como a chave é enviada</th><td>
					<select name="auth_style">
						<option value="spotgifts" <?php selected( $s['auth_style'], 'spotgifts' ); ?>>Spot Gifts / Stricker (AuthenticateClient → token de sessão)</option>
						<option value="query_key" <?php selected( $s['auth_style'], 'query_key' ); ?>>Parâmetro na URL (?chave=...)</option>
						<option value="bearer" <?php selected( $s['auth_style'], 'bearer' ); ?>>Header Authorization: Bearer</option>
						<option value="header_apikey" <?php selected( $s['auth_style'], 'header_apikey' ); ?>>Header customizado</option>
					</select>
					Nome do parâmetro/header (não usado no modo Spot Gifts): <input type="text" name="auth_param_name" value="<?php echo esc_attr( $s['auth_param_name'] ); ?>" class="small-text">
				</td></tr>
				<tr><th>Idioma (parâmetro "lang")</th><td><input type="text" name="lang" value="<?php echo esc_attr( $s['lang'] ); ?>" class="small-text"> <span class="description">A Spot só aceita "PT" no Brasil.</span></td></tr>
			</table>

			<h2>Formato da resposta</h2>
			<table class="form-table">
				<tr><th>Caminho até a lista de produtos</th><td><input type="text" name="field_list_path" value="<?php echo esc_attr( $s['field_list_path'] ); ?>" class="regular-text" placeholder="ex: data.produtos (deixe em branco se a resposta já é a lista)"></td></tr>
				<tr><th>Campo do código/SKU</th><td><input type="text" name="field_code" value="<?php echo esc_attr( $s['field_code'] ); ?>" class="regular-text"></td></tr>
				<tr><th>Campo do nome</th><td><input type="text" name="field_name" value="<?php echo esc_attr( $s['field_name'] ); ?>" class="regular-text"></td></tr>
				<tr><th>Campo do estoque</th><td><input type="text" name="field_stock" value="<?php echo esc_attr( $s['field_stock'] ); ?>" class="regular-text"></td></tr>
			</table>

			<h2>Regras de estoque</h2>
			<table class="form-table">
				<tr><th>Gerenciar estoque</th><td><label><input type="checkbox" name="manage_stock" <?php checked( $s['manage_stock'] ); ?>> Liga o controle de estoque do produto</label></td></tr>
				<tr><th>Marcar "fora de estoque"</th><td><label><input type="checkbox" name="set_out_of_stock" <?php checked( $s['set_out_of_stock'] ); ?>> Desligado por padrão (mesma cautela do módulo XBZ)</label> — limite: <input type="number" name="oos_threshold" value="<?php echo (int) $s['oos_threshold']; ?>" class="small-text"></td></tr>
			</table>

			<h2>Categorização automática</h2>
			<table class="form-table">
				<tr><th>Categorizar produtos sem categoria</th><td><label><input type="checkbox" name="auto_categorize" <?php checked( $s['auto_categorize'] ); ?>> ao sincronizar, produtos casados por SKU que ainda não têm categoria recebem uma automaticamente</label></td></tr>
				<tr><th>Categoria padrão</th><td><input type="text" name="cat_default" value="<?php echo esc_attr( $s['cat_default'] ); ?>" class="regular-text"></td></tr>
				<tr><th>Regras de categoria</th><td><textarea name="cat_rules" rows="14" class="large-text code"><?php echo esc_textarea( $s['cat_rules'] ); ?></textarea></td></tr>
			</table>

			<h2>Criação de produtos novos</h2>
			<div class="notice notice-warning inline">
				<p>Como a Spot é um catálogo separado, produtos dela não existem ainda no site. Esta seção cria os que estiverem faltando. Clique em <em>Testar produtos</em> primeiro para confirmar os nomes de campo, depois use <em>Prévia de criação</em> (não grava nada) antes de <em>Criar produtos novos</em>.</p>
			</div>
			<p>
				<button type="submit" name="dcspotstk_action" value="cleanup_now" class="button" style="border-color:#b32d2e;color:#b32d2e;" onclick="return confirm('Isso vai apagar PERMANENTEMENTE todos os produtos criados por este plugin (marcados _dcspot_source=spot), inclusive os duplicados. Não afeta produtos antigos do site (XBZ/Asia). Confirma?');">🗑️ Apagar todos os produtos criados pelo plugin (limpar e recomeçar)</button>
			<button type="submit" name="dcspotstk_action" value="dedup_now" class="button" style="border-color:#b8860b;color:#b8860b;" onclick="return confirm('Isso vai apagar os produtos com SKU repetido, mantendo só o mais antigo de cada SKU. Não mexe em produtos sem duplicata. Confirma?');">🧹 Remover SKUs duplicados (mantém o mais antigo)</button>
			<button type="submit" name="dcspotstk_action" value="pricefix_now" class="button" style="border-color:#2271b1;color:#2271b1;">💲 Corrigir preço para 0 nos já criados (ativa "Orçar item")</button>
			</p>
			<table class="form-table">
				<tr><th>Endpoint de produtos</th><td><input type="text" name="products_endpoint" value="<?php echo esc_attr( $s['products_endpoint'] ); ?>" class="regular-text"> <span class="description">Ex: OptionalsComplete (traz nome + descrição + SKU num item só)</span></td></tr>
				<tr><th>Caminho até a lista</th><td><input type="text" name="products_list_path" value="<?php echo esc_attr( $s['products_list_path'] ); ?>" class="regular-text"></td></tr>
				<tr><th>Campo do SKU</th><td><input type="text" name="pfield_sku" value="<?php echo esc_attr( $s['pfield_sku'] ); ?>" class="regular-text"></td></tr>
				<tr><th>Campo da referência (família)</th><td><input type="text" name="pfield_ref" value="<?php echo esc_attr( $s['pfield_ref'] ); ?>" class="regular-text"></td></tr>
				<tr><th>Campo do nome</th><td><input type="text" name="pfield_name" value="<?php echo esc_attr( $s['pfield_name'] ); ?>" class="regular-text"></td></tr>
				<tr><th>Campo da descrição</th><td><input type="text" name="pfield_desc" value="<?php echo esc_attr( $s['pfield_desc'] ); ?>" class="regular-text"></td></tr>
				<tr><th>Campo da imagem principal</th><td><input type="text" name="pfield_image" value="<?php echo esc_attr( $s['pfield_image'] ); ?>" class="regular-text"></td></tr>
				<tr><th>Endpoint de imagens</th><td><input type="text" name="images_endpoint" value="<?php echo esc_attr( $s['images_endpoint'] ); ?>" class="regular-text"> <span class="description">Busca separada, só pra imagem (o endpoint de produtos acima pode não trazer imagem)</span></td></tr>
				<tr><th>Caminho até a lista (imagens)</th><td><input type="text" name="images_list_path" value="<?php echo esc_attr( $s['images_list_path'] ); ?>" class="regular-text"></td></tr>
				<tr><th>URL base das imagens</th><td><input type="text" name="images_base_url" value="<?php echo esc_attr( $s['images_base_url'] ); ?>" class="regular-text"> <span class="description">Colada na frente do nome do arquivo que a API devolve (confirmado: www.spotgifts.com.br/fotos/produtos/)</span></td></tr>
				<tr><th>Criar produtos novos</th><td><label><input type="checkbox" name="create_new" <?php checked( $s['create_new'] ); ?>> chave mestra — sem isso os botões abaixo não gravam nada</label></td></tr>
				<tr><th>Publicar direto</th><td><label><input type="checkbox" name="create_publish" <?php checked( $s['create_publish'] ); ?>> se desmarcado, cria como rascunho para revisão antes</label></td></tr>
				<tr><th>Baixar imagem</th><td><label><input type="checkbox" name="create_image" <?php checked( $s['create_image'] ); ?>> puxa a imagem principal da Spot como imagem destacada</label></td></tr>
				<tr><th>Estoque mínimo para criar</th><td><input type="number" name="create_min_stock" value="<?php echo (int) $s['create_min_stock']; ?>" class="small-text"> <span class="description">Não cria SKU com estoque abaixo disso</span></td></tr>
			</table>
			<p class="submit">
				<button type="submit" name="dcspotstk_action" value="test_products" class="button button-secondary">Testar produtos</button>
				<button type="submit" name="dcspotstk_action" value="test_images" class="button button-secondary">Testar imagens</button>
				<button type="submit" name="dcspotstk_action" value="preview_create" class="button button-secondary">Prévia de criação (não grava nada)</button>
				<button type="submit" name="dcspotstk_action" value="create_now" class="button button-primary" onclick="return confirm('Isso vai criar produtos novos de verdade no site. Confirma?');">Criar produtos novos</button>
			</p>

			<h2>Agendamento</h2>
			<table class="form-table">
				<tr><th>Modo simulação</th><td><label><input type="checkbox" name="dry_run" <?php checked( $s['dry_run'] ); ?>> Não grava nada, só mostra o que mudaria</label></td></tr>
				<tr><th>Ativar sincronização automática</th><td><label><input type="checkbox" name="enabled" <?php checked( $s['enabled'] ); ?>> só ligue depois de testar a conexão</label></td></tr>
				<tr><th>Frequência</th><td>
					<select name="frequency">
						<option value="hourly" <?php selected( $s['frequency'], 'hourly' ); ?>>De hora em hora</option>
						<option value="twicedaily" <?php selected( $s['frequency'], 'twicedaily' ); ?>>2× por dia</option>
						<option value="daily" <?php selected( $s['frequency'], 'daily' ); ?>>1× por dia</option>
					</select>
				</td></tr>
			</table>

			<p class="submit">
				<button type="submit" name="dcspotstk_action" value="test" class="button button-secondary">Testar conexão (estoque)</button>
				<button type="submit" name="dcspotstk_action" value="sync_now" class="button button-primary">Rodar sincronização de estoque agora (respeita o modo simulação acima)</button>
				<button type="submit" name="dcspotstk_action" value="save" class="button">Só salvar</button>
			</p>
		</form>
	</div>
	<?php
}
