<?php
/**
 * Endereco da franquia: campos no cadastro (CEP, rua, numero, complemento, bairro; cidade/UF ja existem), com o CEP
 * preenchendo o resto (ViaCEP). Fica no cadastro da unidade como endereco de cobranca e de entrega do WooCommerce.
 * No carrinho e no checkout de quem compra pela loja da franquia (ou da propria franquia logada), o endereco de entrega
 * ja vem preenchido com o da franquia, entao o frete aparece direto.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Grava o endereco quando o cadastro da franquia cria o usuario (o snippet #109112 chama wp_insert_user). */
add_action(
	'user_register',
	function ( $uid ) {
		if ( empty( $_POST['omx_cad'] ) || empty( $_POST['omx_cep'] ) ) { // phpcs:ignore
			return;
		}
		$g   = function ( $k ) {
			return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore
		};
		$cid = explode( ' - ', $g( 'cidade' ) );
		$dados = array(
			'postcode'     => preg_replace( '/\D/', '', $g( 'omx_cep' ) ),
			'address_1'    => $g( 'omx_rua' ),
			'number'       => $g( 'omx_numero' ),
			'address_2'    => $g( 'omx_compl' ),
			'neighborhood' => $g( 'omx_bairro' ),
			'city'         => trim( (string) $cid[0] ),
			'state'        => strtoupper( trim( (string) ( isset( $cid[1] ) ? $cid[1] : '' ) ) ),
			'country'      => 'BR',
			'company'      => $g( 'escola' ),
		);
		foreach ( array( 'billing', 'shipping' ) as $tipo ) {
			foreach ( $dados as $k => $v ) {
				if ( '' !== $v ) {
					update_user_meta( $uid, $tipo . '_' . $k, $v );
				}
			}
		}
		$tel = $g( 'telefone' );
		if ( '' !== $tel ) {
			update_user_meta( $uid, 'billing_phone', $tel );
		}
		$nome = $g( 'responsavel' );
		if ( '' !== $nome ) {
			$p = explode( ' ', $nome, 2 );
			update_user_meta( $uid, 'billing_first_name', $p[0] );
			update_user_meta( $uid, 'shipping_first_name', $p[0] );
			if ( isset( $p[1] ) ) {
				update_user_meta( $uid, 'billing_last_name', $p[1] );
				update_user_meta( $uid, 'shipping_last_name', $p[1] );
				// Nome da conta dividido igual (senao o WooCommerce junta "Ana Maria" + "Maria" no checkout).
				update_user_meta( $uid, 'first_name', $p[0] );
				update_user_meta( $uid, 'last_name', $p[1] );
			}
		}
		$cnpj = preg_replace( '/\D/', '', $g( 'cnpj' ) );
		if ( '' !== $cnpj ) {
			update_user_meta( $uid, 'billing_cnpj', $cnpj );
			update_user_meta( $uid, 'billing_persontype', '2' ); // pessoa juridica (nota fiscal com CNPJ)
		}
		$mail = sanitize_email( $g( 'email' ) );
		if ( $mail ) {
			update_user_meta( $uid, 'billing_email', $mail );
		}
	},
	20
);

/* Campos de endereco no passo 2 do cadastro (tela cheia do entrar.php), com mascara do CEP e busca no ViaCEP. */
function dcom_endereco_cadastro_js() {
	return '<script>(function(){var c=document.querySelector(".omc input[name=cidade]");if(!c){return;}var f=c.closest(".f"),g=f?f.parentNode:null;if(!g){return;}'
		. 'function campo(n,l,req,ph,full){var d=document.createElement("div");d.className="f"+(full?" full":"");d.innerHTML="<label>"+l+(req?" <i>*</i>":"")+"</label><input type=\"text\" name=\""+n+"\" placeholder=\""+ph+"\""+(req?" required":"")+">";return d;}'
		. 'var cep=campo("omx_cep","CEP da escola",true,"00000-000",true),rua=campo("omx_rua","Rua / Avenida",true,"Rua das Flores",true),num=campo("omx_numero","Número",true,"123",false),cpl=campo("omx_compl","Complemento",false,"Sala, bloco...",false),bai=campo("omx_bairro","Bairro",true,"Centro",true);'
		. 'var sels=[].filter.call(g.querySelectorAll("select"),function(x){return !x.name;}),se=sels[0]||null,sc=sels[1]||null;'
		. 'g.insertBefore(cep,se?se.closest(".f"):f);var ref=f.nextSibling;[rua,num,cpl,bai].forEach(function(x){g.insertBefore(x,ref);});'
		. 'var ci=cep.querySelector("input");ci.setAttribute("inputmode","numeric");ci.setAttribute("maxlength","9");'
		. 'ci.addEventListener("input",function(){var v=ci.value.replace(/\D/g,"").slice(0,8);ci.value=v.length>5?v.slice(0,5)+"-"+v.slice(5):v;if(v.length!==8){return;}'
		. 'fetch("https://viacep.com.br/ws/"+v+"/json/").then(function(r){return r.json();}).then(function(j){if(!j||j.erro){return;}'
		. 'if(j.logradouro){rua.querySelector("input").value=j.logradouro;}if(j.bairro){bai.querySelector("input").value=j.bairro;}'
		. 'if(se&&j.uf){se.value=j.uf;se.dispatchEvent(new Event("change"));var t=0,iv=setInterval(function(){var cs=[].filter.call(g.querySelectorAll("select"),function(x){return !x.name&&x!==se;})[0];if(cs&&cs.options.length>2){cs.value=j.localidade;cs.dispatchEvent(new Event("change"));clearInterval(iv);}if(++t>40){clearInterval(iv);}},250);}'
		. 'num.querySelector("input").focus();}).catch(function(){});});'
		. '})();</script>';
}

