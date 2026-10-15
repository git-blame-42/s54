# F2. Teste unitário de middleware

Pedido, como o professor faria na hora: "Escreva testes unitários para
os middlewares `Authenticate` e `Admin`."

Chance: média. Tempo estimado: 20 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção F2.

Este cenário é empilhado sobre o A1: o `redirectTo` original é privado
e dá `exit`, então não há como testar `handle` sem o `BaseMiddleware`
do A1 (com `deny()` e `redirectTo()` protegidos). Branch no clone:
`fvp/f2-teste-unitario-middleware`, com dois commits acima de `main`.
`main` continua igual ao repositório público (`86c81ca`).

| Commit | Mensagem | Origem |
|--------|----------|--------|
| `432adce` | `refactor: Centralize redirect logic in BaseMiddleware` | cenário A1 (mesmo commit, mesmo SHA) |
| `42bc22a` | `test: Add unit tests for Authenticate and Admin middlewares` | este cenário |

## O que mudou (só o commit `42bc22a`)

Nenhum código de produção. Só arquivos novos em
`tests/Unit/App/Middleware/`:

- `AuthenticateDouble.php`: subclasse nomeada de `Authenticate` que
  sobrescreve `redirectTo` e guarda o destino em `public ?string $location`.
- `AdminDouble.php`: o mesmo para `Admin`.
- `MiddlewareTestCase.php`: base abstrata (estende `Tests\TestCase`).
  `setUp` recarrega as rotas (`Router::reset()` + `require
  config/routes.php`), define `REQUEST_METHOD`/`REQUEST_URI` e zera a
  sessão; `tearDown` desfaz tudo. Helpers `request()` e
  `createUser(string $role)`.
- `AuthenticateTest.php`: 3 testes (sem sessão, participante, admin).
- `AdminTest.php`: 3 testes (sem sessão, participante, admin).

5 arquivos, +188 linhas.

## Por que assim

- Dublê em classe NOMEADA, não anônima como no exemplo do catálogo:
  classe anônima atribuída a variável tipada pela classe pai faz o
  PHPStan nível 6 acusar `$location` inexistente, e a classe nomeada
  segue o PSR-4 (`Tests\Unit\App\Middleware\...` em
  `tests/Unit/App/Middleware/`), verificado com
  `composer dump-autoload --strict-psr`.
- Pasta `tests/Unit/App/Middleware/` espelha `app/Middleware/`, como
  `tests/Unit/Lib/...` espelha `lib/...`.
- Estende `Tests\TestCase` porque `Auth::check()` e `Auth::user()`
  consultam a tabela `users`; o `TestCase` cria e apaga o banco.
- Cada teste confere o destino E a mensagem flash (`FlashMessage::get()`
  inteiro, então também prova que nada foi gravado nos casos liberados).
- `Admin` sem sessão não redireciona: é o comportamento atual (quem nega
  é o `Authenticate`, do mesmo grupo de rotas). O teste documenta isso.
- Nomes descritivos `test_<acao>_should_<resultado>`; PSR-12, PHPStan 6,
  comentários em pt-BR, identificadores e commit em inglês
  (`grading-path.md`).

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

Resultado obtido: Unit 77 testes, Integration 9, Acceptance 9 (a base
do A1 e o público são 71 / 9 / 9). Testes novos deste commit:

- `tests/Unit/App/Middleware/AuthenticateTest.php`:
  `test_handle_without_session_should_redirect_to_login_with_flash`,
  `test_handle_with_participant_session_should_not_redirect`,
  `test_handle_with_admin_session_should_not_redirect`.
- `tests/Unit/App/Middleware/AdminTest.php`:
  `test_handle_without_session_should_not_redirect`,
  `test_handle_with_participant_session_should_redirect_to_dashboard_with_flash`,
  `test_handle_with_admin_session_should_not_redirect`.

Só os novos: `./run test tests/Unit/App` (6 testes, 12 asserções).
Prova de que os testes pegam erro: inverter a condição do `Admin`
(`$user->isAdmin()` no lugar de `!$user->isAdmin()`) derruba 2 dos 6.

No navegador: nada muda (só testes). Os testes de aceitação continuam
verdes.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público. `<caminho>` abaixo é `s50/exemplos-fvp/f2-teste-unitario-middleware`
(relativo ao clone do s50).

