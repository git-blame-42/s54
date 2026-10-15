# A10. Manter o e-mail digitado depois de uma falha

Pedido, como o professor faria na hora: "Errei a senha e tive que
digitar o e-mail de novo. Faça o formulário voltar com o e-mail
preenchido."

Chance: média. Tempo estimado: 15 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção A10.

Branch no clone: `fvp/a10-manter-email-apos-falha`. `main` continua
igual ao repositório público (`86c81ca`). Empilhado no A9 (usa a
variável `$email` que o A9 cria em `authenticate`).

| Commit | Mensagem | Origem |
|--------|----------|--------|
| `1819996` | `fix: Handle empty login form without HTML5 validation` | cenário A9 (mesmo commit, mesmo SHA) |
| `0d13c9e` | `feat: Keep typed email after failed login` | este cenário |

## O que mudou (só o commit `0d13c9e`)

- `app/Controllers/AuthenticationsController.php`: no `else` de
  `authenticate`, em vez de `redirectTo('/login')`, grava o flash
  "E-mail ou senha inválidos!" e faz
  `render('authentications/new', compact('title', 'email'))`. O flash
  aparece na mesma resposta, porque `_flash_message.phtml` chama
  `FlashMessage::get()` durante o `render`.
- `app/views/authentications/new.phtml`: o input de e-mail ganha
  `value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>"`.
  A senha nunca volta preenchida. `new()` não passa `$email`; o `?? ''`
  cobre.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`: os
  três testes de falha (`invalid_credentials`, `without_params`,
  `empty_fields`) trocam `Location: /login` por: sem `Location:`, com a
  mensagem e com `value="fulano@example.com"` (ou `value=""`). Teste
  novo de escape do e-mail digitado.
- `tests/Integration/Access/LoginAccessTest.php`: os dois testes de POST
  do A9 passam a esperar 200 com a mensagem (antes, 302 para `/login`);
  renomeados para `..._should_render_login_with_error`.
- `tests/Acceptance/authentications/LoginCest.php`:
  `loginWithInvalidCredentials` confere
  `seeInField('user[email]', 'fulano@example.com')` e senha vazia.

## Por que assim

- Rubrica 4.2 cobra "re-render mantendo valores" no formulário com
  erro (`docs/grading-path.md`).
- Saída escapada com `htmlspecialchars(..., ENT_QUOTES)` no `value`,
  testado com `"><script>`.
- Parâmetros só pelo `Request` (herdado do A9: `getParam('user', [])`).
- Controller fino: só escolhe entre redirect (sucesso) e render (falha).
- Testes em três camadas: controller, acesso e aceitação.
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

Resultado obtido: Unit 71 testes, Integration 16, Acceptance 10 (eram
71 / 9 / 9 no público e 71 / 15 / 10 no A9). PHPCS e PHPStan sem erros;
`composer dump-autoload --strict-psr` ok. Schema não mudou.

Testes novos deste commit:

- `AuthenticationsControllerTest::test_authenticate_failure_should_escape_typed_email`

Testes alterados: os três de falha em `AuthenticationsControllerTest`,
os dois de POST em `LoginAccessTest` e `LoginCest::loginWithInvalidCredentials`.

No navegador: `http://localhost/login`, digite `user@example.com` e a
senha `errada`, clique em "Entrar". A página mostra "E-mail ou senha
inválidos!" com o e-mail ainda no campo e a senha vazia. Com `123456`,
entra no painel.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público. `<caminho>` abaixo é `s50/exemplos-fvp/a10-manter-email-apos-falha`
(relativo ao clone do s50).

Primeiro veja se o alvo já tem o A9 (`git log --oneline | grep "Handle
empty login"`). Se tem, pegue só `0d13c9e`. Se não tem, pegue os dois,
na ordem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a10 s50/exemplos-fvp/a10-manter-email-apos-falha/projeto
git fetch fvp-a10 fvp/a10-manter-email-apos-falha

git cherry-pick 0d13c9e             # alvo já tem o A9
git cherry-pick 1819996 0d13c9e     # alvo sem o A9: os dois, nesta ordem
git cherry-pick main..fvp-a10/fvp/a10-manter-email-apos-falha   # mesma coisa, por intervalo

git remote remove fvp-a10
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/a10-manter-email-apos-falha/solucao.bundle fvp/a10-manter-email-apos-falha:fvp-a10
git cherry-pick 0d13c9e             # alvo já tem o A9
git cherry-pick main..fvp-a10       # alvo sem o A9: aplica os dois commits
git branch -D fvp-a10               # opcional, limpa a ref
```

Testado em clone limpo de `86c81ca`: `git cherry-pick main..tmp` aplica
os dois sem conflito. Testado em clone que já tinha o A9 aplicado por
cherry-pick: `git cherry-pick 0d13c9e` aplica limpo. O cherry-pick cria
commits novos; o SHA no seu repositório vai ser outro.

### 3. A partir do `solucao.patch` (`git am`)

O patch tem os dois commits (A9 e A10). Alvo sem o A9:

```
git am s50/exemplos-fvp/a10-manter-email-apos-falha/solucao.patch       # cria dois commits novos com as mesmas mensagens
git apply --3way s50/exemplos-fvp/a10-manter-email-apos-falha/solucao.patch   # só o diff, sem commit
```

Alvo que já tem o A9: use o bundle ou o clone (item 1 ou 2) com
`0d13c9e`; o `git am` do patch inteiro tentaria reaplicar o A9.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `AuthenticationsController::authenticate`, bloco `else`: C3 (troca o
  texto da mensagem), B5 e B9 (mensagens extras de falha). Fique com o
  `render` deste cenário e com o texto do outro.
- `app/views/authentications/new.phtml`: C4 (layout do login) e A15
  (campo oculto do token CSRF). Mantenha o `value=` no input de e-mail
  e o que o outro cenário trouxe.
- Testes que conferem `Location: /login` depois de login com falha
  (de qualquer outro cenário): com este commit a falha não redireciona;
  troque por `assertStringContainsString('E-mail ou senha inválidos!', ...)`.
  `grep -rn "Location: /login" tests/` mostra os casos.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (não há
mudança de schema).

## Armadilhas

- Isso abandona o Post/Redirect/Get que o `problem-track` usa no login:
  recarregar a página com falha reenvia o POST. Faça só se o professor
  pedir para manter o valor e diga a troca.
- `render` sem `$title` no `compact`: página 500.
- Teste de acesso (Guzzle) com e-mail não vazio consulta o banco, e o
  banco `eventos_test` não existe para o `web` durante a suíte; por isso
  o caso "e-mail mantido" fica no teste de controller e na aceitação.
- Não escape duas vezes: o `value` usa `htmlspecialchars` uma vez, e o
  controller passa o `$email` cru.
