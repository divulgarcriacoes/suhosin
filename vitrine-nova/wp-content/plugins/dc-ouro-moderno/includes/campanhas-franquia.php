<?php
/**
 * Campanhas da Rede e Materiais e Artes (dados) + aba "Marketing e Campanhas" da Area do Franqueado
 * (/my-account-2/escola-campanhas/) e banner da campanha no ar na tela inicial da escola.
 * A Matriz cadastra em Painel da Matriz > Campanhas da Rede / Materiais e Artes (includes/matriz-telas.php).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'dcom_campanhas' ) ) {
	function dcom_campanhas() {
		$c = get_option( 'dcom_campanhas' );
		return is_array( $c ) ? $c : array();
	}

	function dcom_materiais() {
		$m = get_option( 'dcom_materiais' );
		return is_array( $m ) ? $m : array();
	}

	/* Campanhas no ar hoje (ativas e dentro das datas), mais novas primeiro. */
	function dcom_campanhas_ativas() {
		$hoje = wp_date( 'Y-m-d' );
		$out  = array();
		foreach ( dcom_campanhas() as $c ) {
			if ( empty( $c['ativo'] ) || ( ! empty( $c['inicio'] ) && $c['inicio'] > $hoje ) || ( ! empty( $c['fim'] ) && $c['fim'] < $hoje ) ) {
				continue;
			}
			$out[] = $c;
		}
		return array_reverse( $out );
	}
}

/* Banner da tela inicial da escola: a campanha mais nova no ar (ou '' para usar o banner padrao). */
function dcom_campanha_banner_html() {
	$at = dcom_campanhas_ativas();
	if ( ! $at ) {
		return '';
	}
	$c   = $at[0];
	$img = $c['img'] ? wp_get_attachment_image_url( $c['img'], 'full' ) : '';
	$btn = '' !== $c['link'] ? '<a class="dcomp-btn" href="' . esc_url( $c['link'] ) . '">' . esc_html( '' !== $c['botao'] ? $c['botao'] : 'Ver campanha' ) . ' &rarr;</a>' : '<a class="dcomp-btn" href="' . esc_url( wc_get_account_endpoint_url( 'escola-campanhas' ) ) . '">Ver materiais da campanha &rarr;</a>';
	return '<div class="dcomp-ban"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '><div><h3>' . esc_html( $c['titulo'] ) . '</h3>' . ( '' !== $c['texto'] ? '<p>' . nl2br( esc_html( $c['texto'] ) ) . '</p>' : '' ) . $btn . '</div></div>';
}

/* Aba /my-account-2/escola-campanhas/. */
add_filter(
	'woocommerce_get_query_vars',
	function ( $v ) {
		$v['escola-campanhas'] = 'escola-campanhas';
		return $v;
	}
);
add_action(
	'init',
	function () {
		add_rewrite_endpoint( 'escola-campanhas', EP_ROOT | EP_PAGES );
		if ( get_option( 'dcom_camp_rw' ) !== '1' ) {
			flush_rewrite_rules( false );
			update_option( 'dcom_camp_rw', '1', false );
		}
	},
	20
);
add_filter(
	'woocommerce_endpoint_escola-campanhas_title',
	function () {
		return 'Marketing e Campanhas';
	}
);

