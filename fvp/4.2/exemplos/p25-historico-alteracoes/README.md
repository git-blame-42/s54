# P25. Histórico de alterações dos Eventos

Pedido, como o professor faria na hora: "Quero saber o que mudou em
cada Evento. Toda vez que um administrador editar, guarde, para cada
campo que realmente mudou, o valor antigo, o novo, quem editou e
quando. Mostre esse histórico no detalhe do Evento, do mais recente
para o mais antigo. Quem editou vem da sessão, não do formulário."

Chance: média-baixa. Tempo estimado: 35-45 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P25.

Branch no clone: `fvp/p25-historico-alteracoes`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                       |
|-----------|------------------------------------------------|
| `0fa5bbe` | `feat: Record the change history of events`    |

## O que mudou

- `database/schema.sql`: tabela `event_changes` (`id`, `event_id`,
  `user_id`, `field`, `old_value`, `new_value`, `created_at`):
  - `event_id` com `ON DELETE CASCADE`: removido o Evento, o
    histórico vai junto;
  - `user_id` com `ON DELETE RESTRICT`, como nas outras tabelas;
  - `old_value`/`new_value` `TEXT NULL` (a descrição é `TEXT`);
  - índice `(event_id, created_at)` para a consulta do show;
  - `DROP TABLE IF EXISTS event_changes` antes do de `event_user`.
- `app/Models/EventChange.php` (novo):
  - `$columns` com as 6 colunas; o construtor preenche
    `created_at` com `date('Y-m-d H:i:s')` quando não vem do banco.
    No schema a coluna é `TIMESTAMP ... DEFAULT CURRENT_TIMESTAMP`,
    mas esse `DEFAULT` nunca é usado: o `save()` grava todas as
    colunas de `$columns`, então o valor vem sempre do PHP;
  - `validates()`: `event_id`, `user_id` e `field` obrigatórios;
    `field` precisa ser uma coluna de `Event` (`inclusion`);
  - `forEvent(int $eventId)`: SQL próprio com `prepare` +
    `bindValue`, `ORDER BY created_at DESC, id DESC`;
  - `user()` (`BelongsTo`) e `userName()`;
  - `fieldLabel()`, `oldValueLabel()`, `newValueLabel()`: rótulo
    do campo ("Vagas") e do valor como o show do Evento já mostra
    (`Event::KINDS`, `MODALITIES`, `AUDIENCES`, data `d/m/Y`).
- `app/Models/Event.php`:
  - `saveWithHistory(?int $userId): bool`: sem autor, não salva
    (erro em `base`); calcula as mudanças **antes** do `save()`;
    se o `save()` falhar, não grava nada; senão grava um
    `EventChange` por campo alterado;
  - `changedFields()`: recarrega o original **uma vez** com
    `self::findById($this->id)` e compara coluna a coluna de
    `$columns` (menos `user_id`), com valores normalizados.
    Devolve `[campo => [antigo, novo]]`;
  - `comparableValue()` (privado): tudo vira string; as colunas
    inteiras editáveis (`INTEGER_COLUMNS`, hoje só `capacity`)
    passam por `filter_var(..., FILTER_VALIDATE_INT)`; o SET é
    ordenado; `\r\n` vira `\n`. O `new_value` de `capacity` é
    gravado já normalizado (`12`, não `'+12'`);
  - `history()`: `EventChange::forEvent($this->id)`;
  - PHPDoc do `safeDestroy`: o histórico não bloqueia a remoção.
- `app/Controllers/Admin/EventsController.php`:
  - `update`: `$event->saveWithHistory($this->currentUser()?->id)`
    no lugar de `$event->save()`;
  - `show`: `$changes = $event->history()` vai para a view no
    `compact`.
- `app/views/admin/events/show.phtml`: seção `#history` abaixo dos
  botões, tabela Campo / De / Para / Quem / Quando, tudo com
  `htmlspecialchars` (De/Para com `nl2br` por causa da descrição).
  Sem histórico: "Nenhuma alteração registrada."
- `database/Populate/populate.php`: duas linhas de exemplo (ver
  "Por que assim").
