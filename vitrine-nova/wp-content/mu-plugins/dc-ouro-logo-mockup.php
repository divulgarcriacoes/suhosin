<?php
/**
 * Plugin Name: DC · Ouro Moderno: logo da escola nos produtos (mockup automático)
 * Description: 2026-11-23. Na loja de cada franquia, os produtos aparecem com o logo DAQUELA escola aplicado na foto, seguindo a regra de aplicação do produto
 *   (posição, tamanho, giro e versão do logo: colorido, branco, laser ou 1 cor escura). A regra é ajustada no produto, aba "Franquias" > "Aplicação do logo".
 *   Sem regra salva, vale um padrão pelo tipo de produto (caneta, caderno, sacola, power bank, garrafa...). Produto com imagem manual da franquia (_dcom_fr_img) continua com a imagem manual.
 *   Também: campo "Logo branco" na Identidade da loja e aviso para a escola enviar os dois logos (colorido e branco).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Regras ---------- */

function dcmk_modos() {
	return array(
		'cor'    => 'Logo colorido (UV / digital / transfer)',
		'branco' => 'Logo branco (silk 1 cor em produto escuro)',
		'laser'  => 'Laser em metal (gravado claro)',
		'escuro' => '1 cor escura (laser em bambu/madeira, silk preto em kraft)',
		'queimado' => 'Laser em cortiça/madeira (marrom queimado)',
		'nada'   => 'Não aplicar logo neste produto',
	);
}

/* Padrão pelo tipo de produto (nome). x/y = centro em % da foto, w = largura em % da foto, r = giro em graus. */
function dcmk_padrao( $pid ) {
	$t     = ' ' . remove_accents( strtolower( (string) get_the_title( $pid ) ) ) . ' ';
	$tem   = function ( $lista ) use ( $t ) {
		foreach ( $lista as $p ) {
			if ( false !== strpos( $t, $p ) ) {
				return true;
			}
		}
		return false;
	};
	$metal = $tem( array( 'metal', 'aluminio', 'inox', ' aco ', 'aco inox' ) );
	$natur = $tem( array( 'bambu', 'madeira', 'cortica', 'palha', 'kraft', 'papelao' ) );
	$m     = $metal ? 'laser' : ( $natur ? 'escuro' : 'cor' );
	if ( $tem( array( 'caderno', 'caderneta', 'bloco', 'agenda', 'planner', 'risque' ) ) ) {
		return array( 'x' => 50, 'y' => 46, 'w' => 38, 'r' => 0, 'm' => $m );
	}
	if ( $tem( array( 'sacola', 'saco ', 'sacochila', 'mochila', 'bolsa', 'necessaire', 'pasta' ) ) ) {
		return array( 'x' => 50, 'y' => 58, 'w' => 38, 'r' => 0, 'm' => $m );
	}
	if ( $tem( array( 'caneta', 'lapis', 'lapiseira', 'marca texto' ) ) ) {
		return array( 'x' => 50, 'y' => 50, 'w' => 26, 'r' => -90, 'm' => $metal ? 'laser' : 'branco' );
	}
	if ( $tem( array( 'power bank', 'bateria', 'carregador' ) ) ) {
		return array( 'x' => 50, 'y' => 50, 'w' => 26, 'r' => 0, 'm' => $m );
	}
	if ( $tem( array( 'garrafa', 'squeeze', 'copo', 'caneca', 'termica', 'termico' ) ) ) {
		return array( 'x' => 50, 'y' => 55, 'w' => 24, 'r' => 0, 'm' => $m );
	}
	return array( 'x' => 50, 'y' => 50, 'w' => 28, 'r' => 0, 'm' => $m );
}

function dcmk_regra( $pid ) {
	$r = get_post_meta( (int) $pid, '_dcmk', true );
	$p = dcmk_padrao( $pid );
	if ( ! is_array( $r ) ) {
		$p['salva'] = 0;
		return $p;
	}
	$r          = array_merge( $p, $r );
	$r['salva'] = 1;
	return $r;
}

/* Gera (uma vez, com cache em uploads/dcmk/) o logo sem fundo e a versão branca.
   O fundo é tirado a partir das bordas (cor dos cantos), então os brancos de dentro do logo ficam. */
function dcmk_gerar( $att ) {
	$att = (int) $att;
	$src = $att ? get_attached_file( $att ) : '';
	if ( ! $src || ! file_exists( $src ) || ! function_exists( 'imagecreatefromstring' ) ) {
		return array();
	}
	$up  = wp_upload_dir();
	$dir = $up['basedir'] . '/dcmk';
	$url = $up['baseurl'] . '/dcmk';
	$key = $att . '-' . filemtime( $src ) . '-v5';
	$fc  = $dir . '/' . $key . '-cor.png';
	$fb  = $dir . '/' . $key . '-branco.png';
	if ( file_exists( $fc ) && file_exists( $fb ) ) {
		return array( 'cor' => $url . '/' . $key . '-cor.png', 'branco' => $url . '/' . $key . '-branco.png' );
	}
	$im = @imagecreatefromstring( (string) file_get_contents( $src ) ); // phpcs:ignore
	if ( ! $im ) {
		return array();
	}
	$w  = imagesx( $im );
	$h  = imagesy( $im );
	$k  = min( 1, 1600 / max( $w, $h ) );
	$nw = max( 1, (int) round( $w * $k ) );
	$nh = max( 1, (int) round( $h * $k ) );
	$t  = imagecreatetruecolor( $nw, $nh );
	imagealphablending( $t, false );
	imagesavealpha( $t, true );
	imagefill( $t, 0, 0, imagecolorallocatealpha( $t, 0, 0, 0, 127 ) );
	imagecopyresampled( $t, $im, 0, 0, 0, 0, $nw, $nh, $w, $h );
	imagedestroy( $im );
	// Fundo = cor dos cantos (ou transparente). O preenchimento parte das bordas e passa pelo fundo
	// e pelo contorno branco (logo "adesivo"); o que está dentro do logo fica.
	$cs = array( array( 0, 0 ), array( $nw - 1, 0 ), array( 0, $nh - 1 ), array( $nw - 1, $nh - 1 ) );
	$r  = 0;
	$g  = 0;
	$b  = 0;
	$tr = 0;
	foreach ( $cs as $c ) {
		$v   = imagecolorat( $t, $c[0], $c[1] );
		$tr += ( ( $v >> 24 ) & 0x7F ) > 100 ? 1 : 0;
		$r  += ( $v >> 16 ) & 0xFF;
		$g  += ( $v >> 8 ) & 0xFF;
		$b  += $v & 0xFF;
	}
	$opaco = $tr < 3;
	$r    /= 4;
	$g    /= 4;
	$b    /= 4;
	// 0 = fundo (some), 1 = borda suave (meio transparente, não espalha), 2 = logo.
	$tipo = function ( $x, $y ) use ( $t, $r, $g, $b, $opaco ) {
		$v  = imagecolorat( $t, $x, $y );
		$a  = ( $v >> 24 ) & 0x7F;
		$cr = ( $v >> 16 ) & 0xFF;
		$cg = ( $v >> 8 ) & 0xFF;
		$cb = $v & 0xFF;
		if ( $a >= 100 ) {
			return array( 0, 127 );
		}
		$dw = sqrt( pow( 255 - $cr, 2 ) + pow( 255 - $cg, 2 ) + pow( 255 - $cb, 2 ) );
		$db = $opaco ? sqrt( pow( $cr - $r, 2 ) + pow( $cg - $g, 2 ) + pow( $cb - $b, 2 ) ) : 999;
		$d  = min( $dw, $db );
		if ( $d <= 40 ) {
			return array( 0, 127 );
		}
		if ( $d <= 70 ) {
			return array( 1, max( $a, (int) round( 127 * ( 70 - $d ) / 30 ) ) );
		}
		return array( 2, $a );
	};
	$n      = $nw * $nh;
	$vis    = str_repeat( "\0", $n );
	$fila   = array();
	for ( $x = 0; $x < $nw; $x++ ) {
		$fila[] = array( $x, 0 );
		$fila[] = array( $x, $nh - 1 );
	}
	for ( $y = 0; $y < $nh; $y++ ) {
		$fila[] = array( 0, $y );
		$fila[] = array( $nw - 1, $y );
	}
	$transp = imagecolorallocatealpha( $t, 255, 255, 255, 127 );
	// Logo que já vem com fundo transparente (PNG): fica como está, inclusive o contorno branco das letras.
	while ( $opaco && $fila ) {
		list( $x, $y ) = array_pop( $fila );
		$i = $y * $nw + $x;
		if ( "\0" !== $vis[ $i ] ) {
			continue;
		}
		$vis[ $i ] = "\1";
		$tp        = $tipo( $x, $y );
		if ( 2 === $tp[0] ) {
			continue;
		}
		if ( 1 === $tp[0] ) {
			$v = imagecolorat( $t, $x, $y );
			imagesetpixel( $t, $x, $y, imagecolorallocatealpha( $t, ( $v >> 16 ) & 0xFF, ( $v >> 8 ) & 0xFF, $v & 0xFF, $tp[1] ) );
			continue;
		}
		imagesetpixel( $t, $x, $y, $transp );
		if ( $x > 0 ) {
			$fila[] = array( $x - 1, $y );
		}
		if ( $x < $nw - 1 ) {
			$fila[] = array( $x + 1, $y );
		}
		if ( $y > 0 ) {
			$fila[] = array( $x, $y - 1 );
		}
		if ( $y < $nh - 1 ) {
			$fila[] = array( $x, $y + 1 );
		}
	}
	wp_mkdir_p( $dir );
	imagepng( $t, $fc );
	// Versão branca: mesma forma, tudo branco.
	for ( $y = 0; $y < $nh; $y++ ) {
		for ( $x = 0; $x < $nw; $x++ ) {
			$v = imagecolorat( $t, $x, $y );
			$a = ( $v >> 24 ) & 0x7F;
			if ( $a >= 127 ) {
				continue;
			}
			// Detalhes brancos de dentro do logo viram vazado (como no silk de 1 cor).
			$dw = sqrt( pow( 255 - ( ( $v >> 16 ) & 0xFF ), 2 ) + pow( 255 - ( ( $v >> 8 ) & 0xFF ), 2 ) + pow( 255 - ( $v & 0xFF ), 2 ) );
			if ( $dw < 90 ) {
				$a = max( $a, (int) round( 127 * ( 90 - max( 40, $dw ) ) / 50 ) ); // vazado suave
			}
			imagesetpixel( $t, $x, $y, imagecolorallocatealpha( $t, 255, 255, 255, $a ) );
		}
	}
	imagepng( $t, $fb );
	imagedestroy( $t );
	return array( 'cor' => $url . '/' . $key . '-cor.png', 'branco' => $url . '/' . $key . '-branco.png' );
}

