<?php
/**
 * Painel "Cast > Logos" (no menu do WordPress): a Cast sobe aqui os logos da marca dela.
 * Seis lugares: horizontal e vertical, nas versões colorido, branco e colorido para fundo escuro.
 * Guarda na opção dccast_marca_logos com os mesmos nomes de campo que o sistema de logo nos produtos já usa
 * (omx_logo, omx_logo_branco, omx_logo_escuro, omx_logo_vertical, omx_logo_vertical_branco, omx_logo_vertical_escuro).
 * Não tem ligação com a Ouro Moderno: é só da marca Cast.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dccast_logo_slots() {
	return array(
		'omx_logo'                 => array( 'Horizontal colorido', 'Para fundo claro. PNG com fundo transparente.', 'claro' ),
		'omx_logo_branco'          => array( 'Horizontal branco', 'Todo branco, para fundo escuro. PNG transparente.', 'escuro' ),
		'omx_logo_escuro'          => array( 'Horizontal colorido para fundo escuro', 'Opcional. Símbolo colorido e letras brancas.', 'escuro' ),
		'omx_logo_vertical'        => array( 'Vertical colorido', 'Para áreas altas (caneta, squeeze). Fundo claro.', 'claro' ),
		'omx_logo_vertical_branco' => array( 'Vertical branco', 'Todo branco, para fundo escuro.', 'escuro' ),
		'omx_logo_vertical_escuro' => array( 'Vertical colorido para fundo escuro', 'Opcional. Símbolo colorido e letras brancas.', 'escuro' ),
	);
}

function dccast_cfg() {
	$o = get_option( 'dccast_marca_cfg' );
	$o = is_array( $o ) ? $o : array();
	return array_merge( array( 'cor' => '#D4AF37', 'fundo' => '#F7F3EC', 'fundo_img' => 0 ), $o );
}

function dccast_hosts() {
	return array( 'cast.giftbrand.com.br', 'cast.divulgarcriacoes.com.br' );
}

function dccast_host_cast() {
	$h = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( preg_replace( '/:\d+$/', '', (string) wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : ''; // phpcs:ignore
	return in_array( $h, dccast_hosts(), true );
}

/* Config da loja (nome, categoria, página, cores, logo do cabeçalho) para o sistema de lojas parceiras (dcpt_cfg). */
add_filter(
	'dcpt_cfg',
	function ( $cfg ) {
		$c   = dccast_cfg();
		$sv  = dccast_logos_salvos();
		$logo = ! empty( $sv['omx_logo_branco'] ) ? (int) $sv['omx_logo_branco'] : ( ! empty( $sv['omx_logo'] ) ? (int) $sv['omx_logo'] : 0 );
		foreach ( dccast_hosts() as $h ) {
			$cfg[ $h ] = array(
				'nome'   => 'Cast',
				'meta'   => '_dc_cast',
				'cat'    => 'cast',
				'page'   => (int) get_option( 'dccast_page', 0 ),
				'logo'   => $logo,
				'icon'   => 0,
				'logo_h' => 66,
				'cor'    => $c['cor'],
				'fundo'  => '#14110D',
			);
		}
		return $cfg;
	}
);

/* Página própria (como a Casafinna): sem banner nem categorias do layout padrão, a página já tem os ícones. */
add_filter(
	'dclay_stores',
	function ( $s ) {
		foreach ( dccast_hosts() as $h ) {
			if ( isset( $s[ $h ] ) ) {
				$s[ $h ]['hero']  = false;
				$s[ $h ]['cats']  = false;
				$s[ $h ]['cor']   = dccast_cfg()['cor'];
			}
		}
		return $s;
	}
);

/* Fundo da página da loja (cor e, se tiver, imagem). */
add_action( 'wp_head', function () {
	if ( is_admin() || ! dccast_host_cast() ) {
		return;
	}
	$c   = dccast_cfg();
	$img = $c['fundo_img'] ? (string) wp_get_attachment_image_url( (int) $c['fundo_img'], 'full' ) : '';
	echo '<style id="dccast-fundo">body{background-color:' . esc_html( $c['fundo'] ) . '!important;' . ( $img ? 'background-image:url(' . esc_url( $img ) . ')!important;background-size:cover!important;background-attachment:fixed!important;background-position:center!important;' : '' ) . '}</style>'; // phpcs:ignore
}, 140 );

