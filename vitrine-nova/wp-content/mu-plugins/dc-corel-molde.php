<?php
/**
 * Plugin Name: DC · Moldes pelo CorelDRAW
 * Description: 2026-11-23. Recebe do CorelDRAW (macro "Enviar para o site") a foto de um item só e os 4 cantos do molde vermelho de um produto,
 *   e grava no produto (foto da loja da franquia + área de gravação). Página: Produtos > Moldes (Corel), com a chave e a macro para copiar.
 *   Precisa do mu-plugin dc-ouro-logo-mockup.php (regras _dcmk / _dcmk_foto).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dccm_chave( $nova = false ) {
	$k = (string) get_option( 'dccm_chave', '' );
	if ( '' === $k || $nova ) {
		$k = wp_generate_password( 40, false, false );
		update_option( 'dccm_chave', $k, false );
	}
	return $k;
}

/* Acha o produto pelo SKU (ou código do fornecedor). Variação -> produto pai. */
function dccm_produto( $sku ) {
	$sku = trim( (string) $sku );
	if ( '' === $sku ) {
		return 0;
	}
	$id = function_exists( 'wc_get_product_id_by_sku' ) ? (int) wc_get_product_id_by_sku( $sku ) : 0;
	if ( ! $id ) {
		global $wpdb;
		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				WHERE p.post_type = 'product' AND p.post_status IN ('publish','draft','private')
				AND ( ( m.meta_key = '_sku' AND ( m.meta_value = %s OR m.meta_value LIKE %s ) ) OR ( m.meta_key = '_dcspot_ref' AND m.meta_value = %s ) )
				ORDER BY p.post_status = 'publish' DESC, p.ID DESC LIMIT 1",
				$sku,
				$wpdb->esc_like( $sku ) . '-%',
				$sku
			)
		);
	}
	if ( $id && 'product_variation' === get_post_type( $id ) ) {
		$id = (int) wp_get_post_parent_id( $id );
	}
	return $id;
}

/* Brilho medio (0-255) dentro da area do molde, na foto de um item so do produto. */
function dccm_luminancia( $pid, $q ) {
	$att = (int) get_post_meta( $pid, '_dcmk_foto', true );
	$arq = $att ? get_attached_file( $att ) : '';
	if ( ! $arq || ! file_exists( $arq ) || ! function_exists( 'imagecreatefromstring' ) ) {
		return null;
	}
	$im = @imagecreatefromstring( (string) file_get_contents( $arq ) ); // phpcs:ignore
	if ( ! $im ) {
		return null;
	}
	$w  = imagesx( $im );
	$h  = imagesy( $im );
	$s  = min( $w, $h );
	$ox = ( $w - $s ) / 2;
	$oy = ( $h - $s ) / 2;
	$xs = array( $q[0], $q[2], $q[4], $q[6] );
	$ys = array( $q[1], $q[3], $q[5], $q[7] );
	$x0 = min( $xs );
	$x1 = max( $xs );
	$y0 = min( $ys );
	$y1 = max( $ys );
	$cx = ( $x0 + $x1 ) / 2;
	$cy = ( $y0 + $y1 ) / 2;
	$t  = 0;
	$n  = 0;
	for ( $i = 0; $i < 15; $i++ ) {
		for ( $j = 0; $j < 15; $j++ ) {
			$px = $cx + ( ( $x1 - $x0 ) * 0.6 ) * ( $i / 14 - 0.5 );
			$py = $cy + ( ( $y1 - $y0 ) * 0.6 ) * ( $j / 14 - 0.5 );
			$x  = (int) max( 0, min( $w - 1, $ox + $px / 100 * $s ) );
			$y  = (int) max( 0, min( $h - 1, $oy + $py / 100 * $s ) );
			$c  = imagecolorsforindex( $im, imagecolorat( $im, $x, $y ) );
			$t += 0.299 * $c['red'] + 0.587 * $c['green'] + 0.114 * $c['blue'];
			$n++;
		}
	}
	imagedestroy( $im );
	return $n ? $t / $n : null;
}

