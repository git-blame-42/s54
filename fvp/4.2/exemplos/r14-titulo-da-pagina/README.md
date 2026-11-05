# R14. Título da aba com o nome da página e do Evento

Pedido, como o professor faria na hora: "A aba do navegador mostra
sempre 'Eventos do Câmpus'. Quero o nome da página na aba, e no
detalhe o nome do Evento."

Chance: baixa-média. Tempo estimado: 10-15 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, R14.

Branch no clone: `fvp/r14-titulo-da-pagina`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                   |
|-----------|--------------------------------------------|
| `373c74f` | `feat: Show page title in the browser tab` |

## O que mudou

- `app/views/layouts/application.phtml`:
  - primeira linha nova: `<!DOCTYPE html>` (antes o layout começava
    em `<html>` e o navegador entrava em modo quirks);
  - `<title>` fixo vira
    `<title><?= htmlspecialchars($title) ?> | Eventos do Câmpus</title>`;
  - o `<h1>` continua no mesmo lugar, agora com
    `<?= htmlspecialchars($title) ?>` no lugar de `<?= $title ?>`.
- `app/Controllers/Admin/EventsController.php`, `show`:
  `$title = htmlspecialchars($event->title);` vira
  `$title = $event->title;` (cru; quem escapa é o layout).
