# R7. Voltar à mesma página da listagem depois de remover ou editar

Pedido, como o professor faria na hora: "Removi um Evento da página 3
e o sistema me jogou na página 1. Depois de remover, volte para a
página onde eu estava. E o Cancelar da edição deve voltar ao detalhe
do Evento, não à listagem."

Chance: média-baixa. Tempo estimado: 10-15 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, R7.

Branch no clone: `fvp/r7-voltar-mesma-pagina`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

Commit:

- `d80b3c8` `feat: Return to the same listing page after removing an
  event`

## O que mudou

- `app/views/admin/events/index.phtml`: o form de remoção de cada
  linha ganha
  `<input type="hidden" name="page" value="<?= $paginator->getPage() ?>">`.
- `app/Controllers/Admin/EventsController.php`: `destroy` redireciona
  para `indexUrl($request->getParam('page'))` (método privado novo):
  - `page` ausente, não numérico (`abc`, `-2`, `2.5`, array), `0` ou
    `1` → `/admin/events`;
  - senão → `/admin/events/page/{page}`, limitado à última página que
    ainda existe (removeu o último Evento da página 3 → página 2;
    `page=99` → última página).
  - Vale também quando a remoção é barrada (Evento com Inscrições):
    a mensagem aparece na mesma página.
- Cancelar do formulário: `_form.phtml` usa `$cancelUrl`;
  `new.phtml` define a listagem e `edit.phtml` o `show` do Evento
  (também ao reabrir o form depois de um `update` inválido).
- Testes:
  - `EventsControllerTest` (8 novos): o form manda a página atual;
    `destroy` com `page=2` volta para a página 2; `page` ausente,
    inválido ou `1` volta para `/admin/events`; último Evento da
    página 3 → página 2; `page=99` → última; remoção barrada também
    volta para a página; Cancelar aponta para o `show` na edição (e
    depois de `update` inválido) e para a listagem no cadastro.
  - `DeleteEventCest::deleteEventKeepsTheCurrentPage`: remove o
    Evento 07 na página 2 e continua em `/admin/events/page/2`
    ("Mostrando 6 - 10 de 15").
  - `UpdateSuccessfullyCest::cancelEditGoesBackToShow`: Cancelar na
    edição abre o `show` sem salvar.

## Por que assim

- `page` explícito no form em vez de `redirectBack()`: o
  `HTTP_REFERER` pode ser omitido pelo navegador (`Referrer-Policy`)
  e não existe nos testes de controller e no `curl`, onde o
  `redirectBack()` cai em `/`.
- O valor vem do usuário: só `ctype_digit` (string só com dígitos)
  maior que 1 é aceito; o resto volta à primeira página. Sem `(int)`
  direto em qualquer coisa ("conversão silenciosa").
- Página que deixou de existir: a contagem vem do próprio `Paginator`
  (`Event::paginate(1, PER_PAGE)->totalOfPages()`), sem SQL novo. Sem
  isso, quem removesse o último Evento da última página cairia numa
  página vazia, sem navegação.
- Depois de editar com sucesso, o `update` já volta para o `show`
  (#24); o que faltava era o Cancelar, que ia para a listagem.

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

Resultado obtido: Unit 159 testes, Integration 53, Acceptance 25 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema e populate não
mudaram.

No navegador (admin@example.com / 123456): em `/admin/events/page/2`,
"Remover" num Evento sem Inscrições (ex.: o 8º) mostra "Evento removido
com sucesso!" e continua na página 2. Na edição de um Evento,
"Cancelar" abre o detalhe dele.

## Relação com outros cenários

- N10 (página fora do intervalo): aqui o `destroy` já limita a página
  à última existente; com N10, uma URL manual fora do intervalo também
  é tratada na listagem.
- N1, N2, N3 (filtro, busca, ordenação): para voltar à mesma página
  *com o filtro*, mande também os parâmetros deles como campos ocultos
  e repasse no `route('admin.events.paginate', [...])`.
- P11 (`p11-soft-delete-arquivar`): o `destroy` vira arquivar; o
  retorno à mesma página vale igual, contando só os ativos.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/r7-voltar-mesma-pagina`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/r7-voltar-mesma-pagina`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-r7 \
    s50/fvp/4.2/exemplos/r7-voltar-mesma-pagina/projeto
git fetch fvp-r7 fvp/r7-voltar-mesma-pagina
git cherry-pick d80b3c8
git remote remove fvp-r7
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/r7-voltar-mesma-pagina/solucao.bundle \
    fvp/r7-voltar-mesma-pagina:fvp-r7
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick d80b3c8
# opcional, limpa a ref
git branch -D fvp-r7
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/r7-voltar-mesma-pagina/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/r7-voltar-mesma-pagina/solucao.patch
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

- `EventsController::destroy`: P11, Q1, Q2 e R1 mexem no mesmo
  método. Mantenha a lógica deles e troque só o redirect final por
  `$this->indexUrl($request->getParam('page'))`.
- `index.phtml` (form de remoção): R4 (confirmação) e P11 (Arquivar)
  mexem nas mesmas linhas; fique com o campo oculto `page` e com o
  que o outro cenário acrescentou.
- `_form.phtml`, `new.phtml`, `edit.phtml`: quem incluir o
  `_form.phtml` em outra view precisa definir `$cancelUrl`.
- `EventsControllerTest`: testes que comparam `Location` exato do
  `destroy` (`assertSame`) continuam valendo sem `page`.

## Armadilhas

- `$cancelUrl` indefinido em alguma view que inclui o `_form.phtml`:
  warning vira 500 (`ErrorsHandler`).
- Esquecer o campo oculto: o `destroy` nunca recebe `page` e tudo
  continua voltando para a página 1 (o teste de controller passa,
  porque manda `page` direto; o Cest pega).
- Removeu o último Evento da última página: sem o limite pela
  contagem, a página fica vazia e sem navegação.
- `(int) $page` sem validar: `abc` vira 0 e `2.5` vira 2, aceitando
  qualquer coisa calada.
- `redirectBack()` parece mais simples, mas sem `Referer` cai em `/`.
