<?php
/**
 * Plugin Name: DC · Ouro Moderno: tela de boas-vindas da escola (envio dos logos)
 * Description: 2026-11-24. Quando a escola entra na Área do Franqueado e ainda falta algum logo, o painel abre com a tela de boas-vindas
 *   pedindo as 4 versões (horizontal colorido/branco, vertical colorido/branco). Depois do envio mostra a prévia dos brindes com a marca dela.
 *   Abrir de novo a qualquer momento: /my-account-2/?boas-vindas=1. Usa o mu-plugin dc-ouro-logo-mockup.php para a prévia.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dcbv_campos() {
	return array(
		'dcbv_hc' => array( 'omx_logo', 'Logo horizontal colorido para fundos claros', 'O logo principal, deitado, com as cores da escola.', false, true ),
		'dcbv_he' => array( 'omx_logo_escuro', 'Logo horizontal colorido para fundos escuros', 'Símbolo colorido e letras brancas.', true, false ),
		'dcbv_hb' => array( 'omx_logo_branco', 'Logo horizontal branco', 'O mesmo logo, todo em branco.', true, false ),
		'dcbv_vc' => array( 'omx_logo_vertical', 'Logo vertical colorido para fundos claros', 'Símbolo em cima e o nome embaixo.', false, false ),
		'dcbv_ve' => array( 'omx_logo_vertical_escuro', 'Logo vertical colorido para fundos escuros', 'Símbolo colorido e letras brancas.', true, false ),
		'dcbv_vb' => array( 'omx_logo_vertical_branco', 'Logo vertical branco', 'O vertical, todo em branco.', true, false ),
	);
}

function dcbv_unidade() {
	if ( ! is_user_logged_in() || ! function_exists( 'omx_is_escola' ) || ! omx_is_escola() ) {
		return 0;
	}
	$uid = get_current_user_id();
	$p   = function_exists( 'omx_parent' ) ? (int) omx_parent( $uid ) : $uid;
	return $p === $uid ? $uid : 0; // só o responsável pela unidade envia
}

function dcbv_faltam( $p ) {
	$f   = array();
	$sem = array(
		'omx_logo'                 => get_user_meta( $p, 'omx_sem_logo_h', true ),
		'omx_logo_branco'          => get_user_meta( $p, 'omx_sem_logo_h', true ),
		'omx_logo_vertical'        => get_user_meta( $p, 'omx_sem_logo_v', true ),
		'omx_logo_vertical_branco' => get_user_meta( $p, 'omx_sem_logo_v', true ),
	);
	foreach ( dcbv_campos() as $c ) {
		if ( ! empty( $sem[ $c[0] ] ) || in_array( $c[0], array( 'omx_logo_escuro', 'omx_logo_vertical_escuro' ), true ) ) {
			continue; // a escola informou que não tem essa versão (as de fundo escuro são opcionais)
		}
		if ( ! (int) get_user_meta( $p, $c[0], true ) ) {
			$f[] = $c[1];
		}
	}
	return $f;
}

/* Guia dos logos: exemplo visual das 4 versões (marca genérica) e como mandar o arquivo. */
function dcbv_guia() {
	$marca = function ( $cor1, $cor2, $txt, $vert ) {
		$icone = '<circle cx="0" cy="0" r="22" fill="' . $cor1 . '"/><path d="M-12 6 L0 -12 L12 6 Z" fill="' . $cor2 . '"/><circle cx="0" cy="10" r="4" fill="' . $cor2 . '"/>';
		if ( $vert ) {
			return '<svg viewBox="-70 -40 140 110" width="100%" height="100%" aria-hidden="true"><g transform="translate(0,-8)">' . $icone . '</g><text x="0" y="36" text-anchor="middle" font-family="Poppins,Arial,sans-serif" font-weight="800" font-size="16" fill="' . $txt . '">SUA ESCOLA</text><rect x="-44" y="44" width="88" height="3" rx="1.5" fill="' . $cor1 . '"/></svg>';
		}
		return '<svg viewBox="-30 -32 210 64" width="100%" height="100%" aria-hidden="true"><g transform="translate(0,0) scale(.95)">' . $icone . '</g><text x="32" y="2" font-family="Poppins,Arial,sans-serif" font-weight="800" font-size="20" fill="' . $txt . '">SUA</text><text x="32" y="22" font-family="Poppins,Arial,sans-serif" font-weight="800" font-size="20" fill="' . $txt . '">ESCOLA</text></svg>';
	};
	$cx = function ( $svg, $escuro, $tit, $uso ) {
		return '<div class="bvg-i"><div class="bvg-v ' . ( $escuro ? 'es' : 'cl' ) . '">' . $svg . '</div><b>' . $tit . '</b><small>' . $uso . '</small></div>';
	};
	return '<div class="bv-box bvg"><h3>Como são os 4 logos</h3><p>São o <b>mesmo logo</b> da escola em 4 versões. Cada brinde usa a versão que combina com ele: o site escolhe sozinho.</p>'
		. '<div class="bvg-g">'
		. $cx( $marca( '#F5B400', '#0B2A5B', '#0B2A5B', false ), false, '1. Horizontal colorido', 'Símbolo ao lado do nome. Vai nos brindes claros com área larga: cadernos, sacolas, power banks.' )
		. $cx( $marca( '#FFFFFF', '#2E3192', '#FFFFFF', false ), true, '2. Horizontal branco', 'O mesmo logo, todo branco. Vai nos brindes escuros e na gravação de 1 cor ou a laser.' )
		. $cx( $marca( '#F5B400', '#0B2A5B', '#0B2A5B', true ), false, '3. Vertical colorido', 'Símbolo em cima e o nome embaixo. Vai em áreas altas: squeezes, garrafas, etiquetas.' )
		. $cx( $marca( '#FFFFFF', '#2E3192', '#FFFFFF', true ), true, '4. Vertical branco', 'O vertical todo branco. Vai em squeezes e garrafas escuras ou de metal.' )
		. '</div>'
		. '<div class="bvg-r"><div class="ok"><b>Como mandar</b><ul><li>Arquivo <b>PNG com fundo transparente</b> (sem quadrado branco em volta).</li><li>Boa qualidade: pelo menos <b>1000 px</b> de largura.</li><li>O logo inteiro, sem cortar, sem sombra e sem borda.</li><li>Quem fez o logo (designer/gráfica) normalmente tem essas versões.</li></ul></div>'
		. '<div class="no"><b>Evite</b><ul><li>Foto ou print do logo (fica borrado).</li><li>Logo com fundo branco ou colorido.</li><li>Arquivo em PDF ou Word.</li><li>Logo muito pequeno, tirado do WhatsApp ou de rede social.</li></ul></div></div>'
		. '<p class="bvg-n">Só tem o colorido? Pode mandar só ele: o site gera a versão branca. Mas com as 4 versões os brindes ficam bem mais bonitos.</p></div>';
}