- As outras actions já passavam `$title` fixo ("Eventos", "Novo
  Evento", "Editar Evento", "Home Page", "Login", os dois painéis):
  sem mudança. Todas as páginas ganham "<página> | Eventos do Câmpus".
- Testes:
  - `EventsControllerTest`: a listagem começa com `<!DOCTYPE html>` e
    tem `<title>Eventos | Eventos do Câmpus</title>`; dois testes
    novos no `show` (nome do Evento na aba e no `<h1>`; título
    `A & <b>B</b>` escapado uma vez só nos dois lugares).
  - `HomeControllerTest`: `<title>Home Page | Eventos do Câmpus</title>`.
  - `ListEventsCest::listEventsHasLinksToShowAndEdit`:
    `seeInTitle('Eventos | Eventos do Câmpus')` na listagem e
    `seeInTitle('Evento 01 | Eventos do Câmpus')` depois de clicar
    em Ver.

## Por que assim

- Um layout só, um `$title` só: toda action já passa `$title` no
  `compact` (o `render` faz `extract`, então o layout enxerga a
  variável). Basta usar a mesma variável no `<head>`.
- Escape uma vez, na saída (no layout). Antes, o `show` escapava no
  controller e o layout imprimia cru. Com o `$title` indo para dois
  lugares, escapar no controller e no layout daria `A &amp; B` na aba
  e no `<h1>`. As outras actions passam texto fixo sem caractere
  especial, então escapar no layout não muda nada para elas.
- `<!DOCTYPE html>`: o Bootstrap 5 não suporta modo quirks, e o
  catálogo aponta a ausência junto com o título. A suíte de aceitação
  inteira passou sem ajuste de layout.
- O `<h1>` foi mantido com as quebras de linha de antes: os testes
  existentes usam `<h1>\s*...\s*</h1>`.
- Não foi feito o opcional "Editar: <nome>" na aba do editar: o
  pedido é o nome na aba, e o `<h1>` "Editar Evento" é o que os testes
  conferem. Receita abaixo.

### Receita: "Editar: <nome>" na aba, sem mudar o `<h1>`

```php
// EventsController::edit e o else de update
$title = 'Editar Evento';
$pageTitle = "Editar: {$event->title}";
$this->render('admin/events/edit', compact('event', 'title', 'pageTitle'));
```

```php
<!-- application.phtml -->
<title>
    <?= htmlspecialchars($pageTitle ?? $title) ?> | Eventos do Câmpus
</title>
```

O `??` é obrigatório: as outras actions não passam `$pageTitle` e o
warning de variável indefinida vira 500. Depois de um update
inválido, `$event->title` é o texto digitado (pode estar vazio:
"Editar: | ...").

## Como verificar

```
./run up -d db php php_test web
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 159 testes, Integration 47, Acceptance 23
(base: 159 / 45 / 23; a aceitação ganhou duas asserções no Cest
existente). PHPCS e PHPStan sem erros. Schema não mudou.

Testes novos:

- `EventsControllerTest`:
  - `test_show_puts_event_title_in_the_browser_tab`
  - `test_show_escapes_event_title_once_in_tab_and_heading`
- Asserções novas em `test_index_lists_five_events_per_page`,
  `HomeControllerTest::test_render_home_page` e
  `ListEventsCest::listEventsHasLinksToShowAndEdit`.

No terminal (no clone do s50, sem porta no host):

```
docker compose exec -T web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec -T web curl -s -b /tmp/c \
    http://localhost/admin/events/1 | head -7
```

Saída: a página começa com `<!DOCTYPE html>` e tem
`<title>Semana Acadêmica de Informática 2026 | Eventos do
Câmpus</title>`. No navegador, a aba do detalhe do Evento 1 mostra o
nome dele e a listagem mostra "Eventos | Eventos do Câmpus".

## Relação com outros cenários

- Q6 (`q6-escape-xss`): mexe nas mesmas duas linhas
  (`EventsController::show` e o `<h1>` do layout) pelo mesmo motivo,
  escapar na saída. Aplicados juntos, faça uma vez só:
  `$title = $event->title;` no controller e `htmlspecialchars($title)`
  em toda saída do `$title` no layout (`<title>` e `<h1>`).
- R1 (`r1-flash-titulo-warning`): também passa o título cru do Evento,
  só que no flash, e escapa no partial `_flash_message.phtml`. Mesma
  regra (dado cru até a view, escape na saída); arquivos diferentes.

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/r14-titulo-da-pagina`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh s50/fvp/4.2/exemplos/r14-titulo-da-pagina
cd /caminho/do/seu/repositorio
git remote add fvp-r14 \
    s50/fvp/4.2/exemplos/r14-titulo-da-pagina/projeto
git fetch fvp-r14 fvp/r14-titulo-da-pagina
git cherry-pick 373c74f
git remote remove fvp-r14
```

### 2. A partir do `solucao.bundle`

```
git fetch \
    s50/fvp/4.2/exemplos/r14-titulo-da-pagina/solucao.bundle \
    fvp/r14-titulo-da-pagina:fvp-r14
git cherry-pick 373c74f
# opcional, limpa a ref
git branch -D fvp-r14
```

### 3. A partir do `solucao.patch`

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/r14-titulo-da-pagina/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/r14-titulo-da-pagina/solucao.patch
```

### Se der conflito

```
# arquivos marcados "both modified"
git status
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue
# ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar e por quê:

- `app/views/layouts/application.phtml`: Q6 troca o mesmo `<h1>`;
  outros cenários acrescentam CSS/JS no `<head>`. Fique com o
  `<!DOCTYPE html>`, o `<title>` novo e `htmlspecialchars($title)`.
- `EventsController::show`: Q6 e outros cenários mexem no show.
  Mantenha `$title = $event->title;` sem escape.
- `EventsControllerTest.php` e `ListEventsCest.php`: testes ou
  asserções novas no mesmo ponto; fique com os dois.

## Armadilhas

- Escape duplo: escapar no layout e deixar o `htmlspecialchars` no
  `show` faz `A & B` aparecer como `A &amp; B` na aba e no `<h1>`. O
  teste `..._escapes_event_title_once_...` pega isso (`&amp;amp;`).
- Escapar só no `<title>` e esquecer o `<h1>` (ou o contrário) abre
  XSS no lugar esquecido, já que o controller agora passa cru.
- `$title` faltando num `render` agora quebra o layout em dois
  lugares (warning de variável indefinida vira 500). Toda action
  nova precisa de `$title` no `compact`.
- `htmlspecialchars(null)` é deprecado no PHP 8.1+: `$title` tem que
  ser string (o título do Evento é obrigatório no banco).
- O `<!DOCTYPE html>` precisa ser a primeira coisa da resposta; nada
  de espaço ou linha antes dele no layout.
- `seeInTitle` do Codeception confere se o texto está contido no
  título; use o título inteiro ("Evento 01 | Eventos do Câmpus") para
  não passar com um título errado que só contenha "Evento 01".