/* Logos da escola: colorido (omx_logo) e branco (omx_logo_branco, ou gerado a partir do colorido). */
function dcmk_logos( $fr ) {
	$fr  = function_exists( 'omx_parent' ) ? (int) omx_parent( (int) $fr ) : (int) $fr;
	$out = array( 'cor' => '', 'branco' => '', 'png' => false );
	$lg  = (int) get_user_meta( $fr, 'omx_logo', true );
	$lb  = (int) get_user_meta( $fr, 'omx_logo_branco', true );
	if ( $lg ) {
		$gen = dcmk_gerar( $lg );
		if ( $gen ) {
			$out['cor']    = $gen['cor'];
			$out['branco'] = $gen['branco'];
			$out['png']    = true;
		} else {
			$out['cor'] = (string) wp_get_attachment_image_url( $lg, '1536x1536' );
			$out['png'] = in_array( get_post_mime_type( $lg ), array( 'image/png', 'image/webp' ), true );
		}
	}
	if ( $lb ) {
		$out['branco'] = (string) wp_get_attachment_image_url( $lb, '1536x1536' ); // enviado pela escola: usa como está
	}
	// Logo vertical (para áreas altas: caneta em pé, squeeze, etiqueta vertical...).
	$lv  = (int) get_user_meta( $fr, 'omx_logo_vertical', true );
	$lvb = (int) get_user_meta( $fr, 'omx_logo_vertical_branco', true );
	if ( $lv ) {
		$gv = dcmk_gerar( $lv );
		if ( $gv ) {
			$out['v']  = $gv['cor'];
			$out['vb'] = $gv['branco'];
		} else {
			$out['v'] = (string) wp_get_attachment_image_url( $lv, '1536x1536' );
		}
	}
	if ( $lvb ) {
		$out['vb'] = (string) wp_get_attachment_image_url( $lvb, '1536x1536' );
	}
	// Colorido para fundo escuro (símbolo colorido + letras brancas): horizontal e vertical.
	foreach ( array( 'ce' => 'omx_logo_escuro', 've' => 'omx_logo_vertical_escuro' ) as $ck => $cm ) {
		$ca = (int) get_user_meta( $fr, $cm, true );
		if ( $ca ) {
			$cg          = dcmk_gerar( $ca );
			$out[ $ck ] = $cg ? $cg['cor'] : (string) wp_get_attachment_image_url( $ca, '1536x1536' );
		}
	}
	return $out;
}

/* Claridade (0-255) do meio da área do molde numa foto. Guardada na própria imagem. */
function dcmk_lum( $att, $q ) {
	$att = (int) $att;
	if ( ! $att || ! is_array( $q ) || 8 !== count( $q ) || ! function_exists( 'imagecreatefromstring' ) ) {
		return null;
	}
	$k = '_dcmk_lum_' . substr( md5( implode( ',', $q ) ), 0, 8 );
	$c = get_post_meta( $att, $k, true );
	if ( '' !== $c ) {
		return (int) $c < 0 ? null : (int) $c;
	}
	$f  = get_attached_file( $att );
	$md = image_get_intermediate_size( $att, 'medium' );
	if ( $md && ! empty( $md['path'] ) ) {
		$up = wp_upload_dir();
		if ( file_exists( $up['basedir'] . '/' . $md['path'] ) ) {
			$f = $up['basedir'] . '/' . $md['path'];
		}
	}
	$v = -1;
	$im = ( $f && file_exists( $f ) ) ? @imagecreatefromstring( (string) file_get_contents( $f ) ) : false; // phpcs:ignore
	if ( $im ) {
		$w  = imagesx( $im );
		$h  = imagesy( $im );
		$s  = max( $w, $h );
		$ox = ( $s - $w ) / 2;
		$oy = ( $s - $h ) / 2;
		$xs = array( $q[0], $q[2], $q[4], $q[6] );
		$ys = array( $q[1], $q[3], $q[5], $q[7] );
		$cx = ( min( $xs ) + max( $xs ) ) / 2;
		$cy = ( min( $ys ) + max( $ys ) ) / 2;
		$hw = ( max( $xs ) - min( $xs ) ) * 0.3;
		$hh = ( max( $ys ) - min( $ys ) ) * 0.3;
		$sum = 0;
		$n   = 0;
		for ( $i = 0; $i <= 10; $i++ ) {
			for ( $j = 0; $j <= 10; $j++ ) {
				$px = (int) round( ( $cx - $hw + 2 * $hw * $i / 10 ) / 100 * $s - $ox );
				$py = (int) round( ( $cy - $hh + 2 * $hh * $j / 10 ) / 100 * $s - $oy );
				if ( $px < 0 || $py < 0 || $px >= $w || $py >= $h ) {
					continue;
				}
				$cc = imagecolorsforindex( $im, imagecolorat( $im, $px, $py ) );
				if ( $cc['alpha'] > 100 ) {
					continue;
				}
				$sum += 0.299 * $cc['red'] + 0.587 * $cc['green'] + 0.114 * $cc['blue'];
				$n++;
			}
		}
		if ( $n ) {
			$v = (int) round( $sum / $n );
		}
		imagedestroy( $im );
	}
	update_post_meta( $att, $k, $v );
	return $v < 0 ? null : $v;
}

/* Nome-base do arquivo (sem tamanho, sem -scaled, sem extensão) -> casa com a imagem na tela. */
function dcmk_base( $att ) {
	return preg_replace( '/-scaled$/', '', (string) pathinfo( (string) get_attached_file( (int) $att ), PATHINFO_FILENAME ) );
}

/* CSS dos modos (o mesmo na loja e no editor do produto). */
function dcmk_css() {
	return '.dcmk{position:absolute;pointer-events:none;z-index:2;overflow:visible}.dcmk img{position:absolute;left:0;top:0;width:100%!important;height:auto!important;max-width:none!important;max-height:none!important;min-height:0!important;aspect-ratio:auto!important;background:none!important;padding:0!important;object-fit:fill!important;margin:0!important;border:0!important;box-shadow:none!important;border-radius:0!important;opacity:1;transform-origin:50% 50%}'
		. '.dcmk[data-m=cor] img{opacity:.96}'
		. '.dcmk[data-m=branco] img{opacity:.95}.dcmk[data-m=branco] img.sil{filter:brightness(0) invert(1)}'
		. '.dcmk[data-m=laser] img{filter:brightness(0) invert(.9);opacity:.88}'
		. '.dcmk[data-m=escuro] img{filter:brightness(0);opacity:.72;mix-blend-mode:multiply}'
		. '.dcmk[data-m=queimado] img{filter:brightness(0) invert(48.2%) sepia(100.4%) saturate(2078%) hue-rotate(344.2deg) brightness(41.7%) contrast(81.8%);opacity:.9;mix-blend-mode:multiply}'
		. '.dcmk[data-m=queimado] img.lum{filter:url(#dcmkq);opacity:.92}'
		. '.dcmk.dob{mix-blend-mode:multiply}.dcmk.dob[data-m=branco]{mix-blend-mode:normal}'
		. '.dcmk.q img{width:var(--w)!important;height:var(--h)!important;transform-origin:0 0!important}';
}

