<?php
/**
 * Tela ENTRAR do Ouro Moderno (/my-account-2/entrar-escola/) no modelo do Grupo Alta Linha:
 * tela cheia com a imagem de fundo, sem menu nem rodape do site, logo em cima e cartao central,
 * nas cores do Ouro Moderno (azul e amarelo), com o botao Mostrar senha.
 * O formulario e o mesmo login do WooCommerce (mesmo nonce, mesmos campos): o login da escola vai para o painel
 * da escola e o da Diretoria/Equipe vai para o Painel da Matriz (includes/acesso.php).
 * Desligar: define( 'DCOM_ENTRAR_MODE', 'off' ) no wp-config.php (a tela antiga do shortcode volta).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dcom_entrar_path() {
	$acc = function_exists( 'wc_get_page_permalink' ) ? (string) wp_parse_url( wc_get_page_permalink( 'myaccount' ), PHP_URL_PATH ) : '/my-account-2/';
	return untrailingslashit( $acc ) . '/entrar-escola';
}

function dcom_entrar_bg() {
	if ( function_exists( 'dcpt_cur_or_page' ) && function_exists( 'dcpt_img' ) ) {
		$c = dcpt_cur_or_page();
		if ( $c && ! empty( $c['bg'] ) ) {
			return (string) dcpt_img( $c['bg'] );
		}
	}
	return 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/11/FUNDON.jpg'; // foto das telas Entrar/Cadastrar (midia 120231)
}

/* Quem ja esta logado e abre Entrar/Cadastrar vai direto ao painel: Matriz -> /painel-matriz/, escola e demais -> Minha conta. */
function dcom_ir_para_painel() {
	$u   = wp_get_current_user();
	$dst = ( function_exists( 'dcmx_so_matriz' ) && dcmx_so_matriz( $u ) ) ? home_url( '/painel-matriz/' ) : wc_get_page_permalink( 'myaccount' );
	wp_safe_redirect( $dst );
	exit;
}

/* Rodape das telas cheias. */
function dcom_tela_credito() {
	return '<footer class="om-ft">Ouro Moderno &mdash; Desenvolvido por <a href="https://giftbrand.com.br" target="_blank" rel="noopener">Giftbrand</a></footer>';
}

/* Fundo (foto + camada azul) das telas cheias. */
function dcom_tela_fundo() {
	$bg = dcom_entrar_bg();
	return '' !== $bg
		? 'background:linear-gradient(rgba(5,18,44,.72),rgba(5,18,44,.80)),url(' . esc_url( $bg ) . ') center/cover no-repeat fixed'
		: 'background:linear-gradient(120deg,#07193a,#0B2A5B 55%,#0A3F8F)';
}

