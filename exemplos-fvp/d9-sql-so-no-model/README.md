# D9. SQL só no model: métodos estáticos nomeados

Pedido, como o professor faria na hora: "Mostre no painel do admin
quantos administradores existem e a lista de usuários em ordem de nome.
Onde fica a consulta?" Ou, ao contrário, ele mostra um controller com
`SELECT` e pede para mover.

Chance: alta como pergunta, média como alteração. Tempo estimado:
15 min. Catálogo: `fvp-possiveis-modificacoes.md`, seção 9 (complemento 2,
issues da Entrega 4.1), D9 / issue utfprarena #6.

Origem (utfprarena #6, 1º item): "os controllers não devem conter
consultas SQL diretamente [...] Consultas como `SELECT * FROM courts`
[...] devem ser realizadas na camada de Model. O controller deve se
concentrar em receber as requisições, coordenar as operações e retornar
as respostas."

A base (`86c81ca`) já não tem SQL em controller. O exemplo mostra o
padrão para quando surgir uma consulta que o `Model` não oferece.

Branch no clone: `fvp/d9-sql-so-no-model`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `630c5a6` | `refactor: Move user queries into named model methods` |

## O que mudou

- `app/Models/User.php`: dois métodos estáticos novos (imports
  `Core\Database\Database` e `PDO`):
  - `orderedByName(): array` (`@return array<User>`):
    `SELECT id, {$columns} FROM {$table} ORDER BY name`, com `prepare` +
    `execute` e `array_map(fn ($row) => new static($row), ...)`. Devolve
    objetos `User`, não arrays.
  - `countByRole(string $role): int`:
    `SELECT COUNT(*) FROM {$table} WHERE role = :role`, com
    `bindValue(':role', $role)` e `(int) $stmt->fetchColumn()`.
- `app/Controllers/Admin/DashboardController.php`: `index` faz uma linha
  por consulta (`$users`, `$totalAdmins`, `$totalParticipants`) e
  renderiza. Nenhum SQL, `Database::` ou `PDO`.
- `app/views/admin/dashboard/index.phtml`: contadores "Administradores" e
  "Participantes" e uma tabela (nome, e-mail, perfil) em ordem de nome,
  com `htmlspecialchars` nas células.
- `tests/Unit/Models/Users/UserTest.php`: `test_ordered_by_name_should_sort_ascending`
  e `test_count_by_role`.
- `tests/Integration/Controllers/Admin/DashboardControllerTest.php` (novo):
  a página traz os contadores e os nomes na ordem.
- `tests/Acceptance/admin/AdminDashboardCest.php`:
  `seeCountersAndUsersOrderedByName`.

Antes (o erro típico, adaptado ao nosso projeto; não escreva):

```php
// ERRADO: SQL no controller, e a view recebe arrays
public function index(): void
{
    $pdo = Database::getDatabaseConn();
    $rows = $pdo->query('SELECT * FROM users')->fetchAll(PDO::FETCH_ASSOC);
    $title = 'Painel Administrativo';
    $this->render('admin/dashboard/index', compact('rows', 'title'));
}
```

Depois:

```php
public function index(): void
{
    $users = User::orderedByName();
    $totalAdmins = User::countByRole('admin');
    $totalParticipants = User::countByRole('participant');
    $title = 'Painel Administrativo';
    $this->render(
        'admin/dashboard/index',
        compact('users', 'totalAdmins', 'totalParticipants', 'title')
    );
}
```

## Por que assim

Regra de decisão, nesta ordem:

1. Dá com o que o `Model` já tem? `all()`, `where([...])` (só igualdade,
   ligada por AND), `findBy`, `findById`, `exists`, `paginate`. Use e
   pronto.
2. Não dá (`ORDER BY`, `LIKE`, `COUNT`, `JOIN`)? Método estático no
   model, com nome que diz o que faz, `prepare` + `bindValue`, devolvendo
   objetos (`new static($row)`).
3. O controller fica com uma linha por consulta.

- O nome diz a intenção: `orderedByName()` e `countByRole('admin')` se
  leem como a regra de negócio, sem abrir o SQL. Se a ordenação mudar
  (sobrenome, data), muda um lugar só, e quem chama não percebe.
- O controller recebe, coordena e responde, como pede a issue. Regra de
  negócio também é do model: `isAdmin()` e `registerLogin()` já seguem o
  padrão; o controller pergunta, não decide.
- O teste do model não precisa de controller: chama `User::orderedByName()`
  direto contra o banco de teste. Fica rápido, sem sessão nem HTML, e
  falha exatamente no ponto da consulta. O teste de integração só
  confere que o controller passa os dados para a view.
- `countByRole` conta no banco (`COUNT(*)`), sem carregar os usuários.
- `{$table}` e `{$columns}` vêm da classe, não da requisição; o valor
  do usuário (`$role`) entra só por `bindValue`.
- PSR-12 e PHPStan 6 sem erros; textos em pt-BR; commit em inglês
  `type: Title`.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
grep -rn "SELECT\|Database::\|PDO" app/Controllers/   # não devolve nada
```

Resultado obtido: Unit 73 testes, Integration 10, Acceptance 10 (eram
71 / 9 / 9 na base). PHPCS e PHPStan sem erros; `composer
dump-autoload -o --strict-psr` ok. Schema não mudou. O `grep` acima não
devolve nada.

Testes novos:

- `UserTest::test_ordered_by_name_should_sort_ascending`
- `UserTest::test_count_by_role`
- `DashboardControllerTest::test_index_should_show_counters_and_users_ordered_by_name`
- `AdminDashboardCest::seeCountersAndUsersOrderedByName`

No navegador (após `./run db:populate`): entre como `admin@example.com`
/ `123456` e abra `http://localhost/admin`. Aparecem "Administradores: 1",
"Participantes: 1" e a tabela com "Administrador" antes de "Usuário".

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/exemplos-fvp/d9-sql-so-no-model`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-d9 s50/exemplos-fvp/d9-sql-so-no-model/projeto
git fetch fvp-d9 fvp/d9-sql-so-no-model
git cherry-pick 630c5a6
git remote remove fvp-d9
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/d9-sql-so-no-model/solucao.bundle fvp/d9-sql-so-no-model:fvp-d9
git cherry-pick 630c5a6        # o bundle traz o objeto; o cherry-pick cria commit novo
git branch -D fvp-d9           # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/d9-sql-so-no-model/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/d9-sql-so-no-model/solucao.patch   # só o diff, sem commit
```

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- D5 (contadores no painel) mexe nos mesmos seis arquivos. O maior
  choque é `User::countByRole`: D5 usa `count(static::where([...]))`,
  aqui é `COUNT(*)` com `bindValue`. Fique com um método só (o daqui
  conta no banco). No controller e na view, junte as variáveis
  (`$totalUsers` do D5 continua valendo); em
  `DashboardControllerTest.php` (os dois criam o arquivo) e
  `UserTest.php`, fique com os testes dos dois, renomeando o que tiver
  nome repetido.
- D1 e D7 não tocam nos arquivos deste cenário (eles criam
  `Admin\UsersController` e mexem em `Model::paginate`/`Paginator`).
  Aplicam sem conflito. Se você já tem D1+D7, a listagem paginada por
  nome fica em `/admin/users`, e a tabela daqui no painel pode virar só
  os contadores.
- D4 (busca por nome/e-mail) e H2 (constantes de perfil) também
  acrescentam métodos ou constantes em `User.php`; fique com tudo. Com
  H2 aplicado, troque `'admin'`/`'participant'` por `User::ROLE_ADMIN` e
  `User::ROLE_PARTICIPANT` no controller.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (não há
mudança de schema).

## Armadilhas

- `SELECT *` com `new static($row)` quebra aqui: `users` tem
  `created_at` e `updated_at`, que não estão em `$columns`, e
  `Model::__set` lança "Property created_at not found". Sempre
  `SELECT id, {$columns}`, como `all()` faz.
- Nunca `"WHERE role = '{$role}'"`: o que vem de fora entra por
  `bindValue`.
- PHPStan nível 6 cobre `app/`: método que devolve array precisa de
  `@return array<User>`.
- `paginate()` não aceita `ORDER BY` (o `Paginator` monta o próprio
  SQL). Listagem ordenada e paginada ao mesmo tempo exige mexer em
  `lib/Paginator.php` (é o que D7 faz); diga isso antes de prometer.
- `Database::getDatabaseConn()` abre uma conexão por chamada. Para o FVP
  está bom; não "otimize" o `core/`.
- O `setUp` do `UserTest` já cria "User 1" e "User 2": a lista esperada
  em `test_ordered_by_name_should_sort_ascending` inclui os dois.
