# Q6. Revisar escape e testar XSS fora dos campos do Evento

Pedido, como o professor faria na hora: "Ponham
`<script>alert(1)</script>` no título de um Evento e mostrem que nada
executa. Depois revisem o escape de todas as telas, não só as do
Evento."

Chance: média-baixa. Tempo estimado: 15-20 min.
Catálogo: `s50/fvp/4.2/catalogo.md`, Q6.

Branch no clone: `fvp/q6-escape-xss`, a partir de `main` (`f31006b`).

| Commit    | Mensagem                                         |
|-----------|--------------------------------------------------|
| `29252b1` | `fix: Escape user data outside event fields`     |

## O que mudou

Os campos do Evento já saíam escapados na listagem, no detalhe e no
form. O que faltava:

- `app/views/layouts/_navbar.phtml:23`: o e-mail do usuário logado
  passa a sair com `htmlspecialchars(...)`.
- `app/views/admin/dashboard/index.phtml:1` e
  `app/views/dashboard/index.phtml:1`: o nome do usuário, idem.
- `app/views/layouts/_flash_message.phtml`: tipo (classe
  `alert-...`) e texto do flash com `htmlspecialchars`; a short tag
  `<? endforeach; ?>` da linha 6 virou `<?php endforeach; ?>`.
- Título da página: `EventsController::show` passa
  `$title = $event->title;` (cru) e o layout
  (`app/views/layouts/application.phtml:26`) imprime
  `<?= htmlspecialchars($title) ?>`. O escape saiu do controller e foi
  para a saída, uma vez só, valendo para todas as páginas.
- Testes:
  - `EventsControllerTest` (5 novos):
    - `test_event_title_is_escaped_once`: título `<b>x</b>` em
      `index`, `show` e `edit` sai `&lt;b&gt;x&lt;/b&gt;`, sem
      `<b>x</b>` e sem `&amp;lt;` (escape duplo);
    - `test_show_escapes_event_title_in_page_heading_once`:
      `<script>alert(1)</script>` no `<h1>` do show, escapado uma vez;
    - `test_navbar_escapes_user_email`: e-mail
      `<i>a</i>@example.com` gravado com `update` (não valida);
    - `test_flash_message_is_escaped`: `FlashMessage::danger('<b>x</b>')`;
    - `test_event_title_is_stored_without_escape`: o `create` grava
      `<b>x</b>` como veio e o form de edição mostra
      `value="&lt;b&gt;x&lt;/b&gt;"` (escape na saída, não na entrada).
  - `tests/Integration/Controllers/DashboardControllerTest.php` e
    `tests/Integration/Controllers/Admin/DashboardControllerTest.php`
    (novos, 1 cada): usuário de nome `<b>Ana</b>` logado com
    `Auth::login`; o painel mostra `&lt;b&gt;Ana&lt;/b&gt;`.
  - `tests/Acceptance/admin/events/ScriptInTitleCest.php` (novo):
    cadastra pelo form um Evento com título
    `<script>alert(1)</script>` e vê o texto literal no `<h1>` e no
    corpo do detalhe, na listagem (página 4) e no campo do form de
    edição. Se o script rodasse, o `alert` abriria um diálogo e o
    passo seguinte do Selenium falharia.

## Por que assim

- Escape na saída, nunca na entrada: o banco guarda o texto como foi
  digitado. `htmlspecialchars` no `fill` ou no model gravaria `&lt;`,
  ocuparia mais do `VARCHAR(255)` e o form de edição mostraria
  `&amp;lt;`.
- Escape no lugar onde o valor vira HTML: o `$title` é usado no
  layout, então é lá que se escapa. Com o escape no controller, cada
  action nova que passasse texto do banco teria de lembrar de escapar;
  as que passam texto fixo (`'Eventos'`, `'Login'`...) não mudam de
  aparência com o escape no layout.
- Trocar o layout sem tirar o `htmlspecialchars` do controller dá
  escape duplo: o `<h1>` mostraria `&lt;b&gt;` literal. A asserção
  `assertStringNotContainsString('&amp;lt;', ...)` pega esse erro.
- Os testes do título do Evento já passavam na base: são a rede para a
  mudança do `$title`. Os do navbar, do flash e dos painéis falham sem
  a correção (conferido revertendo só `app/`: 4 de 7 falham).
- Sem flags no `htmlspecialchars`: no PHP 8.1+ o padrão já é
  `ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401`, então aspas simples em
  atributo também saem escapadas.
- Ficaram sem escape, de propósito: rótulos de `Event::KINDS`,
  `MODALITIES` e `AUDIENCES` (constantes do código), números
  (`(int)`, `enrollmentsCount()`), datas formatadas por `date()` e o
  HTML montado pelos closures `$invalid`/`$feedback` do form (o
  `$feedback` já escapa a mensagem de erro).

