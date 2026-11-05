# P16. Remover Evento só com código de confirmação gerado no backend

Pedido, como o professor faria na hora: "Remover um Evento agora exige
confirmação. O botão 'Remover' abre uma página que mostra um código de
4 dígitos gerado no servidor (finja que ele foi enviado por e-mail). O
Evento só é removido se o administrador digitar esse código. Código
errado ou em branco: avise e não remova."

Chance: média-alta. Tempo estimado: 30-40 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P16. Variante de R4; segue o padrão da FVP
real da 4.1 (P12).

Branch no clone: `fvp/p16-remover-com-codigo-confirmacao`, a partir de
`main` (`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                               |
|-----------|--------------------------------------------------------|
| `e72d5f5` | `feat: Require a confirmation code to remove an Event` |

## O que mudou

- `lib/ConfirmationCode.php` (classe nova, estática, como
  `FlashMessage`):
  - `generate(string $key): int`: `random_int(1000, 9999)`, guarda em
    `$_SESSION['confirmation_codes'][$key]` (substitui o anterior) e
    devolve o código;
  - `current(string $key): ?int`;
  - `check(string $key, mixed $code): bool`: `true` só se existe código
    para a chave e o valor enviado (string do form ou int, com `trim`)
    é igual a ele; `null`, array ou chave sem código → `false`;
  - `clear(string $key): void`.
- `config/routes.php`: `GET /admin/events/{id}/delete` →
  `confirmDestroy`, nome `admin.events.delete`, **antes** de
  `GET /admin/events/{id}` (mesmo cuidado de `new`).
- `app/Controllers/Admin/EventsController.php`:
  - `confirmDestroy`: `findEvent` (não achou → `eventNotFound()`),
    `ConfirmationCode::generate("event.{$event->id}")` e renderiza
    `admin/events/delete` com `$event`, `$code`, `$title`;
  - `destroy`: depois do `findEvent`, `$valid =
    ConfirmationCode::check($key, $request->getParam(
    'confirmation_code'))` e, **sempre**, `ConfirmationCode::clear(
    $key)` (o código vale para uma tentativa só). Inválido → flash
    `danger` "Código de confirmação inválido! O evento não foi
    removido." e redirect para `admin.events.delete`, que gera um
    código novo; válido → segue o fluxo de antes (`safeDestroy`, que
    mantém a regra P03 do projeto: Evento com Inscrições não é
    removido);
  - helper privado `confirmationKey(Event $event): string`.
- `app/views/admin/events/delete.phtml` (nova): título e data do
  Evento, o código em `<strong id="confirmation_code_value">`, form
  `POST` + `_method=DELETE` com o campo `confirmation_code`, botão
  "Remover" e link "Cancelar" (volta ao `show`).
- `index.phtml`: "Remover" deixou de ser form `DELETE` e virou link
  para `/admin/events/{id}/delete`.

## Por que assim

- O código nasce e fica no servidor: o form não carrega o valor certo
  (nem em campo oculto), então só quem abriu a página de confirmação
  na mesma sessão consegue remover. Um `DELETE` forjado direto, sem
  passar pela página, é recusado.
- Mostrar o código na tela não é segurança contra o próprio usuário
  (ele lê e digita): é prova de leitura, um passo deliberado antes de
  uma ação irreversível. Em produção o código iria por e-mail, e aí
  também confirmaria que quem remove tem acesso à conta.
- Um código por Evento (`event.{id}`): abrir a confirmação de dois
  Eventos em abas diferentes não mistura os códigos, e o código de um
  não remove o outro.
- Classe pequena em `lib/`, estática: a lógica fica fora do controller
  e é testável com o array `$_SESSION` do PHPUnit, sem sessão real
  (`session_start`). O controller só gera, confere e limpa.
- Uma tentativa por código: o `clear` vem logo depois do `check`, certo
  ou errado. Se o código errado não fosse invalidado, um script podia
  repetir o `DELETE` testando os 9000 valores com o mesmo código. Cada
  visita à página gera outro.
- Validação de parâmetro no limite HTTP: `check` aceita só string ou
  int; `confirmation_code[]=x` vira `false`, sem `TypeError`.
- `GET .../delete` para a página (não muda nada) e `DELETE` para a ação
  (RESTful). O nome `admin.events.delete` segue o padrão
  `admin.events.*`.

## Como verificar

```
./run up -d db php php_test web
./run db:populate
./run composer dump-autoload -o --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 169, Integration 58, Acceptance 24 (base: 159 /
45 / 23). PHPCS e PHPStan sem erros; PSR-4 estrito ok. Schema não
mudou.

Testes novos:

- `tests/Unit/Lib/ConfirmationCodeTest.php` (novo, 10 testes): faixa
  1000–9999, guarda na sessão, `null` sem código, um código por chave,
  certo como string/int/com espaços, errado/vazio/`null`/array,
  código de outra chave, sem código gerado, gerar de novo substitui,
  `clear` só da chave.
- `EventsControllerTest`: link "Remover" na listagem; página de
  confirmação com o código da sessão; id inexistente; `destroy` sem
  código, com código errado (não remove e invalida o código: nem o
  certo vale depois), sem ter visitado a página, com código de outro
  Evento (não remove); com código certo (remove e limpa); certo
  mas com Inscrições (não remove, mensagem de sempre). Os dois testes
  de `destroy` que já existiam passaram a gerar o código antes.
- `EventsAccessTest`: rota nova em `eventRoutes()` (sem sessão →
  `/login`, Participante → `/dashboard`);
  `test_admin_should_access_the_delete_confirmation_page` (200 e
  código de 4 dígitos);
  `test_admin_should_remove_only_with_the_code_shown_on_the_page`
  (código errado volta à confirmação; abre a página de novo, lê o
  código novo por HTTP e remove).
- `DeleteEventCest`: `clickRemove` agora abre a confirmação, lê o
  código com `grabTextFrom('#confirmation_code_value')`, digita e
  envia; cenário novo `deleteWithWrongCodeKeepsTheEvent`.

Na tela (dentro da rede do compose):

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c \
    http://localhost/admin/events/16/delete | grep confirmation_code
```

## Relação com outros cenários

- R4 (confirmação antes de remover, JS em arquivo próprio): mesma
  intenção, mas o `confirm()` do R4 roda no navegador e um `DELETE`
  direto passa por ele. P16 confere no servidor. Se os dois forem
  pedidos, o `confirm()` pode ir no botão "Remover" da página de
  confirmação.
- P12 (código aleatório no login, a FVP real da 4.1): mesma receita
  (`random_int` no servidor, guardado em `$_SESSION`, conferido no
  POST seguinte). `ConfirmationCode` serve para os dois: chave
  `login.{user_id}` em vez de `event.{id}`.
- P1 (código de check-in): também `random_int(1000, 9999)`, mas
  gravado no banco e público para os inscritos; aqui é temporário e
  só na sessão.
- Regra P03 do projeto (`safeDestroy`: Evento com Inscrições não é
  removido): continua valendo depois do código certo. P17 cria a
  exceção explícita (remover em cascata).
- Q2 e Q1: outro *early return* no `destroy`; com eles, a ordem é
  dono → editável → código.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p16-remover-com-codigo-confirmacao`.

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público. `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/p16-remover-com-codigo-confirmacao`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p16 \
    s50/fvp/4.2/exemplos/p16-remover-com-codigo-confirmacao/projeto
git fetch fvp-p16 fvp/p16-remover-com-codigo-confirmacao
git cherry-pick e72d5f5
git remote remove fvp-p16
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p16-remover-com-codigo-confirmacao/solucao.bundle \
    fvp/p16-remover-com-codigo-confirmacao:fvp-p16
git cherry-pick e72d5f5
# opcional, limpa a ref
git branch -D fvp-p16
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am \
    s50/fvp/4.2/exemplos/p16-remover-com-codigo-confirmacao/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p16-remover-com-codigo-confirmacao/solucao.patch
```

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(classe nova em `lib/`) e os testes.

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

- `index.phtml` (célula de ações): o form "Remover" virou link; R4,
  Q1, Q2, P5, P6 mexem na mesma célula. Fique com o link deste e
  acrescente os botões dos outros.
- `EventsController::destroy`: Q1/Q2 põem *early return* no mesmo
  ponto. Mantenha todos; o `check` do código por último, logo antes
  do `safeDestroy`.
- `config/routes.php`: outras rotas novas perto do `show`; a de
  `.../delete` precisa ficar antes de `GET /admin/events/{id}`.
- `EventsControllerTest` (testes de `destroy` que já existiam): se
  outro cenário também mexeu neles, gere o código antes do `delete`.
- `DeleteEventCest::clickRemove`: R4 também muda esse helper.

## Armadilhas

- Colocar o código certo num `<input type="hidden">` ou na URL: o
  form passa a "confirmar sozinho" e qualquer `DELETE` forjado leva o
  valor junto. O valor certo só existe na sessão.
- Um código só para a sessão inteira (sem chave por Evento): o código
  visto para o Evento A remove o Evento B.
- Limpar o código só no acerto: com o código errado ainda valendo, um
  script repete o `DELETE` e testa os 9000 valores. O teste
  `test_destroy_with_wrong_code_keeps_the_event_and_invalidates_the_code`
  pega isso (depois do erro, nem o código certo remove).
- Não limpar depois do acerto: o mesmo código serve de novo; o teste
  `test_destroy_with_right_code_removes_and_clears_the_code` pega.
- Esquecer o `return` depois do `redirectTo` no código errado: em
  produção o `redirectTo` dá `exit`, mas no teste de controller ele só
  imprime e a ação seguiria para o `safeDestroy`.
- Comparar com `==`: `'1234abc' == 1234` era `true` no PHP 7; aqui a
  comparação é `===` entre strings.
- `$request->getParam('confirmation_code')` pode vir `null` ou array;
  `check` trata os dois.
- Rota `.../delete` depois de `GET /admin/events/{id}`: hoje o router
  não casaria `1/delete` com `{id}`, mas manter as rotas fixas antes do
  `{id}` é o padrão do projeto.
- Os testes de `destroy` que já existiam quebram (o Evento não é
  removido sem código): gere o código com
  `ConfirmationCode::generate("event.{$event->id}")` antes do `delete`.
- `FlashMessage` e `ConfirmationCode` guardam em `$_SESSION`, que
  sobrevive entre testes: limpe `confirmation_codes` no `tearDown`.
