#!/usr/bin/env python3
"""Gerador automatico de artes: produtos do WooCommerce + cenarios de IA (ComfyUI).

Uso:
  python gerador.py --limite 4                 # 4 produtos, cenario por IA
  python gerador.py --limite 4 --sem-ia        # sem ComfyUI (fundo em degrade)
  python gerador.py --produto-id 93008         # um produto especifico
  python gerador.py --demo                     # produto desenhado, sem internet
Variaveis de ambiente (opcionais, so para ler produtos pela API):
  WC_KEY, WC_SECRET   chaves somente-leitura do WooCommerce
"""
import argparse, io, json, os, random, re, sys, time, uuid
from html import unescape
from pathlib import Path

import requests
from PIL import Image, ImageChops, ImageDraw, ImageFilter, ImageFont

AQUI = Path(__file__).parent
CFG = json.loads((AQUI / "config.json").read_text(encoding="utf-8"))


# ---------- produtos ----------
def _store_para_v3(p):
    """Converte um produto da API publica (Store API) para o formato usado aqui."""
    pr = p.get("prices") or {}
    casas = int(pr.get("currency_minor_unit", 2))
    try:
        preco = str(int(pr.get("price", "0")) / (10 ** casas))
    except (TypeError, ValueError):
        preco = "0"
    return {"id": p.get("id", 0), "name": p.get("name", ""), "price": preco, "permalink": p.get("permalink", ""), "sku": p.get("sku", ""),
            "estoque": "instock" if p.get("is_in_stock", True) else "outofstock",
            "qtd": p.get("low_stock_remaining"),
            "categories": [{"name": c.get("name", "")} for c in p.get("categories", [])],
            "images": [{"src": i.get("src", "")} for i in p.get("images", [])]}


def buscar_produtos(limite, produto_id=None, categoria=None, ids=None, busca=None, sku=None, ordem="date"):
    loja = CFG["loja"]["url"].rstrip("/")
    cab = {"User-Agent": "Mozilla/5.0 (gerador-artes)"}
    if os.environ.get("WC_KEY"):
        auth = (os.environ["WC_KEY"], os.environ["WC_SECRET"])
        base = loja + "/wp-json/wc/v3/products"
        if produto_id:
            r = requests.get(f"{base}/{produto_id}", auth=auth, headers=cab, timeout=30)
            r.raise_for_status()
            return [r.json()]
        r = requests.get(base, auth=auth, headers=cab, timeout=30,
                         params={"per_page": limite, "status": "publish", "orderby": "date"})
        r.raise_for_status()
        return r.json()
    # sem chaves: usa a API publica da loja (nao precisa de login)
    base = loja + "/wp-json/wc/store/v1/products"
    if produto_id:
        r = requests.get(f"{base}/{produto_id}", headers=cab, timeout=30)
        r.raise_for_status()
        return [_store_para_v3(r.json())]
    params = {"per_page": limite, "orderby": ordem, "order": "asc" if ordem == "price" else "desc"}
    if categoria:
        params["category"] = categoria
    if ids:
        params["include"] = ",".join(str(i) for i in ids)
        params["per_page"] = max(limite, len(ids))
    if busca:
        params["search"] = busca
    if sku:
        params["sku"] = sku
    quer = params["per_page"]
    out, pagina = [], 1
    while len(out) < quer:
        params.update(per_page=min(100, quer - len(out)), page=pagina)
        r = requests.get(base, headers=cab, timeout=30, params=params)
        r.raise_for_status()
        lote = r.json()
        out += [_store_para_v3(p) for p in lote]
        if len(lote) < params["per_page"]:
            break
        pagina += 1
    return out[:quer]


def pedido_minimo(link):
    """Tenta ler 'Pedido minimo: N un' na pagina do produto. Se nao der, retorna None."""
    if not link:
        return None
    try:
        r = requests.get(link, headers={"User-Agent": "Mozilla/5.0 (gerador-artes)"}, timeout=20)
        m = re.search(r"Pedido m[ií]nimo:?\s*(?:<[^>]+>\s*)*(\d+)", r.text)
        return int(m.group(1)) if m else None
    except Exception:
        return None


def baixar_imagem(url):
    pasta = AQUI / "cache"
    pasta.mkdir(parents=True, exist_ok=True)
    nome = pasta / re.sub(r"[^\w.-]", "_", url.split("?")[0].split("/")[-1])
    if not nome.exists():
        r = requests.get(url, headers={"User-Agent": "Mozilla/5.0 (gerador-artes)"}, timeout=60)
        r.raise_for_status()
        nome.write_bytes(r.content)
    return Image.open(nome).convert("RGBA")


# ---------- recorte ----------
_sessao = None
def recortar(img):
    """Remove o fundo com rembg. Retorna RGBA com o produto isolado."""
    global _sessao
    from rembg import new_session, remove
    if _sessao is None:
        _sessao = new_session("isnet-general-use")
    buf = io.BytesIO(); img.convert("RGB").save(buf, "PNG")
    return Image.open(io.BytesIO(remove(buf.getvalue(), session=_sessao))).convert("RGBA")


def aparar(img):
    caixa = img.getchannel("A").point(lambda a: 255 if a > 8 else 0).getbbox()
    return img.crop(caixa) if caixa else img


# ---------- cenario ----------
def prompt_cenario(produto, forcado=None):
    cen = CFG["cenarios_por_categoria"]
    if forcado:
        return forcado
    nome = unescape(produto.get("name", "")).lower()
    # 1) palavras no nome do produto (ex.: "champagne" -> cenario de vinho)
    for palavra, chave in CFG.get("cenarios_por_palavra", {}).items():
        if palavra in nome and chave in cen:
            return cen[chave]
    # 2) categoria do produto
    for c in [c["name"].lower() for c in produto.get("categories", [])]:
        if c in cen:
            return cen[c]
    return cen["default"]


