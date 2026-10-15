# D7. Ordenar a listagem

Pedido, como o professor faria na hora: "A lista de usuários do admin
está na ordem de cadastro. Ordene por nome."

Chance: baixa. Tempo estimado: 10 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção D7.

Este cenário é empilhado sobre o D1 (a listagem paginada
`/admin/users` precisa existir). Branch no clone:
`fvp/d7-ordenar-listagem`, com dois commits acima de `main`. `main`
continua igual ao repositório público (`86c81ca`).

| Commit | Mensagem | Origem |
|--------|----------|--------|
| `7c8d56c` | `feat: List users with pagination in admin area` | cenário D1 (mesmo commit, mesmo SHA) |
| `7584d77` | `feat: Order users listing by name` | este cenário |

## O que mudou (só o commit `7584d77`)

- `lib/Paginator.php`: parâmetro opcional `order_by` no construtor (o
  último, padrão `null`). Com ele, o `SELECT` ganha `ORDER BY <coluna>,
  id` antes do `LIMIT/OFFSET`; sem ele, nada muda. A coluna é conferida
  contra `id` + `$columns` do model (`InvalidArgumentException` se não
  estiver lá), porque nome de coluna não aceita bind e entra direto no
  SQL.
- `core/Database/ActiveRecord/Model.php`: `paginate()` aceita
  `?string $order_by = null` e repassa ao `Paginator`.
- `app/Controllers/Admin/UsersController.php`: `User::paginate(...,
  order_by: 'name')`.
- `tests/Unit/Lib/PaginatorTest.php` (novo): 4 testes com usuários
  criados fora de ordem (Zé, Ana, Bruno, Ana).
- `tests/Integration/Controllers/Admin/UsersControllerTest.php`: teste
  `test_index_should_order_users_by_name` (Zé criado antes de Ana; Ana
  aparece antes dos participantes na página 1, Zé vai para o fim da
  página 2). Os participantes do `setUp` passam a se chamar
  `Participante 01` ... `12`.
- `tests/Acceptance/admin/AdminUsersCest.php`: mesmo zero à esquerda
  nos nomes dos participantes do `_before`.

6 arquivos, +143 / -7 linhas.

Desvio do catálogo: o catálogo sugere `usort` no controller (rápido) ou
um método no model com SQL próprio (certo). Os dois não servem para uma
listagem paginada: `usort` ordena só os 10 registros da página, e um
método com SQL próprio perderia o `Paginator`. O `paginate()` do
framework não aceitava ordenação, então a menor mudança correta é o
parâmetro `order_by` no `Model::paginate` e no `Paginator`, que ordena
no banco antes de cortar a página. O `HasMany::paginate` não foi
alterado (não é usado no projeto).

## Por que assim

- Ordenação no banco, antes do `LIMIT/OFFSET`: a ordem vale para a
  listagem inteira, não só para a página.
- `id` como desempate: dois nomes iguais têm sempre a mesma ordem, e
  nenhum registro aparece em duas páginas.
- Lista branca de colunas: `ORDER BY` não aceita parâmetro preparado;
  sem a conferência, `order_by` seria porta para SQL injection se um
  dia viesse do `Request`.
- Parâmetro opcional com padrão `null`: nenhum chamador existente muda
  de comportamento.
- Controller fino: só diz qual coluna; o SQL fica no
  `Model`/`Paginator`.
- Por que mudar os nomes dos participantes nos testes: ordem por nome
  é alfabética, então "Participante 10" vem antes de "Participante 2"
  e os testes de paginação do D1 (que esperam 1 a 9 na página 1)
  quebrariam. Com `sprintf('Participante %02d', $i)` a ordem alfabética
  coincide com a de cadastro e as asserções do D1 ficam iguais.
- Testes em unit (Paginator/Model) e controller, nomes descritivos;
  PSR-12, PHPStan 6, comentários em pt-BR, identificadores e commit em
  inglês (`grading-path.md`).

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run composer dump-autoload --optimize --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 75 testes, Integration 15, Acceptance 13 (a base
do D1 é 71 / 14 / 13; o público é 71 / 9 / 9). Testes novos deste
commit:

- `tests/Unit/Lib/PaginatorTest.php`:
  `test_paginate_without_order_by_should_keep_the_insertion_order`,
  `test_paginate_with_order_by_name_should_sort_alphabetically_and_untie_by_id`,
  `test_paginate_with_order_by_name_should_sort_before_splitting_pages`,
  `test_paginate_with_unknown_order_by_column_should_throw_exception`.
