# Como criar um tema (campanha)

1. Crie uma pasta nova aqui dentro, com o nome da campanha, sem espaços e sem acentos. Ex.: `novembro-azul`
2. Coloque nela dois arquivos de fundo, com estes nomes exatos:
   - `fundo_feed.png`  (1080 x 1220, é só copiar o de `modelo/` e editar)
   - `fundo_story.png` (1080 x 1920)
   Nos fundos fica: logo, slogan/selo da campanha e o quadro branco da foto.
   NÃO coloque nome, preço, código, estoque nem "A PARTIR DE": o gerador escreve isso.
3. (Opcional) Crie `tema.json` com a cor do bloco de preço e da etiqueta de código:
   {"cor_destaque": "#0057B8"}
   Sem esse arquivo, a cor continua laranja.
4. Gere usando o nome da pasta:
   python gerador.py --tema novembro-azul --busca caneca --limite 20

Para criar outra campanha, copie uma pasta de tema, troque os dois fundos e a cor.