/* JS que posiciona o logo sobre a foto (usado na loja e no editor). */
function dcmk_js_aplicar() {
	return 'function dcmkH(s,d){var A=[],B=[],i,c,r,k;for(i=0;i<4;i++){var x=s[i][0],y=s[i][1],u=d[i][0],v=d[i][1];A.push([x,y,1,0,0,0,-u*x,-u*y]);B.push(u);A.push([0,0,0,x,y,1,-v*x,-v*y]);B.push(v);}for(c=0;c<8;c++){var p=c;for(r=c+1;r<8;r++){if(Math.abs(A[r][c])>Math.abs(A[p][c]))p=r;}var t=A[c];A[c]=A[p];A[p]=t;t=B[c];B[c]=B[p];B[p]=t;for(r=0;r<8;r++){if(r===c)continue;var f=A[r][c]/A[c][c];for(k=c;k<8;k++)A[r][k]-=f*A[c][k];B[r]-=f*B[c];}}var h=[];for(c=0;c<8;c++)h.push(B[c]/A[c][c]);return h;}'
		. 'function dcmkAp(h,x,y){var w=h[6]*x+h[7]*y+1;return[(h[0]*x+h[1]*y+h[2])/w,(h[3]*x+h[4]*y+h[5])/w];}'
		. 'function dcmkD(a,b){return Math.sqrt(Math.pow(a[0]-b[0],2)+Math.pow(a[1]-b[1],2));}'
		. 'function dcmkPos(box,img,R){var c=box.parentNode;if(!c)return;var ir=img.getBoundingClientRect(),cr=c.getBoundingClientRect();if(!ir.width)return;var s=Math.min(ir.width,ir.height),ox=ir.left-cr.left+(ir.width-s)/2+c.scrollLeft,oy=ir.top-cr.top+(ir.height-s)/2+c.scrollTop;var li=box.firstChild;'
		. 'if(R.q&&R.q.length===8){box.classList.add("q");box.style.left=ox+"px";box.style.top=oy+"px";box.style.width=s+"px";box.style.height=s+"px";if(!li||!li.naturalWidth)return;var Lw=li.naturalWidth,Lh=li.naturalHeight,P=[],i;for(i=0;i<4;i++)P.push([R.q[i*2]*s/100,R.q[i*2+1]*s/100]);var k=((Math.round((R.r||0)/90)%4)+4)%4,Q=[];for(i=0;i<4;i++)Q.push(P[(i+k)%4]);var Wa=(dcmkD(Q[0],Q[1])+dcmkD(Q[3],Q[2]))/2,Ha=(dcmkD(Q[0],Q[3])+dcmkD(Q[1],Q[2]))/2;if(Wa<0.5||Ha<0.5){box.style.display="none";return;}box.style.display="";var a=Lw/Lh,pd=(R.w>0&&R.w<=100?R.w:88)/100,sc=Math.min(pd,pd*Ha*a/Wa),uw=sc,uh=sc*Wa/(a*Ha),u0=(1-uw)/2,v0=(1-uh)/2;var Hq=dcmkH([[0,0],[1,0],[1,1],[0,1]],Q);var T=[dcmkAp(Hq,u0,v0),dcmkAp(Hq,u0+uw,v0),dcmkAp(Hq,u0+uw,v0+uh),dcmkAp(Hq,u0,v0+uh)];var tw=Math.max(dcmkD(T[0],T[1]),dcmkD(T[0],T[3])*a),dp=window.devicePixelRatio||1,f=Math.min(1,Math.max(1.25*tw,40)/Lw);Lw=Lw*f;Lh=Lh*f;var h=dcmkH([[0,0],[Lw,0],[Lw,Lh],[0,Lh]],T),M="matrix3d("+[h[0],h[3],0,h[6],h[1],h[4],0,h[7],0,0,1,0,h[2],h[5],0,1].join(",")+")";li.style.setProperty("width",Lw+"px","important");li.style.setProperty("height",Lh+"px","important");li.style.setProperty("transform-origin","0 0","important");li.style.top="0px";li.style.setProperty("transform",M,"important");var cv=box.querySelector("canvas.dcq");try{if(!cv){cv=document.createElement("canvas");cv.className="dcq";box.appendChild(cv);}var cw=Math.max(1,Math.round(Lw*dp)),ch=Math.max(1,Math.round(Lh*dp));if(cv.width!==cw||cv.height!==ch||cv.getAttribute("data-s")!==li.currentSrc){var sr=li,sw=li.naturalWidth,sh=li.naturalHeight;while(sw/2>=cw&&sw>4){var t=document.createElement("canvas");t.width=Math.round(sw/2);t.height=Math.round(sh/2);var tx=t.getContext("2d");tx.imageSmoothingQuality="high";tx.drawImage(sr,0,0,t.width,t.height);sr=t;sw=t.width;sh=t.height;}cv.width=cw;cv.height=ch;var cx=cv.getContext("2d");cx.imageSmoothingQuality="high";cx.clearRect(0,0,cw,ch);cx.drawImage(sr,0,0,cw,ch);var lz=box.getAttribute("data-m")==="laser";if(lz){cx.globalCompositeOperation="source-in";var gr=cx.createLinearGradient(0,0,cw,ch);gr.addColorStop(0,"#f5f6f7");gr.addColorStop(.3,"#cacdd1");gr.addColorStop(.5,"#eef0f2");gr.addColorStop(.72,"#babec2");gr.addColorStop(1,"#e2e4e6");cx.fillStyle=gr;cx.fillRect(0,0,cw,ch);cx.globalCompositeOperation="source-atop";cx.globalAlpha=.08;for(var yy=0;yy<ch;yy+=2){cx.fillStyle=(yy%4)?"#ffffff":"#a4a8ac";cx.fillRect(0,yy,cw,1);}cx.globalAlpha=1;cx.globalCompositeOperation="source-over";}cv.setAttribute("data-s",li.currentSrc);}var cs=getComputedStyle(li),lz2=box.getAttribute("data-m")==="laser";cv.style.cssText="position:absolute;left:0;top:0;margin:0;padding:0;border:0;max-width:none;pointer-events:none;width:"+Lw+"px;height:"+Lh+"px;transform-origin:0 0;transform:"+M+";filter:"+(lz2?"drop-shadow(0 0 .5px rgba(0,0,0,.5)) drop-shadow(0 .5px 0 rgba(255,255,255,.35))":cs.filter)+";opacity:"+(lz2?"0.96":cs.opacity)+";mix-blend-mode:"+cs.mixBlendMode;li.style.setProperty("visibility","hidden","important");}catch(e){if(cv){cv.remove();}li.style.removeProperty("visibility");}return;}'
		. 'box.classList.remove("q");var cq=box.querySelector("canvas.dcq");if(cq){cq.remove();}if(li){li.style.removeProperty("visibility");}if(li){li.style.removeProperty("width");li.style.removeProperty("height");li.style.removeProperty("transform-origin");}var w=s*R.w/100;box.style.width=w+"px";box.style.height="0px";box.style.left=(ox+s*R.x/100-w/2)+"px";box.style.top=(oy+s*R.y/100)+"px";if(li){var hh=li.naturalHeight&&li.naturalWidth?w*li.naturalHeight/li.naturalWidth:w*.35;li.style.setProperty("width",w+"px","important");li.style.setProperty("height",hh+"px","important");li.style.top=(-hh/2)+"px";li.style.transform="rotate("+(R.r||0)+"deg)";}}'
		. 'function dcmkLogo(R,L,lum){if(R.m==="nada")return null;if(R.l==="v"&&L.v){L={cor:L.v,branco:L.vb||"",ce:L.ve||"",png:true};}else if(R.l!=="h"&&R.q&&R.q.length===8&&L.v){var q=R.q,w1=Math.hypot(q[2]-q[0],q[3]-q[1])+Math.hypot(q[4]-q[6],q[5]-q[7]),h1=Math.hypot(q[6]-q[0],q[7]-q[1])+Math.hypot(q[4]-q[2],q[5]-q[3]);if(((Math.round((R.r||0)/90)%2)+2)%2===1){var t0=w1;w1=h1;h1=t0;}if(h1>w1*1.2){L={cor:L.v,branco:L.vb||"",ce:L.ve||"",png:true};}}var u=L.cor,sil=false,m=R.m;if(m==="cor"&&L.ce&&(R.t==="escuro"||(R.t!=="claro"&&lum!=null&&lum<120))){u=L.ce;}if(m==="branco"){if(L.branco){u=L.branco;}else if(L.png){sil=true;}else{m="cor";}}if((m==="laser"||m==="escuro"||m==="queimado")&&L.branco){u=L.branco;}if((m==="laser"||m==="escuro"||m==="queimado")&&!L.png&&!L.branco){m="cor";}if(!u)return null;var lu=(m==="queimado"&&u!==L.branco);if(lu&&!document.getElementById("dcmkq")){var ns="http://www.w3.org/2000/svg",sv=document.createElementNS(ns,"svg"),fl=document.createElementNS(ns,"filter"),fm=document.createElementNS(ns,"feColorMatrix");sv.setAttribute("width","0");sv.setAttribute("height","0");sv.setAttribute("aria-hidden","true");sv.style.position="absolute";fl.setAttribute("id","dcmkq");fl.setAttribute("color-interpolation-filters","sRGB");fm.setAttribute("type","matrix");fm.setAttribute("values","0 0 0 0 .431 0 0 0 0 .231 0 0 0 0 .141 -.2126 -.7152 -.0722 1 0");fl.appendChild(fm);sv.appendChild(fl);document.body.appendChild(sv);}var d=document.createElement("span");d.className="dcmk";d.setAttribute("data-m",m);d.setAttribute("aria-hidden","true");var i=document.createElement("img");i.src=u;i.alt="";i.decoding="async";if(sil)i.className="sil";if(lu)i.className="lum";if(R.d)d.className+=" dob";d.appendChild(i);return d;}';
}

/* ---------- Foto de um item só (sem o conjunto de cores) ---------- */

