# P8. Exportar a listagem em CSV

Pedido, como o professor faria na hora: "Coloque na listagem de
Eventos um botão que baixa todos os Eventos em CSV
(`GET /admin/events/export`)."

Chance: média. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P8.

Branch no clone: `fvp/p8-exportar-csv`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                              |
|-----------|---------------------------------------|
| `e8642bd` | `feat: Export events listing as CSV`  |

## O que mudou

- `config/routes.php`: `Route::get('/admin/events/export', ...)` com
  nome `admin.events.export`, depois de `/admin/events/new` e ANTES
  de `/admin/events/{id}`. O comentário do bloco cita `new` e
  `export`.
- `app/Controllers/Admin/EventsController.php`: ação `export()`.
  - `header('Content-Type: text/csv; charset=UTF-8')` e
    `header('Content-Disposition: attachment;
    filename="eventos.csv"')`.
  - `fopen('php://output', 'w')`, BOM UTF-8, linha de cabeçalho
    (Título, Tipo, Modalidade, Data, Local, Inscrições, Capacidade)
    e uma linha por Evento com `fputcsv`. Tipo e modalidade pelo
    rótulo (`Event::KINDS`, `Event::MODALITIES`), data `d/m/Y`.
  - Não chama `render()` nem `exit`: a resposta é só o arquivo.
- `app/views/admin/events/index.phtml`: botão "Exportar CSV" ao lado
  de "Novo Evento".
- Testes:
  - `EventsControllerTest` (Integration):
    - `test_export_returns_csv_with_header_and_events` (BOM,
      cabeçalho, linha completa de um Evento com vírgula no título e
      1 Inscrição, segundo Evento, sem `<html`);
    - `test_index_links_to_csv_export`.
  - `EventsAccessTest` (Integration): `admin.events.export` em
    `eventRoutes()` (sem sessão vai para `/login`, Participante vai
    para `/dashboard`) e `test_admin_should_download_events_as_csv`
    (admin logado: 200, `Content-Type` `text/csv`,
    `Content-Disposition` e o título do Evento no corpo).
  - `ExportEventsCest` (Acceptance, novo): o link "Exportar CSV"
    aparece na listagem e aponta para `/admin/events/export`.

## Por que assim

- O `render()` do `Controller` sempre envolve a view no layout
  HTML; o `renderJson` é só para JSON. Para CSV a ação escreve direto
  na saída, como o `renderJson` faz (`header` + `echo`).
- `fputcsv` cuida de aspas e vírgulas (o local do populate tem
  vírgula: "Bloco D, Auditório Central"). Montar com `implode(',')`
  quebraria as colunas.
- `php://output` passa pelo buffer de saída, então o
  `ControllerTestCase` (com `ob_start`) captura o CSV no teste.
- Sem `exit` no fim: o teste de controller precisa que a ação
  retorne; em produção o script termina sozinho depois da ação.
- Controller fino: os dados vêm de `Event::all()` e
  `enrollmentsCount()`, sem SQL novo.
- Diferenças do catálogo: lá o arquivo é `events.csv` e as colunas
  são os valores crus (`id`, `kind` = `workshop`, data ISO). Aqui
  `eventos.csv`, colunas em pt-BR com os rótulos da tela e BOM. As
  duas formas valem; os testes conferem a forma escolhida.

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

Resultado obtido: Unit 159, Integration 50, Acceptance 24 (base 159 /
45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Na tela: logue como `admin@example.com` / `123456`, abra
`/admin/events` e clique em "Exportar CSV"; o navegador baixa
`eventos.csv`. Sem porta no host:

```
docker compose exec web curl -s -c /tmp/c -d \
    'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -D - -b /tmp/c \
    http://localhost/admin/events/export
```

Saída obtida (início, populate):

```
Content-Type: text/csv; charset=UTF-8
Content-Disposition: attachment; filename="eventos.csv"

Título,Tipo,Modalidade,Data,Local,Inscrições,Capacidade
"Semana Acadêmica de Informática 2026","Semana acadêmica",
  Presencial,19/11/2026,"Bloco D, Auditório Central",3,3
```

(A segunda linha do CSV foi quebrada aqui só para caber.)

## Relação com outros cenários

- O1 (`o1-rota-eventos-lotados`) cria outra rota fixa
  (`/admin/events/full`) no mesmo ponto de `config/routes.php` e
  outro botão no topo de `index.phtml`.
- S2 (`s2-teste-acesso-rota-nova`): mesma receita de teste de acesso
  usada aqui (rota em `eventRoutes()` + admin com 200).
- O4 (`o4-resposta-json`): outra resposta sem layout; lá via
  `renderJson`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/p8-exportar-csv
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é `s50/fvp/4.2/exemplos/p8-exportar-csv`.
Cherry-pick cria commit novo (SHA diferente); preserva conteúdo e
mensagem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p8 <caminho>/projeto
git fetch fvp-p8 fvp/p8-exportar-csv
git cherry-pick e8642bd
git remote remove fvp-p8
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/p8-exportar-csv:fvp-p8
git cherry-pick e8642bd
# opcional, limpa a ref
git branch -D fvp-p8
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

- `config/routes.php`: outra rota fixa (O1) no mesmo ponto. Fique com
  as duas linhas, ambas antes de `{id}`.
- `index.phtml`: outro botão no topo (O1) ou filtro/busca (M3, M4).
  Fique com os dois.
- `EventsController.php`, `EventsControllerTest.php`,
  `EventsAccessTest.php` (`eventRoutes()`): métodos e rotas novos no
  mesmo ponto; mantenha os dois blocos.

Depois de aplicar: rode `./run phpcs` e os testes de novo.

## Armadilhas

- Chamar `render()`: o CSV sai dentro do layout HTML.
- `exit` no fim da ação: mata o PHPUnit no teste de controller.
- Rota depois de `{id}`: `export` vira id e a resposta é 302 com
  "Evento não encontrado!". O teste de controller não pega (não passa
  pelo Router); o teste de acesso do admin pega.
- Qualquer `echo` antes do `header()` dá "headers already sent" no
  navegador (no teste CLI não aparece).
- Excel em pt-BR espera `;` como separador: `fputcsv($out, $row,
  ';')` se o professor abrir no Excel. O BOM só resolve os acentos.
- No PHP 8.4, `fputcsv` sem o parâmetro `escape` explícito gera
  aviso de depreciação; aqui o PHP é 8.3.
- O teste confere o CSV com `str_getcsv` linha a linha; não compare a
  string inteira (aspas dependem do conteúdo).
- Títulos que começam com `=`, `+`, `-` ou `@` viram fórmula no Excel
  (CSV injection); fora do escopo, mas vale citar se perguntarem.
