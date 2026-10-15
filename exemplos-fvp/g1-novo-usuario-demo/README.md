# G1. Novo usuário de demonstração ou outras credenciais

Pedido, como o professor faria na hora: "Acrescente ao populate uma
participante `maria@example.com` (Maria Participante) e um segundo
administrador `gestor@example.com` (Gestor), ambos com senha `123456`,
e mostre o login dos dois."

Chance: alta. Tempo estimado: 5 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção G1.

Branch no clone: `fvp/g1-novo-usuario-demo`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `54da0e0` | `chore: Add demo users to populate script` |

## O que mudou

- `database/Populate/populate.php`: dois blocos novos, copiados dos
  existentes: `$manager` (Gestor, `role => 'admin'`) logo depois do
  administrador e `$maria` (Maria Participante, sem `role`, o model
  assume `participant`) logo depois do usuário comum. A mensagem final
  agora lista os quatro usuários, um por linha, com o papel de cada um.
- `README.md` do projeto: na seção "Populate database", uma frase
  dizendo que o populate recria as tabelas e uma tabela com os quatro
  e-mails, nomes e papéis (senha `123456`). Antes nenhum arquivo do
  repositório listava as credenciais; a pasta `wiki/` do clone só tem
  `Home`, `Descrição-do-Projeto`, `Mantenedores` e `_Sidebar`, sem
  credenciais, então não foi alterada (a página da 4.1 com as
  credenciais fica na wiki do GitHub, fora deste repositório; se o
  professor pedir, atualize-a lá à mão com a mesma tabela).

## Por que assim

- Usuários criados com `new User([...])` e `save()`: passam pela
  validação do model e a senha vira `encrypted_password` com hash.
  `update([...])` não validaria nem faria o hash (armadilha da seção 4).
- `role` só aceita `admin` e `participant` (ENUM no `schema.sql`); os
  dois valores usados já existem, então o schema não muda.
- Senha com `password_confirmation`, igual aos blocos existentes; sem
  isso o `save()` falha na validação.
- Estilo PSR-12 mantido, embora `database/` esteja fora do `paths` do
  `phpstan.neon` e no `exclude-pattern` do `phpcs.xml`.
- Textos pt-BR (nomes, mensagem do script, README); identificadores em
  inglês (`$manager`, `$created`); commit em inglês `type: Title`
  (`grading-path.md`).

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:reset && ./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 71 testes, Integration 9, Acceptance 9 (eram
71 / 9 / 9 na base). Nenhum teste novo: o populate não roda nos
testes. `./run composer dump-autoload --optimize --strict-psr`,
`./run phpcs` e `./run phpstan` sem erros.

Saída do `./run db:populate`:

```
Usuários criados (senha 123456):
  admin@example.com (admin)
  gestor@example.com (admin)
  user@example.com (participant)
  maria@example.com (participant)
```

Conferência no banco (`./run db:console` e depois a consulta):

```
mysql> SELECT name, email, role FROM users;
+--------------------+--------------------+-------------+
| name               | email              | role        |
+--------------------+--------------------+-------------+
| Administrador      | admin@example.com  | admin       |
| Gestor             | gestor@example.com | admin       |
| Usuário            | user@example.com   | participant |
| Maria Participante | maria@example.com  | participant |
+--------------------+--------------------+-------------+
```

Rodar duas vezes não duplica: `populate.php` começa com
`Database::migrate()`, que recria as tabelas a partir do
`database/schema.sql`. O resultado acima foi obtido depois de rodar
`./run db:populate` duas vezes seguidas: continuam quatro linhas.

No navegador: `http://localhost`.

1. Abra `http://localhost/login`, entre com `maria@example.com` /
   `123456`. Mensagem "Login realizado com sucesso!" e painel em
   `/dashboard`.
2. Acesse `http://localhost/admin`: Maria é participante, então é
   redirecionada para `/dashboard`. Saia (logout).
3. Entre com `gestor@example.com` / `123456`. Acesse
   `http://localhost/admin`: a área administrativa abre (200).
4. Teste uma senha errada para qualquer um dos dois: volta ao login com
   "E-mail ou senha inválidos!".

Os passos 1 a 3 foram conferidos com `curl` de dentro da rede do
Docker (login 302 para `/dashboard`, `/dashboard` 200; Maria em
`/admin` recebe 302 para `/dashboard`; Gestor em `/admin` recebe 200).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `s50/exemplos-fvp/g1-novo-usuario-demo`
abaixo é o diretório deste cenário no s50.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-g1 s50/exemplos-fvp/g1-novo-usuario-demo/projeto
git fetch fvp-g1 fvp/g1-novo-usuario-demo
git cherry-pick 54da0e0
git remote remove fvp-g1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/g1-novo-usuario-demo/solucao.bundle fvp/g1-novo-usuario-demo:fvp-g1
git cherry-pick 54da0e0        # o bundle traz o objeto 54da0e0; o cherry-pick cria um commit novo no alvo
git branch -D fvp-g1           # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/g1-novo-usuario-demo/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/g1-novo-usuario-demo/solucao.patch   # só o diff, sem commit
```

Conferido: num clone limpo de `86c81ca`, o `git fetch` do bundle
seguido de `git cherry-pick` aplicou sem conflito, e o `git am` do
patch também.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `database/Populate/populate.php`: A7 (perfil `organizer`), B3
  (coluna `phone`), B5 (usuário inativo) e G2 (gerar vários usuários)
  também editam este arquivo, em geral nos mesmos blocos de usuário ou
  na linha do `echo` final. Resolva mantendo os blocos dos dois lados:
  os usuários novos deste cenário (`$manager`, `$maria`) e o que o
  outro cenário acrescentou (campo `phone`, usuário inativo, laço de
  usuários etc.). Se o outro lado acrescentou um campo a todos os
  usuários (por exemplo `phone` em B3), acrescente-o também aos dois
  blocos novos. Na mensagem final, fique com o laço deste cenário e
  inclua as variáveis novas do outro lado no array.
- `README.md` do projeto: só conflita se o outro lado também mexeu na
  seção "Populate database"; mantenha as duas adições.

Depois de aplicar: `./run db:populate` (recria as tabelas e os
usuários); rode os testes e o `./run phpcs` de novo.

## Armadilhas

- E-mail repetido no populate: o model valida unicidade e o `save()`
  falha em silêncio (retorna `false`); o usuário simplesmente não
  aparece. Confira com o `SELECT` acima.
- Esquecer `password_confirmation`: mesma falha silenciosa.
- `role` fora de `admin`/`participant` exige mudar o ENUM do
  `schema.sql` e rodar `./run db:reset` (isso já é o cenário A7).
- Não use `update([...])` para criar ou trocar senha: não valida nem
  faz hash.
- `./run db:populate` apaga tudo que foi criado à mão no navegador,
  porque recria as tabelas. Avise o professor antes de rodar na frente
  dele, se houver dados que ele queira ver.
- `LoginHelper::logout()` dos testes de aceitação clica em
  `fulano@example.com`; não renomeie os usuários de teste achando que
  são os do populate.
- A wiki do GitHub (página da 4.1) é outro repositório: atualizar o
  `README.md` do projeto não muda a wiki.