/* Escolhe, entre a foto principal e a galeria, uma foto de UM produto: arquivo com o nome da cor
   (ex.: Sacola-TNT-AZUL-23125, 12513_AZU, ER165B-PRE). Preferência: azul, preto, depois a primeira cor.
   Pode ser fixada à mão no produto (meta _dcmk_foto = id da imagem; 0 ou vazio = automático). */
function dcmk_foto_unica( $pid ) {
	$pid = (int) $pid;
	$fix = (int) get_post_meta( $pid, '_dcmk_foto', true );
	if ( $fix && wp_attachment_is_image( $fix ) ) {
		return $fix;
	}
	$ids = array_filter( array_map( 'intval', array_merge( array( (int) get_post_meta( $pid, '_thumbnail_id', true ) ), explode( ',', (string) get_post_meta( $pid, '_product_image_gallery', true ) ) ) ) );
	$cor = '(AZUL|PRETO|BRANCO|VERMELHO|VERDE|AMARELO|LARANJA|ROXO|ROSA|CINZA|KRAFT|BEGE|MARROM|VINHO|PINK|LILAS|CREME|NATURAL|AZU|PRE|BRA|VER|VD|AMA|ROS|ROX|PRA)';
	$pref = array( 'AZUL' => 1, 'AZU' => 1, 'PRETO' => 2, 'PRE' => 2 );
	$best = 0;
	$bn   = 99;
	// Spot: fotos por cor com o código da cor no nome (93717_114.jpg). A tabela de cores diz qual código é azul/preto.
	$tab = json_decode( (string) get_post_meta( $pid, '_dc_color_stock_table', true ), true );
	$cod = array();
	foreach ( is_array( $tab ) ? $tab : array() as $t ) {
		if ( ! empty( $t['sku'] ) && preg_match( '/-(\d{3})$/', (string) $t['sku'], $mm ) ) {
			$l          = remove_accents( strtolower( (string) ( isset( $t['label'] ) ? $t['label'] : '' ) ) );
			$cod[ $mm[1] ] = ( false !== strpos( $l, 'azul' ) && false === strpos( $l, 'claro' ) ) ? 1 : ( false !== strpos( $l, 'preto' ) ? 2 : 5 );
		}
	}
	foreach ( $ids as $a ) {
		$f = pathinfo( (string) get_attached_file( $a ), PATHINFO_FILENAME );
		if ( preg_match( '/_(\d{3})(?:-[a-z0-9]+)?$/i', $f, $mm ) && ! preg_match( '/(_amb|-c)(?:-\d+)?$/', $f ) ) {
			$n = isset( $cod[ $mm[1] ] ) ? $cod[ $mm[1] ] : 5;
			if ( $n < $bn ) {
				$bn   = $n;
				$best = $a;
			}
			continue;
		}
		if ( ! preg_match( '/(?:^|[-_])' . $cor . '(?:[-_]|$)/', $f, $m ) || preg_match( '/d\d(?:[-_]|$)/', $f ) ) {
			continue;
		}
		$n = isset( $pref[ $m[1] ] ) ? $pref[ $m[1] ] : 5;
		if ( preg_match( '/AZUL-(CLARO|BEBE)/', $f ) ) {
			$n = 4;
		}
		if ( $n < $bn ) {
			$bn   = $n;
			$best = $a;
		}
	}
	return $best;
}

/* Na loja da franquia (e no produto aberto por ela), mostra a foto de um item só. A arte manual da franquia continua valendo. */
function dcmk_img_unica( $img_id, $product ) {
	if ( ( is_admin() && ! wp_doing_ajax() ) || ! is_object( $product ) || ! function_exists( 'dcom_fr_atual' ) ) {
		return $img_id;
	}
	$fr = dcom_fr_atual();
	if ( ! $fr && function_exists( 'dccf_host_casafinna' ) && dccf_host_casafinna() ) {
		$fr = -1; // Casafinna: usa a mesma foto de um item só (ou a fixada no produto) das lojas Ouro
	}
	if ( ! $fr ) {
		return $img_id;
	}
	$pid = $product->is_type( 'variation' ) ? (int) $product->get_parent_id() : (int) $product->get_id();
	$man = function_exists( 'dcom_fr_mapa' ) ? dcom_fr_mapa( $pid ) : array();
	if ( ! empty( $man[ $fr ] ) ) {
		return $img_id;
	}
	$u = dcmk_foto_unica( $pid );
	return $u ? $u : $img_id;
}
add_filter( 'woocommerce_product_get_image_id', 'dcmk_img_unica', 35, 2 );

/* ---------- Loja da franquia ---------- */

