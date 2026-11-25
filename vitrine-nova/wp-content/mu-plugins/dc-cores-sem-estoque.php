<?php
/**
 * Plugin Name: DC - Esconde cores sem estoque
 * Description: Nas "Cores disponíveis" do produto e nas bolinhas da vitrine, mostra só as cores que têm estoque no fornecedor.
 */
add_action(
	'wp_head',
	function () {
		echo '<style id="dc-cores-sem-estoque">.dcc-chip.off,.dcv-cores i.out{display:none!important}</style>';
	},
	99
);
