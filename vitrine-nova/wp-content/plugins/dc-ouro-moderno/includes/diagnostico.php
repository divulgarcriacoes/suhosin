<?php
/**
 * Diagnostico de erro critico: quando o WordPress mostra "Ha um erro critico", acrescenta a mensagem do erro
 * (arquivo, linha) SO se o endereco tiver ?dcdiag=<codigo>. Sem o codigo, a tela continua igual.
 * Pode apagar este arquivo depois de achar o problema.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'DCOM_DIAG_TOKEN' ) ) {
	define( 'DCOM_DIAG_TOKEN', '9fe0b4279ac6345a8869394c' );
}
add_filter(
	'wp_php_error_message',
	function ( $message, $error ) {
		$t = isset( $_GET['dcdiag'] ) ? (string) $_GET['dcdiag'] : ''; // phpcs:ignore
		if ( '' === $t || ! hash_equals( DCOM_DIAG_TOKEN, $t ) || ! is_array( $error ) ) {
			return $message;
		}
		$msg  = isset( $error['message'] ) ? (string) $error['message'] : '';
		$file = isset( $error['file'] ) ? str_replace( ABSPATH, '', (string) $error['file'] ) : '';
		return $message . '<pre id="dcdiag" style="white-space:pre-wrap;font-size:12px">DCDIAG ' . esc_html( $file . ':' . ( isset( $error['line'] ) ? (int) $error['line'] : 0 ) . "\n" . str_replace( ABSPATH, '', $msg ) ) . '</pre>';
	},
	10,
	2
);
