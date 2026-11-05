# R8. Lista de todos os erros no topo do form

Pedido, como o professor faria na hora: "Quando o cadastro ou a edição
falhar, mostre no topo do formulário quais campos estão errados, com o
nome do campo e o motivo. Mantenha a mensagem embaixo de cada campo."

Chance: média. Tempo estimado: 15-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, R8.

Branch no clone: `fvp/r8-erros-no-topo-do-form`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

Commit:

- `02f7509` `feat: List all validation errors on top of the event
  form`

## O que mudou

- `core/Database/ActiveRecord/Model.php`: `allErrors(): array`
  (`@return array<string, string>`), devolve `$this->errors` inteiro
  (atributo => mensagem, na ordem em que a validação adicionou). Antes
  só existia `errors($index)`, uma mensagem por vez; `errors()` sem
  argumento devolve `null`.
- `app/Models/Event.php`: `LABELS` (rótulos pt-BR de `title`,
  `description`, `kind`, `modality`, `audience`, `starts_on`,
  `location`, `capacity` e `user_id`) e `label(string $attribute)`,
  que devolve o rótulo ou o próprio nome (ex.: `base`).
- `app/views/admin/events/_errors.phtml` (novo): se
  `$event->hasErrors()`, um `alert-danger` com id `form_errors`,
  "Corrija os campos abaixo:" e um `<li>` por erro,
  `htmlspecialchars(Event::label($attr))`: `htmlspecialchars($msg)`.
- `app/views/admin/events/_form.phtml`: inclui a parcial antes do
  `<form>`; os `<label>`/`<legend>` dos campos passam a usar
  `Event::label(...)` (um lugar só para os nomes). O feedback por campo
  (`is-invalid` + `invalid-feedback`) não mudou. Vale para new e edit,
  que compartilham o `_form`.
- Testes:
  - `tests/Unit/Core/Database/ModelErrorsTest.php` (novo, model
    anônimo): `allErrors` vazio, com os dois erros em ordem, limpo por
    nova validação e com erro manual (`base`).
  - `EventTest`: `label()` e "todo campo validado tem rótulo".
  - `EventsControllerTest`: create inválido lista os 3 erros (e só
    eles) antes do `<form>`, mantendo o feedback do campo; update
    inválido lista "Vagas: não pode ser menor que o total de
    inscrições (2)!"; new/edit sem erro não mostram a lista; a parcial
    escapa rótulo e mensagem.
  - `CreateWithInvalidDataCest`: os dois cenários conferem as linhas
    em `#form_errors` (todos os campos no form vazio; só Data e Vagas
    no outro, sem "Título:").

## Por que assim

- O core não tinha como pedir todos os erros: `allErrors()` é um
  getter de uma linha no `Model`, útil para qualquer model (bônus de
  framework). A view não acessa `$errors` (protegido) direto.
- Rótulos no model (`LABELS`) e não na parcial: os mesmos nomes
  aparecem no `<label>` do campo e na lista, sem duplicar texto. O
  `label()` tolera atributo sem rótulo (`base`).
- Parcial separada (`_errors.phtml`): o `_form` continua legível e a
  lista pode ir para outro form (ex.: um futuro form de Inscrição).
- Escape na parcial: hoje as mensagens são fixas, mas uma mensagem com
  dado do usuário (ex.: título repetido) passaria a ser XSS sem ele.
- Lista **e** feedback por campo: a lista dá a visão geral; a
  mensagem embaixo do campo mostra onde corrigir.
- O flash "Existem dados incorretos!" continua: ele é do layout, a
  lista é do formulário.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
```

Resultado obtido: Unit 165 testes, Integration 49, Acceptance 23
(base: 159 / 45 / 23; o Cest foi ajustado, sem cenário novo). PHPCS e
PHPStan sem erros. Sem schema nem classe nova em `app/`.

Testes novos:

- `ModelErrorsTest`:
  - `test_all_errors_should_be_empty_without_errors`
  - `test_all_errors_should_return_every_error_in_order`
  - `test_all_errors_should_be_cleared_by_a_new_validation`
  - `test_all_errors_should_include_errors_added_by_hand`
- `EventTest`:
  - `test_label_should_return_the_pt_br_label_of_each_field`
  - `test_every_validated_field_should_have_a_label`
- `EventsControllerTest`:
  - `test_create_with_invalid_data_lists_every_error_on_top`
  - `test_update_with_invalid_data_lists_errors_on_top`
  - `test_valid_forms_do_not_show_the_error_list`
  - `test_error_list_escapes_labels_and_messages`

No navegador: em `/admin/events/new`, clique em "Salvar" sem
preencher. No topo aparece "Corrija os campos abaixo:" com "Título:
não pode ser vazio!", "Descrição: ...", ..., "Público-alvo: não pode
ser vazio!", e cada campo continua vermelho com a sua mensagem.

## Relação com outros cenários

- R1 (`r1-flash-titulo-warning`): o R1 escapa o flash no partial do
  layout; aqui a lista nova já nasce escapada. Os dois juntos não
  conflitam (arquivos diferentes). O flash "Existem dados incorretos!"
  pode até sair se o professor achar a lista suficiente.
- C7 da 4.1 (escape de mensagem): mesma regra, escapar na saída, num
  lugar só.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/r8-erros-no-topo-do-form`
(devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/fvp/4.2/exemplos/r8-erros-no-topo-do-form`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-r8 \
    s50/fvp/4.2/exemplos/r8-erros-no-topo-do-form/projeto
git fetch fvp-r8 fvp/r8-erros-no-topo-do-form
git cherry-pick 02f7509
git remote remove fvp-r8
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/r8-erros-no-topo-do-form/solucao.bundle \
    fvp/r8-erros-no-topo-do-form:fvp-r8
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 02f7509
# opcional, limpa a ref
git branch -D fvp-r8
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/r8-erros-no-topo-do-form/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/r8-erros-no-topo-do-form/solucao.patch
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

Arquivos que costumam conflitar neste cenário e por quê:

- `_form.phtml`: M1 (campo novo) e outros mexem nos campos. Mantenha
  o `require` da parcial no topo e use `Event::label('campo')` no
  `<label>` do campo novo (e acrescente o rótulo em `LABELS`).
- `Event.php`: constantes e métodos novos lado a lado.
- `Model.php`: outro cenário de core (N11 `count()`) no mesmo
  arquivo, em pontos diferentes; fique com os dois.
- `EventsControllerTest.php` e `CreateWithInvalidDataCest.php`:
  testes/asserções novas; fique com os dois lados.

Depois de aplicar: rode os testes e o `./run phpcs` (sem schema).

## Armadilhas

- `errors()` sem argumento devolve `null`, não a lista: por isso o
  `allErrors()` no core.
- Campo novo sem rótulo em `LABELS` aparece na lista com o nome em
  inglês (`starts_on: ...`): o teste
  `test_every_validated_field_should_have_a_label` pega isso.
- `audience` ausente cai no `notEmpty` ("não pode ser vazio!"), não no
  "selecione ao menos uma opção!" (que é para valor vazio/inválido
  depois do `notEmpty`).
- Contar `<li>` na página inteira conta também o menu do navbar: o
  teste recorta só o bloco `#form_errors`.
- Esquecer o escape na parcial: mensagem com HTML vira XSS.
- `hasErrors()` só tem algo depois de `isValid()`/`save()`: no `new` e
  no `edit` (sem validação) a lista não aparece.
