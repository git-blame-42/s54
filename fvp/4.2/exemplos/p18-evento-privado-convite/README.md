# P18. Evento privado com código de convite

Pedido, como o professor faria na hora: "Um Evento pode ser privado:
só se inscreve quem informar o código de convite. O código é gerado
pelo sistema e só o administrador o vê."

Chance: baixa. Tempo estimado: 30-40 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P18.

Branch no clone: `fvp/p18-evento-privado-convite`, empilhada sobre
`fvp/p2-inscricao-participante` (P2), que está sobre O2. Cadeia:
`main` (`f31006b`) -> O2 -> P2 -> P18. O cenário precisa da
inscrição pelo Participante (`EnrollmentsController::create` e a
tela `/events/{id}`), que não existe em `main`.

| Commit    | Mensagem                                                  |
|-----------|-----------------------------------------------------------|
| `0669a19` | `feat: Add events listing and details for participants` * |
| `458a05b` | `feat: Allow participants to enroll in events` *          |
| `8e91ab7` | `feat: Add private events with invite code`               |

`*` commits da base (O2 e P2); o deste cenário é só o `8e91ab7`.

## O que mudou

- `database/schema.sql` (`events`): `is_private TINYINT(1) NOT NULL
  DEFAULT 0` e `invite_code SMALLINT UNSIGNED NULL`.
- `app/Models/Event.php`:
  - `'is_private'` e `'invite_code'` em `$columns`, `@property`,
    `INVITE_CODE_MIN = 1000` e `INVITE_CODE_MAX = 9999`.
  - Construtor: `is_private` nulo vira `0`, para o objeto novo já
    ser público antes de salvar (o formulário de `new` usa
    `isPrivate()`). Para o banco não é necessário: o `save()`
    também normaliza.
  - `save()` sobrescrito: normaliza `is_private` para `1`/`0`;
    público fica com `invite_code` `NULL`; privado sem código ganha
    `random_int(1000, 9999)`. Salvar de novo não troca o código.
  - `isPrivate(): bool` e `matchesInviteCode(?string $code): bool`
    (`ctype_digit` + comparação de inteiros; nulo, vazio, `abc`,
    `1234abc` e ` 1234` não casam; Evento público nunca casa).
- `app/Models/Enrollment.php`: propriedade **não persistida**
  `private ?string $inviteCode` com `withInviteCode(?string $code):
  static`. Na criação, Evento privado com código que não casa dá
  `errors('base')` = "código de convite inválido!" (antes da checagem
  de vagas).
- `app/Controllers/EnrollmentsController.php` (`create`): lê
  `invite_code` com `is_string` + `trim` e passa por
  `withInviteCode()`.
- `app/Controllers/Admin/EventsController.php`: `is_private` em
  `FIELDS`; `invite_code` **não** (é gerado no model).
- Views:
  - `admin/events/_form.phtml`: checkbox `event[is_private]`
    (`id="event_is_private"`, valor `1`).
  - `admin/events/show.phtml`: "Visibilidade" = "Público" ou
    "Privado (código de convite: 1234)" (classe `visibility`).
  - `events/show.phtml` (Participante): em Evento privado, o
    formulário de inscrição ganha o campo `invite_code` e o aviso
    "Evento privado: informe o código de convite...". O código nunca
    aparece para o Participante (nem na listagem).
- Populate: sem mudança; os 16 Eventos ficam públicos.
- Testes:
  - `EventTest` (5): público sem código (mesmo forçando um);
    privado gera código na faixa; código não muda ao salvar de novo;
    checkbox desmarcado (null) torna público e apaga o código;
    `matchesInviteCode` com certo, errados e Evento público.
  - `EnrollmentTest` (2): privado recusa nulo, vazio, `abc` e
    código errado com `errors('base')` e aceita o certo; público
    ignora o código.
  - `EnrollmentsControllerTest` (3): privado sem código e com
    código em array recusados (flash), com o código certo (com
    espaços em volta) inscreve.
  - `EventsControllerTest` do Participante (1): privado mostra o
    campo sem revelar o código; público não mostra o campo.
  - `Admin/EventsControllerTest` (3): criar privado gera o código
    ignorando `invite_code` do form e o `show` mostra; update sem o
    checkbox torna público; o formulário tem o checkbox.
  - Aceitação: `admin/events/PrivateEventCest.php` (cadastra
    privado pelo formulário e vê o código; público mostra
    "Público") e `events/PrivateEventEnrollCest.php` (Participante
    tenta sem código, com código errado e com o certo).

## Por que assim

- O código é regra de negócio: nasce no `save()` do Evento (mesmo
  mecanismo do P1), não vem do formulário nem do controller.
- A checagem do convite fica em
  `Enrollment::validatesAvailableSpots()`, o método de
  `validates()` que só roda na criação, junto com "evento não
  encontrado" e "não há vagas". Assim, salvar de novo uma Inscrição
  existente não pede o código, e o controller só repassa o que o
  Participante digitou.
- `withInviteCode()` em vez de coluna: o código digitado não é dado
  da Inscrição, só serve para validar. A propriedade precisa estar
  declarada: o `Model::__set` lança exceção para nomes fora de
  `$columns` e das propriedades.
- `ctype_digit` + `(int)` só depois: `'1234abc'` não pode virar
  `1234`.
