# M8. Enum PHP `EventKind` no lugar de `Event::KINDS`

Pedido, como o professor faria na hora: "troque a constante
`Event::KINDS` por um enum do PHP 8.1, `EventKind: string`, com um
método `label()` que devolve o texto em português. O select, a
listagem, o show e a validação passam a usar o enum."

Escopo: só o tipo (`kind`). `Event::MODALITIES` e `Event::AUDIENCES`
ficaram como estavam, de propósito, porque o pedido cita só `KINDS`.
Se o professor pedir também a modalidade, repita o molde: um
`enum EventModality: string` (`InPerson`, `Online`, `Hybrid`) com
`label()`/`options()`, `tryFrom` na validação, `modalityLabel()` com
`tryFrom` e os radios do `_form` percorrendo `options()`. O público-
alvo é SET (vários valores): o enum serve para a lista de opções, mas a
coluna continua sendo string separada por vírgula.

Chance: média-baixa. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M8.

Branch no clone: `fvp/m8-enum-event-kind`, a partir de `main`
(`f31006b`).

| Commit | Mensagem |
|--------|----------|
| `a87be2d` | `refactor: Replace Event::KINDS with EventKind enum` |

## O que mudou

- `app/Models/EventKind.php` (novo, um enum por arquivo, PSR-4):
  - `enum EventKind: string` com os cases `Lecture` (`lecture`),
    `Workshop` (`workshop`), `ShortCourse` (`short_course`) e
    `AcademicWeek` (`academic_week`): os mesmos valores do
    `ENUM('lecture', 'workshop', 'short_course', 'academic_week')` do
    `schema.sql` (a chave é `short_course`, não `minicourse`).
  - `label(): string` com `match ($this)` exaustivo: Palestra,
    Oficina, Minicurso, Semana acadêmica.
  - `static options(): array` (`@return array<string, string>`):
    valor do banco => rótulo, para o select.
- `app/Models/Event.php`:
  - `KINDS` removida.
  - `validates()`: `notEmpty('kind')` e depois o privado
    `validatesKind()`, que aceita só se
    `EventKind::tryFrom((string) $this->kind) !== null`; senão
    `kind` recebe "não é uma opção válida!" (mesma mensagem do
    `inclusion` de antes).
  - `kindLabel(): string` =
    `EventKind::tryFrom((string) $this->kind)?->label()`, e o valor
    cru se ele não for um case.
- Views: `_form.phtml` percorre `EventKind::options()`;
  `index.phtml` e `show.phtml` mostram
  `htmlspecialchars($event->kindLabel())`.
- `database/Populate/populate.php`: os 16 Eventos usam
  `EventKind::Lecture->value` etc. no lugar da string solta.
- Cests: `Event::KINDS['workshop']` virou
  `EventKind::Workshop->label()`; `Event::KINDS[$event->kind]` virou
  `$event->kindLabel()`; o `BaseEventsCest` tira os valores de
  `EventKind::cases()`. Nenhum cenário novo: os existentes já cobrem o
  select pelo rótulo.
- Testes novos:
  - `EventKindTest` (Unit): rótulos, `options()` na ordem dos cases,
    `tryFrom` conhecido e desconhecido (`concert`, vazio, nome do case
    `Workshop`, rótulo `Oficina`), `from` inválido lança
    `ValueError`, cases iguais ao ENUM do `schema.sql`.
  - `EventTest`: aceita todos os cases (agora por
    `EventKind::cases()`), recusa `Lecture`/`Palestra`/`ShortCourse`,
    `kindLabel()` com case válido e com valor fora do enum
    (`seminar` aparece cru, sem `ValueError`).
  - `EventsControllerTest`: o select do `new` tem cada
    `<option value="...">Rótulo</option>`; o `edit` marca o tipo do
    Evento; listagem e show mostram o rótulo e não o valor cru.

## Por que assim

- Um tipo só para valor e rótulo: o PHP não deixa existir um case fora
  da lista, e o `match` sem `default` faz o phpstan acusar um case
  novo sem rótulo.
- `tryFrom` na validação: devolve `null` para valor desconhecido, sem
  exceção, e o erro vai para o campo como qualquer outra validação do
  model.
- `tryFrom` também na exibição (`kindLabel()`): se um dia houver no
  banco um valor sem case (case removido do enum, dado antigo, `ALTER`
  aplicado antes do código), `from()` lançaria `ValueError` e
  derrubaria a listagem inteira. Com `tryFrom` a tela mostra o valor
  cru e continua de pé. `from()` não aparece no código de `app/`.
