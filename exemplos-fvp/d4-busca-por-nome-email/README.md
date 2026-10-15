# D4. Busca por nome ou e-mail

Pedido, como o professor faria na hora: "Quero buscar usuários por um
pedaço do nome ou do e-mail."

Chance: baixa. Tempo estimado: 20 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção D4.

Branch no clone: `fvp/d4-busca-por-nome-email`. `main` continua igual
ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `783330d` | `feat: Search users by name or email` |

## O que mudou

- `app/Models/User.php`: novo `User::search(string $term): array`, com
  SQL próprio (`WHERE name LIKE :term OR email LIKE :term ORDER BY
  name`), bind de `"%{$term}%"` e mapeamento `new static($row)` como em
  `Model::all()`. Imports `Core\Database\Database` e `PDO`.
- `tests/Unit/Models/Users/UserSearchTest.php` (novo): quatro testes
  do método, estendendo `Tests\TestCase` (cria e derruba o banco de
  teste a cada teste).

## Por que assim

- `Model::where` só faz igualdade (`=`); `LIKE` exige SQL próprio no
  model, não no controller (MVC: consulta ao banco fica no model).
- SQL preparado com bind: o termo do usuário nunca é interpolado no
  SQL, evitando SQL injection (página "Segurança" do Moodle). Só nomes
  de tabela/colunas, fixos na classe, entram por interpolação, como no
  próprio `Model::all()`.
- Placeholder `:term` repetido funciona porque o PDO do MySQL emula
  prepares por padrão; não foi preciso usar dois placeholders.
- Testes na camada do model, nomes `test_search_should_...`, um arquivo
  novo para não mexer no `setUp` do `UserTest` (que o professor citou
  na issue #9).
- PSR-12 e PHPStan nível 6 (`@return array<User>`), PSR-4 no teste,
  identificadores em inglês, comentários em pt-BR, commit em inglês
  `type: Title` (`grading-path.md`).

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

O schema não mudou; não precisa de `./run db:reset`.

Resultado obtido: Unit 75 testes, Integration 9, Acceptance 9 (eram
71 / 9 / 9 na base). Testes novos, em
`tests/Unit/Models/Users/UserSearchTest.php`:

- `test_search_should_match_only_by_name`
- `test_search_should_match_only_by_email`
- `test_search_should_return_empty_array_when_nothing_matches`
- `test_search_should_return_results_ordered_by_name`

No navegador: nada muda, o cenário só cria o método no model. Para ver
na tela, ligue-o à listagem do D1 (seção "Como ligar à tela").
O comportamento fica coberto pelos quatro testes acima.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/exemplos-fvp/d4-busca-por-nome-email`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-d4 s50/exemplos-fvp/d4-busca-por-nome-email/projeto
git fetch fvp-d4 fvp/d4-busca-por-nome-email
git cherry-pick 783330d
git remote remove fvp-d4
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/d4-busca-por-nome-email/solucao.bundle fvp/d4-busca-por-nome-email:fvp-d4
git cherry-pick 783330d        # o bundle traz o objeto 783330d; o cherry-pick cria um commit novo, com outro SHA
git branch -D fvp-d4           # opcional, limpa a ref
```

Testado: clone limpo do público em `86c81ca`, fetch do bundle e
cherry-pick aplicaram sem conflito.

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/d4-busca-por-nome-email/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/d4-busca-por-nome-email/solucao.patch   # só o diff, sem commit
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

- `app/Models/User.php`: B1, B2, B3, B5 e A7 também mexem nele (novas
  colunas em `$columns`, `@property`, validações, métodos novos). O
  conflito costuma ser no bloco de `use` do topo e na região antes de
  `isAdmin()`. Resolva mantendo os dois lados: os imports
  `Core\Database\Database` e `PDO` mais os do outro cenário, e o método
  `search` ao lado dos métodos novos.
- `tests/Unit/Models/Users/UserSearchTest.php` é arquivo novo; não
  conflita. Se outro cenário tornar uma coluna obrigatória (validação
  nova em `User::validates`), ajuste o `createUser` do teste para
  preenchê-la, senão o `save()` falha e os testes de busca quebram.

Depois de aplicar: o schema não muda, então não precisa de
`./run db:reset`; rode os testes e o `./run phpcs` de novo.

## Armadilhas

- Se uma coluna nova entrar em `User::$columns` (B1/B2), o `search` já
  a seleciona, porque monta a lista a partir de `$columns`.
- Não interpole `$term` no SQL; sempre `bindValue`. `%` e `_` digitados
  pelo usuário funcionam como curingas do `LIKE`; para o FVP isso é
  aceitável.
- Se o PDO for configurado com `PDO::ATTR_EMULATE_PREPARES => false`,
  o `:term` repetido dá erro `Invalid parameter number`; aí use dois
  placeholders (`:name_term`, `:email_term`) com o mesmo valor.
- A ordenação usa a collation da tabela (MySQL, sem diferenciar
  maiúsculas); o teste de ordem usa nomes que não dependem disso.
- O teste estende `Tests\TestCase`; sem `parent::setUp()` o banco de
  teste não existe e o `save()` falha.

## Como ligar à tela

Esboço sobre o D1 (`/admin/users` em `App\Controllers\Admin\UsersController`):

```php
public function index(Request $request): void
{
    $q = trim((string) $request->getParam('q', ''));
    $users = $q === '' ? User::all() : User::search($q);
    $title = 'Usuários';
    $this->render('admin/users/index', compact('users', 'q', 'title'));
}
```

```php
<!-- app/views/admin/users/index.phtml, acima da tabela -->
<form method="GET" action="<?= route('admin.users.index') ?>" class="d-flex gap-2 mb-3">
  <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Nome ou e-mail">
  <button type="submit" class="btn btn-primary">Buscar</button>
</form>
```

Com busca ativa, a paginação do D1 some (o `search` devolve tudo); um
teste de controller/aceitação deve cobrir `/admin/users?q=...`.
