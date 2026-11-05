# R1. Flash: texto, título do Evento, tipo `warning`

Pedido, como o professor faria na hora: "Mostre o título do Evento nas
mensagens de cadastro, atualização e remoção. Quando a remoção for
barrada por Inscrições, use um flash de aviso (`warning`), não de
erro. Cuidado com título que tenha HTML."

Chance: média-alta. Tempo estimado: 10-15 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, R1 (detalhes: `e4.2-preparo-fvp.md`, M9).

Branch no clone: `fvp/r1-flash-titulo-warning`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

Commit:

- `75e9e86` `feat: Show event title in flash messages and add warning
  type`

## O que mudou

- `app/Controllers/Admin/EventsController.php`, textos com o título
  **cru**:
  - `create`: `Evento "<título>" cadastrado com sucesso!`
  - `update`: `Evento "<título>" atualizado com sucesso!`
  - `destroy`: `Evento "<título>" removido com sucesso!`
  - `destroy` barrado: `FlashMessage::warning(...)` com
    `Evento "<título>" não removido: <motivo do model>`, em que o
    motivo é `$event->errors('base')` (ex.: "não é possível remover
    um evento com inscrições (3)!"). Antes era `danger` só com o
    motivo.
  - "Evento não encontrado!" e "Existem dados incorretos!" não mudam.
- `lib/FlashMessage.php`: `warning(string $value)`, igual a
  `success`/`danger` (`self::message('warning', $value)`).
- `app/views/layouts/_flash_message.phtml`: `<?= $msg ?>` passa a
  `<?= htmlspecialchars($msg) ?>`. O partial já monta
  `alert alert-<?= $type ?>`, então `warning` vira `alert-warning`
  (amarelo) sem mais nada.
- Testes:
  - `FlashMessageTest::test_warning` (cópia de `test_danger`).
  - `EventsControllerTest`: as 4 asserções de texto exato mudaram
    (create, update, destroy, destroy barrado agora em
    `$_SESSION['flash']['warning']`); dois testes novos (escape do
    título com `<script>` na página seguinte e classe
    `alert-warning`).
  - Cests: `CreateSuccessfullyCest`, `UpdateSuccessfullyCest` e
    `DeleteEventCest` com os textos novos (e o seletor
    `.alert-success`/`.alert-warning`); `DeleteEventCest` ganhou
    `flashWithSpecialCharactersInTitleIsEscaped` (cadastra
    `A & "B" <b>x</b>`, remove e vê o título literal).

## Por que assim

- Título cru no controller e escape num lugar só, o partial: protege
  todas as mensagens, inclusive as futuras, e evita o escape duplo
  (`&amp;amp;` no código-fonte, `&amp;` literal na tela) que aparece
  quando se escapa nos dois lugares.
- Nenhuma mensagem atual depende de HTML no flash, então escapar no
  partial não quebra nada (conferido com `grep FlashMessage:: app`).
- `warning` para a remoção barrada: não é erro do sistema nem dado
  inválido, é uma regra de negócio (P03) que o usuário precisa ver.
  O texto do motivo continua vindo do model (`safeDestroy`); o
  controller só acrescenta o título.
- `$title` da página do `show` já era escapado no controller e não
  passa pelo partial: não foi mexido.

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

Resultado obtido: Unit 160 testes, Integration 47, Acceptance 24
(base: 159 / 45 / 23). PHPCS e PHPStan sem erros. Sem schema novo nem
classe nova.

Testes novos:

- `FlashMessageTest::test_warning`
- `EventsControllerTest`:
  - `test_flash_with_event_title_is_escaped_once_in_the_layout`
  - `test_warning_flash_uses_the_warning_alert_class`
- `DeleteEventCest::flashWithSpecialCharactersInTitleIsEscaped`

No navegador: cadastre um Evento com título `A & "B" <b>x</b>` (sem
Inscrições) e remova-o. A mensagem verde mostra
`Evento "A & "B" <b>x</b>" removido com sucesso!` literal, sem negrito.
Tente remover o primeiro Evento (lotado, 3 Inscrições): aparece o
alerta amarelo "... não removido: não é possível remover um evento
com inscrições (3)!".

## Relação com outros cenários

- Q1 (`q1-so-dono-edita-remove`): mesmo controller; se o professor
  quiser mensagem própria para Evento alheio, use o `warning` daqui.
  Conflito pequeno no `destroy` (fique com os dois).
- P10 (`p10-comentarios-evento`): o flash de "comentário publicado"
  também passa pelo partial escapado; não escape no controller.
- P12 (`p12-codigo-aleatorio-login`): mostra o código no flash `info`
  ou `warning`; o escape do partial não atrapalha (só dígitos).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/r1-flash-titulo-warning`
(devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/fvp/4.2/exemplos/r1-flash-titulo-warning`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-r1 \
    s50/fvp/4.2/exemplos/r1-flash-titulo-warning/projeto
git fetch fvp-r1 fvp/r1-flash-titulo-warning
git cherry-pick 75e9e86
git remote remove fvp-r1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/r1-flash-titulo-warning/solucao.bundle \
    fvp/r1-flash-titulo-warning:fvp-r1
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 75e9e86
# opcional, limpa a ref
git branch -D fvp-r1
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/r1-flash-titulo-warning/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/r1-flash-titulo-warning/solucao.patch
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

- `EventsController.php` (linhas dos `FlashMessage::`): qualquer
  cenário que mexa em create/update/destroy. Fique com a lógica do
  outro e com os textos deste.
- `EventsControllerTest.php` e os Cests de Eventos: asserções de texto
  exato. Use os textos novos em todas.
- `_flash_message.phtml`: se o outro lado já escapou no partial,
  mantenha um `htmlspecialchars` só; se escapou no controller, tire de
  lá.

Depois de aplicar: rode os testes e o `./run phpcs` (sem schema).

## Armadilhas

- Escapar nos dois (controller e partial) dá `&amp;amp;`: o teste
  `test_flash_with_event_title_is_escaped_once_in_the_layout` confere
  que não acontece.
- Um flash por tipo (`$_SESSION['flash'][$type]`): duas mensagens do
  mesmo tipo na mesma requisição, a segunda apaga a primeira. Por isso
  o aviso de remoção virou `warning` e não um segundo `danger`.
- O texto do motivo ("não é possível remover...") é do model
  (`Event::safeDestroy`); mudar esse texto é lá, e o teste do model
  também confere.
- Create e update redirecionam para o `show`: o flash de sucesso
  aparece lá, não na listagem.
- Muitas asserções conferem a string exata (Integration e Cests):
  mudam todas juntas.
- `_flash_message.phtml` termina com `<? endforeach; ?>` (short tag):
  não mexa, funciona porque `short_open_tag` está ligado na imagem.
- No teste de controller o flash só vira HTML na requisição seguinte:
  o teste faz o `destroy` e depois um `get('index')` para ver o
  partial.