- Testes:
  - `EventChangeTest` (novo, 15 testes, dois com casos: 8 e 2).
  - `EventsControllerTest`: 5 testes novos.
  - `EventHistoryCest` (novo, 2 cenários).

## Por que assim

- SQL só no model: a ordenação está em `EventChange::forEvent`;
  o controller só chama `history()` e `saveWithHistory()`.
- Comparação com o banco, não com o request nem com o objeto em
  memória: o framework não guarda o "antes" e o `fill` já
  sobrescreveu os atributos. Recarregar com `findById` é o mesmo
  caminho do `validatesStartsOnNotPast`. Um teste prova que, se
  outra edição gravou no meio, o "de" é o valor do banco.
- Mudanças calculadas antes do `save()`: depois dele o banco já
  tem os valores novos e nada pareceria alterado.
- Normalização: cobre os casos que o formulário e a validação da
  base produzem, não qualquer forma "equivalente" de um valor.
  - `capacity`: o banco devolve int `5`; o form manda `'5'`, e o
    `integerMin` (`filter_var`) também aceita `'+5'` e `' 5'`, que
    o banco grava como `5`. Só `(string)` não basta (`'+5'` contra
    `'5'` daria a linha falsa "Vagas 5 para +5"); por isso o
    `filter_var` também na comparação. Coluna inteira nova (M1)
    entra em `INTEGER_COLUMNS`.
  - SET: volta na ordem da definição da coluna, não na enviada.
  - Textarea: chega com `\r\n`.
  - Sem ela, todo Salvar gravaria linhas falsas (conferido: sem
    normalizar, `test_same_values_as_the_form_sends_are_not_changes`
    falha).
  - Não é normalizado: espaço no fim do título, `'020'` em
    `capacity` (`filter_var` recusa e a validação barra), datas em
    outro formato (a validação exige `Y-m-d`). Texto diferente que
    o banco grava diferente é mudança de verdade.
- `user_id` fora da comparação: o formulário não o edita e o
  `fill` nunca o copia.
- Autor só da sessão: o controller passa
  `$this->currentUser()?->id`; o model não lê o request. O teste
  de integração manda `user_id` dentro e fora de `event[]` e o
  autor continua sendo o usuário logado. `saveWithHistory(null)`
  recusa em vez de gravar histórico sem autor (a rota é do grupo
  `admin`, então na prática sempre há usuário).
- `created_at DESC, id DESC`: as linhas de uma mesma edição têm o
  mesmo segundo; o `id` desempata (a última inserida primeiro).
- `ON DELETE CASCADE`: o histórico pertence ao Evento. Diferente de
  `event_user` (`RESTRICT`, regra P03 do projeto), ele não deve
  impedir a remoção.
- Sem transação: cada chamada a `Database::getDatabaseConn()` abre
  uma conexão nova, então `save()` e os `EventChange` não cabem na
  mesma transação sem mexer no `core/`. Consequência: se um
  `EventChange` falhar (não deveria: os dados vêm do próprio
  model), o Evento já está salvo e o histórico fica parcial (as
  linhas gravadas antes da falha ficam). O model lança
  `RuntimeException` em vez de esconder o erro, e ela vira 500 no
  `ErrorsHandler`. A opção com transação (catálogo, P25, passo 4)
  muda 2 linhas no `core/`: `Model::save(?PDO $pdo = null)` e
  `$pdo ??= Database::getDatabaseConn();`. Aí o `saveWithHistory`
  abre um PDO, faz `beginTransaction()`, passa o mesmo `$pdo` ao
  `save()` do Evento e de cada `EventChange`, e `commit()` ou
  `rollBack()`. Não fiz para não mexer no `core/`.
- Populate com duas linhas: uma edição de exemplo do Administrador,
  há dois dias, no Evento "Introdução a APIs REST com PHP" (local e
  vagas). O `new_value` é o valor atual do Evento, então o
  histórico confere com os dados; as contagens do populate (16
  Eventos, 14 Inscrições) não mudam. Assim o show já mostra a
  tabela sem precisar editar antes, e as duas linhas com o mesmo
  `created_at` mostram o desempate por `id`.

## Como verificar