add_action(
	'wp_head',
	function () {
		$fr = ! empty( $GLOBALS['dcom_fr_atual'] ) ? (int) $GLOBALS['dcom_fr_atual'] : ( ( function_exists( 'dcom_fr_atual' ) && function_exists( 'is_product' ) && is_product() ) ? (int) dcom_fr_atual() : 0 );
		if ( ! $fr || is_admin() ) {
			return;
		}
		$L = dcmk_logos( $fr );
		if ( '' === $L['cor'] && '' === $L['branco'] ) {
			return;
		}
		// Produtos da vitrine da franquia: Padrão das franquias (1456) + categoria da própria franquia.
		$cats = array( 1456 );
		if ( function_exists( 'dcom_loja_garantir_cat' ) ) {
			$c = (int) dcom_loja_garantir_cat( $fr );
			if ( $c ) {
				$cats[] = $c;
			}
		}
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 600,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cats ) ), // phpcs:ignore
			)
		);
		$mapa = array();
		foreach ( $ids as $pid ) {
			$man = function_exists( 'dcom_fr_mapa' ) ? dcom_fr_mapa( $pid ) : array();
			if ( ! empty( $man[ $fr ] ) ) {
				continue; // imagem manual desta franquia: fica a manual
			}
			$r = dcmk_regra( $pid );
			if ( 'nada' === $r['m'] ) {
				continue;
			}
			if ( empty( $r['q'] ) || ! is_array( $r['q'] ) || 8 !== count( $r['q'] ) ) {
				continue; // só mostra o logo nos produtos que já têm o molde do Corel
			}
			$mapa[ (int) $pid ] = array( 'x' => (float) $r['x'], 'y' => (float) $r['y'], 'w' => (float) $r['w'], 'r' => (float) $r['r'], 'm' => (string) $r['m'], 's' => (string) get_post_field( 'post_name', $pid ) );
			if ( ! empty( $r['q'] ) && is_array( $r['q'] ) && 8 === count( $r['q'] ) ) {
				$mapa[ (int) $pid ]['q'] = array_map( 'floatval', $r['q'] );
			}
			if ( ! empty( $r['l'] ) && in_array( $r['l'], array( 'h', 'v' ), true ) ) {
				$mapa[ (int) $pid ]['l'] = $r['l'];
			}
			if ( ! empty( $r['t'] ) && in_array( $r['t'], array( 'claro', 'escuro' ), true ) ) {
				$mapa[ (int) $pid ]['t'] = $r['t'];
			}
			if ( ! empty( $r['qx'] ) && is_array( $r['qx'] ) ) {
				$mapa[ (int) $pid ]['qx'] = array_values( array_filter( array_map( function ( $qq ) {
					return ( is_array( $qq ) && 8 === count( $qq ) ) ? array_map( 'floatval', $qq ) : null;
				}, $r['qx'] ) ) );
			}
		}
		if ( ! $mapa ) {
			return;
		}
		// Claridade da área do molde em cada foto (produto claro = logo de letras coloridas; escuro = versão para fundo escuro).
		$lu = array();
		if ( ! empty( $L['ce'] ) || ! empty( $L['ve'] ) ) {
			$pp = ( function_exists( 'is_product' ) && is_product() ) ? (int) get_queried_object_id() : 0;
			foreach ( $mapa as $pid => $r ) {
				$pr   = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
				$atts = $pr ? array( (int) $pr->get_image_id() ) : array();
				if ( $pid === $pp && $pr ) {
					$atts = array_merge( $atts, array( (int) get_post_thumbnail_id( $pid ) ), array_map( 'intval', $pr->get_gallery_image_ids() ) );
				}
				foreach ( array_unique( array_filter( $atts ) ) as $a ) {
					$lv = dcmk_lum( $a, $r['q'] );
					if ( null !== $lv ) {
						$lu[ dcmk_base( $a ) ] = $lv;
					}
				}
			}
		}
		echo '<style id="dcmk-css">' . dcmk_css() . '</style>'; // phpcs:ignore
		echo '<script id="dcmk-js">(function(){var M=' . wp_json_encode( $mapa ) . ',L=' . wp_json_encode( $L ) . ',LU=' . wp_json_encode( (object) $lu ) . ',P=' . ( ( function_exists( 'is_product' ) && is_product() ) ? (int) get_queried_object_id() : 0 ) . ',S={},B=[];function dcmkB(i){var s=((i.getAttribute("data-large_image")||i.getAttribute("data-src")||i.currentSrc||i.src||"").split("/").pop()||"").split("?")[0];return s.replace(/\\.[a-z0-9]+$/i,"").replace(/-\\d+x\\d+$/,"").replace(/-scaled$/,"");}for(var k in M){S[decodeURIComponent(M[k].s)]=k;}'
			. dcmk_js_aplicar() // phpcs:ignore
			. 'function pid(el){if(P&&el.closest(".woocommerce-product-gallery"))return M[P]?String(P):null;var li=el.closest("li.product,.product");if(li){var mm=(li.className||"").match(/post-(\d+)/);if(mm&&M[mm[1]])return mm[1];}var a=el.closest("a[href*=\"/produto/\"]")||(li&&li.querySelector("a[href*=\"/produto/\"]"));if(a){var s=a.getAttribute("href").split("/produto/")[1];if(s){s=decodeURIComponent(s.split(/[/?#]/)[0]);if(S[s])return S[s];}}return null;}'
			. 'function cont(img){var c=img.closest(".dcv-img")||img.closest("a")||img.parentNode;if(getComputedStyle(c).position==="static"){c.style.position="relative";}return c;}'
			. 'function run(){document.querySelectorAll("li.product img, .dcv-card img, .dcv-cola img, .products img, .dcom img, .woocommerce-product-gallery__image img").forEach(function(img){if(img.dataset.dcmk||img.closest(".dcmk")||img.classList.contains("zoomImg"))return;var sl=img.closest(".woocommerce-product-gallery__image");if(sl&&sl.parentNode&&sl.parentNode.firstElementChild!==sl){var fn=((img.getAttribute("data-large_image")||img.getAttribute("data-src")||img.src||"").split("/").pop()||"").replace(/-\\d+x\\d+(?=\\.)/,"");if(!/(_\\d{3}(-[a-z0-9]+)?\\.|[-_](AZUL|PRETO|BRANCO|VERMELHO|VERDE|AMARELO|LARANJA|ROXO|ROSA|CINZA|KRAFT|BEGE|MARROM|VINHO|PINK|LILAS|DOURADO|PRATA|AZU|PRE|PRA|BRA|VD|VER|AMA|LAR|ROX|ROS|CIN|MAR|VIN|DOU|BEG|AZ|AC|AE|AM|BR|BC|VM|VI|PT|LA|CZ|RS|RX|MR|TRANSPARENTE|TRANSLUCIDO|TRANSLUCIDA|CRISTAL|FUME|CREME|NATURAL|TRA|NAT|GRAFITE|CHUMBO|CHAMPAGNE)[-_.])/.test(fn)||/(_amb|-c|d\\d)(-\\d+)?\\./i.test(fn))return;}if(img.closest("header,.dcle-top"))return;var id=pid(img);if(!id)return;var R=M[id];var lu=LU[dcmkB(img)];var b=dcmkLogo(R,L,lu);img.dataset.dcmk="1";if(!b)return;var c=cont(img);c.appendChild(b);var f=function(){dcmkPos(b,img,R);};B.push(f);if(img.complete)f();img.addEventListener("load",f);b.firstChild.addEventListener("load",f);if(sl){dcmkZoom(sl,b,R,lu);}if(R.qx&&R.qx.length){R.qx.forEach(function(qq){var R2=Array.isArray(qq)?Object.assign({},R,{q:qq,qx:null}):Object.assign({},R,{d:0},qq,{qx:null});if(!R2.q||R2.q.length!==8)return;var b2=dcmkLogo(R2,L,lu);if(!b2)return;c.appendChild(b2);var f2=function(){dcmkPos(b2,img,R2);};B.push(f2);if(img.complete)f2();img.addEventListener("load",f2);b2.firstChild.addEventListener("load",f2);});}});}'
			. 'function dcmkZoom(sl,b,R,lu){if(sl.dataset.dcz)return;sl.dataset.dcz="1";var zb=null,raf=0;function up(){raf=0;var z=sl.querySelector("img.zoomImg");var on=z&&parseFloat(getComputedStyle(z).opacity)>0.05;if(!on){sl.querySelectorAll(".dcmk").forEach(function(x){if(x!==zb)x.style.visibility="";});if(zb)zb.style.display="none";return;}if(!zb){zb=dcmkLogo(R,L,lu);if(!zb)return;zb.style.zIndex="6";sl.appendChild(zb);zb.firstChild.addEventListener("load",up);}zb.style.display="";sl.querySelectorAll(".dcmk").forEach(function(x){if(x!==zb)x.style.visibility="hidden";});dcmkPos(zb,z,R);}function q(){if(!raf)raf=requestAnimationFrame(up);}sl.addEventListener("mousemove",q);sl.addEventListener("mouseenter",function(){setTimeout(q,40);setTimeout(q,320);});sl.addEventListener("mouseleave",function(){setTimeout(q,40);setTimeout(q,400);});}'
			. 'function all(){B.forEach(function(f){f();});}'
			. 'if(document.readyState!=="loading"){run();}else{document.addEventListener("DOMContentLoaded",run);}window.addEventListener("load",function(){run();all();});window.addEventListener("resize",all);'
			. 'new MutationObserver(function(){clearTimeout(window.__dcmkT);window.__dcmkT=setTimeout(function(){run();all();},120);}).observe(document.documentElement,{childList:true,subtree:true});})();</script>';
	},
	99
);

/* ---------- Editor da regra no produto (aba Franquias) ---------- */

function dcmk_logo_exemplo() {
	$ids = get_users( array( 'role' => 'escola', 'meta_key' => 'omx_logo', 'meta_compare' => 'EXISTS', 'fields' => 'ID', 'number' => 30 ) ); // phpcs:ignore
	$pref = 0;
	foreach ( (array) $ids as $u ) {
		if ( false !== stripos( (string) get_user_meta( (int) $u, 'omx_escola', true ), 'aprenda' ) ) {
			$pref = (int) $u;
			break;
		}
	}
	if ( ! $pref && $ids ) {
		$pref = (int) $ids[0];
	}
	return $pref ? dcmk_logos( $pref ) : array( 'cor' => '', 'branco' => '', 'png' => false );
}

