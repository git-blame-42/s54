# P11. Soft delete (arquivar Evento)

Pedido, como o professor faria na hora: "Em vez de remover, arquive o
Evento (`deleted_at`). Arquivados não aparecem na listagem, ficam numa
página própria e podem ser restaurados. As Inscrições continuam."

Chance: baixa. Tempo estimado: 30-40 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P11.

Branch no clone: `fvp/p11-soft-delete-arquivar`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

Commits:

- `6011e4c` `feat: Support null conditions as IS NULL in Paginator`
- `91408e0` `feat: Archive events instead of deleting them`

## O que mudou

- `database/schema.sql`: `deleted_at DATETIME NULL` em `events`
  (`NULL` = ativo).
- `lib/Paginator.php` (1º commit): condição com valor `null` vira
  `coluna IS NULL` e não é ligada no `bindValue`. Antes virava
  `coluna = NULL`, que nunca é verdadeiro; nada dependia disso.
- `app/Models/Event.php`:
  - `@property string|null $deleted_at` e a chave em `$columns`;
  - `isArchived()`, `archive()` (grava `deleted_at` com
    `update()`; erro em `errors('base')` se for registro novo ou já
    arquivado) e `restore()` (volta a `NULL`);
  - `Event::active()` e `Event::archived()` com SQL próprio
    (`WHERE deleted_at IS NULL` / `IS NOT NULL`, `ORDER BY`),
    `@return array<static>`;
  - `Event::paginateActive($page, $perPage, $route)`: monta o
    `Paginator` com `conditions: ['deleted_at' => null]` (o
    `Model::paginate` do core não repassa condições).
- `config/routes.php`: `GET /admin/events/archived` (antes de
  `{id}`) e `PUT /admin/events/{id}/restore`.
- `EventsController`: `index` usa `paginateActive`; `destroy` chama
  `archive()` ("Evento arquivado com sucesso!"); ações novas
  `archived` (título "Eventos arquivados") e `restore` ("Evento
  restaurado com sucesso!", volta para os arquivados).
- Views: `index.phtml` ganha o botão "Arquivados" e o "Remover" vira
  "Arquivar"; `archived.phtml` (nova) lista título, data, Inscrições,
  "Arquivado em" e o botão "Restaurar" (`_method=PUT`); `show.phtml`
  avisa "Evento arquivado em ...".
- Testes:
  - `EventTest`: 9 testes novos (arquivar, `active()`/`archived()`,
    Inscrições mantidas, arquivar duas vezes, registro novo,
    restaurar, restaurar ativo, `save()` mantém arquivado,
    `paginateActive` pula arquivados).
  - `EventsControllerTest`: os 2 testes de `destroy` viraram de
    arquivamento; 8 novos para `archived`, `restore`, aviso no `show`
    e botões da listagem.
  - `EventsAccessTest`: `archived` e `restore` no provider de rotas
    (não autenticado e Participante barrados), Participante não
    restaura, Administrador arquiva e vê a página de arquivados.
  - `DeleteEventCest` reescrito: arquivar some da listagem e fica no
    banco; Evento com Inscrições é arquivado e as mantém; arquivar,
    abrir "Arquivados" e restaurar. `ListEventsCest` procura
    "Arquivar" no lugar de "Remover".

## Por que assim

- `deleted_at DATETIME NULL` guarda quando foi arquivado e dispensa
  um segundo campo. O custo: `IS NULL` não é igualdade, e o
  `Paginator` só fazia igualdade. A extensão em `lib/Paginator.php` é
  pequena, retrocompatível e vale para qualquer model; o `core/` não
  mudou. Alternativa sem tocar em `lib/`: um flag
  `archived TINYINT(1) NOT NULL DEFAULT 0` e
  `conditions: ['archived' => 0]` (forma do catálogo).
- SQL só no model (`active`, `archived`, `paginateActive`); o
  controller continua fino.
- `findById` e `all()` do core continuam vendo arquivados: o `show`
  de um arquivado funciona (com aviso) e o `restore` acha o Evento.
- Arquivar com Inscrições é permitido: nada é apagado, e a regra P03
  (não remover com Inscrições) protegia contra perda de dados, que o
  arquivamento não causa. `safeDestroy()` continua no model.
- A rota `restore` é `PUT` (muda o recurso), com `_method` no form.

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

Resultado obtido: Unit 168 testes, Integration 60, Acceptance 24 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros; `db:populate` sem erro.

No navegador (admin@example.com / 123456): em `/admin/events`,
"Arquivar" na linha de um Evento; ele some e aparece em "Arquivados"
(`/admin/events/archived`) com a data do arquivamento; "Restaurar"
devolve à listagem.

## Relação com outros cenários

- M1 (`m1-campo-novo-workload-hours`) e M4 (`m4-segunda-data-ends-on`):
  também acrescentam coluna em `events` e em `$columns`; conflito só
  de vizinhança no schema e no model.
- M3 (`m3-enum-novo-seminar`): também muda o schema.
- T1 (`t1-populate-campo-eventos-admin`): os Eventos do populate
  nascem ativos (`deleted_at` nulo), nada a mudar.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p11-soft-delete-arquivar`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/p11-soft-delete-arquivar`. São dois commits, na
ordem (o segundo usa o `Paginator` do primeiro).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p11 \
    s50/fvp/4.2/exemplos/p11-soft-delete-arquivar/projeto
git fetch fvp-p11 fvp/p11-soft-delete-arquivar
git cherry-pick 6011e4c 91408e0
git remote remove fvp-p11
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p11-soft-delete-arquivar/solucao.bundle \
    fvp/p11-soft-delete-arquivar:fvp-p11
# o bundle traz os objetos; o cherry-pick cria commits novos
git cherry-pick 6011e4c 91408e0
# opcional, limpa a ref
git branch -D fvp-p11
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria os dois commits com as mesmas mensagens
git am s50/fvp/4.2/exemplos/p11-soft-delete-arquivar/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p11-soft-delete-arquivar/solucao.patch
```

Depois de aplicar: `./run db:populate` (schema mudou), os testes e o
`./run phpcs`.

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

- `EventsController::index`: N1, N2, N3 e N4 também trocam o
  `Event::paginate`. Junte as condições num só `Paginator` (ex.:
  `['deleted_at' => null, 'kind' => $kind]`).
- `EventsController::destroy` e `DeleteEventCest`: Q1/Q2 (dono,
  Evento passado) mexem no mesmo método; mantenha a checagem deles
  antes do `archive()`.
- `config/routes.php`: outras rotas fixas (`full`, `export`) também
  entram antes de `{id}`; fique com todas.
- `schema.sql`, `$columns`: outros campos novos; fique com os dois.

## Armadilhas

- `Paginator` com `['deleted_at' => null]` sem o 1º commit gera
  `deleted_at = NULL`: a listagem fica vazia, sem erro.
- `Event::all()`, `findById` e `where` do core não filtram
  arquivados; cada uso em `app/` precisa decidir se quer ativos.
- Rota `/admin/events/archived` depois de `{id}`: "archived" vira id e
  cai em "Evento não encontrado!".
- `deleted_at` em `$columns`: o `save()` regrava o valor carregado, então
  editar um arquivado não o restaura (há teste). Se ficar fora de
  `$columns`, o `__set` lança "Property deleted_at not found".
- `DeleteEventCest` e `EventsControllerTest` esperavam remoção real e
  a mensagem do P03: precisam ser adaptados.
- `PUT` só chega com `_method` no POST; sem ele, 404.