function dccast_logos_salvos() {
	$o = get_option( 'dccast_marca_logos' );
	return is_array( $o ) ? $o : array();
}

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Logos da Cast', 'Cast', 'manage_options', 'dccast-logos', 'dccast_logos_pagina', 'dashicons-format-image', 58 );
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'toplevel_page_dccast-logos' === $hook ) {
			wp_enqueue_media();
		}
	}
);

add_action(
	'admin_post_dccast_logos',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['dccast_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dccast_n'] ) ), 'dccast_logos' ) ) {
			wp_die( 'Sem permissão.' );
		}
		$ok  = array( 'image/png', 'image/webp', 'image/svg+xml', 'image/jpeg' );
		$new = array();
		foreach ( array_keys( dccast_logo_slots() ) as $k ) {
			$id = isset( $_POST[ $k ] ) ? absint( $_POST[ $k ] ) : 0;
			if ( $id && 'attachment' === get_post_type( $id ) && in_array( get_post_mime_type( $id ), $ok, true ) ) {
				$new[ $k ] = $id;
			}
		}
		update_option( 'dccast_marca_logos', $new, false );
		$cfg = dccast_cfg();
		foreach ( array( 'dccast_cor' => 'cor', 'dccast_fundo' => 'fundo' ) as $campo => $k ) {
			$v = isset( $_POST[ $campo ] ) ? sanitize_hex_color( wp_unslash( $_POST[ $campo ] ) ) : ''; // phpcs:ignore
			if ( $v ) {
				$cfg[ $k ] = $v;
			}
		}
		$fi = isset( $_POST['dccast_fundo_img'] ) ? absint( $_POST['dccast_fundo_img'] ) : 0;
		$cfg['fundo_img'] = ( $fi && wp_attachment_is_image( $fi ) ) ? $fi : 0;
		update_option( 'dccast_marca_cfg', $cfg, false );
		delete_transient( 'dccast_logos_v' );
		wp_safe_redirect( add_query_arg( array( 'page' => 'dccast-logos', 'salvo' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}
);

function dccast_logos_pagina() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$sv    = dccast_logos_salvos();
	$slots = dccast_logo_slots();
	$feitos = count( $sv );
	echo '<style>.dccast{max-width:1040px;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}.dccast h1{font-size:26px;margin:18px 0 6px}.dccast .sub{color:#5b6478;margin:0 0 18px;font-size:14px;line-height:1.5}'
		. '.dccast-g{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:16px}.dccast-c{background:#fff;border:1px solid #dfe3ea;border-radius:14px;padding:16px}'
		. '.dccast-c h3{margin:0 0 4px;font-size:15px}.dccast-c p{margin:0 0 12px;color:#667085;font-size:12.5px}'
		. '.dccast-v{height:130px;border-radius:10px;border:1px dashed #cfd5df;display:flex;align-items:center;justify-content:center;overflow:hidden;margin:0 0 12px;position:relative}'
		. '.dccast-v.claro{background:#fff}.dccast-v.escuro{background:#14110d}.dccast-v img{max-width:86%;max-height:86%;object-fit:contain}.dccast-v em{color:#98a2b3;font-size:12.5px;font-style:normal}'
		. '.dccast-b{display:flex;gap:8px}.dccast .button-primary{background:#b8902b;border-color:#b8902b}.dccast-ok{background:#ecfdf3;border-left:4px solid #12805c;padding:10px 14px;border-radius:8px;margin:0 0 16px}'
		. '.dccast-st{display:inline-block;font-size:12px;padding:2px 8px;border-radius:99px;background:#f2f4f7;color:#475467;margin-left:6px}.dccast-st.on{background:#d1fadf;color:#05603a}</style>';
	echo '<div class="wrap dccast"><h1>Identidade da Cast <span class="dccast-st ' . ( $feitos ? 'on' : '' ) . '">' . (int) $feitos . ' de 6 enviados</span></h1>';
	echo '<p class="sub">Envie os logos da marca. O sistema aplica o logo certo em cada produto (fundo claro ou escuro, formato horizontal ou vertical). Os dois <b>obrigatórios</b> são o <b>horizontal colorido</b> e o <b>horizontal branco</b>; os outros melhoram o resultado. Use <b>PNG com fundo transparente</b> (JPG deixa uma caixa branca em volta).</p>';
	if ( ! empty( $_GET['salvo'] ) ) { // phpcs:ignore
		echo '<div class="dccast-ok">Logos salvos.</div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="dccast_logos">' . wp_nonce_field( 'dccast_logos', 'dccast_n', true, false ) . '<div class="dccast-g">';
	foreach ( $slots as $k => $s ) {
		$id  = isset( $sv[ $k ] ) ? (int) $sv[ $k ] : 0;
		$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		if ( $id && ! $url ) {
			$url = (string) wp_get_attachment_url( $id );
		}
		echo '<div class="dccast-c" data-k="' . esc_attr( $k ) . '"><h3>' . esc_html( $s[0] ) . '</h3><p>' . esc_html( $s[1] ) . '</p>'
			. '<div class="dccast-v ' . esc_attr( $s[2] ) . '">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '<em>Nenhum arquivo</em>' ) . '</div>'
			. '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . (int) $id . '">'
			. '<div class="dccast-b"><button type="button" class="button dccast-esc">' . ( $url ? 'Trocar' : 'Escolher arquivo' ) . '</button><button type="button" class="button dccast-tir"' . ( $url ? '' : ' style="display:none"' ) . '>Tirar</button></div></div>';
	}
	echo '</div>';
	$cf   = dccast_cfg();
	$fid  = (int) $cf['fundo_img'];
	$furl = $fid ? (string) wp_get_attachment_image_url( $fid, 'medium' ) : '';
	echo '<h2 style="margin:26px 0 10px;font-size:18px">Aparência da loja</h2><div class="dccast-g">'
		. '<div class="dccast-c"><h3>Cor principal</h3><p>Botões e destaques da loja. Digite o código (ex.: #D4AF37) ou escolha no quadrado.</p><div style="display:flex;gap:8px;align-items:center"><input type="color" id="dccast-cor-s" value="' . esc_attr( $cf['cor'] ) . '" style="width:48px;height:40px;padding:0"><input type="text" name="dccast_cor" id="dccast-cor" value="' . esc_attr( $cf['cor'] ) . '" maxlength="7" style="width:120px;font-family:monospace"></div></div>'
		. '<div class="dccast-c"><h3>Cor de fundo</h3><p>Fundo da página da loja, em volta dos produtos.</p><div style="display:flex;gap:8px;align-items:center"><input type="color" id="dccast-fun-s" value="' . esc_attr( $cf['fundo'] ) . '" style="width:48px;height:40px;padding:0"><input type="text" name="dccast_fundo" id="dccast-fun" value="' . esc_attr( $cf['fundo'] ) . '" maxlength="7" style="width:120px;font-family:monospace"></div></div>'
		. '<div class="dccast-c" data-k="fundo_img"><h3>Imagem de fundo</h3><p>Opcional. Foto em volta dos produtos (fica no lugar da cor). Horizontal, ex.: 1920 x 1080 px.</p><div class="dccast-v claro">' . ( $furl ? '<img src="' . esc_url( $furl ) . '" alt="">' : '<em>Nenhum arquivo</em>' ) . '</div><input type="hidden" name="dccast_fundo_img" value="' . (int) $fid . '"><div class="dccast-b"><button type="button" class="button dccast-esc">' . ( $furl ? 'Trocar' : 'Escolher arquivo' ) . '</button><button type="button" class="button dccast-tir"' . ( $furl ? '' : ' style="display:none"' ) . '>Tirar</button></div></div>'
		. '</div><p style="margin-top:18px"><button type="submit" class="button button-primary button-hero">Salvar</button></p></form></div>';
	?>
<script>(function($){function sel(c){return $(c).closest('.dccast-c');}
$('.dccast-esc').on('click',function(e){e.preventDefault();var card=sel(this),fr=wp.media({title:'Logo da Cast',button:{text:'Usar este logo'},library:{type:['image']},multiple:false});
fr.on('select',function(){var a=fr.state().get('selection').first().toJSON();var u=(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url);card.find('input[type=hidden]').val(a.id);card.find('.dccast-v').html('<img src="'+u+'" alt="">');card.find('.dccast-esc').text('Trocar');card.find('.dccast-tir').show();});fr.open();});
[['#dccast-cor-s','#dccast-cor'],['#dccast-fun-s','#dccast-fun']].forEach(function(p){$(p[0]).on('input',function(){$(p[1]).val(this.value.toUpperCase());});$(p[1]).on('input',function(){if(/^#[0-9a-fA-F]{6}$/.test(this.value)){$(p[0]).val(this.value);}});});
$('.dccast-tir').on('click',function(e){e.preventDefault();var card=sel(this);card.find('input[type=hidden]').val('0');card.find('.dccast-v').html('<em>Nenhum arquivo</em>');card.find('.dccast-esc').text('Escolher arquivo');$(this).hide();});})(jQuery);</script>
	<?php
}

/* Montagem da loja (uma vez): categoria CAST com os produtos padrão da Ouro (1456) e a página inicial copiada da Casafinna. */
add_action(
	'init',
	function () {
		if ( get_option( 'dccast_setup_v1' ) || ! function_exists( 'wc_get_product' ) || ! term_exists( 1456, 'product_cat' ) || ! get_post( 109107 ) ) {
			return;
		}
		update_option( 'dccast_setup_v1', time(), false );

		// 1) categoria CAST
		$t = term_exists( 'cast', 'product_cat' );
		if ( ! $t ) {
			$t = wp_insert_term( 'CAST', 'product_cat', array( 'slug' => 'cast' ) );
		}
		$tid = is_array( $t ) ? (int) $t['term_id'] : (int) $t;
		if ( $tid ) {
			update_option( 'dccast_cat', $tid, false );
			$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => 1456, 'include_children' => false ) ) ) ); // phpcs:ignore
			foreach ( $ids as $id ) {
				wp_set_object_terms( (int) $id, array( $tid ), 'product_cat', true );
			}
			update_option( 'dccast_prod_n', count( $ids ), false );
		}

		// 2) página inicial: cópia da página da Casafinna
		if ( ! get_option( 'dccast_page' ) ) {
			$orig = get_post( 109107 );
			$novo = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Cast', 'post_name' => 'cast-loja', 'post_content' => $orig->post_content ), true );
			if ( $novo && ! is_wp_error( $novo ) ) {
				foreach ( get_post_meta( 109107 ) as $k => $v ) {
					if ( in_array( $k, array( '_edit_lock', '_edit_last', '_elementor_css' ), true ) ) {
						continue;
					}
					$val = maybe_unserialize( $v[0] );
					if ( '_elementor_data' === $k ) {
						$val = str_replace( 'Casafinna', 'Cast', wp_slash( (string) $v[0] ) );
						update_post_meta( $novo, $k, $val );
						continue;
					}
					update_post_meta( $novo, $k, wp_slash( $val ) );
				}
				update_option( 'dccast_page', (int) $novo, false );
			}
		}
		dccast_limpa_cache();
	},
	75
);

function dccast_limpa_cache() {
	foreach ( dccast_hosts() as $h ) {
		$dir = WP_CONTENT_DIR . '/cache/wp-rocket/' . $h;
		if ( ! is_dir( $dir ) ) {
			continue;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			if ( $f->isFile() && 0 === strpos( $f->getFilename(), 'index' ) ) {
				@unlink( $f->getPathname() ); // phpcs:ignore
			}
		}
	}
}
add_action( 'update_option_dccast_marca_logos', 'dccast_limpa_cache' );
add_action( 'add_option_dccast_marca_logos', 'dccast_limpa_cache' );
add_action( 'update_option_dccast_marca_cfg', 'dccast_limpa_cache' );
add_action( 'save_post_product', 'dccast_limpa_cache', 99 );
add_action( 'set_object_terms', function ( $o, $t, $tt, $tax ) {
	if ( 'product_cat' === $tax && 'product' === get_post_type( $o ) ) {
		dccast_limpa_cache();
	}
}, 10, 4 );