add_action(
	'woocommerce_product_data_panels',
	function () {
		global $post;
		if ( ! $post ) {
			return;
		}
		$r   = dcmk_regra( $post->ID );
		$L   = dcmk_logo_exemplo();
		$fu  = dcmk_foto_unica( $post->ID );
		$fix = (int) get_post_meta( $post->ID, '_dcmk_foto', true );
		$tid = $fu ? $fu : (int) get_post_thumbnail_id( $post->ID );
		$fot = $tid ? (string) wp_get_attachment_image_url( $tid, 'woocommerce_single' ) : '';
		wp_nonce_field( 'dcmk', 'dcmk_n' );
		$num = function ( $n, $tit, $min, $max, $st, $v ) {
			return '<label style="display:block;margin:0 0 10px"><b>' . $tit . '</b> <output id="dcmk_o_' . $n . '">' . esc_html( $v ) . '</output><input type="range" name="dcmk_' . $n . '" id="dcmk_' . $n . '" min="' . $min . '" max="' . $max . '" step="' . $st . '" value="' . esc_attr( $v ) . '" style="width:100%"></label>';
		};
		echo '<div id="dcmk_panel_wrap" style="display:none"><div id="dcmk_box" style="border-top:1px solid #e5e5e5;margin:18px 0 0;padding:14px 0 0">'
			. '<h3 style="margin:0 0 6px">Aplicação do logo (mockup automático nas lojas das franquias)</h3>'
			. '<p style="margin:0 0 12px">Arraste o logo na foto para posicionar. Esta regra vale para <b>todas</b> as franquias: cada loja mostra o logo da própria escola. '
			. ( $r['salva'] ? '' : '<span style="color:#b26200">Ainda sem regra salva: mostrando o padrão pelo tipo de produto.</span>' ) . '</p>'
			. '<div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start">'
			. '<div id="dcmk_stage" style="position:relative;width:340px;height:340px;background:#fff;border:1px solid #dcdcde;border-radius:8px;overflow:hidden;user-select:none">'
			. ( '' !== $fot ? '<img id="dcmk_foto" src="' . esc_url( $fot ) . '" alt="" style="width:340px;height:340px;object-fit:contain;display:block" draggable="false">' : '<div style="padding:20px">Produto sem foto principal.</div>' )
			. '</div><div style="flex:1;min-width:240px;max-width:380px">'
			. '<label style="display:block;margin:0 0 10px"><b>Gravação / versão do logo</b><select name="dcmk_m" id="dcmk_m" style="width:100%;margin-top:4px">';
		foreach ( dcmk_modos() as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $r['m'], $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		$lv = isset( $r['l'] ) ? $r['l'] : '';
		echo '</select></label>'
			. '<label style="display:block;margin:0 0 10px"><b>Qual logo usar</b><select name="dcmk_l" id="dcmk_l" style="width:100%;margin-top:4px">'
			. '<option value=""' . selected( $lv, '', false ) . '>Automático (pelo formato da área)</option>'
			. '<option value="h"' . selected( $lv, 'h', false ) . '>Horizontal</option>'
			. '<option value="v"' . selected( $lv, 'v', false ) . '>Vertical</option></select></label>'
			. $num( 'w', 'Tamanho (% da foto)', 4, 90, 1, $r['w'] ) // phpcs:ignore
			. $num( 'r', 'Giro (graus)', -180, 180, 1, $r['r'] ) // phpcs:ignore
			. $num( 'x', 'Horizontal (%)', 0, 100, 0.5, $r['x'] ) // phpcs:ignore
			. $num( 'y', 'Vertical (%)', 0, 100, 0.5, $r['y'] ) // phpcs:ignore
			. '<p style="margin:4px 0"><button type="button" class="button" id="dcmk_g90">Girar 90°</button> <button type="button" class="button" id="dcmk_cen">Centralizar</button></p>'
			. '<div style="margin:10px 0 6px;padding:10px;border:1px dashed #c3c4c7;border-radius:6px"><b>Área de gravação (perspectiva)</b><p class="description" style="margin:4px 0 8px">Marque os 4 cantos da área onde a arte entra (arraste as bolinhas na foto). O logo se encaixa dentro dela, inclinado como a foto. Com a área marcada, o Tamanho vira a margem dentro da área (88 = quase cheia).</p>'
			. '<input type="hidden" name="dcmk_q" id="dcmk_q" value="' . esc_attr( ( ! empty( $r['q'] ) && is_array( $r['q'] ) ) ? implode( ',', $r['q'] ) : '' ) . '">'
			. '<button type="button" class="button" id="dcmk_qnew">Marcar área</button> <button type="button" class="button" id="dcmk_qred">Detectar área vermelha</button> <button type="button" class="button-link" id="dcmk_qdel" style="color:#b32d2e;margin-left:6px">Tirar área</button></div>'
			. '<div style="margin:12px 0 6px;padding:10px;border:1px dashed #c3c4c7;border-radius:6px"><b>Foto de um item só</b> <span class="description">(usada na loja da franquia)</span><div style="display:flex;gap:8px;align-items:center;margin-top:6px"><img id="dcmk_fpv" src="' . esc_url( $fu ? (string) wp_get_attachment_image_url( $fu, 'thumbnail' ) : '' ) . '" alt="" style="width:56px;height:56px;object-fit:contain;border:1px solid #ddd;border-radius:6px;background:#fff' . ( $fu ? '' : ';display:none' ) . '"><input type="hidden" name="dcmk_foto" id="dcmk_foto" value=""><button type="button" class="button" id="dcmk_fbt">' . ( $fu ? 'Trocar foto' : 'Escolher foto' ) . '</button>' . ( $fix ? '<label><input type="checkbox" name="dcmk_foto_auto" value="1"> voltar à automática</label>' : '' ) . '</div>' . ( $fu ? '' : '<p class="description" style="margin:6px 0 0;color:#b32d2e">Este produto ainda não tem foto de um item só: a loja mostra a foto com todas as cores.</p>' ) . '</div>'
			. '<input type="hidden" name="dcmk_salvar" value="1"><label style="display:block;margin:8px 0"><input type="checkbox" name="dcmk_padrao" value="1"> Voltar ao padrão automático</label>'
			. '<p class="description">Exemplo com o logo de uma escola. Clique em <b>Atualizar</b> para salvar.</p></div></div></div></div>'
			. '<style>' . dcmk_css() . '</style>' // phpcs:ignore
			. '<script>jQuery(function($){var W=$("#dcmk_panel_wrap"),P=$("#dcom_fr_panel");if(P.length){P.append(W.children());}else{W.show();}'
			. 'var L=' . wp_json_encode( $L ) . ',st=document.getElementById("dcmk_stage"),img=document.getElementById("dcmk_foto");if(!img)return;' . dcmk_js_aplicar() // phpcs:ignore
			. 'var b=null;function Qg(){var v=($("#dcmk_q").val()||"").split(",").map(Number);return v.length===8&&!v.some(isNaN)?v:null;}function Qs(v){$("#dcmk_q").val(v?v.map(function(n){return n.toFixed(1);}).join(","):"");}'
			. 'function R(){var o={x:+$("#dcmk_x").val(),y:+$("#dcmk_y").val(),w:+$("#dcmk_w").val(),r:+$("#dcmk_r").val(),m:$("#dcmk_m").val(),l:$("#dcmk_l").val()},q=Qg();if(q)o.q=q;return o;}'
			. 'var H=[],sv=null;function hd(){H.forEach(function(e){e.remove();});H=[];if(sv){sv.remove();sv=null;}var q=Qg();if(!q)return;var ns="http://www.w3.org/2000/svg";sv=document.createElementNS(ns,"svg");sv.setAttribute("viewBox","0 0 100 100");sv.setAttribute("preserveAspectRatio","none");sv.style.cssText="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none;z-index:3";var pg=document.createElementNS(ns,"polygon");pg.setAttribute("points",q[0]+","+q[1]+" "+q[2]+","+q[3]+" "+q[4]+","+q[5]+" "+q[6]+","+q[7]);pg.setAttribute("fill","rgba(237,28,36,.12)");pg.setAttribute("stroke","#ed1c24");pg.setAttribute("stroke-width",".5");pg.setAttribute("vector-effect","non-scaling-stroke");sv.appendChild(pg);st.appendChild(sv);for(var i=0;i<4;i++){var d=document.createElement("div");d.style.cssText="position:absolute;z-index:4;width:14px;height:14px;margin:-7px 0 0 -7px;border-radius:50%;background:#fff;border:2px solid #ed1c24;box-shadow:0 1px 3px rgba(0,0,0,.4);left:"+q[i*2]+"%;top:"+q[i*2+1]+"%;pointer-events:none";st.appendChild(d);H.push(d);}}'
			. 'function draw(){hd();if(b){b.remove();b=null;}["x","y","w","r"].forEach(function(n){$("#dcmk_o_"+n).text($("#dcmk_"+n).val());});var r=R();b=dcmkLogo(r,L);if(!b)return;st.appendChild(b);var f=function(){dcmkPos(b,img,r);};f();b.firstChild.addEventListener("load",f);}'
			. '$("#dcmk_box").on("input change","input,select",draw);$("#dcmk_g90").on("click",function(){var v=+$("#dcmk_r").val()+90;if(v>180)v-=360;$("#dcmk_r").val(v);draw();});$("#dcmk_cen").on("click",function(){$("#dcmk_x").val(50);$("#dcmk_y").val(50);draw();});'
			. 'var drag=false,hi=-1,last=null;function pc(e){var q=img.getBoundingClientRect();return[Math.max(0,Math.min(100,(e.clientX-q.left)/q.width*100)),Math.max(0,Math.min(100,(e.clientY-q.top)/q.height*100))];}'
			. 'st.addEventListener("pointerdown",function(e){drag=true;st.setPointerCapture(e.pointerId);var p=pc(e),q=Qg();hi=-1;last=p;if(q){var bd=99;for(var i=0;i<4;i++){var dd=Math.sqrt(Math.pow(q[i*2]-p[0],2)+Math.pow(q[i*2+1]-p[1],2));if(dd<bd){bd=dd;hi=i;}}if(bd>5)hi=-2;}mv(e);});st.addEventListener("pointermove",function(e){if(drag)mv(e);});st.addEventListener("pointerup",function(){drag=false;});'
			. 'function mv(e){var p=pc(e),q=Qg();if(q&&hi>=0){q[hi*2]=p[0];q[hi*2+1]=p[1];Qs(q);}else if(q&&hi===-2){var dx=p[0]-last[0],dy=p[1]-last[1];for(var i=0;i<4;i++){q[i*2]+=dx;q[i*2+1]+=dy;}Qs(q);}else if(!q){$("#dcmk_x").val(p[0].toFixed(1));$("#dcmk_y").val(p[1].toFixed(1));}last=p;draw();}'
			. '$("#dcmk_qnew").on("click",function(){var x=+$("#dcmk_x").val(),y=+$("#dcmk_y").val(),w=Math.max(10,+$("#dcmk_w").val())/2,h=w*.75;Qs([x-w,y-h,x+w,y-h,x+w,y+h,x-w,y+h]);if(+$("#dcmk_w").val()<40)$("#dcmk_w").val(88);draw();});$("#dcmk_qdel").on("click",function(){Qs(null);draw();});'
			. 'var fq;$("#dcmk_qred").on("click",function(e){e.preventDefault();if(!window.wp||!wp.media)return;if(!fq){fq=wp.media({title:"Foto com a área em vermelho",button:{text:"Detectar área"},library:{type:"image"},multiple:false});fq.on("select",function(){var a=fq.state().get("selection").first().toJSON();var im=new Image();im.onload=function(){var N=400,cv=document.createElement("canvas");cv.width=N;cv.height=N;var cx=cv.getContext("2d");cx.fillStyle="#fff";cx.fillRect(0,0,N,N);var k=Math.min(N/im.width,N/im.height),dw=im.width*k,dh=im.height*k;cx.drawImage(im,(N-dw)/2,(N-dh)/2,dw,dh);var px=cx.getImageData(0,0,N,N).data,c=[null,null,null,null],v=[1e9,-1e9,-1e9,1e9],n=0;for(var y=0;y<N;y++)for(var x=0;x<N;x++){var o=(y*N+x)*4,R0=px[o],G0=px[o+1],B0=px[o+2];if(R0>170&&G0<90&&B0<90&&R0-G0>110){n++;var s1=x+y,s2=x-y;if(s1<v[0]){v[0]=s1;c[0]=[x,y];}if(s2>v[1]){v[1]=s2;c[1]=[x,y];}if(s1>v[2]){v[2]=s1;c[2]=[x,y];}if(s2<v[3]){v[3]=s2;c[3]=[x,y];}}}if(n<50){alert("Não achei área vermelha nessa imagem.");return;}var q=[];c.forEach(function(p){q.push(p[0]/N*100,p[1]/N*100);});Qs(q);if(+$("#dcmk_w").val()<40)$("#dcmk_w").val(88);draw();};im.onerror=function(){alert("Não consegui ler a imagem.");};im.src=a.url;});}fq.open();});'
			. 'var fr;$("#dcmk_fbt").on("click",function(e){e.preventDefault();if(!window.wp||!wp.media)return;if(!fr){fr=wp.media({title:"Foto de um item só",button:{text:"Usar esta foto"},library:{type:"image"},multiple:false});fr.on("select",function(){var a=fr.state().get("selection").first().toJSON();$("#dcmk_foto").val(a.id);var t=(a.sizes&&a.sizes.thumbnail)?a.sizes.thumbnail.url:a.url;$("#dcmk_fpv").attr("src",t).show();img.src=(a.sizes&&a.sizes.woocommerce_single)?a.sizes.woocommerce_single.url:a.url;});}fr.open();});'
			. 'if(img.complete){draw();}else{img.addEventListener("load",draw);}$(document).on("click",".dcom_fr_options a, li.dcom_fr_tab a",function(){setTimeout(draw,50);});});</script>';
	},
	20
);

add_action(
	'save_post_product',
	function ( $pid ) {
		if ( empty( $_POST['dcmk_salvar'] ) || ! isset( $_POST['dcmk_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcmk_n'] ) ), 'dcmk' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $pid ) ) {
			return;
		}
		if ( ! empty( $_POST['dcmk_foto_auto'] ) ) {
			delete_post_meta( $pid, '_dcmk_foto' );
		} elseif ( ! empty( $_POST['dcmk_foto'] ) && wp_attachment_is_image( absint( $_POST['dcmk_foto'] ) ) ) {
			update_post_meta( $pid, '_dcmk_foto', absint( $_POST['dcmk_foto'] ) );
		}
		if ( ! empty( $_POST['dcmk_padrao'] ) ) {
			delete_post_meta( $pid, '_dcmk' );
			return;
		}
		$m = isset( $_POST['dcmk_m'] ) ? sanitize_key( wp_unslash( $_POST['dcmk_m'] ) ) : 'cor';
		$m = isset( dcmk_modos()[ $m ] ) ? $m : 'cor';
		$g = function ( $k, $min, $max, $d ) {
			$v = isset( $_POST[ 'dcmk_' . $k ] ) ? (float) wp_unslash( $_POST[ 'dcmk_' . $k ] ) : $d; // phpcs:ignore
			return round( max( $min, min( $max, $v ) ), 1 );
		};
		$novo = array( 'x' => $g( 'x', 0, 100, 50 ), 'y' => $g( 'y', 0, 100, 50 ), 'w' => $g( 'w', 4, 90, 28 ), 'r' => $g( 'r', -180, 180, 0 ), 'm' => $m );
		$lp   = isset( $_POST['dcmk_l'] ) ? sanitize_key( wp_unslash( $_POST['dcmk_l'] ) ) : '';
		if ( in_array( $lp, array( 'h', 'v' ), true ) ) {
			$novo['l'] = $lp;
		}
		$ant = get_post_meta( $pid, '_dcmk', true );
		if ( is_array( $ant ) && ! empty( $ant['t'] ) ) {
			$novo['t'] = $ant['t']; // versão colorida escolhida na macro (fundos claros/escuros)
		}
		if ( is_array( $ant ) && ! empty( $ant['qx'] ) ) {
			$novo['qx'] = $ant['qx']; // outras áreas do molde (logo em mais de um lugar)
		}
		$qv   = isset( $_POST['dcmk_q'] ) ? array_map( 'floatval', explode( ',', sanitize_text_field( wp_unslash( $_POST['dcmk_q'] ) ) ) ) : array();
		if ( 8 === count( $qv ) ) {
			$novo['q'] = array_map(
				function ( $n ) {
					return round( max( -20, min( 120, $n ) ), 1 );
				},
				$qv
			);
		}
		$pad  = dcmk_padrao( $pid );
		// Só grava quando mudou algo em relação ao padrão (assim produtos nunca abertos seguem o padrão automático).
		if ( $novo != $pad || get_post_meta( $pid, '_dcmk', true ) ) { // phpcs:ignore
			update_post_meta( $pid, '_dcmk', $novo );
		}
	},
	31
);

