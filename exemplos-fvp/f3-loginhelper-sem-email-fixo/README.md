# F3. LoginHelper::logout() sem e-mail fixo

Pedido, como o professor faria na hora: "O `logout()` do helper de
teste só funciona para o Fulano. Faça funcionar para qualquer usuário,
inclusive o admin."

Chance: média-baixa. Tempo estimado: 10 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção F3.

Branch no clone: `fvp/f3-loginhelper-sem-email-fixo`. `main` continua
igual ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `7ef4f43` | `test: Make LoginHelper logout independent of the user email` |

## O que mudou

- `app/views/layouts/_navbar.phtml`: o `<a class="nav-link
  dropdown-toggle">` do menu do usuário ganha `id="user_menu"`.
- `tests/Support/Helper/LoginHelper.php`: `logout()` clica em
  `#user_menu` (seletor CSS) em vez do texto `fulano@example.com`;
  depois clica em `Sair`, como antes.
- `tests/Acceptance/admin/AdminDashboardCest.php`: teste novo
  `logoutAsAdmin`, que entra como `admin@example.com`, abre `/admin`,
  chama `$page->logout()` e confere `/login`, a mensagem "Logout
  realizado com sucesso!" e que o e-mail do admin sumiu da tela.

Resultado: 3 arquivos, +13 / -2 linhas.

## Por que assim

- Seletor por `id` e não pelo texto do link: o helper deixa de
  depender de dado de teste (e-mail) e continua valendo se a navbar
  passar a mostrar o nome (cenário C2). Catálogo, seção F3 e
  armadilha "`LoginHelper::logout()` clica no e-mail
  `fulano@example.com`" (seção 4).
- `id` explícito em vez de `.dropdown-toggle`: se a navbar ganhar
  outro dropdown (menu de administração, por exemplo), `#user_menu`
  continua apontando para um único elemento.
- A assinatura de `logout()` não mudou, então não é preciso
  `./run codecept build` nem mexer nos Cests existentes.
- Teste de aceitação com um usuário diferente de Fulano prova a
  mudança. Com o helper antigo esse teste falha com `'fulano@example.com'
  is invalid CSS and XPath selector and Link or Button element ... was
  not found` (conferido antes do commit).
- PSR-12 e PHPStan nível 6 sem erros; atributo HTML e identificadores
  em inglês; commit em inglês, `type: Title` (`grading-path.md`).

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Sem mudança de schema; `db:reset` não é necessário.

Resultado obtido: Unit 71 testes, Integration 9, Acceptance 10 (eram
71 / 9 / 9 na base). Teste novo: `AdminDashboardCest::logoutAsAdmin`
em `tests/Acceptance/admin/AdminDashboardCest.php`. O
`LoginCest::logout` (Fulano) continua passando com o helper novo.

No navegador: `http://localhost`, entre como `admin@example.com`, abra
`/admin`, clique no e-mail no canto direito da navbar e em "Sair". Nada
muda para o usuário; a mudança visível é só o `id="user_menu"` no HTML
(inspecione o elemento).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50 (`s50/exemplos-fvp/f3-loginhelper-sem-email-fixo`).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-f3 <caminho>/projeto
git fetch fvp-f3 fvp/f3-loginhelper-sem-email-fixo
git cherry-pick 7ef4f43
git remote remove fvp-f3
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/f3-loginhelper-sem-email-fixo:fvp-f3
git cherry-pick 7ef4f43      # cria commit novo com o mesmo conteúdo
git branch -D fvp-f3         # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch   # só o diff, sem commit
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

- `tests/Support/Helper/LoginHelper.php`, se o alvo já tem C2 (que
  troca o clique por `.dropdown-toggle`). Fique com uma das duas
  linhas; `#user_menu` exige o `id` na navbar, `.dropdown-toggle` não.
- `app/views/layouts/_navbar.phtml`, se o alvo já tem C2 (a linha
  seguinte ao `dropdown-toggle` passa a imprimir o nome), A2 (Sair vira
  formulário DELETE), A6, A7 ou D1 (itens novos de menu). O conflito é
  de contexto: mantenha as linhas do alvo e só acrescente
  `id="user_menu"` no `<a class="nav-link dropdown-toggle" ...>`.
- `tests/Acceptance/admin/AdminDashboardCest.php`, se o alvo já
  acrescentou testes no fim da classe (A4, A8, A12, F1, entre outros).
  Mantenha os dois métodos.

Depois de aplicar: rode os testes e o `./run phpcs` de novo.

## Armadilhas

- Com A2 aplicado, "Sair" é um `<button>` dentro de `<form>`; o
  `click('Sair')` continua achando o botão pelo texto, sem mudança.
- Se o alvo tiver mais de um elemento com `id="user_menu"` (navbar
  duplicada em outro layout), o WebDriver clica no primeiro; o `id`
  deve ser único na página.
- O dropdown do Bootstrap precisa abrir antes do clique em "Sair":
  com o menu fechado o link fica oculto e o WebDriver não clica nele.
  Não remova o primeiro `click('#user_menu')`.
