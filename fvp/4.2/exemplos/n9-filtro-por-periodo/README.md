# N9. Filtro por período (de/até) validado, mantido na paginação

Pedido, como o professor faria na hora: "Na listagem de Eventos do
admin, filtre por período: `?from=AAAA-MM-DD&to=AAAA-MM-DD`. Data
inválida mostra erro, não é ignorada em silêncio. A página 2 continua
filtrada."

Chance: média. Tempo estimado: 30-40 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, N9 (forma 2, paginada).

Branch no clone: `fvp/n9-filtro-por-periodo`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit | Mensagem |
|--------|----------|
| `5c58645` | `feat: Filter events by period keeping it in pagination` |

## O que mudou

- `lib/Paginator.php`: dois parâmetros novos no construtor, no fim e
  opcionais (quem já chama não muda):
  - `queryParams` + getter `queryParams()`: o que os links de página
    repetem na query string (igual ao N1);
  - `ranges` (`['coluna' => [de, até]]`): `buildConditions()` gera
    `coluna BETWEEN :coluna_from AND :coluna_to` junto com as
    igualdades de `conditions`, e `bindConditions()` faz o
    `bindValue` das duas pontas.
- `app/Models/Event.php`: `Event::paginateBetween(?from, ?to, page,
  per_page, route)` monta o `Paginator` com
  `ranges: ['starts_on' => [...]]` e `queryParams` só com as pontas
  informadas. Ponta `null` vira `MIN_DATE` (`1000-01-01`) ou
  `MAX_DATE` (`9999-12-31`), os limites do `DATE` do MySQL.
- `app/Controllers/Admin/EventsController.php`, `index`:
  - `dateParam()`: lê `from`/`to` com `Request::getParam`; ausente ou
    vazio = `''`; array (`?from[]=x`) vira valor inválido (não é
    ignorado);
  - `periodError()` + `isValidDate()`: formato `Y-m-d` que exista no
    calendário (`createFromFormat('!Y-m-d')` + `format() === valor`,
    a ideia do `parseDate` privado de `lib/Validations.php`) e
    `from <= to`;
  - válido e com pelo menos uma ponta: `Event::paginateBetween`;
    inválido: flash `danger` com o motivo + "A listagem abaixo está
    sem filtro." e `Event::paginate` normal; `from`, `to` e `error`
    no `compact`.
- `app/views/paginator/_pages.phtml`: closure `$pageUrl` que junta
  `page` com `$paginator->queryParams()` (os três links usam ela).
- `app/views/admin/events/index.phtml`: form `GET` com dois
  `type="date"` ("De", "Até"), "Filtrar" e "Limpar"; valores mantidos;
  `is-invalid` nos campos quando há erro; "Nenhum evento no período."
  quando o filtro não acha nada.
- Testes:
  - `EventTest` (+4): bordas entram (`Início`/`Fim` sim,
    `Antes`/`Depois` não); paginação só dos filtrados (7 no período,
    página 2 com 2); uma ponta aberta de cada lado; período vazio.
  - `EventsControllerTest` admin (+11, 6 do data provider): filtro com
    link `/admin/events/page/2?from=...&to=...` e página 2 filtrada;
    bordas; só `from`; período sem Eventos; inválidos (mês 13,
    30/02, `dd/mm/aaaa`, texto, array, `from > to`) mostram a flash e
    a lista sem filtro; sem filtro os links continuam sem `?from=`.
  - `FilterEventsByPeriodCest` (novo, 3): filtrar Evento 02 a 13 (12
    Eventos), ir à página 2 e 3 pela paginação com o filtro na URL,
    "Limpar"; data inválida pela URL; `from` depois de `to`.

## Por que assim

- Forma paginada: o pedido diz "mantido na paginação". `Model::paginate`
  (em `core/`) não repassa condições e o `Paginator` só faz igualdade;
  em vez de mexer em `core/`, o `Paginator` (em `lib/`) ganhou
  `ranges`, e o Evento ganhou um método próprio que o monta.
- SQL só no `Paginator`/model, com `prepare` + `bindValue`; o
  controller só decide qual método chamar.
- Validação no controller: é validação de **entrada** do filtro, não
  regra de negócio do Evento (não há model "filtro" com
  `validates()`). Sem SQL nem regra do Evento ali.
- Data inválida: **erro com flash e listagem sem filtro** (a outra
  opção do pedido seria lista vazia). Assim o admin vê o motivo e não
  confunde "filtro errado" com "nenhum Evento". Nunca ignora em
  silêncio: até `?from[]=` é acusado.
- Só uma ponta: aceita, com o outro lado aberto ("de hoje em diante",
  "até tal dia"). Testado nos dois lados.
- `BETWEEN` com `DATE` inclui as duas pontas (testado nas bordas).

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 163 testes, Integration 56, Acceptance 26 (eram
159 / 45 / 23 na base). PHPCS e PHPStan sem erros. Schema não mudou;
nenhuma classe nova (o `lib/` não entra no PHPStan).