- `options()` devolve o mesmo formato do antigo `KINDS`
  (`valor => rótulo`), então o `foreach` do select quase não muda.
- Cases em PascalCase (PSR-12 / PER) e valores iguais ao banco: o
  banco e o `$_POST` continuam guardando a string, nada de migração.
- Rótulo com `htmlspecialchars` na view, como o resto da saída.

## Como verificar

```
./run composer dump-autoload -o --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
./run db:populate
```

Resultado obtido: Unit 171 testes, Integration 48, Acceptance 23
(base 159 / 45 / 23). PHPCS e PHPStan sem erros; PSR-4 estrito ok;
schema não mudou; `db:populate` sem erro.

No navegador: `/admin/events/new` mostra o select com Palestra,
Oficina, Minicurso e Semana acadêmica; a listagem e o show mostram o
rótulo.

## Relação com outros cenários

- M3 (valor novo em ENUM/SET): com o enum, um tipo novo (ex.:
  `seminar`) é um `case Seminar = 'seminar';` mais a linha no
  `match` do `label()` e o valor no `ALTER`/`schema.sql`. O
  `EventKindTest` acusa se o schema e o enum divergirem. Se o M3 já
  estiver aplicado (com `KINDS`), mova o valor novo para um case.
- R3 (formatação na view num só lugar): `kindLabel()` é o mesmo
  movimento, tirar o `KINDS[...] ?? ...` repetido nas views.
  `MODALITIES` e `AUDIENCES` ficaram fora (ver "Escopo" no topo).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/m8-enum-event-kind
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/m8-enum-event-kind`. Cherry-pick cria commit
novo (SHA diferente); preserva conteúdo e mensagem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m8 <caminho>/projeto
git fetch fvp-m8 fvp/m8-enum-event-kind
git cherry-pick a87be2d
git remote remove fvp-m8
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/m8-enum-event-kind:fvp-m8
git cherry-pick a87be2d
# opcional, limpa a ref
git branch -D fvp-m8
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am <caminho>/solucao.patch
# só o diff, sem commit
git apply --3way <caminho>/solucao.patch
```

Depois de aplicar, a classe é nova:

```
./run composer dump-autoload -o --strict-psr
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

- `app/Models/Event.php`: o `validates()` é mexido por quase todo
  cenário M e N. Fique com as regras dos dois; a linha do `kind` é
  `Validations::notEmpty('kind', $this) && $this->validatesKind();`.
- Qualquer arquivo que o outro cenário fez usar `Event::KINDS`
  (filtro por tipo do N1, CSV do P8, JSON do O4): troque por
  `EventKind::options()` ou `$event->kindLabel()`. Procure com
  `grep -rn KINDS app tests database`.
- `populate.php`: Eventos novos de outro cenário com `'lecture'`
  solto funcionam igual; troque por `EventKind::...->value` por
  consistência.
- Cests: `selectOption('#event_kind', ...)` de outro cenário com
  `Event::KINDS[...]` precisa virar `EventKind::X->label()`.

Depois de aplicar: rode `./run phpcs` e os testes de novo.

## Armadilhas

- Confira as chaves reais: no schema é `short_course`. Um case
  `'minicourse'` faria o MySQL recusar o INSERT (ENUM) mesmo com a
  validação passando.
- `from()` lança `ValueError` (não é `Exception`, é `Error`, e o
  `catch (Exception)` não pega). Na validação e na exibição use
  `tryFrom()`; `from()` só onde um valor inválido deve mesmo parar
  tudo (o teste do enum usa para provar isso).
- `match` sem `default`: se faltar um case, o PHP lança
  `UnhandledMatchError` em tempo de execução e o phpstan acusa
  `match.unhandled`. Não ponha `default` para calar o aviso.
- phpstan: `@property string $kind` faz `is_string($this->kind)`
  virar "sempre verdadeiro"; use `tryFrom((string) $this->kind)`. E
  `assertNull(EventKind::tryFrom('concert'))` com literal dá
  "sempre verdadeiro" (o phpstan resolve o enum); use data provider.
- PSR-4 estrito: o enum fica sozinho em `app/Models/EventKind.php`,
  e é preciso o `dump-autoload` (o autoload otimizado não acha a
  classe nova sem ele).
- Enum não tem `__toString`: `<?= EventKind::Lecture ?>` dá erro;
  use `->value` (banco/form) ou `->label()` (tela).
- `tryFrom` diferencia maiúsculas: `Lecture` e o rótulo `Palestra`
  são inválidos (o teste cobre).
- Enums não entram em `$columns`: o model continua guardando a string;
  o enum é só a lista de valores e rótulos.
