# R13. Textos de vagas com concordância

Pedido, como o professor faria na hora: "'1 disponíveis' está errado.
E a coluna Vagas mostra '1/10': são vagas ou inscritos? Corrija o
texto, sem repetir `if` de plural nas views."

Chance: média-baixa. Tempo estimado: 5-10 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, R13.

Branch no clone: `fvp/r13-concordancia-vagas`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit | Mensagem |
|--------|----------|
| `cac9775` | `feat: Show available spots with number agreement` |

## O que mudou

- `app/Models/Event.php`: método novo logo depois de
  `availableSpots()`:

```php
public function availableSpotsLabel(): string
{
    $spots = $this->availableSpots();

    return match ($spots) {
        0 => 'Lotado',
        1 => '1 vaga disponível',
        default => "{$spots} vagas disponíveis",
    };
}
```

- `app/views/admin/events/show.phtml`: o rótulo virou
  `Inscritos/Vagas`, e o parêntese passou de
  `(<?= $event->availableSpots() ?> disponíveis)` para
  `(<?= htmlspecialchars($event->availableSpotsLabel()) ?>)`.
- `app/views/admin/events/index.phtml`: cabeçalho `Vagas` virou
  `Inscritos/Vagas`. O corpo `1/10` ficou igual.
- `tests/Unit/Models/Events/EventTest.php`: 2 testes novos.
  - Capacidade 3: sem Inscrições dá "3 vagas disponíveis", com 2 dá
    "1 vaga disponível", com 3 dá "Lotado".
  - Borda: `update(['capacity' => 1])` com 2 Inscrições (o `update()`
    do framework não valida) dá `availableSpots()` 0 e "Lotado",
    nunca número negativo.
- `tests/Integration/Controllers/Admin/EventsControllerTest.php`: 2
  testes novos no show.
  - Capacidade 2 e uma Inscrição: aparecem `Inscritos/Vagas`, `1/2` e
    "(1 vaga disponível)", e nenhum "disponíveis".
  - Capacidade 1 e uma Inscrição: "(Lotado)".

## Por que assim

- O texto é montado uma vez, no model, a partir de `availableSpots()`,
  que já existia. A view só imprime. Um `if` de plural repetido em
  duas views é justamente o que o professor cobra.
- `match` compara com `===`. Funciona porque `availableSpots()`
  devolve `int`: o `capacity` que vem do PDO como string já passa por
  `(int)` lá dentro.
- `max(0, ...)` em `availableSpots()` garante "Lotado" mesmo com
  capacidade abaixo das Inscrições.
- O show continua com o mesmo número de consultas: a chamada nova
  substitui a de `availableSpots()`.
- O formato `x/y` não mudou, para não quebrar `ListEventsCest` (`3/3`,
  `0/22`, `1/23`) e os testes de controller (`1/10`, `1/4`). A dúvida
  "vagas ou inscritos?" foi resolvida no cabeçalho.
- `htmlspecialchars` na saída, como nas outras views, mesmo o texto
  sendo fixo.

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

Resultado obtido: Unit 161 testes, Integration 47, Acceptance 23
(eram 159 / 45 / 23 na base). PHPCS e PHPStan sem erros. Schema e
populate não mudaram.

No navegador (populate), logado como admin:

- `/admin/events/1`: "3/3 (Lotado)".
- `/admin/events/2`: "2/80 (78 vagas disponíveis)".
- `/admin/events/3`: "1/25 (24 vagas disponíveis)". Edite a
  capacidade para 2 e o show mostra "1/2 (1 vaga disponível)".
- `/admin/events`: a coluna se chama "Inscritos/Vagas".

Conferido por HTTP no stack: os três primeiros itens acima saíram
exatamente assim.

## Relação com outros cenários

- N6 (`n6-badge-lotado-contador`): o badge "Lotado" da listagem usa
  `availableSpots() === 0`, a mesma condição do `0 => 'Lotado'` daqui.
  Se aplicar os dois, mantenha o mesmo texto ("Lotado"). Dá para
  trocar o badge por `availableSpotsLabel()` se o professor quiser o
  texto completo também na listagem.
- R3 (`r3-helper-formatacao`): centraliza formatação num helper. O
  rótulo de vagas pode ir para lá. Escolha um lugar só (model ou
  helper) e não duplique o `match`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/r13-concordancia-vagas`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/r13-concordancia-vagas`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-r13 \
    s50/fvp/4.2/exemplos/r13-concordancia-vagas/projeto
git fetch fvp-r13 fvp/r13-concordancia-vagas
git cherry-pick cac9775
git remote remove fvp-r13
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/r13-concordancia-vagas/solucao.bundle \
    fvp/r13-concordancia-vagas:fvp-r13
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick cac9775
# opcional, limpa a ref
git branch -D fvp-r13
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/r13-concordancia-vagas/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/r13-concordancia-vagas/solucao.patch
```

Depois de aplicar: os testes e o `./run phpcs` (schema não mudou).

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

- `show.phtml`, bloco de vagas, e `index.phtml`, cabeçalho e coluna
  de vagas: N6, R3 e qualquer campo novo mexem nessas linhas. Fique
  com o rótulo `Inscritos/Vagas` e com a chamada
  `availableSpotsLabel()`.
- `Event.php`, região de `availableSpots()`: N6 e N11 mexem perto.
  Mantenha os dois métodos.
- `EventTest.php` e `EventsControllerTest.php`: testes novos no mesmo
  ponto; fique com os dois.

## Armadilhas

- Mudar o formato `x/y` (para "1 de 10", por exemplo) quebra o
  `ListEventsCest` e os testes de controller com `1/10` e `1/4`.
  Troque só o cabeçalho ou atualize os três na mesma mudança.
- O `enroll($event, $total)` do `EventTest` cria participantes
  `p{id}-{i}@example.com`. Chamá-lo duas vezes no mesmo Evento repete
  o e-mail, o usuário não é salvo e a Inscrição estoura com
  "user_id cannot be null". Use um Evento por caso.
- `availableSpots()` precisa continuar devolvendo `int`. Se alguém
  tirar o `(int)`, o `match` nunca casa `0` nem `1` e tudo cai no
  `default`.
- A remoção barrada também tem número fixo ("com inscrições (1)"),
  mas está fora do pedido. Mudar esse texto quebra o
  `EventsControllerTest` e o `DeleteEventCest`.
