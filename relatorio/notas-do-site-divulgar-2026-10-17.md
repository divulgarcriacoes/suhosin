# Notas do site Divulgar Criações (gravadas em 17/10/2026)

Leia isto ANTES de investigar ou perguntar de novo. Também confira o histórico da conversa antes de pedir testes ao Walter.

## Como o site está montado

- WordPress da Divulgar (`divulgarcriacoes.com.br`) com Elementor, WooCommerce, Rank Math, Site Kit, WP Rocket.
- Quase tudo "sob medida" está em **snippets do EMCP Tools (Sandbox)**, e não em plugin. A lista está no Sandbox (cerca de 200 ativos). Dá para ler cada um com `get-php-snippet`.
- Domínios **estacionados** no mesmo WordPress (alias): `giftbrand.com.br` e `liriodepureza.com.br`. Não são sites separados.
- Arquivo de preços `dc-price-omnitek.php` (fica no servidor; só o Walter edita). Cópia com o desconto por categoria: ver abaixo.

## GiftBrand (giftbrand.com.br) — revenda/parceiros

Snippets (lidos em 17/10):
- **86880** página inicial de cadastro de revendedor (só em giftbrand.com.br). Botão "Já sou parceiro" vai para `/wp-login.php?redirect_to=/wp-admin/admin.php?page=dc-painel-comercial`.
- **87788** "trava": em giftbrand.com.br só abrem `/wp-admin`, `/wp-login.php`, `/wp-content/`, `/wp-includes/`, `/wp-json`, `/orcamento-lista/`, robots, favicon, `.well-known`. `/my-account*` vai para o painel (logado) ou para `/wp-login.php`. Home com parâmetros que não sejam `dc_catalogo` ou `dc_reg` volta para `/`. Todo o resto volta para `/`.
- **87827** loja do parceiro `/?loja=CODIGO` (pública, preço = preço Divulgar × (1 + comissão do parceiro)).
- **82857** login do vendedor vai ao painel (`login_redirect`, usa `admin_url(...)`); **84383** conta de parceiro vai ao painel; **82903** bloqueio de parceiro; **94665** troca links do painel por giftbrand.com.br só para visitantes.
- Parceiro = papel `vendedor`. Cadastro novo fica `dc_pendente` até o Walter aprovar.

### Problema aberto (17/10): ERR_TOO_MANY_REDIRECTS no GiftBrand
- Home e tela de login do giftbrand.com.br abrem normalmente.
- Depois do login, `giftbrand.com.br/wp-admin/admin.php?page=dc-painel-comercial` entra em ciclo. `giftbrand.com.br/my-account-2/` também.
- Walter tentou 10 vezes. Não há Cloudflare. O Brevo no DNS não tem relação (só registros de e-mail).
- Nenhum snippet lido explica o ciclo sozinho. Hipóteses não confirmadas: cookie de login não aceito no domínio estacionado (COOKIE_DOMAIN, FORCE_SSL_ADMIN, WP_HOME/WP_SITEURL no `wp-config.php`), `admin_url()` apontando para o domínio principal, ou combinação trava + redirecionamento do painel.
- Próximo passo combinado: print da aba Network do Chrome (F12, "Preserve log") com a cadeia de redirecionamentos, e dizer com qual tipo de conta entra (parceiro aprovado, administrador ou teste).

## Preços (arquivo dc-price-omnitek.php)

- Preço unitário = (custo + gravação) × margem × imposto. Gravação = % do custo (Tampografia 25%, Silk 22%, Laser 20%, demais 20%).
- Margem e imposto são globais (tela de Precificação). Só Marcas usa a tela "Importar Só Marcas" (dc_sm_cfg).
- Preço calculado para o **pedido mínimo** do produto (classe DC_Min_Order_Qty); descontos por quantidade: 0, 10, 14,5, 17, 19,5 e 22% nos múltiplos 1, 2, 5, 7, 10 e 20 do mínimo. Se o unitário ficar abaixo de R$ 2,10, soma R$ 1,20 (R$ 0,40 em canetas de plástico).
- O preço gravado no produto (que o Google e a Meta leem) só muda com o recálculo: `wp-admin/?dc_chav_recalc=1` (20 produtos por vez, não fechar a aba). Foi o que gerou diferenças entre o Google e a página (mínimo mudou de 15 para 20).
- **Desconto por categoria** (feito em 17/10): tela em Ferramentas > Desconto por categoria (`tools.php?page=dc-desc-max`), opção `dc_desc_max_cat`. Subcategoria vazia herda a mãe; vazio herda, zero cancela; produto em várias categorias usa a mais específica. Entra em `dcprice_calculate_for_product`, então vale na loja, no preço gravado, no Google, na Meta e na revenda. Não vale para produtos Só Marcas com faixas de preço do fornecedor.
- Teste feito: categoria ACESSÓRIOS PARA CARROS com 5%: R$ 27,20 virou R$ 25,84 e R$ 33,14 virou R$ 31,48 (conta exata).

