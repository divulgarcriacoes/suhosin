<?php
/**
 * Logo e banner da Loja do franqueado, enviados pela propria unidade na aba "Minha loja" (escola-loja) da Area do Franqueado.
 * Ficam na Biblioteca de midia e no cadastro da unidade: omx_logo (ja usado pelo snippet #109112) e omx_banner.
 * So a unidade principal envia (a equipe ve, mas nao troca). Formatos: JPG, PNG ou WEBP, ate 5 MB.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Recebe o envio (antes da pagina ser montada) e volta para a aba com o aviso. */
add_action(
	'template_redirect',
	function () {
		if ( empty( $_POST['dcup_envio'] ) || ! is_user_logged_in() || ! function_exists( 'omx_is_escola' ) || ! omx_is_escola() ) { // phpcs:ignore
			return;
		}
		$uid  = get_current_user_id();
		$dest = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'escola-loja' ) : home_url( '/' );
		if ( ! isset( $_POST['dcup_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcup_n'] ) ), 'dcup' ) ) {
			wp_safe_redirect( add_query_arg( 'dcup', 'erro', $dest ) );
			exit;
		}
		if ( function_exists( 'omx_parent' ) && omx_parent( $uid ) !== $uid ) {
			wp_safe_redirect( add_query_arg( 'dcup', 'equipe', $dest ) );
			exit;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$ok  = 0;
		$err = 0;
		foreach ( array( 'dcup_logo' => 'omx_logo', 'dcup_banner' => 'omx_banner', 'dcup_fundo_img' => 'omx_fundo_img' ) as $campo => $meta ) {
			if ( empty( $_FILES[ $campo ]['name'] ) ) {
				continue;
			}
			$f    = $_FILES[ $campo ]; // phpcs:ignore
			$tipo = wp_check_filetype_and_ext( $f['tmp_name'], $f['name'], array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) );
			if ( ! empty( $f['error'] ) || (int) $f['size'] > 5 * MB_IN_BYTES || empty( $tipo['type'] ) ) {
				$err++;
				continue;
			}
			$id = media_handle_upload( $campo, 0, array( 'post_title' => get_user_meta( $uid, 'omx_escola', true ) . ( 'omx_logo' === $meta ? ' - logo' : ( 'omx_fundo_img' === $meta ? ' - fundo' : ' - banner' ) ) ), array( 'test_form' => false, 'mimes' => array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) ) );
			if ( is_wp_error( $id ) ) {
				$err++;
				continue;
			}
			update_user_meta( $uid, $meta, (int) $id );
			$ok++;
		}
		// Cores da loja (campo hexadecimal ou seletor): cor principal (omx_cor) e cor de fundo da vitrine (omx_fundo).
		foreach ( array( 'dcup_cor' => 'omx_cor', 'dcup_fundo' => 'omx_fundo' ) as $campo => $meta ) {
			if ( ! isset( $_POST[ $campo ] ) ) { // phpcs:ignore
				continue;
			}
			$hex = strtoupper( trim( sanitize_text_field( wp_unslash( $_POST[ $campo ] ) ) ) ); // phpcs:ignore
			$hex = '#' . ltrim( $hex, '#' );
			if ( preg_match( '/^#[0-9A-F]{3}$/', $hex ) ) {
				$hex = '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
			}
			if ( ! preg_match( '/^#[0-9A-F]{6}$/', $hex ) ) {
				continue;
			}
			if ( strtoupper( (string) get_user_meta( $uid, $meta, true ) ) !== $hex ) {
				update_user_meta( $uid, $meta, $hex );
				$ok++;
			}
		}
		if ( ! empty( $_POST['dcup_tirar_fundo_img'] ) ) { // phpcs:ignore
			delete_user_meta( $uid, 'omx_fundo_img' );
			$ok++;
		}
		if ( ! empty( $_POST['dcup_tirar_banner'] ) ) { // phpcs:ignore
			delete_user_meta( $uid, 'omx_banner' );
			$ok++;
		}
		delete_transient( 'omx_cfg_cache' );
		wp_safe_redirect( add_query_arg( 'dcup', $err ? 'erro' : ( $ok ? 'ok' : 'nada' ), $dest ) );
		exit;
	},
	-5
);

