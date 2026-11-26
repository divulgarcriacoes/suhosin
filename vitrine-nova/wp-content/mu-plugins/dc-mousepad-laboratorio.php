<?php
/**
 * Mouse pad no laboratório (26/11/2026): 10 fotos do BLAIR 93282-106 para a escola escolher a estampa.
 * Base: foto "Laboratório moderno de informática codando" (121663) + máscara do mouse pad (121664, feita no Corel).
 * Cada estampa vem do banco de imagens do Magnific (sem IA), entra na área da máscara em perspectiva
 * e recebe a sombra do mouse pad branco (mistura "Subtrair", transparência da própria máscara).
 * Faz uma foto por visita (opção dcmp_lab_pos) até terminar as 10.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dcmp_lab_estampas() {
	return array(
		1  => array( 'Tecnologia azul brilhante', 'https://downloadscdn5.magnific.com/download_vector/jpg/1/1035/2/2497/2497969_13417.jpg?token=exp=1795712462~hmac=44952f358a8e77d6c03d019cd1a9b8af' ),
		2  => array( 'Circuito futurista', 'https://downloadscdn5.magnific.com/download_vector/jpg/1/1035/1/1594/1594428_11592.jpg?token=exp=1795712463~hmac=22f305e0ee29abf6888dcd622727292b' ),
		3  => array( 'Placa-mãe azul', 'https://downloadscdn6.magnific.com/53876/125/124654.jpg?filename=motherboard-circuit-technology-background-gradient-blue.jpg&token=exp=1795712464~hmac=1dfd4b941ffc900592fd4e8a088a25d4' ),
		4  => array( 'Microchip', 'https://downloadscdn6.magnific.com/53876/111/110820.jpg?filename=smart-microchip-background-motherboard-closeup-technology-remix.jpg&token=exp=1795712465~hmac=90ee12cae56dfaeba12adff884c324d7' ),
		5  => array( 'Rede de conexões', 'https://downloadscdn6.magnific.com/1048/12/11867.jpg?filename=3d-abstract-background-with-network-connections-low-poly-plexus-design.jpg&token=exp=1795712904~hmac=18715d0c8d8171e4c8d0564f1f75404d' ),
		6  => array( 'Tecnologia abstrata', 'https://downloadscdn6.magnific.com/1194/618/617185.jpg?filename=abstract-technology-background-concept.jpg&token=exp=1795712905~hmac=126b88843b2cacf1ceda9f063b52a5aa' ),
		7  => array( 'Tinta colorida', 'https://downloadscdn6.magnific.com/53876/109/108486.jpg?filename=colorful-alcohol-ink-abstract-background.jpg&token=exp=1795712907~hmac=743545e24508bd7bc1443d6d337708b6' ),
		8  => array( 'Abstrato 3D roxo', 'https://downloadscdn5.magnific.com/download_vector/jpg/53/53876/20/20776/20776368_v1072-036-c.jpg?token=exp=1795712471~hmac=1553124414fdebacc4d62425017ca0fb' ),
		9  => array( 'Tinta roxa', 'https://downloadscdn6.magnific.com/53876/111/110811.jpg?filename=purple-alcohol-ink-abstract-background.jpg&token=exp=1795713078~hmac=3dc62a42a2a243b1747ff9dcc85b4e12' ),
		10 => array( 'Ondas vermelho e laranja', 'https://downloadscdn6.magnific.com/23/2148236/2148235959.jpg?filename=gleaming-wavy-red-orange-abstract-background.jpg&token=exp=1795712909~hmac=2d60f2a42286744b18ca1bcd79964165' ),
	);
}

// Homografia: 4 pontos de origem -> 4 de destino. Devolve os 8 coeficientes.
function dcmp_lab_h( $s, $d ) {
	$a = array();
	for ( $i = 0; $i < 4; $i++ ) {
		list( $x, $y ) = $s[ $i ];
		list( $u, $v ) = $d[ $i ];
		$a[] = array( $x, $y, 1, 0, 0, 0, -$u * $x, -$u * $y, $u );
		$a[] = array( 0, 0, 0, $x, $y, 1, -$v * $x, -$v * $y, $v );
	}
	for ( $c = 0; $c < 8; $c++ ) {
		$p = $c;
		for ( $r = $c + 1; $r < 8; $r++ ) {
			if ( abs( $a[ $r ][ $c ] ) > abs( $a[ $p ][ $c ] ) ) {
				$p = $r;
			}
		}
		$t       = $a[ $c ];
		$a[ $c ] = $a[ $p ];
		$a[ $p ] = $t;
		for ( $r = 0; $r < 8; $r++ ) {
			if ( $r === $c ) {
				continue;
			}
			$f = $a[ $r ][ $c ] / $a[ $c ][ $c ];
			for ( $k = $c; $k < 9; $k++ ) {
				$a[ $r ][ $k ] -= $f * $a[ $c ][ $k ];
			}
		}
	}
	$h = array();
	for ( $c = 0; $c < 8; $c++ ) {
		$h[] = $a[ $c ][8] / $a[ $c ][ $c ];
	}
	return $h;
}

function dcmp_lab_fazer( $n, $nome, $url ) {
	$foto = get_attached_file( 121663 );
	$masc = get_attached_file( 121664 );
	if ( ! $foto || ! $masc || ! file_exists( $foto ) || ! file_exists( $masc ) ) {
		return 'sem foto/máscara';
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$tmp = download_url( $url, 120 );
	if ( is_wp_error( $tmp ) ) {
		return 'download: ' . $tmp->get_error_message();
	}
	$src = @imagecreatefromstring( (string) file_get_contents( $tmp ) ); // phpcs:ignore
	@unlink( $tmp ); // phpcs:ignore
	if ( ! $src ) {
		return 'estampa inválida';
	}
	// Estampa: recorte quadrado do meio, 1000 px.
	$sw = imagesx( $src );
	$sh = imagesy( $src );
	$q  = min( $sw, $sh );
	$S  = 1000;
	$art = imagecreatetruecolor( $S, $S );
	imagecopyresampled( $art, $src, 0, 0, (int) ( ( $sw - $q ) / 2 ), (int) ( ( $sh - $q ) / 2 ), $S, $S, $q, $q );
	imagedestroy( $src );

	$im = imagecreatefromstring( (string) file_get_contents( $foto ) );
	// As medidas abaixo são da foto em 1600 px (o arquivo original tem 1634).
	if ( 1600 !== imagesx( $im ) ) {
		$i2 = imagecreatetruecolor( 1600, 1600 );
		imagecopyresampled( $i2, $im, 0, 0, 0, 0, 1600, 1600, imagesx( $im ), imagesy( $im ) );
		imagedestroy( $im );
		$im = $i2;
	}
	$mo = imagecreatefrompng( $masc );
	// A máscara do Corel está em 97% do tamanho da foto, no ponto (154, 930).
	$k  = 0.97;
	$mw = (int) round( imagesx( $mo ) * $k );
	$mh = (int) round( imagesy( $mo ) * $k );
	$m  = imagecreatetruecolor( $mw, $mh );
	imagealphablending( $m, false );
	imagesavealpha( $m, true );
	imagefill( $m, 0, 0, imagecolorallocatealpha( $m, 0, 0, 0, 127 ) );
	imagecopyresampled( $m, $mo, 0, 0, 0, 0, $mw, $mh, imagesx( $mo ), imagesy( $mo ) );
	imagedestroy( $mo );
	$ox = 154;
	$oy = 930;

	// Cantos do mouse pad na foto (topo, direita, frente, esquerda), um pouco para fora por causa dos cantos arredondados.
	$C  = array( array( 765, 930 ), array( 1421, 1081 ), array( 900, 1422 ), array( 154, 1149 ) );
	$cx = 0;
	$cy = 0;
	foreach ( $C as $p ) {
		$cx += $p[0] / 4;
		$cy += $p[1] / 4;
	}
	foreach ( $C as $i => $p ) {
		$C[ $i ] = array( $cx + ( $p[0] - $cx ) * 1.03, $cy + ( $p[1] - $cy ) * 1.03 );
	}
	// Foto -> estampa (canto de cima da estampa = topo do mouse pad).
	$h = dcmp_lab_h( $C, array( array( 0, 0 ), array( $S - 1, 0 ), array( $S - 1, $S - 1 ), array( 0, $S - 1 ) ) );

	// Claridade de referência do mouse pad branco (percentil 93).
	$ls = array();
	for ( $y = 0; $y < $mh; $y += 4 ) {
		for ( $x = 0; $x < $mw; $x += 4 ) {
			if ( ( ( imagecolorat( $m, $x, $y ) >> 24 ) & 0x7F ) < 20 ) {
				$c    = imagecolorat( $im, $x + $ox, $y + $oy );
				$ls[] = 0.299 * ( ( $c >> 16 ) & 255 ) + 0.587 * ( ( $c >> 8 ) & 255 ) + 0.114 * ( $c & 255 );
			}
		}
	}
	sort( $ls );
	$p93 = $ls ? max( 120, $ls[ (int) floor( count( $ls ) * 0.93 ) ] ) : 235;

	for ( $y = 0; $y < $mh; $y++ ) {
		for ( $x = 0; $x < $mw; $x++ ) {
			$ma = 127 - ( ( imagecolorat( $m, $x, $y ) >> 24 ) & 0x7F );
			if ( $ma <= 0 ) {
				continue;
			}
			$px = $x + $ox;
			$py = $y + $oy;
			$w  = $h[6] * $px + $h[7] * $py + 1;
			$u  = (int) round( ( $h[0] * $px + $h[1] * $py + $h[2] ) / $w );
			$v  = (int) round( ( $h[3] * $px + $h[4] * $py + $h[5] ) / $w );
			$u  = max( 0, min( $S - 1, $u ) );
			$v  = max( 0, min( $S - 1, $v ) );
			$a  = imagecolorat( $art, $u, $v );
			$b  = imagecolorat( $im, $px, $py );
			$br = ( $b >> 16 ) & 255;
			$bg = ( $b >> 8 ) & 255;
			$bb = $b & 255;
			$l  = min( 255, ( 0.299 * $br + 0.587 * $bg + 0.114 * $bb ) * 255 / $p93 );
			$sb = 255 - $l; // sombra do tecido (Subtrair)
			$al = $ma / 127; // transparência vem da própria máscara (Corel)
			$r  = max( 0, ( ( $a >> 16 ) & 255 ) - $sb );
			$g  = max( 0, ( ( $a >> 8 ) & 255 ) - $sb );
			$bl = max( 0, ( $a & 255 ) - $sb );
			$r  = (int) round( $br + ( $r - $br ) * $al );
			$g  = (int) round( $bg + ( $g - $bg ) * $al );
			$bl = (int) round( $bb + ( $bl - $bb ) * $al );
			imagesetpixel( $im, $px, $py, ( $r << 16 ) | ( $g << 8 ) | $bl );
		}
	}
	imagedestroy( $art );
	imagedestroy( $m );

	ob_start();
	imagejpeg( $im, null, 90 );
	$jpg = ob_get_clean();
	imagedestroy( $im );
	$arq = 'mouse-pad-laboratorio-' . sprintf( '%02d', $n ) . '.jpg';
	$up  = wp_upload_bits( $arq, null, $jpg );
	if ( ! empty( $up['error'] ) ) {
		return 'upload: ' . $up['error'];
	}
	$att = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'Mouse pad ' . sprintf( '%02d', $n ) . ' - ' . $nome,
			'post_status'    => 'inherit',
		),
		$up['file']
	);
	if ( ! $att || is_wp_error( $att ) ) {
		return 'anexo';
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $up['file'] ) );
	update_post_meta( $att, '_wp_attachment_image_alt', 'Mouse pad personalizado - estampa ' . $nome );
	return (int) $att;
}

add_action( 'init', function () {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	// v2: refaz as 10 (medida da foto corrigida e estampas sem texto).
	if ( ! get_option( 'dcmp_lab_v2' ) ) {
		update_option( 'dcmp_lab_v2', time(), false );
		foreach ( (array) get_option( 'dcmp_lab_ids', array() ) as $old ) {
			if ( is_int( $old ) && $old > 121664 && 0 === strpos( get_the_title( $old ), 'Mouse pad ' ) ) {
				wp_delete_attachment( $old, true );
			}
		}
		update_option( 'dcmp_lab_ids', array(), false );
		update_option( 'dcmp_lab_pos', 0, false );
	}
	// v4: coloca as 10 na galeria do BLAIR 93282-106 (60350), na ordem 01 a 10.
	if ( ! get_option( 'dcmp_lab_v4' ) && get_option( 'dcmp_lab_v3' ) && function_exists( 'wc_get_product' ) ) {
		$ids = array_filter( (array) get_option( 'dcmp_lab_ids', array() ), 'is_int' );
		$pr  = wc_get_product( 60350 );
		if ( $pr && 10 === count( $ids ) ) {
			update_option( 'dcmp_lab_v4', time(), false );
			ksort( $ids );
			$pr->set_gallery_image_ids( array_values( array_unique( array_merge( $pr->get_gallery_image_ids(), array_values( $ids ) ) ) ) );
			$pr->save();
		}
	}
	// v3: troca a 09 (estampa clara demais).
	if ( ! get_option( 'dcmp_lab_v3' ) && 10 === (int) get_option( 'dcmp_lab_pos', 0 ) ) {
		update_option( 'dcmp_lab_v3', time(), false );
		$ids = (array) get_option( 'dcmp_lab_ids', array() );
		if ( ! empty( $ids[9] ) && is_int( $ids[9] ) ) {
			wp_delete_attachment( $ids[9], true );
		}
		$lst9 = dcmp_lab_estampas();
		$ids[9] = dcmp_lab_fazer( 9, $lst9[9][0], $lst9[9][1] );
		update_option( 'dcmp_lab_ids', $ids, false );
		return;
	}
	$pos = (int) get_option( 'dcmp_lab_pos', 0 );
	$lst = dcmp_lab_estampas();
	if ( $pos >= count( $lst ) || get_transient( 'dcmp_lab_lock' ) ) {
		return;
	}
	set_transient( 'dcmp_lab_lock', 1, 120 );
	@set_time_limit( 120 ); // phpcs:ignore
	$n   = $pos + 1;
	$r   = dcmp_lab_fazer( $n, $lst[ $n ][0], $lst[ $n ][1] );
	$ids = (array) get_option( 'dcmp_lab_ids', array() );
	$ids[ $n ] = $r;
	update_option( 'dcmp_lab_ids', $ids, false );
	update_option( 'dcmp_lab_pos', $n, false );
	delete_transient( 'dcmp_lab_lock' );
}, 60 );
