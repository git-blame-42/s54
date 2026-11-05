# T4. Itens por página e capacidade máxima no `.env`

Pedido, como o professor faria na hora: "O número de itens por página
não pode ficar fixo no código. A capacidade máxima de um Evento também
vem da configuração."

Chance: média-baixa. Tempo estimado: 15-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, T4: "`EVENTS_PER_PAGE` e `MAX_CAPACITY` no
`.env`, lidos de `$_ENV` num só lugar (`lib/Config.php`), com faixa e
valor padrão".

Branch no clone: `fvp/t4-env-per-page`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                     |
|-----------|--------------------------------------------------------------|
| `d89aa72` | `feat: Read events per page and max capacity from .env`      |

## O que mudou

- `.env.example`: `EVENTS_PER_PAGE=5` e `MAX_CAPACITY=1000`. O CI
  copia esse arquivo para `.env`; quem já tem `.env` acrescenta as
  duas linhas à mão (o `.env` não é versionado).
- `lib/Config.php` (classe nova): `Config::int(string $key,
  int $default, int $min, int $max): int`. Lê `$_ENV[$key]`; só
  aceita string com dígitos (`ctype_digit`) dentro de
  `[$min, $max]`. Chave ausente, vazia, `"abc"`, `"-5"`, `"2.5"`,
  `"0"` ou fora da faixa devolvem `$default`.
- `lib/Validations.php`: `integerMax($attribute, $obj, int $max)`,
  cópia de `integerMin` com `>` no lugar de `<`. Mensagem
  "deve ser menor ou igual a {$max}!".
- `app/Controllers/Admin/EventsController.php`, `index`:
  `per_page: Config::int('EVENTS_PER_PAGE', self::PER_PAGE, 1, 100)`.
  `PER_PAGE = 5` continua, agora como valor padrão.
- `app/Models/Event.php`: constante `MAX_CAPACITY = 1000` (padrão) e,
  em `validates()`, a capacidade passa a ser
  `notEmpty && integerMin(1) && integerMax(Config::int(
  'MAX_CAPACITY', self::MAX_CAPACITY, 1, 100000))`, antes da regra
  da base (capacidade >= Inscrições).
- Testes:
  - `tests/Unit/Lib/ConfigTest.php` (novo, 12): sem a chave devolve o
    padrão; dígitos na faixa (`"1"`, `"10"`, `"100"`, `"007"`);
    10 valores inválidos (data provider) caem no padrão.
  - `NumberAndDateValidationsTest` (3): `integerMax` aceita até o
    máximo, recusa acima com a mensagem, recusa não inteiros.
  - `EventTest` (4): sem a chave, 1001 é inválido e 1000 é válido;
    com `MAX_CAPACITY = '100'`, 101 é inválido; `'0'` no env cai no
    padrão 1000; `'2147483648'` vira erro de validação em vez de
    `PDOException` no `INSERT`. `tearDown` faz `unset`.
  - `EventsControllerTest` (5): com `EVENTS_PER_PAGE = '10'`,
    página 1 tem 01 a 10 e página 2 tem 11 e 12; `'0'`, `'abc'`,
    `'500'` e `''` voltam a 5 por página. `tearDown` faz `unset`.
    O teste antigo `test_index_lists_five_events_per_page` segue
    valendo como "sem a chave usa 5".

## Por que assim

- Uma leitura só, em `lib/Config.php`: controller e model não repetem
  a validação do valor, e o próximo número de configuração reaproveita
  o método. `lib/` é onde ficam os utilitários do framework
  (`Validations`, `Paginator`, `StringUtils`).
- `$_ENV`, nunca `getenv()`: o `EnvLoader` grava o `.env` em `$_ENV`
  e não faz `putenv`, então `getenv('EVENTS_PER_PAGE')` devolve
  `false` no servidor.
- O valor do `.env` é sempre string. `ctype_digit` antes do `(int)`
  impede conversão silenciosa (`(int) "abc"` é 0, `(int) "10abc"`
  é 10). Faixa obrigatória: `"0"` faria o `Paginator` dividir por
  zero; mil itens por página derrubaria a listagem.
- `integerMax` em `lib/Validations.php` (e não um `if` no model):
  segue o padrão do `integerMin`, a validação continua em
  `validates()` e o teste fica junto dos outros de número. Também
  resolve um defeito real: capacidade acima de 2147483647 passava no
  `integerMin` e estourava a coluna `INT` com erro 500.
- Faixa de `MAX_CAPACITY` até 100000: um valor absurdo no `.env` não
  libera capacidades que a coluna `INT` não guarda. Padrão 1000 cobre
  o populate (maior capacidade: 300) e os Cests (até 60).
- Valor inválido cai no padrão em silêncio, sem exceção: a página
  continua funcionando com uma configuração errada.