- `tests/Integration/Controllers/Admin/UsersControllerTest.php`:
  `test_index_should_order_users_by_name` (confirmado vermelho sem o
  `order_by: 'name'` no controller).

No navegador: `http://localhost`, login `admin@example.com` /
`123456`, menu "Usuários". A coluna "Nome" está em ordem alfabética
(os ids ficam fora de ordem); a página 2 continua a sequência.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público. `<caminho>` abaixo é `s50/exemplos-fvp/d7-ordenar-listagem`
(relativo ao clone do s50).

Primeiro veja se o alvo já tem o D1 (`git log --oneline | grep "List
users"`). Se tem, pegue só `7584d77`. Se não tem, pegue os dois, na
ordem. Sem o D1, `7584d77` sozinho conflita (o `UsersController` não
existe).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-d7 <caminho>/projeto
git fetch fvp-d7 fvp/d7-ordenar-listagem

git cherry-pick 7584d77             # alvo já tem o D1
git cherry-pick 7c8d56c 7584d77     # alvo sem o D1: os dois, nesta ordem
git cherry-pick main..fvp-d7/fvp/d7-ordenar-listagem   # mesma coisa, por intervalo

git remote remove fvp-d7
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/d7-ordenar-listagem:fvp-d7
git cherry-pick 7584d77             # alvo já tem o D1
git cherry-pick main..fvp-d7        # alvo sem o D1: aplica os dois commits
git branch -D fvp-d7                # opcional, limpa a ref
```

Testado em clone limpo de `86c81ca`: `git cherry-pick 7c8d56c 7584d77`
aplica os dois sem conflito. O cherry-pick cria commits novos; o SHA no
seu repositório vai ser outro.

### 3. A partir do `solucao.patch` (`git am`)

O patch contém os dois commits (formato mbox). Em alvo sem o D1:

```
git am <caminho>/solucao.patch       # cria os dois commits, mesmas mensagens
```

Testado em clone limpo de `86c81ca`: aplica os dois. Em alvo que já tem
o D1, o primeiro patch falha ("already exists in index"). Pule-o:

```
git am <caminho>/solucao.patch
git am --skip                        # pula o D1, aplica o D7
```

Ou gere um patch só do D7 no clone `projeto/` com
`git format-patch -1 7584d77 --stdout > d7.patch` e aplique esse.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `tests/Integration/Controllers/Admin/UsersControllerTest.php`:
  testado sobre D2, D6 e E1, conflita nos três, porque todos
  acrescentam testes no fim da classe. Fique com os testes dos dois
  lados. Depois rode os testes: se o outro cenário confere a posição de
  um participante, os nomes com zero à esquerda mantêm a ordem antiga.
- `app/Controllers/Admin/UsersController.php`: se o alvo mudou a
  chamada `User::paginate(...)` (D4 com busca, E1 com JSON), acrescente
  só o argumento `order_by: 'name'`. Sobre o D4 o cherry-pick foi
  limpo, mas confira se a busca usa outro caminho (SQL próprio) e
  ponha `ORDER BY name` lá também.
- `lib/Paginator.php` e `core/Database/ActiveRecord/Model.php`: só
  conflitam se outro cenário mexer no `paginate` (por exemplo, para
  aceitar condições `LIKE`). Mantenha os dois parâmetros.

Depois de aplicar: não há mudança de schema; rode `./run phpcs`,
`./run phpstan` e os testes de novo.

## Armadilhas

- `usort` no controller parece funcionar com poucos usuários, mas ordena
  só a página atual: com 11 ou mais usuários a página 2 recomeça do "A".
- `ORDER BY` precisa vir antes do `LIMIT/OFFSET` no SQL; depois, o
  MySQL acusa erro de sintaxe.
- Não dá para usar `:order_by` no `ORDER BY` (o PDO coloca aspas e o
  MySQL ordena por uma constante, ou seja, não ordena). Por isso a lista
  branca e a interpolação.
- Ordem alfabética de "Participante 10" vem antes de "Participante 2":
  testes de paginação que contam com a ordem de cadastro quebram. Use
  zero à esquerda nos nomes de teste.
- A colação do MySQL 8 (`utf8mb4_0900_ai_ci`) ignora caixa e acento:
  "ana" e "Ana" empatam, "Élio" fica junto dos "E". O `id` desempata.
- `lib/` não está no `paths` do PHPStan; o tipo do parâmetro novo é
  conferido só pelo `Model::paginate` (que está em `core/`).
