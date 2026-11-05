# M15. Escolher o responsável entre os administradores

Pedido, como o professor faria na hora: "Ao cadastrar ou editar um
Evento, deixe escolher qual administrador é o responsável por ele."

Chance: média-baixa. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M15.

Branch no clone: `fvp/m15-responsavel-admin`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                         |
|-----------|--------------------------------------------------|
| `fda346d` | `feat: Choose the responsible admin for an event` |

## O que mudou

- `app/Models/User.php`: `User::admins(): array`
  (`@return array<static>`), com `self::where(['role' => 'admin'])`.
- `app/Models/Event.php`:
  - `validates()` começa com
    `Validations::notEmpty('user_id', $this) &&
    $this->validatesUserIsAdmin();` (validação do M11);
  - `validatesUserIsAdmin()`: confere o formato com
    `filter_var($this->user_id, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]])` e só então
    `User::findById($id)`; formato inválido, usuário inexistente ou
    não admin dão o erro "deve ser um administrador existente!" no
    campo `user_id`. Válido, o `user_id` passa a ser o inteiro
    conferido (o `INSERT` não recebe a string do formulário).
- `app/Controllers/Admin/EventsController.php`:
  - `new()`: `$event->user_id = $this->currentUser()?->id;` (o
    select abre com o logado);
  - `create()`: define o logado **antes** do `fill()`;
  - `fill()`: troca `user_id` só se o formulário mandar um valor
    escalar não vazio; ausente, fica o atual (o logado no create, o
    gravado no update).
- `app/views/admin/events/_form.phtml`: `<select id="event_user_id"
  name="event[user_id]">` "Responsável", com `User::admins()`,
  `selected` no atual (comparação `(string) $event->user_id ===
  (string) $admin->id`) e o erro do campo (`is-invalid` +
  `invalid-feedback`).
- `app/views/admin/events/show.phtml`: "Responsável" com
  `htmlspecialchars($event->user()->get()->name)`.
- Testes:
  - `EventTest`: outro admin é válido; Participante e id inexistente
    são inválidos (os testes conferem que só há o erro de `user_id` e
    que nada foi gravado); ids mal formados (`'1abc'`, `'abc'`,
    `'1.5'`, `'0'`, `'-1'`, com o id do admin no lugar do 1) são
    recusados; string numérica válida é gravada como inteiro; trocar
    para Participante na edição é recusado; `User::admins()` só traz
    admins. `user_id` entrou na lista de obrigatórios
    (`requiredFieldsProvider`, Evento vazio e `errorsOf`).
  - `EventsControllerTest`: o antigo
    `test_create_ignores_user_id_sent_by_the_form` foi invertido para
    `test_create_with_unknown_user_id_renders_form_with_error` (o form
    agora manda); outro admin grava e aparece no show; Participante e
    `'<id do admin>abc'` são recusados; sem `user_id` fica o logado;
    o `new` lista só admins com o logado selecionado; o `update` troca
    o responsável.
  - `tests/Acceptance/admin/events/ChooseResponsibleCest.php` (novo):
    cria um segundo admin, cadastra escolhendo-o no select, vê o nome
    no show e o select da edição com ele selecionado.

## Por que assim

- Abrir `user_id` ao formulário sem validar seria a falha de "recurso
  pai sem checagem": um POST forjado daria o Evento a um Participante
  e um id inexistente estouraria a FK (página 500). A regra fica no
  model (`validates()`), não no controller nem só no `select`.
- `user_id` fora do laço `FIELDS`, com tratamento próprio no `fill()`
  (o catálogo sugere pôr em `FIELDS`): o laço grava `null` quando o
  campo não vem, e aí o "sem `user_id` usa o logado" e o "edição sem o
  campo mantém o atual" se perderiam. O efeito é o mesmo de estar em
  `FIELDS`, sem esse efeito colateral.
