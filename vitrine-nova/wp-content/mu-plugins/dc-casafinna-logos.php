<?php
/**
 * Painel "Casafinna > Logos" (no menu do WordPress): a Casafinna sobe aqui os logos da marca dela.
 * Seis lugares: horizontal e vertical, nas versões colorido, branco e colorido para fundo escuro.
 * Guarda na opção dccf_marca_logos com os mesmos nomes de campo que o sistema de logo nos produtos já usa
 * (omx_logo, omx_logo_branco, omx_logo_escuro, omx_logo_vertical, omx_logo_vertical_branco, omx_logo_vertical_escuro).
 * Não tem ligação com a Ouro Moderno: é só da marca Casafinna.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dccf_logo_slots() {
	return array(
		'omx_logo'                 => array( 'Horizontal colorido', 'Para fundo claro. PNG com fundo transparente.', 'claro' ),
		'omx_logo_branco'          => array( 'Horizontal branco', 'Todo branco, para fundo escuro. PNG transparente.', 'escuro' ),
		'omx_logo_escuro'          => array( 'Horizontal colorido para fundo escuro', 'Opcional. Símbolo colorido e letras brancas.', 'escuro' ),
		'omx_logo_vertical'        => array( 'Vertical colorido', 'Para áreas altas (caneta, squeeze). Fundo claro.', 'claro' ),
		'omx_logo_vertical_branco' => array( 'Vertical branco', 'Todo branco, para fundo escuro.', 'escuro' ),
		'omx_logo_vertical_escuro' => array( 'Vertical colorido para fundo escuro', 'Opcional. Símbolo colorido e letras brancas.', 'escuro' ),
	);
}

function dccf_cfg() {
	$o = get_option( 'dccf_marca_cfg' );
	$o = is_array( $o ) ? $o : array();
	return array_merge( array( 'cor' => '#D4AF37', 'fundo' => '#F7F3EC', 'fundo_img' => 0 ), $o );
}

function dccf_host_casafinna() {
	$h = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( preg_replace( '/:\d+$/', '', (string) wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : ''; // phpcs:ignore
	return 'casafinna.giftbrand.com.br' === $h;
}

/* Cor principal (botões e destaques) da loja da Casafinna, vinda deste painel. */
add_filter( 'dclay_stores', function ( $s ) {
	if ( isset( $s['casafinna.giftbrand.com.br'] ) ) {
		$c = dccf_cfg();
		$s['casafinna.giftbrand.com.br']['cor'] = $c['cor'];
	}
	return $s;
} );

/* Fundo da página da loja da Casafinna (cor e, se tiver, imagem). */
add_action( 'wp_head', function () {
	if ( is_admin() || ! dccf_host_casafinna() ) {
		return;
	}
	$c   = dccf_cfg();
	$img = $c['fundo_img'] ? (string) wp_get_attachment_image_url( (int) $c['fundo_img'], 'full' ) : '';
	echo '<style id="dccf-fundo">body{background-color:' . esc_html( $c['fundo'] ) . '!important;' . ( $img ? 'background-image:url(' . esc_url( $img ) . ')!important;background-size:cover!important;background-attachment:fixed!important;background-position:center!important;' : '' ) . '}</style>'; // phpcs:ignore
}, 140 );

function dccf_logos_salvos() {
	$o = get_option( 'dccf_marca_logos' );
	return is_array( $o ) ? $o : array();
}

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Logos da Casafinna', 'Casafinna', 'manage_options', 'dccf-logos', 'dccf_logos_pagina', 'dashicons-format-image', 58 );
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'toplevel_page_dccf-logos' === $hook ) {
			wp_enqueue_media();
		}
	}
);