/* ---------- Logo branco na Identidade da loja ---------- */

add_action(
	'template_redirect',
	function () {
		if ( empty( $_POST['dcup_envio'] ) || ( empty( $_FILES['dcup_logo_branco']['name'] ) && empty( $_FILES['dcup_logo_vertical']['name'] ) && empty( $_FILES['dcup_logo_vertical_branco']['name'] ) ) || ! is_user_logged_in() || ! function_exists( 'omx_is_escola' ) || ! omx_is_escola() ) { // phpcs:ignore
			return;
		}
		if ( ! isset( $_POST['dcup_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcup_n'] ) ), 'dcup' ) ) {
			return;
		}
		$uid = get_current_user_id();
		if ( function_exists( 'omx_parent' ) && omx_parent( $uid ) !== $uid ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$mim = array( 'png' => 'image/png', 'webp' => 'image/webp' );
		$ok  = 0;
		foreach ( array( 'dcup_logo_escuro' => array( 'omx_logo_escuro', 'logo colorido fundo escuro' ), 'dcup_logo_vertical_escuro' => array( 'omx_logo_vertical_escuro', 'logo vertical colorido fundo escuro' ), 'dcup_logo_branco' => array( 'omx_logo_branco', 'logo branco' ), 'dcup_logo_vertical' => array( 'omx_logo_vertical', 'logo vertical' ), 'dcup_logo_vertical_branco' => array( 'omx_logo_vertical_branco', 'logo vertical branco' ) ) as $campo => $cfg ) {
			if ( empty( $_FILES[ $campo ]['name'] ) ) {
				continue;
			}
			$f    = $_FILES[ $campo ]; // phpcs:ignore
			$tipo = wp_check_filetype_and_ext( $f['tmp_name'], $f['name'], $mim );
			if ( ! empty( $f['error'] ) || (int) $f['size'] > 5 * MB_IN_BYTES || empty( $tipo['type'] ) ) {
				continue;
			}
			$id = media_handle_upload( $campo, 0, array( 'post_title' => get_user_meta( $uid, 'omx_escola', true ) . ' - ' . $cfg[1] ), array( 'test_form' => false, 'mimes' => $mim ) );
			if ( ! is_wp_error( $id ) ) {
				update_user_meta( $uid, $cfg[0], (int) $id );
				$ok++;
			}
		}
		if ( $ok ) {
			add_filter(
				'wp_redirect',
				function ( $l ) {
					return str_replace( 'dcup=nada', 'dcup=ok', (string) $l );
				}
			);
		}
	},
	-6 // antes do envio normal (-5), que redireciona
);

/* Campo "Logo branco" dentro do formulário de Identidade (aba Minha loja). */
add_action(
	'woocommerce_account_escola-loja_endpoint',
	function () {
		if ( ! function_exists( 'omx_is_escola' ) || ! omx_is_escola() ) {
			return;
		}
		$uid  = get_current_user_id();
		$p    = function_exists( 'omx_parent' ) ? (int) omx_parent( $uid ) : $uid;
		$pode = $p === $uid;
		$card = function ( $meta, $campo, $tit, $dica, $escuro ) use ( $p, $pode ) {
			$a = (int) get_user_meta( $p, $meta, true );
			$u = $a ? (string) wp_get_attachment_image_url( $a, 'medium' ) : '';
			return '<div class="dcup-c"><b>' . $tit . '</b><small>' . $dica . '</small><div class="dcup-pv" style="background:' . ( $escuro ? '#0B2A5B' : '#EEF1F6' ) . '">' . ( '' !== $u ? '<img src="' . esc_url( $u ) . '" alt="">' : '<span style="color:' . ( $escuro ? '#C9D3E8' : '#98A2B3' ) . '">N&atilde;o enviado</span>' ) . '</div>' . ( $pode ? '<input type="file" name="' . $campo . '" accept="image/png,image/webp">' : '' ) . '</div>';
		};
		$html = '<div id="dcmk-lb" style="margin-top:18px"><b style="display:block;color:#0B2A5B;font-size:16px;margin:0 0 4px">Vers&otilde;es do logo para os brindes</b><small style="display:block;color:#667085;margin:0 0 10px">Todas em PNG com fundo transparente. S&atilde;o 6 vers&otilde;es. O site escolhe sozinho a certa para cada produto: &aacute;rea larga = horizontal, &aacute;rea alta = vertical; produto claro = colorido para fundos claros, produto escuro = colorido para fundos escuros, grava&ccedil;&atilde;o de 1 cor = branco.</small><div class="dcup-g3" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px">'
			. $card( 'omx_logo_escuro', 'dcup_logo_escuro', 'Logo horizontal colorido para fundos escuros', 'S&iacute;mbolo colorido e letras brancas.', true )
			. $card( 'omx_logo_branco', 'dcup_logo_branco', 'Logo horizontal branco', 'Todo em branco, para grava&ccedil;&atilde;o de 1 cor.', true )
			. $card( 'omx_logo_vertical', 'dcup_logo_vertical', 'Logo vertical colorido para fundos claros', 'S&iacute;mbolo em cima, nome embaixo, letras coloridas.', false )
			. $card( 'omx_logo_vertical_escuro', 'dcup_logo_vertical_escuro', 'Logo vertical colorido para fundos escuros', 'S&iacute;mbolo colorido e letras brancas.', true )
			. $card( 'omx_logo_vertical_branco', 'dcup_logo_vertical_branco', 'Logo vertical branco', 'Todo em branco, para grava&ccedil;&atilde;o de 1 cor.', true )
			. '</div></div>';
		echo '<script>(function(){var g=document.querySelector(".dcup .dcup-g");if(!g)return;var c=g.querySelector(".dcup-c");var lb=c&&c.querySelector("small");if(lb){lb.innerHTML="S&iacute;mbolo e letras coloridas.";}var b=c&&c.querySelector("b");if(b){b.textContent="Logo horizontal colorido para fundos claros";}var t=document.createElement("div");t.innerHTML=' . wp_json_encode( $html ) . ';g.parentNode.insertBefore(t.firstChild,g.nextSibling);var ng=document.querySelector("#dcmk-lb .dcup-g3");if(ng&&c){ng.insertBefore(c,ng.firstChild);g.style.gridTemplateColumns="1fr";}var s=document.createElement("style");s.textContent="@media(max-width:760px){.dcup .dcup-g3{grid-template-columns:1fr!important}}#dcmk-lb .dcup-pv:not([style*=\"#0B2A5B\"]){background:#A7A9AC!important}";document.head.appendChild(s);})();</script>'; // phpcs:ignore
	},
	21
);

