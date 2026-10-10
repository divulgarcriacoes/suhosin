# Resumo do trabalho – Divulgar Criações

## 1. Painel Comercial (WordPress)
Snippets no EMCP Tools → Sandbox (todos precisam estar ativos):
- 94396: tema claro do admin (verde #0B8A6A no giftbrand, laranja #F5821F nos demais) e menus da barra de admin em branco.
- 94397: tabelas, botões e etiquetas das telas `dc-*`.
- 94398: "Status dos pedidos" dentro do Painel Comercial.
- 94399: Visão geral lista todos os itens do menu.
- 94400: campo de busca na lista de produtos do parceiro (carrega na grade).
- 94401: rolagem infinita no lugar da paginação (lojas dos parceiros).
- 94402: alinhamento da lupa.
- 94403: recálculo de preços dos chaveiros (pode ser desativado depois de rodar).
- 94404: tela "Gerador de artes" (escolhe produtos e gera o comando; copiar ou baixar .bat).

## 2. Regra de preço dos chaveiros
- Categoria 153 e filhas: se o valor unitário de venda ficar abaixo de R$ 2,10, soma R$ 1,20.
- Vale para Divulgar e parceiros. O parceiro parte do valor da Divulgar + a comissão dele (a tabela de revenda é ignorada nos chaveiros).
- Arquivo: `wp-content/plugins/dc-price-engine/dc-price-omnitek.php` (v2). O original está guardado como `out/orig.php` na pasta de trabalho, para voltar atrás.

## 3. Gerador de artes (roda no computador)
- Pasta: `gerador-artes-produtos` (zip: `gerador-artes-produtos-v11.zip`, versão 11.7).
- Gera `feed.jpg` (1080×1220) e `story.jpg` (1080×1920) por produto na pasta `saida`.
- Fundo, logo e slogan vêm de `modelo/fundo_feed.png` e `modelo/fundo_story.png`. O gerador escreve código, nome (até 3 linhas), estoque, preço, gravação (até 2 linhas) e mínimo.
- Comandos principais:
  - `python gerador.py --limite 5 --so-estoque`
  - `python gerador.py --ids 80131,1380,93014`
  - `python gerador.py --busca caneca --limite 20 --so-estoque`
  - opções: `--zoom 1.3`, `--foto 2`, `--minimo 250`, `--gravacao "..."`, `--continuar`, `--pausa 1.5`
- Guia em PDF: `Guia-Gerador-de-Artes.pdf`.

## 4. Pendências / ideias
- Campo de zoom na tela do painel "Gerador de artes".
- Legenda (texto de postagem) por produto.
- Desativar o snippet 94403 depois que os preços dos chaveiros estiverem estáveis.
