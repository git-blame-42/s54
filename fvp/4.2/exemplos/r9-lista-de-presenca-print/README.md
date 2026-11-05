# R9. Lista de presença imprimível com layout próprio

Pedido, como o professor faria na hora: "Crie uma página para imprimir
a lista de presença do Evento: os inscritos com nome, e-mail e uma
coluna para assinatura, sem a navbar do sistema, com um botão
Imprimir."

Chance: média. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, R9.

Branch no clone: `fvp/r9-lista-de-presenca-print`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                  |
|-----------|-----------------------------------------------------------|
| `311753e` | `feat: Add printable attendance list with its own layout` |

## O que mudou

- `config/routes.php`: `GET /admin/events/{id}/attendance`, nome
  `admin.events.attendance`, no grupo `admin`, logo depois do `edit`.
  Não precisa vir antes de `{id}`: `/admin/events/{id}` só casa com
  um segmento depois de `events`, como o `edit` já mostra.
- `app/Models/Event.php`: `participants(): array` (`@return
  array<int, User>`). Uma consulta só, `prepare` + `bindValue`:

```sql
SELECT users.id, users.name, users.email, users.role
FROM users
INNER JOIN event_user ON event_user.user_id = users.id
WHERE event_user.event_id = :event_id
ORDER BY users.name, users.id
```

  Evento novo devolve `[]`.
- `Admin\EventsController::attendance`: `findEvent` (não encontrado →
  flash e `/admin/events`), `$participants = $event->participants()`,
  `$this->layout = 'print'` e `render('admin/events/attendance',
  compact('event', 'participants', 'title'))`.
- `core/` sem mudança: o `Controller` já tem `protected string $layout
  = 'application'` e o `render()` faz `require
  'app/views/layouts/' . $this->layout . '.phtml'`. Trocar a
  propriedade na ação basta.
- `app/views/layouts/print.phtml` (novo): HTML mínimo, sem navbar,
  sem flash, sem Bootstrap; carrega `/assets/css/print.css` e
  `/assets/js/print.js`; imprime o `$title` no `<h1>` e a view.
- `app/views/admin/events/attendance.phtml` (nova): título, data,
  local e "Inscritos: N/capacidade" no cabeçalho; botão
  `#print_button` "Imprimir" e link "Voltar" (classe `no-print`);
  tabela `#`, Nome, E-mail e Assinatura (em branco), tudo com
  `htmlspecialchars`; "Nenhum inscrito neste evento." sem inscritos.
- `public/assets/css/print.css` (novo): bordas na tabela, coluna de
  assinatura larga, linhas altas; em `@media print` esconde
  `.no-print` e evita quebrar linha entre páginas.
- `public/assets/js/print.js` (novo): no `DOMContentLoaded`, liga o
  clique de `#print_button` a `window.print()`. Nada de `onclick`.
- `show.phtml` do admin: botão "Lista de presença" ao lado de
  "Editar".
- Testes:
  - `EventTest` (2): `participants()` traz só os inscritos do
    Evento, em ordem de nome, como `User`; vazio sem inscrições e em
    Evento novo.
  - `EventsControllerTest` (4): `attendance` mostra título, local,
    data, e-mails, "Assinatura", o botão e o `print.js`, sem
    `onclick`, sem `navbar` e sem `application.css`; vazio; id
    inexistente redireciona; o `show` tem o link.
  - `EventsAccessTest`: rota nova em `eventRoutes()` (visitante vai
    para `/login`, Participante para `/dashboard`) e admin recebe
    200 com o título da página.
  - Cest novo `AttendanceListCest` (2): do show do Evento 01 (3
    inscritos) clica em "Lista de presença", confere 3 linhas com
    nome e e-mail, sem `nav.navbar`, e clica em "Imprimir" com
    `window.print` trocado por um espião (prova que o `print.js`
    funciona sem abrir o diálogo); Evento sem inscritos.

## Por que assim

- Layout por ação com a propriedade `$layout` que o framework já tem:
  nenhuma mudança no core, e as outras ações continuam no
  `application`. Se a propriedade não existisse, o caminho seria um
  parâmetro opcional em `render()`; não foi preciso.
- SQL no model e uma consulta só: buscar as `Enrollment` e chamar
  `->user()->get()` em cada uma faria uma consulta por inscrito
  (N+1). O JOIN também ordena por nome no banco, com a collation do
  MySQL (acentos no lugar certo, o que um `usort` com `strcmp` não
  faz).
- O `BelongsToMany` do framework também faz uma consulta só, mas não
  ordena; para lista de presença a ordem alfabética importa.
- Só `id`, `name`, `email` e `role` no `SELECT`: a senha criptografada
  não precisa sair do banco para imprimir.
- JS em arquivo próprio, sem `onclick`: o padrão do projeto
  (`application.js`) e o que o professor cobra. O CSS de impressão
  também em arquivo, para o layout ficar só com a estrutura.
