# C5. Escapar saída nas views (XSS)

Pedido, como o professor faria na hora: "Cadastrei um usuário com
nome `<script>alert('xss')</script>` e o alerta abriu no painel.
Corrijam: todo dado do usuário tem de sair escapado nas views."

Chance: média (página "Segurança" lista Cross-Site Scripting). Tempo
estimado: 15 min. Catálogo: `fvp-possiveis-modificacoes.md`, seção C5.

Branch no clone: `fvp/c5-escapar-saida-xss`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `7365dfd` | `fix: Escape user data in views` |

## O que mudou

- `lib/helpers.php` (novo): função global `e(?string $value): string`,
  que chama `htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE,
  'UTF-8')`. Fica dentro de `if (!function_exists('e'))`, igual aos
  helpers do framework.
- `composer.json`: `lib/helpers.php` entra em `autoload.files`, ao lado
  de `core/helpers.php`, que não foi editado.
- `app/views/dashboard/index.phtml`: `e($this->currentUser()->name)`.
- `app/views/admin/dashboard/index.phtml`: `e($this->currentUser()->name)`.
- `app/views/layouts/_navbar.phtml`: `e($this->currentUser()->email)`
  no `dropdown-toggle`.
- `app/views/layouts/_flash_message.phtml`: `e($type)` na classe CSS e
  `e($msg)` no texto do alerta.
- `app/views/layouts/application.phtml`: `e($title)` no `<h1>`.
- `tests/Unit/Lib/HelpersTest.php` (novo): 4 testes do helper.
- `tests/Integration/Controllers/DashboardControllerTest.php` (novo):
  4 testes de controller (nome, e-mail na navbar, flash, painel admin).
- `tests/Acceptance/dashboard/DashboardCest.php`: cenário novo com um
  usuário cujo nome contém `<script>`.

Resultado: 10 arquivos, +136 / -7 linhas.

## Por que assim

- Segurança contra XSS, pedida na página "Segurança" da disciplina:
  todo dado que veio do usuário (nome, e-mail, mensagens) passa por
  `htmlspecialchars` antes de ir para o HTML.
- Helper em `lib/` e não em `core/`: o catálogo manda não editar o
  framework (`core/helpers.php`); `lib/` é o lugar das bibliotecas do
  projeto.
- `ENT_QUOTES` escapa aspas simples e duplas: o mesmo helper serve
  para texto e para atributos (`value="..."`, `class="..."`).
- `ENT_SUBSTITUTE` evita string vazia quando chega UTF-8 inválido.
- Um único ponto de escape por valor: nada de `htmlspecialchars(e(...))`
  (escape duplo mostra `&amp;lt;` na tela).
- Testes em três camadas: unit (helper), controller (HTML renderizado)
  e aceitação (navegador real; sem o escape o Selenium para com
  `UnexpectedAlertOpenException`). Nomes descritivos
  (`test_<acao>_should_<resultado>`), conforme `grading-path.md`.
- PSR-12 e PHPStan nível 6 verdes; textos em pt-BR, identificadores em
  inglês, commit em inglês `type: Title`.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run composer dump-autoload   # obrigatório: lib/helpers.php entrou em autoload.files
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

O schema não mudou; `db:reset` não é necessário.

Resultado obtido: Unit 75 testes, Integration 13, Acceptance 10 (eram
71 / 9 / 9 na base). Testes novos:

- `tests/Unit/Lib/HelpersTest.php`: `test_e_should_escape_html_tags`,
  `test_e_should_escape_quotes_and_ampersand`,
  `test_e_should_keep_plain_text_and_accents`,
  `test_e_should_return_empty_string_for_null`.
- `tests/Integration/Controllers/DashboardControllerTest.php`:
  `test_index_should_escape_user_name` (nome `<b>x</b>` sai como
  `&lt;b&gt;x&lt;/b&gt;`), `test_index_should_escape_user_email_in_navbar`,
  `test_index_should_escape_flash_message`,
  `test_admin_index_should_escape_admin_name`.
- `tests/Acceptance/dashboard/DashboardCest.php`:
  `userNameWithScriptIsShownAsText` (o texto literal
  `<script>alert('xss')</script>` aparece no painel e nenhum alerta abre).

Conferido que os testes falham sem a correção nas views: os 4 de
controller falham e o de aceitação para com `unexpected alert open:
{Alert text : xss}`.

No navegador: `http://localhost`, crie (por `./run db:console` ou pelo
populate) um usuário com nome `<script>alert('xss')</script>`, entre
com ele e abra `/dashboard`. O nome aparece como texto, sem alerta.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/c5-escapar-saida-xss`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-c5 <caminho>/projeto
git fetch fvp-c5 fvp/c5-escapar-saida-xss
git cherry-pick 7365dfd
git remote remove fvp-c5
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/c5-escapar-saida-xss:fvp-c5
git cherry-pick 7365dfd        # o bundle traz o objeto; o cherry-pick cria um commit novo
git branch -D fvp-c5           # opcional, limpa a ref
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

- `tests/Integration/Controllers/DashboardControllerTest.php`: C2, B3,
  B4 e F1 também criam este arquivo (conflito "both added"). Junte os
  métodos das duas versões numa classe só e mantenha um único `setUp`
  (aqui ele cria o usuário Fulano).
- `app/views/layouts/_navbar.phtml`: C2 troca `email` por `name` na
  mesma linha; o resultado certo é `<?= e($this->currentUser()->name) ?>`.
  A2, A6, A7, B6, D1-D3, D6, D7, E1 e F3 mexem em outras linhas da
  navbar; aceite as duas versões e confira que o nome ou e-mail
  continua dentro de `e(...)`.
- `app/views/dashboard/index.phtml` e
  `tests/Acceptance/dashboard/DashboardCest.php`:
  B3 e B4 acrescentam dados ao painel; escape também o campo novo
  (`e($this->currentUser()->phone)`, por exemplo) e mantenha os dois
  cenários do Cest.
- Views novas de outros cenários (A10, B7, B8, D1, D3) imprimem dados
  do usuário (`value="..."`, listagens): depois de aplicar, troque os
  `htmlspecialchars(...)` ou `<?= $x ?>` por `e($x)`, sem escapar duas
  vezes.

Depois de aplicar: `./run composer dump-autoload` (senão `e()` não
existe e a página dá 500); rode os testes e o `./run phpcs` de novo.

## Armadilhas

- Sem `./run composer dump-autoload`, `Call to undefined function e()`
  em toda página com layout.
- PHPCS só olha `.php` e o PHPStan exclui `app/views`: erro em `.phtml`
  só aparece nos testes ou no navegador.
- Não escape duas vezes (`htmlspecialchars(e($x))`): a tela mostra
  `&amp;lt;`.
- `route(...)` dentro de `href` não precisa de `e()`: é gerado pelo
  framework, não pelo usuário.
- No Selenium, um `<script>` que executa abre um `alert` e derruba o
  teste com `UnexpectedAlertOpenException`; isso é a prova de que o
  escape faltava, não um defeito do teste.
- `WebDriver::seeInSource` lê o DOM serializado pelo navegador, que
  escreve `<` de texto como `&lt;`; por isso `seeInSource('&lt;script&gt;')`
  funciona.
