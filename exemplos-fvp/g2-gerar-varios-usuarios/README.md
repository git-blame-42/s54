# G2. Gerar vários usuários (para ver paginação)

Pedido, como o professor faria na hora: "Gerem uns 15 usuários no
populate para a gente ver a paginação funcionando."

Chance: média. Tempo estimado: 5 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção G2 (e D1 para a listagem
paginada).

Branch no clone: `fvp/g2-gerar-varios-usuarios`. `main` continua igual
ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `7982012` | `chore: Generate sample participants in populate script` |

## O que mudou

- `database/Populate/populate.php`: depois do `Administrador` e do
  `Usuário`, um laço `for` cria `Participante 1` a `Participante 15`
  (`participante{$i}@example.com`, senha `123456`, `role` padrão
  `participant`), num bloco com comentário em pt-BR explicando que serve
  para demonstrar a paginação (10 por página, 17 usuários, 2 páginas).
  No fim, o script conta os usuários com `count(User::all())` e imprime
  as credenciais extras e o total.

Resultado: 1 arquivo, +16 / -0 linhas. A mensagem original do script
foi mantida; vieram duas linhas novas depois dela.

Saída de `./run db:populate`:

```
Usuários criados: admin@example.com (admin) e user@example.com (participant), senha 123456
Participantes extras: participante1@example.com a participante15@example.com, senha 123456
Total de usuários no banco: 17
```

## Por que assim

- O laço usa `new User([...])->save()`, o mesmo caminho do formulário:
  passa por `User::validates()` (nome, e-mail único, senha +
  `password_confirmation`) e grava o hash da senha. Inserir direto com
  SQL pularia a validação e o hash, e o login dos participantes não
  funcionaria.
- E-mail com o índice (`participante{$i}`) porque `users.email` é único;
  repetir o e-mail faria o `save()` devolver `false` em silêncio.
- O total vem do banco (`User::all()`), não de um contador local: se
  algum `save()` falhar na validação, o número impresso mostra.
- `populate.php` fica fora do PHPCS
  (`<exclude-pattern>/database/</exclude-pattern>`
  em `phpcs.xml`) e do PHPStan (`paths` em `phpstan.neon` só tem `app`,
  `core`, `tests`). Mesmo assim o código segue o estilo do arquivo
  (array sem vírgula final, como os dois usuários acima) e passa em
  `phpcs --standard=PSR12 database/Populate/populate.php` rodado à mão.
- Textos (comentário, saída do script, nomes dos participantes) em
  pt-BR; identificadores (`$participantsCount`, `$totalUsers`) em inglês;
  commit em inglês no formato `type: Title` (`grading-path.md`).
- `chore` e não `feat`: muda só dados de desenvolvimento, nada que o
  usuário final veja.

### Relação com D1 (a listagem paginada)

Este cenário só gera dados. A tela paginada é o cenário D1:
`GET /admin/users` (e `/admin/users/page/{page}`) em
`App\Controllers\Admin\UsersController::index`, que chama
`User::paginate(page: ..., per_page: 10, route: 'admin.users.paginate')`.
`Model::paginate` (`core/Database/ActiveRecord/Model.php`) monta um
`Lib\Paginator` (`lib/Paginator.php`), que faz `COUNT(*)` para o total e
`LIMIT/OFFSET` para a página, e desenha a navegação com
`renderPagesNavigation()`.

Só com os 2 usuários da `main`, a listagem do D1 cabe numa página e a
navegação não tem o que mostrar. Com D1 + G2 há 17 usuários: a página 1
mostra 10 e a página 2 mostra 7. Conferido no container chamando o
`Paginator` direto (sem D1 aplicado):

```
pagina 1: 10 de 2 paginas
pagina 2: 7 de 2 paginas
```

Neste clone a `main` não tem D1, então não há tela de usuários para
abrir. Para ver a página 2 no navegador, aplique D1 e G2 (ordem
indiferente; os dois não mexem nos mesmos arquivos). Testado: os
patches de D1 e G2, aplicados em sequência com `git am` sobre
`86c81ca`, entram sem conflito.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:reset && ./run db:populate   # o populate é o que mudou
./run db:console     # e: SELECT COUNT(*) FROM users;
./run composer dump-autoload --optimize --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Contagem no banco depois do populate (`docker compose exec db mysql ...`,
mesmo SQL do `./run db:console`):

