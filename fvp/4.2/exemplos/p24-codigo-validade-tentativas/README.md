# P24. Código de confirmação com validade e limite de tentativas

Pedido, como o professor faria na hora: "O código de confirmação vale 5
minutos e aceita só 3 tentativas; depois disso é invalidado e outro
precisa ser gerado. Mostre mensagens diferentes para código errado,
expirado e tentativas esgotadas."

Chance: média. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P24. O catálogo descreve P24 sobre P16
(código na sessão) ou sobre P23 (código no registro); este exemplo é a
versão sobre P23: validade e tentativas viram colunas do Evento.

Branch no clone: `fvp/p24-codigo-validade-tentativas`, empilhada sobre
P23 (`fvp/p23-codigo-do-registro-para-remover`), que parte de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz). P24 usa a
coluna `confirmation_code`, o `confirmsCode` e o form de remoção do
`show` de P23.

| Commit    | Mensagem                                                     |
|-----------|--------------------------------------------------------------|
| `2af97ce` | (base, P23) `feat: Require the Event confirmation code ...`  |
| `522e028` | `feat: Expire the confirmation code and limit wrong attempts`|

A mensagem completa do commit de P23 é `feat: Require the Event
confirmation code to remove it`.

## O que mudou

- `lib/CodeCheck.php` (novo): `enum CodeCheck` com `Ok`, `Wrong`,
  `Expired`, `Exhausted` e `message()`: "Código incorreto! Tente de
  novo.", "Código expirado! Um novo foi gerado.", "Tentativas
  esgotadas! Um novo código foi gerado.".
- `database/schema.sql`: `confirmation_code_issued_at INT UNSIGNED NOT
  NULL` (epoch) e `confirmation_code_attempts TINYINT UNSIGNED NOT
  NULL` em `events`.
- `app/Models/Event.php`:
  - as duas colunas em `@property` e `$columns`; constantes
    `CODE_TTL_SECONDS = 300` e `CODE_MAX_ATTEMPTS = 3`;
  - construtor: além do código (P23), põe `time()` em `issued_at` e
    `0` em `attempts` quando vazios (o `save()` ignora `DEFAULT`);
  - `issueConfirmationCode(?int $now = null): int`: código novo,
    `issued_at = $now ?? time()`, `attempts = 0`; em registro salvo,
    grava com `update()`;
  - `currentConfirmationCode(?int $now = null): int`: devolve o código
    se ainda vale (sem zerar tentativas); se expirou ou esgotou, chama
    `issueConfirmationCode`;
  - `checkConfirmationCode(mixed $given, ?int $now = null): CodeCheck`:
    esgotado → `Exhausted`; `$now - issued_at > 300` → `Expired`;
    `confirmsCode` (P23) certo → `Ok` e invalida o código (uso único,
    `issued_at = 0`); senão conta a tentativa no banco e devolve
    `Exhausted` na terceira, `Wrong` antes;
  - privados `confirmationCodeState(int $now): ?CodeCheck` e
    `codeAttribute()` (inteiro da coluna via `filter_var`).
- `EventsController`:
  - `show`: `$event->currentConfirmationCode()` antes do `render`;
  - `destroy`: `$result = $event->checkConfirmationCode(...)`; se não
    for `Ok`, `FlashMessage::danger($result->message())`, redirect
    para o `show` e `return`.
- `show.phtml`: ao lado do código, "vale 5 minutos e aceita 3
  tentativas".

