# C4. Layout próprio para a tela de login

Pedido, como o professor faria na hora: "A tela de login não deve
mostrar a barra de navegação; façam um layout só para ela."

Chance: média. Tempo estimado: 20 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção C4.

Branch no clone: `fvp/c4-layout-login`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `137b6c0` | `feat: Use dedicated layout for login page` |

## O que mudou

- `app/views/layouts/login.phtml` (novo): cópia do `<head>` do
  `application.phtml` (Bootstrap, ícones, `application.css`,
  `login.css`), sem `_navbar.phtml`; mantém `_flash_message.phtml`,
  `<h1><?= $title ?></h1>` centralizado, `require $view` e um link
  "Voltar ao início" para `route('root')`.
- `app/Controllers/AuthenticationsController.php`: `protected string
  $layout = 'login';` com comentário pt-BR. A classe base
  `Core\Http\Controllers\Controller` declara `protected string $layout =
  'application'` e o `render()` monta
  `app/views/layouts/{$this->layout}.phtml`; basta sobrescrever.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`:
  novo `test_login_page_should_not_render_navbar` (resposta não contém
  `class="navbar` e contém "Voltar ao início"). O
  `test_render_login_page` existente continua, porque o `<h1>Login</h1>`
  foi mantido.
- `tests/Acceptance/authentications/LoginCest.php`: no cenário
  `logout`, `see('Login')` vira `see('Login', '//h1')` e
  `dontSeeElement('nav.navbar')`; antes o `see('Login')` podia casar
  com o link "Login" da navbar, que não existe mais nessa tela.

Resultado: 4 arquivos, +51 / -1 linhas.

## Por que assim

- Layout é responsabilidade do framework (`$layout` no controller);
  trocar o layout por controller é o mecanismo previsto, sem `if` na
  view nem duplicar a view de login (`framework-completo.md`, seção 4,
  `Controller::render`).
- O `<h1>` com `$title` ficou no layout, igual ao `application.phtml`,
  para o teste existente e o padrão visual das outras páginas não
  mudarem.
- A parcial de flash ficou no layout: a tela de login é onde aparecem
  "E-mail ou senha inválidos!" e "Logout realizado com sucesso!"; sem
  ela, `LoginCest::loginWithInvalidCredentials` e `logout` quebram.
- Comentários e textos de interface em pt-BR; identificadores em
  inglês; commit em inglês no formato `type: Title` (Code Standards).
- PSR-12, PHPStan nível 6 e PSR-4 sem avisos (views estão fora do
  PHPStan, mas o controller e os testes passam).

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

Resultado obtido: Unit 71 testes, Integration 10, Acceptance 9 (eram
71 / 9 / 9 na base). Teste novo:
`AuthenticationsControllerTest::test_login_page_should_not_render_navbar`.
Teste ajustado: `LoginCest::logout`.

No navegador: `http://localhost/login` abre sem a barra "Eventos do
Câmpus / Login", só o cartão e o link "Voltar ao início". Erre a senha:
a mensagem vermelha aparece acima do cartão. Logue com
`user@example.com` / `123456`: o painel volta a mostrar a navbar.
Clique em "Sair": a tela de login mostra "Logout realizado com
sucesso!" sem navbar.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/c4-layout-login` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-c4 <caminho>/projeto
git fetch fvp-c4 fvp/c4-layout-login
git cherry-pick 137b6c0
git remote remove fvp-c4
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/c4-layout-login:fvp-c4
git cherry-pick 137b6c0
git branch -D fvp-c4          # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito e a árvore
resultante é idêntica à da branch. O commit criado pelo cherry-pick é
novo (SHA diferente), como sempre.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

Testado: `git am` aplica limpo sobre `86c81ca`.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `app/Controllers/AuthenticationsController.php`: A4 (redirect do
  admin) e B5 (usuário inativo) mexem no mesmo arquivo, mas em
  `authenticate()`; a propriedade `$layout` fica no topo da classe.
  Conflito raro; se acontecer, mantenha as duas mudanças.
- `tests/Acceptance/authentications/LoginCest.php`: A3, A5 e C2
  também acrescentam ou alteram cenários aqui. Resolva mantendo todos os
  métodos; no `logout`, fique com `see('Login', '//h1')`.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`:
  A4 adiciona um teste no mesmo arquivo; mantenha os dois.
- `app/views/layouts/login.phtml` é novo; só conflita se o alvo já criou
  um arquivo com esse nome.

Depois de aplicar: rode `./run phpcs`, `./run phpstan` e os testes de
novo. Não há mudança de schema.

## Armadilhas

- `test_render_login_page` procura `<h1>\s*Login\s*</h1>`. Se o layout
  novo tirar o `<h1><?= $title ?></h1>`, ou o teste quebra ou você
  precisa ajustá-lo. Mais fácil manter o `<h1>`.
- `assertStringNotContainsString('navbar', ...)` dá falso positivo se
  qualquer comentário ou classe CSS do layout contiver "navbar"; por
  isso o teste procura `class="navbar`.
- `LoginCest::logout` fazia `see('Login')` sem contexto: casa com o
  `<h1>`, com o `<title>` não (Codeception olha texto visível), mas
  também casaria com o link da navbar. Com `'//h1'` o teste diz o que
  realmente quer.
- Se o professor pedir o layout também para o cadastro (B7), basta
  `protected string $layout = 'login';` no `UsersController`, ou um
  nome mais genérico (`guest.phtml`) e os dois controllers apontando
  para ele.
- O `login.css` já é carregado pelo layout padrão; a classe
  `.card.login` continua valendo no layout novo.