function dccm_receber( WP_REST_Request $r ) {
	$p   = $r->get_json_params();
	$p   = is_array( $p ) ? $p : array();
	$sku = isset( $p['sku'] ) ? sanitize_text_field( (string) $p['sku'] ) : '';
	$pid = dccm_produto( $sku );
	if ( ! $pid ) {
		return new WP_REST_Response( array( 'ok' => false, 'mensagem' => 'Produto com SKU "' . $sku . '" não encontrado no site.' ), 404 );
	}
	$q = isset( $p['q'] ) && is_array( $p['q'] ) ? array_map( 'floatval', $p['q'] ) : array();
	if ( 8 !== count( $q ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'mensagem' => 'Não recebi os 4 cantos do molde vermelho.' ), 400 );
	}
	$q = array_map(
		function ( $n ) {
			return round( max( -20, min( 120, $n ) ), 1 );
		},
		$q
	);
	$msg = array();
	// Foto de um item só (PNG quadrado exportado pelo Corel, já sem o vermelho).
	if ( ! empty( $p['foto'] ) ) {
		$bin = base64_decode( (string) $p['foto'], true ); // phpcs:ignore
		if ( false === $bin || "\x89PNG" !== substr( $bin, 0, 4 ) || strlen( $bin ) > 12 * MB_IN_BYTES ) {
			return new WP_REST_Response( array( 'ok' => false, 'mensagem' => 'A foto não chegou como PNG válido.' ), 400 );
		}
		$nome = 'molde-' . sanitize_file_name( $sku ) . '-' . gmdate( 'YmdHis' ) . '.png';
		$up   = wp_upload_bits( $nome, null, $bin );
		if ( ! empty( $up['error'] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'mensagem' => 'Erro ao salvar a foto: ' . $up['error'] ), 500 );
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$att = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => get_the_title( $pid ) . ' - foto franquia',
				'post_status'    => 'inherit',
			),
			$up['file'],
			$pid
		);
		if ( is_wp_error( $att ) || ! $att ) {
			return new WP_REST_Response( array( 'ok' => false, 'mensagem' => 'Erro ao registrar a foto na Mídia.' ), 500 );
		}
		wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $up['file'] ) );
		update_post_meta( $pid, '_dcmk_foto', (int) $att );
		$msg[] = 'foto';
	}
	// Regra: molde + gravação.
	$regra = get_post_meta( $pid, '_dcmk', true );
	if ( ! is_array( $regra ) ) {
		$regra = function_exists( 'dcmk_padrao' ) ? dcmk_padrao( $pid ) : array( 'x' => 50, 'y' => 50, 'w' => 88, 'r' => 0, 'm' => 'cor' );
		unset( $regra['salva'] );
	}
	if ( empty( $regra['q'] ) ) {
		$regra['w'] = 88;
		$regra['r'] = 0;
	}
	$regra['q'] = $q;
	// Outras áreas vermelhas (logo em mais de um lugar).
	$qx = array();
	foreach ( ( isset( $p['qx'] ) && is_array( $p['qx'] ) ) ? $p['qx'] : array() as $qq ) {
		if ( is_array( $qq ) && 8 === count( $qq ) ) {
			$qx[] = array_map(
				function ( $n ) {
					return round( max( -20, min( 120, (float) $n ) ), 1 );
				},
				array_values( $qq )
			);
		}
	}
	if ( $qx ) {
		$regra['qx'] = array_slice( $qx, 0, 5 );
		$msg[]       = ( count( $regra['qx'] ) + 1 ) . ' áreas';
	} else {
		unset( $regra['qx'] );
	}
	$modos      = function_exists( 'dcmk_modos' ) ? dcmk_modos() : array();
	$m          = isset( $p['modo'] ) ? sanitize_key( (string) $p['modo'] ) : '';
	if ( '' !== $m && isset( $modos[ $m ] ) ) {
		$regra['m'] = $m;
	} elseif ( '' === $m && 'cor' === $regra['m'] ) {
		// Gravacao nao escolhida: se a area do molde e escura (produto preto, azul marinho...), usa o logo branco.
		$lum = dccm_luminancia( $pid, $q );
		if ( null !== $lum && $lum < 110 ) {
			$regra['m'] = 'branco';
			$msg[]      = 'logo branco (area escura)';
		}
	}
	$lg = isset( $p['logo'] ) ? sanitize_key( (string) $p['logo'] ) : '';
	if ( 'h' === $lg || 'v' === $lg ) {
		$regra['l'] = $lg;
		$msg[]      = 'v' === $lg ? 'logo vertical' : 'logo horizontal';
	} elseif ( 'a' === $lg ) {
		unset( $regra['l'] );
	}
	// Versão colorida: fundos claros / fundos escuros (ou automático pela cor de cada foto).
	$tm = isset( $p['tom'] ) ? sanitize_key( (string) $p['tom'] ) : '';
	if ( 'claro' === $tm || 'escuro' === $tm ) {
		$regra['t'] = $tm;
		$msg[]      = 'fundos ' . ( 'claro' === $tm ? 'claros' : 'escuros' );
	} elseif ( 'a' === $tm ) {
		unset( $regra['t'] );
	}
	// Area em pe (caneta, garrafa...) com logo horizontal: deita o logo ao longo do produto.
	$w1 = hypot( $q[2] - $q[0], $q[3] - $q[1] ) + hypot( $q[4] - $q[6], $q[5] - $q[7] );
	$h1 = hypot( $q[6] - $q[0], $q[7] - $q[1] ) + hypot( $q[4] - $q[2], $q[5] - $q[3] );
	if ( 'h' === $lg ) {
		$regra['r'] = ( $h1 > $w1 * 1.2 ) ? -90 : 0; // area em pe: logo deitado, lendo de baixo para cima
	} elseif ( 'v' === $lg || 'a' === $lg ) {
		$regra['r'] = 0;
	}
	if ( isset( $p['margem'] ) && (float) $p['margem'] >= 30 && (float) $p['margem'] <= 100 ) {
		$regra['w'] = round( (float) $p['margem'], 1 );
	}
	if ( isset( $p['giro'] ) && in_array( (int) $p['giro'], array( -90, 0, 90, 180 ), true ) ) {
		$regra['r'] = (int) $p['giro'];
	}
	update_post_meta( $pid, '_dcmk', $regra );
	$msg[] = 'molde';
	// Histórico (últimos 30) para a página Moldes (Corel).
	$log = get_option( 'dccm_log', array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift( $log, array( 't' => current_time( 'mysql' ), 'pid' => $pid, 'sku' => $sku, 'o' => implode( ' + ', $msg ), 'm' => $regra['m'] ) );
	update_option( 'dccm_log', array_slice( $log, 0, 30 ), false );
	$nm = isset( $modos[ $regra['m'] ] ) ? $modos[ $regra['m'] ] : $regra['m'];
	return new WP_REST_Response(
		array(
			'ok'       => true,
			'produto'  => get_the_title( $pid ),
			'id'       => $pid,
			'mensagem' => 'OK! ' . get_the_title( $pid ) . ' (SKU ' . $sku . '): ' . implode( ' + ', $msg ) . ' gravados. Gravação: ' . $nm . '.',
		),
		200
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'dc/v1',
			'/molde',
			array(
				'methods'             => 'POST',
				'callback'            => 'dccm_receber',
				'permission_callback' => function ( WP_REST_Request $r ) {
					$k = (string) $r->get_header( 'x-dc-molde-key' );
					$c = (string) get_option( 'dccm_chave', '' );
					return '' !== $c && '' !== $k && hash_equals( $c, $k );
				},
			)
		);
	}
);

