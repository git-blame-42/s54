# T3. README e wiki da feature nova

Pedido, como o professor faria na hora: "Documente a rota nova no
README e na wiki do projeto: o que ela faz, quem acessa e como
testar."

Chance: média-baixa. Tempo estimado: 10 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, T3.

Cenário só de README: não há branch nem bundle. A receita usa como
feature de exemplo a página de Eventos lotados do O1
(`o1-rota-eventos-lotados`, `GET /admin/events/full`). Os nomes de
rota, de teste e os comandos abaixo foram copiados do código do O1 e
conferidos rodando os comandos num clone com o O1 aplicado.

## O que documentar e onde

São três lugares, e só os dois primeiros entram no commit do projeto:

1. `README.md` do projeto: rota nova (método, caminho, nome, quem
   acessa) e o comando que testa a feature.
2. `wiki/` dentro do repositório: espelho das páginas da wiki
   (`Home.md`, `_Sidebar.md`, `Descrição-do-Projeto.md`,
   `Mantenedores.md`). Atualize se a página da entrega estiver lá.
3. Wiki oficial (`<repo>.wiki.git`, outro repositório): página da
   entrega 4.2 com a seção "Alterações da FVP" e o link da PR.

## 1. README do projeto

O `README.md` da base só tem instalação, testes e linters. Acrescente
uma seção depois de "Run the linters", com as rotas copiadas de
`config/routes.php` (nome igual ao do `->name(...)`):

````markdown
### Funcionalidades

#### Eventos lotados (área administrativa)

Lista só os Eventos sem vagas. Acesso: usuário logado com perfil
admin (grupo `auth` + `admin`). Link "Eventos lotados" na listagem
de Eventos.

| Método | Caminho              | Nome                |
|--------|----------------------|---------------------|
| GET    | `/admin/events/full` | `admin.events.full` |

Ação: `Admin\EventsController::full`.

Testar:

```
$ ./run test --filter full tests/Integration
$ ./run test:browser tests/Acceptance/admin/events/ListFullEventsCest.php
```

Na tela: `./run db:populate`, login `admin@example.com` / `123456`,
abra `/admin/events` e clique em "Eventos lotados".
````

Se o professor pedir "todas as rotas", liste as do grupo
`admin.events.*` na ordem de `config/routes.php` (`index`,
`paginate`, `new`, `full`, `create`, `show`, `edit`, `update`,
`destroy`), sem inventar nenhuma.

## 2. Wiki: página da entrega

Na wiki da base, `Home.md` lista "4.2. CRUD _(em breve)_" e a página
da entrega ainda não existe. A rubrica cobra a wiki com a descrição
da entrega e o link da PR. Acrescente à página da entrega 4.2 (crie
`Entrega-4.2-CRUD.md` se não existir e troque o "_(em breve)_" do
`Home.md` e o item do `_Sidebar.md` por link para ela):

```markdown
## Alterações da FVP

**Pedido:** página na área administrativa com só os Eventos
lotados, em `GET /admin/events/full`, com link na listagem.

**Solução:** rota `admin.events.full` antes de `/admin/events/{id}`;
`Event::full()` filtra os Eventos com `availableSpots() === 0`;
ação `full()` no `Admin\EventsController` e view
`admin/events/full.phtml`, com testes Unit, Integration e Cest.

**PR:** #NUMERO_DA_PR (commit `abc1234`)
```

Troque `#NUMERO_DA_PR` e o SHA pelos de verdade depois de abrir a PR
(`git log --oneline -1` dá o SHA curto).

## 3. Commits e publicação

Commit de documentação separado do `feat:`, na mesma branch:

```
git add README.md wiki/
git commit -m "docs: Document the full events page"
```

A wiki oficial é outro repositório: o commit acima não a atualiza.

```
git clone https://github.com/ORG/REPO.wiki.git
cd REPO.wiki
# edite Home.md, _Sidebar.md e a página da entrega
git add -A
git commit -m "docs: Add FVP changes to the 4.2 page"
git push
```

