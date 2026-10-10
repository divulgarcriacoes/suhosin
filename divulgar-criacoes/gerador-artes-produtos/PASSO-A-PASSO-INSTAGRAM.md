# Publicar e agendar no Instagram (automático)

O fluxo completo: **gerar artes → planejar a agenda → publicar sozinho**.

## Parte 1 – Preparar a conta (uma vez só, feita por você)
1. O Instagram precisa ser uma conta **Profissional** (Comercial/Criador) e estar **ligada a uma Página do Facebook**.
2. Em developers.facebook.com, crie um **app** (tipo "Empresa") e adicione o produto **Instagram (API com login do Facebook)**.
3. No Explorador da API do Graph, gere um **token de acesso** com as permissões `instagram_basic`, `instagram_content_publish`, `pages_show_list` e `pages_read_engagement`. Troque por um token de longa duração (60 dias) e renove de tempos em tempos.
4. Descubra o **ID da conta do Instagram** (campo `instagram_business_account` da sua Página).
5. No WordPress: Usuários → seu perfil → **Senhas de aplicativo** → crie uma com o nome "gerador" e guarde a senha. Ela serve para o script enviar as imagens para a biblioteca de mídia do site (o Instagram só aceita imagem com endereço público).
6. Copie `instagram.exemplo.json` para `instagram.json` e preencha os 5 campos. **Não compartilhe esse arquivo com ninguém.**

## Parte 2 – Rotina de cada lote
1. Gere as artes (cada lote vira uma pasta em `saida`, já com `legenda.txt`):
   `python gerador.py --busca caneca --limite 20 --so-estoque`
2. Veja o que seria postado, sem postar nada:
   `python publicar_instagram.py`
3. Planeje a agenda (ex.: 3 posts por dia útil, começando em 03/11):
   `python publicar_instagram.py --planejar --horarios 09:00,12:30,18:00 --inicio 2026-11-03 --dias-uteis --formato ambos`
   Abra o `agenda.json` do lote para ajustar datas e horários na mão, se quiser. Para tirar um produto, apague a pasta dele antes de planejar.
4. Publique:
   - com o computador ligado, deixe aberto: `python publicar_instagram.py --publicar --formato ambos --aguardar`
   - ou use o **Agendador de Tarefas do Windows** para rodar `python publicar_instagram.py --publicar --formato ambos` a cada 30 minutos; ele posta só o que já está na hora e não repete o que já foi postado (controle em `publicados.json`).

## Cuidados
- Teste primeiro com **um produto** (`--limite 1 --publicar`) e confira o post no Instagram.
- O Instagram permite cerca de 100 publicações por dia pela API. Use poucos posts por dia.
- Stories pela API só funcionam em conta profissional e somem em 24 horas, como sempre.
- Se o firewall do site (Wordfence) bloquear o envio das imagens, libere o IP do seu computador ou envie as imagens pela biblioteca de mídia manualmente.
- Revise as legendas: o `legenda.txt` é gerado automaticamente e pode ser editado antes de publicar.