- A página é do admin (grupo `admin`): lista e-mails de terceiros.

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

Resultado obtido: Unit 161 testes, Integration 52, Acceptance 25 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Testes novos:

- `EventTest`:
  - `test_participants_should_list_the_enrolled_users_ordered_by_name`
  - `test_participants_should_be_empty_without_enrollments_and_for_...`
    (`..._new_records`)
- `EventsControllerTest`:
  - `test_attendance_lists_participants_in_the_print_layout`
  - `test_attendance_without_participants_shows_empty_message`
  - `test_attendance_with_unknown_id_redirects_to_index`
  - `test_show_links_to_attendance`
- `EventsAccessTest`:
  - `admin.events.attendance` nos dois data providers
  - `test_admin_should_access_the_attendance_list`
- `AttendanceListCest`:
  - `openAttendanceListFromShow`
  - `attendanceListOfEventWithoutEnrollments`

No navegador (populate): Admin, "Ver" no primeiro Evento, "Lista de
presença": 3 inscritos em ordem de nome, sem navbar; "Imprimir" abre
o diálogo de impressão, e na prévia o botão e o "Voltar" somem. Por
HTTP:

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c \
    http://localhost/admin/events/1/attendance
```

## Relação com outros cenários

- O3 (`o3-inscritos-no-show`): cria `Event::participants()` como
  relação `BelongsToMany` (`$event->participants()->get()`), enquanto
  aqui `participants()` devolve o array ordenado. Com os dois, fique
  com UM método: ou mantenha o do O3 e use
  `$event->participants()->get()` na lista (perde a ordem por nome),
  ou mantenha este e troque o `->get()` e o `->count()` do O3 por
  `participants()` e `count(...)`.
- P4 (`p4-confirmar-presenca-codigo`): com `confirmed_at`, a lista
  pode trazer uma coluna "Confirmou" (acrescente
  `event_user.confirmed_at` ao `SELECT`) ou listar só os
  confirmados. Sem P4 a coluna de assinatura é a confirmação.
- S2 (`s2-teste-acesso-rota-nova`): o mesmo roteiro de teste de
  acesso para a rota nova.

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/r9-lista-de-presenca-print`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh s50/fvp/4.2/exemplos/r9-lista-de-presenca-print
cd /caminho/do/seu/repositorio
git remote add fvp-r9 \
    s50/fvp/4.2/exemplos/r9-lista-de-presenca-print/projeto
git fetch fvp-r9 fvp/r9-lista-de-presenca-print
git cherry-pick 311753e
git remote remove fvp-r9
```

### 2. A partir do `solucao.bundle`

```
D=s50/fvp/4.2/exemplos/r9-lista-de-presenca-print
git fetch $D/solucao.bundle fvp/r9-lista-de-presenca-print:fvp-r9
git cherry-pick 311753e
# opcional, limpa a ref
git branch -D fvp-r9
```

### 3. A partir do `solucao.patch`

```
D=s50/fvp/4.2/exemplos/r9-lista-de-presenca-print
# cria commit novo com a mesma mensagem
git am $D/solucao.patch
# só o diff, sem commit
git apply --3way $D/solucao.patch
```

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

- `app/Models/Event.php`: `participants()` do O3 (ver acima); nunca
  deixe dois métodos com o mesmo nome (erro fatal do PHP). Os `use`
  de `Database` e `PDO` podem já existir; deixe um de cada.
- `config/routes.php`: outras rotas novas de Eventos (O1, P6, P8) no
  mesmo bloco; fique com todas, cada uma com seu nome, e as fixas
  (ex.: `/admin/events/full`) antes de `{id}`.
- `EventsAccessTest::eventRoutes()`: uma linha por rota; fique com as
  dos dois lados e corrija o comentário com o total de rotas.
- `show.phtml` do admin: outros botões ao lado de "Editar" (P6
  duplicar, P5 status); fique com todos.

## Armadilhas

- Esquecer `$this->layout = 'print'` antes do `render()`: a página
  sai com a navbar (o teste `assertStringNotContainsString('navbar')`
  pega).
- O layout novo precisa imprimir `$title` e fazer `require $view`;
  sem `$title` no `compact`, warning e 500.
- `onclick="window.print()"` no botão: funciona, mas é JS inline, que
  o professor recusa. Use o `print.js`.
- `window.print()` no Selenium abre um diálogo que trava o teste; o
  Cest troca `window.print` por um espião antes do clique.
- `SELECT users.*` traria `encrypted_password`; liste as colunas.
- Sem `ORDER BY`, a ordem é a da tabela pivô (ordem de inscrição):
  ruim para achar o nome na hora de assinar.
- Rota fora do grupo `admin`: qualquer usuário logado veria e-mails
  de todos os inscritos (o teste de acesso do Participante pega).
- O `<a>` "Voltar" usa `route()`: no layout `print` o helper continua
  disponível (é global).
