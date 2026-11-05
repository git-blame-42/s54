# M19. "Nenhuma alteração" ao salvar sem mudar nada

Pedido, como o professor faria na hora: "Abri a edição de um Evento,
cliquei em Salvar sem mexer em nada e o sistema disse 'Evento
atualizado com sucesso!'. Se nada mudou, avise 'Nenhuma alteração' e
não grave. A comparação fica no model."

Chance: média. Tempo estimado: 15-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M19.

Branch no clone: `fvp/m19-nenhuma-alteracao`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                           |
|-----------|----------------------------------------------------|
| `37b68a9` | `feat: Warn when saving an event without changes`  |

## O que mudou

- `app/Models/Event.php`:
  - `hasChanges(): bool`, público. Registro novo devolve `true`.
    Senão busca o original **uma vez** com `self::findById($this->id)`
    e compara coluna a coluna de `static::$columns`; na primeira
    diferença devolve `true`, e `false` se todas forem iguais.
  - `comparableValue(string $column, mixed $value): string`, privado
    e estático, deixa os dois lados no mesmo formato:
    - tudo vira string (`null` vira `''`): o banco devolve
      `capacity` como int `20` e o formulário manda `'20'`;
    - `audience` (SET) é quebrado na vírgula, ordenado e remontado;
    - `\r\n` vira `\n` (quebra de linha do textarea).
- `app/Controllers/Admin/EventsController.php`, `update`: depois do
  `fill` e antes do `save`:

```php
if (!$event->hasChanges()) {
    FlashMessage::warning('Nenhuma alteração foi feita no evento.');
    $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
    return;
}
```

- `lib/FlashMessage.php`: `warning(string $value)`, no molde de
  `success`/`danger`. O partial `_flash_message.phtml` já monta
  `alert alert-<?= $type ?>`, então sai `alert-warning` (amarelo) sem
  mexer na view.
- Testes:
  - `FlashMessageTest::test_warning` (cópia de `test_danger`).
  - `EventTest` (13 casos): novo tem mudança; recém-carregado não
    tem; os mesmos valores em string (como o form manda) não são
    mudança; `audience` em outra ordem não é mudança; `\r\n` contra
    `\n` na descrição não é mudança; cada um dos 8 campos alterado é
    mudança (data provider `changedFieldProvider`).
  - `EventsControllerTest` (3): PUT com os mesmos valores redireciona
    para o show com o flash `warning` e sem `success`; `audience` em
    outra ordem dá o mesmo aviso; mudar só o `audience` é mudança
    (flash `success`, sem `warning`, e o banco fica com o público
    novo).
  - `UpdateSuccessfullyCest` (2): no Evento 03 (dois públicos e
    descrição com quebra de linha gravada como `\n`), abrir a edição
    e clicar em Salvar mostra o aviso em `.alert-warning` e não
    "atualizado"; mudar só a capacidade continua atualizando.

## Por que assim

- Comparação no model, controller fino: o controller só pergunta
  `hasChanges()` e escolhe o flash.
- O original é carregado uma vez, não uma consulta por campo, e
  comparado com todas as colunas. Campo novo em `$columns` (M1, M4)
  entra na comparação sem mexer no método.
- O model do framework não guarda o "antes" (não há snapshot no
  `findById`); buscar de novo no banco é o mesmo caminho que
  `validatesStartsOnNotPast` (e o M10) já usam. Não mexi no `core/`.
- Normalização obrigatória, cada uma provada por teste:
  - `capacity`: `20 !== '20'`. Sem o `(string)`, todo Salvar seria
    "mudança".
  - SET: o MySQL devolve os valores na ordem da definição da coluna,
    não na ordem enviada. O form já envia nessa ordem, mas um PUT
    feito à mão (ou outro form) não precisa.
  - Textarea: o navegador envia quebra de linha como `\r\n`. Um
    Evento gravado com `\n` sempre pareceria alterado. O populate e
    o seed dos Cests não têm descrição em várias linhas: o `\n` do
    Cest novo vem dele mesmo, que grava `"Linha 1\nLinha 2"` com
    `Event::update()` antes de abrir o form (o mesmo acontece com um
    Evento gravado por script, teste ou pelo `Model::update`).
    Conferido: sem o `str_replace`, o Cest novo falha.
- Checagem antes do `save()`, sem validar: se nada mudou não há o que
  gravar, e os dados já estão no banco. Aviso, não erro: o usuário
  não fez nada de errado, por isso `warning` e redirecionamento para
  o show, como no sucesso.
- `warning` idêntico ao do R1 (mesmo método, mesmo lugar). Com
  `git cherry-pick` ou `git am -3`, em qualquer ordem, o trecho igual
  é absorvido sem conflito e fica um `warning` só. `git am` puro
  falha (ver "Se der conflito").

## Como verificar

