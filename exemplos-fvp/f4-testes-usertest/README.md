# F4. Testes que faltam no UserTest

Pedido, como o professor faria na hora: "Mostrem no `UserTest` o teste
que garante que não dá para cadastrar dois usuários com o mesmo e-mail
e que a senha não fica em texto puro. Se não tiver, escrevam."

Chance: alta (a issue #9 do repositório público diz que a explicação
dos testes do usuário foi feita mostrando a classe `User` em vez da
classe que testa o `User`). Tempo estimado: 10 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção F4.

Branch no clone: `fvp/f4-testes-usertest`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `bca2f64` | `test: Cover User validations and password hashing` |

## O que mudou

- `tests/Unit/Models/Users/UserTest.php`: 6 testes novos (+57 linhas).
  Nenhum código de produção mudou.
  - `test_errors_should_return_password_confirmation_error_when_confirmation_is_missing`:
    usuário novo sem `password_confirmation` não salva, erro
    "as senhas devem ser idênticas!" em `password`, tabela continua com 2.
  - `test_save_should_fail_with_duplicated_email`: e-mail já usado no
    `setUp` não salva, erro "já existe um registro com esse dado" em
    `email`, tabela continua com 2.
  - `test_update_should_accept_the_user_own_email`: editar o nome de um
    usuário existente, mantendo o próprio e-mail, salva (o `uniqueness`
    ignora o próprio registro).
  - `test_update_should_fail_with_email_of_another_user`: trocar o
    e-mail do `user2` para o do `user` não salva, e o banco mantém o
    e-mail antigo.
  - `test_password_should_be_stored_as_hash`: o `encrypted_password`
    lido do banco é diferente de `123456` e `password_verify` aceita.
  - `test_same_password_should_generate_different_hashes`: dois usuários
    com a mesma senha têm hashes diferentes (sal do `password_hash`).

Os casos de `findByEmail` (achou / `null`) já existiam na base
(`test_find_by_email_should_return_the_user` e
`test_find_by_email_should_return_null`); não foram duplicados.

### Método do `User` × teste que cobre (antes e depois)

Para a apresentação: abra `tests/Unit/Models/Users/UserTest.php`, não
`app/Models/User.php`. A coluna da direita é o que se mostra na tela.

| Método / regra do `User` | Antes (`86c81ca`) | Depois (`bca2f64`) |
|---|---|---|
| `__construct` (papel padrão `participant`) | `test_is_admin_should_return_false_by_default` | igual |
| `validates` → `notEmpty` de `name` e `email` | `test_errors_should_return_errors` | igual |
| `validates` → `passwordConfirmation` (senhas diferentes) | `test_errors_should_return_password_confirmation_error` | igual |
| `validates` → `passwordConfirmation` (confirmação ausente) | nenhum | `test_errors_should_return_password_confirmation_error_when_confirmation_is_missing` |
| `validates` → `uniqueness('email')` no cadastro | nenhum | `test_save_should_fail_with_duplicated_email` |
| `validates` → `uniqueness('email')` na edição | nenhum | `test_update_should_accept_the_user_own_email`, `test_update_should_fail_with_email_of_another_user` |
| `__set('password')` → `password_hash` | só indireto (`authenticate`) | `test_password_should_be_stored_as_hash`, `test_same_password_should_generate_different_hashes` |
| `__set('password')` só em registro novo | `test_update_should_not_change_the_password` | igual |
| `authenticate` | `test_authenticate_should_return_the_true`, `test_authenticate_should_return_false` | igual |
| `findByEmail` | `test_find_by_email_should_return_the_user`, `test_find_by_email_should_return_null` | igual |
| `isAdmin` | `test_is_admin_should_return_false_by_default`, `test_is_admin_should_return_true_for_admin_role` | igual |
| `registerLogin` / `last_login_at` | `test_register_login_should_set_last_login_at`, `test_last_login_at_should_be_null_by_default` | igual |
| herdados (`save`, `all`, `destroy`, `findById`) | `test_should_create_new_user`, `test_all_should_return_all_users`, `test_destroy_should_remove_the_user`, `test_find_by_id_should_return_the_user`, `test_find_by_id_should_return_null` | igual |

