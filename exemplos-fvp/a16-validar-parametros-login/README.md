# A16. Validar os parâmetros do login sem `?? ''`

Pedido, como o professor faria na hora: "A ausência do parâmetro deve
ser identificada e tratada, não substituída por string vazia. Valide
que `user` tem `email` e `password` antes de consultar o model."

Chance: alta. Tempo estimado: 15 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção 9 (complemento 2, issues da Entrega 4.1), A16 / issues
ice-cream-manager #6 (item 3), reservas-academicas #15 e utfprarena #6
("Tratamento de parâmetros").

Branch no clone: `fvp/a16-validar-parametros-login`. `main` continua
igual ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `906f688` | `fix: Validate login params before querying the user` |

## O que mudou

- `app/Controllers/AuthenticationsController.php`: `authenticate` passa
  `$request->getParam('user')` para o método privado novo
  `credentials(mixed $params): ?array`. Ele devolve
  `['email' => ..., 'password' => ...]` só se `$params` for array e os
  dois campos existirem, forem `string` e não estiverem vazios (nem só
  com espaços). Senão devolve `null`, e o controller responde com a
  flash "E-mail ou senha inválidos!", redireciona para `/login` e faz
  `return`, sem chamar `User::findByEmail`.
- Com credenciais válidas, o fluxo é o de antes: `findByEmail`,
  `authenticate`, `Auth::login`.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`:
  quatro testes novos (sem `user`, sem `email`, senha em branco, e-mail
  como array) e o helper `assertLoginRejected` (sem sessão, `Location:
  /login`, flash genérica). O `setUp` limpa `$_SESSION['flash']` para
  um teste não herdar a mensagem do outro.
- View sem mudança (o `novalidate` é do A9).

## Por que assim

- As três issues criticam o `?? ''`: ele esconde a diferença entre
  chave ausente, `null` e string vazia e manda dados fabricados para a
  consulta e para o `password_verify`. Aqui a forma do payload é
  conferida no limite HTTP e o model só recebe strings reais.
- Mesma mensagem para payload inválido e senha errada: reservas #15
  pede para não revelar quais contas existem.
- `is_string` (e não só `isset`): `user[email][]=x` chega como array e
  daria `TypeError` em `findByEmail(string $email)`.
- Método privado pequeno e tipado (`array{email: string, password:
  string}|null`): o `authenticate` continua curto e o PHPStan sabe que
  `$credentials['email']` é string.
- Parâmetros só pelo `Request::getParam`, sem `$_POST`. PSR-12 e
  PHPStan 6 sem erros; textos em pt-BR; commit em inglês `type: Title`.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
./run composer dump-autoload -o --strict-psr   # depois: ./run composer dump-autoload
```

Resultado obtido: Unit 71 testes, Integration 13, Acceptance 9 (eram
71 / 9 / 9 na base). PHPCS e PHPStan sem erros; PSR-4 estrito ok.
Schema não mudou. Sem a correção, três dos testes novos falham com
`TypeError` em `findByEmail` (`null` e `array`).

Testes novos:

- `AuthenticationsControllerTest::test_authenticate_without_user_param_should_not_login`
- `AuthenticationsControllerTest::test_authenticate_without_email_should_not_login`
- `AuthenticationsControllerTest::test_authenticate_with_blank_password_should_not_login`
- `AuthenticationsControllerTest::test_authenticate_with_non_string_email_should_not_login`

No terminal, com o stack de pé (POST sem `user`):

```
curl -i -X POST http://localhost/login
curl -i -X POST -d 'user[email][]=x&user[password]=1' http://localhost/login
# no clone do s50 (sem porta): docker compose exec -T web curl ...
```

Antes: 500 nos dois. Depois: `302` com `Location: /login`; ao abrir
`/login` aparece "E-mail ou senha inválidos!".

## Relação com outros cenários

- A9 (`a9-login-campos-vazios`) resolve o mesmo 500 com
  `$params['email'] ?? ''`, exatamente o que as issues criticam. Este
  cenário substitui aquele trecho do controller. Se aplicar os dois,
  fique com o `credentials()` deste e com o `novalidate` da view do A9
  (o conflito é só no `authenticate`). O `trim` que o A9 aplicava no
  e-mail pode ir para dentro de `credentials()` se quiser mantê-lo.
