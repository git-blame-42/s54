# R10. "Salvar e cadastrar outro"

Pedido, como o professor faria na hora: "No cadastro de Evento, além
de 'Salvar', ponha um botão 'Salvar e cadastrar outro' que grava e
volta para um formulário vazio, para cadastrar vários em sequência."

Chance: média-baixa. Tempo estimado: 10-15 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, R10.

Branch no clone: `fvp/r10-salvar-e-cadastrar-outro`, a partir de
`main` (`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                          |
|-----------|---------------------------------------------------|
| `03fe748` | `feat: Add save and new button to the event form` |

## O que mudou

- `app/Controllers/Admin/EventsController.php`:
  - constante `SAVE_AND_NEW = 'Salvar e cadastrar outro'`;
  - `create`, depois do `save()` bem-sucedido e do flash: se
    `$request->getParam('commit') === self::SAVE_AND_NEW`, redireciona
    para `route('admin.events.new')` e faz `return`; senão segue para o
    `show`, como antes. Cadastro inválido não mudou (re-renderiza o
    form com erros).
- `app/views/admin/events/_form.phtml`: só quando `!$isUpdate`, um
  segundo `<button type="submit" name="commit" value="Salvar e
  cadastrar outro">` ao lado de "Salvar" (texto e valor vêm da
  constante).
- Testes:
  - `EventsControllerTest`: com o botão, grava e `Location:
    /admin/events/new` com o flash; valores inesperados (`'Salvar'`,
    outro texto, array) caem no `show`; inválido com o botão mostra o
    form com erros; o botão existe no `new` e não no `edit`.
  - `CreateSuccessfullyCest::createTwoEventsInARowWithSaveAndNew`:
    cadastra dois seguidos, sempre de volta a `/admin/events/new` com
    o título vazio.

## Por que assim

- O botão fica fora de `event[...]` (`name="commit"`): o `fill()` só lê
  `event[...]` e os campos de `FIELDS`, então o valor do botão nunca
  vai para o model.
- Só o botão clicado é enviado no POST: "Salvar" (sem `name`) não manda
  `commit`, e o comportamento padrão continua.
- Comparação estrita com a constante: `getParam` pode trazer array ou
  outro texto; qualquer coisa diferente cai no padrão (o teste cobre).
  Texto e comparação vêm da mesma constante, então mudar o rótulo não
  quebra o fluxo.
- `return` depois do `redirectTo`: no teste de controller o
  `redirectTo` não encerra o script; sem o `return`, o segundo
  redirecionamento sobrescreveria o primeiro.
- O flash de sucesso aparece no formulário novo (o layout mostra o
  flash em qualquer página).
- Edição sem o botão: "cadastrar outro" não faz sentido no `edit`. A
  variante "Salvar e continuar editando" seria o mesmo padrão: no
  `update`, com `commit` igual a outra constante, redirecionar para
  `admin.events.edit` em vez do `show`. Ficou de fora (opcional).

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

Resultado obtido: Unit 159 testes, Integration 49, Acceptance 24
(base: 159 / 45 / 23). PHPCS e PHPStan sem erros. Sem schema nem
classe nova.

Testes novos:

- `EventsControllerTest`:
  - `test_create_with_save_and_new_redirects_to_a_blank_form`
  - `test_create_with_unexpected_commit_value_goes_to_show`
  - `test_create_invalid_with_save_and_new_renders_the_form_with_errors`
  - `test_save_and_new_button_only_in_the_new_form`
- `CreateSuccessfullyCest::createTwoEventsInARowWithSaveAndNew`

No navegador: em `/admin/events/new`, preencha e clique em "Salvar e
cadastrar outro": volta ao formulário vazio com "Evento cadastrado com
sucesso!". "Salvar" continua levando ao detalhe do Evento.

## Relação com outros cenários

- R1 (`r1-flash-titulo-warning`): se o R1 estiver aplicado, o flash
  traz o título do Evento recém-cadastrado, o que ajuda aqui (o usuário
  não vê o detalhe). Conflito na linha do `FlashMessage::success` do
  `create`: fique com o texto do R1 e com o `if` deste.
- M13: também mexe no fluxo do `create`/formulário; junte os dois
  trechos no `create` (o `if` deste fica logo depois do flash).
- R8 (`r8-erros-no-topo-do-form`): ambos mexem no `_form.phtml`, em
  pontos diferentes (topo e botões); fique com os dois.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/r10-salvar-e-cadastrar-outro`
(devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50:
`s50/fvp/4.2/exemplos/r10-salvar-e-cadastrar-outro`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-r10 \
    s50/fvp/4.2/exemplos/r10-salvar-e-cadastrar-outro/projeto
git fetch fvp-r10 fvp/r10-salvar-e-cadastrar-outro
git cherry-pick 03fe748
git remote remove fvp-r10
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/r10-salvar-e-cadastrar-outro/solucao.bundle \
    fvp/r10-salvar-e-cadastrar-outro:fvp-r10
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 03fe748
# opcional, limpa a ref
git branch -D fvp-r10
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/r10-salvar-e-cadastrar-outro/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/r10-salvar-e-cadastrar-outro/solucao.patch
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

- `EventsController::create`: R1 (texto do flash), Q1/M11 (regras de
  criação). Mantenha o `if (commit === SAVE_AND_NEW)` logo depois do
  flash e antes do redirect para o `show`.
- `_form.phtml` (área dos botões): outro cenário que mude "Salvar"/
  "Cancelar". Fique com os botões dos dois.
- `EventsControllerTest.php` e `CreateSuccessfullyCest.php`: testes
  novos no fim; fique com os dois lados.

Depois de aplicar: rode os testes e o `./run phpcs` (sem schema).

## Armadilhas

- Pôr o botão como `event[commit]`: o valor entra no array do Evento e
  qualquer cenário que copie `event[...]` sem filtro tentaria gravá-lo.
- Esquecer o `return` depois do primeiro `redirectTo`: no teste, a
  saída fica com o `Location` do `show`.
- Comparar com `==` ou sem conferir o tipo: `commit` como array não
  pode cair no ramo especial.
- O Codeception clica pelo texto: `click('Salvar')` continua achando o
  botão "Salvar" (texto exato) e não o "Salvar e cadastrar outro"; os
  Cests existentes passam sem mudança.
- No `edit` o botão não aparece (`$isUpdate`); se aparecer, o `update`
  ignoraria o valor e iria para o `show` do mesmo jeito.
- O formulário novo vem vazio (é um GET novo); valores do Evento
  anterior não são copiados. Se o professor quiser "repetir os dados",
  é outro cenário (P6, duplicar).
