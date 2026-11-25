# DC · Ouro Moderno (plugin)

Painel da Matriz (wp-admin, menu "Painel da Matriz", para o administrador; `/painel-matriz/` no proprio site para a Diretoria e a Equipe) e Area do Franqueado (Minha conta), mais as funcoes **Diretoria Ouro Moderno** (`dcmx_ver` + `dcmx_config`) e **Equipe da Matriz** (`dcmx_ver`).

## Instalar
1. wp-admin > Plugins > Adicionar novo > Enviar plugin > escolher `dc-ouro-moderno-0.2.4.zip` > Instalar > Ativar.
2. Se aparecer o aviso amarelo, apagar de `wp-content/mu-plugins/` os arquivos `dc-ouro-matriz.php`, `dcom-matriz.inc`, `dc-ouro-painel.php` e `dcom-painel.inc` (sao a copia antiga; com eles la o plugin nao carrega o modulo, para nao dar erro).
3. Limpar o cache do WP Rocket.

## Acesso da Diretoria e da Equipe (0.1.3, mesma logica do Alta Conexao)
`includes/acesso.php`: quem tem so a funcao Diretoria Ouro Moderno ou Equipe da Matriz (sem ser administrador) nunca ve o wp-admin.
- O login (tela do WordPress ou Minha conta) leva a `/painel-matriz/`, inclusive em `ouromoderno.giftbrand.com.br` (roda antes do travamento `dcpt_lock`).
- `/wp-admin` e a Minha conta redirecionam para o painel; a barra de administracao some.
- Antes, o WooCommerce barrava o wp-admin dessas contas e elas caiam na Minha conta vazia.

## Tela Entrar (modelo Grupo Alta Linha)
`includes/entrar.php`: `/my-account-2/entrar-escola/` em tela cheia com a imagem de fundo da loja (cfg `bg`), sem menu nem rodape, logo em cima, cartao azul com borda amarela, botao Mostrar senha. Mesmo login do WooCommerce. Desligar: `define( 'DCOM_ENTRAR_MODE', 'off' );`.

## Painel da Matriz: tela Franquias
`?tela=franquias` (no wp-admin e em `/painel-matriz/`): unidades com nome, cidade, contato, situacao, equipe, pedidos, compras e link da loja; busca e filtro Todas/Ativas/Em analise.

## Desligar
Sem desativar o plugin: no `wp-config.php`, `define( 'DCMX_MODE', 'off' );` (Matriz) ou `define( 'DCOMP_MODE', 'off' );` (Franqueado).

## Pendente
- Comissao da Matriz: ainda usa 10% (opcao `dcmx_comissao_pct`); falta ler a comissao real do parceiro Ouro Moderno no Painel Comercial.
- `includes/matriz.php` e `includes/painel.php` sao copias de `mu-plugins/dcom-matriz.inc` e `mu-plugins/dcom-painel.inc`. Depois de instalar o plugin, a fonte passa a ser a do plugin.
