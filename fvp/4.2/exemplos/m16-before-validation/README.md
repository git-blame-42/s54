# M16. Gancho `beforeValidation` no core (espaços nas pontas)

Pedido, como o professor faria na hora: "Um título só com espaços está
passando. Tire os espaços das pontas antes de validar, mas faça isso no
framework: crie um gancho `beforeValidation` no `Model`, chamado antes
do `validates()`, e use no Evento."

Chance: baixa. Tempo estimado: 15-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M16.

Branch no clone: `fvp/m16-before-validation`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                 |
|-----------|----------------------------------------------------------|
| `ea5ab92` | `feat: Add beforeValidation hook to Model::isValid`      |
| `b1087e5` | `feat: Trim Event title, description and location...`    |

(mensagem completa do segundo: `feat: Trim Event title, description
and location before validation`)

## O que mudou

- `core/Database/ActiveRecord/Model.php`: `isValid()` limpa os erros,
  chama `$this->beforeValidation()` e só então `$this->validates()`.
  Método novo `protected function beforeValidation(): void {}`, vazio:
  os models que não sobrescrevem (`User`, `Enrollment`) continuam
  iguais.
- `app/Models/Event.php`: sobrescreve `beforeValidation()` e faz
  `trim` em `title`, `description` e `location`, só se o valor for
  string (`null` continua `null` e cai no "não pode ser vazio!").
