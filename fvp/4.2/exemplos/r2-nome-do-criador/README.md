# R2. Nome do criador no show/listagem (BelongsTo)

Pedido, como o professor faria na hora: "Mostre quem cadastrou o
Evento, nos detalhes e na listagem. Use a relação com o usuário."

Chance: média-alta. Tempo estimado: 10-15 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, R2.

Branch no clone: `fvp/r2-nome-do-criador`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                            |
|-----------|-----------------------------------------------------|
| `a7c3aa5` | `feat: Show event creator name in listing and show` |

## O que mudou

- `app/Models/Event.php`: método novo `creatorName(): string`. Ele
  chama `$this->user()->get()` (o `BelongsTo` que já existia) e
  devolve o `name` quando o resultado é `instanceof User`.
- `app/views/admin/events/show.phtml`: par
  `<dt>Criado por</dt>` / `<dd>` com
  `htmlspecialchars($event->creatorName())`, depois de "Vagas".
- `app/views/admin/events/index.phtml`: coluna "Criado por" entre
  "Vagas" e "Ações".
- Testes:
  - `EventTest::test_creator_name_should_return_the_name_of_the_creator`
    (dois admins, cada Evento devolve o nome do seu criador).
  - `EventsControllerTest`: `test_index_shows_creator_name` (dois
    criadores na mesma página), `test_show_renders_creator_name`
    (`Admin` do `setUp` no `<dd>`) e `test_show_escapes_creator_name`
    (nome com `<b>` sai escapado). Helper `createAdmin`.
  - `ListEventsCest`: cabeçalho "Criado por", nome do admin em cada
    linha e no show.

## Por que assim

- A relação já existe (`Event::user(): BelongsTo`, usada pela rubrica
  da 4.3). O cenário só a usa; não há SQL novo nem `JOIN` manual.
- `BelongsTo::get()` devolve `Model`, não `User`. Na view,
  `$event->user()->get()->name` funciona (é o `__get` do model); em
  `app/` o `phpstan` exige `instanceof User`. O método no model faz
  isso uma vez e as duas views só chamam `creatorName()`: regra num
  só lugar, como o catálogo recomenda.
- A forma direta, se o professor pedir exatamente ela:

```php
<dd class="col-sm-9">
    <?= htmlspecialchars($event->user()->get()->name) ?>
</dd>
```

- `htmlspecialchars` na saída: o nome vem do cadastro de usuário.
- N+1: na listagem, `creatorName()` faz uma consulta por linha (5 por
  página). É aceitável aqui, igual a `enrollmentsCount()`. Com muitas
  linhas, o certo seria um `JOIN` num método do model ou carregar os
  usuários de uma vez.
- `user_id` sem usuário é impossível (`user_id NOT NULL` e FK
  `fk_events_user`); o `''` do método só satisfaz o tipo.

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

Resultado obtido: Unit 160, Integration 48, Acceptance 23 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

No navegador (populate): `/admin/events/1` mostra "Criado por
Administrador"; a listagem tem a coluna com o mesmo nome em todas as
linhas (o populate tem um só admin).

## Relação com outros cenários

- N6 (`n6-badge-lotado-contador`) e R3 (`r3-helper-formatacao`)
  também mexem em `index.phtml` e `show.phtml`: conflito de texto,
  fácil de juntar (cada um mexe numa célula diferente).
- N3 (`n3-ordenar-por-data`) mexe no controller e no `Paginator`:
  sem conflito.
- "Só o dono edita e remove" (Q1 do catálogo) usa o mesmo `user_id`;
  a coluna deste cenário ajuda a ver de quem é cada Evento.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/r2-nome-do-criador`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/r2-nome-do-criador`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-r2 \
    s50/fvp/4.2/exemplos/r2-nome-do-criador/projeto
git fetch fvp-r2 fvp/r2-nome-do-criador
git cherry-pick a7c3aa5
git remote remove fvp-r2
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/r2-nome-do-criador/solucao.bundle \
    fvp/r2-nome-do-criador:fvp-r2
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick a7c3aa5
# opcional, limpa a ref
git branch -D fvp-r2
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/r2-nome-do-criador/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/r2-nome-do-criador/solucao.patch
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

- `index.phtml` (cabeçalho e linha da tabela) e `show.phtml` (lista
  `<dl>`): outros cenários acrescentam colunas ou itens no mesmo
  ponto. Fique com todos; confira que o número de `<th>` bate com o
  de `<td>`.
- `ListEventsCest` e `EventsControllerTest`: testes novos no mesmo
  trecho; mantenha os dois lados.
- `Event.php`: métodos novos depois de `user()`; mantenha todos.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- `$event->user()->get()->name` em código de `app/` sem `instanceof`:
  o `phpstan` nível 6 acusa propriedade indefinida em `Model`.
- `$event->user->name` (sem parênteses) também funciona pelo `__get`
  mágico, mas esconde a consulta; prefira a forma explícita.
- Esquecer o `<th>` e pôr só o `<td>`: a tabela desalinha.
- Usar `Auth::user()` (quem está logado) no lugar do criador: o nome
  certo vem de `user_id` do Evento.
- Sem `htmlspecialchars`: um nome com `<script>` executa na página.