## Como verificar

```
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 159, Integration 52, Acceptance 24 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema e populate não
mudaram.

Na tela: logado como admin, cadastre um Evento com título
`<script>alert(1)</script>`. Nenhum alerta abre; o texto aparece
literal no `<h1>` e no corpo do detalhe, na listagem e no campo do
form de edição. Pelo terminal (dentro da rede do compose), a fonte do
detalhe traz o título escapado:

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
# troque 17 pelo id do Evento criado
docker compose exec web curl -s -b /tmp/c \
    http://localhost/admin/events/17 | grep -n 'alert(1)'
```

Saída obtida (o `<h1>` e o corpo do detalhe):
`&lt;script&gt;alert(1)&lt;/script&gt;`, sem `<script>` cru.

## Relação com outros cenários

- R1 (`r1-flash-titulo-warning`): também põe `htmlspecialchars($msg)`
  no partial de flash, porque passa o título do Evento (cru) no texto.
  Aplicados juntos, a linha `<div>...</div>` é a mesma; o `warning` do
  R1 vira `alert-warning` igual, agora com o tipo escapado.
- R14 (`r14-titulo-da-pagina`): leva o `$title` também para o
  `<title>` da aba. Com este cenário aplicado, o `$title` já chega cru
  ao layout; escape nos dois lugares (`<title>` e `<h1>`) com
  `htmlspecialchars($title)`. Sem este cenário, o R14 precisa tirar o
  `htmlspecialchars` do `EventsController::show` do mesmo jeito.
- Qualquer cenário que imprima dado do banco numa view nova (nome do
  criador, inscritos, comentários...) segue a mesma regra:
  `htmlspecialchars` na saída.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/q6-escape-xss`.

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público. `<caminho>` abaixo é `s50/fvp/4.2/exemplos/q6-escape-xss`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-q6 s50/fvp/4.2/exemplos/q6-escape-xss/projeto
git fetch fvp-q6 fvp/q6-escape-xss
git cherry-pick 29252b1
git remote remove fvp-q6
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/fvp/4.2/exemplos/q6-escape-xss/solucao.bundle \
    fvp/q6-escape-xss:fvp-q6
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 29252b1
# opcional, limpa a ref
git branch -D fvp-q6
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/q6-escape-xss/solucao.patch
# só o diff, sem commit
git apply --3way s50/fvp/4.2/exemplos/q6-escape-xss/solucao.patch
```

### Se der conflito

```
# arquivos marcados "both modified"
git status
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
# ou: git am --continue
git cherry-pick --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `app/views/layouts/_flash_message.phtml`: R1 mexe na mesma linha do
  `$msg`. Fique com `htmlspecialchars($type)` e
  `htmlspecialchars($msg)`.
- `app/views/layouts/application.phtml`: R14 mexe no `<head>` e no
  `<h1>`. Mantenha `htmlspecialchars($title)` em toda saída do
  `$title`.
- `EventsController::show`: R14 e outros cenários que mexem no show.
  Mantenha `$title = $event->title;` sem escape.
- `EventsControllerTest.php`: testes novos de outros cenários no mesmo
  ponto do arquivo; fique com os dois. Se outro cenário também
  importar `Lib\FlashMessage`, deixe um `use` só.

Depois de aplicar: rode o `./run phpcs` e os testes de novo (não há
mudança de schema nem classe nova fora de `tests/`).

## Armadilhas

- Escape duplo: escapar no layout e deixar o `htmlspecialchars` em
  `EventsController::show` mostra `&lt;b&gt;` literal no `<h1>`.
- Escape na entrada (no `fill` do controller ou no model): grava
  `&lt;` no banco e o form de edição passa a mostrar `&amp;lt;`.
- Depois da mudança, um flash com HTML de propósito (negrito, link)
  vira texto. Hoje nenhum flash do projeto tem HTML.
- `nl2br(htmlspecialchars(...))` nessa ordem, como no detalhe;
  invertido, o `<br>` vira texto.
- A short tag `<?` do flash só funciona porque a imagem não tem
  `php.ini` e o padrão do PHP é `short_open_tag=On`; com
  `php.ini-production` a view quebra. Por isso ela virou `<?php`.
- `AdminDashboardCest` confere `Olá, Admin!`: nome sem caractere
  especial não muda com o escape, e o Cest continua passando.
- No teste do navbar, o e-mail muda com `update` (que não valida) e
  o controller relê o usuário do banco no construtor
  (`Auth::user()`), então a página já mostra o e-mail novo.
- `FlashMessage` guarda em `$_SESSION['flash']`; o `setUp` do
  `EventsControllerTest` já limpa, então o flash do teste não vaza.