/* Quadro "Logo e banner da loja" na aba Minha loja. */
add_action(
	'woocommerce_account_escola-loja_endpoint',
	function () {
		if ( ! function_exists( 'omx_is_escola' ) || ! omx_is_escola() ) {
			return;
		}
		$uid  = get_current_user_id();
		$p    = function_exists( 'omx_parent' ) ? (int) omx_parent( $uid ) : $uid;
		$lg   = (int) get_user_meta( $p, 'omx_logo', true );
		$bn   = (int) get_user_meta( $p, 'omx_banner', true );
		$lgu  = $lg ? (string) wp_get_attachment_image_url( $lg, 'medium' ) : '';
		$bnu  = $bn ? (string) wp_get_attachment_image_url( $bn, 'large' ) : '';
		$fi   = (int) get_user_meta( $p, 'omx_fundo_img', true );
		$fiu  = $fi ? (string) wp_get_attachment_image_url( $fi, 'large' ) : '';
		$pode = $p === $uid;
		$cor  = strtoupper( (string) get_user_meta( $p, 'omx_cor', true ) );
		$cor  = preg_match( '/^#[0-9A-F]{6}$/', $cor ) ? $cor : '#F5B400';
		$fun  = strtoupper( (string) get_user_meta( $p, 'omx_fundo', true ) );
		$fun  = preg_match( '/^#[0-9A-F]{6}$/', $fun ) ? $fun : '#F4F5F8';
		$cmp  = function ( $nome, $tit, $dica, $val ) use ( $pode ) {
			return '<div class="dcup-c"><b>' . $tit . '</b><small>' . $dica . '</small><div class="dcup-cr"><input type="color" value="' . esc_attr( $val ) . '"' . ( $pode ? '' : ' disabled' ) . ' aria-label="' . esc_attr( wp_strip_all_tags( $tit ) ) . '"><input type="text" name="' . esc_attr( $nome ) . '" value="' . esc_attr( $val ) . '" maxlength="7" pattern="#?[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?" placeholder="#F5B400"' . ( $pode ? '' : ' disabled' ) . ' aria-label="C&oacute;digo hexadecimal"></div></div>';
		};
		$msg  = isset( $_GET['dcup'] ) ? sanitize_key( wp_unslash( $_GET['dcup'] ) ) : ''; // phpcs:ignore
		$txt  = array(
			'ok'     => array( 'Pronto! A loja j&aacute; mostra as altera&ccedil;&otilde;es.', 'ok' ),
			'erro'   => array( 'N&atilde;o foi poss&iacute;vel enviar. Use JPG, PNG ou WEBP de at&eacute; 5 MB e tente de novo.', 'er' ),
			'equipe' => array( 'S&oacute; o respons&aacute;vel pela unidade pode trocar o logo e o banner.', 'er' ),
			'nada'   => array( 'Nada foi alterado.', 'er' ),
		);
		echo '<style>.dcup{background:#fff;border:1px solid #E7E9F0;border-radius:16px;box-shadow:0 6px 18px rgba(11,31,63,.05);padding:22px 24px;margin:16px 0 0}.dcup h3{margin:0 0 4px;font-size:22px;color:#0B2A5B}.dcup>p{margin:0 0 16px;color:#5B6478}'
			. '.dcup-g{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr);gap:18px}.dcup-c{border:1px dashed #D0D5DD;border-radius:14px;padding:14px;background:#FAFBFC}.dcup-c b{display:block;margin:0 0 4px;color:#0B2A5B}.dcup-c small{display:block;color:#667085;margin:0 0 10px}'
			. '.dcup-pv{height:120px;border-radius:12px;background:#EEF1F6;display:flex;align-items:center;justify-content:center;overflow:hidden;margin:0 0 10px;color:#98A2B3;font-size:13px}.dcup-pv img{max-width:100%;max-height:100%;object-fit:contain}.dcup-pv.bn img{width:100%;height:100%;object-fit:cover}'
			. '.dcup input[type=file]{width:100%;font-size:13px}.dcup .bt{display:block;width:100%;margin:16px 0 0;border:0;border-radius:12px;background:#F5B400;color:#0B2A5B;font-weight:800;font-size:16px;padding:14px;cursor:pointer}'
			. '.dcup-m{border-radius:12px;padding:10px 14px;margin:0 0 14px;font-size:14px}.dcup-m.ok{background:#ECFDF3;color:#05603A;border:1px solid #ABEFC6}.dcup-m.er{background:#FEF3F2;color:#912018;border:1px solid #FECDCA}'
			. '.dcup label.tb{display:flex;gap:6px;align-items:center;font-size:13px;color:#5B6478;margin-top:8px}.dcup-g2{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-top:18px}.dcup-cr{display:flex;gap:10px;align-items:center}.dcup-cr input[type=color]{width:54px;height:44px;border:1px solid #D0D5DD;border-radius:10px;padding:3px;background:#fff;cursor:pointer}.dcup-cr input[type=text]{flex:1;height:44px;border:1px solid #D0D5DD;border-radius:10px;padding:0 12px;font-size:16px;font-family:monospace;text-transform:uppercase;letter-spacing:.04em}.dcup-cr input:invalid{border-color:#F04438}@media(max-width:760px){.dcup-g,.dcup-g2{grid-template-columns:1fr}}</style>';
		echo '<div class="dcup"><h3>Identidade da loja</h3><p>Logo, banner e cores da sua loja. Dica: use as cores do seu logo.</p>';
		if ( isset( $txt[ $msg ] ) ) {
			echo '<div class="dcup-m ' . esc_attr( $txt[ $msg ][1] ) . '">' . $txt[ $msg ][0] . '</div>'; // phpcs:ignore
		}
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( wc_get_account_endpoint_url( 'escola-loja' ) ) . '">' . wp_nonce_field( 'dcup', 'dcup_n', true, false ) . '<input type="hidden" name="dcup_envio" value="1"><div class="dcup-g">' // phpcs:ignore
			. '<div class="dcup-c"><b>Logo</b><small>Horizontal, de prefer&ecirc;ncia com fundo transparente (PNG). Ex.: 600 &times; 200 px.</small><div class="dcup-pv">' . ( '' !== $lgu ? '<img src="' . esc_url( $lgu ) . '" alt="Logo atual">' : 'Sem logo' ) . '</div>' . ( $pode ? '<input type="file" name="dcup_logo" accept="image/png,image/jpeg,image/webp">' : '' ) . '</div>'
			. '<div class="dcup-c"><b>Banner da loja</b><small>Retangular, horizontal. Ex.: 1600 &times; 400 px (JPG ou PNG).</small><div class="dcup-pv bn">' . ( '' !== $bnu ? '<img src="' . esc_url( $bnu ) . '" alt="Banner atual">' : 'Sem banner' ) . '</div>' . ( $pode ? '<input type="file" name="dcup_banner" accept="image/png,image/jpeg,image/webp">' . ( '' !== $bnu ? '<label class="tb"><input type="checkbox" name="dcup_tirar_banner" value="1"> Tirar o banner</label>' : '' ) : '' ) . '</div>'
			. '</div><div class="dcup-g2">'
			. $cmp( 'dcup_cor', 'Cor principal', 'Bot&otilde;es, destaques e a faixa do topo. Digite o c&oacute;digo (ex.: #F26522) ou escolha no quadrado.', $cor )
			. $cmp( 'dcup_fundo', 'Cor de fundo', 'Fundo da p&aacute;gina da loja, em volta dos produtos. Padr&atilde;o: cinza claro #F4F5F8.', $fun )
			. '<div class="dcup-c"><b>Imagem de fundo</b><small>Opcional: uma foto da escola em volta dos produtos (fica no lugar da cor). Horizontal, ex.: 1920 &times; 1080 px, JPG at&eacute; 5 MB.</small><div class="dcup-pv bn">' . ( '' !== $fiu ? '<img src="' . esc_url( $fiu ) . '" alt="Imagem de fundo atual">' : 'Sem imagem' ) . '</div>' . ( $pode ? '<input type="file" name="dcup_fundo_img" accept="image/png,image/jpeg,image/webp">' . ( '' !== $fiu ? '<label class="tb"><input type="checkbox" name="dcup_tirar_fundo_img" value="1"> Tirar a imagem de fundo</label>' : '' ) : '' ) . '</div>'
			. '</div>' . ( $pode ? '<button type="submit" class="bt">Salvar identidade da loja</button>' : '<p style="margin:12px 0 0;color:#667085">S&oacute; o respons&aacute;vel pela unidade pode trocar o logo e o banner.</p>' ) . '</form></div>'
			. '<script>(function(){document.querySelectorAll(".dcup-cr").forEach(function(w){var c=w.querySelector("input[type=color]"),t=w.querySelector("input[type=text]");if(!c||!t){return;}c.addEventListener("input",function(){t.value=c.value.toUpperCase();});t.addEventListener("input",function(){var v=t.value.trim();if(v&&v[0]!=="#"){v="#"+v;}if(/^#[0-9a-fA-F]{3}$/.test(v)){v="#"+v[1]+v[1]+v[2]+v[2]+v[3]+v[3];}if(/^#[0-9a-fA-F]{6}$/.test(v)){c.value=v.toLowerCase();}});});document.querySelectorAll(".omx-box.omx-form").forEach(function(b){var h=b.querySelector("h3");if(h&&/Identidade visual/i.test(h.textContent)&&b.querySelector("input[name=cor]")){b.style.display="none";}});})();</script>';
	},
	20
);
