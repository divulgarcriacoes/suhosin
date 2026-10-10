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