## Como verificar

```
./run composer dump-autoload -o --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 178, Integration 50, Acceptance 23
(base 159 / 45 / 23; nenhum Cest novo nem ajustado). PHPCS e PHPStan
sem erros; PSR-4 estrito ok. Schema não mudou.

Os testes PHPUnit não rodam o `EnvLoader`: `$_ENV` só tem o que está
no `<env>` do `phpunit.xml`. Por isso "sem a chave" é o padrão, e os
testes que gravam `$_ENV[...]` fazem `unset` no `tearDown`.

Na tela (populate): acrescente as duas chaves ao seu `.env`, logue como
`admin@example.com` / `123456` e abra `/admin/events`: 5 Eventos,
4 páginas. Troque para `EVENTS_PER_PAGE=3` no `.env` e recarregue,
sem reiniciar contêiner (o `.env` é lido a cada requisição): 3 por
página, 6 páginas. `EVENTS_PER_PAGE=0` volta a 5. Com
`MAX_CAPACITY=100`, salvar um Evento com 101 vagas mostra "deve ser
menor ou igual a 100!".

Conferido por `curl` de dentro da rede (populate, 16 Eventos):
`EVENTS_PER_PAGE=5` dá "Mostrando 1 - 5 de 16" e 4 páginas; `=3` dá
"Mostrando 1 - 3 de 16" e 6 páginas; `=0`, `=abc` e `=101` voltam a
"Mostrando 1 - 5 de 16".

## Relação com outros cenários

- N4 (`n4-per-page`): fixa 10 por página e deixa o usuário escolher.
  Juntos, o padrão vem de `Config::int('EVENTS_PER_PAGE', ...)` e a
  escolha `?per_page=` continua restrita à lista fechada do N4.
- M2 (`m2-validacao-maxlength`): traz a receita do `integerMax`; aqui
  ele foi criado de verdade. Se aplicar os dois, fique com um só
  `integerMax` em `lib/Validations.php`.
- S4 (`s4-testes-de-borda`): cita o `PDOException` de capacidade
  acima do `INT`; com este cenário vira erro de validação.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/t4-env-per-page
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é `s50/fvp/4.2/exemplos/t4-env-per-page`.
Cherry-pick cria commit novo (SHA diferente); preserva conteúdo e
mensagem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-t4 <caminho>/projeto
git fetch fvp-t4 fvp/t4-env-per-page
git cherry-pick d89aa72
git remote remove fvp-t4
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/t4-env-per-page:fvp-t4
git cherry-pick d89aa72
# opcional, limpa a ref
git branch -D fvp-t4
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

- `EventsController.php`, `index`: N1/N4/N3 mexem na chamada do
  `paginate()`. Mantenha os argumentos deles e troque só o
  `per_page:` pelo `Config::int(...)`.
- `Event.php`, `validates()`: outros cenários (M2, M5, M10) mexem na
  capacidade ou logo acima. Fique com a cadeia
  `notEmpty && integerMin && integerMax` e com as regras novas deles.
- `lib/Validations.php`: se o M2 já criou `integerMax`, mantenha um.
- `.env.example`: outra chave nova no fim; fique com as duas.
- `EventTest.php`/`EventsControllerTest.php`: testes novos no mesmo
  ponto e um segundo `tearDown`; junte num só.

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(classe nova), acrescente as chaves ao `.env`, `./run phpcs`,
`./run phpstan` e os testes.

## Armadilhas

- Valor do `.env` é string (`""` para chave vazia): `(int)` direto
  aceita `"abc"` como 0 e `"0"` divide por zero no `Paginator`.
  Valide com `ctype_digit` e faixa, e use o padrão.
- `getenv()` não enxerga o `.env` (o `EnvLoader` não faz `putenv`);
  leia `$_ENV`. Ao contrário, uma variável que já existe no processo
  (ex.: `environment:` do `php_test`) vence o `.env`.
- O `.env` do teste não é o seu: PHPUnit usa só o `<env>` do
  `phpunit.xml`; os Cests passam pelo `bootstrap` e leem o `.env` do
  projeto (no CI, a cópia do `.env.example`). Por isso o padrão no
  código precisa ser o mesmo valor do `.env.example`.
- Teste que grava `$_ENV[...]` sem `unset` no `tearDown` vaza para os
  testes seguintes (mesmo processo).
- Nunca commite o `.env`, só o `.env.example`. Quem já tem `.env`
  precisa acrescentar as chaves (sem elas, vale o padrão do código).
- Títulos `Evento 1`...`Evento 12` no teste: "Evento 1" casa com
  "Evento 10"; use `sprintf('Evento %02d', $i)`.
- Classe nova em `lib/`: `dump-autoload -o --strict-psr`, senão
  `Class "Lib\Config" not found`.