```
./run up -d db php php_test web
./run composer dump-autoload -o --strict-psr
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 182 testes, Integration 50, Acceptance 25
(base: 159 / 45 / 23). PHPCS e PHPStan sem erros. O populate
termina com "2 Alterações de exemplo no histórico do Evento
"Introdução a APIs REST com PHP"".

Testes novos:

- `EventChangeTest`:
  - `test_save_with_history_should_record_only_the_changed_fields`
  - `test_save_with_history_should_record_one_row_for_each_field`
    (8 casos)
  - `test_save_with_history_should_record_nothing_without_changes`
  - `test_same_values_as_the_form_sends_are_not_changes`
  - `test_capacity_with_sign_or_space_is_the_same_integer`
    (`'+10'` e `' 10'` contra `10`: 0 linhas)
  - `test_changed_capacity_is_recorded_as_the_stored_integer`
  - `test_invalid_data_should_not_save_nor_record_history`
  - `test_without_editor_should_not_save_nor_record_history`
  - `test_new_record_has_no_changed_fields_nor_history`
  - `test_changes_are_compared_with_the_stored_values`
  - `test_removing_the_event_removes_its_history` (CASCADE)
  - `test_history_should_be_newest_first_with_id_as_tie_breaker`
  - `test_event_change_requires_event_user_and_a_known_field`
  - `test_event_change_fills_created_at_when_missing`
  - `test_event_change_labels_show_what_the_user_sees`
- `EventsControllerTest`:
  - `test_update_records_history_with_the_session_user`
  - `test_update_without_changes_records_no_history`
  - `test_update_with_invalid_data_records_no_history`
  - `test_show_lists_history_newest_first_and_escaped`
  - `test_show_without_history_says_so`
- `EventHistoryCest`:
  - `editAndSeeHistoryOnShow`: show sem histórico, editar título
    (com `<i>`) e vagas, duas linhas no show, autor
    "Administrador", HTML escapado.
  - `saveWithoutChangesAddsNoHistory`: muda o local (1 linha),
    abre a edição e salva sem mexer, continua 1 linha.

No navegador (populate): Admin, Eventos, "Ver" em "Introdução a
APIs REST com PHP": a seção "Histórico de alterações" mostra Vagas
(60 para 80) e Local. Edite o Tipo e salve: aparece uma linha nova
no topo (Palestra para Oficina, Administrador, agora). Salvar de
novo sem mexer não acrescenta linha.

## Relação com outros cenários

- M19 (`m19-nenhuma-alteracao`): mesma comparação com o banco e a
  mesma normalização. Com os dois, fique com um `comparableValue`
  só e troque o corpo do `hasChanges()` por
  `return $this->newRecord() || $this->changedFields() !== [];`
  (o aviso "Nenhuma alteração" sai antes e nenhum histórico é
  gravado, o que já é o comportamento deste cenário).
- M12 (`m12-ultimo-editor`): também usa o usuário da sessão no
  `update`. O `updated_by` entra em `$columns` e passaria a gerar
  linha no histórico a cada editor diferente: acrescente-o ao
  `array_diff(static::$columns, ['user_id'])` de `changedFields()`.
- M1 (`m1-campo-novo-workload-hours`) e outros campos novos: entram
  no histórico sem mexer em `changedFields()`. Falta o rótulo em
  `EventChange::FIELD_LABELS` (sem ele aparece o nome da coluna) e,
  se for inteiro como `workload_hours`, incluí-lo em
  `Event::INTEGER_COLUMNS` (senão `'+4'` contra `4` vira linha).
- P10 (`p10-comentarios-evento`): mesmo molde de tabela filha com
  `ON DELETE CASCADE` e `created_at` preenchido no construtor.
- R5 (`r5-created-updated-at`): mostra `updated_at` do Evento; o
  histórico diz o quê e quem, o `updated_at` só o quando.
- N10 (`n10-pagina-id-invalido`): o `findEvent` da base ainda usa
  `(int)` no id da rota (`/admin/events/1abc` edita o Evento 1 e
  grava histórico nele). Este cenário não mexe nisso; o N10 troca
  por `filter_var`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois
de `s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p25-historico-alteracoes`
(devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/p25-historico-alteracoes`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p25-historico-alteracoes
cd /caminho/do/seu/repositorio
git remote add fvp-p25 \
    s50/fvp/4.2/exemplos/p25-historico-alteracoes/projeto
