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

## Casa Finna (casafinna.com.br): venda online de móveis planejados

O site atual é institucional e completo, sem loja. Conceito: "Projete sua casa online", com três entradas (quero comprar, quero projetar, quero inspiração). Modelo: configurador + orçamento em faixa de preço + projetista + CRM + pagamento + acompanhamento. Não é um e-commerce tradicional.

Etapas combinadas, uma por vez, cada uma testada antes da próxima:
1. "Começar meu projeto": configurador de 7 passos (ambiente, tamanho, fotos, planta, estilo, acabamentos, dados), protocolo (ex.: CF-10842), e-mail de confirmação e painel interno de projetos.
2. Páginas de ambientes (SEO) e orçamento online em faixa de preço.
3. Área do cliente "Meu projeto" com etapas e apresentação (aprovar, solicitar alteração, falar com projetista).
4. Contrato digital e pagamento (Pix, cartão, boleto).
5. Painel de gestão (funil) e distribuição de leads por projetista e região.
6. Parceiros (arquitetos, designers) e indicação por link.
7. IA que gera ideia a partir da foto (por último; exige chave e tem custo).

Para começar a etapa 1 falta:
- Acesso ao site da Casa Finna pelo EMCP Tools (as ferramentas hoje só alcançam o site da Divulgar Criações; o ambiente bloqueia o endereço casafinna.com.br).
- Ambientes e acabamentos reais.
- Projetistas e como dividem os projetos (cidade ou região).
- Tabela de preço por metro linear (ambiente, padrão e acabamento) para a faixa de estimativa, na etapa 2. Não inventar faixa.
- Contrato digital: definir como registrar o aceite com valor legal. Financiamento fica fora da primeira versão.

## Rede de designers (ideia em definição)

Rede centrada no designer, de todas as áreas (gráfico, interiores, móveis planejados, moda, produto, web, ilustração e outras). O designer é quem assina. Cliente final, lojistas e fornecedores existem para dar pedidos, vagas e fornecedores ao designer. Não é uma rede social de feed no começo.

Partes do produto:
- Sala do designer: apresentação, projetos por área (fotos, 3D, planta, estilo, materiais, prazo), faixa de preço, serviços, avaliações e botão "quero um projeto assim". Painel privado para cadastrar projetos e atender pedidos.
- Áreas de atuação: cada designer escolhe uma ou mais. Busca, vagas, classificados e anúncios são filtrados por área.
- Pedidos de clientes: o cliente escolhe a área e o designer e pede projeto ou orçamento. Principal motivo para o designer assinar.
- Vagas: qualquer empresa pode publicar vagas para designers e áreas afins. Candidato se cadastra e se candidata com um clique. Currículo é dado pessoal (LGPD). Vaga expira em 30 a 45 dias.
- Classificado: qualquer empresa ou profissional anuncia produtos e serviços (material, software, equipamento, gráfica, curso, montagem). Anúncio com validade de 30 dias.
- Publicidade: faixa na home e nas páginas de área, patrocinado na busca e perfil de loja ou fornecedor. Sempre identificar como "Publicidade". Painel do anunciante com cliques e visualizações.

Receita:
- Assinatura dos designers (planos por número de projetos na sala, destaque nas buscas e pedidos por mês), cobrança recorrente pelo Mercado Pago. Sem pagamento, a sala sai do ar sem apagar nada.
- Anúncios, classificados e vagas de empresas: incluídos em plano ou avulsos, com destaque pago.
- Como não há comissão por projeto, o contato direto (WhatsApp) do designer pode aparecer.

Regras decididas:
- Qualquer empresa ou profissional pode anunciar, publicar vaga ou classificado, em qualquer área.
- Moderação: revisar antes de publicar no começo, ter botão "denunciar", lista de itens proibidos e remoção rápida. Deixar claro que a negociação é entre as partes e que a plataforma não contrata ninguém.
- Lançar com todas as áreas no cadastro, mas convidar os primeiros 20 a 30 designers em uma área forte (talvez móveis planejados, por causa da Casa Finna) e dar os primeiros meses grátis.

Em aberto: valores e número de planos, período de teste, limite de pedidos por plano, plataforma (WordPress ou sistema próprio), qual área fortalecer primeiro e se a Casa Finna participa.

### Premiações e avaliações (rede de designers)

- Dois prêmios mensais separados: "Melhor avaliado do mês" (só avaliações verificadas de clientes de projetos, com nota ponderada, mínimo de avaliações no mês, desempate por quantidade e por data) e "Escolha do público" (voto aberto, exige cadastro, um voto por pessoa por designer por mês, proteção contra robôs, votos suspeitos podem ser anulados). Exemplo: o melhor avaliado de dezembro ganha um tablet.
- Fluxo de avaliação: o designer cadastra o projeto concluído com o contato do cliente e a plataforma envia o link de avaliação, com confirmação por código. Uma avaliação por projeto e por cliente, o designer só pode responder. Selo de origem: "projeto contratado pela plataforma" ou "cliente indicado pelo designer" (peso menor ou fora do prêmio).
- O designer divulga o próprio link para o público votar. Isso gera acesso e cadastros para a rede.
- Cuidados: regulamento claro (quem participa, critérios, prazos, direitos de imagem, entrega do prêmio), checar com advogado se a premiação exige autorização, e ter o fluxo de avaliação pronto antes da primeira edição.
- Medir: registrar a origem de cada voto e visita (link do designer) para mostrar a ele quantas pessoas trouxe. Preparar o servidor e o cache para picos perto do fechamento.

### Referência: Behance e áreas de atuação (rede de designers)

- Referência de estrutura: Behance. Barra de áreas no topo, grade de projetos só com imagem (título, autor, curtidas e visualizações), selo PRO ao lado do nome com card de venda do plano pago no meio da grade, login com Google discreto, abas "For You" e "Following". Usar só como referência de estrutura; visual, nome e textos devem ser próprios.
- Lacuna a explorar: no Behance só existe "Arquitetura"; interiores e móveis planejados quase não aparecem. Fortalecer primeiro Interiores e Móveis planejados.
- A rede mistura todas as áreas, com áreas como porta de entrada (página, destaques e ranking por área). A sala do designer muda os campos conforme a área (móveis e interiores: ambiente, medidas, materiais, planta, 3D). Primeira versão com campos comuns (título, fotos, descrição, cidade, ano) mais os campos de móveis e interiores.
- Diferenciais em relação ao Behance: cliente buscando designer na cidade com pedido de projeto e orçamento, português com Pix e boleto, vagas e classificados no mesmo lugar, premiações por avaliação de clientes reais, cidade do designer no card.
- Nome: em escolha. Critérios: curto, fácil de falar, serve para todas as áreas, domínio .com.br e Instagram livres, marca livre no INPI (conferir fora daqui). Ideias: Ateliê, Traço, Croqui, Trama, Elo, ou nome inventado curto com "a rede de designers do Brasil" como apoio.