def cenario_comfyui(prompt):
    c = CFG["comfyui"]
    wf = json.loads((AQUI / "workflows" / "sdxl_cenario.json").read_text())
    wf["3"]["inputs"].update(seed=random.randint(0, 2**32 - 1), steps=c["passos"], cfg=c["cfg"])
    wf["4"]["inputs"]["ckpt_name"] = c["checkpoint"]
    wf["5"]["inputs"].update(width=c["largura"], height=c["altura"])
    wf["6"]["inputs"]["text"] = prompt
    wf["7"]["inputs"]["text"] = CFG["prompt_negativo"]
    url = c["url"].rstrip("/")
    pid = requests.post(f"{url}/prompt", json={"prompt": wf, "client_id": str(uuid.uuid4())},
                        timeout=30).json()["prompt_id"]
    fim = time.time() + c["timeout_segundos"]
    while time.time() < fim:
        hist = requests.get(f"{url}/history/{pid}", timeout=30).json()
        if pid in hist:
            for saida in hist[pid]["outputs"].values():
                for im in saida.get("images", []):
                    r = requests.get(f"{url}/view", params=im, timeout=60)
                    return Image.open(io.BytesIO(r.content)).convert("RGB")
        time.sleep(1.5)
    raise TimeoutError("ComfyUI nao respondeu a tempo")


def produto_escuro(rgba):
    """True se o produto e predominantemente escuro (preto, azul-marinho...)."""
    pq = rgba.copy(); pq.thumbnail((120, 120))
    px = [p for p in list(pq.getdata()) if p[3] > 200]
    if not px:
        return False
    lum = sum(0.299 * r + 0.587 * g + 0.114 * b for r, g, b, _ in px) / len(px)
    return lum < 105


def cenario_degrade(tam, claro=False):
    if claro:   # produto escuro: fundo claro para destacar
        topo, base = (250, 248, 244), (214, 220, 228)
        img = Image.new("RGB", (1, tam[1]))
        for y in range(tam[1]):
            t = y / tam[1]
            img.putpixel((0, y), tuple(int(topo[k] * (1 - t) + base[k] * t) for k in range(3)))
        return img.resize(tam)
    a = tuple(int(CFG["loja"]["cor_principal"][i:i+2], 16) for i in (1, 3, 5))
    img = Image.new("RGB", (1, tam[1]))
    for y in range(tam[1]):
        t = y / tam[1]
        img.putpixel((0, y), tuple(int(a[k] * (1 - t * .55) + 235 * t * .55) for k in range(3)))
    return img.resize(tam)


# ---------- composicao ----------
def fonte(tam, negrito=True):
    for p in ["/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf" if negrito else "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
              "C:/Windows/Fonts/arialbd.ttf" if negrito else "C:/Windows/Fonts/arial.ttf",
              "/System/Library/Fonts/Helvetica.ttc"]:
        if Path(p).exists():
            return ImageFont.truetype(p, tam)
    return ImageFont.load_default()


def quebrar(draw, texto, f, largura):
    linhas, atual = [], ""
    for pal in texto.split():
        teste = (atual + " " + pal).strip()
        if draw.textlength(teste, font=f) <= largura:
            atual = teste
        else:
            linhas.append(atual); atual = pal
    if atual: linhas.append(atual)
    return linhas[:3]


def _pill(d, xy, texto, f, bg, fg, pad_x, pad_y, raio=18):
    """Desenha um texto dentro de uma pilula colorida. xy = canto superior esquerdo. Retorna (x1, y1, x2, y2)."""
    w = d.textlength(texto, font=f)
    x, y = xy
    caixa = (x, y, x + w + pad_x * 2, y + f.size + pad_y * 2)
    d.rounded_rectangle(caixa, raio, fill=bg)
    d.text((x + pad_x, y + pad_y - f.size * .05), texto, font=f, fill=fg)
    return caixa


def selo_estoque(d, W, margem, estoque, qtd=None, mostrar_qtd=False):
    """Selo no canto superior direito: EM ESTOQUE / ULTIMAS UNIDADES / SOB CONSULTA."""
    f = fonte(int(W * .03))
    if estoque == "outofstock":
        texto, cor = "SOB CONSULTA", "#5B6B7F"
    elif qtd is not None and 0 < int(qtd) <= 20:
        texto, cor = "ÚLTIMAS UNIDADES", "#D98A00"
    elif mostrar_qtd and qtd:
        texto, cor = f"EM ESTOQUE: {int(qtd):,} un".replace(",", "."), "#1E9E5A"
    else:
        texto, cor = "EM ESTOQUE", "#1E9E5A"
    texto = "●  " + texto
    larg = d.textlength(texto, font=f) + int(W * .04)
    _pill(d, (W - margem - larg, margem), texto, f, cor, "white", int(W * .02), int(W * .01), 14)


def faixa_simples(fundo, W, H, nome, preco):
    d = ImageDraw.Draw(fundo)
    faixa_h = int(H * (.2 if H <= W else .13))
    fundo.alpha_composite(Image.new("RGBA", (W, faixa_h), CFG["loja"]["cor_principal"] + "E6"), (0, H - faixa_h))
    f_preco = fonte(int(W * .06))
    margem = int(W * .06)
    for fator in (1.0, .88, .78, .7, .62):
        f_nome = fonte(int(W * .045 * fator))
        linhas = quebrar(d, nome, f_nome, W * .5)
        if len(linhas) <= 2:
            break
    ty = H - faixa_h + (faixa_h - len(linhas) * f_nome.size * 1.2) / 2
    for l in linhas:
        d.text((margem, ty), l, font=f_nome, fill="white"); ty += f_nome.size * 1.2
    txt = f"R$ {preco}"
    larg = d.textlength(txt, font=f_preco)
    bx0, by0 = W - margem - larg - 40, H - faixa_h + (faixa_h - f_preco.size * 1.7) / 2
    d.rounded_rectangle((bx0, by0, W - margem, by0 + f_preco.size * 1.7), 18, fill=CFG["loja"]["cor_destaque"])
    d.text((bx0 + 20, by0 + f_preco.size * .3), txt, font=f_preco, fill=CFG["loja"]["cor_principal"])