Depois do commit, toda regra do `User` tem pelo menos um
`test_<acao>_should_<resultado>` que a exercita diretamente.

## Por que assim

- Responde à issue #9 do professor (avaliação da 4.1): a explicação do
  `UserTest` foi o ponto fraco; agora cada método tem teste nomeado e a
  tabela acima liga um ao outro.
- Lacunas listadas no catálogo (seção 7): "`UserTest` não cobre e-mail
  duplicado nem o hash da senha".
- Só testes: o model já fazia tudo isso; o pedido é provar, não mudar.
- Nomes `test_<acao>_should_<resultado>` e asserções sobre o banco
  (`User::all()`, `User::findById`), não só sobre o objeto em memória
  (`grading-path.md`, rubrica de testes).
- Mensagens comparadas em pt-BR, exatamente as de `Lib\Validations`.
- PSR-12 e PHPStan nível 6 sem avisos; commit em inglês `type: Title`.
- Prova de que os testes pegam regressão: com `Validations::uniqueness`
  comentado e `password_hash` trocado por texto puro em `User.php`, o
  `UserTest` cai (4 falhas, 2 erros). Revertido em seguida.

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

Resultado obtido: Unit 77 testes, Integration 9, Acceptance 9 (eram
71 / 9 / 9 na base). Testes novos (todos em
`tests/Unit/Models/Users/UserTest.php`):
`test_errors_should_return_password_confirmation_error_when_confirmation_is_missing`,
`test_save_should_fail_with_duplicated_email`,
`test_update_should_accept_the_user_own_email`,
`test_update_should_fail_with_email_of_another_user`,
`test_password_should_be_stored_as_hash`,
`test_same_password_should_generate_different_hashes`.

Só o arquivo: `./run test tests/Unit/Models/Users/UserTest.php` (25
testes).

No navegador: não há mudança de interface. Para mostrar o e-mail
duplicado na tela seria preciso um cadastro (não existe na 4.1); o
teste unitário é a prova.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/f4-testes-usertest` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-f4 <caminho>/projeto
git fetch fvp-f4 fvp/f4-testes-usertest
git cherry-pick bca2f64
git remote remove fvp-f4
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/f4-testes-usertest:fvp-f4
git cherry-pick bca2f64
git branch -D fvp-f4         # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O
cherry-pick cria um commit novo (SHA diferente).

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

Arquivos que costumam conflitar neste cenário e por quê: só
`tests/Unit/Models/Users/UserTest.php`. Os testes novos entram logo
depois de `test_errors_should_return_password_confirmation_error`;
cenários que também acrescentam testes ao `UserTest` (validação de
formato de e-mail, `where` por papel em D5, etc.) mexem na mesma
região. Resolva mantendo os dois blocos de métodos, um depois do
outro. Se o alvo mudou o texto de `Lib\Validations` (C3, mensagens),
ajuste as strings esperadas nos testes novos.

Depois de aplicar: não há mudança de schema; rode os testes e o
`./run phpcs` de novo.

## Armadilhas

- O `setUp` já cria `fulano@example.com` e `fulano1@example.com`; use
  um e-mail novo (`fulano3@...`) quando o teste não for de duplicidade,
  senão a falha vem do `uniqueness` e não do que se quer testar.
- `passwordConfirmation` só roda em registro novo; testar senha
  diferente num usuário já salvo passa, e isso é o comportamento certo.
- Sem `password` e sem `password_confirmation` (os dois `null`) a
  confirmação passa; quem barra é o banco. Não escreva teste para isso
  sem antes combinar o comportamento esperado.
- `errors('email')` devolve só a primeira mensagem do campo.
- Na apresentação, fale do arquivo de teste na tela (crítica da
  issue #9), usando a tabela acima como roteiro.
