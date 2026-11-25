<?php
/**
 * Plugin Name: DC · Ouro Moderno (Painel da Matriz e Area do Franqueado)
 * Description: Painel da Matriz (controle da rede de franqueados: no wp-admin para o administrador e em /painel-matriz/ para a Diretoria e a Equipe) e Area do Franqueado (Minha conta), com as funcoes Diretoria Ouro Moderno e Equipe da Matriz. Substitui os arquivos dc-ouro-matriz.php, dcom-matriz.inc, dc-ouro-painel.php e dcom-painel.inc da pasta mu-plugins.
 * Version: 0.4.6
 * Author: Divulgar Criações
 * Text Domain: dc-ouro-moderno
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DCOM_PLUGIN_VERSION', '0.4.6' );

/* Interruptores: defina 'off' no wp-config.php para desligar um modulo sem desativar o plugin. */
if ( ! defined( 'DCMX_MODE' ) ) {
	define( 'DCMX_MODE', 'on' );
}
if ( ! defined( 'DCOMP_MODE' ) ) {
	define( 'DCOMP_MODE', 'on' );
}

/*
 * Os mesmos arquivos ainda podem estar em wp-content/mu-plugins (copia antiga). Carregar os dois daria erro fatal
 * (funcoes repetidas). Se a copia antiga existir, o modulo correspondente do plugin NAO e carregado e um aviso aparece.
 */
function dcom_plugin_mu_antigo( $arquivo ) {
	return defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/' . $arquivo );
}

add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$velhos = array();
		foreach ( array( 'dc-ouro-matriz.php', 'dcom-matriz.inc', 'dc-ouro-painel.php', 'dcom-painel.inc' ) as $f ) {
			if ( dcom_plugin_mu_antigo( $f ) ) {
				$velhos[] = $f;
			}
		}
		if ( $velhos ) {
			echo '<div class="notice notice-warning"><p><strong>DC · Ouro Moderno:</strong> ainda existem arquivos antigos em <code>wp-content/mu-plugins</code> (' . esc_html( implode( ', ', $velhos ) ) . '). Enquanto eles existirem, o plugin n&atilde;o carrega o m&oacute;dulo correspondente. Apague esses arquivos para o plugin assumir.</p></div>'; // phpcs:ignore
		}
	}
);

/*
 * Funcoes da Matriz (mesmo modelo do Alta Conexao: funcoes proprias, so com as permissoes que precisam).
 * dcmx_ver = ver o Painel da Matriz (todas as unidades). dcmx_config = mudar a comissao.
 * Diretoria Ouro Moderno: dcmx_ver + dcmx_config. Equipe da Matriz: so dcmx_ver. Administrador recebe as duas.
 * Roda uma vez (opcao dcmx_roles_v); para refazer, apague a opcao.
 */
add_action(
	'init',
	function () {
		try {
			if ( '2' === (string) get_option( 'dcmx_roles_v' ) ) {
				return;
			}
			remove_role( 'dcmx_diretoria' );
			remove_role( 'dcmx_equipe' );
			add_role( 'dcmx_diretoria', 'Diretoria Ouro Moderno', array( 'read' => true, 'dcmx_ver' => true, 'dcmx_config' => true ) );
			add_role( 'dcmx_equipe', 'Equipe da Matriz', array( 'read' => true, 'dcmx_ver' => true ) );
			$adm = get_role( 'administrator' );
			if ( $adm ) {
				$adm->add_cap( 'dcmx_ver' );
				$adm->add_cap( 'dcmx_config' );
			}
			update_option( 'dcmx_roles_v', '2', false );
		} catch ( \Throwable $e ) {
			error_log( 'dc-ouro-matriz (funcoes): ' . $e->getMessage() ); // phpcs:ignore
		}
	},
	5
);

/* Ao ativar o plugin, as funcoes sao criadas na hora (e refeitas, se a versao mudou). */
register_activation_hook(
	__FILE__,
	function () {
		delete_option( 'dcmx_roles_v' );
	}
);

/* Painel da Matriz: no wp-admin (administrador) e em /painel-matriz/ (Diretoria e Equipe), com o acesso no modelo do Alta Conexao. */
add_action(
	'plugins_loaded',
	function () {
		if ( 'on' !== DCMX_MODE || dcom_plugin_mu_antigo( 'dc-ouro-matriz.php' ) || dcom_plugin_mu_antigo( 'dcom-matriz.inc' ) ) {
			return;
		}
		try {
			require_once __DIR__ . '/includes/matriz.php';
			require_once __DIR__ . '/includes/acesso.php';
		} catch ( \Throwable $e ) {
			error_log( 'dc-ouro-moderno (matriz): ' . $e->getMessage() ); // phpcs:ignore
		}
	},
	5
);

/* Diagnostico de erro critico (so com ?dcdiag=codigo). */
require_once __DIR__ . '/includes/diagnostico.php';

/* Loja do parceiro (giftbrand.com.br/?loja=): preco calculado na hora. Carrega ja, antes do snippet que monta a pagina. */
require_once __DIR__ . '/includes/preco-parceiro.php';

/* Area do Franqueado: Minha conta. */
add_action(
	'plugins_loaded',
	function () {
		if ( 'on' !== DCOMP_MODE || dcom_plugin_mu_antigo( 'dc-ouro-painel.php' ) || dcom_plugin_mu_antigo( 'dcom-painel.inc' ) ) {
			return;
		}
		try {
			require_once __DIR__ . '/includes/painel.php';
		} catch ( \Throwable $e ) {
			error_log( 'dc-ouro-moderno (painel): ' . $e->getMessage() ); // phpcs:ignore
		}
	},
	5
);
