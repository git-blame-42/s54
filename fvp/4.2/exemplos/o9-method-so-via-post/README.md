# O9. `_method` só via POST (GET não pode remover Evento)

Pedido, como o professor faria na hora: "GET nunca pode alterar
dados. Hoje `GET /admin/events/16?_method=DELETE` remove o Evento.
Corrija no framework: o `_method` só vale em formulário POST."

Chance: média. Tempo estimado: 15-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, O9.

Branch no clone: `fvp/o9-method-so-via-post`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                            |
|-----------|-----------------------------------------------------|
| `7b2351f` | `fix: Accept _method override only from POST body` |

## O problema (conferido na base)

`core/Http/Request.php:18` fazia:

```php
$this->method = $_REQUEST['_method'] ?? $_SERVER['REQUEST_METHOD'];
```

`$_REQUEST` junta query string e corpo. Com o admin logado:

```
# no clone (sem porta no host), de dentro do contêiner web
docker compose exec -T web curl -s -b /tmp/c \
    -o /dev/null -w '%{http_code}\n' \
    'http://localhost/admin/events/16?_method=DELETE'
```

Resposta 302 para `/admin/events` com "Evento removido com
sucesso!". Um link, um `<img src="...?_method=DELETE">` numa página
qualquer ou o prefetch do navegador remove Eventos (CSRF via GET).

## O que mudou

- `core/Http/Request.php`:
  - constante `OVERRIDABLE_METHODS = ['PUT', 'DELETE']` (os únicos
    métodos além de GET/POST que o Router conhece; não há
    `Route::patch`);
  - método privado `resolveMethod()`: parte de
    `$_SERVER['REQUEST_METHOD']`; só se ele for `POST` e
    `$_POST['_method']` for string, troca pelo valor em maiúsculas,
    e só se estiver em `OVERRIDABLE_METHODS`. Qualquer outro caso
    devolve o método real.
  - `getParams()`/`getParam()` continuam lendo `$_REQUEST` (nada
    muda para os controllers).
- `tests/Unit/Core/Http/RequestTest.php` (6 testes novos):
  - GET com `_method=DELETE` continua GET;
  - POST com `_method=DELETE` no corpo vira DELETE;
  - POST com `PUT`/`put`/`delete` vira PUT/DELETE;
  - POST com `_method` só na query string continua POST;
  - valores inválidos ignorados (`GET`, `HEAD`, `OPTIONS`, `TRACE`,
    `PATCH`, `FOO`, vazio, array);
  - PUT/DELETE reais continuam como estão (um PUT real com
    `_method=DELETE` continua PUT).
  - o `tearDown` passou a limpar `$_POST` e `$_GET`.
- `tests/Integration/Access/EventsAccessTest.php` (4 testes novos,
  HTTP de verdade com admin logado):
  - `GET /admin/events/{id}?_method=DELETE`: 200 (rota show) e o
    Evento continua no banco;
  - `GET ...?_method=PUT` com dados válidos na query: 200 e o título
    não muda;
  - `POST /admin/events/{id}?_method=DELETE` sem `_method` no corpo:
    404 (não há rota POST nesse caminho; o Router lança
    `HTTPException` 404) e o Evento continua;
  - `POST` com `_method=DELETE` no corpo (o formulário "Remover")
    continua removendo: 302 para `/admin/events`.

## Por que assim

- A correção fica no core, num lugar só: todas as rotas PUT/DELETE
  (Eventos e as que vierem) ficam protegidas. Corrigir no controller
  (`if ($_SERVER['REQUEST_METHOD'] !== 'POST')` no `destroy`) deixaria
  o `update` e as rotas futuras abertas.
- `$_POST` e não `$_REQUEST`: o campo oculto `_method` dos formulários
  (`_form.phtml` e "Remover" em `index.phtml`) vai no corpo; na query
  string ele não tem uso legítimo.
