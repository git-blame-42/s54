# PHP: sintaxe essencial e como se virar sem internet

Para criar ou alterar código do projeto só com o que está no disco:
sem internet, sem IA, sem documentação online. Duas partes:

- Parte 1: a sintaxe PHP 8 que o framework e o projeto usam.
- Parte 2: onde está a documentação offline e como achar as coisas.

Pressupõe o `framework-completo.md` para o "onde mexer".

# Parte 1: sintaxe PHP 8 que o projeto usa

## 1.1 Arquivo, namespace, use

```php
<?php

namespace App\Controllers\Admin;

use App\Models\User;
use Core\Http\Controllers\Controller;
use Core\Http\Request;

class UsersController extends Controller
{
}
```

- `<?php` na linha 1; sem `?>` no fim de arquivo só-PHP.
- Namespace = pasta; nome da classe = nome do arquivo (PSR-4).
  `App\Controllers\Admin\UsersController` vive em
  `app/Controllers/Admin/UsersController.php`.
- `use` importa uma classe de outro namespace. Sem o `use`, `User`
  dentro de `App\Controllers\Admin` significaria
  `App\Controllers\Admin\User`, que não existe.
- Classes nativas (`Exception`, `PDO`, `DateTime`) estão no namespace
  global: escreva `\PDO` ou importe com `use PDO;`.
- Funções globais (`route()`, `dd()`, `date()`) não precisam de `use`.

## 1.2 Classes

```php
abstract class Base implements Alguma      // interface: implements
{
    public const LIMITE = 10;                // constante: self::LIMITE
    protected static string $table = 'x';    // estática: static::$table
    private ?User $owner = null;             // tipada, pode ser null

    public function __construct(
        private string $name,                // promoção: cria e atribui
        private int $size = 0
    ) {
    }

    public static function build(): static   // retorna a subclasse
    {
        return new static();
    }

    public function name(): string
    {
        return $this->name;
    }

    abstract public function run(Request $request): void;
}

class Filha extends Base
{
    use AlgumTrait;                          // trait: copia métodos

    public function run(Request $request): void
    {
        parent::__construct('x');            // método da mãe
    }
}
```

- `$this->prop` e `$this->metodo()` para instância; `self::` para a
  própria classe; `static::` para a classe que chamou (é como o Model
  acha `$table` e `$columns` da subclasse); `parent::` para a mãe.
- `new static($row)` cria a subclasse certa (padrão do framework em
  `findById`, `all`, `where`).
- Visibilidade: `public` (todos), `protected` (classe e filhas),
  `private` (só a classe).
- Tipos: `?User` (ou null), `User|null` (igual), `int|string`,
  `mixed`, `void`, `static`, `array`, `bool`, `callable`.
- Argumentos nomeados: `User::paginate(page: 2, per_page: 10)`.
  Ordem livre, pode pular os que têm padrão.
- Métodos mágicos usados pelo framework: `__get($nome)` roda ao ler
  propriedade inexistente ou inacessível; `__set($nome, $valor)` ao
  gravar. É assim que `$user->email` chega em `$attributes`.

## 1.3 Variáveis, operadores, strings, arrays

```php
$a = 5;
$b = '5';
$a == $b;    // true  (compara valor, converte tipo)
$a === $b;   // false (compara valor E tipo)  <- use este
$x = $param ?? 'padrao';          // se null/indefinido, usa padrao
$nome = $user?->name;             // null se $user for null
$v = $flag ?: 'vazio';            // se falso, usa 'vazio'
$n = (int) '12';
$ok = (bool) '0';                 // false
$s = (string) 3;
$f = (float) '1.5';
```

Strings:

```php
'sem interpolacao: $x nao expande';
"com interpolacao: {$user->name} e {$arr['k']} e $simples";
'concat' . ' com ponto ' . $x;
$sql = <<<SQL
    SELECT id FROM {$table} WHERE id = :id
SQL;                               // heredoc: expande variaveis
```

Funções de string mais úteis (`php --rf nome` mostra a assinatura):
`strlen`, `mb_strlen` (acentos), `trim`, `strtolower`, `strtoupper`,
`ucfirst`, `str_contains`, `str_starts_with`, `str_replace`,
`substr`, `sprintf('%05d', $n)`, `explode(',', $s)`,
`implode(', ', $arr)`, `preg_match('/^\d+$/', $s)`,
`number_format($v, 2, ',', '.')`, `htmlspecialchars($s)`.

Arrays:

