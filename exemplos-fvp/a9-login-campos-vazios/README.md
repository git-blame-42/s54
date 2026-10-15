# A9. Login com campos vazios: novalidate e controller robusto

Pedido, como o professor faria na hora: "Mostre o login com dados
incorretos, deixando os campos em branco. O servidor tem que responder
com a mensagem de erro, não o navegador."

Chance: alta. Tempo estimado: 15 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção A9.

Branch no clone: `fvp/a9-login-campos-vazios`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `1819996` | `fix: Handle empty login form without HTML5 validation` |

## O que mudou

- `app/views/authentications/new.phtml`: `novalidate` no `<form>` e
  `required` removido dos dois inputs. O `type="email"` fica (teclado
  certo no celular); com `novalidate` ele não bloqueia o envio.
- `app/Controllers/AuthenticationsController.php`: `authenticate` lê
  `$request->getParam('user', [])`, monta
  `$email = trim($params['email'] ?? '')` e
  `$password = $params['password'] ?? ''`, e só chama `User::findByEmail` com
  e-mail não vazio. POST sem `user[...]` ou com campos vazios cai no `else`
  (flash "E-mail ou senha inválidos!" e redirect para `/login`) em vez de 500.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`: três
  testes novos (sem parâmetros, campos vazios, e-mail com espaços).
- `tests/Integration/Access/LoginAccessTest.php` (novo): `GET /login`
  traz `novalidate`; `POST /login` sem corpo e com campos vazios
  responde 302 para `/login`.
- `tests/Acceptance/authentications/LoginCest.php`: `loginWithEmptyFields`
  envia o formulário vazio no navegador e vê a mensagem.

## Por que assim

- Feedback 2025-2 do professor: demonstrar "dados incorretos" com
  validação no servidor; o HTML5 bloqueava o envio
  (`docs/grading-path.md`, "2025-2 feedback still applies").
- `novalidate` + validação no servidor, como no `problem-track`.
- Parâmetros só pelo `Request` (`getParam` com valor padrão), sem
  `$_POST`.
- O `ErrorsHandler` trata warning como erro (500); `?? ''` evita o
  warning de índice indefinido e o `TypeError` de `findByEmail(null)`.
- Controller continua fino: só normaliza a entrada; a busca e a
  checagem de senha ficam no model.
- Testes em três camadas: controller, acesso (HTTP real) e aceitação.
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
```

Resultado obtido: Unit 71 testes, Integration 15, Acceptance 10 (eram
71 / 9 / 9 na base). PHPCS e PHPStan sem erros; `composer
dump-autoload --strict-psr` ok. Schema não mudou.

Testes novos:

- `AuthenticationsControllerTest::test_authenticate_without_params_should_not_login`
- `AuthenticationsControllerTest::test_authenticate_with_empty_fields_should_not_login`
- `AuthenticationsControllerTest::test_authenticate_with_spaces_around_email_should_login`
- `LoginAccessTest::test_login_form_should_disable_html5_validation`
- `LoginAccessTest::test_post_login_without_params_should_redirect_to_login`
- `LoginAccessTest::test_post_login_with_empty_fields_should_redirect_to_login`
- `LoginCest::loginWithEmptyFields`

No navegador: `http://localhost/login`, clique em "Entrar" com os dois
campos vazios. A página volta para `/login` com "E-mail ou senha
inválidos!" (antes o navegador mostrava o balão "Preencha este campo").
Depois, `user@example.com` / `123456` entra normalmente.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/exemplos-fvp/a9-login-campos-vazios`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a9 s50/exemplos-fvp/a9-login-campos-vazios/projeto
git fetch fvp-a9 fvp/a9-login-campos-vazios
git cherry-pick 1819996
git remote remove fvp-a9
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/a9-login-campos-vazios/solucao.bundle fvp/a9-login-campos-vazios:fvp-a9
git cherry-pick 1819996        # o bundle traz o objeto; o cherry-pick cria commit novo
git branch -D fvp-a9           # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/a9-login-campos-vazios/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/a9-login-campos-vazios/solucao.patch   # só o diff, sem commit
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

- `AuthenticationsController::authenticate`: também é mexido por A4, A11, A13,
  B5, B9, C3, C4 e H1. Mantenha as três linhas de normalização (`$params`,
  `$email`, `$password`) no topo e use `$email`/`$password` no código do outro
  cenário no lugar de `$params['email']`/`$params['password']`.
- `app/views/authentications/new.phtml`: C4 (layout) e A15 (token
  CSRF) mexem no `<form>`. Fique com o `novalidate` e com o que o
  outro cenário acrescentou.
- `AuthenticationsControllerTest.php` e `LoginCest.php`: testes novos
  de outros cenários no mesmo ponto do arquivo; fique com os dois.
- A10 já vem empilhado em cima deste commit (`a10-manter-email-apos-falha`).

Depois de aplicar: rode os testes e o `./run phpcs` de novo (não há
mudança de schema).

## Armadilhas

- `findByEmail('')` não quebra (consulta sem resultado); o que quebra
  é `null` (`TypeError`) e o índice ausente (warning vira 500 no
  `ErrorsHandler`).
- Com `novalidate`, `required` vira só decoração; aqui foi removido
  para não sugerir validação no navegador.
- `trim` resolve e-mail com espaço; maiúsculas já funcionam porque o
  MySQL 8 compara sem diferenciar caixa.
- `LoginHelper::login('', '')` funciona: `fillField` com string vazia
  só limpa o campo.
