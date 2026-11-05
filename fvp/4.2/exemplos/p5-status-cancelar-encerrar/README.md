# P5. Status do Evento: cancelar e encerrar

Pedido, como o professor faria na hora: "O administrador pode cancelar
um Evento. Cancelado aparece com destaque na listagem e no detalhe e
não aceita mais edição. Acrescente também 'encerrar'. Todo Evento novo
nasce agendado."

Chance: alta. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P5.

Branch no clone: `fvp/p5-status-cancelar-encerrar`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                 |
|-----------|----------------------------------------------------------|
| `56398d1` | `feat: Add Event status with cancel and finish actions`  |

## O que mudou

- `database/schema.sql`: `status ENUM('scheduled', 'canceled',
  'finished') NOT NULL DEFAULT 'scheduled'` em `events`.
- `app/Models/Event.php`:
  - `@property string $status`, `'status'` em `$columns`;
  - `STATUSES` com os rótulos (Agendado, Cancelado, Encerrado);
  - construtor: `parent::__construct($params)` e, se `status` ficou
    `null`, `$this->status = 'scheduled'` (modelo do `User`, que faz o
    mesmo com `role`);
  - em `validates()`: `notEmpty('status')` e `inclusion` com
    `array_keys(self::STATUSES)`;
  - `cancel(): bool` e `finish(): bool`: só saem de `scheduled`; senão
    `addError('base', 'só um evento agendado pode ser cancelado!')`
    (ou "encerrado") e `false`. Mudam `status` e chamam `save()`;
  - `isCanceled()`, `isFinished()`, `statusLabel()`.
- `config/routes.php` (grupo admin, depois do `destroy`):
  `PUT /admin/events/{id}/cancel` → `admin.events.cancel` e
  `PUT /admin/events/{id}/finish` → `admin.events.finish`.
- `app/Controllers/Admin/EventsController.php`:
  - `cancel` e `finish`: `findEvent`; não achou → `eventNotFound()`;
    `$event->cancel()` → flash `success` "Evento cancelado!" ou
    `danger` com `errors('base')`; redireciona para o `show`;
  - `edit` e `update`: se `isCanceled()`, flash `danger` "Evento
    cancelado não pode ser editado!" e redirect para o `show`
    (*early return*, como o `eventNotFound`).
  - `status` **não** entrou em `FIELDS`: o formulário não muda o status.
- Views:
  - `index.phtml`: badge `text-bg-secondary` com o rótulo ao lado do
    título; botão "Cancelar Evento" (form `post` + `_method=PUT`) só em
    Evento agendado;
  - `show.phtml`: linha "Situação" (`#event_status`) e, se agendado, os
    botões "Cancelar Evento" e "Encerrar Evento".
- Populate: sem mudança (o construtor dá o padrão).

## Por que assim

- `Model::save()` grava todas as colunas de `$columns`: o `DEFAULT` do
  schema nunca é usado, o INSERT mandaria `NULL`. O padrão fica no
  construtor; assim populate, testes e `BaseEventsCest` continuam
  iguais.
- Regra de transição no model (`cancel()`/`finish()`); o controller só
  pergunta e escolhe o flash. Mesma forma do `safeDestroy()`.
- `PUT` porque a ação altera um recurso existente (RESTful); no HTML vai
  como `POST` com `_method=PUT`, igual ao `update`.
- `cancel()` usa `save()` (passa por `validates()`), como o catálogo.
  Consequência: um Evento inválido (ex.: capacidade abaixo das
  inscrições) não cancela. Se o professor quiser cancelar mesmo assim,
  troque por `$this->update(['status' => 'canceled'])`, que não valida.
- Botão "Cancelar Evento" e não "Cancelar": o formulário já tem um link
  "Cancelar" (voltar sem salvar).

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

Resultado obtido: Unit 168, Integration 63, Acceptance 27 (base: 159 /
45 / 23). PHPCS e PHPStan sem erros. Populate sem erro.

Testes novos:

