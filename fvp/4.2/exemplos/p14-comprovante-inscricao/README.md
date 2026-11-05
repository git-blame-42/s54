# P14. Comprovante de inscrição

Pedido, como o professor faria na hora: "Ao se inscrever, o
Participante recebe um número de comprovante de 4 dígitos (1000 a
9999), gerado pelo sistema e único dentro do Evento. Ele vê o número
na página do Evento e o administrador vê os números na lista de
inscritos."

Chance: média. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P14.

Branch no clone: `fvp/p14-comprovante-inscricao`, empilhada sobre
`fvp/p2-inscricao-participante` (P2), que está sobre O2. Cadeia:
`main` (`f31006b`) -> O2 -> P2 -> P14.

| Commit    | Mensagem                                                  |
|-----------|-----------------------------------------------------------|
| `0669a19` | `feat: Add events listing and details for participants` * |
| `458a05b` | `feat: Allow participants to enroll in events` *          |
| `3faf1d8` | `feat: Add enrollment receipt code`                       |

`*` commits da base (O2 e P2); o deste cenário é só o `3faf1d8`.

## O que mudou

- `database/schema.sql` (`event_user`): coluna
  `receipt_code SMALLINT UNSIGNED NOT NULL` e
  `CONSTRAINT index_event_user_on_event_id_and_receipt_code
  UNIQUE (event_id, receipt_code)`.
- `app/Models/Enrollment.php`:
  - `'receipt_code'` em `$columns`, `@property`, constantes
    `RECEIPT_CODE_MIN = 1000` e `RECEIPT_CODE_MAX = 9999`.
  - `save()` sobrescrito: em registro novo sem número, chama
    `newReceiptCode($eventId)` e depois `parent::save()`. Salvar de
    novo não troca o número.
  - `newReceiptCode(int $eventId): int`: `random_int(1000, 9999)`
    em laço até não existir
    `['event_id' => $eventId, 'receipt_code' => $code]`.
  - `validates()` ganha `validatesReceiptCode()`: obrigatório,
    inteiro de 1000 a 9999 ("deve estar entre 1000 e 9999!") e
    único no Evento com `Validations::uniqueness(['event_id',
    'receipt_code'])` ("já existe neste evento!").
- `app/Models/User.php`: `enrollmentIn(Event $event): ?Enrollment`
  (a Inscrição do usuário no Evento, com `instanceof`).
- `app/Models/Event.php`: `enrollments(): array`
  (`@return array<Enrollment>`, via `Enrollment::where`).
- `app/views/events/show.phtml` (Participante): depois de inscrito,
  `Comprovante de inscrição: Nº 1234` (classe `receipt`).
- `app/views/admin/events/show.phtml`: o O3 (inscritos no `show`)
  não está na base, então entrou uma lista mínima "Inscritos"
  (`table.enrollments`: comprovante, nome e e-mail) ou "Nenhuma
  inscrição.".
- `EnrollmentsController` não mudou: o número não vem do form.
- Populate: as 14 Inscrições ganham número sozinhas (o populate já
  usa `Enrollment::save()`).
- Testes:
  - `EnrollmentTest`: faixa; não muda ao salvar de novo; 5
    Inscrições no mesmo Evento com números distintos; número
    repetido no mesmo Evento recusado; mesmo número em outro Evento
    aceito; fora da faixa (999, 10000, `abc`) recusado;
    `newReceiptCode` pula o número já usado; `enrollmentIn` e
    `enrollments()`.
  - `EnrollmentsControllerTest`: `receipt_code=1` enviado no POST é
    ignorado (o número gerado fica na faixa).
  - `EventsControllerTest` (Participante): inscrito vê
    "Comprovante de inscrição" e o número; quem não está inscrito
    não vê.
  - `Admin/EventsControllerTest`: o `show` lista inscritos com o
    número; sem inscrições, "Nenhuma inscrição.".
  - Aceitação: `tests/Acceptance/events/EnrollmentReceiptCest.php`
    (`extends BaseParticipantEventsCest`): o Participante 1 se
    inscreve no Evento 02 e vê o número; o admin vê os 3 números do
    Evento 01 na lista de inscritos.

## Por que assim

- Regra de negócio no model: o número nasce no `save()` da
  Inscrição, como o código de check-in do P1. Controller e
  formulário não sabem que ele existe.
- "Único por Evento", não global: a unicidade é o par
  `(event_id, receipt_code)`, no laço, na validação e no `UNIQUE`
  do schema. Cada Evento tem 9000 números possíveis, bem mais que
  as vagas.
- `random_int` (imprevisível), não `rand()`.
- Três camadas: o laço evita colisão no caminho normal; o
  `uniqueness` recusa quem força um número repetido (ex.:
  `new Enrollment([... 'receipt_code' => X])`); o `UNIQUE` segura
  qualquer escrita que fuja do model.
- `filter_var(..., FILTER_VALIDATE_INT)` na faixa: recusa `abc` e
  `12.5` sem `(int)` silencioso.
