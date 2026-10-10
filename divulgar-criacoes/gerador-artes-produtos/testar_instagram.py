"""Testa o instagram.json sem postar nada e sem mostrar o token. Uso: python testar_instagram.py"""
import json, sys
from pathlib import Path
import requests

f = Path(__file__).resolve().parent / "instagram.json"
if not f.exists():
    sys.exit("Nao achei o instagram.json nesta pasta.")
try:
    c = json.loads(f.read_text(encoding="utf-8"))
except Exception as e:
    sys.exit(f"O instagram.json tem erro de formato (aspas ou virgulas): {e}")

tok = str(c.get("access_token", ""))
print("\n== Conferencia do token (o token nao e mostrado) ==")
print(f"Tamanho: {len(tok)} caracteres (um token valido costuma ter mais de 150)")
print(f"Comeca com: {tok[:4]!r}   termina com: {tok[-2:]!r}")
if tok != tok.strip():
    print("PROBLEMA: ha espaco ou quebra de linha no comeco/fim do token.")
if " " in tok.strip() or "\n" in tok or "\r" in tok:
    print("PROBLEMA: ha espaco ou quebra de linha DENTRO do token (copiou errado).")
if tok.startswith("COLE_"):
    print("PROBLEMA: ainda esta o texto de exemplo no access_token.")
print(f"ig_user_id: {c.get('ig_user_id')!r}  |  api_base: {c.get('api_base')!r}")

tok = "".join(tok.split())
print("\n== Teste na Meta ==")
for nome, base in (("graph.instagram.com", "https://graph.instagram.com/v21.0"), ("graph.facebook.com", "https://graph.facebook.com/v21.0")):
    try:
        r = requests.get(f"{base}/me", params={"fields": "id,username", "access_token": tok}, timeout=30).json()
    except Exception as e:
        print(f"{nome}: falha de conexao ({e})"); continue
    if "error" in r:
        print(f"{nome}: ERRO - {r['error'].get('message')}")
    else:
        print(f"{nome}: OK -> conta {r.get('username')} (id {r.get('id')})")
print("\nSe um dos dois deu OK, use esse endereco no campo api_base. Se os dois deram ERRO, gere um token novo.")
