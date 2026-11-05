# M17. Campo sim/não por checkbox (`has_certificate`)

Pedido, como o professor faria na hora: "Marque se o Evento emite
certificado. É um checkbox no formulário; a listagem e o detalhe mostram
Sim ou Não. Cadastrar ou editar com a caixa desmarcada não pode dar erro."

Chance: média-alta. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, M17.

Branch no clone: `fvp/m17-checkbox-certificado`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                    |
|-----------|---------------------------------------------|
| `29f34b7` | `feat: Add certificate checkbox to events`  |

## O que mudou

- `database/schema.sql`: `has_certificate TINYINT(1) NOT NULL DEFAULT 0`
  depois de `capacity` em `events`.
- `app/Models/Event.php`:
  - `'has_certificate'` em `$columns` e `@property int|string|null`.
  - No fim de `validates()`: `normalizeHasCertificate()` e depois
    `Validations::inclusion('has_certificate', $this, ['0', '1'])`.
  - `normalizeHasCertificate()` (privado): nulo ou vazio vira `'0'`;
    int (o que o banco devolve) vira `'0'`/`'1'`. Outros valores
    (`'2'`, `'on'`, `'sim'`) ficam como estão para o `inclusion()`
    recusar com "não é uma opção válida!".
  - `hasCertificate(): bool`, que compara com cast:
    `(string) $this->has_certificate === '1'`.
- `app/Controllers/Admin/EventsController.php`: `'has_certificate'` em
  `FIELDS` (a constante foi quebrada em linhas para caber em 130 col).
- `app/views/admin/events/_form.phtml`: bloco `form-check` com um
  `<input type="hidden" name="event[has_certificate]" value="0">` **antes**
  do checkbox `id="event_has_certificate"` (`value="1"`, `checked` via
  `hasCertificate()`), `$invalid()` e `$feedback()`.
- `index.phtml`: coluna "Certificado" (Sim/Não); `show.phtml`: par
  `dt`/`dd` "Certificado" (`dd` com classe `certificate`).
- `database/Populate/populate.php`: `'has_certificate' => $kind ===
  'lecture' ? '0' : '1'` (palestras não emitem; os outros tipos emitem),
  sem mexer nas linhas posicionais do array de Eventos.
- Testes:
  - `EventTest` (6): aceita `'0'` e `'1'`; nulo e vazio são gravados
    como `0` (sem erro 500); `'2'`, `2`, `'on'`, `'sim'`, `'-1'`, `' 1'`
    são recusados; lido do banco volta `int 1` e o Evento continua
    válido e salvável; desmarcar na edição grava `0`;
    `hasCertificate()` com `'1'`, `1`, `'0'`, `0`, nulo, vazio, `2`.
  - `EventsControllerTest` (8): o hidden `value="0"` vem antes do
    checkbox; criar marcado grava `1` e o `show` mostra Sim; criar com
    `'0'` grava `0`; POST **sem** o campo grava `0` e redireciona;
    valor `'2'` marca o campo com o erro embaixo; o `edit` de um Evento
    com certificado abre marcado; update com `'0'` desmarca; a listagem
    mostra Sim/Não. `validParams()` ganhou `'has_certificate' => '0'`
    (é o que o hidden envia).
  - `BaseEventsCest`: Eventos pares com certificado (Evento 02 sim,
    Evento 01 não) e `fillEventForm` aceita `has_certificate`
    (`true` marca, `false` desmarca).
  - `CertificateCheckboxCest` (3): cadastrar marcado (Sim, grava `1`),
    cadastrar desmarcado (Não, grava `0`) e desmarcar ao editar o
    Evento 02 (o form abre marcado; depois grava `0`).

## Por que assim

- Checkbox desmarcado não vai no POST. O controller faz
  `$params[$field] ?? null`, então chega `null`; o `Model::save()` grava
  todas as colunas de `$columns` e o `INSERT` manda `NULL` para uma
  coluna `NOT NULL`: erro 500. O `DEFAULT 0` do schema não ajuda, porque
  a coluna é enviada (com `NULL`).
- Duas defesas, cada uma com seu papel:
  - o hidden `value=0` antes do checkbox faz o formulário sempre enviar
    o campo. Com nomes repetidos, o PHP fica com o **último** valor:
    marcado, o `1` do checkbox sobrescreve o `0`;
  - a normalização no model cobre quem não manda o campo (outro
    formulário, `curl`, teste) e o Evento lido do banco.
- O TINYINT volta como `int` (PDO no PHP 8.1+), mas o formulário envia
  string. O `inclusion()` do `lib/Validations.php` compara com `===`;
  sem normalizar, um Evento lido do banco (`1`) seria recusado contra
  `['0', '1']` ao salvar de novo. Por isso o model converte para string
  antes de validar, e a view usa `hasCertificate()` (cast) em vez de
  `$event->has_certificate === '1'`.
