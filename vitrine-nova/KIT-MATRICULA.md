# Kit Matrícula — plano (26/11/2026)

## Por que
A parceira Ouro Moderno vai divulgar o kit matrícula para **cerca de 1.200 escolas**. Cada escola vê o kit **com o próprio logo** na vitrine dela e personaliza.

## Como vai funcionar (combinado com o Walter)
- Um **produto "Kit Matrícula"** na vitrine (categoria 1456, Padrão das franquias, que propaga para 1457, 1458, 1469 e 1471).
- **O Walter monta a arte do kit no Corel**: uma imagem com todos os itens juntos e **uma área vermelha em cada item** (a macro aceita até 6 áreas). Ele envia o molde para o SKU do kit, e o logo da escola entra em todos os itens de uma vez.
- **Opções do kit:** bolsa = **sacola** ou **mochila saco**; os outros itens são fixos.
- **Preço do kit = soma dos itens** na quantidade escolhida, pelo motor de preços. **Mínimo de 250 kits**, por causa da sacola e da mochila.

## Itens (divulgarcriacoes.com.br)
| Item | ID | SKU | Molde | Tabela (unitário) |
|---|---|---|---|---|
| Sacola TNT com costura termo | 40853 | 13780N | sim | 250: 4,38 · 500: 3,94 · 1250: 3,74 · 1750: 3,63 · 2500: 3,52 · 5000: 3,42 |
| Sacochila (mochila saco) TNT | 80545 | 14805 | não | 250: 5,46 · 500: 5,40 · 1250: 5,35 · 1750: 5,19 · 2500: 5,03 · 5000: 4,88 |
| Squeeze Dobrável 480ml | 5909 | 12459 | não | 15: 7,42 · 30: 6,68 · 75: 6,34 · 105: 6,15 · 150: 5,97 · 300: 5,79 |
| Bloco com autoadesivos e caneta kraft | 6753 | 05049 | sim | 15: 12,22 · 30: 11,00 · 75: 10,45 · 105: 10,13 · 150: 9,83 · 300: 9,54 |

Todos são produtos simples. Ainda **sem molde**: mochila 14805 e squeeze 12459. Outras opções de bolsa com molde: 13781I e 15175.

## Implementação planejada (mu-plugin `dc-kit-matricula.php`)
1. Na 1ª execução, **cria o produto** pelo código (o MCP `create-post` não grava `_sku` nem `_price`): SKU `KITMAT`, simples, publicado, nas categorias da vitrine e com a imagem temporária do 1º item.
2. **Preço por quantidade:** para cada item, `dcprice_calculate_with_curve( $item_id, $qty )['unit_price']` (a mesma função do carrinho), e o kit é a soma. Faixas do kit: 250, 500, 1000, 2500, 5000. Cache em transient.
3. **Página do produto:** tabela própria "Escolha a bolsa" (sacola ou mochila) e "Escolha a quantidade", com preço por kit e total. A calculadora do dc-price-engine não roda no kit, porque `dcprice_product_is_priceable` falha.
4. **Carrinho:** `woocommerce_before_calculate_totals` define o preço do kit = soma na quantidade do carrinho; a opção da bolsa vai em cart item data; validação do mínimo de 250.
5. **Card da vitrine:** "a partir de" = preço do kit com sacola em 250 unidades.
6. **Antes de enviar ao site:** `php -l` local e `node --check` no JS gerado. Lição de 25/11: aspas simples dentro de string PHP com aspas simples derrubaram o site.

## Pendências
- Arte do kit no Corel (Walter) e envio do molde para o SKU KITMAT.
- Moldes da mochila 14805 e da squeeze 12459, se forem vendidas separadas também.
- Confirmar com o Walter: preço fechado ou soma dos itens, mínimo, comissão da parceira e prazo da ação.
