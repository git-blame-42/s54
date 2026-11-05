# S1. Teste de aceitação da modificação

Pedido, como o professor faria na hora (logo depois do filtro por
tipo do N1): "Agora escreva o teste de aceitação do filtro: filtrar
por tipo, ver só os Eventos daquele tipo e ir para a página 2 sem
perder o filtro."

Chance: alta. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, S1 (seção M11 do preparo da 4.2).

Branch no clone: `fvp/s1-teste-aceitacao-filtro`, empilhada sobre
`fvp/n1-filtro-por-tipo-paginacao` (que sai de `main`, `f31006b`).

| Commit    | Mensagem                                                       |
|-----------|----------------------------------------------------------------|
| `0fe3c48` | `feat: Filter events by kind keeping the filter in pagination` |
| `1ec7865` | `test: Add acceptance test for the events kind filter`         |

O primeiro commit é a base (N1); o deste cenário é só o `1ec7865`.

## O que mudou

- Arquivo novo `tests/Acceptance/admin/events/FilterEventsCest.php`,
  namespace `Tests\Acceptance\admin\events`, `extends BaseEventsCest`
  (sem `_before`: a base cria os 16 Eventos e faz o login do admin).
  Três cenários:
  - `filterByKind`: em `/admin/events`, escolhe "Oficina" no
    `#filter_kind` e clica em "Filtrar". Confere a URL
    `/admin/events?kind=workshop`, a opção selecionada, 4 linhas
    (Eventos 02, 06, 10 e 14), nenhum outro Evento e
    "Mostrando 1 - 4 de 4".
  - `keepFilterOnPageTwo`: cadastra duas Oficinas pelo formulário
    (6 Oficinas, 2 páginas no filtro), abre o filtro, confere o link
    `/admin/events/page/2?kind=workshop`, clica em "2", vê só a
    "Oficina Extra 2" e "Mostrando 6 - 6 de 6", e volta por
    "Anterior" para `/admin/events/page/1?kind=workshop`.
  - `clearFilter`: com o filtro ativo, escolhe "Todos" e volta à
    listagem completa ("Mostrando 1 - 5 de 16").
- Nenhum código de aplicação muda neste commit.

## Por que assim (como escrever um Cest a partir da base)

O `BaseEventsCest` já entrega quase tudo; o Cest novo só descreve o
cenário.

- Dados prontos: 16 Eventos `Evento 01` a `Evento 16` (`title($n)`
  devolve o título com zero à esquerda), datas futuras crescentes,
  tipos em rodízio: Palestra (01, 05, 09, 13), Oficina (02, 06, 10,
  14), Minicurso (03, 07, 11, 15), Semana acadêmica (04, 08, 12, 16).
  `PER_PAGE = 5` e `TOTAL_EVENTS = 16` são constantes da base.
- Login: o `_before` da base loga `admin@example.com`. Não escreva
  outro `_before` (ou chame `parent::_before($page)` primeiro).
- Janela: a base faz `resizeWindow(1280, 2400)`; o Selenium não
  clica em elemento fora da tela. Num Cest fora da base, copie isso.
- Formulário: `fillEventForm($page, $this->validEventData([...]))`
  preenche só as chaves informadas; `validEventData` devolve dados
  válidos (tipo Oficina, data de amanhã) e aceita sobrescritas,
  como `['title' => 'Oficina Extra 1']`. A data é posta por
  `executeJS` dentro do helper: não digite no `input[type=date]`.
- Select: `selectOption('#filter_kind', 'Oficina')` aceita o rótulo
  ou o valor (`workshop`); `seeOptionIsSelected` confere depois.
- Linha da tabela: `rowOf('Evento 02')` devolve o XPath da linha,
  útil para `click('Editar', $this->rowOf(...))` ou
  `see('0/22', $this->rowOf(...))`.
- Um método público por cenário, com `wantTo` em pt-BR; conferir a
  URL (`seeCurrentUrlEquals`) prova que o filtro está na query
  string, e `see`/`dontSee` com contexto `'table'` evitam casar com
  o `<select>` (que tem os rótulos "Oficina", "Palestra").
