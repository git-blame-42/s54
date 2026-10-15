# A13. Regenerar o id da sessão no login (session hijacking)

Pedido, como o professor faria na hora: "Protejam a sessão contra
sequestro: o id da sessão precisa mudar quando o usuário faz login."

Chance: baixa-média. Tempo estimado: 10 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção A13.

Branch no clone: `fvp/a13-regenerar-sessao-login`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `3a11792` | `fix: Regenerate session id on login and destroy it on logout` |

## O que mudou

- `lib/Authentication/Auth.php`: `login()` chama `session_regenerate_id(true)`
  antes de gravar o id do usuário; `logout()` remove `$_SESSION['user']`
  inteiro e também regenera o id (a sessão antiga é apagada pelo `true`);
  método privado `regenerateSessionId()` só age com
  `session_status() === PHP_SESSION_ACTIVE`.
- `tests/Unit/Lib/Authentication/AuthTest.php`: dois testes novos (login sem
  sessão nativa grava o id; logout limpa os dados do usuário e preserva as
  outras chaves, como o flash).
- `tests/Acceptance/authentications/LoginCest.php`: dois testes novos que leem o
  cookie `PHPSESSID` antes e depois do login e do logout e exigem que mude.

## Por que assim

- Session fixation/hijacking: um id conhecido antes do login (ou copiado antes
  do logout) deixa de valer. `true` apaga o arquivo da sessão antiga no
  servidor.
- Sem `session_destroy()` no logout: o `FlashMessage` "Logout realizado com
  sucesso!" é gravado na sessão logo depois de `Auth::logout()`; destruir a
  sessão apagaria a mensagem (o teste `LoginCest::logout` quebraria). Regenerar
  o id + limpar `user` dá o mesmo efeito de segurança.
- `session_status()` protege os testes: PHPUnit roda em CLI sem
  `session_start()`, e `session_regenerate_id` fora de sessão ativa gera
  warning.
- Encapsulamento da sessão na classe `Auth` (feedback 2025-2, ver H1 no
  catálogo); controller não mudou.
- Cookie `PHPSESSID`: nome padrão do PHP; `config/bootstrap.php` só chama
  `session_start()`.
- Asserções no Cest via `PHPUnit\Framework\Assert` (a suíte de aceitação
  só habilita `WebDriver`, não o módulo `Asserts`).
- PSR-12 e PHPStan nível 6 limpos; comentários em pt-BR; identificadores e
  commit em inglês.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 73 testes, Integration 9, Acceptance 11 (eram
71 / 9 / 9 na base). Testes novos:

- `AuthTest::test_login_without_active_session_should_store_user_id`
  (`tests/Unit/Lib/Authentication/AuthTest.php`)
- `AuthTest::test_logout_should_clear_user_data_and_keep_other_session_keys`
  (idem)
- `LoginCest::loginShouldRegenerateSessionId`
  (`tests/Acceptance/authentications/LoginCest.php`)
- `LoginCest::logoutShouldRegenerateSessionId` (idem)

Prova negativa: com a linha `session_regenerate_id(true)` comentada, os dois
Cests novos falham (11 testes, 2 falhas).

No navegador: `http://localhost/login`, abrir DevTools > Application > Cookies e
anotar `PHPSESSID`; entrar com `fulano@example.com` / `123456`; o valor muda.
Sair: muda de novo e o flash "Logout realizado com sucesso!" aparece.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/exemplos-fvp/a13-regenerar-sessao-login`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a13 s50/exemplos-fvp/a13-regenerar-sessao-login/projeto
git fetch fvp-a13 fvp/a13-regenerar-sessao-login
git cherry-pick 3a11792
git remote remove fvp-a13
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/a13-regenerar-sessao-login/solucao.bundle fvp/a13-regenerar-sessao-login:fvp-a13
git cherry-pick 3a11792        # o commit criado no alvo terá SHA novo
git branch -D fvp-a13          # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/a13-regenerar-sessao-login/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/a13-regenerar-sessao-login/solucao.patch   # só o diff, sem commit
```

Testado: cherry-pick limpo sobre um clone de `86c81ca`.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `lib/Authentication/Auth.php`: A14 (expirar sessão) e H1 (`Auth::attempt`)
  mexem na mesma classe; B4/B9 podem mexer em `login()`. Mantenha os dois
  blocos: a chamada `self::regenerateSessionId()` no início de `login()`, a
  limpeza + regeneração em `logout()` e o método privado no fim. Se A14
  gravar um carimbo de tempo em `$_SESSION['user']`, ele também é limpo pelo
  `unset($_SESSION['user'])` (é o desejado).
- `tests/Acceptance/authentications/LoginCest.php` e `AuthTest.php`: outros
  cenários (A2, A4, A10, A15, B5, B9, H1) acrescentam métodos no fim da
  classe; o conflito é só de posição, mantenha os métodos de ambos e o
  `use PHPUnit\Framework\Assert;`.

Depois de aplicar: não há mudança de schema; rode os testes
e o `./run phpcs` de novo.

## Armadilhas

- Sem o `if (session_status() === PHP_SESSION_ACTIVE)`, os testes
  unit/controller emitem warning de `session_regenerate_id`.
- Não use `session_destroy()` no logout: some o flash de logout e
  `LoginCest::logout` falha.
- `$page->assertNotEquals` não existe na suíte de aceitação (sem módulo
  `Asserts`); use `PHPUnit\Framework\Assert` ou habilite `Asserts` em
  `tests/Acceptance.suite.yml` e rode `codecept build`.
- Desvio do catálogo: o catálogo só regenera no login; aqui o logout também
  regenera e limpa `$_SESSION['user']` inteiro (era só `['user']['id']`). A
  mensagem do commit diz "destroy it on logout" no sentido de descartar a
  sessão antiga (`session_regenerate_id(true)`), não de chamar
  `session_destroy()`.
