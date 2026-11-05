# O8. Status HTTP corretos (404, 422)

Pedido, como o professor faria na hora: "Evento inexistente deve
responder 404, não 302 para a listagem. Formulário inválido deve
responder 422. Teste o status por HTTP."

Chance: baixa. Tempo estimado: 15-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, O8.

Branch no clone: `fvp/o8-status-http`, a partir de `main` (`f31006b` do público;
no bundle `5dedab5`, ver README raiz).

Commit:

- `b54d2be` `feat: Respond 404 for unknown events and 422 for invalid
  forms`

## O que mudou

- `app/Controllers/Admin/EventsController.php`:
  - `eventNotFound()`: no lugar de flash + redirect,
    `http_response_code(404)` e `render('errors/404', ...)` com
    `$title = 'Evento não encontrado'`. Vale para `show`, `edit`,
    `update` e `destroy` com id inexistente.
  - `create` e `update` inválidos: `http_response_code(422)` logo
    antes do `render` do formulário (o flash "Existem dados
    incorretos!" e os erros por campo continuam).
- `app/views/errors/404.phtml` (novo): "O evento procurado não existe
  ou foi removido." e o botão "Voltar para a lista de eventos".
- Testes:
  - `EventsAccessTest` (HTTP real, Guzzle): o antigo
    `test_admin_should_be_redirected_to_index_when_event_does_not_exist`
    (302) virou
    `test_admin_should_get_404_when_event_does_not_exist`, com data
    provider para GET show, GET edit, PUT e DELETE (404, sem
    `Location`, corpo com "Evento não encontrado"); novos: create
    inválido -> 422 sem gravar; update inválido (`_method=PUT`) -> 422
    com o Evento intacto; create válido continua 302 para o `show`.
  - `EventsControllerTest`: `test_show_with_unknown_id_redirects_to_index`
    virou `test_show_with_unknown_id_renders_not_found_page` (página,
    sem `Location` nem flash); novo teste para `edit`/`update`/
    `destroy` com id inexistente.
  - `NotFoundEventCest` (novo): `/admin/events/999999` fica no mesmo
    endereço, mostra o `h1` "Evento não encontrado" e o botão volta à
    listagem.

## Por que assim

- O teste de controller roda em CLI: lá `http_response_code()` não
  tem efeito e o teste não vê status. Por isso os status são
  conferidos por HTTP (`EventsAccessTest`, Guzzle contra o `web`),
  com `http_errors => false` para o Guzzle não lançar exceção em
  4xx. O Cest (Selenium) também não lê status: ele confere a página.
- `http_response_code()` antes do `render`: o `render` imprime; o
  status tem de ir antes da saída.
- Página própria em vez de `throw new HTTPException`: o
  `ErrorsHandler` mostraria o stack trace com caminhos do servidor.
- Um lugar só (`eventNotFound`): as quatro ações já passavam por ele.
- 422 só no form inválido: o pedido chegou e foi entendido, os dados
  é que não passaram. Sucesso continua 302 (create/update para o
  `show`, destroy para a listagem).
- Custo, a dizer ao professor: flash + redirect era o padrão da base;
  dois testes mudaram de 302 para 404/página.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
```

Resultado obtido: Unit 159 testes, Integration 52, Acceptance 24
(base: 159 / 45 / 23). PHPCS e PHPStan sem erros. Sem schema.

Testes novos ou alterados:

- `EventsAccessTest`:
  - `test_admin_should_get_404_when_event_does_not_exist` (4 casos;
    substitui o teste de 302)
  - `test_invalid_create_should_respond_422_with_the_form`
  - `test_invalid_update_should_respond_422_and_keep_the_event`
  - `test_valid_create_should_still_redirect_with_302`
- `EventsControllerTest`:
  - `test_show_with_unknown_id_renders_not_found_page` (substitui
    `..._redirects_to_index`)
  - `test_edit_update_and_destroy_with_unknown_id_render_not_found_page`
- `NotFoundEventCest::unknownEventShowsNotFoundPage`

Pelo terminal, com cookie de admin (clone sem porta):

```
docker compose exec web sh -c "curl -s -c /tmp/a \
  -d 'user[email]=admin@example.com&user[password]=123456' \
  http://localhost/login >/dev/null; \
  curl -s -i -b /tmp/a http://localhost/admin/events/999999 \
  | head -1; \
  curl -s -i -b /tmp/a -d 'event[title]=' \
  http://localhost/admin/events | head -1"
# HTTP/1.1 404 Not Found
# HTTP/1.1 422
```

(Nesta stack o 422 sai sem a frase ao lado; o código é o que
importa.)

## Relação com outros cenários

- Q1 (`q1-so-dono-edita-remove`): Evento de outro Administrador cai em
  `eventNotFound`; com este cenário aplicado, vira 404 também. Se o
  professor preferir 403 nesse caso, é um `http_response_code(403)`
  separado. Os testes do Q1 que esperam `Location: /admin/events`
  precisam mudar.
- R1 (`r1-flash-titulo-warning`): só textos de flash; sem conflito de
  lógica. O flash "Evento não encontrado!" deixa de existir aqui.
- N10 (`n10-pagina-id-invalido`): id inválido (texto) também pode
  responder 404 pelo mesmo `eventNotFound`.
- A8 da 4.1: mesma ideia (status corretos) aplicada ao login.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/o8-status-http`
(devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/fvp/4.2/exemplos/o8-status-http`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-o8 s50/fvp/4.2/exemplos/o8-status-http/projeto
git fetch fvp-o8 fvp/o8-status-http
git cherry-pick b54d2be
git remote remove fvp-o8
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/fvp/4.2/exemplos/o8-status-http/solucao.bundle \
    fvp/o8-status-http:fvp-o8
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick b54d2be
# opcional, limpa a ref
git branch -D fvp-o8
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/o8-status-http/solucao.patch
# só o diff, sem commit
git apply --3way s50/fvp/4.2/exemplos/o8-status-http/solucao.patch
```

### Se der conflito

```
# arquivos marcados "both modified"
git status
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
# ou: git am --continue
git cherry-pick --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `EventsController::eventNotFound` e os ramos `else` de
  `create`/`update`: Q1, R1, R10 mexem perto. Mantenha o
  `http_response_code(...)` antes de cada `render`.
- `EventsAccessTest.php` e `EventsControllerTest.php`: o teste antigo
  de 302 foi substituído; se o outro lado também o alterou, fique com
  a versão 404 daqui e acrescente as asserções do outro.

Depois de aplicar: rode os testes e o `./run phpcs` (sem schema).

## Armadilhas

- `http_response_code()` depois do `render`: o status não muda
  (cabeçalhos já enviados).
- Testar status no `EventsControllerTest`: em CLI ele não existe; o
  teste passaria sem testar nada.
- Guzzle sem `http_errors => false` lança `ClientException` em 404 e
  422 em vez de devolver a resposta.
- `throw new HTTPException(...)` para "não encontrado" dá 404, mas com
  stack trace na tela.
- O layout consome o flash ao renderizar: no teste de controller, para
  ver que não há flash, confira a ausência de `alert-danger` na página
  e não `$_SESSION['flash']` (que já foi apagado).
- 404 no `destroy`/`update` de id inexistente: o formulário que
  disparou o pedido fica para trás; a página 404 tem o link de volta.
