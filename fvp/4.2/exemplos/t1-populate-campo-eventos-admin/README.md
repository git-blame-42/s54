# T1. Populate: campo novo, mais Eventos, segundo admin

Pedido, como o professor faria na hora: "Atualize o populate: crie
um segundo Administrador e Eventos suficientes para ver a paginação
com mais páginas (30 Eventos)."

Chance: alta (sempre junto de M1, M3, P1, P5). Tempo estimado:
5-10 min. Catálogo: `s50/fvp/4.2/catalogo.md`, T1.

Branch no clone: `fvp/t1-populate-campo-eventos-admin`, a partir de
`main` (`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                  |
|-----------|-----------------------------------------------------------|
| `40128f7` | `chore: Add second admin and more events to the populate` |

## O que mudou

- `database/Populate/populate.php`:
  - segundo Administrador, `Administradora Dois`
    (`admin2@example.com`, senha `123456`), criado logo depois do
    primeiro;
  - laço `for ($number = 17; $number <= 30; ...)` depois dos 16
    Eventos fixos: `Evento 17` a `Evento 30`, do segundo admin, com
    data `+ (80 + $number)` dias (+97 a +110), tipo e modalidade
    alternando pelas chaves de `KINDS` e `MODALITIES`, capacidade
    `10 + $number`;
  - mensagens: "admin@... e admin2@... (admin)" e
    "14 Eventos criados pela Administradora Dois".
- `tests/Integration/Database/PopulateTest.php` (novo): roda o
  populate num processo PHP separado, no banco de teste, e confere
  2 admins (admin2 consegue autenticar), 6 usuários, 30 Eventos
  (16 do admin, 14 do admin2), 6 páginas de 5, datas crescentes e
  futuras, 14 Inscrições, o primeiro Evento lotado e as mensagens.
- Nenhum Cest mudou: eles criam os próprios dados no `_before`
  (`BaseEventsCest`), sem depender do populate.

## Por que assim

- Os Eventos novos entram depois dos 16 fixos em `$eventModels`:
  as Inscrições usam índices desse array (`0 => [0, 1, 2]`, ...), e
  nada muda de posição.
- Datas depois do último fixo (+82): a ordem por `id` continua igual
  à ordem por data, como o contrato da listagem assume, e nenhum
  Evento fica no passado (a validação barraria o `save()`).
- `save()` com `throw` se inválido, igual aos fixos: um Evento
  inválido aborta o populate com o título na mensagem.
- Tipo e modalidade vêm de `array_keys(Event::KINDS)` e
  `MODALITIES`: se um valor novo entrar no ENUM (M3), o populate não
  precisa mudar.
- Segundo admin dono dos Eventos novos: deixa pronto o cenário "só
  quem criou edita e remove" (Q1) e a coluna "Criado por" (R2).
- O teste do populate é o que pega erro de populate antes de alguém
  rodar `./run db:populate`. Ele usa o banco de teste: o
  `phpunit.xml` define `DB_DATABASE=eventos_test` com `putenv`, e o
  processo filho herda.

### Campo novo (quando vier junto de M1)

Na base não há coluna nova, então este commit não mexe nisso. Com um
campo novo (ex.: `workload_hours`), acrescente a chave no
`new Event([...])` dos dois laços:

```php
// valor igual para todos
'workload_hours' => 4,
```

Para variar por Evento, ponha uma 9ª posição em **todas** as linhas
de `$events` e no `foreach ($events as [..., $capacity, $hours])`.
`Model::save()` grava todas as colunas de `$columns`: campo esquecido
vai como `NULL` e quebra o `NOT NULL`.

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

Saída do `./run db:populate`:

```
Usuários criados: admin@example.com e admin2@example.com (admin),
    user@example.com (participant), senha 123456
4 Participantes criados (user@, mariana@, carlos@, fernanda@), ...
16 Eventos criados pelo Administrador
14 Eventos criados pela Administradora Dois
14 Inscrições criadas em 6 Eventos
```

(a primeira linha sai numa linha só; aqui foi quebrada para caber.)

Resultado obtido: Unit 159, Integration 50, Acceptance 23 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros (o `database/` fica fora do
`phpcs`). Schema não mudou.

No navegador: login com `admin2@example.com`/`123456` entra; a
listagem tem 6 páginas e a última mostra "Mostrando 26 - 30 de 30".

## Relação com outros cenários

- M3 (`m3-enum-novo-seminar`): o laço novo usa `KINDS`, então o tipo
  novo aparece nos Eventos gerados sem mudar o populate.
- N3 (`n3-ordenar-por-data`): as datas dos Eventos novos crescem com
  o `id`; a ordem é a mesma com ou sem `ORDER BY`.
- R2 (`r2-nome-do-criador`): com dois admins, a coluna "Criado por"
  mostra nomes diferentes nas páginas 4 a 6.
- N6 (`n6-badge-lotado-contador`): o painel passa a mostrar 30
  Eventos.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/t1-populate-campo-eventos-admin`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/t1-populate-campo-eventos-admin`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-t1 \
    s50/fvp/4.2/exemplos/t1-populate-campo-eventos-admin/projeto
git fetch fvp-t1 fvp/t1-populate-campo-eventos-admin
git cherry-pick 40128f7
git remote remove fvp-t1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/t1-populate-campo-eventos-admin/solucao.bundle \
    fvp/t1-populate-campo-eventos-admin:fvp-t1
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 40128f7
# opcional, limpa a ref
git branch -D fvp-t1
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am \
    s50/fvp/4.2/exemplos/t1-populate-campo-eventos-admin/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/t1-populate-campo-eventos-admin/solucao.patch
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

- `database/Populate/populate.php`: quase todo cenário com campo
  novo mexe no `new Event([...])` do laço dos fixos. Fique com as
  duas mudanças e repita a chave nova no laço dos Eventos 17-30.
- Se outro cenário também criou um segundo admin, fique com um só
  (o `email` é único; dois `admin2@` abortam no `save()`).

Depois de aplicar: `./run db:populate`, os testes e o `./run phpcs`.

## Armadilhas

- `db:populate` apaga tudo (`migrate()` faz `DROP`): não existe
  "acrescentar só os novos".
- Inserir os Eventos novos no meio do array `$events`: os índices
  das Inscrições passam a apontar para outros Eventos (e o Evento
  lotado deixa de ser o primeiro).
- Data no passado: a validação (`notPastDate`) barra o `save()` e o
  populate aborta com `RuntimeException`. O `+14` é só convenção do
  populate (folga para os testes de navegador), não regra do model.
- Título repetido não é erro, mas confunde os testes manuais; use
  `sprintf('Evento %02d', ...)`.
- Campo novo esquecido em um dos laços: `NULL` no `INSERT`, erro de
  `NOT NULL`.
- `123456` é senha de demonstração; não invente outra só para o
  admin2.