/* ---------- Aviso: escola sem os dois logos ---------- */

function dcmk_falta_logo( $uid ) {
	if ( ! function_exists( 'omx_is_escola' ) || ! omx_is_escola() ) {
		return array();
	}
	$p = function_exists( 'omx_parent' ) ? (int) omx_parent( $uid ) : (int) $uid;
	if ( $p !== (int) $uid ) {
		return array(); // equipe não envia logo
	}
	$f = array();
	foreach ( array( 'omx_logo' => 'horizontal colorido', 'omx_logo_branco' => 'horizontal branco', 'omx_logo_vertical' => 'vertical colorido', 'omx_logo_vertical_branco' => 'vertical branco' ) as $mk => $nm ) {
		if ( ! (int) get_user_meta( $p, $mk, true ) ) {
			$f[] = $nm;
		}
	}
	return $f;
}

add_action(
	'woocommerce_account_content',
	function () {
		$f = dcmk_falta_logo( get_current_user_id() );
		if ( ! $f || ! function_exists( 'wc_get_account_endpoint_url' ) || ! empty( $GLOBALS['dcbv_mostrou'] ) ) {
			return;
		}
		$url = wc_get_account_endpoint_url( 'escola-loja' );
		echo '<div style="background:#FFF8E1;border:2px solid #F5B400;border-radius:16px;padding:18px 20px;margin:0 0 18px;display:flex;gap:16px;align-items:center;flex-wrap:wrap"><div style="flex:1;min-width:240px"><b style="display:block;font-size:18px;color:#0B2A5B;margin:0 0 4px">Envie o logo da sua escola</b>'
			. '<span style="color:#5B6478;font-size:14px">Com ele, todos os brindes da sua loja aparecem com a sua marca. Precisamos do logo <b>horizontal</b> e <b>vertical</b>, cada um <b>colorido</b> e <b>branco</b> (PNG com fundo transparente). Falta: <b>' . esc_html( implode( ', ', $f ) ) . '</b>.</span></div>'
			. '<a href="' . esc_url( $url ) . '#dcmk-lb" style="background:#F5B400;color:#0B2A5B;font-weight:800;border-radius:12px;padding:12px 20px;text-decoration:none">Enviar logos</a></div>';
	},
	1
);

/* No primeiro login (ou enquanto faltar logo), a escola cai direto na tela de envio. */
add_filter(
	'woocommerce_login_redirect',
	function ( $redir, $user ) {
		if ( $user instanceof WP_User && function_exists( 'wc_get_account_endpoint_url' ) && ! get_user_meta( $user->ID, 'dcmk_pediu_logo', true ) ) {
			wp_set_current_user( $user->ID );
			if ( dcmk_falta_logo( $user->ID ) ) {
				update_user_meta( $user->ID, 'dcmk_pediu_logo', current_time( 'mysql' ) );
				return wc_get_page_permalink( 'myaccount' ); // tela de boas-vindas (dc-ouro-boas-vindas.php)
			}
		}
		return $redir;
	},
	50,
	2
);

/* Clicar na cor (Cores disponíveis) troca a foto principal para a foto daquela cor. */
add_action(
	'wp_footer',
	function () {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		// Spot: foto por código de cor no nome (93717_114.jpg) -> nome da cor pela tabela de estoque.
		$cm  = array();
		$cs  = array();
		$tab = json_decode( (string) get_post_meta( get_queried_object_id(), '_dc_color_stock_table', true ), true );
		foreach ( is_array( $tab ) ? $tab : array() as $t ) {
			if ( ! empty( $t['sku'] ) && ! empty( $t['label'] ) && preg_match( '/-(\d{3})$/', (string) $t['sku'], $mm ) ) {
				$cm[ $mm[1] ] = (string) $t['label'];
			}
			// XBZ/Asia: código da cor em letras no fim do SKU (BTN301-AC = Azul Claro) -> foto ...-AC.jpg
			if ( ! empty( $t['sku'] ) && ! empty( $t['label'] ) && preg_match( '/-([A-Za-z]{1,4})$/', (string) $t['sku'], $mm ) ) {
				$cs[ strtoupper( $mm[1] ) ] = (string) $t['label'];
			}
		}
		?>
<script id="dcmk-corfoto">(function(){var CM=<?php echo wp_json_encode( (object) $cm ); ?>;var CS=<?php echo wp_json_encode( (object) $cs ); ?>;var S={DOURADO:["DOURADO","DOU","AMA","AMARELO","OURO","GOLD"],PRATA:["PRATA","PRA","SILVER","PRAT"],VERMELHO:["VERMELHO","VER","VRM","VM"],PRETO:["PRETO","PRE","PRT"],BRANCO:["BRANCO","BRA","BCO"],VERDE:["VERDE","VD","VRD"],AZUL:["AZUL","AZU","AZ"],AMARELO:["AMARELO","AMA"],CINZA:["CINZA","CIN","CZ"],LARANJA:["LARANJA","LAR","LJ"],ROXO:["ROXO","ROX","LILAS","LIL"],TRANSPARENTE:["TRANSPARENTE","TRANSLUCIDO","TRANSLUCIDA","TRA","CRISTAL"],ROSA:["ROSA","ROS","PINK"],MARROM:["MARROM","MAR"],BEGE:["BEGE","BEG"],VINHO:["VINHO","VIN"],NATURAL:["NATURAL","NAT"],KRAFT:["KRAFT","KRA"]};
function nm(im){return (((im.getAttribute("data-large_image")||im.getAttribute("data-src")||im.src||"").split("/").pop())||"").toUpperCase().replace(/-\d+X\d+(?=\.)/,"");}
document.addEventListener("dc:cor",function(e){var c=((e.detail&&e.detail.cor)||"").toUpperCase().normalize("NFD").replace(/[̀-ͯ]/g,"");var ws=c.split(/[^A-Z]+/).filter(Boolean);if(!ws.length)return;var sl=[].slice.call(document.querySelectorAll(".woocommerce-product-gallery__image"));function vai(i){var th=document.querySelectorAll(".flex-control-thumbs li img")[i];if(th){th.click();}else if(window.jQuery){var g=jQuery(".woocommerce-product-gallery");if(g.data("flexslider"))g.flexslider(i);}}function nc(x){return String(x||"").toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g,"").replace(/[^A-Z]+/g," ").trim();}var cn=nc(c);for(var j=0;j<sl.length;j++){var ij=sl[j].querySelector("img");if(!ij)continue;var mm=nm(ij).match(/_(\d{3})(-[A-Z0-9]+)?\./);if(mm&&CM[mm[1]]&&nc(CM[mm[1]])===cn){vai(j);return;}}for(var sk in CS){if(nc(CS[sk])!==cn)continue;var re=new RegExp("[-_]"+sk+"([-_.]|$)");for(var j2=0;j2<sl.length;j2++){var i2=sl[j2].querySelector("img");if(!i2)continue;var f2=nm(i2);if(re.test(f2)&&!/(_AMB|D\d)(-\d+)?\./.test(f2)){vai(j2);return;}}}
for(var w=0;w<ws.length;w++){var t=S[ws[w]]||[ws[w],ws[w].slice(0,3)];for(var i=0;i<sl.length;i++){var im=sl[i].querySelector("img");if(!im)continue;var fn=nm(im);if(/(_AMB|-C|D\d)(-\d+)?\./.test(fn))continue;var hit=t.some(function(k){return new RegExp("[-_]"+k+"[-_.]").test(fn);});if(hit){var th=document.querySelectorAll(".flex-control-thumbs li img")[i];if(th){th.click();}else if(window.jQuery){var g=jQuery(".woocommerce-product-gallery");if(g.data("flexslider"))g.flexslider(i);}return;}}}vai(0);});})();</script>
		<?php
	},
	99
);
