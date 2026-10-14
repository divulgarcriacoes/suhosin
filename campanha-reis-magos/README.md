# Campanha dos Reis Magos

Modelo navegável do site de arrecadação da **Paróquia São Luís Gonzaga** (São Roque | SP) para a construção da capela em honra ao Menino Jesus.

> Esta pasta é independente do restante do repositório (extensão PHP Suhosin). Nada fora dela foi alterado.

## Como abrir

Abra `index.html` no navegador. Não precisa de servidor nem de instalação. As imagens ficam em `img/`.

## Áreas do site

Início · A capela · Eventos · Painel de doações (com projeção) · Avanço da obra · Transparência · Família doadora · Livro de Ouro · Parceiros · Doe material · Área do fornecedor · Área do doador · Administração.

O resumo completo, com capturas de tela, regras definidas e decisões pendentes, está em `docs/Campanha-Reis-Magos-Resumo.pdf`. As capturas de cada área ficam em `docs/telas/`.

## O que é real e o que é exemplo

- **Real (do folheto):** nome da paróquia, lema, textos da história, arquitetura, sustentabilidade e oração, imagens da capela, regras de doação.
- **Exemplo:** valores arrecadados, meta, doadores, etapas com custos e datas, eventos, empresas parceiras e fornecedores, CNPJ `00.000.000/0000-00` e QR Code do Pix.

Nenhuma empresa real foi usada como parceira ou fornecedora.

## Observações técnicas

- Arquivo único em HTML, CSS e JavaScript, sem dependências. Só as fontes vêm do Google Fonts, com alternativas locais.
- Os dados de exemplo estão no início do bloco `<script>`; datas de eventos são relativas ao dia de hoje.
- Os avisos push, os pagamentos, o login de administrador e de fornecedor são simulações. No WordPress, dependem de gateway de pagamento, serviço de notificação e perfis de usuário.