add_action(
	'template_redirect',
	function () {
		if ( defined( 'DCOM_ENTRAR_MODE' ) && 'off' === DCOM_ENTRAR_MODE ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore
		$path = untrailingslashit( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
		if ( dcom_entrar_path() !== $path || ! function_exists( 'wc_get_page_permalink' ) ) {
			return;
		}
		if ( is_user_logged_in() ) {
			dcom_ir_para_painel();
		}

		$acc     = wc_get_page_permalink( 'myaccount' );
		$cad     = home_url( '/my-account-2/cadastro-escola/' );
		$destino = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'escola-painel' ) : $acc;
		$logo    = 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/11/original-1.webp';

		ob_start();
		if ( function_exists( 'wc_print_notices' ) ) {
			wc_print_notices();
		}
		$avisos = ob_get_clean();

		$fundo = dcom_tela_fundo();

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Entrar · Ouro Moderno</title>'
			. '<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
			. '<style>'
			. '*{box-sizing:border-box}html,body{margin:0;padding:0;min-height:100%}'
			. 'body{min-height:100vh;' . $fundo . ';font-family:Poppins,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:#fff;display:flex;flex-direction:column}'
			. '.om-w{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:6vh 16px 28px}'
			. '.om-lg{display:block;width:300px;max-width:78vw;height:auto;margin:0 auto 26px}'
			. '.om-c{width:440px;max-width:100%;background:rgba(7,25,58,.80);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);border:1px solid rgba(245,180,0,.55);border-radius:26px;box-shadow:0 0 0 1px rgba(245,180,0,.12),0 24px 70px rgba(0,0,0,.55),0 0 40px rgba(245,180,0,.12);padding:34px 34px 28px}'
			. '.om-c h1{margin:0 0 8px;font-size:34px;line-height:1.1;font-weight:800;color:#fff}'
			. '.om-c .sub{margin:0 0 22px;color:#D3DCE9;font-size:15px}'
			. '.om-f{margin:0 0 16px}.om-f label{display:block;font-weight:600;font-size:14px;margin:0 0 7px;color:#fff}'
			. '.om-in{position:relative}.om-in input{width:100%;height:50px;background:rgba(255,255,255,.07);border:1px solid rgba(245,180,0,.45);border-radius:12px;color:#fff;padding:0 14px;font-size:16px;font-family:inherit;outline:0}'
			. '.om-in input:focus{border-color:#F5B400;box-shadow:0 0 0 3px rgba(245,180,0,.2)}.om-in input.pw{padding-right:92px}'
			. '.om-ver{position:absolute;right:9px;top:50%;transform:translateY(-50%);background:rgba(245,180,0,.12);color:#F5B400;border:1px solid rgba(245,180,0,.7);border-radius:8px;font:600 12px Poppins,system-ui,sans-serif;padding:5px 10px;cursor:pointer}.om-ver:hover{background:rgba(245,180,0,.22)}'
			. '.om-ck{display:flex;align-items:center;gap:8px;font-size:14px;color:#D3DCE9;margin:2px 0 18px;cursor:pointer}.om-ck input{accent-color:#F5B400;width:16px;height:16px}'
			. '.om-bt{display:block;width:100%;height:54px;border:0;border-radius:14px;background:linear-gradient(180deg,#FFC933 0%,#F5B400 100%);color:#0B2A5B;font:700 17px Poppins,system-ui,sans-serif;cursor:pointer;box-shadow:0 8px 24px rgba(245,180,0,.35)}.om-bt:hover{filter:brightness(1.06)}'
			. '.om-lk{margin:18px 0 0;text-align:center;font-size:14px;color:#D3DCE9}.om-lk a{color:#F5B400;font-weight:600}'
			. '.om-c .woocommerce-error,.om-c .woocommerce-message,.om-c .woocommerce-info{list-style:none;margin:0 0 16px;padding:12px 14px;border-radius:12px;background:rgba(7,25,58,.9);border:1px solid rgba(245,180,0,.55);border-left:4px solid #F5B400;color:#fff;font-size:14px}.om-c .woocommerce-error li{margin:0}.om-c .woocommerce-error a,.om-c .woocommerce-message a{color:#F5B400}'
			. '.om-ft{text-align:center;font-size:12.5px;color:#BFCBE0;padding:14px 12px 18px}.om-ft a{color:#F5B400;font-weight:600;text-decoration:none}'
			. '@media(max-width:520px){.om-c{padding:26px 20px 22px}.om-c h1{font-size:29px}}'
			. '</style></head><body>'
			. '<main class="om-w"><a href="' . esc_url( home_url( '/' ) ) . '"><img class="om-lg" src="' . esc_url( $logo ) . '" alt="Ouro Moderno" width="1013" height="171"></a>'
			. '<div class="om-c"><h1>Entrar</h1><p class="sub">Acesse a &Aacute;rea da Escola Ouro Moderno.</p>' . $avisos // phpcs:ignore
			. '<form method="post" action="' . esc_url( $acc ) . '">' . wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce', true, false ) // phpcs:ignore
			. '<input type="hidden" name="redirect" value="' . esc_url( $destino ) . '">'
			. '<div class="om-f"><label for="om-u">E-mail</label><div class="om-in"><input id="om-u" type="text" name="username" required autocomplete="username"></div></div>'
			. '<div class="om-f"><label for="om-p">Senha</label><div class="om-in"><input id="om-p" class="pw" type="password" name="password" required autocomplete="current-password"><button type="button" class="om-ver" aria-controls="om-p" aria-pressed="false">Mostrar</button></div></div>'
			. '<label class="om-ck"><input type="checkbox" name="rememberme" value="forever">Lembrar-me</label>'
			. '<button type="submit" name="login" value="Acessar" class="om-bt">Entrar</button>'
			. '<p class="om-lk">Ainda n&atilde;o tem cadastro? <a href="' . esc_url( $cad ) . '">Cadastre sua escola</a> &middot; <a href="' . esc_url( wc_lostpassword_url() ) . '">Esqueci a senha</a></p>'
			. '</form></div></main>'
			. dcom_tela_credito()
			. '<script>(function(){var b=document.querySelector(".om-ver"),p=document.getElementById("om-p");if(!b||!p){return;}b.addEventListener("click",function(){var v=p.type==="password";p.type=v?"text":"password";b.textContent=v?"Ocultar":"Mostrar";b.setAttribute("aria-pressed",v?"true":"false");p.focus();});})();</script>'
			. '</body></html>';
		exit;
	},
	0
);

/*
 * Tela CADASTRAR (/my-account-2/cadastro-escola/): o mesmo formulario de 3 etapas do shortcode [om_cadastro_escola]
 * (o envio continua sendo tratado pelo omx_handle_actions do snippet #109112), em tela cheia: sem menu nem rodape do site,
 * foto de fundo ocupando toda a tela.
 */
add_action(
	'template_redirect',
	function () {
		if ( defined( 'DCOM_ENTRAR_MODE' ) && 'off' === DCOM_ENTRAR_MODE ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore
		$path = untrailingslashit( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
		$cad  = untrailingslashit( substr( dcom_entrar_path(), 0, -strlen( '/entrar-escola' ) ) ) . '/cadastro-escola';
		if ( $cad !== $path || ! shortcode_exists( 'om_cadastro_escola' ) ) {
			return;
		}
		if ( is_user_logged_in() ) {
			dcom_ir_para_painel();
		}
		$html = do_shortcode( '[om_cadastro_escola]' );
		$logo = 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/11/original-1.webp';
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Cadastre sua escola · Ouro Moderno</title>'
			. '<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
			. '<style>*{box-sizing:border-box}html,body{margin:0;padding:0;min-height:100%}'
			. 'body{min-height:100vh;' . dcom_tela_fundo() . ';font-family:Poppins,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:#fff;display:flex;flex-direction:column}'
			. '.om-w{flex:1;display:flex;flex-direction:column;justify-content:center;padding:4vh 0 10px}'
			. '.om-lg{display:block;width:300px;max-width:78vw;height:auto;margin:0 auto 6px}'
			. '.omc{background:none!important;width:100%!important;margin:0!important;padding:28px 20px 34px!important}'
			. '.omc h1,.omc .card h2{font-family:Poppins,system-ui,sans-serif!important}'
			. '.om-ft{text-align:center;font-size:12.5px;color:#BFCBE0;padding:14px 12px 18px}.om-ft a{color:#F5B400;font-weight:600;text-decoration:none}'
			. '.om-pw{position:relative;display:block;width:100%}.omc .om-pw input[type=password],.omc .om-pw input[type=text]{padding-right:88px!important}.om-ver{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:#FFF6DB;color:#0B2A5B;border:1px solid #F5B400;border-radius:8px;font:600 12px Poppins,system-ui,sans-serif;padding:5px 10px;cursor:pointer;line-height:1.2}.om-ver:hover{background:#FFEBB0}'
			. '</style></head><body><main class="om-w"><a href="' . esc_url( home_url( '/' ) ) . '"><img class="om-lg" src="' . esc_url( $logo ) . '" alt="Ouro Moderno" width="1013" height="171"></a>'
			. $html // phpcs:ignore
			. '</main>' . dcom_tela_credito()
			. '<script>(function(){var c=document.querySelector(".omc input[name=cidade]");if(!c){return;}var f=c.closest(".f");if(!f){return;}'
			. 'var U=[["AC","Acre"],["AL","Alagoas"],["AP","Amapá"],["AM","Amazonas"],["BA","Bahia"],["CE","Ceará"],["DF","Distrito Federal"],["ES","Espírito Santo"],["GO","Goiás"],["MA","Maranhão"],["MT","Mato Grosso"],["MS","Mato Grosso do Sul"],["MG","Minas Gerais"],["PA","Pará"],["PB","Paraíba"],["PR","Paraná"],["PE","Pernambuco"],["PI","Piauí"],["RJ","Rio de Janeiro"],["RN","Rio Grande do Norte"],["RS","Rio Grande do Sul"],["RO","Rondônia"],["RR","Roraima"],["SC","Santa Catarina"],["SP","São Paulo"],["SE","Sergipe"],["TO","Tocantins"]];'
			. 'var ini=(c.value||"").split(" - "),iniC=ini[0]||"",iniU=(ini[1]||"").toUpperCase();'
			. 'var fe=document.createElement("div");fe.className="f";fe.innerHTML="<label>Estado <i>*</i></label>";var se=document.createElement("select");se.required=true;se.innerHTML="<option value=\"\">Selecione o estado</option>"+U.map(function(u){return "<option value=\""+u[0]+"\">"+u[1]+" ("+u[0]+")</option>";}).join("");fe.appendChild(se);f.parentNode.insertBefore(fe,f);'
			. 'var sc=document.createElement("select");sc.required=true;sc.disabled=true;sc.innerHTML="<option value=\"\">Escolha o estado primeiro</option>";c.type="hidden";c.required=false;c.parentNode.insertBefore(sc,c);'
			. 'function sync(){c.value=(sc.value&&se.value)?sc.value+" - "+se.value:"";}'
			. 'function txt(){sc.remove();var t=document.createElement("input");t.type="text";t.required=true;t.placeholder="Digite a cidade";t.value=iniC;c.parentNode.insertBefore(t,c);t.addEventListener("input",function(){c.value=t.value&&se.value?t.value+" - "+se.value:"";});sc=t;}'
			. 'function load(uf,pre){sc.disabled=true;sc.innerHTML="<option value=\"\">Carregando cidades...</option>";fetch("https://servicodados.ibge.gov.br/api/v1/localidades/estados/"+uf+"/municipios?orderBy=nome").then(function(r){if(!r.ok){throw 0;}return r.json();}).then(function(l){sc.innerHTML="<option value=\"\">Selecione a cidade</option>"+l.map(function(m){return "<option>"+m.nome.replace(/&/g,"&amp;").replace(/</g,"&lt;")+"</option>";}).join("");sc.disabled=false;if(pre){sc.value=pre;}sync();}).catch(function(){if(sc.tagName==="SELECT"){txt();}});}'
			. 'se.addEventListener("change",function(){c.value="";if(sc.tagName==="SELECT"){if(se.value){load(se.value,"");}else{sc.disabled=true;sc.innerHTML="<option value=\"\">Escolha o estado primeiro</option>";}}else{sc.dispatchEvent(new Event("input"));}});'
			. 'sc.addEventListener("change",sync);'
			. 'if(iniU){se.value=iniU;if(se.value){load(iniU,iniC);}}'
			. '})();</script>'
			. '<script>(function(){function d(v){return (v||"").replace(/\D/g,"");}'
			. 'function cnpj(v){v=d(v).slice(0,14);var o="";for(var i=0;i<v.length;i++){if(i===2||i===5){o+=".";}else if(i===8){o+="/";}else if(i===12){o+="-";}o+=v[i];}return o;}'
			. 'function tel(v){v=d(v).slice(0,11);if(!v){return "";}var o="("+v.slice(0,2);if(v.length>=2){o+=") ";}var r=v.slice(2);if(r.length>8){o+=r.slice(0,5)+"-"+r.slice(5);}else if(r.length>4){o+=r.slice(0,4)+"-"+r.slice(4);}else{o+=r;}return o;}'
			. 'function liga(sel,fn,max,ph,ok){var e=document.querySelector(sel);if(!e){return;}e.setAttribute("inputmode","numeric");e.setAttribute("maxlength",max);e.setAttribute("placeholder",ph);e.setAttribute("autocomplete","off");'
			. 'var ap=function(){var p=e.value.length-e.selectionStart;e.value=fn(e.value);var n=Math.max(0,e.value.length-p);try{e.setSelectionRange(n,n);}catch(x){}};'
			. 'e.addEventListener("input",ap);e.addEventListener("blur",ap);if(e.value){e.value=fn(e.value);}}'
			. 'liga(".omc input[name=cnpj]",cnpj,18,"00.000.000/0000-00",function(x){return x.length===14;});'
			. 'liga(".omc input[name=telefone]",tel,15,"(11) 99999-9999",function(x){return x.length===10||x.length===11;});'
			. 'var r=document.querySelector(".omc");if(r){r.addEventListener("click",function(ev){var t=ev.target;if(!t.classList||!t.classList.contains("nx")){return;}var st=t.closest(".stp");if(!st){return;}'
			. 'var c=st.querySelector("input[name=cnpj]"),w=st.querySelector("input[name=telefone]"),m=r.querySelector(".msg"),er="",f=null;'
			. 'if(w&&w.value&&!(d(w.value).length===10||d(w.value).length===11)){er="WhatsApp incompleto: use (11) 99999-9999.";f=w;}'
			. 'else if(c&&c.value&&d(c.value).length!==14){er="CNPJ incompleto: use 00.000.000/0000-00 (ou deixe em branco).";f=c;}'
			. 'if(er){ev.stopImmediatePropagation();ev.preventDefault();if(m){m.textContent=er;}f.focus();}},true);}'
			. '})();</script>'
			. ( function_exists( 'dcom_endereco_cadastro_js' ) ? dcom_endereco_cadastro_js() : '' )
			. '<script>(function(){var l=document.querySelectorAll(".omc input[type=password]");Array.prototype.forEach.call(l,function(p){var w=document.createElement("span");w.className="om-pw";p.parentNode.insertBefore(w,p);w.appendChild(p);var b=document.createElement("button");b.type="button";b.className="om-ver";b.textContent="Mostrar";b.setAttribute("aria-pressed","false");w.appendChild(b);b.addEventListener("click",function(){var v=p.type==="password";p.type=v?"text":"password";b.textContent=v?"Ocultar":"Mostrar";b.setAttribute("aria-pressed",v?"true":"false");p.focus();});});})();</script>'
			. '</body></html>'; // phpcs:ignore
		exit;
	},
	0
);

/*
 * Tela PARABENS depois do cadastro: o cadastro (omx_handle_actions) ja entra com a conta nova e manda para
 * Minha conta > escola-painel com ?omx_msg=ok-cadastro. Aqui essa chegada vira uma tela cheia de parabens,
 * com o botao Entrar na loja (e o link para o painel da escola).
 */
add_action(
	'template_redirect',
	function () {
		if ( defined( 'DCOM_ENTRAR_MODE' ) && 'off' === DCOM_ENTRAR_MODE ) {
			return;
		}
		if ( ! isset( $_GET['omx_msg'] ) || 'ok-cadastro' !== sanitize_key( wp_unslash( $_GET['omx_msg'] ) ) ) { // phpcs:ignore
			return;
		}
		if ( ! is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		$u      = wp_get_current_user();
		$escola = (string) get_user_meta( $u->ID, 'omx_escola', true );
		$nome   = trim( (string) $u->first_name );
		if ( '' === $nome ) {
			$nome = trim( strtok( (string) $u->display_name, ' ' ) );
		}
		$loja   = 'https://ouromoderno.giftbrand.com.br/';
		$painel = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'escola-painel' ) : home_url( '/' );
		$logo   = 'https://divulgarcriacoes.com.br/wp-content/uploads/2026/11/original-1.webp';
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Cadastro feito · Ouro Moderno</title>'
			. '<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
			. '<style>*{box-sizing:border-box}html,body{margin:0;padding:0;min-height:100%}'
			. 'body{min-height:100vh;' . dcom_tela_fundo() . ';font-family:Poppins,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:#fff;display:flex;flex-direction:column}'
			. '.om-w{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:6vh 16px 28px}'
			. '.om-lg{display:block;width:300px;max-width:78vw;height:auto;margin:0 auto 26px}'
			. '.om-c{width:520px;max-width:100%;text-align:center;background:rgba(7,25,58,.80);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);border:1px solid rgba(245,180,0,.55);border-radius:26px;box-shadow:0 0 0 1px rgba(245,180,0,.12),0 24px 70px rgba(0,0,0,.55),0 0 40px rgba(245,180,0,.12);padding:38px 34px 32px}'
			. '.om-ok{width:84px;height:84px;margin:0 auto 18px;border-radius:50%;background:linear-gradient(180deg,#FFC933,#F5B400);color:#0B2A5B;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 30px rgba(245,180,0,.4)}.om-ok svg{width:44px;height:44px}'
			. '.om-c h1{margin:0 0 10px;font-size:34px;line-height:1.15;font-weight:800;color:#fff}.om-c h1 span{color:#F5B400}'
			. '.om-c p{margin:0 0 12px;color:#D3DCE9;font-size:15.5px;line-height:1.55}.om-c p b{color:#fff}'
			. '.om-bt{display:block;width:100%;margin:22px 0 0;padding:16px;border-radius:14px;background:linear-gradient(180deg,#FFC933 0%,#F5B400 100%);color:#0B2A5B;font:700 17px Poppins,system-ui,sans-serif;text-decoration:none;box-shadow:0 8px 24px rgba(245,180,0,.35)}.om-bt:hover{filter:brightness(1.06)}'
			. '.om-lk{display:inline-block;margin-top:16px;color:#F5B400;font-weight:600;font-size:14px}'
			. '.om-ft{text-align:center;font-size:12.5px;color:#BFCBE0;padding:14px 12px 18px}.om-ft a{color:#F5B400;font-weight:600;text-decoration:none}'
			. '@media(max-width:520px){.om-c{padding:30px 20px 26px}.om-c h1{font-size:28px}}'
			. '</style></head><body><main class="om-w"><a href="' . esc_url( $loja ) . '"><img class="om-lg" src="' . esc_url( $logo ) . '" alt="Ouro Moderno" width="1013" height="171"></a>'
			. '<div class="om-c"><div class="om-ok"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>'
			. '<h1>Parab&eacute;ns' . ( '' !== $nome ? ', <span>' . esc_html( $nome ) . '</span>' : '' ) . '!</h1>'
			. '<p>O cadastro ' . ( '' !== $escola ? 'da <b>' . esc_html( $escola ) . '</b> ' : 'da sua escola ' ) . 'foi feito com sucesso e voc&ecirc; j&aacute; est&aacute; conectado.</p>'
			. ( 'aprovada' === (string) get_user_meta( $u->ID, 'omx_status', true )
				? '<p>Sua loja j&aacute; est&aacute; liberada. Escolha os brindes da sua escola.</p><a class="om-bt" href="' . esc_url( $loja ) . '">Entrar na loja</a><a class="om-lk" href="' . esc_url( $painel ) . '">Ir para o painel da escola &rsaquo;</a>'
				: '<p>Agora o cadastro passa pela <b>an&aacute;lise</b> da nossa equipe. A <b>loja</b> &eacute; liberada assim que ele for aprovado.</p><a class="om-bt" href="' . esc_url( $painel ) . '">Ir para o meu painel</a>' )
			. '</div></main>'
			. dcom_tela_credito() . '</body></html>'; // phpcs:ignore
		exit;
	},
	0
);

/*
 * O cadastro e as acoes da escola (snippet #109112) rodam antes do endereco da loja ser ajustado, entao mandam para
 * divulgarcriacoes.com.br/my-account-2/... - onde a pessoa nao esta logada (o login vale so no endereco da loja).
 * Aqui esse redirecionamento continua no endereco em que a pessoa esta (ex.: ouromoderno.giftbrand.com.br).
 */
add_filter(
	'wp_redirect',
	function ( $location ) {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( preg_replace( '/:\d+$/', '', (string) wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : ''; // phpcs:ignore
		if ( '' === $host || ! preg_match( '/(^|\.)giftbrand\.com\.br$/', $host ) ) {
			return $location;
		}
		$p = wp_parse_url( (string) $location );
		if ( empty( $p['host'] ) || strtolower( $p['host'] ) === $host || ! in_array( strtolower( $p['host'] ), array( 'divulgarcriacoes.com.br', 'www.divulgarcriacoes.com.br' ), true ) ) {
			return $location;
		}
		$path = isset( $p['path'] ) ? (string) $p['path'] : '/';
		if ( 0 !== strpos( $path, '/my-account-2/' ) ) {
			return $location;
		}
		return 'https://' . $host . $path . ( isset( $p['query'] ) ? '?' . $p['query'] : '' ) . ( isset( $p['fragment'] ) ? '#' . $p['fragment'] : '' );
	},
	99
);