```php
$lista = [1, 2, 3];
$mapa = ['email' => 'a@b.c', 'role' => 'admin'];
$lista[] = 4;                       // acrescenta no fim
$mapa['name'] = 'Ana';              // cria ou altera chave
isset($mapa['x']);                  // false; nao da aviso
array_key_exists('x', $mapa);       // idem, mas true se valor for null
count($lista);
in_array(2, $lista);
array_keys($mapa);
array_map(fn ($u) => $u->id, $users);          // transforma
array_filter($users, fn ($u) => $u->isAdmin()); // filtra (mantem chaves)
array_values($arr);                            // reindexa 0..n
array_merge($a, $b);                           // junta
[$primeiro, $segundo] = $lista;                // desestrutura
foreach ($mapa as $chave => $valor) {
    echo "{$chave}: {$valor}";
}
compact('user', 'title');   // ['user' => $user, 'title' => $title]
extract($data);             // cria $user e $title a partir do array
```

Funções anônimas:

```php
$dobro = fn ($n) => $n * 2;                 // arrow: 1 expressao,
                                            // enxerga variaveis de fora
// closure: precisa de use para enxergar $base
$soma = function ($n) use ($base) {
    return $n + $base;
};
```

## 1.4 Controle de fluxo

```php
if ($a > 1) {
} elseif ($a === 1) {
} else {
}

$texto = $ok ? 'sim' : 'nao';

$label = match ($role) {                    // estrito (===), sem break
    'admin' => 'Administrador',
    'participant' => 'Participante',
    default => 'Outro',
};

foreach ($users as $i => $user) {
    if ($i > 10) {
        break;
    }
}

for ($i = 0; $i < 10; $i++) {
    echo $i;
}

while ($cond) {
    $cond = proximo();
}

try {
    $x = fazAlgo();
} catch (\PDOException $e) {
    throw new \RuntimeException('msg: ' . $e->getMessage());
} finally {
    limpa();
}

if ($obj instanceof User) {
    echo $obj->name;
}

// prefira retornar cedo a aninhar ifs
if ($user === null) {
    return;
}
```

## 1.5 Datas, senhas, validação, PDO, superglobais

```php
date('Y-m-d H:i:s');                        // agora, formato MySQL
date('d/m/Y H:i', strtotime($str));         // formata string do banco
$d = new \DateTimeImmutable('2026-10-15');
$d->modify('+7 days')->format('Y-m-d');
$d < new \DateTimeImmutable();              // comparacao direta

password_hash($senha, PASSWORD_DEFAULT);    // guardar
password_verify($senha, $hash);             // conferir

filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
filter_var($n, FILTER_VALIDATE_INT) !== false;
ctype_digit($s);
preg_match('/^\d{11}$/', $cpf) === 1;
mb_strlen($senha) >= 6;
```

PDO (padrão do framework; copie de `Model::where`):

```php
$pdo = Database::getDatabaseConn();
$stmt = $pdo->prepare('SELECT id, name FROM users WHERE name LIKE :t');
$stmt->bindValue(':t', "%{$termo}%");
$stmt->execute();
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);   // array de arrays
$row  = $stmt->fetch(\PDO::FETCH_ASSOC);      // uma linha ou false
$stmt->rowCount();
$pdo->lastInsertId();
```

Nunca concatene valor do usuário no SQL; use placeholder. Nome de
tabela e coluna podem ser interpolados porque vêm do código.

Superglobais: `$_SESSION` (sessão; o framework usa em `Auth` e
`FlashMessage`), `$_SERVER` (`REQUEST_METHOD`, `HTTP_ACCEPT`,
`HTTP_REFERER`), `$_FILES['campo']` (upload: `name`, `tmp_name`,
`size`, `error`), `$_REQUEST` (só através do `Request`; o professor
penaliza `$_POST` direto no controller).

## 1.6 Views .phtml

```php
<?= $title ?>                       <!-- igual a <?php echo $title ?> -->

<?php if ($this->currentUser() === null) : ?>
    <a href="<?= route('users.login') ?>">Login</a>
<?php else : ?>
    <span><?= $this->currentUser()->name ?></span>
<?php endif; ?>

<?php foreach ($users as $user) : ?>
    <tr><td><?= $user->email ?></td></tr>
<?php endforeach; ?>

<?php for ($p = 1; $p <= $total; $p++) : ?> ... <?php endfor; ?>

<?php require '_navbar.phtml'; ?>   <!-- parcial, relativo ao layout -->
```

