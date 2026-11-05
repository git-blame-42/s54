# M8. Enum PHP `EventKind` no lugar de `Event::KINDS`

Pedido, como o professor faria na hora: "troque a constante
`Event::KINDS` por um enum do PHP 8.1, `EventKind: string`, com um
método `label()` que devolve o texto em português. O select, a
listagem, o show e a validação passam a usar o enum."

Chance: média-baixa. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M8.

Branch no clone: `fvp/m8-enum-event-kind`, a partir de `main`
(`f31006b`).

| Commit | Mensagem |
|--------|----------|
| `f914a63` | `refactor: Replace Event::KINDS with EventKind enum` |

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
  - `kindLabel(): string` = `EventKind::from($this->kind)->label()`.
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
    `kindLabel()`.
  - `EventsControllerTest`: o select do `new` tem cada
    `<option value="...">Rótulo</option>`; o `edit` marca o tipo do
    Evento; listagem e show mostram o rótulo e não o valor cru.

## Por que assim

- Um tipo só para valor e rótulo: o PHP não deixa existir um case fora
  da lista, e o `match` sem `default` faz o phpstan acusar um case
  novo sem rótulo.
- `tryFrom` na validação: devolve `null` para valor desconhecido, sem
  exceção, e o erro vai para o campo como qualquer outra validação do
  model. `from` (que lança `ValueError`) só em `kindLabel()`, usado
  em Evento já salvo (o ENUM do banco garante um valor válido).
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

Resultado obtido: Unit 170 testes, Integration 48, Acceptance 23
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
  movimento, tirar o `KINDS[...] ?? ...` repetido nas views. O mesmo
  vale para `MODALITIES` e `AUDIENCES` (aqui não mudaram, fora do
  pedido).

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
git cherry-pick f914a63
git remote remove fvp-m8
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/m8-enum-event-kind:fvp-m8
git cherry-pick f914a63
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
- `from()` lança `ValueError` (não é `Exception`, é `Error`): use em
  dado já validado. Na validação, `tryFrom()`.
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
