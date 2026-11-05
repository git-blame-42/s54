# N3 passo a passo: Ordenar por data no `Paginator`

Pedido típico: "ordene os Eventos pela data (mais próximos primeiro),
no banco, para valer em todas as páginas". Hoje a listagem segue o
`id`; o `Paginator` não tem `ORDER BY`. Chance: média-alta. Tempo:
15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/n3-ordenar-por-data/` (patch, bundle
e árvore `projeto/`). Pré-requisitos: nenhum (parte de `main`). Schema
não muda.

Ordem = fluxo da requisição: model (`Paginator` e `Model::paginate`) ->
controller -> testes. Rota e views não mudam. Na prova, prioridade:
funcionar no navegador, depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `lib/Paginator.php`
2. `core/Database/ActiveRecord/Model.php`
3. `app/Controllers/Admin/EventsController.php`
4. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
5. `tests/Acceptance/admin/events/CreateSuccessfullyCest.php`
6. `tests/Acceptance/admin/events/BaseEventsCest.php`
7. `tests/Acceptance/admin/events/ListEventsCest.php`

Dica: dá pra escrever primeiro o teste do passo 4 (falha), depois 1-3.

## 1. `lib/Paginator.php`

a) Último parâmetro do construtor, depois de `$route` (linha ~25):

```php
        private array $conditions = [],
        private ?string $route = null,
        private ?string $orderBy = null
    ) {
```

b) `loadRegisters()` (linha ~118): monte `$order` depois de
`$attributes` e insira no SQL entre o `WHERE` e o `LIMIT`:

```php
        $attributes = implode(', ', $this->attributes);
        // $orderBy vem sempre do código (controller), nunca da requisição: não há bind para ORDER BY.
        $order = $this->orderBy !== null ? "ORDER BY {$this->orderBy}" : '';

        $sql = <<<SQL
            SELECT id, {$attributes} FROM {$this->table}
            {$this->buildConditions()}
            {$order}
            LIMIT :limit OFFSET :offset
        SQL;
```

Por quê:

- Ordenar no SQL é o único jeito certo com paginação: `LIMIT/OFFSET`
  corta as 5 linhas **depois** do `ORDER BY`.
- Ordem SQL obrigatória: `WHERE ... ORDER BY ... LIMIT ...`.
- `ORDER BY` não aceita `bindValue` (é identificador, não valor): a
  string tem que vir do código, nunca da requisição.
- `loadTotals()` (`COUNT(*)`) não precisa de ordem: não mexa.
- Parâmetro opcional no fim: quem chama hoje não quebra.

## 2. `core/Database/ActiveRecord/Model.php`

`paginate()` (linha ~284): parâmetro novo e repasse nomeado.

```php
    public static function paginate(
        int $page = 1,
        int $per_page = 10,
        string $route = null,
        ?string $orderBy = null
    ): Paginator {
        return new Paginator(
            class: static::class,
            page: $page,
            per_page: $per_page,
            table: static::$table,
            attributes: static::$columns,
            route: $route,
            orderBy: $orderBy
        );
    }
```

Por quê:

- O controller não instancia `Paginator`; chama `Event::paginate()`.
  Sem repassar aqui, o `orderBy:` do controller dá erro de argumento
  desconhecido.
- Sem o argumento, comportamento igual ao de antes.

## 3. `app/Controllers/Admin/EventsController.php`

Em `index()`, na chamada `Event::paginate(` (linhas ~18-22), depois de
`route:`:

```php
            route: 'admin.events.paginate',
            // Mais próximos primeiro; id desempata datas iguais (a ordem não muda entre páginas).
            orderBy: 'starts_on, id'
        );
```

Por quê:

- Controller só diz **qual** ordem; SQL fica em `lib/`.
- `id` desempata: sem ele, datas iguais podem trocar de página entre
  requisições. `test_index_lists_five_events_per_page` cria 7 Eventos
  na mesma data e depende da ordem de `id`; o desempate o mantém verde.

Variante (se pedirem `?sort=`): lista fechada, nunca interpolar a URL.

```php
$allowed = ['date' => 'starts_on, id', 'title' => 'title, id'];
$sort = (string) $request->getParam('sort', 'date');
$orderBy = $allowed[$sort] ?? $allowed['date'];
```

## 4. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

a) Testes novos depois de `test_index_lists_five_events_per_page`:

```php
    public function test_index_orders_events_by_date_across_pages(): void
    {
        // Cadastrados fora de ordem: a ordem de id não coincide com a de data.
        $days = [30, 5, 20, 1, 25, 10, 15];
        foreach ($days as $day) {
            $this->createEvent([
                'title' => sprintf('Evento em %02d dias', $day),
                'starts_on' => date('Y-m-d', strtotime("+{$day} days")),
            ]);
        }

        $page1 = $this->get(action: 'index', controllerName: self::CONTROLLER);
        $page2 = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => 2]);

        // Página 1: os 5 mais próximos, em ordem crescente de data.
        $this->assertTitlesInOrder($page1, ['01', '05', '10', '15', '20']);
        $this->assertStringNotContainsString('Evento em 25 dias', $page1);
        $this->assertStringNotContainsString('Evento em 30 dias', $page1);

        // Página 2: o restante, sem repetir os da página 1 (ordenado no SQL, não na página).
        $this->assertTitlesInOrder($page2, ['25', '30']);
        $this->assertStringNotContainsString('Evento em 01 dias', $page2);
    }

    public function test_index_breaks_date_ties_by_id(): void
    {
        $date = date('Y-m-d', strtotime('+3 days'));
        $this->createEvent(['title' => 'Empate B', 'starts_on' => $date]);
        $this->createEvent(['title' => 'Empate A', 'starts_on' => $date]);
        $this->createEvent(['title' => 'Antes de todos', 'starts_on' => date('Y-m-d', strtotime('+1 day'))]);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $antes = strpos($response, 'Antes de todos');
        $empateB = strpos($response, 'Empate B');
        $empateA = strpos($response, 'Empate A');
        $this->assertNotFalse($antes);
        $this->assertNotFalse($empateB);
        $this->assertNotFalse($empateA);
        $this->assertLessThan($empateB, $antes);
        $this->assertLessThan($empateA, $empateB);
    }
