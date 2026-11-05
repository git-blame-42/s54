# O5. Admin vê e lista só os próprios Eventos

Pedido, como o professor faria na hora: "Cada Administrador deve ver,
na listagem, só os Eventos que ele criou, com a paginação contando só
os dele. Use a relação `events()` do usuário logado."

Chance: média-alta. Tempo estimado: 15-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, O5.

Branch no clone: `fvp/o5-admin-lista-so-proprios`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                             |
|-----------|------------------------------------------------------|
| `1b9fed5` | `feat: List and manage only the logged admin events` |

## O que mudou

- `app/Controllers/Admin/EventsController.php`:
  - `index`: `$owner->events()->paginate(page:, per_page:, route:)`
    no lugar de `Event::paginate(...)`. O `HasMany::paginate` do core
    (`core/Database/ActiveRecord/HasMany.php:44-55`)
    monta o `Paginator` com `conditions: ['user_id' => id]`, então a
    contagem ("Mostrando 1 - 5 de N") e as páginas são só do logado.
    Sem usuário logado (só se o middleware falhar), vai para `/login`.
  - `new`/`create`: o método privado `newEvent()` usa
    `currentUser()?->events()->new()`, que já preenche `user_id`
    (antes: `new Event()` + `user_id` à mão).
  - `findEvent` (usado por `show`, `edit`, `update` e `destroy`):
    `currentUser()?->events()->findById($id)` + `instanceof Event`.
    O Evento de outro admin vira `null` e cai em "Evento não
    encontrado!" com redirect para `/admin/events`.
- `database/Populate/populate.php`: segundo Administrador
  `admin2@example.com` (senha `123456`) com 3 Eventos próprios
  (títulos terminam em "(Administrador 2)"). O `admin@example.com`
  continua com os 16 Eventos, as 14 Inscrições e o Evento 1 lotado.
  A criação dos Eventos virou uma closure `$createEvent($dono, $linha)`
  que usa `$owner->events()->new([...])`.
- Testes:
  - `EventsControllerTest` (7 novos, com um 2º admin):
    listagem só do logado; paginação com 7 próprios e 4 alheios
    ("1 - 5 de 7", "6 - 7 de 7", sem página 3); o outro admin vê só
    os dele; sem login vai para `/login`; `create` grava o logado
    como dono; `show`/`edit` de Evento alheio redirecionam;
    `update`/`destroy` de Evento alheio não mudam nada.
  - `EventsAccessTest`: por HTTP, o 2º admin não vê o Evento na
    listagem e o `show` dele redireciona. Os testes existentes
    seguem iguais.
  - `ListEventsCest::listShowsOnlyTheLoggedAdminEvents`: Evento de
    outro admin não aparece, a contagem continua 16 e abrir a URL
    dele mostra "Evento não encontrado!". O `seedEvents` do
    `BaseEventsCest` cria todos os Eventos com o admin logado, então
    os Cests antigos não mudaram.

## Por que assim

- `HasMany::paginate` já existe no core e aceita `page`, `per_page` e
  `route`; não é preciso SQL novo nem mexer no `Paginator`.
- `show` também ficou restrito (ideia do Q1): se a listagem esconde
  os Eventos alheios, abrir a URL deles não deveria funcionar. A
  resposta é "não encontrado", sem revelar que o Evento existe. Para
  deixar o `show` aberto a todo admin, troque só no `show` o
  `findEvent` por `Event::findById(...)`.
- `instanceof Event`: `HasMany::findById`/`new` devolvem `?Model` /
  `Model`; o PHPStan nível 6 exige a conversão para `?Event`/`Event`.
- O 2º admin ganha Eventos novos em vez de "roubar" Eventos do
  primeiro: os números do populate do `admin@example.com` (16
  Eventos, 4 páginas, Evento 1 lotado) continuam valendo.

## Como verificar

```
./run up -d
# refaz o schema (o populate roda migrate()) e popula
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium
./run test:browser
```

Resultado obtido: Unit 159 testes, Integration 53, Acceptance 24 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros; `db:populate` sem erro
(16 Eventos do Administrador, 3 do Administrador 2, 14 Inscrições).