/* Recebe o envio. */
add_action(
	'template_redirect',
	function () {
		if ( empty( $_POST['dcbv_envio'] ) ) { // phpcs:ignore
			return;
		}
		$p    = dcbv_unidade();
		$volt = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
		if ( ! $p || ! isset( $_POST['dcbv_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcbv_n'] ) ), 'dcbv' ) ) {
			wp_safe_redirect( add_query_arg( 'bv', 'erro', $volt ) );
			exit;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$ok   = 0;
		$err  = 0;
		$nome = (string) get_user_meta( $p, 'omx_escola', true );
		foreach ( dcbv_campos() as $campo => $c ) {
			if ( empty( $_FILES[ $campo ]['name'] ) ) {
				continue;
			}
			$mim = $c[4] ? array( 'png' => 'image/png', 'webp' => 'image/webp', 'jpg|jpeg|jpe' => 'image/jpeg' ) : array( 'png' => 'image/png', 'webp' => 'image/webp' );
			$f   = $_FILES[ $campo ]; // phpcs:ignore
			$t   = wp_check_filetype_and_ext( $f['tmp_name'], $f['name'], $mim );
			if ( ! empty( $f['error'] ) || (int) $f['size'] > 5 * MB_IN_BYTES || empty( $t['type'] ) ) {
				$err++;
				continue;
			}
			$id = media_handle_upload( $campo, 0, array( 'post_title' => $nome . ' - ' . strtolower( $c[1] ) ), array( 'test_form' => false, 'mimes' => $mim ) );
			if ( is_wp_error( $id ) ) {
				$err++;
				continue;
			}
			update_user_meta( $p, $c[0], (int) $id );
			$ok++;
		}
		// "Não tenho logo horizontal / vertical": fica no cadastro (a equipe vê no perfil da escola).
		foreach ( array( 'dcbv_sem_h' => 'omx_sem_logo_h', 'dcbv_sem_v' => 'omx_sem_logo_v' ) as $cx => $mk ) {
			$antes = (string) get_user_meta( $p, $mk, true );
			if ( ! empty( $_POST[ $cx ] ) ) { // phpcs:ignore
				update_user_meta( $p, $mk, current_time( 'mysql' ) );
				$ok += '' === $antes ? 1 : 0;
			} elseif ( '' !== $antes ) {
				delete_user_meta( $p, $mk );
				$ok++;
			}
		}
		// Sem logo horizontal: o vertical vira o logo principal da loja (topo, brindes).
		if ( get_user_meta( $p, 'omx_sem_logo_h', true ) && ! (int) get_user_meta( $p, 'omx_logo', true ) && (int) get_user_meta( $p, 'omx_logo_vertical', true ) ) {
			update_user_meta( $p, 'omx_logo', (int) get_user_meta( $p, 'omx_logo_vertical', true ) );
			update_user_meta( $p, 'omx_logo_do_vertical', 1 );
			if ( ! (int) get_user_meta( $p, 'omx_logo_branco', true ) && (int) get_user_meta( $p, 'omx_logo_vertical_branco', true ) ) {
				update_user_meta( $p, 'omx_logo_branco', (int) get_user_meta( $p, 'omx_logo_vertical_branco', true ) );
			}
		}
		delete_transient( 'omx_cfg_cache' );
		wp_safe_redirect( add_query_arg( 'bv', $err ? 'erro' : ( $ok ? 'ok' : 'nada' ), $volt ) );
		exit;
	},
	-7
);