add_action(
	'admin_post_dccf_logos',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['dccf_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dccf_n'] ) ), 'dccf_logos' ) ) {
			wp_die( 'Sem permissão.' );
		}
		$ok  = array( 'image/png', 'image/webp', 'image/svg+xml', 'image/jpeg' );
		$new = array();
		foreach ( array_keys( dccf_logo_slots() ) as $k ) {
			$id = isset( $_POST[ $k ] ) ? absint( $_POST[ $k ] ) : 0;
			if ( $id && 'attachment' === get_post_type( $id ) && in_array( get_post_mime_type( $id ), $ok, true ) ) {
				$new[ $k ] = $id;
			}
		}
		update_option( 'dccf_marca_logos', $new, false );
		$cfg = dccf_cfg();
		foreach ( array( 'dccf_cor' => 'cor', 'dccf_fundo' => 'fundo' ) as $campo => $k ) {
			$v = isset( $_POST[ $campo ] ) ? sanitize_hex_color( wp_unslash( $_POST[ $campo ] ) ) : ''; // phpcs:ignore
			if ( $v ) {
				$cfg[ $k ] = $v;
			}
		}
		$fi = isset( $_POST['dccf_fundo_img'] ) ? absint( $_POST['dccf_fundo_img'] ) : 0;
		$cfg['fundo_img'] = ( $fi && wp_attachment_is_image( $fi ) ) ? $fi : 0;
		update_option( 'dccf_marca_cfg', $cfg, false );
		delete_transient( 'dccf_logos_v' );
		wp_safe_redirect( add_query_arg( array( 'page' => 'dccf-logos', 'salvo' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}
);

function dccf_logos_pagina() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$sv    = dccf_logos_salvos();
	$slots = dccf_logo_slots();
	$feitos = count( $sv );
	echo '<style>.dccf{max-width:1040px;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}.dccf h1{font-size:26px;margin:18px 0 6px}.dccf .sub{color:#5b6478;margin:0 0 18px;font-size:14px;line-height:1.5}'
		. '.dccf-g{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:16px}.dccf-c{background:#fff;border:1px solid #dfe3ea;border-radius:14px;padding:16px}'
		. '.dccf-c h3{margin:0 0 4px;font-size:15px}.dccf-c p{margin:0 0 12px;color:#667085;font-size:12.5px}'
		. '.dccf-v{height:130px;border-radius:10px;border:1px dashed #cfd5df;display:flex;align-items:center;justify-content:center;overflow:hidden;margin:0 0 12px;position:relative}'
		. '.dccf-v.claro{background:#fff}.dccf-v.escuro{background:#14110d}.dccf-v img{max-width:86%;max-height:86%;object-fit:contain}.dccf-v em{color:#98a2b3;font-size:12.5px;font-style:normal}'
		. '.dccf-b{display:flex;gap:8px}.dccf .button-primary{background:#b8902b;border-color:#b8902b}.dccf-ok{background:#ecfdf3;border-left:4px solid #12805c;padding:10px 14px;border-radius:8px;margin:0 0 16px}'
		. '.dccf-st{display:inline-block;font-size:12px;padding:2px 8px;border-radius:99px;background:#f2f4f7;color:#475467;margin-left:6px}.dccf-st.on{background:#d1fadf;color:#05603a}</style>';
	echo '<div class="wrap dccf"><h1>Identidade da Casafinna <span class="dccf-st ' . ( $feitos ? 'on' : '' ) . '">' . (int) $feitos . ' de 6 enviados</span></h1>';
	echo '<p class="sub">Envie os logos da marca. O sistema aplica o logo certo em cada produto (fundo claro ou escuro, formato horizontal ou vertical). Os dois <b>obrigatórios</b> são o <b>horizontal colorido</b> e o <b>horizontal branco</b>; os outros melhoram o resultado. Use <b>PNG com fundo transparente</b> (JPG deixa uma caixa branca em volta).</p>';
	if ( ! empty( $_GET['salvo'] ) ) { // phpcs:ignore
		echo '<div class="dccf-ok">Logos salvos.</div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="dccf_logos">' . wp_nonce_field( 'dccf_logos', 'dccf_n', true, false ) . '<div class="dccf-g">';
	foreach ( $slots as $k => $s ) {
		$id  = isset( $sv[ $k ] ) ? (int) $sv[ $k ] : 0;
		$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		if ( $id && ! $url ) {
			$url = (string) wp_get_attachment_url( $id );
		}
		echo '<div class="dccf-c" data-k="' . esc_attr( $k ) . '"><h3>' . esc_html( $s[0] ) . '</h3><p>' . esc_html( $s[1] ) . '</p>'
			. '<div class="dccf-v ' . esc_attr( $s[2] ) . '">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '<em>Nenhum arquivo</em>' ) . '</div>'
			. '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . (int) $id . '">'
			. '<div class="dccf-b"><button type="button" class="button dccf-esc">' . ( $url ? 'Trocar' : 'Escolher arquivo' ) . '</button><button type="button" class="button dccf-tir"' . ( $url ? '' : ' style="display:none"' ) . '>Tirar</button></div></div>';
	}
	echo '</div>';
	$cf   = dccf_cfg();
	$fid  = (int) $cf['fundo_img'];
	$furl = $fid ? (string) wp_get_attachment_image_url( $fid, 'medium' ) : '';
	echo '<h2 style="margin:26px 0 10px;font-size:18px">Aparência da loja</h2><div class="dccf-g">'
		. '<div class="dccf-c"><h3>Cor principal</h3><p>Botões e destaques da loja. Digite o código (ex.: #D4AF37) ou escolha no quadrado.</p><div style="display:flex;gap:8px;align-items:center"><input type="color" id="dccf-cor-s" value="' . esc_attr( $cf['cor'] ) . '" style="width:48px;height:40px;padding:0"><input type="text" name="dccf_cor" id="dccf-cor" value="' . esc_attr( $cf['cor'] ) . '" maxlength="7" style="width:120px;font-family:monospace"></div></div>'
		. '<div class="dccf-c"><h3>Cor de fundo</h3><p>Fundo da página da loja, em volta dos produtos.</p><div style="display:flex;gap:8px;align-items:center"><input type="color" id="dccf-fun-s" value="' . esc_attr( $cf['fundo'] ) . '" style="width:48px;height:40px;padding:0"><input type="text" name="dccf_fundo" id="dccf-fun" value="' . esc_attr( $cf['fundo'] ) . '" maxlength="7" style="width:120px;font-family:monospace"></div></div>'
		. '<div class="dccf-c" data-k="fundo_img"><h3>Imagem de fundo</h3><p>Opcional. Foto em volta dos produtos (fica no lugar da cor). Horizontal, ex.: 1920 x 1080 px.</p><div class="dccf-v claro">' . ( $furl ? '<img src="' . esc_url( $furl ) . '" alt="">' : '<em>Nenhum arquivo</em>' ) . '</div><input type="hidden" name="dccf_fundo_img" value="' . (int) $fid . '"><div class="dccf-b"><button type="button" class="button dccf-esc">' . ( $furl ? 'Trocar' : 'Escolher arquivo' ) . '</button><button type="button" class="button dccf-tir"' . ( $furl ? '' : ' style="display:none"' ) . '>Tirar</button></div></div>'
		. '</div><p style="margin-top:18px"><button type="submit" class="button button-primary button-hero">Salvar</button></p></form></div>';
	?>
<script>(function($){function sel(c){return $(c).closest('.dccf-c');}
$('.dccf-esc').on('click',function(e){e.preventDefault();var card=sel(this),fr=wp.media({title:'Logo da Casafinna',button:{text:'Usar este logo'},library:{type:['image']},multiple:false});
fr.on('select',function(){var a=fr.state().get('selection').first().toJSON();var u=(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url);card.find('input[type=hidden]').val(a.id);card.find('.dccf-v').html('<img src="'+u+'" alt="">');card.find('.dccf-esc').text('Trocar');card.find('.dccf-tir').show();});fr.open();});
[['#dccf-cor-s','#dccf-cor'],['#dccf-fun-s','#dccf-fun']].forEach(function(p){$(p[0]).on('input',function(){$(p[1]).val(this.value.toUpperCase());});$(p[1]).on('input',function(){if(/^#[0-9a-fA-F]{6}$/.test(this.value)){$(p[0]).val(this.value);}});});
$('.dccf-tir').on('click',function(e){e.preventDefault();var card=sel(this);card.find('input[type=hidden]').val('0');card.find('.dccf-v').html('<em>Nenhum arquivo</em>');card.find('.dccf-esc').text('Escolher arquivo');$(this).hide();});})(jQuery);</script>
	<?php
}