- Lista fechada (`PUT`, `DELETE`): `_method=GET` num POST não pode
  "rebaixar" a requisição, e valores inventados (`FOO`) não viram
  método. PATCH fica de fora porque o Router não tem rota PATCH; se
  um dia tiver, basta acrescentar à constante.
- `is_string`: `_method[]=DELETE` chega como array; sem a checagem,
  `strtoupper` daria `TypeError` (página 500).
- Os formulários e os Cests não mudam: eles já mandam POST com
  `_method` no corpo. Os testes de acesso antigos mandam PUT/DELETE
  reais pelo Guzzle e continuam verdes.

## Como verificar

```
./run up -d db php php_test web
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 165, Integration 49, Acceptance 23 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros (o `phpstan` cobre
`core/`). Schema não mudou. Sem a correção, 3 testes unit e 3 de
acesso novos falham (conferido com `git stash` do `Request.php`).

No terminal, admin logado (cookie em `/tmp/c`, ver briefing):

```
docker compose exec -T web curl -s -b /tmp/c \
    -o /dev/null -w '%{http_code}\n' \
    'http://localhost/admin/events/16?_method=DELETE'
# 200 (abre o show; antes: 302 e Evento removido)
docker compose exec -T web curl -s -b /tmp/c -X POST \
    -o /dev/null -w '%{http_code}\n' \
    'http://localhost/admin/events/16?_method=DELETE'
# 404
```

No navegador, o botão "Remover" da listagem continua funcionando.

## Relação com outros cenários

- Token CSRF nos formulários (F6 da lista de lacunas, variante de
  A15 da 4.1): complementa este. Aqui o GET deixa de alterar dados;
  o token protege os POST. Os dois juntos fecham o CSRF.
- R4 (confirmação JS antes de remover): é só interface; não protege
  contra o GET com `_method`. Este cenário é a correção de verdade.
- Qualquer cenário que crie rota PUT/DELETE nova (ex.: P2 inscrição)
  herda a proteção sem mudar nada.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/o9-method-so-via-post`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/o9-method-so-via-post`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-o9 \
    s50/fvp/4.2/exemplos/o9-method-so-via-post/projeto
git fetch fvp-o9 fvp/o9-method-so-via-post
git cherry-pick 7b2351f
git remote remove fvp-o9
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/o9-method-so-via-post/solucao.bundle \
    fvp/o9-method-so-via-post:fvp-o9
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 7b2351f
# opcional, limpa a ref
git branch -D fvp-o9
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/o9-method-so-via-post/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/o9-method-so-via-post/solucao.patch
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

- `core/Http/Request.php`: pouca gente mexe no core; se alguém mexeu
  no construtor (ex.: ler JSON do corpo), mantenha a linha
  `$this->method = $this->resolveMethod();` e o resto do outro lado.
- `RequestTest.php` e `EventsAccessTest.php`: testes novos no mesmo
  trecho; mantenha todos (o `tearDown` deste limpa `$_POST`/`$_GET`).

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- Trocar `$_REQUEST` por `$_POST` sem checar o método real: um PUT
  real com `_method=DELETE` no corpo viraria DELETE. Só POST pode ser
  sobrescrito.
- Aceitar qualquer valor: `_method=GET` num POST faria um formulário
  cair numa rota GET; `_method=FOO` dá 404 confuso. Lista fechada.
- Esquecer o `strtoupper`: `_method=delete` deixaria de casar com a
  rota (`Route` compara com `'DELETE'`).
- Testar só com o teste de controller: o `ControllerTestCase` chama a
  ação direto, sem Router nem `Request::getMethod()`; ele não pega
  essa falha. O teste que conta é o unit do `Request` e o de acesso
  por HTTP.
- `$_POST` sobra entre testes unit: limpe no `tearDown`, senão um
  teste "GET" herda o `_method` do anterior.
- Confirmação JS (`confirm()`) não resolve: o ataque não passa pelo
  botão.