/* Sem o logo horizontal colorido a escola não navega pela Área do Franqueado: volta sempre para a tela de boas-vindas. */
add_action(
	'template_redirect',
	function () {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || ! empty( $_POST['dcbv_envio'] ) ) { // phpcs:ignore
			return;
		}
		$p = dcbv_unidade();
		if ( ! $p || (int) get_user_meta( $p, 'omx_logo', true ) || get_user_meta( $p, 'omx_vet_pedido', true ) ) {
			return;
		}
		$ep = ( function_exists( 'WC' ) && WC()->query ) ? (string) WC()->query->get_current_endpoint() : '';
		if ( '' === $ep || 'customer-logout' === $ep ) {
			return;
		}
		wp_safe_redirect( add_query_arg( 'boas-vindas', 1, wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	},
	-6
);

/* "Fazer depois": some por 1 dia (o aviso amarelo continua). */
add_action(
	'template_redirect',
	function () {
		if ( empty( $_GET['bv_depois'] ) ) { // phpcs:ignore
			return;
		}
		$p = dcbv_unidade();
		if ( $p && isset( $_GET['_n'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_n'] ) ), 'dcbv_depois' ) ) {
			update_user_meta( $p, 'dcbv_depois', time() + DAY_IN_SECONDS );
		}
		wp_safe_redirect( remove_query_arg( array( 'bv_depois', '_n' ) ) );
		exit;
	},
	-7
);

/* A tela. */
add_action(
	'woocommerce_account_content',
	function () {
		if ( ! function_exists( 'WC' ) || ! WC()->query || '' !== (string) WC()->query->get_current_endpoint() ) {
			return; // só no início do painel
		}
		$p = dcbv_unidade();
		if ( ! $p ) {
			return;
		}
		$msg    = isset( $_GET['bv'] ) ? sanitize_key( wp_unslash( $_GET['bv'] ) ) : ''; // phpcs:ignore
		$forca  = ! empty( $_GET['boas-vindas'] ); // phpcs:ignore
		$faltam = dcbv_faltam( $p );
		$adiou  = (int) get_user_meta( $p, 'dcbv_depois', true ) > time();
		$tem_hc = (int) get_user_meta( $p, 'omx_logo', true ) > 0; // horizontal colorido: obrigatório
		$vet    = get_user_meta( $p, 'omx_vet_pedido', true );
		if ( ! $forca && '' === $msg && ( ! $faltam || ( $adiou && $tem_hc ) || $vet ) ) {
			return;
		}
		$GLOBALS['dcbv_mostrou'] = true;
		$escola = (string) get_user_meta( $p, 'omx_escola', true );
		$loja   = function_exists( 'dcom_loja_url' ) ? (string) dcom_loja_url( $p ) : '';
		$act    = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
		$avisos = array(
			'ok'   => array( 'Logos recebidos! Veja abaixo como seus brindes ficam com a sua marca.', 'ok' ),
			'erro' => array( 'Algum arquivo não foi aceito. Use PNG com fundo transparente (o horizontal colorido também pode ser JPG), até 5 MB.', 'er' ),
			'nada' => array( 'Nenhum arquivo foi escolhido.', 'er' ),
			'vet'  => array( 'Recebemos seu logo! A equipe GiftBrand vai vetorizar e colocar as versões na sua loja. Você não precisa fazer mais nada: pode abrir a sua loja normalmente.', 'ok' ),
		);
		echo '<style>'
			. '.bv{font-family:Poppins,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:#101828;margin:0 0 26px}'
			. '.bv-hero{background:linear-gradient(110deg,#07142b,#0B2A5B 70%);color:#fff;border-radius:20px;padding:30px 32px;position:relative;overflow:hidden}'
			. '.bv-hero h2{margin:0 0 6px;font-size:28px;line-height:1.2;color:#fff}.bv-hero p{margin:0;color:#C9D3E8;font-size:15px;max-width:640px}'
			. '.bv-hero:after{content:"";position:absolute;right:-60px;top:-60px;width:220px;height:220px;border-radius:50%;background:rgba(245,180,0,.16)}'
			. '.bv-steps{display:flex;gap:10px;flex-wrap:wrap;margin:18px 0 0}.bv-steps span{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.25);border-radius:999px;padding:7px 14px;font-size:13px}.bv-steps b{color:#F5B400;margin-right:4px}'
			. '.bv-box{background:#fff;border:1px solid #E7E9F0;border-radius:20px;box-shadow:0 8px 24px rgba(11,31,63,.06);padding:24px 26px;margin:16px 0 0}'
			. '.bv-box h3{margin:0 0 4px;font-size:20px;color:#0B2A5B}.bv-box>p{margin:0 0 16px;color:#5B6478;font-size:14px}'
			. '.bv-g{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}'
			. '.bv-c{border:2px dashed #D0D5DD;border-radius:16px;padding:12px;display:flex;flex-direction:column;gap:8px;cursor:pointer;transition:border-color .15s,background .15s}.bv-c:hover{border-color:#F5B400;background:#FFFBEB}'
			. '.bv-c b{color:#0B2A5B;font-size:15px}.bv-c small{color:#667085;font-size:12.5px;min-height:34px}'
			. '.bv-pv{height:110px;border-radius:12px;display:flex;align-items:center;justify-content:center;overflow:hidden;font-size:12.5px;padding:10px;box-sizing:border-box}.bv-pv.cl{background:#F2F4F7;color:#98A2B3}.bv-pv.es{background:#0B2A5B;color:#8EA0C4}.bv-pv img{max-width:100%;max-height:100%;object-fit:contain}'
			. '.bv-st{font-size:12px;font-weight:700}.bv-st.ok{color:#079455}.bv-st.no{color:#B54708}.bv-c input[type=file]{display:none}'
			. '.bv-bt{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:12px;background:#F5B400;color:#0B2A5B!important;font-weight:800;font-size:16px;padding:14px 26px;cursor:pointer;text-decoration:none!important}.bv-bt.sec{background:#EEF1F6}'
			. '.bv-ac{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:18px 0 0}.bv-ac a.lk{color:#667085;font-size:13.5px}'
			. '.bv-dc{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:14px 0 0;font-size:13px;color:#475467}.bv-dc div{background:#F8FAFC;border-radius:12px;padding:10px 12px}.bv-dc b{display:block;color:#0B2A5B;margin:0 0 2px}'
			. '.bv-m{border-radius:12px;padding:12px 16px;margin:16px 0 0;font-size:14px}.bv-m.ok{background:#ECFDF3;color:#05603A;border:1px solid #ABEFC6}.bv-m.er{background:#FEF3F2;color:#912018;border:1px solid #FECDCA}'
			. '.bv-pr{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.bv-p{border:1px solid #EEF0F4;border-radius:14px;padding:8px;background:#fff}.bv-im{position:relative;aspect-ratio:1;border-radius:10px;overflow:hidden;background:#fff}.bv-im img.f{width:100%;height:100%;object-fit:contain;display:block}.bv-p span{display:block;font-size:12px;color:#344054;margin:6px 2px 2px;line-height:1.3;height:31px;overflow:hidden}'
			. '.bv-vet{border:2px solid #FEDF89;background:#FFFCF5}.bv-vet h3{font-size:18px!important}.bv-vg{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.4fr);gap:14px}.bv-vf{display:flex;flex-direction:column;gap:6px;font-size:13.5px}.bv-vf b{color:#0B2A5B}.bv-vf textarea{border:1px solid #D0D5DD;border-radius:10px;padding:8px 10px;font:inherit;resize:vertical}.bv-vok{background:#ECFDF3;color:#05603A;border-radius:10px;padding:10px 12px}@media(max-width:700px){.bv-vg{grid-template-columns:1fr}}'
			. '.bv-cw{display:flex;flex-direction:column;gap:6px}.bv-cw>.bv-c{flex:1}.bv-nt{display:flex;gap:8px;align-items:center;justify-content:center;cursor:pointer;font-size:13.5px;font-weight:700;color:#B54708;background:#FFFBEB;border:1px solid #FEDF89;border-radius:10px;padding:7px 10px}.bv-nt input{width:17px;height:17px;margin:0}'
			. '.bvg-g{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.bvg-i b{display:block;color:#0B2A5B;font-size:14.5px;margin:8px 0 2px}.bvg-i small{display:block;color:#667085;font-size:12.5px;line-height:1.45}.bvg-v{height:120px;border-radius:14px;display:flex;align-items:center;justify-content:center;padding:14px 18px;box-sizing:border-box}.bvg-v.cl{background:#F8FAFC;border:1px solid #EEF0F4}.bvg-v.es{background:#2E3192}'
			. '.bvg-r{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:18px 0 0}.bvg-r>div{border-radius:14px;padding:12px 16px;font-size:13.5px}.bvg-r ul{margin:6px 0 0;padding-left:18px}.bvg-r li{margin:3px 0}.bvg-r .ok{background:#ECFDF3;color:#05603A}.bvg-r .no{background:#FEF3F2;color:#912018}.bvg-n{margin:12px 0 0!important;font-size:13px!important}'
			. '@media(max-width:900px){.bvg-g{grid-template-columns:repeat(2,minmax(0,1fr))}.bvg-r{grid-template-columns:1fr}}'
			. '@media(max-width:900px){.bv-g{grid-template-columns:repeat(2,minmax(0,1fr))}.bv-pr{grid-template-columns:repeat(3,minmax(0,1fr))}.bv-dc{grid-template-columns:1fr}}'
			. '@media(max-width:520px){.bv-hero{padding:22px 20px}.bv-hero h2{font-size:22px}.bv-box{padding:18px 16px}.bv-pr{grid-template-columns:repeat(2,minmax(0,1fr))}}'
			. ( function_exists( 'dcmk_css' ) ? dcmk_css() : '' )
			. '</style>';
		echo '<div class="bv"><div class="bv-hero"><h2>' . ( $faltam ? 'Bem-vinda' . ( '' !== $escola ? ', ' . esc_html( $escola ) : '' ) . '!' : 'Sua marca nos brindes' ) . '</h2>'
			. '<p>' . ( $faltam ? 'Antes de começar, envie o logo da sua escola. Com ele, todos os brindes da sua loja aparecem com a sua marca, prontos para os alunos e para a divulgação.' : 'Seus logos estão cadastrados. Se precisar trocar algum, é só enviar de novo aqui.' ) . '</p>'
			. '<div class="bv-steps"><span><b>1</b>Envie os logos</span><span><b>2</b>Veja os brindes com a sua marca</span><span><b>3</b>Abra a sua loja</span></div></div>';
		if ( isset( $avisos[ $msg ] ) ) {
			echo '<div class="bv-m ' . esc_attr( $avisos[ $msg ][1] ) . '">' . esc_html( $avisos[ $msg ][0] ) . '</div>';
		}
		echo dcbv_guia(); // phpcs:ignore
		// Envio.
		echo '<form class="bv-box" method="post" enctype="multipart/form-data" action="' . esc_url( $act ) . '">' . wp_nonce_field( 'dcbv', 'dcbv_n', true, false ) . '<input type="hidden" name="dcbv_envio" value="1">' // phpcs:ignore
			. '<h3>1. Envie os logos da escola</h3><p>Clique em cada quadro para escolher o arquivo. Use <b>PNG com fundo transparente</b>, de boa qualidade (mínimo 1000 px de largura). Se tiver só o colorido, pode mandar só ele: o site gera o branco.</p><div class="bv-g">';
		$sem_h = (bool) get_user_meta( $p, 'omx_sem_logo_h', true );
		$sem_v = (bool) get_user_meta( $p, 'omx_sem_logo_v', true );
		foreach ( dcbv_campos() as $campo => $c ) {
			$a = (int) get_user_meta( $p, $c[0], true );
			$u = $a ? (string) wp_get_attachment_image_url( $a, 'medium' ) : '';
			echo '<div class="bv-cw"><label class="bv-c" for="' . esc_attr( $campo ) . '"><b>' . esc_html( $c[1] ) . ( 'dcbv_hc' === $campo ? ' <em style="font-style:normal;font-size:11px;background:#FEF0C7;color:#B54708;border-radius:999px;padding:2px 8px;margin-left:4px">obrigatório</em>' : '' ) . '</b><small>' . esc_html( $c[2] ) . '</small>'
				. '<div class="bv-pv ' . ( $c[3] ? 'es' : 'cl' ) . '" id="' . esc_attr( $campo ) . '_pv">' . ( '' !== $u ? '<img src="' . esc_url( $u ) . '" alt="">' : 'Clique para escolher' ) . '</div>'
				. '<span class="bv-st ' . ( $a ? 'ok' : 'no' ) . '" id="' . esc_attr( $campo ) . '_st">' . ( $a ? '✓ Enviado (clique para trocar)' : 'Falta enviar' ) . '</span>'
				. '<input type="file" id="' . esc_attr( $campo ) . '" name="' . esc_attr( $campo ) . '" accept="image/png,image/webp' . ( $c[4] ? ',image/jpeg' : '' ) . '"></label>'
				. ( 'dcbv_hc' === $campo ? '<label class="bv-nt"><input type="checkbox" name="dcbv_sem_h" id="dcbv_sem_h" value="1"' . checked( $sem_h, true, false ) . '> Não tem</label>' : '' )
				. ( 'dcbv_vc' === $campo ? '<label class="bv-nt"><input type="checkbox" name="dcbv_sem_v" id="dcbv_sem_v" value="1"' . checked( $sem_v, true, false ) . '> Não tem</label>' : '' )
				. '</div>';
		}
		echo '</div>'
			. '<div class="bv-dc"><div><b>Horizontal</b>Vai em cadernos, sacolas, power banks: áreas largas.</div><div><b>Vertical</b>Vai em squeezes, garrafas, etiquetas: áreas altas.</div><div><b>Branco</b>Vai em produtos escuros e gravação a laser ou de 1 cor.</div></div>'
			. '<div class="bv-ac"><button type="submit" class="bv-bt">Enviar logos</button>'
			. ( $faltam && $tem_hc ? '<a class="lk" href="' . esc_url( add_query_arg( array( 'bv_depois' => 1, '_n' => wp_create_nonce( 'dcbv_depois' ) ), $act ) ) . '">Fazer depois</a>' : '' )
			. ( ! $tem_hc ? '<span style="font-size:13px;color:#B54708">Envie pelo menos um logo <b>colorido</b> (horizontal ou, se não tiver, o vertical) para liberar a sua loja.</span>' : '' )
			. '</div></form>';
		// Vetorização pela equipe GiftBrand.
		echo '<form class="bv-box bv-vet" method="post" enctype="multipart/form-data" action="' . esc_url( $act ) . '">' . wp_nonce_field( 'dcbv_vet', 'dcbv_vn', true, false ) . '<input type="hidden" name="dcbv_vet" value="1">' // phpcs:ignore
			. '<h3>Não tem seu logo vetorizado? Nossa equipe GiftBrand faz para você.</h3>'
			. ( is_array( $vet ) && ! empty( $vet['t'] )
				? '<p class="bv-vok">✓ Pedido enviado em ' . esc_html( mysql2date( 'd/m/Y H:i', $vet['t'] ) ) . '. Nossa equipe está preparando os seus logos e vai colocar na sua loja. Se quiser, pode mandar outro arquivo abaixo.</p>'
				: '<p>Mande o arquivo que você tiver do logo (foto, JPG, PNG ou PDF), mesmo que esteja com fundo ou em baixa qualidade. A gente prepara as 4 versões e coloca na sua loja.</p>' )
			. '<div class="bv-vg"><label class="bv-vf"><b>Arquivo do logo</b><input type="file" name="dcbv_vet_arq" accept="image/png,image/jpeg,image/webp,application/pdf" required></label>'
			. '<label class="bv-vf"><b>Observação (opcional)</b><textarea name="dcbv_vet_obs" rows="2" placeholder="Ex.: as cores são azul e amarelo; o nome é Escola Real"></textarea></label></div>'
			. '<div class="bv-ac"><button type="submit" class="bv-bt">Enviar logo</button></div></form>';
		// Prévia dos brindes com a marca.
		if ( (int) get_user_meta( $p, 'omx_logo', true ) && function_exists( 'dcmk_logos' ) && function_exists( 'dcmk_regra' ) ) {
			$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 200, 'fields' => 'ids', 'no_found_rows' => true, 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => array( 1456 ) ) ) ) ); // phpcs:ignore
			$com = array();
			$sem = array();
			foreach ( $ids as $pid ) {
				$r = dcmk_regra( $pid );
				if ( 'nada' === $r['m'] ) {
					continue;
				}
				$f = function_exists( 'dcmk_foto_unica' ) ? (int) dcmk_foto_unica( $pid ) : 0;
				if ( ! empty( $r['q'] ) && $f ) {
					$com[] = $pid;
				} elseif ( $f ) {
					$sem[] = $pid;
				}
			}
			$sel = array_slice( $com, 0, 6 ); // só produtos com molde do Corel
			if ( $sel ) {
				$M = array();
				echo '<div class="bv-box"><h3>2. Seus brindes com a sua marca</h3><p>Uma amostra de como a sua loja mostra os produtos. A arte final de cada pedido é conferida pela nossa equipe antes da produção.</p><div class="bv-pr">';
				foreach ( $sel as $pid ) {
					$r = dcmk_regra( $pid );
					$f = (int) dcmk_foto_unica( $pid );
					$M[ $pid ] = array( 'x' => (float) $r['x'], 'y' => (float) $r['y'], 'w' => (float) $r['w'], 'r' => (float) $r['r'], 'm' => (string) $r['m'] );
					if ( ! empty( $r['q'] ) ) {
						$M[ $pid ]['q'] = array_map( 'floatval', $r['q'] );
					}
					if ( ! empty( $r['l'] ) && in_array( $r['l'], array( 'h', 'v' ), true ) ) {
						$M[ $pid ]['l'] = $r['l'];
					}
					echo '<div class="bv-p"><div class="bv-im" data-p="' . (int) $pid . '"><img class="f" src="' . esc_url( (string) wp_get_attachment_image_url( $f, 'woocommerce_thumbnail' ) ) . '" alt=""></div><span>' . esc_html( get_the_title( $pid ) ) . '</span></div>';
				}
				echo '</div>' . ( '' !== $loja ? '<div class="bv-ac"><a class="bv-bt" href="' . esc_url( $loja ) . '">3. Abrir a minha loja &rarr;</a></div>' : '' ) . '</div>';
				echo '<script>(function(){var M=' . wp_json_encode( $M ) . ',L=' . wp_json_encode( dcmk_logos( $p ) ) . ';' . ( function_exists( 'dcmk_js_aplicar' ) ? dcmk_js_aplicar() : '' ) // phpcs:ignore
					. 'var B=[];document.querySelectorAll(".bv-im").forEach(function(w){var R=M[w.getAttribute("data-p")],img=w.querySelector("img.f");if(!R||!img||typeof dcmkLogo!=="function")return;var b=dcmkLogo(R,L);if(!b)return;w.appendChild(b);var f=function(){dcmkPos(b,img,R);};B.push(f);if(img.complete)f();img.addEventListener("load",f);b.firstChild.addEventListener("load",f);});window.addEventListener("resize",function(){B.forEach(function(f){f();});});})();</script>';
			}
		}
		echo '</div>';
		// Prévia imediata do arquivo escolhido + caixas "não tenho".
		echo '<script>(function(){function sem(){var h=document.getElementById("dcbv_sem_h"),v=document.getElementById("dcbv_sem_v");[["dcbv_hc",h],["dcbv_hb",h],["dcbv_vc",v],["dcbv_vb",v]].forEach(function(x){var l=document.querySelector("label[for=\\""+x[0]+"\\"]");if(l&&x[1]){l.style.opacity=x[1].checked?".35":"";l.style.pointerEvents=x[1].checked?"none":"";}});if(h&&v&&h.checked&&v.checked){v.checked=false;sem();}}["dcbv_sem_h","dcbv_sem_v"].forEach(function(i){var e=document.getElementById(i);if(e)e.addEventListener("change",sem);});sem();})();</script>';
		echo '<script>(function(){document.querySelectorAll(".bv-c input[type=file]").forEach(function(i){i.addEventListener("change",function(){var f=i.files&&i.files[0];if(!f)return;var pv=document.getElementById(i.id+"_pv"),st=document.getElementById(i.id+"_st");var r=new FileReader();r.onload=function(){pv.innerHTML="<img src=\""+r.result+"\" alt=\"\">";};r.readAsDataURL(f);st.className="bv-st ok";st.textContent="✓ "+f.name+" (clique em Enviar logos)";});});})();</script>';
	},
	0
);