def faixa_comercial(fundo, W, H, nome, preco, minimo, cor_faixa=None, etiqueta=True):
    """Layout de venda: etiqueta no topo, 'a partir de' + preco por unidade, pedido minimo e botao de orcamento."""
    lj = CFG["loja"]
    cor_cta = lj.get("cor_cta", "#FF5900")
    d = ImageDraw.Draw(fundo)
    margem = int(W * .05)
    faixa_h = int(H * (.31 if H <= W else .215))
    topo = H - faixa_h
    cor_f = cor_faixa or lj["cor_principal"]
    fundo.alpha_composite(Image.new("RGBA", (W, faixa_h), cor_f + "F5"), (0, topo))
    d.rectangle((0, topo, W, topo + max(4, int(H * .004))), fill=cor_cta)   # filete laranja

    # etiqueta no topo
    if etiqueta:
        _pill(d, (margem, margem), lj.get("etiqueta", "TUDO PARA SUA MARCA!"), fonte(int(W * .03)),
              cor_cta, "white", int(W * .02), int(W * .01), 14)

    # linha 1: nome (esquerda) e preco (direita)
    f_pre = fonte(int(W * .062))
    f_ap = fonte(int(W * .026), False)
    f_un = fonte(int(W * .03))
    txt_preco = f"R$ {preco}"
    larg_p = d.textlength(txt_preco, font=f_pre) + int(W * .05)
    y1 = topo + int(faixa_h * .09)
    d.text((W - margem - larg_p + int(W * .005), y1), "a partir de", font=f_ap, fill="#D7DFEA")
    caixa = _pill(d, (W - margem - larg_p, y1 + f_ap.size * 1.3), txt_preco, f_pre,
                  lj["cor_destaque"], cor_f, int(W * .025), int(W * .012), 18)
    d.text((caixa[2] - d.textlength("/un", font=f_un), y1 + 2), "preço por unidade", font=f_ap, fill="#D7DFEA") if False else None
    d.text((caixa[2] - d.textlength("por unidade", font=f_ap), y1), "por unidade", font=f_ap, fill="#D7DFEA")

    larg_nome = W - 2 * margem - larg_p - int(W * .03)
    for fator in (1.0, .88, .78, .7, .62):
        f_nome = fonte(int(W * .048 * fator))
        linhas = quebrar(d, nome, f_nome, larg_nome)
        if len(linhas) <= 2:
            break
    ty = y1 + 4
    for l in linhas:
        d.text((margem, ty), l, font=f_nome, fill="white"); ty += f_nome.size * 1.22

    # linha 2: pedido minimo / site (esquerda) e botao de orcamento (direita)
    f_btn = fonte(int(W * .034))
    f_inf = fonte(int(W * .03), False)
    wa = lj.get("whatsapp", "").strip()
    texto_btn = "ORÇAMENTO NO WHATSAPP" if wa else lj.get("cta", "PEÇA SEU ORÇAMENTO")
    larg_b = d.textlength(texto_btn, font=f_btn) + int(W * .06)
    alt_b = f_btn.size * 1.9
    yb = H - margem * .7 - alt_b
    d.rounded_rectangle((W - margem - larg_b, yb, W - margem, yb + alt_b), int(alt_b / 2), fill=cor_cta)
    d.text((W - margem - larg_b + int(W * .03), yb + (alt_b - f_btn.size) / 2 - f_btn.size * .06),
           texto_btn, font=f_btn, fill="white")
    linhas_inf = []
    if minimo:
        linhas_inf.append(f"Pedido mínimo: {minimo} un")
    linhas_inf.append(wa or lj["url"].replace("https://", "").replace("http://", "").rstrip("/"))
    yi = yb + (alt_b - len(linhas_inf) * f_inf.size * 1.25) / 2
    for l in linhas_inf:
        d.text((margem, yi), l, font=f_inf, fill="#D7DFEA"); yi += f_inf.size * 1.25
    return topo


