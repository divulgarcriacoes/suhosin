# Modelo da legenda

O gerador monta o `legenda.txt` de cada produto a partir do arquivo `legenda_modelo.txt`.
Abra no Bloco de Notas e mude o texto como quiser. Campos que você pode usar (entre chaves):

{nome} {codigo} {preco} {minimo} {gravacao} {categoria} {link} {whatsapp} {hashtags}

- Se um campo estiver vazio para o produto (ex.: sem gravação, sem WhatsApp), a linha inteira some. 
- O WhatsApp vem do `config.json` (campo "whatsapp" dentro de "loja").
- Para usar outro modelo: python gerador.py --legenda-modelo legenda_modelo_descontraido.txt ...