/* Perfil da escola no painel (Usuários > Editar): os logos enviados e o que ela informou, para a equipe definir as artes. */
function dcbv_perfil( $u ) {
	if ( ! current_user_can( 'edit_users' ) || ! ( $u instanceof WP_User ) ) {
		return;
	}
	$p = (int) $u->ID;
	if ( ! in_array( 'escola', (array) $u->roles, true ) && '' === (string) get_user_meta( $p, 'omx_escola', true ) ) {
		return;
	}
	echo '<h2>Logos da escola (Ouro Moderno)</h2><table class="form-table"><tr><th>Versões enviadas</th><td><div style="display:flex;gap:12px;flex-wrap:wrap">';
	foreach ( dcbv_campos() as $c ) {
		$a  = (int) get_user_meta( $p, $c[0], true );
		$ur = $a ? (string) wp_get_attachment_image_url( $a, 'medium' ) : '';
		echo '<div style="width:150px;text-align:center"><div style="height:90px;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:6px;box-sizing:border-box;background:' . ( $c[3] ? '#0B2A5B' : '#F2F4F7' ) . '">' . ( '' !== $ur ? '<a href="' . esc_url( (string) wp_get_attachment_url( $a ) ) . '" target="_blank"><img src="' . esc_url( $ur ) . '" style="max-width:100%;max-height:78px" alt=""></a>' : '<span style="color:#98A2B3;font-size:12px">não enviado</span>' ) . '</div><small>' . esc_html( $c[1] ) . '</small></div>';
	}
	echo '</div>';
	$av = array();
	if ( get_user_meta( $p, 'omx_sem_logo_h', true ) ) {
		$av[] = 'A escola informou que <b>NÃO TEM logo horizontal</b>' . ( get_user_meta( $p, 'omx_logo_do_vertical', true ) ? ' (o vertical está sendo usado como logo principal)' : '' ) . '.';
	}
	if ( get_user_meta( $p, 'omx_sem_logo_v', true ) ) {
		$av[] = 'A escola informou que <b>NÃO TEM logo vertical</b>.';
	}
	if ( $av ) {
		echo '<p style="margin-top:10px;padding:8px 12px;background:#FFFBEB;border:1px solid #FEDF89;border-radius:6px">' . implode( '<br>', $av ) . '</p>'; // phpcs:ignore
	}
	echo '</td></tr></table>';
}
add_action( 'show_user_profile', 'dcbv_perfil' );
add_action( 'edit_user_profile', 'dcbv_perfil' );

