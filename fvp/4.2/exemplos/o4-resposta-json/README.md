# O4. Resposta JSON da listagem

Pedido, como o professor faria na hora: "A listagem de Eventos do
admin deve responder em JSON quando o cliente pedir
(`Accept: application/json`), para a tela consumir via Ajax."

Chance: baixa. Tempo estimado: 20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, O4.

Branch no clone: `fvp/o4-resposta-json`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                     |
|-----------|----------------------------------------------|
| `306b19d` | `feat: Return JSON for admin events listing` |

## O que mudou

- `app/Controllers/Admin/EventsController.php`: a ação `index`
  continua uma só. Depois de montar `$paginator` e `$events`, se
  `$request->acceptJson()` for verdadeiro chama
  `$this->renderJson('admin/events/index', compact('events',
  'paginator'))` e faz `return`; senão, o `render` HTML de antes.
- `app/views/admin/events/index.json.php` (novo): preenche
  `$json['events']` (id, title, kind, modality, starts_on, location,
  capacity, enrollments, available_spots), `$json['page']` e
  `$json['total']` (total de Eventos, não só da página).
- `EventsControllerTest` (Integration), três testes novos:
  - `test_index_returns_json_when_client_accepts_json` (7 Eventos:
    página 1, total 7, 5 Eventos, campos do primeiro, sem `<table`);
  - `test_index_returns_json_for_the_requested_page` (página 2 com
    Evento 6 e Evento 7);
  - `test_index_renders_html_without_json_accept_header`.
- `EventsAccessTest` (Integration), dois testes novos por HTTP:
  - `test_events_index_as_json_should_redirect_to_login_without_session`
    (302 para `/login`: o JSON não fura o middleware);
  - `test_admin_should_get_events_index_as_json` (200,
    `Content-Type` `application/json`, título e total).
- Sem Cest: não há tela nova; a listagem HTML continua coberta pelos
  Cests existentes.

## Por que assim

- `renderJson` já existe em `core/Http/Controllers/Controller.php`:
  monta `app/views/<view>.json.php`, inicializa `$json = []`, manda
  o `Content-Type` e faz o `json_encode`. Não foi preciso criá-lo; a
  view `.json.php` é a convenção do framework.
- Uma rota, duas representações, escolhidas pelo header `Accept`:
  continua RESTful (`GET /admin/events`), sem `/admin/events.json`
  nem `/api/...`.
- A view lista os campos um a um: não vaza nada que não foi pedido
  e números saem como número (`(int) capacity`).
- `page` e `total` vêm do `Paginator` (`getPage()`,
  `totalOfRegisters()`): o cliente sabe se há mais páginas.
- Autorização continua no middleware `admin`; o teste de acesso
  prova.
- O catálogo mostra só `events` (id, title, starts_on,
  available_spots); aqui seguiu-se o pedido com `page` e `total`.

## Como verificar

```
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 159, Integration 50, Acceptance 23 (base 159 /
45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Pelo terminal (populate), de dentro da rede do compose:

```
docker compose exec web curl -s -c /tmp/c -d \
    'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c \
    -H 'Accept: application/json' http://localhost/admin/events
```

Saída obtida (início):

```
{"events":[{"id":1,"title":"Semana Acadêmica ...",
"kind":"academic_week",...,"capacity":3,"enrollments":3,
"available_spots":0},...],"page":1,"total":16}
```

No navegador, logado como admin, no console do DevTools:

```js
fetch('/admin/events', { headers: { Accept: 'application/json' } })
  .then(r => r.json())
  .then(console.log)
```

## Relação com outros cenários

- Modelo: cenário E1 da 4.1 (`s50/fvp/4.1/exemplos/`
  `e1-resposta-json-listagem`), o mesmo padrão para a listagem de
  usuários.
- P8 (`p8-exportar-csv`): outra resposta sem layout, mas por rota
  própria e saída crua.
- O1, P8 e O3 mexem nos mesmos arquivos de teste, em outros pontos.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/o4-resposta-json
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é `s50/fvp/4.2/exemplos/o4-resposta-json`.
Cherry-pick cria commit novo (SHA diferente); preserva conteúdo e
mensagem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-o4 <caminho>/projeto
git fetch fvp-o4 fvp/o4-resposta-json
git cherry-pick 306b19d
git remote remove fvp-o4
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/o4-resposta-json:fvp-o4
git cherry-pick 306b19d
# opcional, limpa a ref
git branch -D fvp-o4
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am <caminho>/solucao.patch
# só o diff, sem commit
git apply --3way <caminho>/solucao.patch
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

Arquivos que costumam conflitar e por quê:

- `EventsController::index`: filtro, busca, ordenação ou `per_page`
  (N1, N3 e N4) mudam o `paginate`. Fique com a versão do alvo e ponha o
  `if ($request->acceptJson())` logo depois de `$events`, passando
  também as variáveis novas que a view JSON precisar.
- `EventsControllerTest.php` e `EventsAccessTest.php`: testes novos
  no mesmo ponto; mantenha os dois blocos.

Depois de aplicar: rode `./run phpcs` e os testes de novo.

## Armadilhas

- `acceptJson()` compara igualdade exata com `application/json`:
  `Accept: application/json, */*` (padrão de alguns clientes) cai no
  HTML. Mande o header exato.
- Sem o `return` depois do `renderJson`, a ação segue e cola o HTML
  depois do JSON.
- O `ControllerTestCase` limpa `$_SERVER['HTTP_ACCEPT']` no
  `tearDown`; se o seu não limpar, os testes seguintes recebem JSON.
- O header do framework sai como `chartset=utf-8` (erro de grafia em
  `Controller::renderJson`); o teste confere só o começo
  (`application/json`). Não é parte do cenário.
- Não use `json_encode($events)` direto: `Model` tem propriedades
  protegidas e sairia `{}`; e serializar tudo pode vazar campos.
- `$json` já vem inicializado pelo `renderJson`; a view só preenche.