Na tela: `admin@example.com` / `123456`, "Eventos", escolha "De" e
"Até" cobrindo duas semanas, "Filtrar", depois a página 2. Sem porta
no host (troque as datas por duas do populate):

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c \
    'http://localhost/admin/events?from=2026-11-10&to=2026-11-24' \
    | grep -E 'page/2|Mostrando'
# data inválida: aparece a flash e "Mostrando 1 - 5 de 16"
docker compose exec web curl -s -b /tmp/c \
    'http://localhost/admin/events?from=2026-13-40' | grep inválida
```

## Relação com outros cenários

- N1 (`n1-filtro-por-tipo-paginacao`): mesmo `queryParams` no
  `Paginator` e mesma closure `$pageUrl` no `_pages.phtml`. Se aplicar
  os dois, os trechos são quase iguais: fique com uma cópia e passe
  `conditions: ['kind' => ...]` junto com `ranges` (o
  `buildConditions` deste já combina igualdade e intervalo) e
  `queryParams` com `kind`, `from` e `to`. O N1 também mexe em
  `Model::paginate` (`core/`); este não.
- N5 (`n5-eventos-futuros-passados`): "só futuros" é um período aberto
  (`from` = hoje, `to` = null): `Event::paginateBetween(date('Y-m-d'),
  null, ...)`. Os dois juntos conflitam no `index` do controller.
- N8 (`n8-ordenar-pelo-cabecalho`): ordena a listagem pelo cabeçalho
  (não filtra) e também usa `queryParams` para manter a ordem ao
  paginar. Com os dois, os links de página e de cabeçalho precisam
  levar `from`, `to` e a ordem juntos; o `index` do controller
  conflita.
- O2 (`o2-eventos-para-participante`) tem listagem própria
  (`events/index.phtml`); o filtro aqui é só no admin, mas o
  `_pages.phtml` mudado serve às duas (sem `queryParams`, os links não
  mudam).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/n9-filtro-por-periodo`.

Pré-requisito: o repositório alvo descende de `f31006b`. `<caminho>`
abaixo: `s50/fvp/4.2/exemplos/n9-filtro-por-periodo`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-n9 s50/fvp/4.2/exemplos/n9-filtro-por-periodo/projeto
git fetch fvp-n9 fvp/n9-filtro-por-periodo
git cherry-pick 5c58645
git remote remove fvp-n9
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/fvp/4.2/exemplos/n9-filtro-por-periodo/solucao.bundle \
    fvp/n9-filtro-por-periodo:fvp-n9
git cherry-pick 5c58645
# opcional, limpa a ref
git branch -D fvp-n9
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/n9-filtro-por-periodo/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/n9-filtro-por-periodo/solucao.patch
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

Arquivos que costumam conflitar e por quê:

- `lib/Paginator.php` e `_pages.phtml`: N1, N4 e outros filtros
  acrescentam `queryParams` do mesmo jeito. Fique com uma cópia do
  getter e da closure; mantenha `ranges` e o `buildConditions` deste.
  Ordem dos parâmetros do construtor: use argumentos nomeados ao
  chamar, como aqui.
- `Admin\EventsController::index`: N1, N3, N4, N5 e N8 mudam o mesmo
  método. Junte as leituras de parâmetro e chame um só
  `Paginator`/`paginate*` com tudo (`conditions`, `ranges`,
  `queryParams`).
- `admin/events/index.phtml`: outro form de filtro no topo; junte os
  campos num form só para um filtro não apagar o outro ao enviar.
- `EventsControllerTest`/`EventTest`: testes novos no mesmo ponto;
  fique com todos (o `use DataProvider` pode duplicar).

Depois de aplicar: rode os testes e o `./run phpcs` de novo.

## Armadilhas

- Validar só com `strtotime`: aceita expressões em inglês como
  "tomorrow" e "+1 week" e datas impossíveis como "2026-02-30" (vira
  02/03); "amanhã" devolve `false`, porque ele só entende inglês. Por
  isso `createFromFormat('!Y-m-d')` + comparar o `format()` com o
  valor digitado.
- `?from[]=x` chega como array: `trim()`/comparação lançaria
  `TypeError` (500); o `dateParam` transforma em valor inválido.
- Variável do controller dentro do `_pages.phtml`: o partial só
  enxerga `$paginator` (incluído pelo `Paginator`), daí o
  `queryParams()` guardado nele. Sem isso a página 2 perde o filtro.
- `from`, `to` e `error` fora do `compact`: a view usa as três e a
  página dá 500 (warning vira erro).
- Comparar `from > to` como string só funciona porque as duas já foram
  validadas em `Y-m-d`.
- `BETWEEN` com `DATETIME` (se `starts_on` virar data e hora, M7) não
  inclui o dia final inteiro: o `to` precisaria de `23:59:59`.
- `Paginator` não ordena: a listagem segue o `id`. Ordenar por data é
  o N3.
- `input type="date"` envia `AAAA-MM-DD` mesmo mostrando `dd/mm/aaaa`;
  no Cest o valor é definido por JS para não depender do idioma do
  navegador.