- `EventTest`: `test_new_event_should_be_scheduled`,
  `test_status_should_accept_every_enum_value`,
  `test_status_outside_enum_should_be_invalid`,
  `test_cancel_should_cancel_a_scheduled_event`,
  `test_cancel_should_fail_for_an_already_canceled_event`,
  `test_cancel_should_fail_for_a_finished_event`,
  `test_finish_should_finish_a_scheduled_event`,
  `test_finish_should_fail_for_a_canceled_event`,
  `test_cancel_should_not_save_an_invalid_event`.
- `EventsControllerTest`: badge e "Situação"; botões só em agendado;
  `cancel` (ok, duas vezes, encerrado, id inexistente); `finish`;
  `edit`/`update` de cancelado redirecionam; `update` ignora `status`
  vindo do form.
- `EventsAccessTest`: as duas rotas novas nos providers (sem sessão →
  `/login`, Participante → `/dashboard`, com `_method=PUT`);
  `test_participant_should_not_cancel_or_finish_events`,
  `test_admin_should_cancel_an_event`.
- `CancelEventCest` (novo): cancelar pelo show, cancelar pela
  listagem, cancelado não abre a edição, encerrar pelo show.

## Relação com outros cenários

- Q2 (`q2-bloquear-passado-cancelado`) parte deste commit e troca o
  bloqueio "cancelado não edita" por `isEditable()` (cancelado **ou**
  passado), valendo também para remover, e esconde os botões.
- P2 (inscrição): com os dois aplicados, `Enrollment::validates()` deve
  recusar inscrição em Evento cancelado (`addError('base', 'evento
  cancelado!')`).
- P6 (duplicar): a cópia de um Evento cancelado deve voltar a
  `scheduled` (`$copy->status = 'scheduled';`).
- M3 (valor novo em ENUM): mesmo par schema + constante + `inclusion`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p5-status-cancelar-encerrar`.

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público. `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/p5-status-cancelar-encerrar`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p5 \
    s50/fvp/4.2/exemplos/p5-status-cancelar-encerrar/projeto
git fetch fvp-p5 fvp/p5-status-cancelar-encerrar
git cherry-pick 56398d1
git remote remove fvp-p5
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p5-status-cancelar-encerrar/solucao.bundle \
    fvp/p5-status-cancelar-encerrar:fvp-p5
git cherry-pick 56398d1
# opcional, limpa a ref
git branch -D fvp-p5
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/p5-status-cancelar-encerrar/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p5-status-cancelar-encerrar/solucao.patch
```

Depois de aplicar: `./run db:populate` (o schema mudou) e os testes.

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

- `database/schema.sql`, `Event::$columns` e `validates()`: todo
  cenário de campo novo (M1, P1...) mexe no fim das listas. Fique com
  as duas colunas e as duas validações.
- `config/routes.php`: P6 e O1 acrescentam rotas no mesmo ponto.
- `EventsController::edit`/`update`: Q1 e Q2 também põem *early
  return* depois do `findEvent`. Q1 (dono) primeiro, depois este.
- `index.phtml`: a célula do título e a de ações são mexidas por R2,
  N6, Q1, P6. Junte o conteúdo.
- `EventsAccessTest::eventRoutes`: outras rotas novas; fique com todas.

## Armadilhas

- Confiar no `DEFAULT 'scheduled'` do schema: o `save()` manda `NULL`
  e o MySQL recusa (`NOT NULL`). O padrão vai no construtor.
- `$this->status ??= 'scheduled'` no construtor não funciona: o
  `Model` não define `__isset`, então `??` vê sempre "não definido" e
  sobrescreve o valor vindo do banco. Use `=== null`.
- Pôr `status` em `FIELDS` ou num `<select>` do form: qualquer admin
  "descancela" pela edição. Só as ações mudam o status.
- Esquecer o `<input type="hidden" name="_method" value="PUT">`: o
  navegador manda `POST`, que não casa a rota `PUT`, e cai no 404.
- `cancel()` sem checar `scheduled` deixa "cancelar" um Evento
  encerrado.
- O flash compara texto exato nos testes (`Evento cancelado!`).
