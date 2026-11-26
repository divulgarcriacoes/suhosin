<?php
/**
 * Loja e categorias: sem paginação. Os produtos vão entrando sozinhos conforme a pessoa rola a página.
 * Esconde os números de página e, perto do fim da lista, busca a página seguinte e acrescenta os produtos.
 * Funciona com os filtros da loja (quando o filtro troca a lista, a paginação nova passa a valer).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dcrl_ativo() {
	return function_exists( 'is_shop' ) && ! is_admin() && ( is_shop() || is_product_taxonomy() );
}

add_action(
	'wp_head',
	function () {
		if ( ! dcrl_ativo() ) {
			return;
		}
		echo '<style id="dcrl-css">.woocommerce-pagination{display:none!important}.dcrl-mais{text-align:center;padding:26px 0;color:#8a8a84;font:600 14px Poppins,Arial,sans-serif;min-height:24px}'
			. '.dcrl-mais.on:before{content:"";display:inline-block;width:18px;height:18px;margin-right:10px;vertical-align:-4px;border:3px solid #e4e4e0;border-top-color:#f5821f;border-radius:50%;animation:dcrlg .8s linear infinite}@keyframes dcrlg{to{transform:rotate(360deg)}}</style>';
	},
	131
);

add_action(
	'wp_footer',
	function () {
		if ( ! dcrl_ativo() ) {
			return;
		}
		?>
<script id="dcrl-js">(function(){var busy=false,fim=null;
function lista(){return document.querySelector('ul.products');}
function prox(){var a=document.querySelector('.woocommerce-pagination a.next');return a?a.href:null;}
function barra(){var u=lista();if(!u)return null;var b=document.getElementById('dcrl-mais');if(!b){b=document.createElement('div');b.id='dcrl-mais';b.className='dcrl-mais';u.parentNode.insertBefore(b,u.nextSibling);}return b;}
function carregar(){var url=prox(),u=lista();if(busy||!url||!u)return;busy=true;var b=barra();if(b){b.className='dcrl-mais on';b.textContent='Carregando mais produtos';}
fetch(url,{credentials:'same-origin'}).then(function(r){return r.text();}).then(function(t){var d=new DOMParser().parseFromString(t,'text/html');var n=d.querySelector('ul.products');var ja={};u.querySelectorAll('li.product').forEach(function(li){var m=(li.className||'').match(/post-(\d+)/);if(m)ja[m[1]]=1;});
if(n){n.querySelectorAll('li.product').forEach(function(li){var m=(li.className||'').match(/post-(\d+)/);if(m&&ja[m[1]])return;u.appendChild(document.importNode(li,true));});}
var pn=d.querySelector('.woocommerce-pagination'),po=document.querySelector('.woocommerce-pagination');if(po){if(pn){po.innerHTML=pn.innerHTML;}else{po.innerHTML='';}}
busy=false;if(b){b.className='dcrl-mais';b.textContent='';}setTimeout(ver,200);}).catch(function(){busy=false;if(b){b.className='dcrl-mais';b.textContent='';}});}
function ver(){var u=lista();if(!u||busy||!prox())return;var r=u.getBoundingClientRect();if(r.bottom-window.innerHeight<1400){carregar();}}
var tm=0;function agenda(){if(tm)return;tm=setTimeout(function(){tm=0;ver();},120);}
window.addEventListener('scroll',agenda,{passive:true});window.addEventListener('resize',agenda);
new MutationObserver(agenda).observe(document.body,{childList:true,subtree:true});
if(document.readyState!=='loading'){setTimeout(ver,600);}else{document.addEventListener('DOMContentLoaded',function(){setTimeout(ver,600);});}
})();</script>
		<?php
	},
	99
);