- Testes:
  - `tests/Unit/Core/Database/ActiveRecord/BeforeValidationTest.php`
    (novo, 6), com dois models de teste na mesma pasta
    (`TrimmingModel`, que sobrescreve o gancho e registra a ordem das
    chamadas, e `PlainModel`, que não sobrescreve): o gancho roda
    antes de `validates()`; roda a cada `isValid()`; `validates()` já
    vê o valor aparado (`"   \t\n"` vira `''` e é inválido); o valor
    aparado fica no objeto; `null` não é tocado; o gancho padrão não
    muda nada (`'   '` continua `'   '`). Sem banco.
  - `tests/Unit/Models/Events/EventTest.php` (8): `title`,
    `description` e `location` só com espaços são inválidos, com um
    erro só, no campo certo (data provider, 3); `save()` grava
    `' Oficina '` como `'Oficina'` (conferido com `findById`), e o
    mesmo para descrição e local; espaços do meio ficam; `save()` na
    edição também apara; `kind` com espaços continua inválido (o trim
    é só dos textos livres); `update()` NÃO apara (documenta o
    limite).
  - `CreateWithInvalidDataCest`: `createWithOnlySpacesInTextFields`
    (título, descrição e local com `'   '` mostram "não pode ser
    vazio!" nos três, nada é gravado).
  - `CreateSuccessfullyCest`: `createEventTrimsOuterSpaces` (título
    `'   Oficina de Git   '` vira `Oficina de Git` no `h1` do show e
    no banco; descrição e local também).

## Por que assim

- A regra fica no model, perto do `validates()`, e o gancho no core
  serve a qualquer model. Pôr `trim` no `fill` do controller também
  funciona, mas é regra de negócio no controller e só vale para quem
  passa por ali (testes do model, populate e outros controllers não).
- O gancho é chamado dentro de `isValid()`, e não em `save()`: assim
  quem só valida (`isValid()` nos testes, formulário com erro) já vê o
  valor normalizado, e `save()` herda porque chama `isValid()`.
- `protected` e vazio no core: não muda nenhum model que não peça.
- Só os três campos de texto livre. `kind`, `modality`, `audience`,
  `starts_on` e `capacity` são validados pelo valor (lista, data,
  inteiro); aparar ali esconderia dado errado vindo do formulário.
- `is_string` antes do `trim`: `trim(null)` é depreciado no PHP 8.1+ e
  o `null` precisa chegar ao `notEmpty` para dar o erro de sempre.
- Não mexi em `Validations::notEmpty`: mudar a validação afetaria
  senha e qualquer outro campo do projeto inteiro.

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
```

Resultado obtido: Unit 173, Integration 45, Acceptance 25 (base 159 /
45 / 23; dois Cests novos). PHPCS e PHPStan sem erros; PSR-4 estrito
ok. Schema e populate não mudaram.

Na tela (populate): logue como `admin@example.com` / `123456`, abra
`/admin/events/new`, preencha tudo e ponha só espaços no título: volta
o formulário com "não pode ser vazio!" no título. Com
`"  Oficina  "`, o show abre com o título `Oficina`. Pelo terminal, de
dentro da rede (o POST devolve o form com o erro no título):

```
docker compose exec -T web curl -s -c /tmp/c \
  -d 'user[email]=admin@example.com&user[password]=123456' \
  http://localhost/login
docker compose exec -T web curl -s -b /tmp/c \
  --data-urlencode 'event[title]=   ' \
  -d 'event[description]=Desc&event[kind]=lecture' \
  -d 'event[modality]=online&event[audience][]=students' \
  -d 'event[starts_on]=2030-01-01&event[location]=Sala' \
  -d 'event[capacity]=10' \
  http://localhost/admin/events | grep -A2 'id="event_title"'
```

## Relação com outros cenários

- M2 (`m2-validacao-maxlength`): com o gancho, `maxLength`/`minLength`
  contam o texto já aparado (`'  ab  '` tem 2 caracteres, não 6).
- M6 (`m6-unicidade-titulo-data`): sem o trim, `'Oficina '` e
  `'Oficina'` passam como títulos diferentes no `uniqueness`; com o
  gancho, viram o mesmo e a duplicidade é pega.
- M9 (`m9-slug-unico-gerado`): o slug é gerado a partir do título; o
  `beforeValidation()` é um lugar natural para gerá-lo (já com o
  título aparado), em vez de sobrescrever `save()`.
- M13 (`m13-fill-no-model`): se o `fill` foi para o model, o `trim`
  poderia ir junto; o gancho continua melhor porque vale também para
  quem monta o Evento sem o `fill`.
- M14 (`m14-contador-visualizacoes`): usa `Model::update()`, que não
  passa por `isValid()`, logo nem pelo gancho (ver Armadilhas).
- M19 (`m19-nenhuma-alteracao`): a comparação com o original deve ser
  feita depois do trim, senão `'Oficina '` conta como alteração.
- S4 (`s4-testes-de-borda`): "título só com espaços" é um teste de
  borda clássico; este cenário é a correção.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/m16-before-validation
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/m16-before-validation`. Cherry-pick cria commit
novo (SHA diferente); preserva conteúdo e mensagem. São dois commits:
pegue os dois, na ordem (o primeiro sozinho dá só o gancho do core).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m16 <caminho>/projeto
git fetch fvp-m16 fvp/m16-before-validation
git cherry-pick ea5ab92 b1087e5
git remote remove fvp-m16
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle \
  fvp/m16-before-validation:fvp-m16
git cherry-pick ea5ab92 b1087e5
# opcional, limpa a ref
git branch -D fvp-m16
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria os dois commits, com as mesmas mensagens
git am <caminho>/solucao.patch
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

- `Model.php`, `isValid()`: outro cenário que mexe no core (N11,
  N14) costuma acrescentar métodos em outro ponto; se alguém também
  mudou `isValid()`, mantenha a ordem: limpar erros,
  `beforeValidation()`, `validates()`.
- `Event.php`: quase todo cenário M acrescenta linhas no
  `validates()`. O método novo fica logo antes dele; fique com os
  dois. Se outro cenário (M9) já tiver um `beforeValidation()`,
  junte os dois corpos num método só.
- `EventTest.php`, `CreateWithInvalidDataCest.php`,
  `CreateSuccessfullyCest.php`: testes novos no mesmo ponto; mantenha
  todos.

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(pasta e classes de teste novas), `./run phpcs`, `./run phpstan` e os
testes.

## Armadilhas

- `update()` não passa por `isValid()` (`Model::update` grava direto
  as colunas recebidas): o gancho vale só para `save()`. O CRUD de
  Eventos usa `save()` no create e no update do controller, então a
  tela apara nos dois; quem chamar `$event->update([...])` grava o
  texto cru (o teste `test_update_should_not_run_before_validation`
  deixa isso explícito). Diga isso se o professor perguntar.
- Não leve o gancho para o `User` sem pensar: espaço na senha é
  legítimo, e aparar a senha muda o hash. E-mail e nome poderiam,
  senha e confirmação não. Código de check-in/convite (P1, P18) é
  comparado ao que o usuário digita: apare os dois lados ou nenhum.
- `trim` não tira espaço não separável (U+00A0, comum em texto
  colado). Se o professor testar isso, troque por
  `preg_replace('/^\s+|\s+$/u', '', $valor)`.
- O gancho altera o objeto mesmo quando a validação falha: o
  formulário volta com o valor já aparado (título `'   '` volta
  vazio). É o comportamento esperado, mas saiba explicar.
- `is_string` antes do `trim`: o `fill` do controller põe `null` em
  campo ausente, e `trim(null)` dá aviso de depreciação no PHP 8.1+.
- Pasta nova de teste (`tests/Unit/Core/Database/ActiveRecord`):
  namespace igual ao caminho e um model de teste por arquivo, senão o
  `--strict-psr` reclama.