```

b) Helper privado, antes do docblock de `validParams()` (fim da
classe):

```php
    /**
     * Confere que os títulos "Evento em NN dias" aparecem na resposta nesta ordem.
     *
     * @param array<int, string> $days
     */
    private function assertTitlesInOrder(string $response, array $days): void
    {
        $previous = -1;
        foreach ($days as $day) {
            $position = strpos($response, "Evento em {$day} dias");
            $this->assertNotFalse($position, "Evento em {$day} dias não aparece");
            $this->assertGreaterThan($previous, $position, "Evento em {$day} dias fora de ordem");
            $previous = $position;
        }
    }
```

Por quê:

- Datas fora da ordem de `id`: sem `ORDER BY` o teste falha.
- Confere **duas** páginas: pega o `usort` no controller (página 1
  certa, página 2 errada).
- `strpos` = posição no HTML; menor posição = linha acima na tabela.
- Segundo teste trava o desempate por `id` (`Empate B` criado antes
  de `Empate A`, sai antes).
- `@param array<int, string>`: `phpstan` nível 6.

## 5. `tests/Acceptance/admin/events/CreateSuccessfullyCest.php`

Depois de `$page->see($data['location']);`, troque o bloco que abria a
página 4:

```php
        // A listagem é ordenada por data: o Evento novo (amanhã) é o mais próximo e abre a 1ª página,
        // antes do Evento 01 (hoje + 14 dias). Sem ORDER BY ele ficaria no fim (4ª página).
        $page->amOnPage('/admin/events');
        $page->see($data['title'], 'table tbody tr:first-child');
        $page->see('Mostrando 1 - 5 de 17');
        $page->amOnPage('/admin/events/page/4');
        $page->dontSee($data['title'], 'table');
        $page->see('Mostrando 16 - 17 de 17');
```

Por quê: `validEventData` cadastra para amanhã
(`BaseEventsCest.php:171`). Antes ele ia pro fim (página 4); com a
ordenação abre a página 1. É o teste que quebra.

## 6. `tests/Acceptance/admin/events/BaseEventsCest.php`

Só o docblock de `seedEvents()` (linha ~73):

```php
    /**
     * Cria os Eventos em ordem de data crescente. A listagem é ordenada por starts_on (desempate por id),
     * e aqui a ordem de id coincide com a de data, como no populate.
     */
```

Por quê: o comentário antigo dizia "ordem do banco (id), sem ORDER BY".
Código não muda: `seedEvents` já cria em data crescente, então os Cests
de listagem e paginação seguem verdes.

## 7. `tests/Acceptance/admin/events/ListEventsCest.php`

Só o comentário antes do `grabMultiple` (linha ~19):

```php
        // Primeira página: os 5 Eventos mais próximos (ordem de data).
```

Por quê: mesmo motivo; documenta a ordem nova. Asserts iguais.

## Fechamento

```sh
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
docker compose up -d selenium && sleep 5
./run test:browser      # CreateSuccessfullyCest é o que muda
docker compose stop selenium
```

Sem `db:populate`: schema não mudou.

No navegador: o populate sozinho não mostra diferença (datas crescem
com o `id`). Cadastre em `/admin/events/new` um Evento para amanhã:
ele aparece na primeira linha da página 1 (antes ia para a página 4).

Commit (inglês): `feat: Order events listing by date in the Paginator`.

## Armadilhas (resumo)

- `usort` no controller ordena só a página de 5: errado, aparece na
  página 2. Faça no SQL.
- Sem desempate por `id`: datas iguais podem trocar de página; pode
  quebrar `test_index_lists_five_events_per_page` (mesma data).
- Nunca interpole `?sort=` em `ORDER BY` (SQL injection): lista
  fechada (`['starts_on', 'title']`) e `in_array(..., true)`.
- `ORDER BY` depois do `LIMIT`: erro de sintaxe no MySQL.
- `Event::all()` também não ordena; se o pedido for sobre ele, método
  `Event::allOrderedByDate()` com SQL no model.
- Sem `ORDER BY` o MySQL não garante ordem (hoje sai por `id` porque o
  InnoDB lê pela chave primária). Se só apontarem o defeito, `ORDER BY
  id` é a correção mínima e não quebra nada.
- `CreateSuccessfullyCest` esperava o Evento novo na página 4: agora
  ele está na página 1.