git fetch fvp-p25 fvp/p25-historico-alteracoes
git cherry-pick 0fa5bbe
git remote remove fvp-p25
```

### 2. A partir do `solucao.bundle`

```
D=s50/fvp/4.2/exemplos/p25-historico-alteracoes
git fetch $D/solucao.bundle \
    fvp/p25-historico-alteracoes:fvp-p25
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 0fa5bbe
# opcional, limpa a ref
git branch -D fvp-p25
```

### 3. A partir do `solucao.patch`

```
D=s50/fvp/4.2/exemplos/p25-historico-alteracoes
# cria commit novo com a mesma mensagem
git am $D/solucao.patch
# só o diff, sem commit
git apply --3way $D/solucao.patch
```

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(classe nova) e `./run db:populate` (tabela nova).

### Se der conflito

```
# arquivos marcados "both modified"
git status
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue
# ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar e por quê:

- `database/schema.sql`: outro cenário com tabela nova (P10)
  acrescenta `DROP TABLE` e `CREATE TABLE` nos mesmos pontos; fique
  com os dois. Os `DROP` das tabelas filhas vêm antes do de
  `events`.
- `EventsController::update`: M19 põe o `hasChanges()` antes do
  `save()`, Q2 põe um `isEditable()` no começo, M12 preenche o
  `updated_by`. Mantenha os blocos deles e troque só o
  `$event->save()` do `if` por `saveWithHistory(...)`.
- `EventsController::show`: se outro cenário também passa
  variáveis para a view, junte tudo num `compact` só, com `$title`.
- `app/views/admin/events/show.phtml`: o P10 põe os comentários
  depois dos botões e o O3 os inscritos depois de Vagas; fique com
  todas as seções.
- `app/Models/Event.php`: métodos novos entre os `validates*` e
  `user()`; fique com os dois lados (com o M19, ver acima).
- `database/Populate/populate.php`: blocos novos no fim; fique com
  os dois (o do histórico usa `$eventModels[1]` e `$admin`).
- `EventsControllerTest.php`: testes novos antes dos de `destroy` e
  helper `recordChange` antes de `enroll`; fique com os dois.

## Armadilhas

- Comparar com o request ou com o objeto do `findEvent` depois do
  `fill`: os atributos já são os novos. Recarregue do banco.
- Calcular as mudanças depois do `save()`: o banco já tem os
  valores novos e nenhuma linha é gravada.
- Gravar o histórico antes de validar: um Salvar com erro deixaria
  linhas de uma edição que não aconteceu. Aqui só grava se o
  `save()` passou.
- Comparar com `!==` sem normalizar: `20` (int do banco) contra
  `'20'` (form) gera uma linha "Vagas 20 para 20" a cada Salvar; o
  mesmo para o SET em outra ordem e o `\r\n` do textarea.
- Normalizar inteiro só com `(string)`: `'+5'` passa no
  `integerMin`, o banco grava `5` e sai "Vagas 5 para +5". Use
  `filter_var(..., FILTER_VALIDATE_INT)` dos dois lados.
- Autor vindo de `event[user_id]` ou de um campo oculto: qualquer
  um forja. Use `$this->currentUser()`.
- `ORDER BY created_at DESC` sem `id`: as linhas da mesma edição
  têm o mesmo segundo e a ordem entre elas fica indefinida.
- Esquecer o `created_at` no construtor: o `save()` grava `NULL`
  na coluna `NOT NULL` e o `INSERT` falha (o `DEFAULT` não vale).
- `RESTRICT` (ou sem `ON DELETE`) em `event_changes.event_id`: o
  primeiro Evento editado não pode mais ser removido; o
  `safeDestroy` devolve a mensagem genérica de inscrições.
- Esquecer o `DROP TABLE IF EXISTS event_changes` no topo do
  schema: o segundo `db:populate` falha no `CREATE TABLE
  event_changes` (a tabela já existe).
- Saída sem `htmlspecialchars`: título com `<script>` vira código
  no show. O "De/Para" mostra texto que veio do usuário.
