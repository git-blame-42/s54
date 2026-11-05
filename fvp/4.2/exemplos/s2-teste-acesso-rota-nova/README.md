# S2. Teste de acesso por HTTP da rota nova

Pedido, como o professor faria na hora: "Escreva o teste de acesso da
rota nova (`/admin/events/full`): sem login vai para o login,
Participante não entra e o Administrador vê a página."

Chance: ALTA. Tempo estimado: 15-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, S2.

Branch no clone: `fvp/s2-teste-acesso-rota-nova`, empilhada sobre
`fvp/o1-rota-eventos-lotados` (cenário O1), que parte de `main`
(`f31006b`). Sem a rota nova não há o que testar.

| Commit    | Mensagem                                          |
|-----------|---------------------------------------------------|
| `e096227` | `feat: List full events in admin area` (base, O1) |
| `d1a9213` | `test: Cover full events route access over HTTP`  |

## O que mudou

Só o segundo commit (`d1a9213`), 1 arquivo:
`tests/Integration/Access/EventsAccessTest.php`.

- `eventRoutes()` ganha `'admin.events.full' => ['GET',
  '/admin/events/full']`. O data provider alimenta dois testes que já
  existiam, então a rota nova passa a ser coberta em:
  - `test_events_route_should_redirect_to_login_without_session`
    (302 para `/login`);
  - `test_events_route_should_redirect_participant_to_dashboard`
    (302 para `/dashboard`).
- Teste novo `test_admin_should_access_full_events`: cliente logado
  como admin (`loggedClient($this->admin)`), `GET /admin/events/full`,
  espera 200 e o texto "Eventos lotados" (cópia de
  `test_admin_should_access_events_index_and_new`).
- O comentário do provider passa de "8 rotas" para "9 rotas".

## Por que assim

- Os dois testes com provider só provam o bloqueio (middlewares
  `auth` e `admin`). Sem sessão, a resposta seria 302 para `/login`
  mesmo com a rota quebrada, então eles não distinguem.
- O teste do admin com 200 é o que prova que a rota chega na ação
  certa. É o único que pega a rota declarada depois de `{id}`.
- Por HTTP (Guzzle contra `web:8080`, o `php_test`), e não pelo
  `ControllerTestCase`: este chama a ação direto, sem Router.

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

Só os testes de acesso (a "prova" do catálogo):

```
./run test tests/Integration/Access
```

Resultado obtido: Unit 161, Integration 51, Acceptance 24 (base 159 /
45 / 23; depois do O1, 161 / 48 / 24). Os 3 testes novos de
Integration são as duas linhas do provider e o teste do admin.

Prova de que o teste testa algo: mova a linha da rota `full` para
depois de `Route::get('/admin/events/{id}', ...)` em
`config/routes.php` e rode de novo:

```
./run test tests/Integration
```

Resultado obtido com a rota fora do lugar:

```
✘ Admin should access full events
  Failed asserting that 302 matches expected 200.
✔ Full lists only full events          (teste de controller)
✔ Full without full events shows empty message
FAILURES! Tests: 51, Assertions: 170, Failures: 1.
```

O 302 vai para `/admin/events` com a flash "Evento não encontrado!":
o Router casou `full` como `{id}`. Os testes de controller do O1
continuam verdes, e os dois do provider também (sem sessão e
Participante são barrados antes de chegar à rota).

## Relação com outros cenários

- O1 (`o1-rota-eventos-lotados`): base deste cenário.
- P8 (`p8-exportar-csv`) e O4 (`o4-resposta-json`) usam a mesma
  receita de teste de acesso para as rotas deles.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/s2-teste-acesso-rota-nova
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/s2-teste-acesso-rota-nova`. Cherry-pick cria
commits novos (SHAs diferentes); preserva conteúdo e mensagem.

Primeiro decida: o alvo já tem a rota do O1?

- Não tem: aplique os dois commits (`e096227` e `d1a9213`) ou o
  intervalo `main..fvp/s2-teste-acesso-rota-nova`.
- Já tem (aplicou o O1 antes): aplique só o topo, `d1a9213`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-s2 <caminho>/projeto
git fetch fvp-s2 main fvp/s2-teste-acesso-rota-nova
# alvo sem O1: os dois, por intervalo
git cherry-pick fvp-s2/main..fvp-s2/fvp/s2-teste-acesso-rota-nova
# alvo que já tem O1: só o topo
git cherry-pick d1a9213
git remote remove fvp-s2
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle \
    fvp/s2-teste-acesso-rota-nova:fvp-s2
# alvo sem O1
git cherry-pick e096227 d1a9213
# alvo que já tem O1
git cherry-pick d1a9213
# opcional, limpa a ref
git branch -D fvp-s2
```

### 3. A partir do `solucao.patch` (`git am`)

O patch é uma série com os dois commits.

```
# alvo sem O1: aplica os dois
git am <caminho>/solucao.patch
# se o 1º (O1) já existe e conflitar, pule-o
git am --skip
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

- `EventsAccessTest.php`, `eventRoutes()`: outros cenários (P8, O4)
  acrescentam rotas no mesmo array. Fique com todas as linhas.
- Testes novos no mesmo ponto do arquivo: mantenha os dois blocos.
- Conflitos de `config/routes.php` e `index.phtml` são do commit do
  O1; resolva como no README do O1.

Depois de aplicar: rode `./run test tests/Integration` (o `web` e o
`php_test` precisam estar de pé: o teste usa `http://web:8080`).

## Armadilhas

- Testar só "sem sessão": passa mesmo com a rota quebrada. O caso que
  importa é o admin logado com 200.
- `loggedClient` já confere o 302 para `/dashboard` do login; se o
  admin não existir no banco de teste, a falha aparece ali.
- `allow_redirects => false` no `newClient`: sem isso o Guzzle segue
  o 302 e o teste veria a página de destino (200), mascarando o erro.
- Esperar só o status 200 não basta se outra rota responder 200 no
  lugar; confira também o texto ("Eventos lotados").
- Não acrescente a rota em `methodOverrideRoutes()`: lá ficam só PUT e
  DELETE.
