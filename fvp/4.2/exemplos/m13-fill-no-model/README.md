# M13. Montagem do Evento (`fill`/`FIELDS`) sai do controller para o model

Pedido, como o professor faria na hora: "A lista de campos permitidos e
a cópia do formulário para o Evento são regra do model, não do
controller. Leve `FIELDS` e `fill` para o `Event` e deixe `create` e
`update` enxutos, sem mudar o comportamento."

Chance: média-baixa. Tempo estimado: 10-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M13.

Branch no clone: `fvp/m13-fill-no-model`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                         |
|-----------|--------------------------------------------------|
| `830d921` | `refactor: Move event form filling to the model` |

## O que mudou

- `app/Models/Event.php`:
  - `public const FORM_FIELDS` (os mesmos 7 campos do antigo
    `FIELDS`: título, descrição, tipo, modalidade, data, local,
    vagas). Nunca `id` nem `user_id`;
  - `fillFromForm(array $params): void`
    (`@param array<string, mixed>`): copia só as chaves de
    `FORM_FIELDS`; valor ausente ou não escalar vira `null` (a
    validação acusa); `audience` aceita array (checkboxes
    `audience[]`, normalizado por `setAudienceList`) ou string
    `"a,b"`.
- `app/Controllers/Admin/EventsController.php`: saem `FIELDS` e
  `fill()`. `create` e `update` fazem
  `$event->fillFromForm($this->eventParams($request))`; o método
  privado `eventParams()` só lê `event[...]` do `Request` e devolve
  `[]` se não for array. O `user_id` continua sendo definido no
  `create` a partir do usuário logado.
- Testes:
  - `EventTest` (4 novos): copia os campos permitidos (e salva);
    ignora `user_id`, `id` e chaves desconhecidas; ausente/array viram
    `null` e a validação marca "não pode ser vazio!"; `audience` como
    array (com vazio, repetido e não escalar), como string e ausente.
  - `EventsControllerTest` (2 novos): `update` ignora `user_id` e `id`
    forjados; `event` que não é array dá erro de validação, sem 500.
    Os testes existentes (incluindo `create` com `user_id` forjado)
    passam sem mudança.
  - Cests sem mudança.

## Por que assim

- Quais campos o formulário pode preencher e como `audience` vira o
  SET do banco são conhecimento do Evento: no model, qualquer outro
  controller (P2, Participante) reaproveita sem copiar `FIELDS`.
- O controller fica só com HTTP: ler `event[...]` do `Request`,
  chamar o model, flash e redirect.
- Comportamento igual ao de antes, de propósito (é refatoração): campo
  ausente vira `null` também no `update`, como o `fill` fazia. A
  única ampliação é `audience` como string, que o formulário não
  envia, mas que facilita testes e populate.
- Commit `refactor:` porque nada muda para o usuário.
- Alternativa: um service `App\Services\EventForm` com
  `fill(Event $event, array $params)`, se o professor preferir o model
  sem saber de formulário. Exige classe nova e `dump-autoload`; o
  resto é igual.

## Como verificar

```
./run up -d
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium
./run test:browser
```

Resultado obtido: Unit 163 testes, Integration 47, Acceptance 23 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema e populate não
mudaram (`db:populate` roda igual).

No navegador nada muda: cadastrar e editar Eventos funcionam como
antes, com os mesmos erros embaixo dos campos.

## Relação com outros cenários

- P2 (inscrição do Participante) e O2 (área do Participante): outros
  controllers que montem Evento usam `fillFromForm` em vez de copiar
  `FIELDS`.
- S4 (testes de borda): os testes de `fillFromForm` cobrem as bordas
  do formulário (array no lugar de texto, chave ausente, `user_id`).
- M1 e M4 (campo novo): com este cenário aplicado, o campo novo entra
  em `Event::FORM_FIELDS`, não mais no controller.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m13-fill-no-model`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/m13-fill-no-model`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m13 s50/fvp/4.2/exemplos/m13-fill-no-model/projeto
git fetch fvp-m13 fvp/m13-fill-no-model
git cherry-pick 830d921
git remote remove fvp-m13
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/fvp/4.2/exemplos/m13-fill-no-model/solucao.bundle \
    fvp/m13-fill-no-model:fvp-m13
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 830d921
# opcional, limpa a ref
git branch -D fvp-m13
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m13-fill-no-model/solucao.patch
# só o diff, sem commit
git apply --3way s50/fvp/4.2/exemplos/m13-fill-no-model/solucao.patch
```

Depois de aplicar: os testes e o `./run phpcs` (sem mudança de
schema).

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

- `EventsController.php` (`FIELDS`, `fill`, `create`, `update`):
  M1, M4 e M9 mexem em `FIELDS`. Leve o campo novo para
  `Event::FORM_FIELDS` e apague o `FIELDS` do controller.
- `Event.php`: cenários que acrescentam métodos perto de `user()`;
  fique com os dois.

## Armadilhas

- Copiar `$params` inteiro para o model (`new Event($params)`):
  `user_id` forjado no form vira dono do Evento, e chave desconhecida
  lança "Property ... not found". Por isso a lista de permitidos.
- Esquecer o `is_scalar`: `event[title][]=x` chega como array e quebra
  `htmlspecialchars` na view.
- O `getParam('event')` pode não ser array (`event=texto`): o
  controller converte em `[]` antes de chamar o model, que exige
  `array` na assinatura.
- Constante pública no model (`Event::FORM_FIELDS`): se ficar
  `private`, outros controllers voltam a duplicar a lista.
- PHPStan nível 6: `@param array<string, mixed>` no `fillFromForm` e
  `@return array<string, mixed>` no `eventParams`.