- Coluna `is_private` (e não `private`): evita confusão com o
  modificador do PHP e lê melhor (`$event->is_private`).
- Normalizar no model: checkbox desmarcado não é enviado, e o
  `fill()` grava `null`; o `save()` converte para `0`.
- Sem unicidade do código: o convite é por Evento e não identifica
  o Evento sozinho (a URL já identifica). Se o professor pedir único,
  use o laço do P1 com `exists(['invite_code' => ...])`.

## Como verificar

```
# o schema mudou: refaça o banco
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 171, Integration 75, Acceptance 32 (base P2:
164 / 68 / 29; `main`: 159 / 45 / 23). PHPCS e PHPStan sem erros.
`db:populate` sem erro.

Na tela: como `admin@example.com`, edite um Evento, marque "Evento
privado" e salve; o `show` mostra o código. Como `user@example.com`,
abra `/events/<id>`: tente "Inscrever-se" sem código (recusado) e com
o código (inscrito).

## Relação com outros cenários

- P2 (`p2-inscricao-participante`): base direta (inscrição e
  `Enrollment::validatesAvailableSpots()`).
- O2 (`o2-eventos-para-participante`): base do P2 (telas do
  Participante); a listagem dele não mostra o código.
- P1 (`p1-codigo-checkin-aleatorio`): mesmo mecanismo de código
  gerado no `save()`; lá é global e obrigatório, aqui só no privado.
- P14 (`p14-comprovante-inscricao`): também sobre P2, também gera
  número no `save()` (da Inscrição). Juntos, os dois mexem em
  `Enrollment` e em `events/show.phtml`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p18-evento-privado-convite`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/p18-evento-privado-convite`.

Este cenário é empilhado: sem O2 e P2 no alvo, aplique o range
inteiro (`0669a19`, `458a05b` e `8e91ab7`); com O2 e P2 já
aplicados, aplique só o topo (`8e91ab7`).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p18 \
    s50/fvp/4.2/exemplos/p18-evento-privado-convite/projeto
git fetch fvp-p18 fvp/p18-evento-privado-convite
# sem O2 e P2: o range inteiro (base + topo)
git cherry-pick 0669a19^..8e91ab7
# com O2 e P2 já aplicados: só o topo
# git cherry-pick 8e91ab7
git remote remove fvp-p18
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p18-evento-privado-convite/solucao.bundle \
    fvp/p18-evento-privado-convite:fvp-p18
# o bundle traz os objetos; o cherry-pick cria commits novos
git cherry-pick 0669a19^..8e91ab7
# ou, com O2 e P2 já aplicados: git cherry-pick 8e91ab7
# opcional, limpa a ref
git branch -D fvp-p18
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria os commits novos com as mesmas mensagens
git am s50/fvp/4.2/exemplos/p18-evento-privado-convite/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p18-evento-privado-convite/solucao.patch
```

Se o patch trouxer os três commits e o alvo já tiver O2 e P2, o
`git am` para no primeiro com conflito: `git am --skip` em cada
commit da base até chegar ao deste cenário.

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

- `database/schema.sql` e `$columns` do `Event`: todo campo novo
  (M1, P1, M12) entra no mesmo ponto; fique com todos.
- `Event::save()`: o P1 também sobrescreve; junte num `save()` só
  (gere os dois códigos antes do `parent::save()`).
- `Enrollment.php`: o P14 sobrescreve `save()` e acrescenta
  validação; fique com os dois (o convite é checado em
  `validatesAvailableSpots`, o comprovante em `validates`).
- `EventsController::FIELDS`: outros campos novos; fique com todos
  (`invite_code` nunca entra).
- `events/show.phtml`: o P14 mexe no bloco "Você está inscrito"; este
  mexe no formulário do `else`. Fique com os dois.

Depois de aplicar: `./run db:populate` (schema mudou), os testes e
o `./run phpcs`.

## Armadilhas

- Checkbox desmarcado não é enviado: sem normalizar no `save()`,
  `is_private` vai `NULL` e quebra o `NOT NULL` (o `DEFAULT 0` do
  schema não vale, porque o `Model::save()` grava todas as
  colunas). A normalização no `save()` resolve o cadastro e a
  edição; o padrão `0` no construtor é só conveniência.
- Checar o convite em `validates()` direto (fora do ramo de
  criação): ao salvar de novo uma Inscrição existente, o código
  seria exigido outra vez e o `save()` falharia.
- Saída do código no `show` do admin com `htmlspecialchars((string)
  ...)`, como os demais campos, e não com `(int)`.
- `invite_code` em `FIELDS`: o admin escolheria o código pelo POST;
  o teste do controller manda `invite_code=1` e confere que foi
  ignorado.
- `$enrollment->inviteCode = ...` sem declarar a propriedade: o
  `Model::__set` lança "Property inviteCode not found".
- `(int) $code === (int) $event->invite_code` sem `ctype_digit`:
  `'4321abc'` passaria.
- Mostrar o código ao Participante (listagem ou `show`): anula o
  convite. O teste do `show` confere que ele não aparece.
- Teste com código sorteado procurando o número na página: um
  código igual ao ano (ex.: 2026) aparece na data. Os testes que
  procuram "não aparece" fixam o código (4321); o `save()` só gera
  quando vazio.
- `db:populate` esquecido: o banco de desenvolvimento não tem as
  colunas e o cadastro dá 500 (os testes recriam o schema).
