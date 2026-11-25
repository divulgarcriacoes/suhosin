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

' 4 cantos de um vermelho, em % do quadrado da foto: "x1,y1,x2,y2,x3,y3,x4,y4".
Private Function Cantos(red As Shape, esq As Double, topo As Double, lado As Double) As String
    Dim dup As Shape, nd As Node, px As Double, py As Double
    Dim q(7) As Double, best(3) As Double
    best(0) = 1E+30: best(1) = -1E+30: best(2) = -1E+30: best(3) = 1E+30
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