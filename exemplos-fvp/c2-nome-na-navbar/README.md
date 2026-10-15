# C2. Nome em vez de e-mail na navbar

Pedido, como o professor faria na hora: "Mostrem o nome do usuário
na barra de navegação, não o e-mail."

Chance: alta (trivial). Tempo estimado: 10 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção C2.

Branch no clone: `fvp/c2-nome-na-navbar`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `0b7ef7c` | `feat: Show user name instead of email in navbar` |

## O que mudou

- `app/views/layouts/_navbar.phtml`: o `dropdown-toggle` imprime
  `$this->currentUser()->name` em vez de `->email`.
- `tests/Support/Helper/LoginHelper.php`: `logout()` clica em
  `.dropdown-toggle` (seletor CSS) em vez do texto
  `fulano@example.com`.
- `tests/Acceptance/authentications/LoginCest.php`:
  `loginWithValidCredentials` passa a `see('Fulano')` e
  `dontSee('fulano@example.com', '.navbar')`.
- `tests/Integration/Controllers/DashboardControllerTest.php` (novo):
  dois testes; um prova que o `dropdown-toggle` contém "Fulano" e
  que o e-mail não aparece no HTML renderizado.

Resultado: 4 arquivos, +44 / -3 linhas.

Escolha no helper: clicar pelo seletor `.dropdown-toggle`, e não
pelo nome. Assim o `logout()` serve para qualquer usuário (Fulano,
Admin, Organizador), e o Cest não precisa passar o nome. A
alternativa `logout(string $name = 'Fulano')` funciona, mas amarra o
helper a um dado de teste.

## Por que assim

- Mudança só na view; model e controller não sabem de apresentação
  (MVC, `grading-path.md`, seção 4.2).
- Texto que aparece para o usuário continua em pt-BR; nada de
  identificador novo.
- Antes de mexer: `grep -rn "example.com" tests/` para achar todo
  teste que confere o e-mail na tela (armadilha listada no catálogo).
  Só `LoginCest` e `LoginHelper` dependiam do texto.
- Teste de controller novo (rubrica pesa testes): prova o HTML sem
  depender do Selenium.
- Commit em inglês, `type: Title` (Code Standards).

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

Resultado obtido: Unit 71 testes, Integration 11, Acceptance 9
(eram 71 / 9 / 9 na base). Testes novos:

- `DashboardControllerTest::test_index_should_render_user_dashboard`
- `DashboardControllerTest::test_navbar_should_show_user_name_instead_of_email`

No navegador: `http://localhost`, login com `user@example.com` /
`123456`. No canto direito da navbar aparece "Usuário" (o `name` do
populate), não o e-mail. Clique nele e em "Sair".

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/c2-nome-na-navbar` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-c2 <caminho>/projeto
git fetch fvp-c2 fvp/c2-nome-na-navbar
git cherry-pick 0b7ef7c
git remote remove fvp-c2
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/c2-nome-na-navbar:fvp-c2
git cherry-pick 0b7ef7c
git branch -D fvp-c2          # opcional, limpa a ref
```

O cherry-pick cria um commit novo (SHA diferente, ex.: `9c06c9d`),
com a mesma árvore e a mesma mensagem. Testado num clone limpo de
`86c81ca`: aplica sem conflito.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

Testado: `git am` aplica limpo.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário:

- `_navbar.phtml`, se o alvo já mexeu no dropdown (A2 troca o link
  "Sair" por formulário; A6 acrescenta o item "Perfil"; D1 e A7
  acrescentam itens de menu). Resolva mantendo os itens do alvo e
  trocando só `->email` por `->name` na linha do `dropdown-toggle`.
- `LoginHelper.php` e `LoginCest.php`, se o alvo já alterou o fluxo
  de logout (A2). Mantenha o clique em `.dropdown-toggle` seguido de
  `click('Sair')`; com A2 o "Sair" é um botão, e o `click` por texto
  continua funcionando.
- `DashboardControllerTest.php`, se o alvo já criou esse arquivo (B3,
  B4 e F1 criam um com o mesmo nome). Junte os métodos num arquivo
  só; o `setUp` é igual.

### Combinar com A6 (link "Perfil")

O título do catálogo fala em "link Perfil"; a página `/profile` é o
cenário A6. Ordem recomendada:

```
git cherry-pick <sha do A6>     # 00be668 no s50/exemplos-fvp/a6-nova-pagina-restrita-profile
git cherry-pick 0b7ef7c         # este cenário
```

Conflito esperado em `_navbar.phtml` (as duas mudanças tocam o
dropdown). Resolução: fique com o bloco do A6 (item "Perfil" acima
de "Sair") e troque `->email` por `->name` no `dropdown-toggle`.

Depois de aplicar: rode os testes e o `./run phpcs` de novo. Não há
mudança de schema.

## Armadilhas

- `LoginHelper::logout()` clicava no texto do e-mail; esquecê-lo
  derruba os três cenários de `LoginCest` e o de `DashboardCest` que
  deslogam. Clicar pelo seletor `.dropdown-toggle` resolve de vez.
- `see('Fulano')` sozinho é prova fraca: o painel já diz "Bem-vindo(a),
  Fulano!". Por isso o Cest também faz
  `dontSee('fulano@example.com', '.navbar')` e o teste de controller
  confere o conteúdo do `dropdown-toggle`.
- Se o professor pedir nome **e** e-mail (ex.: "Fulano
  (fulano@example.com)"), o `dontSee` do Cest e o
  `assertStringNotContainsString` do teste de controller precisam
  mudar junto.
