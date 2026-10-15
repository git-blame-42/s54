# D5. Contadores com all() e where() no painel administrativo

Pedido, como o professor faria na hora: "Mostrem no painel do admin
quantos usuários existem, quantos são administradores e quantos são
participantes, usando `all()` e `where()` do ActiveRecord."

Chance: alta (trivial, e `where` é item da rubrica 4.2 que o código
entregue ainda não usa). Tempo estimado: 15 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção D5.

Branch no clone: `fvp/d5-contadores-painel-admin`. `main` continua
igual ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `dc2f0b7` | `feat: Show user counters on admin dashboard` |

## O que mudou

- `app/Models/User.php`: novo
  `public static function countByRole(string $role): int`,
  que devolve `count(static::where(['role' => $role]))`.
- `app/Controllers/Admin/DashboardController.php`: `index()` calcula
  `$totalUsers = count(User::all())`,
  `$totalAdmins = User::countByRole('admin')`
  e `$totalParticipants = User::countByRole('participant')` e passa os
  três para a view junto com `$title`.
- `app/views/admin/dashboard/index.phtml`: mantém o "Olá, ...!" e
  acrescenta uma lista com "Usuários cadastrados: N",
  "Administradores: N" e "Participantes: N".
- `tests/Unit/Models/Users/UserTest.php`: dois testes novos
  (`where` por perfil e `countByRole`).
- `tests/Integration/Controllers/Admin/DashboardControllerTest.php`
  (novo): admin logado vê os três contadores com os números certos.
- `tests/Acceptance/admin/AdminDashboardCest.php`: `accessAdminAsAdmin`
  passa a conferir os contadores na página.

Resultado: 6 arquivos, +89 / -1 linhas.

## Por que assim

- `where` é item explícito da rubrica 4.2 (checklist de framework:
  `save`, `all`, `findById`, "new: `where`") e o código
  entregue na 4.1 não o chama diretamente em lugar nenhum (só
  indiretamente via `findBy` em `findByEmail`). Este cenário deixa um
  uso visível e testado (`grading-path.md`).
- `where` real (`core/Database/ActiveRecord/Model.php`):
  `public static function where(array $conditions): array`; monta
  `col = :col` unidos por `AND` e devolve uma lista de models. Por isso
  a contagem é `count(...)` sobre o array.
- Controller fino: a regra "contar por perfil" fica no model
  (`User::countByRole`), como o catálogo sugere para quando o professor
  pedir controller magro; o controller só junta os números para a view.
- Autorização continua no middleware `admin` da rota `/admin`; nada de
  checagem de perfil na ação.
- Textos de interface em pt-BR; identificadores em inglês; commit em
  inglês no formato `type: Title`.
- PSR-12 e PHPStan nível 6 sem avisos; `countByRole` com tipos em
  parâmetro e retorno.
- Testes nas três camadas: unit (model), controller (render com sessão)
  e aceitação (Selenium).

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run composer dump-autoload --optimize --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido (na VM, via `/tmp/fvp/vmrun.sh`): Unit 73 testes,
Integration 10, Acceptance 9 (eram 71 / 9 / 9 na base). Testes novos:

- `UserTest::test_where_should_filter_by_role` e
  `UserTest::test_count_by_role_should_count_users_with_the_role`
  (`tests/Unit/Models/Users/UserTest.php`).
- `DashboardControllerTest::test_index_should_show_user_counters`
  (`tests/Integration/Controllers/Admin/DashboardControllerTest.php`):
  1 admin + 2 participantes; confere "Usuários cadastrados: 3",
  "Administradores: 1", "Participantes: 2".
- `AdminDashboardCest::accessAdminAsAdmin` ganhou três `see` (2 usuários,
  1 admin, 1 participante). Não é teste novo; a contagem continua 9.

Não há mudança de schema nem de populate.

No navegador: `http://localhost`, `./run db:populate`, logue como
`admin@example.com` / `123456` e abra `/admin`: aparecem "Usuários
cadastrados: 2", "Administradores: 1", "Participantes: 1".

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/d5-contadores-painel-admin` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-d5 <caminho>/projeto
git fetch fvp-d5 fvp/d5-contadores-painel-admin
git cherry-pick dc2f0b7
git remote remove fvp-d5
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/d5-contadores-painel-admin:fvp-d5
git cherry-pick dc2f0b7        # o bundle traz o objeto dc2f0b7
git branch -D fvp-d5           # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O
cherry-pick cria um commit novo no alvo (o SHA pode ser outro).

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

Testado: `git am` aplica limpo em `86c81ca`.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `tests/Integration/Controllers/Admin/DashboardControllerTest.php`:
  F1 (testes que faltam) cria o mesmo arquivo (conflito "both added").
  Junte as duas classes num arquivo só, mantendo todos os métodos
  `test_...` e um único conjunto de `use`.
- `app/Models/User.php` e `tests/Unit/Models/Users/UserTest.php`: quase
  todo cenário B (B1, B2, B3, B5, B6, B9), A7, D3, D4, D6, D8, F4 e H2
  acrescentam métodos no fim da classe ou no meio do model. Mantenha os
  dois lados; `countByRole` é independente.
- `app/views/admin/dashboard/index.phtml`: C5 (XSS) troca o `<?= ...->name ?>`
  por `htmlspecialchars(...)`. Fique com a linha do "Olá" do C5 e
  acrescente a `<ul>` deste cenário.
- `tests/Acceptance/admin/AdminDashboardCest.php`: A4, A8, A11, F3 e H2
  mexem no `_before` ou em `accessAdminAsAdmin`. Mantenha a versão do
  alvo e só acrescente os três `$page->see(...)` dos contadores.
- Com H2 (constantes de perfil) ou A7 (perfil `organizer`): troque as
  strings `'admin'`/`'participant'` no controller pelas constantes e, se
  houver novo perfil, acrescente o contador dele.

Depois de aplicar: rode `./run phpcs`, `./run phpstan` e os testes de
novo. Não há mudança de schema.

## Armadilhas

- Mantenha o "Olá, <nome>!" na view: `AdminDashboardCest::accessAdminAsAdmin`
  confere esse texto.
- `where([])` monta `WHERE ` vazio e quebra o SQL; `where` só faz
  igualdade com `AND`. Intervalo ou `LIKE` exige SQL próprio (ver D4).
- `count(User::where(...))` carrega todos os registros só para contar.
  Serve para a disciplina; se o professor pedir eficiência, troque o
  corpo de `countByRole` por um `SELECT COUNT(*) ... WHERE role = :role`
  com `Database::getDatabaseConn()`.
- Os números do teste de aceitação dependem do `_before` do Cest (1
  admin + 1 participante); se outro cenário acrescentar usuários lá,
  ajuste os valores esperados.
- No teste de controller, o admin precisa estar logado (`Auth::login`),
  senão `$this->currentUser()->name` na view dá erro em `null`.
