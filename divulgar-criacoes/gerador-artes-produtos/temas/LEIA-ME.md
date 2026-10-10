# Temas (campanhas)

Um tema é uma pasta aqui dentro com os fundos da campanha. Já vêm prontos: `novembro-azul` e `outubro-rosa`.

## Criar um tema novo (automático)
No cmd, na pasta do gerador:
    python criar_tema.py --nome natal --cor "#C8102E" --selo "NATAL DA SUA MARCA"
- `--nome`: nome da pasta (sem espaço e sem acento)
- `--cor`: cor da campanha em hexadecimal (o laranja do padrão é trocado por ela)
- `--selo`: texto do selo que aparece entre o logo e o quadro branco (opcional)
Também dá para criar pelo painel do site (Gerador de artes, card 5), que monta esse comando para você.

## Usar
    python gerador.py --tema natal --ids 1,2,3 --pasta lote-natal
No painel, escreva o nome do tema no campo "Tema" do card 2.

## Refinar na mão (opcional)
Abra `temas\NOME\fundo_feed.png` e `fundo_story.png` no seu editor de imagem, ajuste e salve com o mesmo nome e tamanho.
O arquivo `tema.json` guarda a cor do bloco de preço e da etiqueta de código: {"cor_destaque": "#1E5BD8"}

## Fazer o fundo do zero
Crie a pasta com `fundo_feed.png` (1080x1220) e `fundo_story.png` (1080x1920), com logo, slogan e o quadro branco, sem preço nem nome.

## Tema com layout próprio (subir pelo painel do site)
Para campanhas com outro layout (quadro em outro lugar, preço em outro lugar, modelo/foto de fundo...):
1. No painel (Gerador de artes, card 6), escreva o nome do tema, suba o **fundo FEED** e o **fundo STORY** (arte limpa, sem preço, nome, etiqueta, selo de estoque).
2. Suba também, se quiser, as imagens-**modelo** com as peças no lugar (quadro branco, etiqueta laranja do código, bloco laranja do preço, selo verde de estoque). O painel mede as posições sozinho; dá para ajustar o texto das posições antes de enviar.
3. Clique em "Enviar tema para o site". O tema fica guardado no site.
4. No gerador, é só usar `--tema nome`. Se a pasta do tema não existir no seu computador, o gerador baixa do site sozinho. Se você mudar o tema no painel, use `--atualizar-tema` para baixar de novo.

As posições ficam em `tema.json` > "layout", em frações da largura (x) e da altura (y). Campos: quadro [x0,y0,x1,y1], tag [x,y], nome [x0,y0,x1,y1], estoque [x,y,"l"|"r"|"c"], preco [x0,y0,x1,y1], rodape "dentro"|"fora", rodape_y, cor_tag, cor_rodape, cor_nome.