- Para a página 2 existir no filtro foi preciso criar mais Oficinas:
  cada tipo tem só 4 Eventos na base. Criar pelo formulário exercita
  o fluxo real; criar direto com `new Event([...])->save()` também
  vale e é mais rápido.

## Como verificar

```
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser tests/Acceptance/admin/events/FilterEventsCest.php
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 160, Integration 50, Acceptance 26 (base N1:
160 / 50 / 23; `main`: 159 / 45 / 23). PHPCS e PHPStan sem erros.

Prova de que o teste testa algo: trocando, no `_pages.phtml`,
`$paginator->queryParams()` por `[]`, o `keepFilterOnPageTwo` falha
("No links containing text '2' and URL
'/admin/events/page/2?kind=workshop'").

## Relação com outros cenários

- Depende do N1 (`n1-filtro-por-tipo-paginacao`): sem o filtro, o
  `#filter_kind` não existe e os três cenários falham.
- Com o N4 (`n4-per-page`) aplicado junto, `PER_PAGE` vira 10: as 6
  Oficinas cabem numa página. Para manter a página 2, crie mais
  Oficinas (11 ou mais) ou use `?per_page=5` na URL inicial.
- O mesmo roteiro serve para os Cests de N2 (busca) e N5
  (futuros/passados): `amOnPage` com o parâmetro, `see`/`dontSee`
  na tabela.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/s1-teste-aceitacao-filtro`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/s1-teste-aceitacao-filtro`.

Este cenário é empilhado: se o alvo ainda não tem o N1, aplique o
range inteiro (base + topo, `0fe3c48` e `1ec7865`); se já tem o N1,
aplique só o topo (`1ec7865`).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-s1 \
    s50/fvp/4.2/exemplos/s1-teste-aceitacao-filtro/projeto
git fetch fvp-s1 fvp/s1-teste-aceitacao-filtro
# sem o N1: range f31006b..1ec7865 (0fe3c48 e 1ec7865)
git cherry-pick f31006b..1ec7865
# com o N1 já aplicado: só o topo
# git cherry-pick 1ec7865
git remote remove fvp-s1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/s1-teste-aceitacao-filtro/solucao.bundle \
    fvp/s1-teste-aceitacao-filtro:fvp-s1
# o bundle traz os objetos; o cherry-pick cria commits novos
git cherry-pick f31006b..1ec7865
# ou, com o N1 já aplicado: git cherry-pick 1ec7865
# opcional, limpa a ref
git branch -D fvp-s1
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria os commits novos com as mesmas mensagens
git am s50/fvp/4.2/exemplos/s1-teste-aceitacao-filtro/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/s1-teste-aceitacao-filtro/solucao.patch
```

Se o patch trouxer os dois commits e o alvo já tiver o N1, o `git
am` para no primeiro com conflito: `git am --skip` pula a base e
segue para o Cest.

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

- O commit do topo só cria `FilterEventsCest.php`: não conflita. Os
  conflitos possíveis são os do N1 (`Paginator`, `Model::paginate`,
  `EventsController::index`, `index.phtml`); veja o README do N1.
- Se o seu filtro usa outro `id` no select ou outro texto no botão,
  ajuste `#filter_kind` e `'Filtrar'` no Cest.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- `see('Oficina')` sem contexto passa sempre: o rótulo está no
  `<select>`. Use o título do Evento e o contexto `'table'`.
- `dontSee('Evento 1')` casaria com `Evento 10`; os títulos da base
  têm zero à esquerda (`title(1)` = `Evento 01`).
- Cada tipo tem 4 Eventos na base: sem criar mais, não há página 2
  no filtro e o cenário principal fica sem prova.
- O `LoginHelper::logout()` clica em `fulano@example.com` fixo: não
  use com o admin logado pela base.
- Data no formulário: não use `fillField` no `#event_starts_on`; o
  `fillEventForm` faz por `executeJS`.
- O Selenium precisa estar de pé (`docker compose up -d selenium`) e
  a suíte inteira demora alguns minutos.
- Teste que passa com a funcionalidade quebrada não prova nada:
  quebre de propósito uma vez (ex.: `queryParams()` -> `[]`).