No navegador: com `admin@example.com` a listagem mostra
"Mostrando 1 - 5 de 16" e nenhum Evento "(Administrador 2)"; com
`admin2@example.com` (senha `123456`), só os 3 dele ("1 - 3 de 3").
Abrir `/admin/events/1` como `admin2` volta para a listagem com
"Evento não encontrado!".

## Relação com outros cenários

- Q1 (`q1-so-dono-edita-remove`): mesmo `findEvent` com
  `events()->findById` + `instanceof`. O Q1 restringe só edit,
  update e destroy (pode deixar o `show` amplo); este restringe
  também a listagem e o `show`. Aplicando os dois, o `findEvent` é o
  mesmo: fique com uma cópia. Se o Q1 tiver mensagem própria ("Você
  não pode alterar este Evento!"), ela deixa de aparecer, porque o
  Evento alheio já não é encontrado.
- T1 (`t1-populate-campo-eventos-admin`): também cria um 2º admin,
  com o **mesmo** e-mail (`admin2@example.com`). Aplicando os dois sem
  cuidado, o populate cria o mesmo usuário duas vezes: o segundo
  `save()` falha no `uniqueness` do e-mail (e, se fosse gravado, no
  `UNIQUE` do banco). Fique com um só bloco de `admin2` (o deste
  cenário, que já tem os 3 Eventos dele) ou troque o e-mail de um dos
  dois. Lembre também que cada admin só vê os Eventos dos quais é
  dono: Eventos novos do T1 criados com `$admin` aparecem para o
  `admin@example.com`.
- N1 a N4 (filtro, busca, ordenação, `per_page`) também trocam o
  `Event::paginate` do `index`: as condições deles precisam somar o
  `user_id` do logado (o `HasMany::paginate` não aceita mais
  condições).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/o5-admin-lista-so-proprios`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/o5-admin-lista-so-proprios`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-o5 \
    s50/fvp/4.2/exemplos/o5-admin-lista-so-proprios/projeto
git fetch fvp-o5 fvp/o5-admin-lista-so-proprios
git cherry-pick 1b9fed5
git remote remove fvp-o5
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/o5-admin-lista-so-proprios/solucao.bundle \
    fvp/o5-admin-lista-so-proprios:fvp-o5
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 1b9fed5
# opcional, limpa a ref
git branch -D fvp-o5
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/o5-admin-lista-so-proprios/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/o5-admin-lista-so-proprios/solucao.patch
```

Depois de aplicar: `./run db:populate` (o populate mudou), os testes
e o `./run phpcs`.

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

- `EventsController::index`: N1-N4 e P11 também trocam o
  `Event::paginate`. Para somar condições ao dono, monte o
  `Paginator` à mão com `conditions: ['user_id' => $owner->id, ...]`.
- `EventsController::findEvent`: Q1 faz a mesma troca; fique com uma.
- `populate.php`: T1 e Q1 também criam um 2º admin e mexem no laço
  de Eventos; aqui o laço virou a closure `$createEvent`. Com o T1, o
  e-mail `admin2@example.com` é o mesmo: mantenha um só usuário.

## Armadilhas

- `HasMany::paginate` usa o nome `per_page` (com `_`) no argumento
  nomeado; `perPage:` dá erro de parâmetro desconhecido.
- `currentUser()` é `?User`: chamar `->events()` direto faz o
  PHPStan reclamar; trate o `null` (aqui, redirect para `/login`) ou
  use `?->`.
- Sem `instanceof Event`, o PHPStan acusa o retorno `?Model`.
- Só a listagem filtrada não basta: `show`/`edit` por URL ainda
  abririam o Evento alheio se o `findEvent` usasse `Event::findById`.
- Os Cests não pegam a mudança sozinhos: o `seedEvents` cria tudo com
  o admin logado. É preciso um cenário com Evento de outro admin.
- O 2º admin sem Eventos próprios mostra "Nenhum evento cadastrado."
  no navegador: crie Eventos para ele no populate.
