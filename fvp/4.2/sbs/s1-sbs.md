# S1 passo a passo: teste de aceitação da modificação

Pedido típico (logo depois do N1): "escreva o teste de aceitação do
filtro: filtrar por tipo, ver só os Eventos daquele tipo e ir para a
página 2 sem perder o filtro". Chance: alta. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/s1-teste-aceitacao-filtro/` (patch,
bundle e árvore `projeto/`). Este arquivo é o mesmo conteúdo em formato
"um arquivo por vez, com o motivo".

Pré-requisito: N1 (`n1-filtro-por-tipo-paginacao`) aplicado. O patch
traz dois commits: o 1º é a base N1
(`feat: Filter events by kind keeping the filter in pagination`); este
guia cobre só o 2º. Sem o N1 o `#filter_kind` não existe e tudo falha.

Ordem: só teste, nenhum código de aplicação muda. Fluxo do Cest = o do
usuário: abrir página -> agir -> conferir URL, tabela, rodapé. Na prova,
prioridade: funcionar (teste passa), depois provar que falha com a
funcionalidade quebrada, depois `phpcs`.

## Arquivos, em ordem

1. `tests/Acceptance/admin/events/FilterEventsCest.php` (novo)

Depois: Selenium de pé, `./run test:browser` no Cest, `./run phpcs`.

## 1. `tests/Acceptance/admin/events/FilterEventsCest.php`

Arquivo novo, ao lado de `ListEventsCest.php`, `DeleteEventCest.php`
etc.

a) Esqueleto da classe:

```php
<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

/**
 * Filtro da listagem por tipo (`?kind=`), mantido na paginação.
 *
 * A base cria 16 Eventos com os tipos em rodízio: Palestra (01, 05, 09, 13), Oficina (02, 06, 10, 14),
 * Minicurso (03, 07, 11, 15) e Semana acadêmica (04, 08, 12, 16). Cada tipo cabe numa página de 5.
 */
class FilterEventsCest extends BaseEventsCest
{
    private const WORKSHOPS = [2, 6, 10, 14];
```

Por quê:

- `extends BaseEventsCest`: a base já cria admin, 3 Participantes e 16
  Eventos (`Evento 01` a `Evento 16`), redimensiona a janela e loga o
  admin no `_before`. O Cest novo só descreve o cenário.
- Sem `_before` próprio. Se precisar, chame `parent::_before($page)`
  primeiro, senão perde dados e login.
- Namespace igual à pasta (`Tests\Acceptance\admin\events`); senão o
  Codeception não acha a classe.
- `WORKSHOPS`: números das Oficinas no rodízio da base. Mantém o teste
  legível.
- Herdado da base: `TOTAL_EVENTS = 16`, `PER_PAGE = 5`, `title($n)`
  (`Evento %02d`), `fillEventForm`, `validEventData`, `rowOf`.

b) Cenário 1, `filterByKind`:

```php
    public function filterByKind(AcceptanceTester $page): void
    {
        $page->wantTo('Filtrar a listagem por tipo');
        $page->amOnPage('/admin/events');

        $page->selectOption('#filter_kind', 'Oficina');
        $page->click('Filtrar');

        $page->seeCurrentUrlEquals('/admin/events?kind=workshop');
        $page->seeOptionIsSelected('#filter_kind', 'Oficina');
        $page->seeNumberOfElements('table tbody tr', count(self::WORKSHOPS));
        for ($i = 1; $i <= self::TOTAL_EVENTS; $i++) {
            if (in_array($i, self::WORKSHOPS, true)) {
                $page->see($this->title($i), 'table');
            } else {
                $page->dontSee($this->title($i), 'table');
            }
        }
        $page->see('Mostrando 1 - 4 de 4');
    }
```

Por quê:

- Um método público por cenário; `wantTo` em pt-BR vira a descrição
  no relatório.
- `selectOption` aceita rótulo (`Oficina`) ou valor (`workshop`).
- `seeCurrentUrlEquals`: prova que o filtro vai na query string (GET),
  não em sessão.
- `seeOptionIsSelected`: a view devolve o filtro marcado no `<select>`.
- `see`/`dontSee` com contexto `'table'`: o `<select>` também tem
  "Oficina", "Palestra"; sem contexto o teste passa sempre.
- Laço sobre os 16: prova "só Oficinas" e "nenhum outro".
- "Mostrando 1 - 4 de 4": o total do paginador também é filtrado.

c) Cenário 2, `keepFilterOnPageTwo` (o principal):

```php
    public function keepFilterOnPageTwo(AcceptanceTester $page): void
    {
        $page->wantTo('Filtrar a listagem por tipo e ir à página 2 com o filtro mantido');

        // Mais duas Oficinas pelo formulário: 6 Oficinas, 2 páginas no filtro.
        $this->createWorkshop($page, 'Oficina Extra 1');
        $this->createWorkshop($page, 'Oficina Extra 2');

        $page->amOnPage('/admin/events?kind=workshop');
        $page->see('Mostrando 1 - 5 de 6');
        $page->see('Oficina Extra 1', 'table');
        $page->dontSee('Oficina Extra 2', 'table');
        $page->seeLink('2', '/admin/events/page/2?kind=workshop');

        $page->click('2', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/2?kind=workshop');
        $page->seeOptionIsSelected('#filter_kind', 'Oficina');
        $page->seeNumberOfElements('table tbody tr', 1);
        $page->see('Oficina Extra 2', 'table');
        $page->dontSee($this->title(2), 'table');
        $page->dontSee($this->title(5), 'table');
        $page->see('Mostrando 6 - 6 de 6');

        $page->click('Anterior', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/1?kind=workshop');
        $page->see($this->title(2), 'table');
        $page->dontSee($this->title(1), 'table');
    }
```

