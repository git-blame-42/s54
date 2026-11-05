# R6. Partials reutilizados e botão Remover no `show`

Pedido, como o professor faria na hora: "A tabela de Eventos e os
botões de ação se repetem: extraia partials, como o `_form.phtml` já
faz para new/edit, e ponha o Remover também no detalhe."

Chance: média-baixa. Tempo estimado: 15-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, R6.

Branch no clone: `fvp/r6-partials-remover-show`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                  |
|-----------|-----------------------------------------------------------|
| `ea87f09` | `feat: Reuse events table partials and add Remover to...` |

(mensagem completa: `feat: Reuse events table partials and add
Remover to show`)

## O que mudou

- `app/views/admin/events/_actions.phtml` (novo): botões Ver
  (opcional), Editar e o form Remover (`_method=DELETE`) de um
  Evento. Docblock `@var \App\Models\Event $event` e
  `@var bool $withView`.
- `app/views/admin/events/_table.phtml` (novo): a `<table>` que
  estava no `index.phtml` (título, tipo, data, local, vagas), com a
  coluna Ações opcional. Docblock `@var array<\App\Models\Event>
  $events` e `@var bool $showActions`; em cada linha inclui o
  `_actions.phtml` com `$withView = true`.
- `app/views/admin/events/index.phtml`: fica só com "Novo Evento", a
  mensagem de vazio, `$showActions = true; require __DIR__ .
  '/_table.phtml';` e o paginador. A saída HTML é a mesma de antes.
- `app/views/admin/events/show.phtml`: no lugar do botão Editar,
  `$withView = false; require __DIR__ . '/_actions.phtml';` (Editar
  e Remover) e o Voltar. Os botões do show passam a ser os mesmos
  da listagem (pequenos, contornados).
- Testes:
  - `EventsControllerTest` (Integration, 2):
    `test_show_has_edit_and_remove_but_not_view` (form `DELETE` do
    Evento, Editar, Voltar, sem Ver) e
    `test_index_rows_have_view_edit_and_remove_actions`.
  - `DeleteEventCest` (Acceptance, 1 novo):
    `deleteEventFromShowPage` (abre o Evento 02, clica Remover, volta
    à listagem com "Evento removido com sucesso!").
  - Os testes e Cests existentes da listagem passam sem mudança
    (mesmo HTML).

## Por que assim

- Mesmo padrão do `_form.phtml`: partial com docblock dizendo o que
  precisa receber, incluído com `require __DIR__ . '/...'`. O
  `render` faz `extract` e o `require` roda no mesmo escopo, então o
  partial enxerga as variáveis definidas antes dele.
- Dois partials, não um: a tabela é da listagem; as ações servem à
  linha da tabela e ao show. Assim o Remover do show é o mesmo form
  da listagem (um lugar só para mudar, ex.: `data-confirm` do R4).
- Flags explícitos (`$showActions`, `$withView`) definidos em toda
  tela que inclui o partial: variável indefinida vira warning, que o
  `ErrorsHandler` transforma em 500.
- Ver some no show (seria um link para a própria página).
- Na base só a listagem tem a tabela; com O1 (`full.phtml`) e O2
  (`events/index.phtml`) aplicados, as outras telas passam a incluir
  o mesmo `_table.phtml` (ver "Relação com outros cenários").

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

Resultado obtido: Unit 159, Integration 47, Acceptance 24 (base 159 /
45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Na tela (populate): logue como admin, abra `/admin/events` (igual a
antes) e clique em "Ver" num Evento sem inscrições. O detalhe mostra
Editar, Remover e Voltar; Remover apaga e volta à listagem com
"Evento removido com sucesso!". No primeiro Evento (com inscrições)
aparece "não é possível remover um evento com inscrições (3)!".

## Relação com outros cenários

- O1 (`o1-rota-eventos-lotados`): o `full.phtml` copia a tabela.
  Com este cenário, troque a cópia por
  `$showActions = false; require __DIR__ . '/_table.phtml';` (ou
  `true`, se quiser as ações também nos lotados).
- O2 (`o2-eventos-para-participante`): a tabela do Participante está
  em outro diretório (`app/views/events/`) e não tem ações de admin;
  inclua com `require __DIR__ . '/../admin/events/_table.phtml'` e
  `$showActions = false`, ou mantenha a cópia se os links precisam
  ir para `events.show`.
- R4 (`r4-confirmacao-js-arquivo`): o `data-confirm` vai no form de
  `_actions.phtml` e passa a valer na listagem e no show. Um Cest que
  remove pelo show precisa de `acceptPopup()`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/r6-partials-remover-show
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/r6-partials-remover-show`. Cherry-pick cria
commit novo (SHA diferente); preserva conteúdo e mensagem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-r6 <caminho>/projeto
git fetch fvp-r6 fvp/r6-partials-remover-show
git cherry-pick ea87f09
git remote remove fvp-r6
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle \
    fvp/r6-partials-remover-show:fvp-r6
git cherry-pick ea87f09
# opcional, limpa a ref
git branch -D fvp-r6
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am <caminho>/solucao.patch
# só o diff, sem commit
git apply --3way <caminho>/solucao.patch
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

- `index.phtml`: quase todo cenário mexe na tabela (coluna nova M1,
  badge N6, status P5, botões O1/P8, `data-confirm` R4). Aceite a
  versão deste cenário (`require` do partial) e leve a mudança do
  outro para dentro de `_table.phtml` ou `_actions.phtml`.
- `show.phtml`: botões perto do Editar (P5, O3). Mantenha o
  `require` do `_actions.phtml` e os botões extras ao lado.
- `EventsControllerTest.php`, `DeleteEventCest.php`: testes novos no
  mesmo ponto; mantenha os dois.

Depois de aplicar: `./run phpcs`, os testes e `./run test:browser`.

## Armadilhas

- Variável não definida antes do `require` (ex.: esquecer
  `$withView` no show): warning → 500. Defina todos os flags.
- Caminho do `require` relativo ao arquivo (`__DIR__`), não à raiz
  do projeto.
- O partial de paginação (`_pages.phtml`) roda em outro escopo
  (`renderPagesNavigation()`): não enxerga `$showActions` nem
  `$events`; não misture.
- `use App\Models\Event;` no topo do partial vale só para aquele
  arquivo; o `index.phtml` não precisa mais dele.
- `$this` dentro do partial é o controller (mesmo escopo do
  `render`): `$this->currentUser()` funciona.
- O `foreach` do `_table.phtml` sobrescreve `$event` no escopo da
  view; na listagem não importa, mas não inclua a tabela no show
  antes de usar o `$event` do detalhe.
- `DeleteEventCest` clica "Remover" dentro de `rowOf()`; no show não
  há tabela, por isso o Cest novo usa `click('Remover', 'main')`.