Como o usuário obtém código novo: ao voltar para o `show` (o redirect
do erro já faz isso), o código expirado ou esgotado é trocado, e a tela
mostra o novo. Por isso as mensagens dizem "Um novo foi gerado".
Atenção: isso faz o `show` gravar no banco num GET (ver "Por que
assim").

## Por que assim

- Relógio injetável por parâmetro, no molde do `$now` de
  `Validations::notPastDate`: os testes passam um instante fixo
  (`$t = 1_000_000`, `$t + 300`, `$t + 301`) e não há `sleep`. O
  controller não passa nada e vale `time()`.
- A regra fica no model e é testada sem controller
  (`EventConfirmationCodeTest`); o controller só traduz o resultado em
  flash e redirect.
- Epoch (`INT UNSIGNED`) e não `DATETIME`: `time()` não tem fuso, e a
  conta `$now - issued_at` é entre inteiros. `NOW()` ou
  `CURRENT_TIMESTAMP` usariam o relógio do MySQL (UTC), e o PHP roda em
  `America/Sao_Paulo`: o código nasceria 3 horas no futuro.
- `currentConfirmationCode` no `show`: recarregar a tela não zera as
  tentativas (o código vivo é mantido), e um código morto é trocado ao
  voltar. Sem isso, o limite não serviria para nada ou o usuário
  ficaria sem código.
- Efeito colateral: o `show` grava no banco num GET. É idempotente
  (só troca código morto; código vivo fica como está), mas muda o
  `updated_at` do Evento, e o estado é do Evento: compartilhado entre
  admins e abas (um admin que abre o `show` troca o código que outro
  estava digitando, se ele já tinha morrido). Alternativa mais
  ortodoxa: o `show` só exibe "expirado"/"esgotado" e um botão
  `POST` "gerar novo código" chama `issueConfirmationCode()`.
- Esgotado antes de expirado, e nenhum dos dois compara o código:
  depois do bloqueio, nem o certo remove.
- Vazio, `null` e array contam tentativa: são entradas erradas como
  qualquer outra (é o que o catálogo pede).
- Uso único: o `Ok` zera `issued_at`. Se o `safeDestroy()` recusar
  (Evento com Inscrições, regra P03 do projeto), o código usado não
  serve de novo; a próxima visita ao `show` gera outro.
- Um `enum` para o resultado: quatro casos fechados, cada um com sua
  mensagem, sem strings soltas no controller.
- `update()` para gravar tentativas: não passa pela validação do
  Evento e devolve `false` se nada mudou, por isso o retorno não é
  usado como "deu certo".
- Diferença do catálogo: lá a entrada vive em `$_SESSION` (array com
  código, instante e tentativas), porque a base é P16. Aqui a base é
  P23, então o mesmo array vira as três colunas do registro e os
  nomes `issue`/`current`/`check` viram métodos do `Event`.

## Como verificar

```
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

Resultado obtido: Unit 183, Integration 61, Acceptance 26 (com P23:
164 / 55 / 24; base `main`: 159 / 45 / 23). PHPCS e PHPStan sem erros;
PSR-4 estrito ok; o populate roda sem erro.

Testes novos:

- `tests/Unit/Models/Events/EventConfirmationCodeTest.php` (novo, 19
  execuções), com `issueConfirmationCode($t)`: Evento novo com
  `time()` e 0 tentativas; `issue` grava código, instante e 0; certo
  em `$t + 300` → `Ok` (borda); `$t + 301` → `Expired` (sem contar
  tentativa); certo duas vezes → `Ok` e `Expired` (uso único); errado,
  errado → `Wrong`, `Wrong`; terceira → `Exhausted`; depois o certo →
  `Exhausted`; dois errados e o certo → `Ok`; tentativas sobrevivem ao
  recarregar; esgotado vence expirado; `'12a'`, `' 1234'`, `''`,
  `null` e array → `Wrong` e contam; editar o Evento mantém tentativas
  e instante; `current` mantém código vivo e tentativas; `current`
  depois de esgotado e de expirado abre entrada nova (confere
  `attempts` e `issued_at`, não "código diferente", que pode repetir 1
  vez em 9000); mensagens distintas.
- `EventsControllerTest`: os três flashes distintos (`issue(time() -
  301)` para expirado, `attempts = 3` para esgotado, terceira tentativa
  errada); `show` troca código esgotado e expirado e mantém o vivo com
  as tentativas. Os testes de P23 com código errado passaram a esperar
  "Código incorreto! Tente de novo.", também em
  `tests/Integration/Access/EventsAccessTest.php:186` e
  `tests/Acceptance/admin/events/DeleteEventCest.php:64`.
- `DeleteEventCest`: `threeWrongCodesGiveANewCode` (duas vezes
  "incorreto", depois "esgotadas", código novo na tela, acerta e
  remove) e `expiredCodeGivesANewCode`.

Na tela (dentro da rede do compose):

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c \
    http://localhost/admin/events/16 | grep -A3 confirmation_code_value
```

## Relação com outros cenários

- P23 (`p23-codigo-do-registro-para-remover`): base obrigatória
  (coluna, `confirmsCode`, form do `show`).
- P16 (código na sessão): o catálogo descreve P24 também sobre P16, com
  `lib/ConfirmationCode.php` guardando um array em `$_SESSION`. Mesma
  regra; muda só onde o estado vive.
- P12 (FVP real da 4.1, código no login): a mesma validade e o mesmo
  limite valem para o código do login.
- Q1/Q2: outro *early return* no `destroy`; a conferência do código
  continua por último, antes do `safeDestroy()`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p24-codigo-validade-tentativas`.

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público. `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/p24-codigo-validade-tentativas`.

Escolha o que aplicar:

- Sem P23 no seu repositório: aplique o range inteiro
  `5dedab5..522e028` (P23 + P24, dois commits, nessa ordem).
- Com P23 já aplicado: aplique só o topo, `522e028`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p24 \
    s50/fvp/4.2/exemplos/p24-codigo-validade-tentativas/projeto
git fetch fvp-p24 fvp/p24-codigo-validade-tentativas
# range (P23 + P24):
git cherry-pick 5dedab5..522e028
# ou só o topo, se já tem P23:
# git cherry-pick 522e028
git remote remove fvp-p24
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p24-codigo-validade-tentativas/solucao.bundle \
    fvp/p24-codigo-validade-tentativas:fvp-p24
git cherry-pick 5dedab5..522e028
# ou só o topo: git cherry-pick 522e028
# opcional, limpa a ref
git branch -D fvp-p24
```

### 3. A partir do `solucao.patch` (`git am`)

O patch traz os dois commits (P23 e P24), em ordem.

```
git am s50/fvp/4.2/exemplos/p24-codigo-validade-tentativas/solucao.patch
# já tem P23? quando o primeiro commit vier vazio ou conflitar:
# git am --skip
```

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(classe nova em `lib/`), `./run db:populate` (colunas novas) e os
testes.

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

- `database/schema.sql`, `Event::$columns` e o construtor do `Event`:
  outros cenários de coluna nova mexem nas mesmas linhas. Fique com
  todas; os padrões do construtor ficam depois do
  `parent::__construct`.
- `EventsController::destroy`: se o seu P23 for diferente, troque a
  chamada de `confirmsCode` pelo `checkConfirmationCode` e use
  `$result->message()` no flash.
- `EventsController::show`: outro cenário que carregue dados no `show`
  (O3, R11); o `currentConfirmationCode()` fica antes do `render`.
- `EventsControllerTest` e `DeleteEventCest`: testes novos no mesmo
  ponto; fique com todos.

## Armadilhas

- `sleep(301)` no teste: proibitivo. O instante entra como parâmetro.
- `NOW()`/`CURRENT_TIMESTAMP` para o instante: relógio do MySQL em UTC
  contra o PHP em Brasília; o código só expiraria depois de 3h05.
  Guarde e compare inteiros de `time()`.
- `new DateTimeImmutable('@' . $t)` sai em UTC: `format('Y-m-d
  H:i:s')` dá 3 horas a mais que `date()`. Não misture strings de
  fusos diferentes.
- Gerar código novo em toda visita ao `show` (como um `issue` em vez de
  `current`): cada erro volta para a tela com `attempts = 0` e o
  limite nunca dispara.
- `attempts` e `issued_at` `NOT NULL` sem valor no construtor: o
  `save()` manda `NULL` e o `INSERT` falha (o `DEFAULT` não vale).
- Usar o retorno de `update()` como sucesso: ele é `false` quando nada
  mudou.
- Contar tentativa num código já esgotado ou expirado: o contador passa
  de 3 e a mensagem vira "incorreto" em vez da certa.
- Testar `current` depois de esgotado comparando "código diferente":
  falha 1 vez em 9000. Confira `attempts` e `issued_at`.
- Esquecer o `return` depois do `redirectTo` de erro: no teste de
  controller a ação seguiria para o `safeDestroy()`.