## Google, Meta, Analytics

- Merchant Center + plugin Google for WooCommerce (menu Marketing). 948 produtos achados pelo Google, status "Em análise". Política de troca publicada em `/politica-de-troca-e-correcoes/` (página 19574): só troca por defeito, 7 dias, e-mail sac@divulgarcriacoes.com.br, custo do envio por conta da Divulgar, nenhum valor devolvido até o produto chegar à Divulgar, sem troca por outro produto, cor varia por lote.
- Pixel da Meta "Divulgar Brindes" (ID 813097410160643), ativo e disparando. Snippet 100585 dispara Lead ao abrir o orçamento; nenhum Lead nos últimos 7 dias (hipótese: robô consome a trava `_dc_lead_sent` antes do cliente).
- Analytics: propriedade nova (G-P73DSXRWG7, tag GT-NGPB7HJG) pelo Site Kit; o código antigo do Elementor foi removido. Tempo real mostrou usuários.

## Cuidados aprendidos

- Criar snippet pelo Sandbox (create-php-snippet) coincidiu **duas vezes** com a tela do Sandbox em branco; apagar o rascunho restaurou o painel. Evitar criar snippets por lá; preferir entregar arquivo.
- Backslashes em código enviado ao Elementor Custom Code são removidos; evitar regex com barras nesses códigos.
- Elementor criado por ferramenta precisa de IDs nos elementos (senão o editor trava).
- O conector EMCP só lê o domínio principal; não abre giftbrand.com.br nem consulta DNS.
- Não pedir ao Walter para repetir testes que o histórico já mostra. Ler o histórico primeiro. Tratar por "você".

## Atualização (17/10, fim do dia): ciclo de redirecionamento dos parceiros

- O ciclo (ERR_TOO_MANY_REDIRECTS em `/my-account-2/` e no painel) acontece **também em divulgarcriacoes.com.br** com conta de parceiro (papel `vendedor`, ex.: natiene@divulgarcriacoes.com.br). Não é problema de domínio estacionado nem de cookie por domínio.
- Descartados: LoginPress (captcha desligado, Google "Não Verificado"), captcha do Wordfence (modo de teste, não bloqueia), passkeys, 2FA do Wordfence (Vendedor = Desativado), snippets 82857, 82862, 82903, 84383 (nenhum manda o parceiro do painel para Minha conta).
- Aviso "domínio inválido para a chave do site" na tela de login = reCAPTCHA do Wordfence sem o giftbrand.com.br autorizado. Não bloqueia o login; limpeza futura em google.com/recaptcha/admin.
- Suspeito principal (não confirmado): proteção do WooCommerce que manda quem não tem `edit_posts`/`manage_woocommerce` de wp-admin para Minha conta (`woocommerce_prevent_admin_access`). Correção proposta, pronta: `relatorio/parceiro-sem-bloqueio-woo.php` (filtro que libera quem tem `dc_view_own_quotes`). Aguardando OK para ativar (colar no Sandbox, gancho init).
- Parceiros cadastrados (papel vendedor): 10, criados de 01 a 07/10. Dona Maria vai começar a vender pelo GiftBrand.
- Snippet 94979 (Metalnox) excluído a pedido; dados salvos ficam na opção `dc_mtx_cfg`.

### RESOLVIDO (17/10, noite)
- Snippet 107937 (ativo): filtro `woocommerce_prevent_admin_access` retorna false para quem tem `dc_view_own_quotes`. Depois de ativado, o parceiro (Natiene) entrou no painel. Causa: a proteção do WooCommerce mandava o parceiro (sem edit_posts/manage_woocommerce) de wp-admin para /my-account-2/, e o snippet 84383 mandava de volta ao painel.
- Pendências menores: reCAPTCHA do Wordfence sem giftbrand.com.br (não bloqueia, está em modo de teste); botão Google do LoginPress "Não Verificado" (não configurado).
