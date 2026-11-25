# Vcela Store — onde paramos (25/11/2026)

## No ar (vcela.com.br)
- WordPress + Elementor + WooCommerce 11.3.1. Página inicial (id 20) com cores e logo da Vcela, sem imagens vetoriais. Faltam fotos reais.
- Plugin **Vcela Provador**: instalado no site = **1.6.0**. O zip mais novo é `vcela-provador-1.7.0.zip`, que ainda **não foi instalado**.
  - Provador com IA (FASHN tryon-v1.6) em /vcelastore. Tem cadastro e aceite dos termos (versão 2026-11-27), abas de categoria e subcategoria, tamanhos e preço por peça, botão Comprar e Pedir no WhatsApp com a página da prova (prévia grande, apagada em 7 dias).
  - Chave da FASHN salva no painel. Créditos: cerca de 100 no início.
  - Lista de clientes: /vcelastore/clientes?k=… (o link está no painel da loja).
  - Ponte WooCommerce: os campos `vcela_preco`, `vcela_preco_promo`, `vcela_estoque`, `vcela_codigo`, `vcela_tamanhos` e `vcela_estoque_tamanhos` ("P:3, M:2, G:4") viram preço, estoque, SKU, atributo Tamanho e variações.
- Produto de teste: **Vestido Midi Plissado Laranja** (id 54, Feminino → Vestidos). O preço R$ 189,90 e o estoque P:3, M:2, G:4 são de teste.
- Peças antigas cadastradas pelo painel da loja (Flor bella, Laranja, Branco top) continuam no provador.

## Pendências
1. Instalar a 1.7.0 e conferir se o produto 54 virou variável (P3, M2, G4).
2. Subir para 50 as provas por cliente durante os testes (painel Vcela Provador → Prova com IA). Depois voltar para 5.
3. Preços, tamanhos e estoque reais do vestido laranja.
4. A irmã preencher `Vcela-cadastro-produtos-modelo.xlsx` e subir as fotos na Mídia com o nome = código. Depois: importar e atualizar a planilha para o estoque por tamanho (P:3, M:2, G:4).
5. Fotos reais para o banner e as categorias da página inicial.
6. Revisão dos termos por advogado antes de vender o produto para outras lojas.
7. A conversa atual não enxerga as ferramentas de escrita de arquivos/plugins do MCP do vcela.com.br. Em uma conversa nova elas aparecem.
8. Piloto antigo no divulgarcriacoes.com.br (mu-plugins `dc-provador-api.php` e `dc-provador-sync.php`, e uploads/provador) pode ser removido.
