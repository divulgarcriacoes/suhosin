' ===== Divulgar Criacoes - Enviar molde para o site (CorelDRAW 2024) =====
' Como usar: selecione a FOTO do produto + o MOLDE VERMELHO por cima e rode "EnviarMoldeParaSite".
Option Explicit

Const SITE As String = "https://divulgarcriacoes.com.br/wp-json/dc/v1/molde"
Const CHAVE As String = "COLE_AQUI_A_CHAVE"  ' pegue em Produtos > Moldes (Corel) no painel do site

Private Function EhVermelho(sh As Shape) As Boolean
    On Error GoTo fim
    If sh.Fill.Type <> cdrUniformFill Then Exit Function
    Dim c As New Color
    c.CopyAssign sh.Fill.UniformColor
    c.ConvertToRGB
    EhVermelho = (c.RGBRed > 190 And c.RGBGreen < 90 And c.RGBBlue < 90)
fim:
End Function

Private Sub Coleta(sh As Shape, reds As ShapeRange, foto As ShapeRange)
    Dim c As Shape
    If sh.Type = cdrGroupShape Then
        For Each c In sh.Shapes
            Coleta c, reds, foto
        Next c
    ElseIf EhVermelho(sh) Then
        reds.Add sh
    Else
        foto.Add sh
    End If
End Sub

Private Function Atn2(y As Double, x As Double) As Double
    Const PI As Double = 3.14159265358979
    If x > 0 Then
        Atn2 = Atn(y / x)
    ElseIf x < 0 Then
        If y >= 0 Then Atn2 = Atn(y / x) + PI Else Atn2 = Atn(y / x) - PI
    Else
        If y >= 0 Then Atn2 = PI / 2 Else Atn2 = -PI / 2
    End If
End Function

' 4 cantos de um vermelho, em % do quadrado da foto: "x1,y1,x2,y2,x3,y3,x4,y4"
' (cima-esquerda, cima-direita, baixo-direita, baixo-esquerda). Funciona inclinado e em perspectiva.
Private Function Cantos(red As Shape, esq As Double, topo As Double, lado As Double) As String
    Dim dup As Shape, nd As Node, qt As Long, i As Long, j As Long, t As Long
    Dim xs(255) As Double, ys(255) As Double, q(7) As Double
    Set dup = red.Duplicate
    dup.ConvertToCurves
    qt = 0
    For Each nd In dup.Curve.Nodes
        If qt < 256 Then
            xs(qt) = (nd.PositionX - esq) / lado * 100
            ys(qt) = (topo - nd.PositionY) / lado * 100
            qt = qt + 1
        End If
    Next nd
    dup.Delete
    If qt > 1 Then
        If Abs(xs(qt - 1) - xs(0)) < 0.01 And Abs(ys(qt - 1) - ys(0)) < 0.01 Then qt = qt - 1
    End If
    If qt = 4 Then
        ' Retangulo/quadrilatero: usa as 4 pontas, em volta do centro, comecando em cima-esquerda.
        Dim cx As Double, cy As Double, ang(3) As Double, ord(3) As Long, s As Long, best As Double
        cx = (xs(0) + xs(1) + xs(2) + xs(3)) / 4
        cy = (ys(0) + ys(1) + ys(2) + ys(3)) / 4
        For i = 0 To 3
            ang(i) = Atn2(ys(i) - cy, xs(i) - cx)
            ord(i) = i
        Next i
        For i = 0 To 2
            For j = 0 To 2 - i
                If ang(ord(j)) > ang(ord(j + 1)) Then t = ord(j): ord(j) = ord(j + 1): ord(j + 1) = t
            Next j
        Next i
        s = 0: best = 1E+30
        For i = 0 To 3
            If xs(ord(i)) + ys(ord(i)) < best Then best = xs(ord(i)) + ys(ord(i)): s = i
        Next i
        For i = 0 To 3
            q(i * 2) = xs(ord((s + i) Mod 4))
            q(i * 2 + 1) = ys(ord((s + i) Mod 4))
        Next i
    Else
        ' Circulo/elipse ou forma livre: pontos mais afastados nas diagonais.
        Dim b(3) As Double
        b(0) = 1E+30: b(1) = -1E+30: b(2) = -1E+30: b(3) = 1E+30
        For i = 0 To qt - 1
            If xs(i) + ys(i) < b(0) Then b(0) = xs(i) + ys(i): q(0) = xs(i): q(1) = ys(i)
            If xs(i) - ys(i) > b(1) Then b(1) = xs(i) - ys(i): q(2) = xs(i): q(3) = ys(i)
            If xs(i) + ys(i) > b(2) Then b(2) = xs(i) + ys(i): q(4) = xs(i): q(5) = ys(i)
            If xs(i) - ys(i) < b(3) Then b(3) = xs(i) - ys(i): q(6) = xs(i): q(7) = ys(i)
        Next i
    End If
    Cantos = N(q(0)) & "," & N(q(1)) & "," & N(q(2)) & "," & N(q(3)) & "," & N(q(4)) & "," & N(q(5)) & "," & N(q(6)) & "," & N(q(7))
End Function

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
    Dim sr As ShapeRange, sh As Shape, reds As New ShapeRange, foto As New ShapeRange
    If ActiveDocument Is Nothing Then Exit Sub
    Set sr = ActiveSelectionRange
    If sr.Count = 0 Then
        MsgBox "Selecione a foto do produto e o molde vermelho por cima.", vbExclamation
        Exit Sub
    End If
    For Each sh In sr
        Coleta sh, reds, foto
    Next sh
    If reds.Count = 0 Then
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
    op = Trim(InputBox("Qual logo usar:" & vbCr & "1 = horizontal colorido p/ fundos claros" & vbCr & "2 = horizontal colorido p/ fundos escuros" & vbCr & "3 = horizontal branco" & vbCr & "4 = vertical colorido p/ fundos claros" & vbCr & "5 = vertical colorido p/ fundos escuros" & vbCr & "6 = vertical branco" & vbCr & "7 = colorido automatico (pela cor de cada foto)" & vbCr & "8 = laser em metal - logo horizontal" & vbCr & "9 = 1 cor escura (bambu/kraft)" & vbCr & "10 = laser em metal - logo vertical" & vbCr & "11 = laser em cortica/madeira (marrom) - logo vertical" & vbCr & "Enter = manter o do site", "Enviar molde", ""))
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
        Case "10": modo = "laser": lg = "v": tom = "a"
        Case "11": modo = "queimado": lg = "v": tom = "a"
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

    ' Cantos de cada vermelho (pode ter mais de um: um logo em cada area).
    Dim q1 As String, qx As String, k As Long
    ActiveDocument.BeginCommandGroup "Enviar molde"
    For k = 1 To reds.Count
        If k = 1 Then
            q1 = Cantos(reds(k), esq, topo, lado)
        Else
            If qx <> "" Then qx = qx & ","
            qx = qx & "[" & Cantos(reds(k), esq, topo, lado) & "]"
        End If
    Next k

    ' Foto limpa: some com os vermelhos, fundo branco quadrado, exporta 1000 x 1000 PNG.
    For k = 1 To reds.Count
        reds(k).Fill.ApplyNoFill
        reds(k).Outline.SetNoOutline
    Next k
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
    js = "{""sku"":""" & Replace(sku, """", "") & """,""modo"":""" & modo & """,""logo"":""" & lg & """,""tom"":""" & tom & """,""q"":[" & q1 & "],""qx"":[" & qx & "],""foto"":""" & Base64Arquivo(arq) & """}"
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