- A mesma ideia vale para o `?? null` dos campos obrigatórios em B7
  (`UsersController::create`), para o `?? ''` de B8
  (`ProfileController::update`) e para o `$params['role'] ?? ''` de D6.
  Filtre o payload no limite HTTP e entregue ao model só campos
  conhecidos e escalares:

```php
/**
 * @param array<int, string> $allowed
 * @return array<string, string>
 */
private function scalarParams(mixed $params, array $allowed): array
{
    $clean = [];

    if (!is_array($params)) {
        return $clean;
    }

    foreach ($allowed as $field) {
        if (isset($params[$field]) && is_scalar($params[$field])) {
            $clean[$field] = (string) $params[$field];
        }
    }

    return $clean;
}

// B7: $user = new User($this->scalarParams(
//     $request->getParam('user'),
//     ['name', 'email', 'password', 'password_confirmation']
// ));
```

  Campo ausente não entra no array; o model marca "não pode ser vazio!"
  e a view mostra qual faltou. Nenhum valor inventado, e `role` nunca
  vem do formulário. Em D6, `scalarParams($request->getParam('user'),
  ['role'])['role'] ?? null` deixa claro que perfil ausente é `null`,
  e `changeRole` recusa.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/exemplos-fvp/a16-validar-parametros-login`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a16 s50/exemplos-fvp/a16-validar-parametros-login/projeto
git fetch fvp-a16 fvp/a16-validar-parametros-login
git cherry-pick 906f688
git remote remove fvp-a16
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/a16-validar-parametros-login/solucao.bundle fvp/a16-validar-parametros-login:fvp-a16
git cherry-pick 906f688        # o bundle traz o objeto; o cherry-pick cria commit novo
git branch -D fvp-a16          # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/a16-validar-parametros-login/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/a16-validar-parametros-login/solucao.patch   # só o diff, sem commit
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

- `AuthenticationsController::authenticate`: também é mexido por A4, A9,
  A10, A11, A13, B5, B9, C3, C4 e H1. Mantenha o bloco
  `$credentials = $this->credentials(...)` + `if ($credentials === null)`
  no topo e use `$credentials['email']`/`$credentials['password']` no
  código do outro cenário no lugar de `$params[...]` ou de
  `$email`/`$password` do A9.
- `AuthenticationsControllerTest.php`: testes novos de outros cenários
  no mesmo ponto do arquivo; fique com os dois. Se o A9 também estiver
  aplicado, `test_authenticate_without_params_should_not_login` (A9) e
  `test_authenticate_without_user_param_should_not_login` (este) cobrem
  o mesmo caso; pode manter os dois.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (não há
mudança de schema).

## Armadilhas

- Hoje (base `86c81ca`) um POST sem `user` dá 500: `$params['email']`
  com `$params === null` gera warning, o `ErrorsHandler` transforma
  warning em erro, e mesmo sem ele `findByEmail(null)` lança
  `TypeError`. O mesmo vale para `user[email]` como array.
- O `return` depois do `redirectTo()` no ramo de erro é obrigatório. Em
  produção `redirectTo` dá `exit`; no teste de controller ele só
  imprime `Location:`, e sem o `return` o método seguiria para
  `findByEmail` com `$credentials` nulo.
- `isset` sozinho não basta: aceita array. E `!== ''` sozinho deixa
  passar senha `' '`; por isso a checagem usa `trim(...) === ''`. A
  senha enviada ao `authenticate` continua sem `trim` (espaço pode
  fazer parte de uma senha válida).
- Não crie uma mensagem "e-mail não cadastrado": revela quais contas
  existem. Uma mensagem própria para "preencha e-mail e senha" é
  aceitável, mas aqui ficou a mesma do login errado.
- `FlashMessage` guarda em `$_SESSION['flash']`, que sobrevive entre
  testes; sem o `unset` no `setUp`, a asserção da flash pode passar
  por causa do teste anterior.
