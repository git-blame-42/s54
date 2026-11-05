# M14. Contador de visualizações com `Model::update()`

Pedido, como o professor faria na hora: "Conte quantas vezes o detalhe
de cada Evento foi aberto. Mostre o número no detalhe e na listagem.
Use o `update()` do model."

Chance: média-baixa. Tempo estimado: 15-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M14.

Branch no clone: `fvp/m14-contador-visualizacoes`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                         |
|-----------|--------------------------------------------------|
| `eb852bb` | `feat: Count event views on the admin show page` |

## O que mudou

- `database/schema.sql`: `views_count INT UNSIGNED NOT NULL DEFAULT 0`
  em `events`.
- `app/Models/Event.php`:
  - `views_count` em `$columns` e `@property`;
  - construtor: se `views_count` vier `null`, vira `0`;
  - `registerView(): void`: em registro salvo, chama
    `$this->update(['views_count' => (int) $this->views_count + 1])`.
- `app/Controllers/Admin/EventsController.php`: o `show` chama
  `$event->registerView()` antes do `render` (uma visualização por
  acesso). `index`, `edit`, `update` e `destroy` não contam.
- Views: `admin/events/show.phtml` ganhou "Visualizações"
  (`#event_views_count`); `admin/events/index.phtml` ganhou a coluna
  "Visualizações" (`td.views-count`).
- `database/Populate/populate.php`: `'views_count' => 0` explícito nos
  16 Eventos.
- Testes:
  - `EventTest`: padrão 0 (em memória e no banco); `registerView`
    incrementa e grava; não valida (Evento passado com `capacity`
    inválida em memória ainda conta, e a `capacity` do banco não muda);
    registro novo não conta; `save()` de edição mantém o contador.
  - `EventsControllerTest`: `show` soma 1 por acesso e exibe o
    número; `index`/`edit` não contam e a listagem mostra a coluna;
    `views_count` enviado no form de edição é ignorado.
  - `ViewsCountCest` (novo): listagem com 0, abre o detalhe duas vezes
    (1, 2) e a listagem mostra 2.

## Por que assim

- `update()` e não `save()`: `update()` grava só a coluna pedida e não
  passa por `validates()`. Com `save()`, ver um Evento que já passou
  ou que está lotado poderia falhar na validação, e todas as colunas
  seriam regravadas.
- Default no construtor: `save()` grava todas as colunas de
  `$columns`, e o `DEFAULT 0` do banco não vale (iria `NULL` e
  quebraria o `NOT NULL`).
- Contar só no `show` do admin: na base (`main`) só existe essa tela de
  detalhe. Se a tela do Participante (`GET /events/{id}`, O2) existir,
  o lugar natural é lá; admin contando a si mesmo infla o número.
  Decida com o professor. A chamada é uma linha em qualquer `show`.
- `fill()` do controller não copia `views_count` (só os campos de
  `FIELDS`): o contador nunca vem do formulário.
- Corrida (documentada no PHPDoc): dois acessos simultâneos leem o
  mesmo valor e um incremento se perde. A versão atômica é um SQL no
  model:

  ```php
  public function registerView(): void
  {
      $stmt = Database::getDatabaseConn()->prepare(
          'UPDATE events SET views_count = views_count + 1 WHERE id = :id'
      );
      $stmt->bindValue(':id', $this->id);
      $stmt->execute();
  }
  ```

  Ficou o `update()` porque é o que o pedido cita e basta para o
  projeto.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
```

Resultado obtido: Unit 164 testes, Integration 48, Acceptance 24
(base: 159 / 45 / 23). PHPCS e PHPStan sem erros. `db:populate` ok
(16 Eventos com `views_count = 0`). Sem classe nova.

Testes novos:

- `EventTest`:
  - `test_views_count_should_default_to_zero`
  - `test_register_view_should_increment_views_count`
  - `test_register_view_should_not_run_the_validation`
  - `test_register_view_on_new_record_should_do_nothing`
  - `test_save_should_keep_the_views_count`
- `EventsControllerTest`:
  - `test_show_increments_views_count_once_per_access`
  - `test_index_and_edit_do_not_count_views_and_index_shows_them`
  - `test_update_does_not_take_views_count_from_the_form`
- `ViewsCountCest::showCountsViews`

No navegador: `/admin/events` mostra 0 em todos; clique em "Ver" de um
Evento (Visualizações: 1), recarregue (2) e volte à listagem (2 na
linha dele). Pelo banco:

```
docker compose exec db mysql -ueventos-user -peventos-pwd \
  eventos_development -e "SELECT id, views_count FROM events LIMIT 3"
```

## Relação com outros cenários

- N6 (`n6-badge-lotado-contador`): outro dado na tela, mas calculado
  (`enrollmentsCount`), não armazenado. Aqui o número é coluna porque
  não dá para recalcular visualizações. Conflito provável na coluna
  nova de `index.phtml`: fique com as duas colunas.
- R5: também mexe no `show`/listagem do admin (dados exibidos); junte
  as linhas novas do `<dl>` e da tabela.
- M1 (campo novo): mesmo roteiro de schema + `$columns`; a diferença é
  que `views_count` não entra no formulário nem em `FIELDS`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m14-contador-visualizacoes`
(devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/fvp/4.2/exemplos/m14-contador-visualizacoes`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m14 \
    s50/fvp/4.2/exemplos/m14-contador-visualizacoes/projeto
git fetch fvp-m14 fvp/m14-contador-visualizacoes
git cherry-pick eb852bb
git remote remove fvp-m14
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/m14-contador-visualizacoes/solucao.bundle \
    fvp/m14-contador-visualizacoes:fvp-m14
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick eb852bb
# opcional, limpa a ref
git branch -D fvp-m14
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m14-contador-visualizacoes/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/m14-contador-visualizacoes/solucao.patch
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

- `schema.sql` e `Event.php` (`$columns`, construtor): outros campos
  novos (M1, P1). Fique com todas as colunas; se já houver construtor
  no `Event`, junte os defaults num só.
- `admin/events/index.phtml` e `show.phtml`: colunas/linhas novas de
  N6, R5, Q1. Fique com todas.
- `EventsController::show`: outro cenário que troque a busca (Q1).
  Mantenha a busca do outro e o `registerView()` antes do `render`.
- `populate.php`: o array do `new Event([...])`; fique com os campos
  dos dois.

Depois de aplicar: `./run db:populate` (schema mudou), os testes e
`./run phpcs`.

## Armadilhas

- Sem default no construtor, `save()` grava `NULL` em `views_count` e
  o `INSERT` falha (`NOT NULL`), mesmo com `DEFAULT 0` no schema.
- `save()` no lugar de `update()`: passa pela validação; ver um Evento
  passado com data alterada, por exemplo, deixaria de contar.
- `updated_at` tem `ON UPDATE CURRENT_TIMESTAMP`: cada visualização
  muda o `updated_at` do Evento. Se isso importar, use o SQL atômico
  com `updated_at = updated_at` no `SET`.
- Corrida entre acessos simultâneos (ver "Por que assim").
- Uma edição aberta antes de várias visualizações regrava o valor
  antigo de `views_count` no `save()` (o `save()` grava todas as
  colunas). Para o projeto é aceitável; documente se o professor
  perguntar.
- Contar no `edit` ou no `index`: só o `show` conta; os testes
  conferem.
- `db:populate` refaz o schema; sem ele o banco de desenvolvimento não
  tem a coluna e a listagem dá 500.