- Decisão sobre o sentido de `user_id`: passa a ser o **responsável**
  (a mesma coluna; o criador não fica mais guardado em separado). Se
  "só o dono edita" (Q1) for aplicado depois, o dono é o responsável.
  Para guardar os dois, seria preciso uma coluna nova
  (`responsible_id`), com FK e a mesma validação.
- `User::admins()` no model, chamado pela view do formulário (como o
  formulário já usa `Event::KINDS`); sem SQL na view. O controller
  continua fino.
- Mensagem no campo (`errors('user_id')` com `is-invalid`), igual aos
  outros campos: o select existe, então o erro aparece ao lado dele.

## Como verificar

```
./run up -d db php php_test web
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 171, Integration 51, Acceptance 24 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. `./run db:populate` sem
erro (todos os Eventos do populate usam o admin). Schema não mudou.

No navegador: com o populate da base (um admin só) o select tem uma
opção, "Administrador". Com o T1 aplicado (`admin2@example.com`),
cadastre um Evento escolhendo "Administradora Dois" e veja o nome em
"Responsável" no show.

## Relação com outros cenários

- M11 (validar `user_id` no model): este cenário inclui a validação
  do M11; aplicar os dois só repete o mesmo método (fique com um).
- T1 (`t1-populate-campo-eventos-admin`): cria o segundo admin; sem
  ele o select tem uma opção só.
- R2 (`r2-nome-do-criador`): mostra o mesmo nome como "Criado por"
  (via `creatorName()`). Com os dois, troque o rótulo para
  "Responsável" e use `creatorName()` aqui, para não duplicar.
- Q1 (só o dono edita): decida que o dono é o responsável.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m15-responsavel-admin`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/m15-responsavel-admin`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m15 \
    s50/fvp/4.2/exemplos/m15-responsavel-admin/projeto
git fetch fvp-m15 fvp/m15-responsavel-admin
git cherry-pick fda346d
git remote remove fvp-m15
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/m15-responsavel-admin/solucao.bundle \
    fvp/m15-responsavel-admin:fvp-m15
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick fda346d
# opcional, limpa a ref
git branch -D fvp-m15
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m15-responsavel-admin/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/m15-responsavel-admin/solucao.patch
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

- `EventsController.php` (`create` e `fill`): outros cenários mexem
  no mesmo `fill()` (campo novo em `FIELDS`). Mantenha o bloco do
  `user_id` depois do laço e o `user_id` do logado **antes** do
  `fill()` no `create`.
- `Event.php` (`validates()`): M11 ou campo novo acrescentam linhas
  no mesmo método; não repita `validatesUserIsAdmin()`.
- `_form.phtml` e `show.phtml`: campos e itens novos no mesmo ponto;
  mantenha todos.
- `EventsControllerTest`: o teste antigo
  `test_create_ignores_user_id_sent_by_the_form` foi substituído; se
  o outro lado ainda o tiver, fique com a versão deste (ele falharia
  por definição).

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- Pôr `user_id` no formulário sem validar no model: qualquer POST
  forjado escolhe o dono (inclusive um Participante).
- Pôr `user_id` em `FIELDS` sem cuidado: campo ausente vira `null` e
  o Evento perde o responsável (ou o `save()` falha).
- No `create`, definir o logado **depois** do `fill()`: sobrescreve a
  escolha do formulário (era o comportamento antigo).
- Apagar o teste que conferia "ignora `user_id` do form": inverta a
  asserção; o caso continua importante (id inválido).
- `selected` com `===` entre tipos diferentes: depois de um POST com
  erro, `$event->user_id` é a string do formulário (`'2'`), e o
  `id` do admin é `int`; `'2' === 2` nunca casa. Compare as duas
  como string (`(string) ... === (string) ...`); `(int)` de novo
  aceitaria `'2abc'` como 2.
- `(int) $this->user_id` na validação: `(int) '1abc'` é 1, então
  `'1abc'` passaria como o admin 1 (e iria para o `INSERT`). Use
  `filter_var(..., FILTER_VALIDATE_INT)`, como no M11.
- Participante não chega ao formulário (grupo `admin`), mas a
  validação cobre quem forjar o POST.