- Sintaxe alternativa (`:` + `endif`/`endforeach`) é a usada no
  projeto. Não misture com chaves no mesmo bloco.
- `$this` dentro da view é o controller. Variáveis vêm do `compact`.
- Saída de dado digitado por usuário: `<?= htmlspecialchars($x) ?>`
  evita HTML injetado. O projeto não escapa hoje; se o professor
  pedir, é isso.

Formulários:

```php
<form action="<?= route('users.create') ?>" method="POST" novalidate>
  <input type="hidden" name="_method" value="PUT">   <!-- se PUT -->
  <input type="text" name="user[name]" value="<?= $user->name ?>">
  <select name="user[role]">
    <option value="admin" <?= $user->role === 'admin' ? 'selected' : '' ?>>
      Admin
    </option>
  </select>
  <input type="checkbox" name="user[active]" value="1"
         <?= $user->active ? 'checked' : '' ?>>
  <div class="invalid-feedback"><?= $user->errors('name') ?? '' ?></div>
  <button type="submit">Salvar</button>
</form>
<!-- upload: method="POST" enctype="multipart/form-data" -->
```

## 1.7 Estilo que o CI exige (PSR-12, resumo prático)

- `<?php`, linha vazia, `namespace`, linha vazia, `use`s, linha vazia,
  classe. Um arquivo, uma classe.
- Chave de classe e de método em linha própria. Chave de `if`, `for`,
  `foreach`, `function` anônima na mesma linha. `} else {` junto.
- 4 espaços, nunca tab. Linha até 130 colunas (regra do projeto).
- Métodos e variáveis em `camelCase`; só em `*Test.php` e `*Cest.php`
  pode `snake_case`. Constantes `UPPER_CASE`. `true`, `false`, `null`
  em minúsculas.
- Espaço depois de vírgula e em volta de `=`, `===`, `=>`, `.`.
- Conserta sozinho: `./run phpcbf app/Models/User.php`. Confere:
  `./run phpcs app/Models/User.php`.

PHPStan nível 6 (também no CI):

- Tipo em todo parâmetro e retorno: `public function x(int $id): ?User`.
- Array precisa de docblock: `/** @return array<User> */`,
  `/** @param array<string, mixed> $params */`.
- Coluna nova no model: `@property string|null $phone` no docblock da
  classe, senão `$user->phone` é "propriedade indefinida".
- Confere: `./run phpstan` (demora; rode no fim).

## 1.8 Como ler os erros mais comuns

- `Parse error: syntax error, unexpected ...`: `;` ou `}` faltando,
  aspas abertas, `->` no lugar de `::`. A linha indicada é onde o
  parser desistiu; o erro costuma estar logo acima.
- `Class "App\X" not found`: `use` faltando, namespace errado ou
  arquivo com nome diferente da classe. Classe nova em pasta PSR-4
  não precisa de `composer dump-autoload`.
- `Call to undefined method X::y()`: nome errado, ou `::` em método de
  instância (ou `->` em estático).
- `Call to a member function x() on null`: `findById`/`findBy`
  devolveu `null` e você usou direto. Teste antes.
- `Undefined variable $title` / `Undefined array key "x"`: faltou no
  `compact`, ou use `$arr['x'] ?? null`.
- `Cannot access protected property`: leia por método público.
- `TypeError: Return value must be of type X, null returned`: a
  função declara `X` mas devolveu `null`; troque para `?X` ou trate.
- `Too few arguments to function`: chamada sem um parâmetro
  obrigatório.
- `Property x not found in App\Models\Y` (do framework): coluna fora
  de `$columns` ou relacionamento com nome que não bate.
- `SQLSTATE[42S22]: Column not found`: `schema.sql` e `$columns`
  divergem, ou faltou `./run db:reset`.
- `SQLSTATE[HY000] [2002] Connection refused`: MySQL ainda subindo;
  espere 20 s e repita.

# Parte 2: se virar sem internet

## 2.1 O que precisa estar no disco (confira antes de precisar)

- Projeto clonado com `vendor/` presente (`./run composer install`
  feito quando havia rede). Sem rede, `composer install` não roda.
- Imagens Docker já baixadas: `docker images` deve listar `nginx`,
  `php` (build local do Dockerfile), `mysql:8.4.0`,
  `selenium/standalone-chrome-debug` e `composer:2.7.2`.
- `.env` criado (`cp .env.example .env`).
- `./run codecept build` já executado (gera
  `tests/Support/_generated/AcceptanceTesterActions.php`).