Por quê:

- Cada tipo tem só 4 Eventos na base (cabe em 1 página de 5). Sem criar
  mais, não existe página 2 no filtro e o pedido fica sem prova.
- Criar pelo formulário exercita o fluxo real. `new Event([...])->save()`
  direto também vale e é mais rápido.
- `seeLink('2', '/admin/events/page/2?kind=workshop')`: confere que o
  link do paginador carrega o `?kind=` (é o que o N1 mudou no
  `_pages.phtml` via `queryParams()`).
- `click(..., '.pagination')`: contexto evita clicar num "2" da
  tabela.
- Ida e volta ("Anterior"): prova o filtro nos dois sentidos.
- Paginador sem `ORDER BY` (ordem de inserção): Oficinas 02, 06, 10,
  14 e Extra 1 na página 1; Extra 2 sozinha na página 2.

d) Cenário 3, `clearFilter`:

```php
    public function clearFilter(AcceptanceTester $page): void
    {
        $page->wantTo('Voltar a listar todos os tipos');
        $page->amOnPage('/admin/events?kind=workshop');
        $page->dontSee($this->title(1), 'table');

        $page->selectOption('#filter_kind', 'Todos');
        $page->click('Filtrar');

        $page->seeCurrentUrlEquals('/admin/events?kind=');
        $page->see($this->title(1), 'table');
        $page->see($this->title(5), 'table');
        $page->see('Mostrando 1 - 5 de 16');
    }
```

Por quê:

- `amOnPage` com o parâmetro pula o clique: atalho para cenário que não
  é sobre o formulário do filtro.
- `?kind=` vazio = sem filtro: volta aos 16 ("1 - 5 de 16").

e) Helper privado, no fim da classe:

```php
    /** Cadastra uma Oficina pelo formulário (validEventData já usa o tipo Oficina). */
    private function createWorkshop(AcceptanceTester $page, string $title): void
    {
        $page->amOnPage('/admin/events/new');
        $this->fillEventForm($page, $this->validEventData(['title' => $title]));
        $page->click('Salvar');
        $page->see('Evento cadastrado com sucesso!');
    }
}
```

Por quê:

- `validEventData` devolve dados válidos (tipo Oficina, data de amanhã)
  e aceita sobrescritas (`['title' => ...]`).
- `fillEventForm` preenche só as chaves informadas e põe a data por
  `executeJS`: não use `fillField` no `#event_starts_on`.
- `see('Evento cadastrado com sucesso!')`: texto exato do flash do
  `create`; falha cedo se o cadastro não salvou.

Armadilhas:

- `dontSee('Evento 1')` casaria com `Evento 10`; use `title(1)`
  (`Evento 01`).
- `LoginHelper::logout()` clica em `fulano@example.com` fixo: não use
  com o admin da base.
- Cenário de Participante: crie base própria copiando o `_before` com
  `$page->login('participante1@example.com', self::PASSWORD)`.
- Se o seu filtro usa outro `id` ou outro texto no botão, troque
  `#filter_kind` e `'Filtrar'`.

## Fechamento

```sh
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser tests/Acceptance/admin/events/FilterEventsCest.php
./run test:browser          # suíte de aceitação toda (demora)
docker compose stop selenium
```

Sem mudança de schema: não precisa `./run db:populate`.

Prova de que o teste testa algo: no `app/views/paginator/_pages.phtml`,
troque `$paginator->queryParams()` por `[]` e rode o Cest:
`keepFilterOnPageTwo` deve falhar ("No links containing text '2' and
URL '/admin/events/page/2?kind=workshop'"). Desfaça.

No navegador: `/admin/events`, filtrar Oficina, ir à página 2 (com
Oficinas extras) e ver `?kind=workshop` mantido na URL.

Commit (inglês): `test: Add acceptance test for the events kind filter`.

## Armadilhas (resumo)

- `see('Oficina')` sem contexto passa sempre (rótulo do `<select>`):
  use título do Evento + contexto `'table'`.
- Títulos com zero à esquerda: `Evento 01`, não `Evento 1`.
- Base tem 4 Eventos por tipo: crie mais para haver página 2.
- Base define dados (`title`, rodízio de tipos, `PER_PAGE = 5`,
  `TOTAL_EVENTS = 16`): se o pedido muda paginação ou ordem (N3, N4),
  base e Cests acompanham. Com N4 (`PER_PAGE` 10), crie 11+ Oficinas
  ou use `?per_page=5`.
- Janela 1280x2400 no `_before`: Selenium não clica fora da tela; fora
  da base, copie o `resizeWindow`.
- Radio: `checkOption('#event_modality_online')`; select:
  `selectOption('#event_kind', 'Oficina')`.
- Data no form só via `fillEventForm` (`executeJS`).
- `LoginHelper::logout()` com e-mail fixo: não use com o admin.
- Selenium de pé; suíte demora.
- Flash conferido com o texto exato.
- Teste que passa com a funcionalidade quebrada não prova nada: quebre
  de propósito uma vez.
