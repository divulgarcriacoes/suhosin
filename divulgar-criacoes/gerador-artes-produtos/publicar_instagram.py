"""Publica no Instagram as artes geradas (feed e/ou story), usando a API oficial da Meta.

Como funciona, para cada produto da pasta do lote:
  1) envia a imagem para a biblioteca de midia do seu site WordPress (o Instagram precisa de um endereco publico da imagem);
  2) cria o post no Instagram com a legenda (legenda.txt) e publica.
Por seguranca, SEM a opcao --publicar ele so SIMULA (mostra o que faria e nao posta nada).

Uso:
  python publicar_instagram.py                      -> simula o lote mais recente (feed)
  python publicar_instagram.py --publicar           -> publica de verdade o feed do lote mais recente
  python publicar_instagram.py --formato story --publicar
  python publicar_instagram.py --lote saida\\2026-10-10_16-30 --limite 3 --publicar
Agendamento (combina com a publicacao automatica):
  python publicar_instagram.py --planejar --horarios 09:00,12:30,18:00 --inicio 2026-11-03 --dias-uteis
      -> cria o agenda.json do lote, distribuindo os posts pelos dias e horarios (nao posta nada)
  python publicar_instagram.py --publicar --aguardar
      -> fica aberto e publica cada post na hora marcada (o computador precisa ficar ligado)
  python publicar_instagram.py --publicar
      -> publica so o que ja esta na hora (use no Agendador de Tarefas do Windows a cada 30 minutos)
Configuracao: copie instagram.exemplo.json para instagram.json e preencha (veja o PASSO-A-PASSO-INSTAGRAM.md).
"""
import argparse, datetime as dt, json, random, sys, time
from pathlib import Path

import requests

AQUI = Path(__file__).resolve().parent
API = "https://graph.facebook.com/v21.0"    # se o token vier do "login do Instagram", use "api_base": "https://graph.instagram.com/v21.0" no instagram.json


def carregar_config():
    f = AQUI / "instagram.json"
    if not f.exists():
        sys.exit("Falta o arquivo instagram.json. Copie instagram.exemplo.json, preencha e salve como instagram.json.")
    c = json.loads(f.read_text(encoding="utf-8"))
    faltam = [k for k in ("ig_user_id", "access_token", "wp_url", "wp_usuario", "wp_senha_app") if not str(c.get(k, "")).strip()]
    if faltam:
        sys.exit("Preencha no instagram.json: " + ", ".join(faltam))
    return c


def lote_mais_recente():
    base = AQUI / "saida"
    pastas = [p for p in base.iterdir() if p.is_dir()] if base.exists() else []
    if not pastas:
        sys.exit("Nao ha nenhum lote na pasta saida. Gere as artes primeiro.")
    return sorted(pastas, key=lambda p: p.stat().st_mtime)[-1]


def enviar_para_o_site(cfg, arquivo, lote):
    nome = f"{lote.name}-{arquivo.parent.name}-{arquivo.name}".replace(" ", "-")
    r = requests.post(cfg["wp_url"].rstrip("/") + "/wp-json/wp/v2/media", auth=(cfg["wp_usuario"], cfg["wp_senha_app"]),
                      headers={"Content-Disposition": f'attachment; filename="{nome}"', "Content-Type": "image/jpeg"},
                      data=arquivo.read_bytes(), timeout=120)
    if r.status_code >= 300:
        raise RuntimeError(f"Falha ao enviar a imagem para o site ({r.status_code}): {r.text[:200]}")
    return r.json()["source_url"]


def publicar(cfg, url_imagem, legenda, story):
    API = cfg.get("api_base") or globals()["API"]
    dados = {"image_url": url_imagem, "access_token": cfg["access_token"]}
    if story:
        dados["media_type"] = "STORIES"
    else:
        dados["caption"] = legenda
    r = requests.post(f"{API}/{cfg['ig_user_id']}/media", data=dados, timeout=60)
    j = r.json()
    if "id" not in j:
        raise RuntimeError(f"Instagram recusou a imagem: {j}")
    cont = j["id"]
    for _ in range(20):                                   # espera o Instagram processar a imagem
        st = requests.get(f"{API}/{cont}", params={"fields": "status_code", "access_token": cfg["access_token"]}, timeout=30).json()
        if st.get("status_code") == "FINISHED":
            break
        if st.get("status_code") in ("ERROR", "EXPIRED"):
            raise RuntimeError(f"Instagram nao processou a imagem: {st}")
        time.sleep(3)
    r = requests.post(f"{API}/{cfg['ig_user_id']}/media_publish", data={"creation_id": cont, "access_token": cfg["access_token"]}, timeout=60)
    j = r.json()
    if "id" not in j:
        raise RuntimeError(f"Nao foi possivel publicar: {j}")
    return j["id"]


