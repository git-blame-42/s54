# O1. Rota RESTful nova com página (lotados)

Pedido, como o professor faria na hora: "Crie uma página na área
administrativa que liste só os Eventos lotados, em
`GET /admin/events/full`, com link na listagem."

Chance: média-alta. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, O1.

Branch no clone: `fvp/o1-rota-eventos-lotados`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                     |
|-----------|----------------------------------------------|
| `229fa79` | `feat: List full events in admin area`       |

## O que mudou

- `config/routes.php`: `Route::get('/admin/events/full', ...)` com nome
  `admin.events.full`, logo depois de `/admin/events/new` e ANTES de
  `/admin/events/{id}`. O comentário do bloco cita `new` e `full`.
- `app/Models/Event.php`: `Event::full(): array` (`@return
  array<static>`) filtra `Event::all()` pelos Eventos com
  `availableSpots() === 0` e reindexa com `array_values`.
- `app/Controllers/Admin/EventsController.php`: ação `full()` chama
  `Event::full()`, define `$title = 'Eventos lotados'` e renderiza
  `admin/events/full` com `compact('events', 'title')`.
- `app/views/admin/events/full.phtml` (novo): a tabela da listagem
  (título, tipo, data, local, vagas, "Ver"), sem paginador e sem
  editar/remover; "Nenhum evento lotado." quando vazia; botão Voltar.
- `app/views/admin/events/index.phtml`: botão "Eventos lotados" ao
  lado de "Novo Evento".
- Testes:
  - `EventTest` (Unit), lotado/com vagas/vazio:
    - `test_full_should_return_only_events_without_available_spots`
    - `test_full_should_be_empty_without_full_events`
  - `EventsControllerTest` (Integration):
    - `test_full_lists_only_full_events`
    - `test_full_without_full_events_shows_empty_message`
    - `test_index_links_to_full_events`
  - `ListFullEventsCest` (Acceptance, novo): clica no link na
    listagem, vê só o Evento 01 (3/3) e volta.

## Por que assim

- Regra no model: "lotado" é regra de negócio do Evento, então mora
  em `Event::full()`, e o controller fica fino (uma linha). Se o
  professor aceitar no controller, o mesmo `array_filter` cabe na
  ação; o model é a resposta segura para "regra/SQL no model".
- O catálogo (passo 3) põe o `array_filter` direto na ação
  `full()`; funciona igual. Aqui ele foi para `Event::full()`.
- O passo 5 do catálogo (teste de acesso por HTTP) ficou no commit do
  S2, empilhado sobre este, para mostrar o que só ele pega.
- Sem SQL novo: reutiliza `availableSpots()`, a mesma conta da tela
  de detalhes e da validação de capacidade. Um `SELECT` com
  subconsulta em `event_user` seria mais rápido, mas duplicaria a
  regra; com 16 Eventos não faz diferença.
- Rota fixa antes de `{id}`, como `new` (o Router casa na ordem).
- Nome no padrão `admin.events.*`; está dentro do grupo
  `auth` + `admin`, então herda os middlewares.
- `$title` no `compact`: o layout usa no `<h1>` (o `<title>` é fixo).
- Saída com `htmlspecialchars`; tipo pelo rótulo de `Event::KINDS`.

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

Resultado obtido: Unit 161, Integration 48, Acceptance 24 (base 159 /
45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Na tela (populate): logue como `admin@example.com` / `123456`, abra
`/admin/events` e clique em "Eventos lotados". Aparece só "Semana
Acadêmica de Informática 2026" (3/3). Sem porta no host:

```
docker compose exec web curl -s -c /tmp/c -d \
    'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c \
    http://localhost/admin/events/full
```

## Relação com outros cenários

- S2 (`s2-teste-acesso-rota-nova`) é empilhado sobre este: acrescenta
  o teste de acesso por HTTP da rota nova. Este commit não tem teste
  de acesso de propósito; o S2 mostra por que ele é necessário.
- P8 (`p8-exportar-csv`) cria outra rota fixa
  (`/admin/events/export`) no mesmo ponto de `config/routes.php` e
  outro botão no mesmo ponto de `index.phtml`.
- O3 e O4 mexem no mesmo controller e nos mesmos testes, em outros
  métodos.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/o1-rota-eventos-lotados
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/o1-rota-eventos-lotados`. Cherry-pick cria
commit novo (SHA diferente); preserva conteúdo e mensagem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-o1 <caminho>/projeto
git fetch fvp-o1 fvp/o1-rota-eventos-lotados
git cherry-pick 229fa79
git remote remove fvp-o1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle \
    fvp/o1-rota-eventos-lotados:fvp-o1
git cherry-pick 229fa79
# opcional, limpa a ref
git branch -D fvp-o1
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

- `config/routes.php`: outro cenário com rota fixa (P8) no mesmo
  ponto. Fique com as duas linhas, ambas antes de `{id}`.
- `app/views/admin/events/index.phtml`: outro botão no topo (P8) ou
  filtro/busca (N1, N2). Fique com os dois.
- `EventsController.php`, `EventTest.php`, `EventsControllerTest.php`:
  métodos novos no mesmo ponto; mantenha os dois blocos.

Depois de aplicar: rode `./run phpcs` e os testes de novo.

## Armadilhas

- Rota depois de `/admin/events/{id}`: `full` vira id, `findById(0)`
  devolve `null` e a resposta é 302 para `/admin/events` com
  "Evento não encontrado!". O teste de controller NÃO pega isso: o
  `ControllerTestCase` chama a ação direto, sem Router. Só o teste
  por HTTP com admin logado pega (cenário S2).
- Esquecer `$title` no `compact`: o layout usa `$title` no `<h1>` e
  quebra (o `<title>` é fixo, não depende dele).
- `array_filter` mantém as chaves: sem `array_values`, `$events[0]`
  pode não existir (o teste unitário usa `[0]`).
- `availableSpots()` usa `max(0, ...)`: um Evento com mais Inscrições
  que vagas (dado antigo) também conta como lotado. É o desejado.
- A tabela de ligação é `event_user` (model `Enrollment`), não
  `enrollments`.
