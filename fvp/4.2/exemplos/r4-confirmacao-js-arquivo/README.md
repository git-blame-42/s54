# R4. Confirmação antes de remover, JS em arquivo próprio

Pedido, como o professor faria na hora: "Peça confirmação antes de
remover um Evento. JavaScript em arquivo próprio, nada de JS na
view."

Chance: média. Tempo estimado: 10-15 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, R4.

Branch no clone: `fvp/r4-confirmacao-js-arquivo`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                 |
|-----------|------------------------------------------|
| `13d0765` | `feat: Confirm before removing an event` |

## O que mudou

- `public/assets/js/events.js` (novo): no `DOMContentLoaded`, liga um
  `submit` em todo `form[data-confirm]`; se `window.confirm(...)` com
  o texto do atributo voltar falso, `event.preventDefault()`.
- `app/views/layouts/application.phtml`: carrega
  `<script src="/assets/js/events.js"></script>` logo depois do
  `application.js`.
- `app/views/admin/events/index.phtml`: o form de remoção ganha
  `data-confirm="Remover o evento &quot;<título>&quot;?"`, com o
  título passado por `htmlspecialchars`. Nenhum `onsubmit`/`onclick`.
- Testes:
  - `DeleteEventCest` (Acceptance): o helper `clickRemove` confere o
    texto do diálogo (`seeInPopup`) e aceita (`acceptPopup`); os dois
    cenários de antes continuam iguais. Cenário novo
    `cancelingConfirmationKeepsEvent`: `cancelPopup`, o Evento 02
    continua na tabela e no banco.
  - `EventsControllerTest` (Integration):
    `test_index_remove_form_asks_confirmation_without_inline_js`
    (atributo com título escapado, inclusive aspas e `<b>`; sem
    `onsubmit`/`onclick`; layout carrega o `events.js`).

## Por que assim

- O professor penaliza JS inline (`onsubmit="return confirm(...)"`);
  o comportamento fica em arquivo próprio e a view só declara o
  texto em `data-confirm`.
- Arquivo separado em vez de bloco no `application.js`: este retorna
  cedo quando não há `image_preview_input`, e código posto depois do
  `return` nunca rodaria.
- Seletor genérico `form[data-confirm]`: serve para qualquer outro
  form destrutivo sem JS novo.
- Título no texto ajuda a não remover o Evento errado;
  `htmlspecialchars` (aspas viram `&quot;`) impede que um título com
  `"` quebre o atributo.
- Sem JS, o form continua funcionando (remove sem perguntar): a
  regra de negócio (P03, Evento com Inscrições) segue no model.

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

Resultado obtido: Unit 159, Integration 46, Acceptance 24 (base 159 /
45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

O arquivo é servido pelo `web`:

```
docker compose exec web curl -s -o /dev/null \
    -w '%{http_code} %{content_type}\n' \
    http://localhost/assets/js/events.js
# 200 application/javascript
```

No navegador: logue como `admin@example.com` / `123456`, abra
`/admin/events` e clique em "Remover" em um Evento sem inscrições.
Aparece "Remover o evento "<título>"?". Cancelar não faz nada; OK
remove e mostra "Evento removido com sucesso!". Use `Ctrl+F5` se o
navegador estiver com o layout antigo em cache.

## Relação com outros cenários

- O1 e P8 acrescentam botões no topo do mesmo `index.phtml`, fora do
  form de remoção.
- O4 (`o4-resposta-json`) não tem relação de código; é o outro
  cenário da 4.2 que prepara a 4.4 (Ajax).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/r4-confirmacao-js-arquivo
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/r4-confirmacao-js-arquivo`. Cherry-pick cria
commit novo (SHA diferente); preserva conteúdo e mensagem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-r4 <caminho>/projeto
git fetch fvp-r4 fvp/r4-confirmacao-js-arquivo
git cherry-pick 13d0765
git remote remove fvp-r4
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle \
    fvp/r4-confirmacao-js-arquivo:fvp-r4
git cherry-pick 13d0765
# opcional, limpa a ref
git branch -D fvp-r4
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
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

- `index.phtml`, linha do `<form>` de remoção: outro cenário que
  mexe nas ações da linha (Q1, só quem criou remove). Fique com a
  condição do outro cenário e acrescente o `data-confirm` no form.
- `application.phtml`: outro `<script>`/`<link>` no `<head>`. Fique
  com os dois.
- `DeleteEventCest.php`: se outro cenário mudou a remoção, todo
  clique em "Remover" precisa de `acceptPopup()` depois.

Depois de aplicar: rode `./run phpcs` e `./run test:browser` de novo.

## Armadilhas

- `onsubmit`/`onclick` inline na view: é o que o professor penaliza.
- Cest sem `acceptPopup()`: o Selenium fica com o diálogo aberto e o
  passo seguinte falha (`unexpected alert open`). Todo `click` em
  "Remover" passa a precisar dele (ou de `cancelPopup()`).
- `seeInPopup` antes de `acceptPopup`: depois de aceitar o diálogo
  some.
- Código novo dentro do `application.js` depois do `return` do
  `image_preview_input` nunca roda.
- Esquecer o `<script>` no layout: tudo passa nos testes de
  Integration que olham só o atributo, mas o Cest falha (não há
  diálogo para aceitar). Por isso o teste de controller também
  confere a tag.
- Título com aspas sem `htmlspecialchars` fecha o atributo antes da
  hora e quebra o HTML.