Primeiro veja se o alvo já tem o A1 (`ls app/Middleware/BaseMiddleware.php`
ou `git log --oneline | grep "Centralize redirect"`). Se tem, pegue só
`42bc22a`. Se não tem, pegue os dois, na ordem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-f2 <caminho>/projeto
git fetch fvp-f2 fvp/f2-teste-unitario-middleware

git cherry-pick 42bc22a             # alvo já tem o A1
git cherry-pick 432adce 42bc22a     # alvo sem o A1: os dois, nesta ordem
git cherry-pick main..fvp-f2/fvp/f2-teste-unitario-middleware   # mesma coisa, por intervalo

git remote remove fvp-f2
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/f2-teste-unitario-middleware:fvp-f2
git cherry-pick 42bc22a             # alvo já tem o A1
git cherry-pick main..fvp-f2        # alvo sem o A1: aplica os dois commits
git branch -D fvp-f2                # opcional, limpa a ref
```

Testado em clone limpo de `86c81ca`: `git cherry-pick main..fvp-f2` e
`git cherry-pick 432adce` seguido de `git cherry-pick 42bc22a` aplicam
sem conflito. O cherry-pick cria commits novos; o SHA no seu repositório
vai ser outro.

Atenção: `git cherry-pick 42bc22a` sozinho, num alvo SEM o A1, também
aplica sem conflito (só cria arquivos), mas os testes não funcionam: o
`redirectTo` da base é privado, o dublê não o sobrescreve e o `exit`
real encerra o PHPUnit no meio. Sempre leve o A1 junto.

### 3. A partir do `solucao.patch` (`git am`)

O patch contém os dois commits (formato mbox). Em alvo sem o A1:

```
git am <caminho>/solucao.patch       # cria os dois commits, mesmas mensagens
```

Em alvo que já tem o A1, o primeiro patch falha. Pule-o:

```
git am <caminho>/solucao.patch
git am --skip                        # pula o A1, aplica o F2
```

Ou gere um patch só do F2 no clone `projeto/` com
`git format-patch -1 42bc22a --stdout > f2.patch` e aplique esse.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `tests/Unit/App/Middleware/AdminDouble.php`, `AuthenticateDouble.php`,
  `AdminTest.php`, `AuthenticateTest.php`: o F1 cria arquivos com os
  mesmos nomes ("both added"). Fique com uma versão de cada dublê
  (são equivalentes) e junte os métodos de teste, sem repetir nomes.
- `tests/Unit/App/Middleware/AdminTest.php`: o A8 (403) e o A12 (admin
  autossuficiente, com `AdminSpy`) também criam esse arquivo. Junte os
  testes e use um dublê só. Com o A12, o teste
  `test_handle_without_session_should_not_redirect` passa a falhar:
  troque-o por um que espera `/login` e a flash do A12. Com o A8, o
  participante recebe 403 em vez de `/dashboard`: ajuste a asserção.
- `app/Middleware/*` (via commit do A1): se o alvo já mudou `Admin` ou
  `Authenticate` sem o A1, resolva mantendo `extends BaseMiddleware` e
  as chamadas a `deny()`.
- O A5 (`GuestDouble`, `GuestTest`) fica na mesma pasta, sem conflito;
  o `GuestTest` pode passar a estender `MiddlewareTestCase`.

Depois de aplicar: não há mudança de schema; rode
`./run composer dump-autoload --strict-psr`, `./run phpcs`,
`./run phpstan` e os testes de novo.

## Armadilhas

- Sem o A1 não há como testar: `redirectTo` privado + `exit` derruba o
  PHPUnit.
- Classe anônima como dublê: o PHPStan acusa a propriedade `$location`
  se a variável for tipada pela classe pai. Use classe nomeada.
- Dublê com nome terminado em `Test.php` vira "teste sem testes" no
  PHPUnit. Use sufixo `Double` (ou `Spy`).
- `route('users.login')` fora do nginx precisa de `Router::reset()` +
  `require config/routes.php` no `setUp`; senão a rota não existe.
- `new Request()` lê `$_SERVER['REQUEST_METHOD']` e `REQUEST_URI`;
  defina os dois no `setUp` e remova no `tearDown`.
- Zere `$_SESSION` antes e depois: `flash` e `user` vazam entre testes do
  mesmo processo.
- `FlashMessage::get()` apaga a flash ao ler; leia uma vez só por teste.