/* ---------- Página Produtos > Moldes (Corel) ---------- */

function dccm_macro( $chave ) {
	$url = rest_url( 'dc/v1/molde' );
	$vba = <<<'VBA'
' ===== Divulgar Criacoes - Enviar molde para o site (CorelDRAW 2024) =====
' Como usar: selecione a FOTO do produto + o MOLDE VERMELHO por cima e rode "EnviarMoldeParaSite".
Option Explicit

Const SITE As String = "{{URL}}"
Const CHAVE As String = "{{CHAVE}}"

Private Function EhVermelho(sh As Shape) As Boolean
    On Error GoTo fim
    If sh.Fill.Type <> cdrUniformFill Then Exit Function
    Dim c As New Color
    c.CopyAssign sh.Fill.UniformColor
    c.ConvertToRGB
    EhVermelho = (c.RGBRed > 190 And c.RGBGreen < 90 And c.RGBBlue < 90)
fim:
End Function

Private Sub Coleta(sh As Shape, ByRef red As Shape, foto As ShapeRange)
    Dim c As Shape
    If sh.Type = cdrGroupShape Then
        For Each c In sh.Shapes
            Coleta c, red, foto
        Next c
    ElseIf red Is Nothing And EhVermelho(sh) Then
        Set red = sh
    Else
        foto.Add sh
    End If
End Sub

Private Function N(v As Double) As String
    N = Trim(Str(Round(v, 2)))
End Function

Private Function Base64Arquivo(caminho As String) As String
    Dim st As Object, b() As Byte, x As Object
    Set st = CreateObject("ADODB.Stream")
    st.Type = 1
    st.Open
    st.LoadFromFile caminho
    b = st.Read
    st.Close
    Set x = CreateObject("MSXML2.DOMDocument.6.0").createElement("b")
    x.DataType = "bin.base64"
    x.nodeTypedValue = b
    Base64Arquivo = Replace(Replace(x.Text, vbLf, ""), vbCr, "")
End Function

Sub EnviarMoldeParaSite()
    Dim sr As ShapeRange, sh As Shape, red As Shape, foto As New ShapeRange
    If ActiveDocument Is Nothing Then Exit Sub
    Set sr = ActiveSelectionRange
    If sr.Count = 0 Then
        MsgBox "Selecione a foto do produto e o molde vermelho por cima.", vbExclamation
        Exit Sub
    End If
    For Each sh In sr
        Coleta sh, red, foto
    Next sh
    If red Is Nothing Then
        MsgBox "Nao achei o molde vermelho na selecao.", vbExclamation
        Exit Sub
    End If
    If foto.Count = 0 Then
        MsgBox "Selecione tambem a foto do produto (nao so o vermelho).", vbExclamation
        Exit Sub
    End If

    Dim sku As String
    sku = Trim(InputBox("SKU do produto no site:", "Enviar molde", GetSetting("DivulgarMolde", "ui", "sku", "")))
    If sku = "" Then Exit Sub
    SaveSetting "DivulgarMolde", "ui", "sku", sku

    Dim modo As String, op As String, lg As String, tom As String
    op = Trim(InputBox("Qual logo usar:" & vbCr & "1 = horizontal colorido p/ fundos claros" & vbCr & "2 = horizontal colorido p/ fundos escuros" & vbCr & "3 = horizontal branco" & vbCr & "4 = vertical colorido p/ fundos claros" & vbCr & "5 = vertical colorido p/ fundos escuros" & vbCr & "6 = vertical branco" & vbCr & "7 = colorido automatico (pela cor de cada foto)" & vbCr & "8 = laser em metal" & vbCr & "9 = 1 cor escura (bambu/kraft)" & vbCr & "Enter = manter o do site", "Enviar molde", ""))
    Select Case op
        Case "1": modo = "cor": lg = "h": tom = "claro"
        Case "2": modo = "cor": lg = "h": tom = "escuro"
        Case "3": modo = "branco": lg = "h": tom = "a"
        Case "4": modo = "cor": lg = "v": tom = "claro"
        Case "5": modo = "cor": lg = "v": tom = "escuro"
        Case "6": modo = "branco": lg = "v": tom = "a"
        Case "7": modo = "cor": lg = "a": tom = "a"
        Case "8": modo = "laser": lg = "h": tom = "a"
        Case "9": modo = "escuro": lg = "h": tom = "a"
        Case Else: modo = "": lg = "": tom = ""
    End Select

    Dim uni As cdrUnit
    uni = ActiveDocument.Unit
    ActiveDocument.Unit = cdrMillimeter

    ' Quadrado em volta da foto (a loja usa foto quadrada).
    Dim fx As Double, fy As Double, fw As Double, fh As Double, lado As Double, esq As Double, topo As Double
    foto.GetBoundingBox fx, fy, fw, fh
    lado = fw
    If fh > lado Then lado = fh
    esq = fx + fw / 2 - lado / 2
    topo = fy + fh / 2 + lado / 2

    ' 4 cantos do vermelho, em % do quadrado.
    Dim dup As Shape, nd As Node, px As Double, py As Double
    Dim q(7) As Double, best(3) As Double, i As Integer
    best(0) = 1E+30: best(1) = -1E+30: best(2) = -1E+30: best(3) = 1E+30
    ActiveDocument.BeginCommandGroup "Enviar molde"
    Set dup = red.Duplicate
    dup.ConvertToCurves
    For Each nd In dup.Curve.Nodes
        px = (nd.PositionX - esq) / lado * 100
        py = (topo - nd.PositionY) / lado * 100
        If px + py < best(0) Then best(0) = px + py: q(0) = px: q(1) = py
        If px - py > best(1) Then best(1) = px - py: q(2) = px: q(3) = py
        If px + py > best(2) Then best(2) = px + py: q(4) = px: q(5) = py
        If px - py < best(3) Then best(3) = px - py: q(6) = px: q(7) = py
    Next nd
    dup.Delete

    ' Foto limpa: some com o vermelho, fundo branco quadrado, exporta 1000 x 1000 PNG.
    red.Fill.ApplyNoFill
    red.Outline.SetNoOutline
    Dim bg As Shape
    Set bg = ActiveLayer.CreateRectangle2(esq, topo - lado, lado, lado)
    bg.Fill.UniformColor.RGBAssign 255, 255, 255
    bg.Outline.SetNoOutline
    bg.OrderToBack
    Dim ex As New ShapeRange
    ex.AddRange sr
    ex.Add bg
    ex.CreateSelection
    Dim arq As String
    arq = Environ("TEMP") & "\dc-molde.png"
    On Error Resume Next
    Kill arq
    On Error GoTo erro
    Dim f As ExportFilter
    Set f = ActiveDocument.ExportBitmap(arq, cdrPNG, cdrSelection, cdrRGBColorImage, 1000, 1000, 150, 150, cdrNormalAntiAliasing, False, False, True, False, cdrCompressionNone)
    f.Finish
    ActiveDocument.EndCommandGroup
    ActiveDocument.Undo
    sr.CreateSelection
    ActiveDocument.Unit = uni

    ' Envia.
    Dim js As String, h As Object
    js = "{""sku"":""" & Replace(sku, """", "") & """,""modo"":""" & modo & """,""logo"":""" & lg & """,""tom"":""" & tom & """,""q"":[" & N(q(0)) & "," & N(q(1)) & "," & N(q(2)) & "," & N(q(3)) & "," & N(q(4)) & "," & N(q(5)) & "," & N(q(6)) & "," & N(q(7)) & "],""foto"":""" & Base64Arquivo(arq) & """}"
    Set h = CreateObject("MSXML2.ServerXMLHTTP.6.0")
    h.Open "POST", SITE, False
    h.setRequestHeader "Content-Type", "application/json"
    h.setRequestHeader "X-DC-Molde-Key", CHAVE
    h.send js
    Dim t As String, a As Long, b As Long
    t = h.responseText
    a = InStr(t, """mensagem"":""")
    If a > 0 Then
        a = a + 12
        b = InStr(a, t, """")
        t = Mid(t, a, b - a)
    End If
    If h.Status = 200 Then
        MsgBox t, vbInformation, "Enviado"
    Else
        MsgBox "Erro " & h.Status & ": " & t, vbExclamation, "Nao enviado"
    End If
    Exit Sub
erro:
    On Error Resume Next
    ActiveDocument.EndCommandGroup
    ActiveDocument.Undo
    ActiveDocument.Unit = uni
    MsgBox "Erro ao exportar: " & Err.Description, vbExclamation
End Sub
VBA;
	return str_replace( array( '{{URL}}', '{{CHAVE}}' ), array( $url, $chave ), $vba );
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=product', 'Moldes (Corel)', 'Moldes (Corel)', 'manage_woocommerce', 'dc-moldes-corel', 'dccm_pagina' );
	}
);

function dccm_pagina() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$nova = isset( $_POST['dccm_nova'] ) && check_admin_referer( 'dccm_nova' );
	$k    = dccm_chave( $nova );
	echo '<div class="wrap"><h1>Moldes pelo CorelDRAW</h1>';
	if ( $nova ) {
		echo '<div class="notice notice-success"><p>Chave nova criada. Cole a macro de novo no Corel (a antiga parou de funcionar).</p></div>';
	}
	echo '<div style="max-width:920px">'
		. '<h2>Como instalar (uma vez)</h2><ol style="font-size:14px;line-height:1.7">'
		. '<li>No CorelDRAW: <b>Ferramentas &gt; Scripts &gt; Editor de script</b> (Alt+F11).</li>'
		. '<li>No editor: menu <b>Insert &gt; Module</b> (Inserir &gt; Módulo).</li>'
		. '<li>Clique em <b>Copiar macro</b> aqui embaixo e cole tudo no módulo. Salve (Ctrl+S) e feche o editor.</li>'
		. '</ol>'
		. '<h2>Como usar</h2><ol style="font-size:14px;line-height:1.7">'
		. '<li>Coloque a foto de UM produto e desenhe o molde em <b>vermelho</b> (R237 G28 B36) por cima, onde a arte entra.</li>'
		. '<li>Selecione a foto + o vermelho.</li>'
		. '<li><b>Ferramentas &gt; Scripts &gt; Executar script</b> &gt; <b>EnviarMoldeParaSite</b>.</li>'
		. '<li>Digite o SKU do produto e o tipo de gravação. Pronto: a loja das franquias já mostra o logo de cada escola na área.</li>'
		. '</ol>'
		. '<p><button type="button" class="button button-primary" id="dccm_cp">Copiar macro</button> <span id="dccm_ok" style="color:#008a20;display:none">Copiado!</span></p>'
		. '<textarea id="dccm_tx" readonly style="width:100%;height:260px;font-family:monospace;font-size:12px">' . esc_textarea( dccm_macro( $k ) ) . '</textarea>'
		. '<p class="description">A macro tem a chave de acesso deste site. Não envie para ninguém. Se vazar, crie uma chave nova:</p>'
		. '<form method="post">' . wp_nonce_field( 'dccm_nova', '_wpnonce', true, false ) . '<input type="hidden" name="dccm_nova" value="1"><button class="button" onclick="return confirm(\'Criar chave nova? A macro antiga para de funcionar.\')">Criar chave nova</button></form>';
	$log = get_option( 'dccm_log', array() );
	if ( is_array( $log ) && $log ) {
		echo '<h2>Últimos envios</h2><table class="widefat striped"><thead><tr><th>Quando</th><th>SKU</th><th>Produto</th><th>Gravado</th><th>Gravação</th></tr></thead><tbody>';
		foreach ( $log as $l ) {
			echo '<tr><td>' . esc_html( $l['t'] ) . '</td><td>' . esc_html( $l['sku'] ) . '</td><td><a href="' . esc_url( get_edit_post_link( (int) $l['pid'] ) ) . '">' . esc_html( get_the_title( (int) $l['pid'] ) ) . '</a></td><td>' . esc_html( $l['o'] ) . '</td><td>' . esc_html( $l['m'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div></div><script>document.getElementById("dccm_cp").onclick=function(){var t=document.getElementById("dccm_tx");t.select();try{navigator.clipboard.writeText(t.value);}catch(e){document.execCommand("copy");}document.getElementById("dccm_ok").style.display="inline";};</script>';
}
