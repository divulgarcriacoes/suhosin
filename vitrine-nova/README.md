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
- `m`: cor | branco | laser | escuro | nada · `l`: h | v · `t`: claro | escuro · `r`: giro (−90 = deitado lendo de baixo para cima).
- `_dcmk_foto`: foto de um item só enviada pelo Corel.
- Só produtos com molde mostram o logo na loja.

## Macro do Corel — "Qual logo usar"

1 horizontal colorido p/ fundos claros · 2 horizontal colorido p/ fundos escuros · 3 horizontal branco ·
4 vertical colorido p/ fundos claros · 5 vertical colorido p/ fundos escuros · 6 vertical branco ·
7 colorido automático (pela cor de cada foto) · 8 laser em metal, logo horizontal · 9 1 cor escura (bambu/kraft) · 10 laser em metal, logo vertical · Enter = manter.

Pode ter até 6 áreas vermelhas na mesma foto (um logo em cada).

## Pendências

- Moldes dos produtos da vitrine (garrafas e copos novos: 31 produtos; 10 deles ainda com foto de todas as cores juntas).
- Fotos de um item só para os produtos Spot com foto `_set`.
- Sacola BTN301 (Asia): conferir o código no fornecedor.
- Cor do texto do botão "Ver os brindes" conforme a cor da escola.