Efeito colateral sem internet: o layout carrega Bootstrap de um CDN
(`application.phtml`). As páginas aparecem sem estilo e o dropdown
não anima; os links ficam visíveis e tudo funciona, inclusive os
testes de aceitação. Não é bug seu.

## 2.2 Documentação do PHP sem internet

O próprio binário do PHP documenta assinaturas:

```
php --rf str_contains        # funcao: parametros e tipos
php --rc PDOStatement         # classe: todos os metodos
php --rc DateTimeImmutable
php --re pdo_mysql            # extensao inteira
php -r 'var_dump(date("Y-m-d"));'    # testar uma expressao
php -a                        # REPL interativo
php -l app/Models/User.php    # so sintaxe, sem executar
```

Sem PHP no host, use o do container:

```
docker compose exec php php --rf array_map
docker compose exec php php -l app/Models/User.php
./run php:console             # REPL dentro do container
```

No REPL do container dá para usar o framework de verdade (é o que o
`populate.php` faz na linha de comando):

```
php > require 'config/bootstrap.php';
php > var_dump(App\Models\User::findByEmail('admin@example.com'));
php > var_dump(App\Models\User::where(['role' => 'admin']));
```

Listas por assunto, quando não lembra o nome:

```
php -r 'print_r(get_defined_functions()["internal"]);' | grep str_
php -r 'print_r(get_class_methods("PDO"));'
php -r 'print_r(array_filter(get_declared_classes(),
        fn ($c) => str_starts_with($c, "Date")));'
```

## 2.3 Documentação das bibliotecas: está no vendor/

Tudo que o projeto usa tem o código-fonte com docblocks em `vendor/`.

- PHPUnit, todas as asserções (160 métodos):
  `vendor/phpunit/phpunit/src/Framework/Assert.php`
  ```
  grep -o 'public static function assert[A-Za-z]*' \
      vendor/phpunit/phpunit/src/Framework/Assert.php | sort -u
  ```
  Mais usadas no projeto: `assertEquals`, `assertTrue`, `assertFalse`,
  `assertNull`, `assertNotNull`, `assertCount`,
  `assertStringContainsString`, `assertMatchesRegularExpression`,
  `assertEmpty`, `assertArrayHasKey`, `assertInstanceOf`.

- Codeception, ações do `$page` com docblock e exemplo:
  `tests/Support/_generated/AcceptanceTesterActions.php` (gerado) e
  `vendor/codeception/module-webdriver/src/Codeception/Module/WebDriver.php`.
  Mais usadas: `amOnPage`, `see`, `dontSee`, `seeCurrentUrlEquals`,
  `seeInCurrentUrl`, `fillField`, `click`, `selectOption`,
  `checkOption`, `attachFile`, `seeElement`, `seeInField`,
  `seeLink`, `wait`, `grabTextFrom`. Seletores: texto do link/botão,
  CSS (`'#id'`, `'.classe'`), XPath (`'//table//tr[1]//td[2]'`).
  Helpers do projeto: `tests/Support/Helper/LoginHelper.php`
  (`login`, `logout`).

- Guzzle (testes de acesso):
  `vendor/guzzlehttp/guzzle/src/ClientInterface.php` e
  `vendor/guzzlehttp/guzzle/src/Client.php`. Métodos `get`, `post`,
  `put`, `delete`; opções `form_params`, `multipart`, `cookies`,
  `allow_redirects`, `headers`. Resposta: `getStatusCode()`,
  `getHeaderLine('Location')`, `(string) getBody()`.

- Bootstrap: sem internet não há referência local. Use só classes
  que já aparecem nas views do projeto (`table`, `btn btn-primary`,
  `form-control`, `mb-3`, `alert alert-danger`, `invalid-feedback`,
  `nav-link`, `card`, `d-flex`).

## 2.4 O projeto é a documentação: de onde copiar

Quero fazer X: abra Y e copie a forma.

- Controller simples: `app/Controllers/DashboardController.php`.
- Controller com fluxo (ler param, decidir, flash, redirecionar):
  `app/Controllers/AuthenticationsController.php`.
- Controller em subpasta/namespace: `app/Controllers/Admin/`.
- Model, validações, método estático de busca, `__set` customizado:
  `app/Models/User.php`.