/* Pedido de vetorização: a escola manda o que tem; a equipe recebe por e-mail e vê no perfil da escola. */
add_action(
	'template_redirect',
	function () {
		if ( empty( $_POST['dcbv_vet'] ) ) { // phpcs:ignore
			return;
		}
		$p    = dcbv_unidade();
		$volt = wc_get_page_permalink( 'myaccount' );
		if ( ! $p || ! isset( $_POST['dcbv_vn'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcbv_vn'] ) ), 'dcbv_vet' ) || empty( $_FILES['dcbv_vet_arq']['name'] ) ) {
			wp_safe_redirect( add_query_arg( 'bv', 'erro', $volt ) );
			exit;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$mim  = array( 'png' => 'image/png', 'webp' => 'image/webp', 'jpg|jpeg|jpe' => 'image/jpeg', 'pdf' => 'application/pdf' );
		$f    = $_FILES['dcbv_vet_arq']; // phpcs:ignore
		$t    = wp_check_filetype_and_ext( $f['tmp_name'], $f['name'], $mim );
		$nome = (string) get_user_meta( $p, 'omx_escola', true );
		if ( ! empty( $f['error'] ) || (int) $f['size'] > 10 * MB_IN_BYTES || empty( $t['type'] ) ) {
			wp_safe_redirect( add_query_arg( 'bv', 'erro', $volt ) );
			exit;
		}
		$id = media_handle_upload( 'dcbv_vet_arq', 0, array( 'post_title' => $nome . ' - logo para vetorizar' ), array( 'test_form' => false, 'mimes' => $mim ) );
		if ( is_wp_error( $id ) ) {
			wp_safe_redirect( add_query_arg( 'bv', 'erro', $volt ) );
			exit;
		}
		$obs = isset( $_POST['dcbv_vet_obs'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dcbv_vet_obs'] ) ) : '';
		update_user_meta( $p, 'omx_vet_pedido', array( 't' => current_time( 'mysql' ), 'a' => (int) $id, 'obs' => $obs ) );
		// Enquanto a equipe prepara: se for imagem, a loja já usa esse arquivo como logo provisório.
		if ( wp_attachment_is_image( $id ) && ! (int) get_user_meta( $p, 'omx_logo', true ) ) {
			update_user_meta( $p, 'omx_logo', (int) $id );
			update_user_meta( $p, 'omx_logo_provisorio', 1 );
		}
		$para = (string) get_option( 'dcbv_vet_email', get_option( 'admin_email' ) );
		wp_mail(
			$para,
			'Vetorizar logo: ' . $nome,
			"A escola {$nome} pediu a vetorização do logo.\n\nArquivo enviado: " . wp_get_attachment_url( $id ) . "\nObservação: " . ( '' !== $obs ? $obs : '-' ) . "\n\nDepois de prontas, suba as 4 versões no perfil da escola:\n" . admin_url( 'user-edit.php?user_id=' . $p ) . "\n"
		);
		delete_transient( 'omx_cfg_cache' );
		wp_safe_redirect( add_query_arg( 'bv', 'vet', $volt ) );
		exit;
	},
	-7
);

/* Equipe: subir as 4 versões prontas no perfil da escola e concluir a vetorização. */
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( in_array( $hook, array( 'user-edit.php', 'profile.php' ), true ) ) {
			wp_enqueue_media();
		}
	}
);

function dcbv_perfil_equipe( $u ) {
	if ( ! current_user_can( 'edit_users' ) || ! ( $u instanceof WP_User ) ) {
		return;
	}
	$p = (int) $u->ID;
	if ( ! in_array( 'escola', (array) $u->roles, true ) && '' === (string) get_user_meta( $p, 'omx_escola', true ) ) {
		return;
	}
	$vet = get_user_meta( $p, 'omx_vet_pedido', true );
	wp_nonce_field( 'dcbv_adm', 'dcbv_adm_n' );
	echo '<table class="form-table"><tr><th>Vetorização (GiftBrand)</th><td>';
	if ( is_array( $vet ) && ! empty( $vet['t'] ) ) {
		$au = ! empty( $vet['a'] ) ? (string) wp_get_attachment_url( (int) $vet['a'] ) : '';
		echo '<div style="padding:10px 12px;background:#FFFBEB;border:1px solid #FEDF89;border-radius:6px;margin:0 0 10px"><b>Pedido em aberto</b> desde ' . esc_html( mysql2date( 'd/m/Y H:i', $vet['t'] ) ) . ( '' !== $au ? ' &middot; <a href="' . esc_url( $au ) . '" target="_blank">baixar o arquivo enviado</a>' : '' ) . ( ! empty( $vet['obs'] ) ? '<br>Observação: ' . esc_html( $vet['obs'] ) : '' ) . ( get_user_meta( $p, 'omx_logo_provisorio', true ) ? '<br><i>A loja está usando o arquivo enviado como logo provisório.</i>' : '' ) . '</div>';
	} elseif ( get_user_meta( $p, 'omx_vet_feito', true ) ) {
		echo '<p>Vetorização concluída em ' . esc_html( mysql2date( 'd/m/Y', (string) get_user_meta( $p, 'omx_vet_feito', true ) ) ) . '.</p>';
	}
	echo '<p class="description">Suba aqui as versões prontas (PNG transparente). Substituem as atuais da escola.</p><div style="display:flex;gap:12px;flex-wrap:wrap;margin:8px 0">';
	foreach ( dcbv_campos() as $campo => $c ) {
		echo '<div style="width:150px"><button type="button" class="button dcbv-adm-bt" data-alvo="dcbv_adm_' . esc_attr( $c[0] ) . '">' . esc_html( $c[1] ) . '</button><input type="hidden" name="dcbv_adm_' . esc_attr( $c[0] ) . '" id="dcbv_adm_' . esc_attr( $c[0] ) . '" value=""><div id="dcbv_adm_' . esc_attr( $c[0] ) . '_pv" style="margin-top:4px;font-size:12px;color:#008a20"></div></div>';
	}
	echo '</div>';
	if ( is_array( $vet ) && ! empty( $vet['t'] ) ) {
		echo '<label><input type="checkbox" name="dcbv_vet_ok" value="1"> Vetorização concluída (avisar que está pronta)</label>';
	}
	echo '</td></tr></table><script>jQuery(function($){var fr={};$(".dcbv-adm-bt").on("click",function(e){e.preventDefault();var al=$(this).data("alvo");if(!fr[al]){fr[al]=wp.media({title:$(this).text(),button:{text:"Usar este logo"},library:{type:"image"},multiple:false});fr[al].on("select",function(){var a=fr[al].state().get("selection").first().toJSON();$("#"+al).val(a.id);$("#"+al+"_pv").text("✓ "+(a.filename||a.title)+" (clique em Atualizar usuário)");});}fr[al].open();});});</script>';
}
add_action( 'show_user_profile', 'dcbv_perfil_equipe', 11 );
add_action( 'edit_user_profile', 'dcbv_perfil_equipe', 11 );

function dcbv_perfil_salvar( $uid ) {
	if ( ! current_user_can( 'edit_users' ) || ! isset( $_POST['dcbv_adm_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcbv_adm_n'] ) ), 'dcbv_adm' ) ) {
		return;
	}
	$mudou = false;
	foreach ( dcbv_campos() as $c ) {
		$v = isset( $_POST[ 'dcbv_adm_' . $c[0] ] ) ? absint( $_POST[ 'dcbv_adm_' . $c[0] ] ) : 0;
		if ( $v && wp_attachment_is_image( $v ) ) {
			update_user_meta( $uid, $c[0], $v );
			$mudou = true;
		}
	}
	if ( $mudou && (int) get_user_meta( $uid, 'omx_logo', true ) && get_user_meta( $uid, 'omx_logo_provisorio', true ) && ! empty( $_POST['dcbv_adm_omx_logo'] ) ) {
		delete_user_meta( $uid, 'omx_logo_provisorio' );
	}
	if ( ! empty( $_POST['dcbv_vet_ok'] ) ) {
		delete_user_meta( $uid, 'omx_vet_pedido' );
		delete_user_meta( $uid, 'omx_logo_provisorio' );
		update_user_meta( $uid, 'omx_vet_feito', current_time( 'mysql' ) );
	}
	if ( $mudou ) {
		delete_transient( 'omx_cfg_cache' );
	}
}
add_action( 'personal_options_update', 'dcbv_perfil_salvar' );
add_action( 'edit_user_profile_update', 'dcbv_perfil_salvar' );
