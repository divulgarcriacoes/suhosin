"""Cria um tema (campanha) novo a partir dos fundos padrao: troca o laranja pela cor da campanha e, se quiser, coloca um selo com o nome.

Exemplos:
  python criar_tema.py --nome novembro-azul --cor "#1E5BD8" --selo "NOVEMBRO AZUL"
  python criar_tema.py --nome outubro-rosa --cor "#E5338C" --selo "OUTUBRO ROSA"
  python criar_tema.py --nome natal --cor "#C8102E" --selo "NATAL DA SUA MARCA"
Depois, use no gerador:  python gerador.py --tema novembro-azul ...
Se quiser refinar a mao, abra os fundos criados em temas/<nome>/ no seu editor e salve com o mesmo nome.
"""
import argparse, colorsys, json, re, sys, unicodedata
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFont

AQUI = Path(__file__).resolve().parent


def hex_para_hue(hx):
    hx = hx.lstrip("#")
    r, g, b = (int(hx[i:i + 2], 16) / 255 for i in (0, 2, 4))
    h, s, v = colorsys.rgb_to_hsv(r, g, b)
    return h * 255, s


def trocar_cor(img, hue_alvo, sat_alvo):
    """Troca o laranja/amarelo da imagem pela cor da campanha; cinza, branco e preto ficam como estao."""
    hsv = np.array(img.convert("RGB").convert("HSV"), dtype=np.float32)
    h, s, v = hsv[..., 0], hsv[..., 1], hsv[..., 2]
    mascara = (h >= 5) & (h <= 44) & (s > 85)               # tons de laranja ate amarelo vivos
    novo_h = (hue_alvo + (h - 19) * 0.55) % 256
    hsv[..., 0] = np.where(mascara, novo_h, h)
    ganho = min(1.0, max(0.55, sat_alvo / 0.85))             # cor da campanha mais clara/suave -> menos saturacao
    hsv[..., 1] = np.where(mascara, np.clip(s * ganho, 0, 255), s)
    out = Image.fromarray(hsv.astype(np.uint8), "HSV").convert("RGB")
    return out


def selo(img, texto, cor, formato):
    """Faixa com o nome da campanha, entre o topo (logo e slogan) e o quadro branco."""
    W, H = img.size
    d = ImageDraw.Draw(img)
    fonte = AQUI / "modelo" / "BebasNeue-Regular.ttf"
    tam = int(H * (0.040 if formato == "feed" else 0.026))
    f = ImageFont.truetype(str(fonte), tam) if fonte.exists() else ImageFont.load_default()
    texto = texto.upper()
    tw = d.textlength(texto, font=f)
    padx, pady = int(tam * .9), int(tam * .28)
    w, h = int(tw + 2 * padx), int(tam + 2 * pady)
    cx = W // 2
    cy = int(H * (0.250 if formato == "feed" else 0.212))
    x0, y0, x1, y1 = cx - w // 2, cy - h // 2, cx + w // 2, cy + h // 2
    d.rounded_rectangle((x0 + 4, y0 + 5, x1 + 4, y1 + 5), h // 2, fill=(0, 0, 0, 90))
    d.rounded_rectangle((x0, y0, x1, y1), h // 2, fill=cor, outline="white", width=max(2, tam // 14))
    d.text((cx - tw / 2, cy - tam * .56), texto, font=f, fill="white")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--nome", required=True, help="nome da pasta do tema (ex.: novembro-azul)")
    ap.add_argument("--cor", required=True, help="cor da campanha em hexadecimal (ex.: #1E5BD8)")
    ap.add_argument("--selo", help="texto do selo da campanha (ex.: \"NOVEMBRO AZUL\"); sem isso, nao poe selo")
    a = ap.parse_args()
    nome = re.sub(r"[^a-z0-9]+", "-", "".join(c for c in unicodedata.normalize("NFD", a.nome.lower()) if unicodedata.category(c) != "Mn")).strip("-")
    if not re.fullmatch(r"#?[0-9a-fA-F]{6}", a.cor):
        sys.exit("A cor precisa ser hexadecimal com 6 digitos, por exemplo #1E5BD8")
    cor = "#" + a.cor.lstrip("#").upper()
    hue, sat = hex_para_hue(cor)
    pasta = AQUI / "temas" / nome
    pasta.mkdir(parents=True, exist_ok=True)
    for fmt in ("feed", "story"):
        base = AQUI / "modelo" / f"fundo_{fmt}.png"
        if not base.exists():
            sys.exit(f"Nao achei {base}")
        im = trocar_cor(Image.open(base), hue, sat)
        if a.selo:
            selo(im, a.selo, cor, fmt)
        im.save(pasta / f"fundo_{fmt}.png")
    (pasta / "tema.json").write_text(json.dumps({"cor_destaque": cor}), encoding="utf-8")
    print(f"Tema criado em: {pasta}\nUse com:  python gerador.py --tema {nome} ...")


if __name__ == "__main__":
    main()