/* Franquia do carrinho: a propria franquia logada, ou a da loja visitada (cookie). */
function dcom_endereco_franquia_atual() {
	if ( is_user_logged_in() && function_exists( 'omx_is_escola' ) && omx_is_escola() ) {
		return function_exists( 'omx_parent' ) ? (int) omx_parent( get_current_user_id() ) : get_current_user_id();
	}
	return function_exists( 'dcom_pedido_franquia_cookie' ) ? dcom_pedido_franquia_cookie() : 0;
}

/* Carrinho e checkout: endereco de entrega da franquia ja preenchido (so quando ainda esta vazio). */
add_action(
	'template_redirect',
	function () {
		if ( ! function_exists( 'is_cart' ) || ! ( is_cart() || is_checkout() ) || ! function_exists( 'WC' ) || ! WC()->customer ) {
			return;
		}
		$fid = dcom_endereco_franquia_atual();
		if ( ! $fid ) {
			return;
		}
		$cep = (string) get_user_meta( $fid, 'shipping_postcode', true );
		if ( '' === $cep ) {
			$cep = (string) get_user_meta( $fid, 'billing_postcode', true );
		}
		$cu = WC()->customer;
		if ( '' === $cep || '' !== (string) $cu->get_shipping_postcode() ) {
			return;
		}
		$m = function ( $k ) use ( $fid ) {
			$v = (string) get_user_meta( $fid, 'shipping_' . $k, true );
			return '' !== $v ? $v : (string) get_user_meta( $fid, 'billing_' . $k, true );
		};
		$cu->set_shipping_country( 'BR' );
		$cu->set_shipping_postcode( $cep );
		$cu->set_shipping_state( $m( 'state' ) );
		$cu->set_shipping_city( $m( 'city' ) );
		$cu->set_shipping_address_1( $m( 'address_1' ) );
		$cu->set_shipping_address_2( $m( 'address_2' ) );
		$cu->set_shipping_company( $m( 'company' ) );
		if ( '' === (string) $cu->get_billing_postcode() ) {
			$cu->set_billing_country( 'BR' );
			$cu->set_billing_postcode( $cep );
			$cu->set_billing_state( $m( 'state' ) );
			$cu->set_billing_city( $m( 'city' ) );
			$cu->set_billing_address_1( $m( 'address_1' ) );
			$cu->set_billing_address_2( $m( 'address_2' ) );
		}
		$cu->set_calculated_shipping( true );
		$cu->save();
	},
	20
);

/* Checkout: CNPJ e razao social da franquia para a nota fiscal (campos do Brazilian Market), quando vazios. */
add_filter(
	'woocommerce_checkout_get_value',
	function ( $v, $campo ) {
		if ( null !== $v && '' !== $v ) {
			return $v;
		}
		$fid = dcom_endereco_franquia_atual();
		if ( ! $fid ) {
			return $v;
		}
		$map = array( 'billing_cnpj' => 'billing_cnpj', 'billing_persontype' => 'billing_persontype', 'billing_company' => 'billing_company', 'billing_number' => 'billing_number', 'billing_neighborhood' => 'billing_neighborhood', 'shipping_number' => 'shipping_number', 'shipping_neighborhood' => 'shipping_neighborhood' );
		if ( isset( $map[ $campo ] ) ) {
			$x = (string) get_user_meta( $fid, $map[ $campo ], true );
			if ( '' === $x && 'billing_cnpj' === $campo ) {
				$x = preg_replace( '/\D/', '', (string) get_user_meta( $fid, 'omx_cnpj', true ) );
			}
			if ( '' === $x && 'billing_company' === $campo ) {
				$x = (string) get_user_meta( $fid, 'omx_escola', true );
			}
			return '' !== $x ? $x : $v;
		}
		return $v;
	},
	20,
	2
);
