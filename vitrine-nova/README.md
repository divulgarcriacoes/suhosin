# Vitrine nova — Lojas das franquias (Ouro Moderno / GiftBrand)

Cópia dos arquivos que estão no ar em `divulgarcriacoes.com.br` / `ouromoderno.giftbrand.com.br`
(mesmo WordPress), tirada em 25/11/2026. Serve para continuar o trabalho de outro computador.

> As chaves (API da Spot, chave da macro do Corel) **não** estão aqui. Elas ficam no painel do site.

## Onde cada arquivo fica no servidor

Tudo dentro de `wp-content/` do WordPress, no mesmo caminho desta pasta:

| Arquivo | O que faz |
|---|---|
| `mu-plugins/dc-ouro-logo-mockup.php` | Coloca o logo da escola nos produtos (molde com perspectiva, 6 versões do logo, claro/escuro por foto, mais de uma área), foto de um item só, troca de foto ao clicar na cor, painel "Versões do logo" |
| `mu-plugins/dc-corel-molde.php` | Recebe o molde enviado pela macro do Corel (`/wp-json/dc/v1/molde`) e a página **Produtos > Moldes (Corel)** |
| `mu-plugins/dc-ouro-boas-vindas.php` | Tela de boas-vindas da escola (pede os logos), vetorização "Enviar logo", logos no cadastro do usuário |
| `mu-plugins/dc-ouro-emails.php` | E-mails com a marca GiftBrand + Ouro Moderno |
| `mu-plugins/dc-ouro-autologin.php` | Entrada automática vinda da Ouro Moderno (primeiro acesso → boas-vindas) |
| `mu-plugins/dc-ouro-loginpress-aprova.php` | Libera no LoginPress as escolas aprovadas |
| `mu-plugins/dc-cores-estoque-sync.php` | Estoque por cor da XBZ e da Asia (tabela "Cores disponíveis") e total = soma das cores |
| `mu-plugins/dc-estoque-sob-consulta.php` | Produto que sumiu do fornecedor há mais de 3 dias mostra "Estoque sob consulta" |
| `mu-plugins/dc-cores-sem-estoque.php` | Esconde cores sem estoque |
| `plugins/dc-ouro-moderno/` | Plugin das lojas das escolas. A **vitrine padrão** está em `includes/vitrine-escola.php` e o topo/loja em `includes/loja-escola.php` |
| `plugins/dc-spot-stock-sync/dc-spot-stock-sync.php` | Sincronizador da Spot (estoque por cor, produtos só com código base, "visto no catálogo") |
| `plugins/dc-xbz-stock-sync/dc-xbz-stock-sync.php` | Sincronizadores da XBZ e da Asia (chamam o estoque por cor e o "visto no catálogo") |
| `corel/DivulgarMolde.bas` | Macro do CorelDRAW 2024. Cole a chave em `CHAVE` (pegue no painel: Produtos > Moldes (Corel)) |

## Vitrine padrão (igual para todas as escolas)

- Layout: o da CENAIC (`includes/vitrine-escola.php`). A vitrine Elementor (página 120849) está **desligada**.
- Seções: Material escolar, Mochilas e sacolas, Garrafas e copos, Tecnologia e estudo, Todos os produtos.
- Os produtos vêm da categoria **Padrão das franquias** (id 1456). Ao entrar/sair dela, o produto entra/sai da categoria de cada escola.
- Topo: logo **colorido para fundos escuros** da escola (cai para vertical escuro → branco → colorido).
- Os 4 produtos do topo são escolhidos entre os que já têm molde.
- O que muda por escola: nome, logos, cor principal, cor/imagem de fundo e banner (painel da escola).

## Logos da escola (6 versões)

| Meta do usuário | Versão |
|---|---|
| `omx_logo` | horizontal colorido para fundos claros |
| `omx_logo_escuro` | horizontal colorido para fundos escuros |
| `omx_logo_branco` | horizontal branco |
| `omx_logo_vertical` | vertical colorido para fundos claros |
| `omx_logo_vertical_escuro` | vertical colorido para fundos escuros |
| `omx_logo_vertical_branco` | vertical branco |

## Molde do produto (meta `_dcmk`)

- `q`: 4 cantos da área vermelha (% da foto quadrada); `qx`: outras áreas (logo em mais de um lugar).
- `m`: cor | branco | laser | escuro | queimado | nada · `l`: h | v · `t`: claro | escuro · `r`: giro (−90 = deitado lendo de baixo para cima).
- `_dcmk_foto`: foto de um item só enviada pelo Corel.
- Só produtos com molde mostram o logo na loja.

## Macro do Corel — "Qual logo usar"