add_action(
	'woocommerce_account_escola-campanhas_endpoint',
	function () {
		$camp = dcom_campanhas_ativas();
		$mats = array_reverse( dcom_materiais(), true );
		echo '<style>.dcmk h2{margin:0 0 6px}.dcmk .sub{color:#667085;margin:0 0 16px}.dcmk-c{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin:0 0 24px}.dcmk-cp{background:#fff;border:1px solid #E7E9F0;border-radius:16px;overflow:hidden}.dcmk-cp img{width:100%;height:150px;object-fit:cover;display:block}.dcmk-cp .tx{padding:14px 16px}.dcmk-cp h3{margin:0 0 6px;font-size:18px}.dcmk-cp p{margin:0 0 8px;color:#475467;font-size:14px}.dcmk-cp small{color:#667085}.dcmk-b{display:inline-block;margin-top:8px;background:#C9A24A;color:#0B1F3F!important;font-weight:800;border-radius:10px;padding:9px 16px;text-decoration:none!important}'
			. '.dcmk-m{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}.dcmk-mi{background:#fff;border:1px solid #E7E9F0;border-radius:14px;padding:12px;display:flex;flex-direction:column;gap:8px}.dcmk-mi .th{height:120px;border-radius:10px;background:#F4F5F8 center/cover no-repeat;display:flex;align-items:center;justify-content:center;font-weight:800;color:#667085;font-size:18px}.dcmk-mi b{font-size:14px}.dcmk-mi span{font-size:12px;color:#667085}.dcmk-f{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 12px}.dcmk-f button{border:1px solid #D0D5DD;background:#fff;border-radius:999px;padding:6px 14px;font-weight:700;cursor:pointer}.dcmk-f button.on{background:#0B1F3F;color:#fff;border-color:#0B1F3F}</style>'; // phpcs:ignore
		echo '<div class="dcmk"><h2>Campanhas da Rede</h2><p class="sub">Campanhas que a Matriz Ouro Moderno est&aacute; fazendo agora.</p>';
		if ( $camp ) {
			echo '<div class="dcmk-c">';
			foreach ( $camp as $c ) {
				$img = $c['img'] ? wp_get_attachment_image_url( $c['img'], 'large' ) : '';
				$per = ( $c['fim'] ? 'At&eacute; ' . esc_html( wp_date( 'd/m/Y', strtotime( $c['fim'] ) ) ) : '' );
				echo '<div class="dcmk-cp">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="">' : '' ) . '<div class="tx"><h3>' . esc_html( $c['titulo'] ) . '</h3>' . ( '' !== $c['texto'] ? '<p>' . nl2br( esc_html( $c['texto'] ) ) . '</p>' : '' ) . '<small>' . $per . '</small>' . ( '' !== $c['link'] ? '<br><a class="dcmk-b" href="' . esc_url( $c['link'] ) . '">' . esc_html( '' !== $c['botao'] ? $c['botao'] : 'Ver campanha' ) . '</a>' : '' ) . '</div></div>'; // phpcs:ignore
			}
			echo '</div>';
		} else {
			echo '<p style="background:#F4F5F8;border-radius:12px;padding:14px">Nenhuma campanha no ar agora.</p>';
		}
		echo '<h2 style="margin-top:8px">Materiais e Artes</h2><p class="sub">Arquivos da Matriz para voc&ecirc; usar na divulga&ccedil;&atilde;o da sua escola.</p>';
		if ( $mats ) {
			$cats = array();
			foreach ( $mats as $m ) {
				$cats[ $m['cat'] ] = 1;
			}
			if ( count( $cats ) > 1 ) {
				echo '<div class="dcmk-f"><button type="button" class="on" data-c="">Todos</button>';
				foreach ( array_keys( $cats ) as $c ) {
					echo '<button type="button" data-c="' . esc_attr( $c ) . '">' . esc_html( $c ) . '</button>';
				}
				echo '</div>';
			}
			echo '<div class="dcmk-m">';
			foreach ( $mats as $m ) {
				$url = (string) wp_get_attachment_url( $m['att'] );
				if ( '' === $url ) {
					continue;
				}
				$th  = wp_attachment_is_image( $m['att'] ) ? wp_get_attachment_image_url( $m['att'], 'medium' ) : '';
				$ext = strtoupper( (string) pathinfo( $url, PATHINFO_EXTENSION ) );
				echo '<div class="dcmk-mi" data-c="' . esc_attr( $m['cat'] ) . '"><div class="th"' . ( $th ? ' style="background-image:url(' . esc_url( $th ) . ')"' : '' ) . '>' . ( $th ? '' : esc_html( $ext ) ) . '</div><b>' . esc_html( $m['titulo'] ) . '</b><span>' . esc_html( $m['cat'] ) . '</span><a class="dcmk-b" href="' . esc_url( $url ) . '" download target="_blank" rel="noopener">Baixar</a></div>'; // phpcs:ignore
			}
			echo '</div><script>document.addEventListener("click",function(e){var b=e.target.closest(".dcmk-f button");if(!b){return;}var c=b.getAttribute("data-c");document.querySelectorAll(".dcmk-f button").forEach(function(x){x.classList.toggle("on",x===b);});document.querySelectorAll(".dcmk-mi").forEach(function(d){d.style.display=(!c||d.getAttribute("data-c")===c)?"":"none";});});</script>';
		} else {
			echo '<p style="background:#F4F5F8;border-radius:12px;padding:14px">A Matriz ainda n&atilde;o enviou materiais.</p>';
		}
		echo '</div>';
	}
);
