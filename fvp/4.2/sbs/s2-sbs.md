# S2 passo a passo: teste de acesso por HTTP da rota nova

Pedido típico: "escreva o teste de acesso da rota nova
(`/admin/events/full`): sem login vai para o login, Participante não
entra e o Administrador vê a página". Chance: alta. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/s2-teste-acesso-rota-nova/` (patch,
bundle e árvore `projeto/`). Este arquivo é o mesmo conteúdo em formato
"um arquivo por vez, com o motivo".

Pré-requisito: O1 (`o1-rota-eventos-lotados`) aplicado: rota
`GET /admin/events/full` (`admin.events.full`), ação `full`, view com
"Eventos lotados". O patch traz dois commits: o 1º é a base O1
(`feat: List full events in admin area`); este guia cobre só o 2º.

Ordem: só teste, nenhum código de aplicação muda. Fluxo do teste = o da
requisição real: cliente HTTP -> Router -> middlewares `auth`/`admin`
-> controller -> resposta. Na prova, prioridade: teste passa, depois
provar que falha com a rota quebrada, depois `phpcs`.

## Arquivos, em ordem

1. `tests/Integration/Access/EventsAccessTest.php`

Depois: `./run test tests/Integration/Access`, `./run phpcs`.

## 1. `tests/Integration/Access/EventsAccessTest.php`

Três pontos.

a) Docblock de `eventRoutes()` (linha ~81): atualize a contagem.

```php
    /**
     * As 9 rotas admin.events.* (index, paginate, new, full, create, show, edit, update, destroy).
     *
```

Por quê: só documentação; mantém o comentário honesto.

b) `eventRoutes()` (linhas ~85-97), depois de `'admin.events.new'`:

```php
            'admin.events.new' => ['GET', '/admin/events/new'],
            'admin.events.full' => ['GET', '/admin/events/full'],
            'admin.events.create' => ['POST', '/admin/events'],
```

Por quê:

- `eventRoutes()` é data provider (`#[DataProvider('eventRoutes')]`) de
  dois testes que já existem:
  `test_events_route_should_redirect_to_login_without_session` (302 ->
  `/login`) e
  `test_events_route_should_redirect_participant_to_dashboard` (302 ->
  `/dashboard`).
- Uma linha = rota nova coberta nos dois. Prova os middlewares `auth` e
  `admin` do grupo em `config/routes.php`.
- Chave = nome da rota; aparece no relatório do PHPUnit.
- GET: não vai em `methodOverrideRoutes()` (só PUT e DELETE).

c) Teste novo, depois de `test_admin_should_access_events_index_and_new`
(linha ~174), antes de
`test_admin_should_be_redirected_to_index_when_event_does_not_exist`:

```php
    /**
     * Pega a rota declarada depois de '/admin/events/{id}': o Router casaria 'full' como id e
     * responderia 302 com "Evento não encontrado!". O teste de controller não passa pelo Router.
     */
    public function test_admin_should_access_full_events(): void
    {
        $client = $this->loggedClient($this->admin);

        $response = $client->get('/admin/events/full');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Eventos lotados', (string) $response->getBody());
    }
```

Por quê:

- Os testes do provider só provam o bloqueio: sem sessão dá 302 para
  `/login` mesmo com a rota quebrada. Não distinguem.
- Admin com 200 prova que a rota chega na ação certa. É o único que
  pega `full` declarada depois de `/admin/events/{id}` (Router casaria
  `full` como `{id}`).
- Cópia de `test_admin_should_access_events_index_and_new`.
- `loggedClient($this->admin)`: faz POST `/login` e já confere 302 para
  `/dashboard`; o cookie de sessão fica no cliente (`'cookies' => true`).
- Texto "Eventos lotados": 200 sozinho não basta se outra rota
  responder 200 no lugar.
- Por HTTP (Guzzle contra `http://web:8080`), não pelo
  `ControllerTestCase`: este chama a ação direto, sem Router nem
  middleware.

Armadilhas:

- `newClient` usa `'allow_redirects' => false`: sem isso o Guzzle segue
  o 302 e o teste vê 200 da página de destino.
- Schema recriado a cada teste: o Evento do `setUp` tem `id` 1;
  `eventRoutes()` usa `/1` fixo.
- Rota `auth` sem admin (O2, P2, P7): classe nova em
  `tests/Integration/Access/` copiando `DashboardAccessTest`. Rota
  pública: espere 200 sem sessão.

## Fechamento

```sh
./run up -d                 # web e php_test de pé (teste usa web:8080)
./run test tests/Integration/Access
./run test tests/Unit
./run test tests/Integration
./run test                  # suíte toda
./run phpcs
./run phpstan
```

Sem mudança de schema: não precisa `./run db:populate`.

Prova de que o teste testa algo: em `config/routes.php`, mova a linha da
rota `full` para depois de `Route::get('/admin/events/{id}', ...)` e rode
`./run test tests/Integration`. Esperado: só
`Admin should access full events` falha
("Failed asserting that 302 matches expected 200"); os de controller do
O1 e os do provider continuam verdes. Desfaça.

No navegador: deslogado, `/admin/events/full` -> `/login`; como
Participante -> `/dashboard`; como admin -> página "Eventos lotados".

Commit (inglês): `test: Cover full events route access over HTTP`.

## Armadilhas (resumo)

- Testar só "sem sessão": passa com a rota quebrada. O que importa é o
  admin logado com 200.
- Teste passa pelo Router de verdade: pega rota mal posicionada (o
  `ControllerTestCase` não pega).
- `loggedClient` exige 302 para `/dashboard` após login: se o login
  mudar (P12, A4 da 4.1), ajuste.
- `allow_redirects => false` no cliente, senão o 302 vira 200.
- Confira status e texto da página.
- PUT/DELETE vão também em `methodOverrideRoutes()`; GET não.
- `id` do Evento do `setUp` é 1 (schema recriado por teste).
- Contêiner `web` de pé (`./run up -d`) para `tests/Integration`.