```
./run up -d db php php_test web
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 173 testes, Integration 48, Acceptance 25 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Sem schema novo nem classe
nova.

Testes novos:

- `FlashMessageTest::test_warning`
- `EventTest`:
  - `test_has_changes_should_be_true_for_a_new_record`
  - `test_has_changes_should_be_false_right_after_loading`
  - `test_has_changes_should_be_false_with_the_same_values_as_...`
    (`..._form_strings`)
  - `test_has_changes_should_ignore_the_order_of_audience`
  - `test_has_changes_should_ignore_crlf_line_breaks_in_description`
  - `test_has_changes_should_detect_each_changed_field` (8 casos)
- `EventsControllerTest`:
  - `test_update_without_changes_warns_and_redirects_to_show`
  - `test_update_with_audience_in_another_order_is_not_a_change`
  - `test_update_changing_only_the_audience_is_a_change`
- `UpdateSuccessfullyCest`:
  - `saveWithoutChangesShowsWarning`
  - `saveAfterChangingOnlyOneFieldStillUpdates`

No navegador (populate): Admin, "Editar" em qualquer Evento, "Salvar"
sem mexer. Aparece o aviso amarelo "Nenhuma alteração foi feita no
evento." na página do Evento.

## Relação com outros cenários

- R1 (`r1-flash-titulo-warning`): cria o mesmo
  `FlashMessage::warning`. Se o R1 já estiver aplicado, este cenário
  só usa o método; se o professor pedir o título no flash, a
  mensagem vira `Nenhuma alteração no evento "<título>".` (o R1
  escapa no partial).
- M10 (`m10-campo-imutavel-com-inscricoes`): também compara com o
  original do banco (`findById`), mas campo a campo, dentro de
  `validates()`. Com os dois, o `hasChanges()` roda antes e um Salvar
  sem mudança nem chega à validação.

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/m19-nenhuma-alteracao`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m19-nenhuma-alteracao
cd /caminho/do/seu/repositorio
git remote add fvp-m19 \
    s50/fvp/4.2/exemplos/m19-nenhuma-alteracao/projeto
git fetch fvp-m19 fvp/m19-nenhuma-alteracao
git cherry-pick 37b68a9
git remote remove fvp-m19
```

### 2. A partir do `solucao.bundle`

```
D=s50/fvp/4.2/exemplos/m19-nenhuma-alteracao
git fetch $D/solucao.bundle \
    fvp/m19-nenhuma-alteracao:fvp-m19
git cherry-pick 37b68a9
# opcional, limpa a ref
git branch -D fvp-m19
```

### 3. A partir do `solucao.patch`

```
D=s50/fvp/4.2/exemplos/m19-nenhuma-alteracao
# cria commit novo com a mesma mensagem
git am $D/solucao.patch
# só o diff, sem commit
git apply --3way $D/solucao.patch
```

### Se der conflito

```
# arquivos marcados "both modified"
git status
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue
# ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar e por quê:

- Com o R1 já aplicado (conferido aplicando sobre a branch do R1):
  - `git cherry-pick` e `git am -3` passam sem conflito; o
    `warning` igual dos dois lados é absorvido e o commit não mexe
    mais em `lib/FlashMessage.php` nem em `FlashMessageTest.php`.
  - `git am` puro (sem `-3`) falha em `FlashMessage.php`,
    `FlashMessageTest.php`, `EventsController.php` e
    `EventsControllerTest.php`: o contexto do patch mudou. Faça
    `git am --abort` e repita com `git am -3 $D/solucao.patch`.
  - Depois de juntar, o texto do sucesso é o do R1
    (`Evento "<título>" atualizado com sucesso!`): troque a string
    em `test_update_changing_only_the_audience_is_a_change` e em
    `saveAfterChangingOnlyOneFieldStillUpdates`.
- `lib/FlashMessage.php` com outro cenário que crie `warning`/`info`
  no mesmo ponto: fique com um método de cada.
- `EventsController::update`: R1 troca o texto do sucesso, Q2 põe um
  `isEditable()` no começo. O bloco do `hasChanges()` fica logo
  depois do `fill`, antes do `if ($event->save())`.
- `app/Models/Event.php`: métodos novos entre `validates()` e
  `user()`; fique com os dois lados.
- `EventTest.php`, `EventsControllerTest.php`,
  `UpdateSuccessfullyCest.php`: testes novos no fim; fique com os
  dois.

## Armadilhas

- Comparar com `!==` sem normalizar: `capacity` `20` (int do banco)
  contra `'20'` (string do form) é sempre diferente, e o aviso nunca
  aparece. `!=` resolve o número, mas não o SET nem o `\r\n`.
- SET em ordem diferente: `'professors,students'` contra
  `'students,professors'` é o mesmo público. Ordene antes de comparar.
- Textarea: o navegador manda `\r\n`. Só os testes Unit/Integration
  não pegam isso (lá o texto vai como está); o Cest pega.
- Buscar o original uma vez por campo (`findById` dentro do laço):
  funciona, mas são 9 consultas por Salvar.
- Comparar depois do `save()`: aí o banco já tem os valores novos e
  tudo parece "sem mudança". A checagem vem antes.
- Esquecer o `return` depois do `redirectTo`: no navegador o `exit`
  segura, mas no `ControllerTestCase` o `redirectTo` só faz `echo`, o
  código segue para o `save()` e o teste vê também o flash `success`.
- Valores "equivalentes" mas com texto diferente contam como mudança
  (`'020'` contra `20`, espaço no fim do título). O `save()` grava e
  o resultado é o esperado; só não sai o aviso.