(Ou edite pela interface web do GitHub, aba Wiki.) Nome de página com
acento vira URL com `%C3%A7` etc.; nos links internos use o nome do
arquivo sem `.md` (`[Entrega 4.2](Entrega-4.2-CRUD)`), como o
`Home.md` já faz com `Mantenedores`.

## Por que assim

- O README é o que o professor abre primeiro; a tabela com método,
  caminho e nome espelha `config/routes.php` e prova que a rota
  existe com aquele nome.
- O comando de teste no README é o mesmo que a CI roda, restrito à
  feature (`--filter`, caminho do Cest): quem lê consegue reproduzir.
- `docs:` separado do `feat:` deixa o histórico da PR legível e casa
  com o tipo "docs" do `.github/pull_request_template.md`.
- A seção "Alterações da FVP" na página da entrega responde ao que a
  rubrica cobra (descrição + link da PR) sem reescrever a página.

## Como verificar

Num clone com o O1 aplicado
(`s50/fvp/4.2/exemplos/o1-rota-eventos-lotados`):

```
# cada rota citada no README existe com esse nome
grep -n "admin.events.full" config/routes.php
# os comandos de teste do README rodam e passam
./run test --filter full tests/Integration
./run test:browser \
  tests/Acceptance/admin/events/ListFullEventsCest.php
```

Resultado obtido (clone do bundle do O1, branch
`fvp/o1-rota-eventos-lotados`): o `grep` acha a rota na linha 30; o
`--filter full` em `tests/Integration` roda 3 testes (os 2 da página
e `test_index_links_to_full_events`), `OK (3 tests, 7 assertions)`; o
Cest passa, `OK (1 test, 7 assertions)`. O mesmo `--filter full` em
`tests/Unit` pega 3 testes, um deles de outro assunto
(`test_full_event_can_be_edited_keeping_its_capacity`, da base):
`--filter` casa por trecho do nome, então prefira o caminho do
arquivo ou um trecho mais específico (`--filter test_full_`).

No GitHub: a aba "Preview" do editor (ou a página da branch) mostra a
tabela e os blocos de código do README; a wiki mostra a página nova
no `_Sidebar`.

## Relação com outros cenários

- O1 (`o1-rota-eventos-lotados`): a feature documentada aqui.
- S2 (`s2-teste-acesso-rota-nova`): o teste de acesso por HTTP da
  mesma rota; cite-o no "Testar" se ele estiver aplicado.
- T2 (`t2-phpcs-phpstan-verdes`): a outra metade do que o professor
  confere na PR (CI verde).
- Qualquer cenário com rota nova (O2, O4, P2, P8...): mesma receita,
  trocando método, caminho, nome e comando de teste.

## Como usar esta receita

1. Termine e teste a feature (commit `feat:`).
2. Copie de `config/routes.php` método, caminho e nome de cada rota
   nova; ache os testes novos (`git show --stat HEAD`).
3. Escreva a seção do README (item 1) e rode os comandos de teste que
   você colou: têm de passar.
4. Atualize `wiki/` se a página da entrega estiver lá; commit
   `docs: Document ...`.
5. Abra a PR, depois atualize a wiki oficial com o número da PR e o
   SHA (item 3).

## Armadilhas

- Documentação em pt-BR; identificadores, rotas e nomes de teste
  exatamente como no código (o `README.md` da base tem títulos em
  inglês, "To run"; mantenha os existentes e escreva os novos em
  pt-BR).
- Não invente endpoints: rota citada e não registrada é o primeiro
  defeito que aparece ao abrir o caminho no navegador.
- `./run test` aceita opções antes do caminho (`--filter full`); o
  `./run test:browser` aceita só o caminho do Cest.
- Wiki é outro repositório: editar `wiki/` no projeto não muda a wiki
  oficial, e vice-versa.
- Credenciais: só as do populate (`admin@example.com` / `123456`), já
  públicas. Nada de `.env`, senha de banco real ou token.
- Link da PR antes de a PR existir: deixe para depois de abri-la, ou
  o link aponta para nada.