- A normalização fica no início da regra, dentro de `validates()`: é
  chamado por `isValid()` e por `save()`, então vale para criar,
  editar e para qualquer código que salve um Evento.
- Valores estranhos não viram `0` em silêncio: só nulo e vazio são
  tratados como desmarcado; o resto o `inclusion()` recusa.
- Controller continua fino: só a chave nova em `FIELDS`, sem `(int)` e
  sem `isset()` especial para o checkbox.

## Como verificar

```
./run up -d
# refaz o schema (o populate roda migrate()) e popula
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 165 testes, Integration 53, Acceptance 26 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros; `db:populate` sem erro.

Conferido também: com a chamada `normalizeHasCertificate()` comentada,
2 dos 6 testes novos do `EventTest` falham (nulo e Evento lido do
banco).

No navegador (admin@example.com / 123456): `/admin/events` tem a coluna
"Certificado" (palestras com Não, os outros com Sim). Edite um Evento,
desmarque "Emite certificado" e salve: o detalhe mostra Não.

## Relação com outros cenários

- M1 (`m1-campo-novo-workload-hours`): mesmo roteiro de campo novo
  (schema, `$columns`, `FIELDS`, form, views, populate, testes). Aqui
  o campo é booleano, que o M1 não cobre. Os dois mexem nas mesmas
  linhas; se aplicar os dois, fique com as duas chaves em cada lista.
- P18 (`p18-evento-privado-convite`): mesmo problema com `is_private`
  (checkbox desmarcado chega nulo); lá a normalização foi no `save()`
  e no construtor, sem hidden. Aqui é hidden + `validates()` com
  `inclusion(['0','1'])`.
- M4 (`m4-segunda-data-ends-on`): outro campo novo em `events`;
  conflito só de vizinhança no schema, model, `FIELDS` e form.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/m17-checkbox-certificado`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/m17-checkbox-certificado`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-m17 \
    s50/fvp/4.2/exemplos/m17-checkbox-certificado/projeto
git fetch fvp-m17 fvp/m17-checkbox-certificado
git cherry-pick 29f34b7
git remote remove fvp-m17
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/m17-checkbox-certificado/solucao.bundle \
    fvp/m17-checkbox-certificado:fvp-m17
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 29f34b7
# opcional, limpa a ref
git branch -D fvp-m17
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/m17-checkbox-certificado/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/m17-checkbox-certificado/solucao.patch
```

Depois de aplicar: `./run db:populate` (schema mudou), os testes e o
`./run phpcs`.

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

- `schema.sql`, `Event.php` (`@property`, `$columns`, fim de
  `validates()`), `FIELDS` do controller, `_form.phtml`, `index.phtml`,
  `show.phtml`: todo campo novo (M1, M4, P18) mexe nas mesmas linhas.
  Fique com as duas colunas/chaves.
- `FIELDS` aqui virou array em várias linhas: se o outro lado ainda tem
  a versão em uma linha, refaça a lista com todas as chaves.
- `populate.php`, `validParams()` do `EventsControllerTest`,
  `seedEvents`/`fillEventForm` do `BaseEventsCest`: mantenha as chaves
  dos dois cenários.

## Armadilhas

- Sem hidden e sem normalização: desmarcar a caixa dá erro 500
  (`Column 'has_certificate' cannot be null`). Os testes de unidade
  podem passar se sempre mandarem `'0'`/`'1'`; o erro só aparece no
  navegador ou num POST sem o campo.
- Hidden **depois** do checkbox: o `0` sobrescreve o `1` e a caixa
  nunca fica marcada ao salvar.
- `inclusion(['0','1'])` sem normalizar: criar funciona, mas um Evento
  lido do banco (`int 1`) fica inválido; qualquer código que faça
  `findById()` e `save()` passa a falhar.
- `checked` com `=== '1'` na view: depois de salvar, o `edit` lê `int 1`
  do banco e abre a caixa desmarcada; editar outro campo e salvar
  apaga o certificado sem ninguém perceber. Use `hasCertificate()`.
- Normalizar tudo que não é `'1'` para `'0'` esconde lixo (`'on'` de um
  checkbox sem `value`, `'sim'`): prefira deixar o `inclusion()`
  recusar.
- Esquecer `db:populate`: `Unknown column` só no navegador; os testes
  passam porque recriam o schema.
- `seedEvents` do `BaseEventsCest` não confere o `save()`: um valor
  inválido em `has_certificate` faz os Cests falharem sem mensagem clara.