- Regra de validação nova: `lib/Validations.php` (copie `notEmpty`).
- Middleware: `app/Middleware/Admin.php`; apelido em `config/App.php`.
- Rota, grupo, nome: `config/routes.php`.
- View com formulário: `app/views/authentications/new.phtml`.
- Layout, navbar, flash: `app/views/layouts/`.
- Paginação: `lib/Paginator.php` (métodos públicos) e
  `app/views/paginator/_pages.phtml`; uso:
  `$paginator->renderPagesNavigation()`.
- Relacionamentos: assinaturas em `core/Database/ActiveRecord/`
  (`HasMany::new/findById/paginate`, `BelongsToMany::get/count`).
- Teste unitário de model: `tests/Unit/Models/Users/UserTest.php`.
- Teste de controller: `tests/Integration/Controllers/
  AuthenticationsControllerTest.php`.
- Teste de acesso: `tests/Integration/Access/DashboardAccessTest.php`.
- Teste de aceitação: `tests/Acceptance/authentications/LoginCest.php`.
- Dados iniciais: `database/Populate/populate.php`.

A API pública do framework em um comando:

```
grep -n 'public .*function' core/Http/Controllers/Controller.php \
    core/Http/Request.php core/Database/ActiveRecord/*.php \
    lib/*.php lib/Authentication/Auth.php
```

## 2.5 Achar coisas: grep, find, git

```
grep -rn "Acesso restrito" app tests        # onde um texto aparece
grep -rn "function paginate" core lib       # onde um metodo e definido
grep -rln "route('users.login')" app tests  # arquivos que usam a rota
grep -rn "example.com" tests                # strings que testes conferem
find app -name '*.phtml'                    # todas as views
git grep -n "isAdmin"                       # grep so no que esta no git
```

Git como memória do projeto (funciona offline):

```
git log --oneline                 # historico
git show --stat <hash>            # arquivos que um commit tocou
git show <hash> -- caminho        # diff de um arquivo naquele commit
git log -p -S 'registerLogin' --  # quando um trecho apareceu
git status; git diff              # o que voce mudou ate agora
git checkout -- arquivo           # desfaz um arquivo
git stash; git stash pop          # guarda e recupera mudancas
```

O commit da Entrega 4.1 (`git log --oneline`, o `[E4.1] feat: ...`)
toca todos os arquivos de uma feature completa: rotas, controller,
middleware, model, views, schema, populate e os quatro tipos de
teste. `git show --stat` dele é a receita.

## 2.6 Banco sem ferramenta gráfica

```
./run db:console
mysql> SHOW TABLES;
mysql> DESCRIBE users;
mysql> SELECT id, email, role, last_login_at FROM users;
mysql> SELECT * FROM users\G        -- um campo por linha
mysql> exit
```

Mudou o `schema.sql`: `./run db:reset && ./run db:populate`. O banco
de teste (`eventos_test`) é criado e apagado pelos próprios testes;
não precisa mexer nele.

## 2.7 Depurar sem debugger

- `dd($var1, $var2)`: imprime e para. Tire antes do commit.
- Página de erro do framework: já mostra mensagem, arquivo, linha e
  stack trace. Leia de cima para baixo até a primeira linha em `app/`.
- Logs: `docker compose logs php --tail 50` (PHP-FPM),
  `cat log/nginx/error.log` (nginx).
- Um teste só: `./run test tests/Unit/Models/Users/UserTest.php`
  ou `./run test tests/Unit/Models/Users/UserTest.php --filter
  test_set_name`.
- Aceitação com detalhe:
  `./run codecept run acceptance LoginCest --debug`. Falhas deixam
  HTML e screenshot em `tests/Acceptance/_output/`.
- Estilo: `./run phpcbf <arquivo>` conserta; `./run phpcs <arquivo>`
  lista o que sobrou.

## 2.8 Roteiro offline em dez linhas

1. `git status` limpo; `./run up -d`; `./run db:reset && ./run
   db:populate`; `./run test tests/Unit` verde.
2. Entenda o pedido como "quero mudar X".
3. Ache o arquivo-modelo em 2.4 e o lugar certo em 2.5.
4. Copie o teste mais parecido; adapte; rode; deve falhar.
5. Escreva o código copiando a forma do arquivo-modelo.
6. Dúvida de função: `php --rf`. Dúvida de classe: `php --rc`.
   Dúvida de biblioteca: `grep` no `vendor/`.
7. Rode o teste; abra no navegador.
8. `./run phpcbf` nos arquivos tocados; `./run phpcs`.
9. `git diff` para revisar; sem `dd()`, sem `var_dump`.
10. `git add -A && git commit -m "feat: ..."` (inglês).