```
+----------+
| COUNT(*) |
+----------+
|       17 |
+----------+
+-------------+----------+
| role        | COUNT(*) |
+-------------+----------+
| admin       |        1 |
| participant |       16 |
+-------------+----------+
```

Resultado obtido: Unit 71 testes, Integration 9, Acceptance 9 (eram
71 / 9 / 9 na base). `phpcs` e `phpstan` sem erros; `dump-autoload
--strict-psr` sem avisos. Testes novos: nenhum (mudança só no populate,
que os testes não usam; eles criam os próprios dados no banco de teste).

No navegador: `http://localhost/login`, entre com
`participante7@example.com` / `123456`; deve cair no painel do
participante. Com D1 aplicado: entre como `admin@example.com`, abra
`/admin/users`, veja 10 linhas e o link para a página 2, que mostra as
7 restantes (`Participante 9` a `Participante 15`).

Neste clone o navegador não foi aberto (o `docker-compose.override.yml`
local tira as portas do host). A senha dos participantes foi conferida
no container:
`User::findByEmail('participante7@example.com')->authenticate('123456')`
devolve `true`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/g2-gerar-varios-usuarios` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-g2 <caminho>/projeto
git fetch fvp-g2 fvp/g2-gerar-varios-usuarios
git cherry-pick 7982012
git remote remove fvp-g2
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/g2-gerar-varios-usuarios:fvp-g2
git cherry-pick 7982012        # o bundle traz o objeto 7982012
git branch -D fvp-g2           # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O
cherry-pick cria um commit novo (no teste, `b0a0ade`), com a mesma
mensagem e o mesmo diff.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

Testado: `git am` aplica limpo sobre `86c81ca`.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê: só
`database/Populate/populate.php`. Testado aplicando G2 por cima de cada
cenário vizinho:

- A7 (perfil `organizer`), B5 (usuário inativo) e G1 (usuário de
  demonstração): CONFLITO. Os três acrescentam usuário logo depois de
  `$user->save();` e reescrevem o `echo` final, exatamente onde G2 entra.
  Resolva mantendo os dois lados: os usuários do outro cenário, depois o
  bloco do laço do G2, depois o `echo` do outro cenário seguido das
  linhas `Participantes extras` e `Total de usuários`. O total passa a
  ser 18 (A7, B5) ou 19 (G1); ajuste o comentário "17 usuários" se
  quiser o número exato. A paginação continua com 2 páginas.
- B3 (coluna `phone`): aplica limpo. Os participantes ficam com
  `phone` NULL (a coluna aceita NULL); se quiser telefone neles, inclua
  `'phone' => ...` no array do laço.

Depois de aplicar: não há mudança de schema, mas rode
`./run db:populate` (ele recria as tabelas com `Database::migrate()`)
para os 15 participantes aparecerem; rode os testes e o `./run phpcs`
de novo.

## Armadilhas

- Esquecer de rodar `./run db:populate` depois de mudar o script: o
  banco continua com 2 usuários e "a paginação não funciona".
- E-mail fixo dentro do laço: a validação de e-mail único faz o
  `save()` devolver `false` sem erro; só 1 participante é criado. Por
  isso o script imprime o total lido do banco.
- Esquecer `password_confirmation`: a validação falha e nenhum
  participante é gravado (de novo, em silêncio).
- Inserir via SQL cru (`INSERT INTO users ...`) pula o hash da senha;
  o login dos participantes não funciona.
- 15 é o mínimo confortável para `per_page: 10`; com menos de 9 extras
  o total fica em até 10 e não aparece página 2.
- Se o professor pedir outro tamanho de página, mude o `per_page` do
  D1, não o populate; e confira que o total ainda passa de uma página.
- PHPCS e PHPStan não olham `database/`: um erro de sintaxe no populate
  só aparece ao rodar `./run db:populate`. Rode sempre.
