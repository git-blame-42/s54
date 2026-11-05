# O7. `Route::patch` no core, usado numa ação parcial (status)

Pedido, como o professor faria na hora: "O framework só tem
`get/post/put/delete`. Acrescente `patch` e use numa alteração
parcial: cancelar e encerrar o Evento mudam só a situação."

Chance: média-baixa. Tempo estimado: 15-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, O7.

Branch no clone: `fvp/o7-route-patch`, empilhada sobre
`fvp/p5-status-cancelar-encerrar` (cenário P5, que parte de `main`
`f31006b`). Sem as ações de status do P5 não há o que trocar para
PATCH.

Commits (`main..fvp/o7-route-patch`):

- `8c64e03` `feat: Add Event status with cancel and finish actions`
  (base, P5)
- `3fae399` `feat: Add Route::patch and use it for event cancel and
  finish` (este cenário)

## O que mudou

Só o segundo commit (`3fae399`):

- `core/Router/Route.php`: `Route::patch(string $uri, $action)`, ao
  lado de `put()` e `delete()`, mesmo PHPDoc dos vizinhos; cria a
  `Route` com método `PATCH` e registra no `Router`.
- `core/Http/Request.php`: sem mudança. A linha
  `$_REQUEST['_method'] ?? $_SERVER['REQUEST_METHOD']` já aceita
  qualquer texto, então um POST com `_method=PATCH` chega ao
  `Router` como PATCH, e o `Route::match` compara o método.
- `config/routes.php`: `admin.events.cancel` e `admin.events.finish`
  passam de `Route::put` para `Route::patch` (mesmos caminhos e
  nomes).
- `app/views/admin/events/index.phtml` e `show.phtml`: os forms de
  "Cancelar Evento" e "Encerrar Evento" mandam `_method=PATCH`. O
  form de edição continua com PUT.
- Testes:
  - `RouteTest` (Unit, 2): `Route::patch` registra uma rota `PATCH`
    (cópia do teste do `put`); rota PATCH casa só com PATCH (não com
    PUT nem POST).
  - `RouterTest` (Unit, 2): POST + `_method=PATCH` despacha para a
    rota PATCH (uma rota PUT no mesmo caminho é ignorada); PUT numa
    rota só PATCH dá `HTTPException` (404).
  - `ControllerTestCase`: helper `patch()` (como `put()`/`delete()`).
  - `EventsControllerTest`: as chamadas de `cancel`/`finish` usam
    `patch()`; o show confere `_method` `PATCH` e não `PUT`.
  - `EventsAccessTest` (Integration, 2 novos): `eventRoutes()` e
    `methodOverrideRoutes()` com `PATCH` para cancel/finish; PUT em
    `/admin/events/{id}/cancel` agora dá 404 e não muda nada; um
    PATCH nativo (`$client->patch(...)`) encerra o Evento.
  - `CancelEventCest` do P5: sem mudança, continua passando (clica
    nos botões, que agora mandam PATCH).

## Por que assim

- PUT é substituição do recurso inteiro (é o `update` do form de
  edição); mudar só a situação é alteração parcial, que é o caso do
  PATCH no REST.
- Mudança mínima no core: um método estático igual aos quatro que já
  existem, com teste em `tests/Unit/Core`. O `Request` não precisou
  mudar porque o `_method` já é genérico.
- As ações `cancel()`/`finish()` do P5 não mudaram: continuam no
  model (`save()` com `validates()`); só o verbo HTTP trocou.
- Nomes de rota mantidos: views e testes que usam `route(...)` não
  quebram.
- O catálogo sugere uma rota única `PATCH /admin/events/{id}/status`
  (`updateStatus`). Aqui foram mantidas as duas rotas do P5
  (`/cancel` e `/finish`), trocando só o verbo, como foi pedido.

## Como verificar

```
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 172, Integration 65, Acceptance 27 (P5: 168 /
63 / 27; `main`: 159 / 45 / 23). PHPCS e PHPStan (que cobre `core/`)
sem erros.

Na tela (populate): logue como admin, abra um Evento agendado e
clique em "Cancelar Evento". No DevTools (aba Network), o POST leva
`_method=PATCH`; a situação vira "Cancelado".

## Relação com outros cenários

- P5 (`p5-status-cancelar-encerrar`): base. Traz a coluna `status`,
  as ações `cancel`/`finish` e o Cest.
- P1 (`p1-codigo-checkin-aleatorio`): outro campo que o form não
  edita; se um dia o código puder ser regenerado, a ação seria outro
  `PATCH` (alteração parcial), não PUT.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/o7-route-patch
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é `s50/fvp/4.2/exemplos/o7-route-patch`.
Cherry-pick cria commits novos (SHAs diferentes); preserva conteúdo e
mensagem.

Primeiro decida: o alvo já tem o P5?

- Não tem: os dois commits (`8c64e03` e `3fae399`) ou o intervalo
  `main..fvp/o7-route-patch`.
- Já tem (aplicou o P5 antes): só o topo, `3fae399`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-o7 <caminho>/projeto
git fetch fvp-o7 main fvp/o7-route-patch
# alvo sem P5: os dois, por intervalo
git cherry-pick fvp-o7/main..fvp-o7/fvp/o7-route-patch
# alvo que já tem P5: só o topo
git cherry-pick 3fae399
git remote remove fvp-o7
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/o7-route-patch:fvp-o7
# alvo sem P5
git cherry-pick 8c64e03 3fae399
# alvo que já tem P5
git cherry-pick 3fae399
# opcional, limpa a ref
git branch -D fvp-o7
```

### 3. A partir do `solucao.patch` (`git am`)

O patch é uma série com os dois commits.

```
# alvo sem P5: aplica os dois
git am <caminho>/solucao.patch
# se o 1º (P5) já existe e conflitar, pule-o
git am --skip
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

- `core/Router/Route.php`: outro cenário que mexe no core do roteador
  (raro). O método novo é independente; fique com os dois.
- `config/routes.php`: rotas novas no grupo `admin` (O1, P8). Fique
  com todas; as de status com `Route::patch`.
- `index.phtml`/`show.phtml`: botões novos perto dos de status (M10,
  R4 com `data-confirm`). Mantenha os dois; o `_method` dos forms de
  status é `PATCH`.
- `EventsAccessTest.php` e `EventsControllerTest.php`: rotas e testes
  novos nos mesmos pontos; mantenha todos.
- Se o alvo não tem P5 e já mudou o schema, o conflito é do commit do
  P5; resolva como no README dele e rode `./run db:populate`.

Depois de aplicar: `./run phpcs`, `./run phpstan` (cobre o core) e os
testes.

## Armadilhas

- Trocar a rota para `patch` e esquecer o `_method` dos forms: o
  form manda PUT, nenhuma rota casa e a resposta é 404 (o teste de
  acesso `test_cancel_with_put_should_no_longer_match_the_route`
  mostra isso).
- O `_method` é comparado como veio: `value="patch"` (minúsculo) não
  casa com `PATCH`.
- Form HTML só faz GET/POST; PATCH só via `_method` (ou `fetch` com
  `method: 'PATCH'`).
- `core/` entra no phpstan nível 6: copie o PHPDoc dos vizinhos
  (`@param mixed[] $action`, `@return Route`).
- O `ControllerTestCase` não passa pelo Router: o `patch()` dele só
  chama a ação. O que prova o verbo é o teste por HTTP e o
  `RouterTest`.
- Rotas novas em `eventRoutes()`/`methodOverrideRoutes()` precisam do
  verbo novo, senão os testes de acesso viram 404 em vez de 302.
