# Notas do projeto Divulgar Criações

Resumo para retomar o trabalho em outra sessão. Última atualização: 05/10/2026.
Não guardar chaves ou senhas neste arquivo.

## Como o site é montado

- WordPress 7.1, WooCommerce, Elementor Pro e Rank Math. Cache: WP Rocket (limpar depois de mudar a loja).
- Quase tudo foi feito com snippets no EMCP Tools (Sandbox). Cada atualização salva o snippet como rascunho inativo e é preciso reativar.
- Regras dos snippets: não chamar funções por variável (o validador bloqueia), proteger funções com `function_exists`, e não registrar `add_action` dentro de um snippet que já roda no mesmo gancho e prioridade (imprimir direto).
- Plugins no repositório (`entregas/`): `dc-min-order-qty-v2.php`, `dc-price-omnitek.php`, `dc-product-source-technique.php`.

## Snippets principais (id: o que faz)

- 83951: botão adicionar ao carrinho, aviso "Adicionado! N itens" com "Ver carrinho". Loja e página do produto sem recarregar. Na página do produto multiplica pacotes por unidades (`.dcprice-pack-size` e `.dcprice-step-value`).
- 84401: taxa de manuseio de 20% sobre o frete (PAC, SEDEX e outros; exceto retirada e frete grátis). Porcentagem no começo do snippet.
- 83949: limpar títulos repetidos (Produtos, Limpar títulos). Mantém o nome curto e junta a medida logo depois do traço (ex.: Bolsa Térmica 2,6 Litros). Tem desfazer.
- 83990 e 83991: técnicas de gravação por material, com regras por produto.
- 82879: botão Categorias da loja (texto alinhado à esquerda, nomes sem `&amp;` duplicado e com acento em maiúsculas).
- 84375, 84374, 84383: Minha conta (widget, CSS e redirecionamento do parceiro para o Painel Comercial).
- 84390, 84391, 84395, 84396: pedido em PDF com arte, nomes e link de pagamento (Mercado Pago), e botão Baixar PDF no orçamento rápido.
- 84397: Envio e NCM (copiar NCM dos fornecedores, peso e medidas por categoria).
- 84399 e 84400: diagnóstico e importação de NCM e peso da API da Spot.
- 82840 a 83988: Painel Comercial por fases (vendedor, clientes, orçamentos, vendas, parceiros, bloqueio, produção, catálogos).
- Preço: plugin `dc-price-engine` (margem 70%, imposto 4,5%, acréscimo de gravação 20%). Fórmula: (custo + gravação) × (1 + margem) × (1 + imposto).

## Decisões de negócio

- Revenda: a agência compra da Divulgar Criações, que emite a nota de produto para a agência pelo preço da Divulgar. A agência revende ao cliente final com a margem dela. A agência não emite nota; isso é responsabilidade dela e do contador dela.
- Exemplo: Divulgar vende R$ 2.000, agência vende R$ 2.400, margem de R$ 400 fica com a agência. Imposto da Divulgar: 4,5% de R$ 2.000 = R$ 90.
- Pendentes a combinar com as agências: prazo de pagamento, prazo de produção, entrega no endereço do cliente da agência com a nota em nome da agência.
- Título do Google (Rank Math): não colocar SKU. Nome curto com volume.
- Frete conferido: 15 garrafas 1,2 L para 18035-011, PAC R$ 39,53 e SEDEX R$ 29,75 já com 20%; 30 garrafas, PAC R$ 72,44 e SEDEX R$ 54,73.

## Pendências

1. Trocar a chave da Spot (está escrita direto no plugin `dc-spot-stock-sync`). Aguardando nota. Nunca repetir a chave em conversa ou arquivo.
2. Reativar o snippet 82879 e conferir o menu de categorias à esquerda.
3. Comparar o frete com o app dos Correios (30 garrafas, CEP 18035-011) para ver se o contrato está ativo no plugin.
4. Gerador de pedido: criar modo "pedido da agência", com o link de pagamento no preço da Divulgar (hoje cobra o preço final ao cliente).
5. Preencher à mão 144 NCMs inválidos e 29 produtos da Spot sem peso de caixa.
6. Aplicar as linhas que faltam em Técnicas por material e conferir a lista "Sem material identificado".
7. Desativar o snippet antigo 84367 (Minha conta, versão antiga).
8. Confirmar com o contador o imposto de 4,5% (hoje multiplica por 1,045; sobre o valor da venda seria dividir por 0,955).

## Material da reunião com as agências

- Página para as agências: https://claude.ai/artifact/4UKLTqWGkLjgTktRmiGM6T
- Roteiro interno: https://claude.ai/artifact/1q3Sw4RynJZ1cJiSHeQJ14
