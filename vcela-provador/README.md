# Vcela Provador (plugin WordPress)

Provador virtual padrão para lojas de roupa. A Vcela Store é a loja modelo.

## Endereços de cada loja
- `https://DOMINIO/{loja}/` — provador (cadastro com aceite dos termos → câmera)
- `https://DOMINIO/{loja}/termos` — termos de uso e privacidade
- `https://DOMINIO/{loja}/clientes?k=CHAVE` — lista de clientes da lojista (+ `&csv=1` planilha)

## Painel
WordPress > **Vcela Provador**: lista de lojas, "Nova loja" (nome, endereço, logo, cores, WhatsApp, Instagram, peças em PNG sem fundo, link da lista de clientes).

## Instalação
Copiar a pasta `vcela-provador/` para `wp-content/plugins/` e ativar. Na ativação cria a tabela `{prefix}vcela_clientes` e a loja modelo `vcelastore`.

## API
- `POST /wp-json/vcela/v1/cadastro` {loja, nome, whatsapp, tamanho, termos, termos_versao, novidades}
- `POST /wp-json/vcela/v1/prova` {token, peca}

Ao mudar os termos, troque `VCP_TERMOS`: todas as clientes aceitam de novo.