- Lista mínima no `show` do admin porque o O3 não está na base. Com
  o O3 aplicado, basta acrescentar a coluna "Comprovante" na lista
  dele e apagar esta.

## Como verificar

```
# o schema mudou: refaça o banco
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 172, Integration 73, Acceptance 31 (base P2:
164 / 68 / 29; `main`: 159 / 45 / 23). PHPCS e PHPStan sem erros.
`db:populate` sem erro: 14 Inscrições com 14 pares
(Evento, número) distintos, todos entre 1000 e 9999.

Na tela: entre como `user@example.com`, abra um Evento com vagas,
clique em "Inscrever-se" e veja "Comprovante de inscrição: Nº ...".
Como `admin@example.com`, abra o Evento 1 em `/admin/events/1`: a
lista "Inscritos" mostra 3 números.

## Relação com outros cenários

- P2 (`p2-inscricao-participante`): base direta; sem a Inscrição
  pelo Participante não há onde mostrar o comprovante.
- O2 (`o2-eventos-para-participante`): base do P2 (listagem e
  `show` do Participante, onde o número aparece).
- P1 (`p1-codigo-checkin-aleatorio`): mesmo mecanismo (`save()`
  sobrescrito, `random_int` em laço, faixa e `UNIQUE`), mas no
  Evento e com unicidade global.
- O3 (inscritos no `show` do admin): se aplicado, a lista dele
  substitui a lista mínima daqui; leve a coluna "Comprovante".

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p14-comprovante-inscricao`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/p14-comprovante-inscricao`.

Este cenário é empilhado: sem O2 e P2 no alvo, aplique o range
inteiro (`0669a19`, `458a05b` e `3faf1d8`); com O2 e P2 já
aplicados, aplique só o topo (`3faf1d8`).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p14 \
    s50/fvp/4.2/exemplos/p14-comprovante-inscricao/projeto
git fetch fvp-p14 fvp/p14-comprovante-inscricao
# sem O2 e P2: o range inteiro (base + topo)
git cherry-pick 0669a19^..3faf1d8
# com O2 e P2 já aplicados: só o topo
# git cherry-pick 3faf1d8
git remote remove fvp-p14
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p14-comprovante-inscricao/solucao.bundle \
    fvp/p14-comprovante-inscricao:fvp-p14
# o bundle traz os objetos; o cherry-pick cria commits novos
git cherry-pick 0669a19^..3faf1d8
# ou, com O2 e P2 já aplicados: git cherry-pick 3faf1d8
# opcional, limpa a ref
git branch -D fvp-p14
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria os commits novos com as mesmas mensagens
git am s50/fvp/4.2/exemplos/p14-comprovante-inscricao/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p14-comprovante-inscricao/solucao.patch
```

Se o patch trouxer os três commits e o alvo já tiver O2 e P2, o
`git am` para no primeiro com conflito: `git am --skip` em cada
commit da base até chegar ao do comprovante.

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

- `database/schema.sql` (`event_user`): outro cenário que mexa na
  Inscrição (ex.: presença/check-in) acrescenta colunas no mesmo
  ponto; fique com todas.
- `app/Models/Enrollment.php`: `$columns`, `validates()` e outro
  `save()` sobrescrito; junte num `save()` só.
- `app/Models/Event.php`: o P1 também sobrescreve `save()` no
  Evento (não conflita com este); o O3 pode criar outro método de
  inscritos (ex.: `participants()`): fique com os dois ou use o do
  O3 na view.
- `app/views/admin/events/show.phtml`: com o O3, fique com a lista
  dele e acrescente a coluna "Comprovante".
- `app/views/events/show.phtml`: outros cenários do Participante
  (cancelar inscrição) mexem no mesmo bloco "Você está inscrito".

Depois de aplicar: `./run db:populate` (schema mudou), os testes e
o `./run phpcs`.

## Armadilhas

- Gerar no controller (ou aceitar do form): regra de negócio fora do
  model, e o Participante escolheria o próprio número.
- Unicidade global (`exists(['receipt_code' => $code])`): funciona,
  mas não é o pedido e limita a 9000 Inscrições no sistema todo.
- `!empty($this->event_id)` dentro do model: o `Model` usa `__get`
  sem `__isset`, então `empty()` em atributo é sempre `true`. Leia
  o atributo para uma variável antes, ou compare com `null`.
- `Model::save()` grava todas as colunas: sem gerar o número antes
  do `parent::save()`, o `INSERT` manda `NULL` e quebra o
  `NOT NULL`.
- Gerar em todo `save()` (sem `newRecord()`): o número muda a cada
  edição; o teste "não muda ao salvar de novo" pega isso.
- Esquecer o `db:populate` depois de mudar o schema: o banco de
  desenvolvimento não tem a coluna e a inscrição dá 500 (os testes
  passam porque recriam o schema).
- `(int) $enrollment->receipt_code` na view: o número já é inteiro,
  o cast só garante que nada além de dígitos vai para o HTML.
