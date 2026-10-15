# A2. Logout via DELETE

Pedido, como o professor faria na hora: "O logout está em GET. Troque
para DELETE, como manda o REST, e ajuste o link da navbar."

Chance: alta (feedback 2025-2 do professor). Tempo estimado: 15 min.
Catálogo: `fvp-possiveis-modificacoes.md`, seção A2.

Branch no clone: `fvp/a2-logout-via-delete`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `508079e` | `feat: Logout via DELETE request` |

## O que mudou

- `config/routes.php`: `Route::get('/logout', ...)` vira
  `Route::delete('/logout', ...)`. Nome `users.logout` e ação `destroy`
  não mudam.
- `app/views/layouts/_navbar.phtml`: o `<a href="/logout">Sair</a>`
  vira `<form method="post">` com `<input type="hidden" name="_method"
  value="DELETE">` e `<button type="submit" class="dropdown-item">Sair
  </button>`. O botão mantém o texto, então `LoginHelper::logout()`
  (`click('Sair')`) continua funcionando.
- `tests/Integration/Access/LogoutAccessTest.php` (novo): `GET /logout`
  responde 404; `DELETE /logout` e `POST /logout` com `_method=DELETE`
  redirecionam para `/login`.
- `tests/Integration/Controllers/ControllerTestCase.php`: helper
  `delete()` ao lado de `get`/`post`/`put`.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`:
  `test_destroy_should_logout` chama `$this->delete(...)` em vez de
  `$this->get(...)`.
- `tests/Unit/Core/Http/RequestTest.php`: teste de que `_method` no
  `$_REQUEST` sobrescreve o `REQUEST_METHOD`.

Como o framework resolve: `Core\Http\Request::__construct` faz
`$this->method = $_REQUEST['_method'] ?? $_SERVER['REQUEST_METHOD']`;
o `Router::dispatch` casa método e URI. Sem rota para `GET /logout`,
`Router` lança `HTTPException(..., 404)` e o `ErrorsHandler` responde
com esse status.

## Por que assim

- Logout muda estado (destrói a sessão); verbo seguro (GET) não
  serve. Feedback 2025-2 do professor para outras equipes: "logout
  via DELETE" (`docs/grading-path.md`, seção 4.1, "2025-2 feedback
  still applies").
- Rota RESTful: `destroy` responde a `DELETE`, como nos recursos do
  `problem-track` (rubrica 4.2 cobra nomes e verbos).
- Navegador só envia GET/POST em formulário; o `_method` oculto é o
  mecanismo do framework (mesmo que `PUT`/`DELETE` do CRUD).
- Testes em três camadas: unit (`Request`), controller
  (`AuthenticationsControllerTest`), acesso (`LogoutAccessTest`) e a
  aceitação `LoginCest::logout` continua cobrindo o botão.
- Commit em inglês `type: Title`; texto de interface em pt-BR.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 72 testes, Integration 12, Acceptance 9 (eram
71 / 9 / 9 na base). PHPCS e PHPStan sem erros; `composer
dump-autoload --strict-psr` ok.

Testes novos:

- `RequestTest::test_should_override_method_with_method_param`
- `LogoutAccessTest::test_logout_via_get_should_not_be_found`
- `LogoutAccessTest::test_logout_via_delete_should_redirect_to_login`
- `LogoutAccessTest::test_logout_via_post_with_method_param_should_redirect_to_login`

No navegador: `http://localhost`, login `user@example.com` / `123456`,
abrir o menu do e-mail, clicar em "Sair": volta para `/login` com
"Logout realizado com sucesso!". Digitar `http://localhost/logout` na
barra de endereço mostra a página de erro 404 do framework.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/a2-logout-via-delete`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a2 <caminho>/projeto
git fetch fvp-a2 fvp/a2-logout-via-delete
git cherry-pick 508079e
git remote remove fvp-a2
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/a2-logout-via-delete:fvp-a2
git cherry-pick 508079e          # mesmo SHA; o bundle preserva o objeto
git branch -D fvp-a2             # opcional, limpa a ref
```

Verificado: num clone limpo do público, o `fetch` do bundle seguido
de `cherry-pick 508079e` aplica sem conflito.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch            # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch  # só o diff, sem commit
```

Verificado: `git am` sobre `86c81ca` gera um commit novo (SHA
diferente, `f48e4e6` no teste) com a mesma mensagem e o mesmo diff.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `config/routes.php`: qualquer outro cenário que mexa na linha do
  logout (A3 move essa rota para dentro do grupo `auth`). Resolva
  mantendo `Route::delete` e a posição que o outro cenário pede.
- `app/views/layouts/_navbar.phtml`: cenários que editam o dropdown
  (C2 troca e-mail por nome, A6 adiciona link Perfil). Mantenha o
  `<form>` do logout e as linhas do outro cenário.
- `tests/Integration/Controllers/ControllerTestCase.php`: se o alvo
  já tem um helper `delete()`, descarte a versão deste commit.

Depois de aplicar: rode `./run test tests/Integration` e
`./run test:browser`; o `LoginCest::logout` confirma o botão.

## Armadilhas

- `GET /logout` passa a dar 404. Qualquer `amOnPage('/logout')` ou
  link antigo em view/README precisa virar o formulário.
- O `<button>` precisa de `class="dropdown-item"` para ficar com o
  mesmo visual do link dentro do dropdown do Bootstrap.
- `_method` é lido de `$_REQUEST`; funciona com `POST` de formulário
  e também com `?_method=DELETE` em GET. Não confie nisso para
  segurança: o que protege é a sessão, não o verbo.
- Em testes de acesso com Guzzle, use `'http_errors' => false` no
  cliente, senão o 404 vira exceção antes do `assertEquals`.