1 horizontal colorido p/ fundos claros · 2 horizontal colorido p/ fundos escuros · 3 horizontal branco ·
4 vertical colorido p/ fundos claros · 5 vertical colorido p/ fundos escuros · 6 vertical branco ·
7 colorido automático (pela cor de cada foto) · 8 laser em metal, logo horizontal · 9 1 cor escura (bambu/kraft) · 10 laser em metal, logo vertical · 11 laser em cortiça/madeira (marrom queimado), logo vertical · Enter = manter.

Pode ter até 6 áreas vermelhas na mesma foto (um logo em cada).

## Pendências

- Moldes dos produtos da vitrine (garrafas e copos novos: 31 produtos; 10 deles ainda com foto de todas as cores juntas).
- Fotos de um item só para os produtos Spot com foto `_set`.
- Sacola BTN301 (Asia): conferir o código no fornecedor.
- Cor do texto do botão "Ver os brindes" conforme a cor da escola.

---

## Atualização de 26–27/11/2026 (arquivos novos e alterados)

Todos estão no mesmo caminho dentro de `wp-content/`. Quando o arquivo tem opção "roda uma vez" (`dc_..._v1`), ele já rodou no servidor.

| Arquivo | O que faz |
|---|---|
| `plugins/dc-price-engine/dcprice-calc.js` | Produto: o campo ao lado do preço é a **quantidade em unidades** (abaixo do mínimo vira o mínimo; a faixa da tabela acompanha) |
| `plugins/dc-price-engine/dc-price-engine.php` | Carrinho usa a **maior faixa que cabe na quantidade** (46 un. = faixa de 30); versão do JS muda com a data do arquivo |
| `plugins/dc-frete/includes/fornecedor.php` | XBZ sem profundidade (objeto redondo): o diâmetro (largura) entra como profundidade |
| `mu-plugins/dc-frete-redondos.php` | Roda uma vez: corrigiu a medida de 162 produtos XBZ que estavam com o chute de 20 x 15 x 10 cm |
| `mu-plugins/dc-frete-copo-320.php` | Roda uma vez: medida real do Copo Térmico Inox 320ml (8,8 x 8,8 x 11,7 cm) |
| `mu-plugins/dc-minicart-topo.php` | Sacola do topo mostra o valor do carrinho (era R$0,00) |
| `mu-plugins/dc-checkout-remover.php` | Checkout: X para tirar cada produto do "Seu pedido" |
| `mu-plugins/dc-loja-rolagem.php` | Loja e categorias sem paginação: os produtos entram ao rolar a página |
| `mu-plugins/dc-loja-cores.php` | Bolinhas das cores com estoque nos cartões da loja Divulgar |
| `mu-plugins/dc-mousepad-laboratorio.php` | 10 fotos do mouse pad no laboratório (estampas do banco de imagens, máscara do Corel), na galeria do BLAIR 93282-106 |
| `mu-plugins/dc-mousepad-tecnologia.php` | Apaga os 10 produtos "Mouse Pad Tecnologia" feitos antes (roda uma vez) |
| `mu-plugins/dc-catalogo-estoque.php` | Catálogo "COPOS E GARRAFAS TÉRMICAS" (121661) |
| `mu-plugins/dc-wordfence-liberar-gift.php` | Libera o endereço do sistema da Gift (44.194.39.85) no Wordfence |
| `mu-plugins/dc-limpa-cache-once.php` | Limpa o cache do WP Rocket uma vez por versão (troque a data da opção para limpar de novo) |
| `mu-plugins/dc-lojas-layout.php` | Layout das lojas em subdomínio (Casafinna, Ambient, Wyda). Agora aceita ajustes por loja pelo filtro `dclay_stores` |
| `mu-plugins/dc-casafinna-logos.php` | Menu **Casafinna** no WordPress: 6 logos, cor principal, cor de fundo, imagem de fundo |
| `mu-plugins/dc-casafinna-css.php` | Roda uma vez: gerou de novo o estilo do Elementor da página 109107 (ícones gigantes) |
| `mu-plugins/dc-casafinna-produtos.php` | Roda uma vez: colocou os 105 produtos da loja Ouro na categoria CASAFINNA (1345) |

### Casafinna (casafinna.giftbrand.com.br)
- Mesmo WordPress da Divulgar. Loja pela categoria **CASAFINNA** (1345) e filhas; página inicial 109107 (Elementor + shortcode `[casafinna_produtos]`).
- Hoje: 125 produtos (105 da loja Ouro + 20 antigos). Preço normal da Divulgar, **sem comissão**, sem nada da Ouro Moderno.
- Os 20 antigos têm o logo dentro da foto (feitos fora do sistema, sem molde). Os outros usam o molde.
- **Falta:** o logo da Casafinna nas fotos dos produtos novos, pela **macro do Corel** (`corel/DivulgarMolde.bas`), e subir os logos no menu Casafinna.
- Quem gerencia é o dono (admin do WordPress). A Casafinna não tem login.
