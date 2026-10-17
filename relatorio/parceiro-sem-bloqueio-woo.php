// Parceiro (vendedor) pode abrir o Painel Comercial no wp-admin sem o WooCommerce mandar para Minha conta.
add_filter( 'woocommerce_prevent_admin_access', function ( $prevent ) {
	$u = wp_get_current_user();
	if ( $u && $u->exists() && user_can( $u, 'dc_view_own_quotes' ) ) {
		return false;
	}
	return $prevent;
} );
