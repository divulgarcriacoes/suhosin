# Gerador de artes de produtos (WooCommerce + IA)

Pega produtos da loja, recorta o fundo, cria um cenário com IA (ComfyUI) e monta artes de feed (1080x1080) e story (1080x1920) com nome e preço.

## Instalação (no seu PC)
1. Python 3.10+ e `pip install -r requirements.txt`
2. Instale o ComfyUI e coloque um modelo SDXL em `models/checkpoints` (nome em `config.json`). Abra o ComfyUI (porta 8188).
3. (Opcional) Chaves somente-leitura do WooCommerce: variáveis `WC_KEY` e `WC_SECRET`.
4. (Opcional) coloque `logo.png` nesta pasta.

## Uso
    python gerador.py --demo                 # teste sem internet nem IA
    python gerador.py --limite 4 --sem-ia    # produtos reais, fundo em degradê
    python gerador.py --limite 4             # produtos reais + cenário por IA
    python gerador.py --produto-id 93008

As artes saem em `saida/<id>-<produto>/feed.jpg` e `story.jpg`.

## Observações
- O produto original entra intacto sobre o cenário; a IA só cria o fundo (preserva a gravação a laser).
- Vidro transparente pode ter borda imperfeita no recorte: revise antes de publicar.
- Cenários por categoria ficam em `config.json` (`cenarios_por_categoria`).
- Status: protótipo. Só a montagem foi testada. Recorte (rembg) e ComfyUI ainda não foram testados com produtos reais.

## Arte padrao da Divulgar (v9)
- O modelo (fundos feed/story, slogan "TUDO PARA SUA MARCA" e fontes) fica na pasta `modelo/`.
- Coloque o logo BRANCO horizontal em `modelo/logo.png` (PNG com fundo transparente).
- `python gerador.py --limite 3 --so-estoque` gera feed e story no padrao (nao usa IA).
- Rodape: `--gravacao "GRAVAÇÃO DA SUA MARCA A LASER"` forca o texto. Sem isso, o gerador tenta ler a tecnica na pagina do produto.
- Para cenarios de IA como antes: `--estilo comercial`.

## Estilo padrao do dia a dia (v10)
- O estilo `cartao` (foto do produto num quadro branco, sem recorte) agora e o padrao: mais rapido e funciona com vidro e acrilico.
- Exemplos:
    python gerador.py --limite 5 --so-estoque
    python gerador.py --ids 93014 80131 1380 --estilo cartao
    python gerador.py --categoria chaveiros-brindes --limite 5 --so-estoque
- O estilo com produto recortado continua em `--estilo padrao`.

## Logo fixo no modelo
- Se o logo ja estiver desenhado nos fundos (modelo/fundo_feed.png e fundo_story.png), coloque `"logo_no_modelo": true` em config.json (dentro de "loja"): o gerador nao desenha o logo de novo.
- Sem o arquivo modelo/logo.png o gerador tambem nao desenha logo nenhum.