def planejar(lote, produtos, formatos, horarios, inicio, so_uteis):
    """Distribui os posts: um produto por horario; o story sai 30 minutos depois do feed."""
    dia = dt.date.fromisoformat(inicio) if inicio else dt.date.today() + dt.timedelta(days=1)
    agenda, i = {}, 0
    for p in produtos:
        while so_uteis and dia.weekday() >= 5:
            dia += dt.timedelta(days=1)
        h, m = map(int, horarios[i % len(horarios)].split(":"))
        base = dt.datetime.combine(dia, dt.time(h, m))
        for fmt in formatos:
            agenda[f"{p.name}/{fmt}"] = (base + dt.timedelta(minutes=30 if fmt == "story" else 0)).isoformat(timespec="minutes")
        i += 1
        if i % len(horarios) == 0:
            dia += dt.timedelta(days=1)
    (lote / "agenda.json").write_text(json.dumps(agenda, indent=1, ensure_ascii=False), encoding="utf-8")
    ultimo = max(agenda.values()) if agenda else "-"
    print(f"Agenda criada: {len(agenda)} post(s), de {min(agenda.values()) if agenda else '-'} ate {ultimo}.\nArquivo: {lote / 'agenda.json'} (voce pode abrir e ajustar as datas na mao).")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--lote", help="pasta do lote dentro de saida (padrao: o mais recente)")
    ap.add_argument("--formato", choices=["feed", "story", "ambos"], default="feed")
    ap.add_argument("--limite", type=int, default=0, help="publica no maximo N produtos (0 = todos)")
    ap.add_argument("--intervalo", type=int, default=90, help="segundos de espera entre posts (padrao 90)")
    ap.add_argument("--publicar", action="store_true", help="publica de verdade (sem isso, so simula)")
    ap.add_argument("--planejar", action="store_true", help="cria o agenda.json do lote (nao posta nada)")
    ap.add_argument("--horarios", default="09:00,12:30,18:00", help="horarios dos posts, separados por virgula")
    ap.add_argument("--inicio", help="primeiro dia da agenda, AAAA-MM-DD (padrao: amanha)")
    ap.add_argument("--dias-uteis", action="store_true", help="na agenda, pula sabado e domingo")
    ap.add_argument("--aguardar", action="store_true", help="com --publicar e agenda: fica aberto e posta cada item na hora marcada")
    a = ap.parse_args()

    lote = Path(a.lote) if a.lote else lote_mais_recente()
    if not lote.is_absolute():
        lote = (AQUI / lote) if (AQUI / lote).exists() else lote.resolve()
    produtos = sorted(p for p in lote.iterdir() if p.is_dir() and (p / "feed.jpg").exists())
    if a.limite:
        produtos = produtos[:a.limite]
    formatos = ["feed", "story"] if a.formato == "ambos" else [a.formato]
    if a.planejar:
        planejar(lote, produtos, formatos, [h.strip() for h in a.horarios.split(",") if h.strip()], a.inicio, a.dias_uteis)
        return
    cfg = carregar_config() if a.publicar else {}
    agf = lote / "agenda.json"
    agenda = json.loads(agf.read_text(encoding="utf-8")) if agf.exists() else {}
    ja = lote / "publicados.json"
    feitos = json.loads(ja.read_text(encoding="utf-8")) if ja.exists() else {}
    print(f"Lote: {lote}\n{len(produtos)} produto(s) | formatos: {', '.join(formatos)} | modo: {'PUBLICANDO' if a.publicar else 'SIMULACAO (nada sera postado)'}\n")

    n = 0
    for p in produtos:
        leg = (p / "legenda.txt").read_text(encoding="utf-8") if (p / "legenda.txt").exists() else p.name
        for fmt in formatos:
            chave = f"{p.name}/{fmt}"
            if chave in feitos:
                print(f"- {chave}: ja publicado, pulando")
                continue
            arq = p / f"{fmt}.jpg"
            if not arq.exists():
                continue
            if agenda and chave in agenda:
                quando = dt.datetime.fromisoformat(agenda[chave])
                if a.publicar and quando > dt.datetime.now():
                    if not a.aguardar:
                        print(f"- {chave}: agendado para {quando:%d/%m %H:%M}, ainda nao esta na hora")
                        continue
                    print(f"- {chave}: aguardando ate {quando:%d/%m %H:%M} ...")
                    while dt.datetime.now() < quando:
                        time.sleep(30)
            if not a.publicar:
                print(f"- {chave}: publicaria {arq.name} com a legenda:\n    " + leg.replace("\n", "\n    ") + "\n")
                continue
            try:
                url = enviar_para_o_site(cfg, arq, lote)
                pid = publicar(cfg, url, leg, fmt == "story")
                feitos[chave] = pid
                ja.write_text(json.dumps(feitos, indent=1), encoding="utf-8")
                n += 1
                print(f"- {chave}: publicado (id {pid})")
            except Exception as e:
                print(f"- {chave}: ERRO - {e}", file=sys.stderr)
                continue
            if not (agenda and chave in agenda):
                time.sleep(a.intervalo + random.randint(0, 20))
    print(f"\nPronto: {n} publicacao(oes) feita(s)." if a.publicar else "\nSimulacao concluida. Para postar de verdade, rode de novo com --publicar.")


if __name__ == "__main__":
    main()