def montar(produto_rgba, fundo, tam, nome, preco, ia=False, estilo="comercial", minimo=None, estoque=None, qtd=None, mostrar_qtd=False):
    W, H = tam
    padrao = estilo == "padrao"
    comercial = estilo in ("comercial", "padrao")
    # fundo cobre o formato (corta o excesso)
    fundo = fundo.copy()
    esc = max(W / fundo.width, H / fundo.height)
    fundo = fundo.resize((int(fundo.width * esc) + 1, int(fundo.height * esc) + 1), Image.LANCZOS)
    fundo = fundo.crop(((fundo.width - W) // 2, (fundo.height - H) // 2,
                        (fundo.width - W) // 2 + W, (fundo.height - H) // 2 + H)).convert("RGBA")

    if padrao:
        tpl = AQUI / "modelo" / ("fundo_story.png" if H > W else "fundo_feed.png")
        if tpl.exists():
            fundo = Image.open(tpl).convert("RGBA").resize((W, H), Image.LANCZOS)
        else:
            print(f"  ! modelo nao encontrado: {tpl}", file=sys.stderr)

    # area livre do produto (entre a etiqueta do topo e a faixa de baixo)
    faixa_h = int(H * ((.31 if H <= W else .215) if comercial else (.2 if H <= W else .13)))
    topo_livre = int(H * ((.2 if H > W else .22) if padrao else (.11 if comercial else .02)))
    area_h = H - faixa_h - topo_livre - int(H * .04)
    p = aparar(produto_rgba)
    esc = min(W * (.74 if H > W else .66) / p.width, area_h * (.96 if comercial else 1.0) / p.height)
    p = p.resize((max(1, int(p.width * esc)), max(1, int(p.height * esc))), Image.LANCZOS)
    x = (W - p.width) // 2
    y = int(topo_livre + area_h * (.54 if ia else .5) - p.height / 2)

    # sombra de contato: uma elipse fina e escura rente a base + uma sombra suave e larga
    base_y = y + p.height
    sombra = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    larga = Image.new("L", (W, H), 0)
    ImageDraw.Draw(larga).ellipse((x + p.width * .02, base_y - p.height * .03,
                                   x + p.width * .98, base_y + p.height * .07), fill=120)
    fina = Image.new("L", (W, H), 0)
    ImageDraw.Draw(fina).ellipse((x + p.width * .06, base_y - p.height * .012,
                                  x + p.width * .94, base_y + p.height * .022), fill=215)
    alfa = ImageChops.lighter(larga.filter(ImageFilter.GaussianBlur(p.width * .035)),
                              fina.filter(ImageFilter.GaussianBlur(max(2, p.width * .008))))
    sombra.putalpha(alfa)
    if padrao:   # luz suave atras do produto, para destacar produtos escuros sobre o fundo cinza
        luz = Image.new("L", (W, H), 0)
        ImageDraw.Draw(luz).ellipse((x - p.width * .12, y - p.height * .06, x + p.width * 1.12, y + p.height * 1.06), fill=95)
        halo = Image.new("RGBA", (W, H), (255, 255, 255, 0))
        halo.putalpha(luz.filter(ImageFilter.GaussianBlur(p.width * .10)))
        fundo.alpha_composite(halo)
    fundo.alpha_composite(sombra)
    fundo.alpha_composite(p, (x, y))

    if comercial:
        faixa_comercial(fundo, W, H, nome, preco, minimo, "#1C1C1A" if padrao else None, not padrao)
        if padrao:
            slg = AQUI / "modelo" / "slogan.png"
            if slg.exists():
                sg = Image.open(slg).convert("RGBA")
                lw = int(W * (.62 if H > W else .5))
                sg = sg.resize((lw, int(sg.height * lw / sg.width)), Image.LANCZOS)
                fundo.alpha_composite(sg, (int(W * .03), int(H * .015)))
        selo_estoque(ImageDraw.Draw(fundo), W, int(W * .05), estoque, qtd, mostrar_qtd)
    else:
        faixa_simples(fundo, W, H, nome, preco)

    # logo (opcional): no estilo comercial fica no canto superior direito
    logo = AQUI / CFG["loja"]["logo"]
    margem = int(W * .05)
    if logo.exists():
        lg = Image.open(logo).convert("RGBA"); lg.thumbnail((int(W * .22), int(H * .08)))
        fundo.alpha_composite(lg, (((W - lg.width) // 2 if comercial else margem), margem if not comercial else int(margem * 1.9)))
    return fundo.convert("RGB")


# ---------- arte padrao da Divulgar (modelo aprovado) ----------
def fonte_modelo(nome_arquivo, tam):
    f = AQUI / "modelo" / nome_arquivo
    if f.exists():
        return ImageFont.truetype(str(f), max(8, int(tam)))
    return fonte(int(tam))


def minimo_na_pagina(t):
    """Pedido minimo do produto na pagina: campo de quantidade (min), 'QUANTIDADE MINIMA', 'minimo de N'.
    'Pedido minimo' solto fica por ultimo porque a pagina tambem lista produtos relacionados."""
    for tag in re.findall(r"<input[^>]*>", t, flags=re.I):
        if re.search(r'name=["\']quantity["\']', tag, flags=re.I):
            m = re.search(r'\smin=["\'](\d+)["\']', tag, flags=re.I)
            if m and int(m.group(1)) > 1:
                return int(m.group(1))
    for rx in (r"QUANTIDADE\s+M[ÍI]NIMA:?\s*(?:<[^>]+>\s*)*(\d+)",
               r"M[ÍI]NIMO\s+DE\s+(\d+)\s*(?:UN|UNID)",
               r"Pedido\s+m[ií]nimo:?\s*(?:<[^>]+>\s*)*(\d+)"):
        m = re.search(rx, t, flags=re.I)
        if m and int(m.group(1)) > 1:
            return int(m.group(1))
    return None


def ler_pagina(link):
    """Le na pagina do produto: pedido minimo, quantidade em estoque e tecnica de gravacao (o que achar)."""
    out = {}
    if not link:
        return out
    try:
        r = requests.get(link, headers={"User-Agent": "Mozilla/5.0 (gerador-artes)"}, timeout=25)
        t = r.text
    except Exception:
        return out
    out_min = minimo_na_pagina(t)
    if out_min:
        out["minimo"] = out_min
    m = re.search(r"(\d[\d.]*)\s+em estoque", t)
    if m:
        out["qtd"] = int(m.group(1).replace(".", ""))
    m = re.search(r"Grava[cç][aã]o:?\s*(?:<[^>]+>\s*)*(?:<img[^>]*>\s*)?([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ ]{2,24})\s*<", t)
    if m:
        out["tecnica"] = m.group(1).strip()
    return out


def texto_gravacao(tecnica):
    base = "GRAVAÇÃO DA SUA MARCA"
    if not tecnica:
        return base
    t = re.sub(r"^(UMA\s+)?GRAVA[CÇ][AÃ]O\s+(EM\s+|A\s+|DE\s+)?", "", tecnica.upper().strip())
    if not t:
        return base
    return f"{base} A {t}" if t.startswith("LASER") else f"{base} EM {t}"


def _ajustar(d, texto, nome_fonte, tam, larg, linhas_max, minimo=.55):
    """Reduz a fonte ate o texto caber em linhas_max linhas. Retorna (fonte, linhas)."""
    f = fonte_modelo(nome_fonte, tam)
    fator = 1.0
    while True:
        f = fonte_modelo(nome_fonte, tam * fator)
        linhas = quebrar(d, texto, f, larg)
        if (len(linhas) <= linhas_max and all(d.textlength(l, font=f) <= larg for l in linhas)) or fator <= minimo:
            return f, linhas      # nunca corta palavras do nome
        fator -= .04


def logo_padrao(W_ref, k, d, fundo, x, y, w, h):
    """Desenha o logo (modelo/logo.png) so se ele existir e se o logo nao estiver ja fixo no modelo."""
    if CFG["loja"].get("logo_no_modelo"):
        return
    lg = AQUI / "modelo" / "logo.png"
    if not lg.exists():
        lg = AQUI / CFG["loja"].get("logo", "logo.png")
    if lg.exists():
        im = Image.open(lg).convert("RGBA"); im.thumbnail((int(w * k), int(h * k)))
        fundo.alpha_composite(im, (int(x * k), int(y * k)))


def tamanho_do_modelo(fmt):
    """Largura do config.json + altura na proporcao do modelo (ex.: feed 4:5 se o fundo for 1080x1350)."""
    base = tuple(CFG["formatos"][fmt])
    tpl = fundo_do_tema(fmt == "story")
    if tpl.exists():
        try:
            with Image.open(tpl) as im:
                return (base[0], int(round(base[0] * im.height / im.width)))
        except Exception:
            pass
    return base


def detectar_cartao(fundo):
    """Procura no fundo um quadro branco grande (se o modelo ja trouxer o quadro desenhado).
    Retorna (x0, y0, x1, y1) em pixels do fundo, ou None."""
    try:
        W, H = fundo.size
        k = 360 / max(W, H)
        pq = fundo.convert("RGB").resize((max(1, int(W * k)), max(1, int(H * k))), Image.BILINEAR)
        mask = pq.point(lambda v: 255 if v >= 246 else 0).convert("L") if False else None
        r, g, b = pq.split()
        lim = lambda v: 255 if v >= 246 else 0
        mask = ImageChops.multiply(ImageChops.multiply(r.point(lim), g.point(lim)), b.point(lim))
        mask = mask.filter(ImageFilter.MinFilter(15))           # apaga tracos finos (aneis, textos)
        box = None
        try:                                                     # fica so com o MAIOR bloco branco (ignora logo branco, aneis etc.)
            import numpy as np
            from scipy import ndimage
            lab, n = ndimage.label(np.array(mask) > 0)
            if n:
                tam = np.bincount(lab.ravel()); tam[0] = 0
                sl = ndimage.find_objects(lab)[int(tam.argmax()) - 1]
                box = (sl[1].start, sl[0].start, sl[1].stop, sl[0].stop)
        except Exception:
            box = mask.getbbox()
        if not box:
            return None
        bw, bh = box[2] - box[0], box[3] - box[1]
        if bw * bh < 0.12 * pq.width * pq.height:
            return None
        m = 7                                                    # devolve a margem comida pela erosao
        return (int((box[0] - m) / k), int((box[1] - m) / k), int((box[2] + m) / k), int((box[3] + m) / k))
    except Exception:
        return None


FOTO_ZOOM = 1.0
TEMA = {"pasta": None, "cor": "#FF5900"}    # --tema: pasta temas/<nome> com fundo_feed.png, fundo_story.png e tema.json


def temas_disponiveis():
    base = AQUI / "temas"
    return sorted(p.name for p in base.iterdir() if p.is_dir() and (p / "fundo_feed.png").exists()) if base.exists() else []


def ativar_tema(nome):
    pasta = AQUI / "temas" / nome
    if not (pasta / "fundo_feed.png").exists() or not (pasta / "fundo_story.png").exists():
        sys.exit(f"Tema '{nome}' nao encontrado. Crie a pasta temas/{nome}/ com fundo_feed.png e fundo_story.png.\n"
                 f"Temas disponiveis: {', '.join(temas_disponiveis()) or '(nenhum ainda)'}")
    TEMA["pasta"] = pasta
    cfg = pasta / "tema.json"
    if cfg.exists():
        try:
            TEMA["cor"] = json.loads(cfg.read_text(encoding="utf-8")).get("cor_destaque", TEMA["cor"])
        except Exception as e:
            print(f"  ! tema.json invalido ({e}); usando a cor padrao", file=sys.stderr)


def fundo_do_tema(story):
    nome = "fundo_story.png" if story else "fundo_feed.png"
    return (TEMA["pasta"] or AQUI / "modelo") / nome


def montar_padrao(produto_rgba, tam, nome, preco, codigo=None, qtd=None, minimo=None, gravacao=None, cartao=False, formato=None):
    """Arte padrao: fundo da marca + slogan + produto + codigo, nome, estoque e preco (igual ao modelo aprovado)."""
    W, H = tam
    story = (formato == "story") if formato else H > W
    k = W / (1081 if story else 1454)           # as medidas abaixo estao na escala dos modelos aprovados
    dy = H / k - (1921 if story else 1454)      # modelo mais alto/baixo que o de referencia (ex.: feed 4:5): o que fica embaixo acompanha
    tpl = fundo_do_tema(story)
    fundo = (Image.open(tpl).convert("RGBA").resize((W, H), Image.LANCZOS) if tpl.exists()
             else Image.new("RGBA", (W, H), (60, 60, 60, 255)))
    d = ImageDraw.Draw(fundo)
    ORANGE, DARK = TEMA["cor"], "#1C1C1A"
    S = lambda v: int(v * k)

    # logo + slogan
    if story:
        logo_padrao(1081, k, d, fundo, 410, 50, 275, 120)
        sw, sx, sy = 760, 180, 170
    else:
        logo_padrao(1454, k, d, fundo, 50, 55, 220, 105)
        sw, sx, sy = (540, 1454 - 540 - 60, 55) if cartao else (600, 110, 130)
    card_rect = None
    texto_y0 = None
    # produto
    if cartao:
        embutido = detectar_cartao(fundo)          # o modelo ja traz o quadro branco?
        if embutido:
            ex0, ey0, ex1, ey1 = embutido
            cx0, cy0, cx1, cy1 = ex0 / k, ey0 / k, ex1 / k, ey1 / k
            d = ImageDraw.Draw(fundo)
        else:
            if story:
                cx0, cy0, cx1, cy1 = 110, 540, 970, 1150
            else:
                cx0, cy0, cx1, cy1 = 210, 300, 1244, 990
            sh = Image.new("RGBA", (W, H), (0, 0, 0, 0))
            ImageDraw.Draw(sh).rounded_rectangle((S(cx0) + S(8), S(cy0) + S(14), S(cx1) + S(8), S(cy1) + S(14)), S(46), fill=(0, 0, 0, 120))
            fundo.alpha_composite(Image.alpha_composite(Image.new("RGBA", (W, H), (0, 0, 0, 0)), sh.filter(ImageFilter.GaussianBlur(S(14)))))
            d = ImageDraw.Draw(fundo)
            d.rounded_rectangle((S(cx0), S(cy0), S(cx1), S(cy1)), S(46), fill="white")
        card_rect = (cx0, cy0, cx1, cy1)
        y_cod_ref = (1180 if story else 1015) + dy
        pb = cy1
        if embutido and cy1 + 15 > y_cod_ref:       # quadro do modelo invade a area do texto: o texto fica FORA do branco
            texto_y0 = cy1 + 15
        foto = produto_rgba.convert("RGBA")
        branco = Image.new("RGBA", foto.size, (255, 255, 255, 255)); branco.alpha_composite(foto); foto = branco.convert("RGB")
        # corta a margem branca da foto, para o produto ocupar melhor o quadro
        caixa = foto.convert("L").point(lambda v: 255 if v < 240 else 0).getbbox()
        if caixa:
            m = int(max(foto.size) * .03)
            caixa = (max(0, caixa[0] - m), max(0, caixa[1] - m), min(foto.width, caixa[2] + m), min(foto.height, caixa[3] + m))
            if (caixa[2] - caixa[0]) > foto.width * .15 and (caixa[3] - caixa[1]) > foto.height * .15:
                foto = foto.crop(caixa)
        pad = S(26)
        esc = min((S(cx1 - cx0) - 2 * pad) / foto.width, (S(pb - cy0) - 2 * pad) / foto.height)
        esc *= max(0.3, FOTO_ZOOM)        # --zoom: aumenta a foto dentro do quadro (o que passar da borda e cortado, como no PowerClip)
        foto = foto.resize((max(1, int(foto.width * esc)), max(1, int(foto.height * esc))), Image.LANCZOS)
        qx0, qy0, qx1, qy1 = S(cx0), S(cy0), S(cx1), S(pb)
        camada = Image.new("RGB", (qx1 - qx0, qy1 - qy0), "white")
        camada.paste(foto, ((qx1 - qx0) // 2 - foto.width // 2, (qy1 - qy0) // 2 - foto.height // 2))
        mascara = Image.new("L", camada.size, 0)
        ImageDraw.Draw(mascara).rounded_rectangle((0, 0, camada.width - 1, camada.height - 1), S(46), fill=255)
        fundo.paste(camada, (qx0, qy0), mascara)
    else:
        p = aparar(produto_rgba)
        if story:
            ax0, ay0, ax1, ay1 = 60, 520, 1020, 1175
        else:
            ax0, ay0, ax1, ay1 = 430, 150, 1340, 1040
        esc = min(S(ax1 - ax0) / p.width, S(ay1 - ay0) / p.height)
        p = p.resize((max(1, int(p.width * esc)), max(1, int(p.height * esc))), Image.LANCZOS)
        px = S((ax0 + ax1) / 2) - p.width // 2 + (0 if story else S(30))
        py = S(ay1) - p.height - S(10)
        sombra = Image.new("RGBA", (W, H), (0, 0, 0, 0))
        ell = Image.new("L", (W, H), 0)
        ImageDraw.Draw(ell).ellipse((px + p.width * .08, py + p.height - p.height * .025, px + p.width * .92, py + p.height + p.height * .03), fill=150)
        sombra.putalpha(ell.filter(ImageFilter.GaussianBlur(max(3, p.width * .02))))
        fundo.alpha_composite(sombra)
        fundo.alpha_composite(p, (px, py))
    slg = AQUI / "modelo" / "slogan.png"
    if slg.exists() and not CFG["loja"].get("slogan_no_modelo"):     # o slogan fica por cima, como no modelo
        sg = Image.open(slg).convert("RGBA")
        sg = sg.resize((S(sw), int(sg.height * S(sw) / sg.width)), Image.LANCZOS)
        fundo.alpha_composite(sg, (S(sx), S(sy)))
    d = ImageDraw.Draw(fundo)

    # codigo (etiqueta laranja)
    f_cod = fonte_modelo("BebasNeue-Regular.ttf", S(40))
    t_cod = f"CÓDIGO - {codigo}" if codigo else None
    # nome do produto
    if story:
        f_nome, linhas = _ajustar(d, nome.upper(), "BebasNeue-Regular.ttf", S(100), S(960), 3)
        y_cod, y_nome, cx = 1180 + dy, 1262 + dy, 540
    else:
        f_nome, linhas = _ajustar(d, nome.upper(), "BebasNeue-Regular.ttf", S(100), S(650), 3)
        y_cod, y_nome, cx = 1015 + dy, 1100 + dy, None
    if texto_y0:
        y_cod = texto_y0
        y_nome = texto_y0 + (62 if story else 78)
    limite_y = (1608 if story else 1395) + dy      # deixa uma margem embaixo, para o nome nao colar na borda            # a faixa do preco (story) ou o fim da arte (feed)
    while (len(linhas) * f_nome.size * .9 > S(limite_y - y_nome) and f_nome.size > S(38)):
        f_nome, linhas = _ajustar(d, nome.upper(), "BebasNeue-Regular.ttf", f_nome.size * .92, S(960 if story else 650), 3, .4)
    if t_cod:
        wc = d.textlength(t_cod, font=f_cod) + S(40)
        x0 = S(cx) - wc / 2 if story else S(120)
        d.rounded_rectangle((x0, S(y_cod), x0 + wc, S(y_cod) + S(60)), S(12), fill=ORANGE)
        d.text((x0 + S(20), S(y_cod) + S(60) / 2 - f_cod.size * .52), t_cod, font=f_cod, fill="white")
    ty = S(y_nome)
    sobre_branco = False
    if card_rect:
        bx_c = (S(cx) if story else S(65) + max(d.textlength(l, font=f_nome) for l in linhas) / 2)
        by_c = ty + len(linhas) * f_nome.size * .9 / 2
        sobre_branco = S(card_rect[0]) <= bx_c <= S(card_rect[2]) and S(card_rect[1]) <= by_c <= S(card_rect[3])
    cor_nome = DARK if sobre_branco else "white"
    for l in linhas:
        x = S(cx) - d.textlength(l, font=f_nome) / 2 if story else S(65)
        if not sobre_branco:
            d.text((x + S(3), ty + S(3)), l, font=f_nome, fill=(0, 0, 0, 150))
        d.text((x, ty), l, font=f_nome, fill=cor_nome, stroke_width=max(1, int(f_nome.size * .01)), stroke_fill=cor_nome)
        ty += f_nome.size * .9

    # selo de estoque
    if qtd:
        teto = int(CFG["loja"].get("estoque_maximo", 50000))
        t_est = (f"+{teto:,} em estoque" if int(qtd) > teto else f"{int(qtd):,} em estoque").replace(",", ".")
        f_est = fonte(S(24))
        we = d.textlength(t_est, font=f_est) + S(40)
        if story and texto_y0 and card_rect:
            ex0, ey0 = S(card_rect[0]) + S(24), S(card_rect[1]) + S(24)
        elif story:
            ex0, ey0 = S(540) - we / 2, S(1525 + dy)
        else:
            ex0, ey0 = S(1151) - we, S(1093 + dy)
            if card_rect and ey0 < S(card_rect[3]) + S(8):      # nao encosta na borda do quadro
                ey0 = S(card_rect[3]) + S(8)
        d.rounded_rectangle((ex0, ey0, ex0 + we, ey0 + S(34)), S(17), fill="#C9F5D3")
        d.text((ex0 + S(20), ey0 + S(17) - f_est.size * .6), t_est, font=f_est, fill="#1E7A3A")

    # bloco de preco
    if story:
        bx0, by0, bx1, by1 = 155, 1618 + dy, 925, 1825 + dy
        fx0, fx1, fy = 180, 892, 1858 + dy
    else:
        bx0, by0, bx1, by1 = 712, 1167 + dy, 1393, 1350 + dy
        fx0, fx1, fy = 732, 1363, 1378 + dy
    d.rounded_rectangle((S(bx0), S(by0), S(bx1), S(by1)), S(28), fill=ORANGE)
    # "A PARTIR DE" na vertical
    f_ap = fonte_modelo("BebasNeue-Regular.ttf", S(36))
    tmp = Image.new("RGBA", (int(d.textlength("A PARTIR DE", font=f_ap)) + 4, f_ap.size + 8), (0, 0, 0, 0))
    ImageDraw.Draw(tmp).text((2, 0), "A PARTIR DE", font=f_ap, fill="white")
    tmp = tmp.rotate(90, expand=True)
    fundo.alpha_composite(tmp, (S(bx0) + S(22), (S(by0) + S(by1)) // 2 - tmp.height // 2))
    # valor
    txt = f"R$ {preco}"
    larg_max = S(bx1 - bx0) - S(150) - S(70)
    f_pr = fonte_modelo("BebasNeue-Regular.ttf", S(230))
    while d.textlength(txt, font=f_pr) + f_pr.size * .04 > larg_max and f_pr.size > 20:
        f_pr = fonte_modelo("BebasNeue-Regular.ttf", f_pr.size - 4)
    # centraliza o valor na altura do bloco usando a altura real dos algarismos (a virgula fica de fora da conta)
    _, t8, _, b8 = d.textbbox((0, 0), "8", font=f_pr)
    cy = (S(by0) + S(by1)) / 2
    d.text((S(bx0) + S(105), cy - (t8 + b8) / 2), txt, font=f_pr, fill="white", stroke_width=max(1, int(f_pr.size * .018)), stroke_fill="white")
    f_un = fonte_modelo("BebasNeue-Regular.ttf", S(34))
    d.text((S(bx1) - S(24) - d.textlength("/UN", font=f_un), S(by1) - S(24) - f_un.size), "/UN", font=f_un, fill="white")

    # rodape: gravacao (esq) e minimo (dir)
    f_rod = fonte_modelo("BebasNeue-Regular.ttf", S(34))
    t_min = f"MÍNIMO {int(minimo)} /UN" if minimo else ""
    larg_grav = S(fx1 - fx0) - (d.textlength(t_min, font=f_rod) + S(30) if t_min else 0)
    lins_r = quebrar(d, gravacao or "GRAVAÇÃO DA SUA MARCA", f_rod, larg_grav)[:2]     # texto longo: segue em duas linhas
    ty_r = S(fy) - (S(12) if len(lins_r) > 1 else 0)
    for lin in lins_r:
        d.text((S(fx0), ty_r), lin, font=f_rod, fill=DARK)
        ty_r += f_rod.size * .95
    if t_min:
        d.text((S(fx1) - d.textlength(t_min, font=f_rod), S(fy) - (S(12) if len(lins_r) > 1 else 0)), t_min, font=f_rod, fill=DARK)
    return fundo.convert("RGB")


def produto_demo():
    img = Image.new("RGBA", (600, 600), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    d.polygon([(190, 140), (410, 140), (385, 480), (215, 480)], fill=(190, 225, 235, 190), outline=(255, 255, 255, 255))
    d.rectangle((215, 440, 385, 480), fill=(120, 170, 190, 230))
    return img, {"name": "Copo Americano para Drink 315ml", "price": "24.96",
                 "categories": [{"name": "COPOS"}], "id": 0, "sku": "CO9100", "minimo": 15, "qtd_estoque": 2917, "tecnica": "Laser"}


# ---------- principal ----------
def preco_br(v):
    try:
        f = float(v)
    except (TypeError, ValueError):
        return "consulte"
    return f"{f:.2f}".replace(".", ",") if f > 0 else "consulte"


def processar(produto, rgba, sem_ia, nomes, cenario=None, estilo="cartao", mostrar_qtd=False, gravacao=None, minimo_forcado=None):
    if estilo in ("padrao", "cartao"):
        nome = unescape(produto["name"])
        info = {k: produto.get(k) for k in ("minimo", "qtd_estoque", "tecnica") if produto.get(k)}
        if minimo_forcado:
            info["minimo"] = minimo_forcado
        if not all(info.get(k) for k in ("minimo", "qtd_estoque")):
            pag = ler_pagina(produto.get("permalink"))
            info.setdefault("minimo", pag.get("minimo")); info.setdefault("qtd_estoque", pag.get("qtd")); info.setdefault("tecnica", pag.get("tecnica"))
        pasta = AQUI / "saida" / f"{produto.get('id', 0)}-{re.sub(r'[^\w]+', '-', nome.lower()).strip('-')[:60]}"
        pasta.mkdir(parents=True, exist_ok=True)
        for fmt in nomes:
            tam = tamanho_do_modelo(fmt)
            montar_padrao(rgba, tam, nome, preco_br(produto.get("price")), produto.get("sku") or None,
                          info.get("qtd_estoque"), info.get("minimo") or CFG["loja"].get("minimo_padrao") or None,
                          gravacao or texto_gravacao(info.get("tecnica")), estilo == "cartao", fmt).save(pasta / f"{fmt}.jpg", quality=93)
        print(f"  ok -> {pasta}")
        return
    prompt = prompt_cenario(produto, cenario)
    base = None
    if not sem_ia:
        try:
            base = cenario_comfyui(prompt)
        except Exception as e:
            print(f"  ! ComfyUI indisponivel ({e}); usando degrade", file=sys.stderr)
    nome = unescape(produto["name"])
    minimo = produto.get("minimo") or (pedido_minimo(produto.get("permalink")) if estilo == "comercial" else None)
    slug = re.sub(r"[^\w]+", "-", nome.lower()).strip("-")[:60]
    pasta = AQUI / "saida" / f"{produto.get('id', 0)}-{slug}"
    pasta.mkdir(parents=True, exist_ok=True)
    for fmt in nomes:
        tam = tuple(CFG["formatos"][fmt])
        fundo = base if base is not None else cenario_degrade(tam, produto_escuro(rgba))
        montar(rgba, fundo, tam, nome, preco_br(produto.get("price")), base is not None, estilo, minimo,
               produto.get("estoque") or produto.get("stock_status") or "instock",
               produto.get("qtd") if produto.get("qtd") is not None else produto.get("stock_quantity"),
               mostrar_qtd).save(pasta / f"{fmt}.jpg", quality=92)
    if base is not None:
        base.save(pasta / "cenario.png")
    print(f"  ok -> {pasta}")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--limite", type=int, default=4)
    ap.add_argument("--produto-id", type=int)
    ap.add_argument("--ids", nargs="+", help="varios produtos de uma vez, com virgula ou espaco (ex.: --ids 93014,80131,1380)")
    ap.add_argument("--busca", help="busca por nome (ex.: --busca caneca)")
    ap.add_argument("--sku", help="produto pelo codigo/SKU")
    ap.add_argument("--ordem", choices=["date", "popularity", "price", "rating"], default="date", help="ordem dos produtos (padrao: mais novos)")
    ap.add_argument("--categoria", help="slug ou id da categoria (ex.: chaveiros-brindes)")
    ap.add_argument("--foto", type=int, default=1, help="numero da foto do produto (1 = primeira, 2 = segunda...)")
    ap.add_argument("--cenario", help="descricao do cenario em ingles (ex.: \"rustic wooden table, candle light\")")
    ap.add_argument("--estilo", choices=["cartao", "padrao", "comercial", "simples"], default="cartao", help="layout da arte")
    ap.add_argument("--mostrar-qtd", action="store_true", help="mostra a quantidade em estoque no selo (so se a loja informar)")
    ap.add_argument("--so-estoque", action="store_true", help="pula produtos sem estoque")
    ap.add_argument("--gravacao", help="texto do rodape (ex.: \"GRAVAÇÃO DA SUA MARCA A LASER\")")
    ap.add_argument("--minimo", type=int, help="forca o pedido minimo no rodape (ex.: --minimo 250)")
    ap.add_argument("--pausa", type=float, default=0.7, help="segundos de espera entre produtos (evita bloqueio do firewall do site)")
    ap.add_argument("--continuar", action="store_true", help="pula os produtos que ja tem arte na pasta saida (para retomar um lote grande)")
    ap.add_argument("--zoom", type=float, default=1.0, help="aumenta a foto dentro do quadro branco, como o PowerClip (ex.: --zoom 1.3; o que passar da borda e cortado)")
    ap.add_argument("--tema", help="tema/campanha: nome da pasta em temas/ (ex.: --tema novembro-azul)")
    ap.add_argument("--sem-ia", action="store_true")
    ap.add_argument("--demo", action="store_true")
    ap.add_argument("--formatos", nargs="+", default=["feed", "story"])
    a = ap.parse_args()
    global FOTO_ZOOM
    FOTO_ZOOM = a.zoom
    if a.tema:
        ativar_tema(a.tema)
    print('gerador.py versao 12.0 (1 "A PARTIR DE" so, foto sem margem branca, --zoom) - arquivo: ' + str(Path(__file__).resolve()))
    if a.ids:   # aceita 93014,80131,1380 ou 93014 80131 1380
        a.ids = [int(x) for tok in a.ids for x in re.split(r"[,;\s]+", tok) if x.strip().isdigit()]

    if a.demo:
        rgba, prod = produto_demo()
        processar(prod, rgba, True, a.formatos, None, a.estilo, a.mostrar_qtd, a.gravacao); return
    produtos = buscar_produtos(a.limite, a.produto_id, a.categoria, a.ids, a.busca, a.sku, a.ordem)
    if a.ids:   # avisa os IDs que a loja nao devolveu (nao e produto, esta oculto ou e rascunho)
        achados = {int(p.get("id", 0)) for p in produtos}
        for i in a.ids:
            if i not in achados:
                print(f"! ID {i}: nao encontrado na loja (confira se e o ID do produto e se esta publicado)")
    feitos, pulados = 0, 0
    for p in produtos:
        print(p["name"])
        if a.continuar and list((AQUI / "saida").glob(f"{p.get('id', 0)}-*/feed.jpg")):
            print("  ja existe, pulando"); pulados += 1; continue
        if a.so_estoque and (p.get("estoque") or p.get("stock_status")) == "outofstock":
            print("  sem estoque, pulando"); pulados += 1; continue
        if not p.get("images"):
            print("  sem foto, pulando"); pulados += 1; continue
        i = min(max(a.foto, 1), len(p["images"])) - 1
        print(f"  usando foto {i + 1} de {len(p['images'])}")
        try:
            img = baixar_imagem(p["images"][i]["src"])
            processar(p, img if a.estilo == "cartao" else recortar(img), a.sem_ia, a.formatos, a.cenario, a.estilo, a.mostrar_qtd, a.gravacao, a.minimo)
            feitos += 1
            time.sleep(max(0.0, a.pausa))
        except Exception as e:
            print(f"  ! erro neste produto: {e}"); pulados += 1
    print(f"\nPronto: {feitos} produto(s) gerado(s), {pulados} pulado(s). Artes na pasta saida.")


if __name__ == "__main__":
    main()
