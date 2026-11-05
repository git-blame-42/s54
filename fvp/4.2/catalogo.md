# FVP 4.2: possíveis modificações no CRUD de Eventos

Catálogo das alterações que o professor pode pedir na validação
prática (FVP) sobre o código da Entrega 4.2 (CRUD de Eventos). Para
cada cenário: pedido típico, chance, tempo, arquivos com linha, passos,
armadilhas e prova. Complementa o catálogo da 4.1 (`fvp/4.1/`), cujos
cenários de login, middleware e perfil continuam valendo.

Base das linhas: `main` do repositório público em `f31006b`
(`git show f31006b:<arquivo>` ou `grep -n` no clone). Se a `main`
avançar, refaça as linhas com `grep -n` antes de confiar nelas.

Lição da FVP da 4.1: o pedido real foi uma **feature nova pequena**
("login por código": o backend gera um número aleatório de 1000 a
9999 e o login só vale depois de validar esse código), não um
micro-ajuste. Rota + controller + model + view + teste em 15 a 40
minutos. A seção P deste catálogo é dedicada a pedidos desse tipo;
P12 descreve o pedido real da 4.1.

Siglas dos arquivos (todos relativos à raiz do projeto):

| Sigla | Arquivo |
|---|---|
| `ROUTES` | `config/routes.php` |
| `EC` | `app/Controllers/Admin/EventsController.php` |
| `EV` | `app/Models/Event.php` |
| `EN` | `app/Models/Enrollment.php` |
| `US` | `app/Models/User.php` |
| `VAL` | `lib/Validations.php` |
| `PAG` | `lib/Paginator.php` |
| `FLASH` | `lib/FlashMessage.php` |
| `MODEL` | `core/Database/ActiveRecord/Model.php` |
| `HASMANY` | `core/Database/ActiveRecord/HasMany.php` |
| `CTRL` | `core/Http/Controllers/Controller.php` |
| `SCH` | `database/schema.sql` |
| `POP` | `database/Populate/populate.php` |
| `FORM` | `app/views/admin/events/_form.phtml` |
| `IDX` | `app/views/admin/events/index.phtml` |
| `SHOW` | `app/views/admin/events/show.phtml` |
| `PAGES` | `app/views/paginator/_pages.phtml` |
| `NAV` | `app/views/layouts/_navbar.phtml` |
| `LAYOUT` | `app/views/layouts/application.phtml` |
| `ECT` | `tests/Integration/Controllers/Admin/EventsControllerTest.php` |
| `EVT` | `tests/Unit/Models/Events/EventTest.php` |
| `ACC` | `tests/Integration/Access/EventsAccessTest.php` |
| `ACT/` | `tests/Acceptance/admin/events/` |
| `CTC` | `tests/Integration/Controllers/ControllerTestCase.php` |

## 1. O que vale saber

- FVP: individual, no laboratório, sobre o código entregue. O fator
  (0.0 a 1.0) multiplica a nota da 4.2; 0.0 zera. Tempo esperado do
  pedido: 15 a 40 minutos. Detalhes em `fvp/4.1/catalogo.md`, seção 1.
- Funcionalidade funcionando no navegador vale mais que teste sem
  funcionalidade. Ordem: funcionar, testar, `phpcs`.
- Credenciais do populate: `admin@example.com` (admin) e
  `user@example.com`, `mariana@`, `carlos@`, `fernanda@example.com`
  (Participantes), senha `123456` (`POP:12-42`).
- Populate: 16 Eventos (4 páginas de 5), 14 Inscrições. O primeiro
  Evento está lotado (3/3). Eventos 1, 2, 3, 4, 6 e 9 têm Inscrições e
  não podem ser removidos (`POP:156-163`).
- Rubrica da 4.2: `save`, `all`, `findById`, `where`, `Validations`,
  `Paginator`, rotas RESTful (nomes, não só verbos), testes de
  aceitação dos 7 cenários, de acesso e unitários, bônus
  "Funcionalidades Extras no Framework".
- Issues de avaliação da 4.2 (03/11, grupos 3, 5 e 6): SQL só no
  model; `render` renderiza, `redirectTo` redireciona; controller
  enxuto com *early return*; nomes `index/new/create/edit/update/destroy`;
  validações de tamanho, formato e valores numéricos no model, sem
  conversão silenciosa (`(int)` que vira 0); autorização no servidor
  (`readonly` no form não protege); recurso pai deve pertencer ao
  usuário; manter valores digitados após erro; partial para os campos;
  CSS e JS em arquivos próprios; testes de borda; regra explícita para
  excluir pai com filhos.

## 2. Preparo da máquina (10 minutos)

```
git clone <repositorio publico> && cd <pasta>
cp .env.example .env
./run composer install
./run up -d --build
./run db:populate
./run codecept build
./run test tests/Unit
```

`--build` garante o fuso `America/Sao_Paulo` (`Dockerfile:10`); sem
ele os testes de data falham. `db:populate` roda `Database::migrate()`
(`POP:10`), que executa o `schema.sql` inteiro (`DROP` + `CREATE`):
sozinho ele já refaz o schema. Nos testes o schema é recriado a cada
teste (`tests/TestCase.php:10-14`).

Comandos: `./run test <caminho>`, `./run test:browser <caminho>`
(precisa do `selenium`, que o `up -d` sobe), `./run phpcs`
(`./run phpcbf` arruma), `./run phpstan`, `./run db:populate`.

## 3. Método de sempre

1. Repita o pedido como "quero mudar X" e ache o cenário:
   `./fvp.sh 4.2 <termo>` ou `./fvp.sh -s <ID>`.
2. Localize os arquivos (tabela do cenário) e abra o exemplo em
   `fvp/4.2/exemplos/<id>-*/` se existir.
3. Escreva ou ajuste o teste e veja falhar.
4. Mude na ordem schema -> model -> rota -> controller -> view.
5. `./run db:populate` se tocou no schema; teste; navegador; `phpcs`.
6. Commit em inglês (`feat: Add ...`) na branch indicada.

## 4. Resumo

| ID | Modificação | Chance | Tempo |
|---|---|---|---|
| M1 | Campo novo em `events` com validação | alta | 25-35 min |
| M2 | Validação nova em `lib/Validations.php` | alta | 15-20 min |
| M3 | Valor novo em ENUM/SET | média | 10-15 min |
| M4 | Data de término não anterior ao início | média-alta | 25-35 min |
| M5 | Validação condicional por modalidade | média | 15-25 min |
| M6 | Unicidade (título único na mesma data) | média-alta | 15-20 min |
| M7 | Horário do Evento (`TIME` ou `DATETIME`) | média-alta | 30-40 min |
| M8 | Enum PHP para tipo e modalidade | média-baixa | 25-35 min |
| M9 | Slug único gerado no backend | média | 25-35 min |
| M10 | Campo que não muda depois de haver inscrições | média | 15-25 min |
| M11 | Validar `user_id` no model | média | 15-20 min |
| M12 | Último editor gravado da sessão | média-baixa | 20-25 min |
| M13 | Montagem do Evento no model ou service | média-baixa | 10-20 min |
| M14 | Contador de visualizações com `update()` | média-baixa | 15-20 min |
| M15 | Escolher o responsável entre os admins | média-baixa | 20-30 min |
| M16 | Gancho `beforeValidation` no core (espaços) | baixa | 15-20 min |
| M17 | Campo sim/não por checkbox (certificado) | média-alta | 25-35 min |
| M18 | Janela de datas: antecedência e limite | média-alta | 15-25 min |
| M19 | "Nenhuma alteração" ao salvar sem mudar | média | 15-20 min |
| M20 | Preço em reais com vírgula | média | 25-35 min |
| M21 | Vagas ilimitadas (`capacity` NULL) | média-baixa | 20-30 min |
| M22 | No máximo N Eventos por dia | média-baixa | 20-25 min |
| M23 | Data digitada como dd/mm/aaaa | baixa | 20-25 min |
| N1 | Filtro por tipo com `where` na paginação | alta | 25-35 min |
| N2 | Busca por título (`LIKE`) | média | 20-30 min |
| N3 | Ordenar por data no `Paginator` | média-alta | 15-25 min |
| N4 | Mudar `per_page` ou deixar o usuário escolher | média-alta | 5-10 min |
| N5 | Só Eventos futuros ou passados | média | 20-30 min |
| N6 | Dado calculado na tela (badge "Lotado", total) | média | 10-15 min |
| N7 | Painel administrativo com resumo | média | 20-30 min |
| N8 | Ordenar clicando no cabeçalho da listagem | média | 25-35 min |
| N9 | Filtro por período | média | 30-40 min |
| N10 | Página e id inválidos, sem `(int)` cego | média-alta | 15-25 min |
| N11 | `Model::count()` no framework | média | 15-20 min |
| N12 | Paginação com Primeira/Última e janela | média | 10-20 min |
| N13 | Filtro fora do `where` (SET, vagas) | média-baixa | 25-35 min |
| N14 | Query Builder mínimo no core | baixa | 40-60 min |
| N15 | Dois paginadores na mesma tela | baixa | 25-35 min |
| O1 | Rota RESTful nova com página (lotados) | média-alta | 20-30 min |
| O2 | Eventos para o Participante (lista e detalhe) | média-alta | 25-35 min |
| O3 | Inscritos no `show` (`participants()`) | média | 20-30 min |
| O4 | Resposta JSON da listagem | baixa | 20 min |
| O5 | Admin vê e lista só os próprios Eventos | média-alta | 15-25 min |
| O6 | Inscritos em página própria, paginada | média-baixa | 25-35 min |
| O7 | `Route::patch` no core | média-baixa | 15-20 min |
| O8 | Status HTTP corretos (404, 422) | baixa | 15-20 min |
| O9 | `_method` só via POST (falha real) | média | 15-20 min |
| O10 | `Route::resource` ou `Route::prefix` no core | média-baixa | 25-40 min |
| O11 | Página 404 amigável, sem stack trace | baixa | 20-25 min |
| P1 | Código de check-in aleatório (1000-9999) | alta | 25-35 min |
| P2 | Inscrição do Participante em um Evento | média-alta | 30-40 min |
| P3 | Cancelar a própria inscrição | média | 15-20 min |
| P4 | Confirmar presença pelo código do Evento | média-baixa | 30-40 min |
| P5 | Status do Evento: cancelar e encerrar | alta | 25-35 min |
| P6 | Duplicar Evento | média-alta | 20-30 min |
| P7 | "Meus eventos" do Participante | média-alta | 20-30 min |
| P8 | Exportar a listagem em CSV | média | 20-30 min |
| P9 | Lista de espera quando lotado | média-baixa | 35-40 min |
| P10 | Comentários do Evento (recurso filho 1xN) | média | 35-40 min |
| P11 | Soft delete (arquivar Evento) | baixa | 30-40 min |
| P12 | Login por código aleatório (FVP real 4.1) | média-baixa | 30-40 min |
| P13 | Admin inscreve e remove um Participante | média-alta | 30-40 min |
| P14 | Comprovante de inscrição com número gerado | média | 25-35 min |
| P15 | Inscrição via JSON | média-baixa | 20-30 min |
| P16 | Remover só com código de confirmação | média-alta | 30-40 min |
| P17 | Remover com inscrições, em cascata e confirmado | média | 20-30 min |
| P18 | Evento privado com código de convite | baixa | 30-40 min |
| P19 | Remoção em lote | baixa | 25-35 min |
| P20 | Exportar Evento em iCal (`.ics`) | baixa | 20-25 min |
| P21 | Imagem de capa do Evento (upload) | baixa | 35-40 min |
| P22 | Adiar o Evento uma semana com um botão | média-baixa | 20-30 min |
| Q1 | Só o dono edita e remove | média-alta | 20-30 min |
| Q2 | Bloquear alterar Evento passado ou cancelado | média | 15-25 min |
| Q3 | Ação só para Participante | média-baixa | 20-30 min |
| Q4 | Perfil Organizador com CRUD dos próprios Eventos | média | 35-40 min |
| Q5 | Token CSRF nos formulários de Evento | média-baixa | 25-35 min |
| Q6 | Revisar escape e testar XSS | média-baixa | 15-20 min |
| R1 | Flash: texto, título do Evento, `warning` | média | 10-15 min |
| R2 | Nome do criador no `show` e na listagem | média-alta | 10-15 min |
| R3 | Formatação na view com helper num só lugar | média | 10-15 min |
| R4 | Confirmar antes de remover (JS em arquivo) | média | 10-15 min |
| R5 | Mostrar `created_at` e `updated_at` | média | 15-25 min |
| R6 | Partials reutilizados e Remover no `show` | média-baixa | 15-25 min |
| R7 | Voltar à mesma página após remover/editar | média-baixa | 10-15 min |
| R8 | Lista de todos os erros no topo do formulário | média | 15-20 min |
| R9 | Lista de presença imprimível com layout próprio | média | 20-30 min |
| R10 | "Salvar e cadastrar outro" | média-baixa | 10-15 min |
| R11 | Evento anterior e próximo no detalhe | média-baixa | 20-25 min |
| R12 | Tela reflete as regras do model | média | 10-20 min |
| R13 | Textos de vagas com concordância | média-baixa | 5-10 min |
| R14 | Título da aba com o nome do Evento | baixa-média | 10-15 min |
| S1 | Teste de aceitação da modificação | alta | 20-30 min |
| S2 | Teste de acesso por HTTP da rota nova | alta | 15-20 min |
| S3 | Teste unitário do método novo do model | alta | 10-15 min |
| S4 | Testes de borda pedidos pelo professor | média-alta | 15-25 min |
| S5 | `PaginatorTest` e Cests do `show` | média-baixa | 15-25 min |
| T1 | Populate: campo novo, mais Eventos, segundo admin | alta | 5-10 min |
| T2 | `phpcs`, `phpstan` e CI verdes | alta | 5 min |
| T3 | README e wiki da feature nova | média-baixa | 10 min |
| T4 | Itens por página e capacidade no `.env` | média-baixa | 15-20 min |

Chance é estimativa a partir da rubrica, das issues de avaliação de
2026-2 (4.1 e 4.2), do pedido real da FVP 4.1 e do que falta no
código. Letras: M campo/validação, N listagem, O rotas e ações, P
features novas, Q acesso, R views e flash, S testes, T populate e
infra. A–H são da 4.1. Os cenários M7+, N7+, O5+, P13+, Q3+, R5+, S5 e
T3+ vieram de análises de lacunas (05/11) feitas depois de os 40
primeiros terem exemplo pronto; defeitos reais da base que não viraram
cenário estão como armadilhas. A seção 5 liga frases típicas do
enunciado ao cenário que as cobre. As seções 6 e 7 reordenam tudo
supondo um enunciado único para a turma inteira.

## 5. Como o professor pede -> cenário

Frases típicas do enunciado e o cenário que as cobre. Com dois IDs, o
primeiro é o principal.

| Como o professor pede | Cenário |
|---|---|
| "Acrescente a carga horária ao Evento" | M1, T1 |
| "Marque se o Evento emite certificado (sim/não)" | M17 |
| "Evento pago: aceite 10,50 e mostre R$ 10,50" | M20 |
| "Título até 100 caracteres; vagas até 500" | M2 |
| "Descrição com no mínimo 10 caracteres" | M2 |
| "Não deixe cadastrar no passado" | já na base (`EV:88-99`) |
| "3 dias de antecedência; no máximo 1 ano à frente" | M18 |
| "Quero digitar a data como dd/mm/aaaa" | M23 |
| "Data de término, que não pode ser antes do início" | M4 |
| "O Evento precisa de horário" | M7 |
| "Acrescente o tipo Seminário" | M3 |
| "Online não precisa de local" ou "precisa de link" | M5 |
| "Online não tem limite de vagas" | M21 |
| "Dois Eventos com o mesmo título na mesma data, não" | M6 |
| "Mesmo auditório no mesmo dia, não" | M6 |
| "No máximo 3 Eventos no mesmo dia" | M22 |
| "Depois de haver inscritos, a data não muda mais" | M10 |
| "Salvei sem mexer e disse que atualizou" | M19 |
| "Escolha qual administrador é o responsável" | M15, M11 |
| "Mais próximos primeiro" | N3 |
| "Clicar em Título ou Data ordena a listagem" | N8 |
| "Filtre por tipo sem perder o filtro na paginação" | N1 |
| "Busca pelo nome" | N2 |
| "Esconda os passados" ou "uma aba de passados" | N5 |
| "Só os do mês" ou "entre duas datas" | N9 |
| "Só os que ainda têm vaga" ou "só para Estudantes" | N13 |
| "Mostre 10 por página" | N4 |
| "`/admin/events/page/999` mostra lixo" | N10 |
| "Mostre Lotado" ou "quantos ainda cabem" | N6 |
| "'1 disponíveis' está errado" | R13 |
| "Uma consulta por linha: crie `count()` no framework" | N11 |
| "Uma página só com os lotados" | O1 |
| "Evento inexistente responde 404, não 302" | O8 |
| "GET nunca pode remover nada" | O9 |
| "Baixar os Eventos em CSV" | P8 |
| "O Participante precisa ver os Eventos" | O2 |
| "Participante se inscreve se houver vaga" | P2 |
| "Participante cancela a própria inscrição" | P3 |
| "Participante vê só os Eventos dele" | P7, O2 |
| "Quem está inscrito? Mostre no detalhe" | O3 |
| "Admin inscreve ou tira alguém do Evento" | P13 |
| "Código de 4 dígitos para confirmar presença" | P1, P4 |
| "Cancelar ou encerrar o Evento" | P5 |
| "Adiar uma semana com um botão" | P22 |
| "Copiar o Evento para o mês que vem" | P6 |
| "Remover só depois de digitar um código" | P16 |
| "Só quem criou edita e remove" | Q1, O5 |
| "Evento passado não pode ser alterado" | Q2 |
| "Admin não se inscreve; só Participante" | Q3 |
| "Ponha `<script>` no título; escape tudo" | Q6 |
| "Com inscritos, nem mostre o Remover" | R12 |
| "Avise antes de apagar" | R4 |
| "Remover também no detalhe" | R6 |
| "Mostre quando o Evento foi cadastrado" | R5 |
| "Mostre o título do Evento no flash" | R1 |
| "Voltar à mesma página depois de remover" | R7 |
| "A aba do navegador deveria mostrar o Evento" | R14 |
| "Escreva o teste de aceitação disso" | S1 |
| "Testes com negativo, texto e sem alteração" | S4 |
| "Atualize o populate" ou "um segundo admin" | T1 |

## 6. Ranking sob pedido único para a turma

Premissa: o professor dá o mesmo enunciado da FVP 4.2 a todos os
grupos que entregaram a 4.2. Os domínios mudam (reservas, quadras,
campanhas, pallets, pastas de links, eventos...), o framework é o
mesmo. Então o pedido tem de caber em qualquer CRUD do template, como
o da 4.1 ("login por código") coube em qualquer login. Cenários que
dependem de inscrição, vagas, lotado ou check-in caem; os de
CRUD e framework sobem.

Classes:

- **genérico**: depende só do framework e do CRUD; o enunciado sai
  sem citar o domínio.
- **adaptável**: escrito sobre Eventos, mas com equivalente óbvio em
  outro CRUD (coluna "Equivalente genérico"). Cai quando o equivalente
  exige algo que nem todo recurso tem (data, ENUM, `user_id`, filho).
- **específico**: só existe no nosso domínio; sem equivalente.

"Chance pedido único" usa a mesma escala da seção 4; a chance original
não muda. Ordem: chance pedido único, depois chance original, depois
ID. Total: 55 genéricos, 34 adaptáveis, 11 específicos.
A matriz dos projetos dos outros grupos vai refinar esta tabela.

| # | ID | Título | Chance original | Chance pedido único | Classe | Justificativa | Equivalente genérico |
|---|---|---|---|---|---|---|---|
| 1 | M1 | Campo novo em `events` com validação | alta | alta | genérico | Todo CRUD aceita coluna nova com validação; é a linha "campo novo + validação" da rubrica e cabe no tempo | campo novo no recurso principal com validação |
| 2 | M2 | Validação nova em `lib/Validations.php` | alta | alta | genérico | Só depende de `Validations` e de um campo de texto ou número, que todo recurso tem; linha da rubrica | — |
| 3 | N1 | Filtro por tipo com `where` na paginação | alta | alta | genérico | Todo CRUD tem index paginado e algum campo para filtrar; é a linha `where` da rubrica | filtro por um campo do recurso com `where`, mantido na paginação |
| 4 | P1 | Código de check-in aleatório (1000-9999) | alta | alta | genérico | Transposição direta da FVP 4.1 para qualquer CRUD: campo gerado no backend, único, validado no model | Código aleatório 1000-9999 único por registro, gerado no backend |
| 5 | S1 | Teste de aceitação da modificação | alta | alta | genérico | Acompanha qualquer pedido único; rubrica cobra Cest da coisa nova | — |
| 6 | S2 | Teste de acesso por HTTP da rota nova | alta | alta | genérico | Todo CRUD tem auth/admin; rota nova sempre pede prova de acesso | — |
| 7 | S3 | Teste unitário do método novo do model | alta | alta | genérico | Qualquer método novo no model pede teste unitário; independe do domínio | — |
| 8 | T1 | Populate: campo novo, mais Eventos, segundo admin | alta | alta | genérico | Todo grupo tem populate; vem junto de campo novo ou paginação | populate com campo novo, mais registros e segundo admin |
| 9 | T2 | `phpcs`, `phpstan` e CI verdes | alta | alta | genérico | Implícito em qualquer pedido; CI é item da rubrica | — |
| 10 | N3 | Ordenar por data no `Paginator` | média-alta | alta | genérico | Todo `Paginator` do template não tem `ORDER BY`; pequeno, toca paginação e vale para qualquer coluna | `ORDER BY` por coluna do recurso no `Paginator`, com desempate por `id` |
| 11 | N10 | Página e id inválidos, sem `(int)` cego | média-alta | alta | genérico | O `(int)` cego vem do template e está em todo CRUD; pequeno, apontado em issue de avaliação da 4.2 | Página e id da rota validados, sem conversão silenciosa |
| 12 | P16 | Remover só com código de confirmação | média-alta | alta | genérico | Todo CRUD tem destroy; repete o padrão da FVP 4.1 (código no backend, validado) numa ação nova | — |
| 13 | S4 | Testes de borda pedidos pelo professor | média-alta | alta | genérico | Vazio, inválido, não autorizado e update sem mudança existem em todo CRUD | testes de borda da validação e do acesso do recurso |
| 14 | P5 | Status do Evento: cancelar e encerrar | alta | média-alta | adaptável | Status ativo/inativo cabe em todo recurso, mas "encerrar por data" e "barrar inscrição" são nossos | campo status (ENUM) no recurso com ação ativar/desativar |
| 15 | M6 | Unicidade (título único na mesma data) | média-alta | média-alta | genérico | Todo recurso tem nome; `uniqueness` já existe e pode já estar em uso, o que tira desafio | nome do recurso único (só ou combinado com outro campo) |
| 16 | M17 | Campo sim/não por checkbox (certificado) | média-alta | média-alta | genérico | Booleano por checkbox cabe em qualquer recurso; é campo novo + validação com armadilha comum a todos | campo sim/não por checkbox no recurso |
| 17 | N4 | Mudar `per_page` ou deixar o usuário escolher | média-alta | média-alta | genérico | Vale para todo CRUD paginado, mas é pequeno demais para ser a FVP inteira; entra como parte | — |
| 18 | O1 | Rota RESTful nova com página (lotados) | média-alta | média-alta | adaptável | "Lotados" é nosso, mas rota GET nova com página de subconjunto existe em todo CRUD | Rota GET nova listando registros do recurso que atendem a um critério |
| 19 | O2 | Eventos para o Participante (lista e detalhe) | média-alta | média-alta | genérico | Todo grupo tem admin x comum; lista e detalhe só leitura para o comum vale em qualquer domínio | Lista e detalhe do recurso, só leitura, para o usuário comum |
| 20 | P6 | Duplicar Evento | média-alta | média-alta | genérico | Todo CRUD pode duplicar um registro; pequeno, mas não toca linha da rubrica (só "+7 dias" é nosso) | duplicar um registro do recurso |
| 21 | Q1 | Só o dono edita e remove | média-alta | média-alta | adaptável | Exige `user_id` no recurso (provável, não garantido); é o D8 da 4.1 levado ao CRUD | só o dono (`user_id`) edita e remove o registro |
| 22 | M9 | Slug único gerado no backend | média | média-alta | genérico | Todo recurso tem nome; é "código gerado no backend e validado", irmão do precedente da 4.1 | slug único gerado do nome do recurso |
| 23 | M19 | "Nenhuma alteração" ao salvar sem mudar | média | média-alta | genérico | Todo CRUD tem edição; pequeno, compara com o banco e cobra detecção de alteração | "nenhuma alteração" ao salvar a edição sem mudar nada |
| 24 | N2 | Busca por título (`LIKE`) | média | média-alta | genérico | Todo recurso tem nome e listagem; pequeno, mas `LIKE` foge do `where` da rubrica | busca pelo nome do recurso na listagem |
| 25 | N8 | Ordenar clicando no cabeçalho da listagem | média | média-alta | genérico | Funciona em qualquer listagem e junta `ORDER BY` com `queryParams` na paginação; um pouco maior | — |
| 26 | N11 | `Model::count()` no framework | média | média-alta | genérico | Core pequeno, igual em todo template; contar registros (total na listagem, por dono) serve a qualquer CRUD | `count()` no Model e total de registros na listagem |
| 27 | N12 | Paginação com Primeira/Última e janela | média | média-alta | genérico | `Paginator` é item da rubrica e é igual em todo grupo; pequeno e testável sem domínio | — |
| 28 | O9 | `_method` só via POST (falha real) | média | média-alta | genérico | Falha de segurança idêntica em todo template; pequeno, com teste unitário de core | — |
| 29 | R4 | Confirmar antes de remover (JS em arquivo) | média | média-alta | genérico | Todo CRUD tem destroy; pequeno, regra de JS em arquivo próprio já cobrada pelo professor | confirmar remoção com JS em arquivo próprio |
| 30 | R5 | Mostrar `created_at` e `updated_at` | média | média-alta | genérico | Toda tabela pode ter `created_at`; o core não lê timestamps e `save()` grava NULL: armadilha igual p/ todos | exibir data de cadastro e de alteração do registro |
| 31 | R8 | Lista de todos os erros no topo do formulário | média | média-alta | genérico | Universal e ligado à validação (item da demonstração); exige método pequeno no core | listar todos os erros de validação no topo do form |
| 32 | M4 | Data de término não anterior ao início | média-alta | média | adaptável | Nem todo recurso tem data; sem ela o grupo cria duas colunas e o pedido cresce | segundo campo de data com ordem em relação ao primeiro |
| 33 | M7 | Horário do Evento (`TIME` ou `DATETIME`) | média-alta | média | adaptável | Depende de campo de data, que nem todo recurso tem; formato `HH:MM:SS` gera armadilha igual para todos | campo de hora ou data-hora no recurso, validado |
| 34 | M18 | Janela de datas: antecedência e limite | média-alta | média | adaptável | Exige campo de data; quem não tem precisa criar antes, e o pedido deixa de caber | data do recurso dentro de uma faixa de dias a partir de hoje |
| 35 | O5 | Admin vê e lista só os próprios Eventos | média-alta | média | adaptável | Mesma dependência de `user_id` do Q1, que é o pedido mais provável dessa família | Admin só lista e cadastra registros dele (`user_id`) |
| 36 | R2 | Nome do criador no `show` e na listagem | média-alta | média | adaptável | Depende de `user_id`/dono no recurso, que é provável mas não garantido em todo CRUD | mostrar o usuário dono do registro (BelongsTo) no detalhe |
| 37 | M5 | Validação condicional por modalidade | média | média | adaptável | Exige um campo de categoria (tipo, status); comum mas não universal | campo obrigatório só quando outro campo tem certo valor |
| 38 | M10 | Campo que não muda depois de haver inscrições | média | média | adaptável | Inscrição é nossa, mas "não muda após criação" serve a todo CRUD e cobra detecção de alteração | campo que não pode mudar depois de criado (comparar com o banco) |
| 39 | M11 | Validar `user_id` no model | média | média | adaptável | `user_id` no recurso é provável, não garantido; sem dono vale para qualquer FK | validar no model a FK do recurso (existe e tem o papel certo) |
| 40 | M20 | Preço em reais com vírgula | média | média | adaptável | Valor decimal cabe na maioria dos recursos, mas em alguns domínios soa forçado | campo decimal com vírgula, validado e formatado na view |
| 41 | N5 | Só Eventos futuros ou passados | média | média | adaptável | Exige campo de data no recurso; nem todo recurso tem data além de `created_at` | Filtro por campo de data do recurso (antes/depois de hoje) |
| 42 | N6 | Dado calculado na tela (badge "Lotado", total) | média | média | adaptável | "Lotado" depende de vagas; o total de registros no painel serve a todos, mas é pequeno | Total de registros no painel ou valor derivado na listagem |
| 43 | N7 | Painel administrativo com resumo | média | média | adaptável | Painel é genérico de nicho; contagem por tipo exige ENUM ou categoria, que nem todos têm | Painel admin com total por categoria e últimos N registros |
| 44 | N9 | Filtro por período | média | média | adaptável | Validar o filtro no model vale para todos, mas exige campo de data no recurso | Filtro de/até sobre campo de data do recurso, com validação |
| 45 | P8 | Exportar a listagem em CSV | média | média | genérico | Roda em qualquer listagem, mas é formato de nicho fora da rubrica (âncora de média) | — |
| 46 | Q2 | Bloquear alterar Evento passado ou cancelado | média | média | adaptável | Depende de campo de data ou status no recurso, comum mas não universal | bloquear editar/remover conforme data ou status do registro |
| 47 | R1 | Flash: texto, título do Evento, `warning` | média | média | genérico | Todo CRUD tem flash no destroy; pequeno demais para pedido sozinho, costuma vir junto | flash com o nome do registro e tipo `warning` novo |
| 48 | R3 | Formatação na view com helper num só lugar | média | média | adaptável | Truncar texto ou badge vale em qualquer CRUD; data por extenso exige campo de data | método de apresentação no model (texto truncado, badge) |
| 49 | M12 | Último editor gravado da sessão | média-baixa | média | genérico | Só precisa de login e do recurso; valor vindo da sessão e nunca do form serve a todo CRUD | — |
| 50 | M14 | Contador de visualizações com `update()` | média-baixa | média | genérico | Coluna nova e `update()` no `show` servem a todo CRUD; pequeno, mas foge das linhas da rubrica | contador de visualizações no `show` com `update()` |
| 51 | M22 | No máximo N Eventos por dia | média-baixa | média | adaptável | "Por dia" exige data, mas "por dono" ou por valor de campo vale em todo CRUD | no máximo N registros por dono ou por valor de um campo |
| 52 | O7 | `Route::patch` no core | média-baixa | média | genérico | Core pequeno, igual em todo template; a ação parcial pode ser qualquer campo do recurso | — |
| 53 | O10 | `Route::resource` ou `Route::prefix` no core | média-baixa | média | genérico | Item de framework da rubrica, aplicável a todo `routes.php`; 25-40 min e risco na ordem das rotas | — |
| 54 | P22 | Adiar o Evento uma semana com um botão | média-baixa | média | adaptável | Ação de um botão que altera campo é universal; exige campo de data, comum mas não garantido | botão que soma N dias à data do recurso (ou altera campo) |
| 55 | Q5 | Token CSRF nos formulários de Evento | média-baixa | média | genérico | Todo CRUD tem forms POST/DELETE e middleware; repete o A15 da 4.1, mas mexe em rotas e helper de login | token CSRF nos formulários do CRUD (criar, editar, remover) |
| 56 | Q6 | Revisar escape e testar XSS | média-baixa | média | genérico | "Ponha `<script>` no campo de texto" roda em qualquer CRUD; pequeno, mas não é linha da rubrica | escapar toda saída do recurso e provar com teste de XSS |
| 57 | R7 | Voltar à mesma página após remover/editar | média-baixa | média | genérico | Todo CRUD tem index paginado + destroy; toca a linha de paginação da rubrica | após remover/editar, voltar à mesma página da listagem |
| 58 | R10 | "Salvar e cadastrar outro" | média-baixa | média | genérico | Todo create aceita o botão extra; pequeno, mas fora da rubrica | botão "Salvar e cadastrar outro" no create |
| 59 | S5 | `PaginatorTest` e Cests do `show` | média-baixa | média | genérico | `Paginator` e show estão em todo template; Paginator é linha da rubrica sem teste próprio | — |
| 60 | T4 | Itens por página e capacidade no `.env` | média-baixa | média | genérico | `per_page` fixo existe em todo index paginado; limite máximo exige campo numérico | itens por página (e limite de campo numérico) lidos do `.env` |
| 61 | O4 | Resposta JSON da listagem | baixa | média | genérico | Genérico de nicho, vale para todos via `renderJson`; prepara o Ajax da 4.4 | — |
| 62 | O8 | Status HTTP corretos (404, 422) | baixa | média | genérico | Todo CRUD responde 302 para não encontrado e 200 para form inválido; pequeno, mas mexe em testes | — |
| 63 | P11 | Soft delete (arquivar Evento) | baixa | média | genérico | Todo CRUD tem destroy; arquivar em vez de apagar é universal, mas mexe no Paginator (`IS NULL`) | — |
| 64 | P19 | Remoção em lote | baixa | média | genérico | Toda listagem admin aceita checkbox e destroy em lote; sobe, mas fica fora da rubrica | — |
| 65 | P7 | "Meus eventos" do Participante | média-alta | média-baixa | adaptável | Exige registros ligados ao usuário comum; no template o CRUD é do admin e user_id não é garantido | página "meus registros" do usuário logado (por user_id) |
| 66 | M3 | Valor novo em ENUM/SET | média | média-baixa | adaptável | Exige ENUM no recurso, não garantido em todos; pequeno demais para render uma FVP sozinho | valor novo num campo de opções fixas do recurso |
| 67 | O3 | Inscritos no `show` (`participants()`) | média | média-baixa | adaptável | Exige relação N:N ou segundo recurso, que nem todo grupo tem; inscrição é conceito nosso | Registros relacionados (1xN ou N:N) listados no `show` |
| 68 | P10 | Comentários do Evento (recurso filho 1xN) | média | média-baixa | genérico | Recurso filho cabe em qualquer CRUD, mas é o cenário mais longo e antecipa a 4.3 | comentários (recurso filho 1xN) no recurso |
| 69 | P17 | Remover com inscrições, em cascata e confirmado | média | média-baixa | adaptável | Só faz sentido se o recurso tiver filhos com FK RESTRICT; nem todo grupo tem segundo recurso | remover registro com filhos em cascata, após confirmação |
| 70 | Q4 | Perfil Organizador com CRUD dos próprios Eventos | média | média-baixa | genérico | Todo grupo tem papéis e CRUD, mas papel novo + middleware + dono passa dos 40 min | perfil novo com CRUD só dos próprios registros |
| 71 | R9 | Lista de presença imprimível com layout próprio | média | média-baixa | específico | Lista de inscritos só existe aqui; reaproveita só o layout de impressão sem navbar | nenhum |
| 72 | R12 | Tela reflete as regras do model | média | média-baixa | adaptável | Nossa regra usa inscrições; outros precisam de uma regra de estado que bloqueie ação | esconder ação ou ajustar `min`/`max` conforme regra do model |
| 73 | M8 | Enum PHP para tipo e modalidade | média-baixa | média-baixa | adaptável | Refatoração que exige constantes de opções; nem todo recurso tem ENUM e não acrescenta função | constantes de opções do recurso viram `enum` do PHP |
| 74 | M13 | Montagem do Evento no model ou service | média-baixa | média-baixa | genérico | Refatoração sem função nova é difícil de avaliar igual entre grupos; rara em FVP | mover a montagem do recurso do controller para model/service |
| 75 | M15 | Escolher o responsável entre os admins | média-baixa | média-baixa | adaptável | Exige `user_id` no recurso e inverte a regra do dono; maior e com decisões por grupo | escolher no form o usuário responsável pelo recurso, validado |
| 76 | M21 | Vagas ilimitadas (`capacity` NULL) | média-baixa | média-baixa | adaptável | Exige campo numérico com limite já existente, que nem todo recurso tem | campo numérico opcional em que vazio significa "sem limite" |
| 77 | N13 | Filtro fora do `where` (SET, vagas) | média-baixa | média-baixa | adaptável | SET e contagem de vagas são raros; serve só quem tem campo numérico ou multivalorado | Filtro com condição que não é igualdade (faixa numérica, `LIKE`) |
| 78 | O6 | Inscritos em página própria, paginada | média-baixa | média-baixa | adaptável | Exige recurso filho; parte reaproveitável: rota aninhada com `{id}` e paginação dos links | Registros filhos de um recurso em rota aninhada paginada |
| 79 | R6 | Partials reutilizados e Remover no `show` | média-baixa | média-baixa | genérico | Remover no show vale em todos; extrair partial exige várias telas com a mesma tabela | partial da tabela/ações do recurso e Remover no detalhe |
| 80 | R11 | Evento anterior e próximo no detalhe | média-baixa | média-baixa | adaptável | Por id vale em todos; ordenar por data exige campo de data e SQL próprio | links anterior/próximo no detalhe (por id ou campo de ordem) |
| 81 | R14 | Título da aba com o nome do Evento | baixa-média | média-baixa | genérico | Todo layout tem `<title>` e todo show tem nome; trivial e fora da rubrica | `<title>` da aba com o nome do registro |
| 82 | T3 | README e wiki da feature nova | média-baixa | média-baixa | genérico | Vale para todos, mas é acessório; raramente vira o pedido em si | — |
| 83 | M16 | Gancho `beforeValidation` no core (espaços) | baixa | média-baixa | genérico | Genérico e pequeno, mas é mudança de core; o professor tende a pedir no recurso, não no `Model` | — |
| 84 | N14 | Query Builder mínimo no core | baixa | média-baixa | genérico | Vale para todos e houve aula de Builder, mas 40-60 min de core estoura a prova | — |
| 85 | O11 | Página 404 amigável, sem stack trace | baixa | média-baixa | genérico | Stack trace aparece em todo template, mas é infra de erro, longe do CRUD que a 4.2 avalia | — |
| 86 | P20 | Exportar Evento em iCal (`.ics`) | baixa | média-baixa | adaptável | Exige campo de data (nem todo recurso tem) e formato de nicho | exportar registro com data em .ics |
| 87 | P2 | Inscrição do Participante em um Evento | média-alta | baixa | específico | Exige N:N usuário-recurso com vagas; nem todo CRUD tem inscrição, e o NxN é tema da 4.4 | nenhum |
| 88 | P13 | Admin inscreve e remove um Participante | média-alta | baixa | específico | Depende de inscrição N:N; a parte "validar user_id vindo do form" já é M11/M15 | nenhum |
| 89 | P3 | Cancelar a própria inscrição | média | baixa | específico | Só existe em cima de P2; sem inscrição não há o que cancelar | nenhum |
| 90 | P14 | Comprovante de inscrição com número gerado | média | baixa | específico | Vive na tabela de inscrição; o padrão "código 1000-9999 único" no recurso já é P1 | nenhum |
| 91 | P4 | Confirmar presença pelo código do Evento | média-baixa | baixa | específico | Presença/inscrição são nossos; a parte "código gerado e validado" já é P1/P16 | nenhum |
| 92 | P9 | Lista de espera quando lotado | média-baixa | baixa | específico | Exige vagas/lotado e inscrição; empilha P2 e P3, sem equivalente em outros CRUDs | nenhum |
| 93 | P12 | Login por código aleatório (FVP real 4.1) | média-baixa | baixa | genérico | Genérico, mas já foi a FVP 4.1 de toda a turma; repetir não avalia nada da 4.2 | — |
| 94 | P15 | Inscrição via JSON | média-baixa | baixa | específico | Exige P2; a parte JSON genérica já é O4 (listagem em JSON) | nenhum |
| 95 | Q3 | Ação só para Participante | média-baixa | baixa | específico | A ação barrada (inscrever-se) é nossa; o middleware "só não-admin" é a parte reaproveitável | nenhum |
| 96 | R13 | Textos de vagas com concordância | média-baixa | baixa | específico | Vagas e inscritos são só nossos; sobra só a ideia de plural no model | nenhum |
| 97 | M23 | Data digitada como dd/mm/aaaa | baixa | baixa | adaptável | Exige campo de data e o `type="date"` já mostra dd/mm/aaaa; pedido raro | campo de data aceito em dd/mm/aaaa e gravado em Y-m-d |
| 98 | N15 | Dois paginadores na mesma tela | baixa | baixa | adaptável | Precisa de critério para separar duas listas (datas) e de mexer no `Paginator`; grande e raro | Duas listagens paginadas independentes do recurso na mesma tela |
| 99 | P18 | Evento privado com código de convite | baixa | baixa | específico | Convite e inscrição são nossos; o código gerado reaproveitável já é P1 | nenhum |
| 100 | P21 | Imagem de capa do Evento (upload) | baixa | baixa | genérico | Cabe em qualquer recurso, mas upload é tema da 4.3 e passa de 40 min com testes | imagem (upload) no recurso |

## 7. Top 15 para estudar primeiro sob pedido único

Ordem de estudo: cada item usa o anterior. Em todos, faça junto T1
(populate), S4 (bordas) e T2 (`phpcs`, `phpstan`, CI); não contam
na lista porque acompanham qualquer pedido.

| Ordem | ID | Por que nessa posição |
|---|---|---|
| 1 | M1 | Base de quase tudo: coluna, `$columns`, form, validação, populate |
| 2 | M2 | Regra nova em `Validations`, usada pelos itens seguintes |
| 3 | S3 | Teste unitário do model; prova de 1, 2 e do resto |
| 4 | P1 | Código gerado no backend e validado: o padrão da FVP 4.1 |
| 5 | P16 | Mesmo padrão (código na sessão) aplicado ao destroy |
| 6 | N1 | `where` mantido na paginação: linha da rubrica |
| 7 | N3 | `ORDER BY` no `Paginator`, que falta em todo template |
| 8 | N10 | Página e id inválidos sem `(int)`: defeito do template |
| 9 | S1 | Cest de aceitação da mudança, cobrado em toda entrega |
| 10 | S2 | Teste de acesso por HTTP de rota ou ação nova |
| 11 | M6 | Unicidade com `$this->id` no update |
| 12 | M19 | Comparar com o banco antes de salvar; base de M10 |
| 13 | Q1 | Autorização do dono no servidor, se o recurso tem `user_id` |
| 14 | M17 | Checkbox: campo ausente no POST e `TINYINT` volta int |
| 15 | M9 | Slug: geração no backend + unicidade, juntando 4 e 11 |

Próximos, se sobrar tempo: R5, N11, N2, N8, N12, N4, O1, O2, O9, P5,
P6, R4, R8 (todos média-alta sob pedido único).

## 8. Cenários

### M1. Campo novo em `events` com validação

Chance: alta. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/m1-campo-novo-workload-hours`.

Pedido típico: "acrescente a carga horária (`workload_hours`, inteiro
>= 1, obrigatório) ao Evento". Variantes: `speaker` (texto, opcional),
`price` (decimal >= 0), `registration_deadline` (data).

Arquivos (todos mudam):

- `SCH:21-38` (`CREATE TABLE events`): a coluna nova.
- `EV:10-21` (`@property`), `EV:48-58` (`$columns`), `EV:60-82`
  (`validates()`; `EV:79-81` é o modelo com `notEmpty` + `integerMin`).
- `EC:14` (`FIELDS`: só o que está aqui é copiado do form, `EC:123-126`).
- `FORM:84-89` (bloco de `capacity`, copiar), `IDX:11-16` e `IDX:22-26`
  (coluna), `SHOW:28-32` (par `dt`/`dd`).
- `POP:134-143` (array do `new Event`).
- Testes: `EVT:41-54` (`params()`), `ECT:272-284` (`validParams()`),
  `ACT/BaseEventsCest.php:84-94` (`seedEvents`), `:122-158`
  (`fillEventForm`, senão o Cest nunca envia o campo) e `:163-175`
  (`validEventData`), `ACC:28-40` (Evento do `setUp`),
  `tests/Unit/Models/Events/EnrollmentTest.php:37-49` (o `setUp` salva um
  Evento).

Passos:

1. Teste primeiro: em `EVT`, copie `test_capacity_should_be_at_least_one`
   (`EVT:196-208`) para o campo novo.
2. `SCH`: `workload_hours INT NOT NULL` depois de `capacity` (`SCH:31`).
3. `EV`: `@property int|string $workload_hours`, a chave em `$columns`,
   e em `validates()`:

   ```php
   Validations::notEmpty('workload_hours', $this) &&
       Validations::integerMin('workload_hours', $this, 1);
   ```
4. `EC:14`: acrescente `'workload_hours'` em `FIELDS`.
5. `FORM`: copie o `<div class="mb-3">` de `capacity` trocando `id`,
   `name="event[workload_hours]"` e o `value`; mantenha `$invalid()` e
   `$feedback()` (`FORM:11-14`). Coluna na `IDX`, `dt`/`dd` na `SHOW`.
6. `POP:134-143`: `'workload_hours' => 4,` (igual para os 16; não mexa
   nas linhas posicionais de `POP:49-130`).
7. Testes: a chave em `params()`, `validParams()`, `seedEvents`,
   `fillEventForm`, no `setUp` do `ACC` e no do `EnrollmentTest`, senão
   quase tudo falha por campo vazio.
8. `./run db:populate` e a suíte.

Armadilhas:

- Esquecer `$columns`: `Property workload_hours not found`
  (`MODEL:86`) ao atribuir, e a coluna nunca vai ao banco.
- Esquecer `FIELDS`: o form envia, o controller ignora, "nunca salva"
  e a validação reclama de vazio.
- Não refazer o schema no banco de desenvolvimento: `Unknown column`
  só no navegador; os testes passam porque recriam o schema.
- `DEFAULT` na coluna não ajuda: `save()` insere **todas** as colunas
  de `$columns` (`MODEL:143-158`), com `NULL` quando falta valor.
- `seedEvents` não confere o retorno de `save()` (`BaseEventsCest.php:95`):
  sem o campo os Cests falham com "0 linhas", sem mensagem clara.
- Campo opcional: não use `notEmpty`; para tipo numérico opcional,
  `if ($this->speaker !== null && $this->speaker !== '')` antes.
- O form tem `novalidate` (`FORM:16`): o erro vem do servidor.
- `(int) $value` no controller é "conversão silenciosa" (issue do
  grupo 5): deixe a string ir ao model e o `integerMin` rejeitar.

Prova: `./run test tests/Unit/Models/Events/EventTest.php` e
`tests/Integration/Controllers/Admin/EventsControllerTest.php`; no
navegador, enviar vazio e `0`: erro embaixo do campo, demais valores
mantidos (`ECT:114-138` é o modelo do teste).

### M2. Validação nova em `lib/Validations.php`

Chance: alta. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/m2-validacao-maxlength`.

Pedido típico: "o título não pode passar de 100 caracteres"
(`maxLength`), "descrição com no mínimo 10" (`minLength`), "vagas no
máximo 500" (`integerMax`), "local só letras, números e espaços"
(`format` com regex).

Arquivos: `VAL:78-94` (`integerMin`, o modelo), `EV:62-64` e `EV:79`
(onde chamar),
`tests/Unit/Lib/Validations/NumberAndDateValidationsTest.php:13-48`
(modelo do teste; o model anônimo está em `:15-17`), `EVT:185-208`.

Passos:

1. Teste: copie `test_integer_min_should_reject_values_below_the_minimum`
   (`:30`) e adapte.
2. O model anônimo do teste só tem colunas numéricas (`:16`): para
   `maxLength` acrescente uma coluna de texto em `$columns`; sem ela,
   `$this->model->name = ...` lança `Property name not found`
   (`MODEL:86`). Mantenha o `// @phpstan-ignore-line` como em `:23`.
3. Em `VAL`, método estático com o formato de `integerMin`:

```php
public static function maxLength($attribute, $obj, int $max)
{
    $value = $obj->$attribute;

    if (is_string($value) && mb_strlen($value) > $max) {
        $obj->addError($attribute, "deve ter no máximo {$max} caracteres!");
        return false;
    }

    return true;
}
```

4. `EV:62`:

   ```php
   Validations::notEmpty('title', $this) &&
       Validations::maxLength('title', $this, 100);
   ```

   (encadeamento com `&&`, padrão de `EV:66-77`).
5. Teste em `EVT` (cópia de `EVT:185-194`) com título de 101 caracteres
   (`str_repeat('a', 101)`).

Armadilhas:

- `strlen` conta bytes: `mb_strlen` para acentos.
- Valor `null`: `notEmpty` primeiro, por isso o `&&`.
- Mensagem em pt-BR terminada em `!`, como as demais.
- Validação no controller: recusada (regra de negócio no model).
- `lib/` não é analisado pelo `phpstan` (`phpstan.neon` cobre `app`,
  `core`, `tests`); tipe os parâmetros novos mesmo assim, o `phpcs`
  roda em tudo.
- `integerMax` tem caso real: `capacity` acima de 2147483647 (`INT`)
  passa no `integerMin` e estoura no `INSERT` com `PDOException` (500).
- `format`: use `preg_match('/^[\p{L}\p{N} ]+$/u', $value) !== 1`
  (flag `u` para acentos).
- Caso real na base: título ou local com mais de 255 caracteres passa em
  `EV:62,64` (só `notEmpty`) e estoura o `VARCHAR(255)` (`SCH:24,30`):
  o MySQL 8.4 do compose roda em modo estrito e o `INSERT` lança
  `PDOException` (500). `VARCHAR` conta caracteres (`mb_strlen` bate);
  `description` é `TEXT` (`SCH:25`), limite de 65535 **bytes** (`strlen`).

Prova: teste novo do `Validations` e do `Event`; no navegador, erro
sob o campo e o resto do form preenchido.

### M3. Valor novo em ENUM/SET

Chance: média. Tempo: 10-15 min.

Exemplo pronto: `fvp/4.2/exemplos/m3-enum-novo-seminar`.

Pedido típico: "acrescente o tipo `seminar` (Seminário)" ou "público
`alumni` (Egressos)".

Arquivos: `SCH:26` (`kind`), `SCH:27` (`modality`), `SCH:28`
(`audience`), `EV:25-30` (`KINDS`), `EV:33-37` (`MODALITIES`),
`EV:40-45` (`AUDIENCES`), `EVT:59-65` (`createEvent`, chama `save()`),
`EVT:140-146`.

Passos:

1. Teste em `EVT`: `$event = $this->createEvent(['kind' => 'seminar']);`
   (ele faz `assertTrue($event->save())`) e
   `assertSame('seminar', Event::findById($event->id)->kind)`.
2. `SCH`: o valor no ENUM/SET. `EV`: chave e rótulo na constante.
3. `./run db:populate` (refaz o schema).
4. `FORM:39-41`, `:48-54`, `:60-66`, `IDX:23`, `SHOW:4,14,17` percorrem
   as constantes: não mudam.

Armadilhas:

- Teste só com `isValid()` passa com o schema velho (`EVT:140-146`);
  é o `INSERT` que falha com `Data truncated`. Chame `save()`.
- Só a constante: `INSERT` falha. Só o schema: `inclusion`
  (`VAL:34-44`) rejeita.
- Valor em inglês sem acento; rótulo em pt-BR.
- SET guarda `students,staff`; `audienceList()`/`setAudienceList()`
  (`EV:180-206`) já tratam.

Prova: `EventTest` com `save()`; criar um Evento com o valor novo no
navegador e ver o rótulo na listagem e no `show`.

### M4. Segunda data (`ends_on`) não anterior a `starts_on`

Chance: média-alta. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/m4-segunda-data-ends-on`.

Pedido típico: "o Evento tem data de término, que não pode ser antes
do início". Variante: "prazo de inscrição (`registration_deadline`)
até a data do Evento".

Arquivos: os de M1 mais `EV:75-77` (bloco de `starts_on`), `EV:88-99`
(`validatesStartsOnNotPast`, modelo de método privado de validação),
`VAL:99-107` (`validDate`), `VAL:140-153` (`parseDate`, privado),
`FORM:70-75` (campo `date`), `ACT/BaseEventsCest.php:148-154` (a data é
preenchida por `executeJS`), `EVT:210-233`.

Passos:

1. Testes em `EVT`: `ends_on` igual a `starts_on` válido; um dia antes
   inválido com mensagem; `ends_on` inválida marcada.
2. M1 completo para a coluna `ends_on DATE NOT NULL` (ou `NULL` se
   opcional).
3. Em `validates()`:

   ```php
   Validations::notEmpty('ends_on', $this) &&
       Validations::validDate('ends_on', $this) &&
       $this->validatesEndsOnAfterStartsOn();
   ```
4. Método privado:

```php
private function validatesEndsOnAfterStartsOn(): void
{
    $start = $this->parseDate($this->starts_on);
    $end = $this->parseDate($this->ends_on);

    if ($start !== null && $end !== null && $end < $start) {
        $this->addError(
            'ends_on',
            'não pode ser anterior à data de início!'
        );
    }
}

private function parseDate(mixed $value): ?\DateTimeImmutable
{
    if (!is_string($value)) {
        return null;
    }

    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
}
```

   A conferência `format() === $value` é a mesma de `VAL:140-153`:
   `createFromFormat('!Y-m-d', '2030-02-31')` devolve 03/03/2030, não
   `false`, e sem ela a comparação roda com uma data "válida" errada.

5. Form, listagem, show, populate
   (`'ends_on' => date('Y-m-d', strtotime("+{$daysAhead} days"))`
   ou +1 dia), testes de controller e Cests (`fillEventForm` precisa de
   um `executeJS` para o campo novo, cópia de `:148-154`).

Armadilhas:

- Comparar strings de data funciona para `Y-m-d`, mas só se as duas
  forem válidas: valide cada uma antes (`validDate`) e saia se alguma
  for inválida, senão aparecem dois erros contraditórios.
- `parseDate` de `VAL` é privado: não dá para reaproveitar de fora;
  copie a chamada `createFromFormat('!Y-m-d', ...)`.
- `starts_on` tem a regra "não no passado" só quando muda
  (`EV:88-99`); não copie essa regra para `ends_on` sem pedido.
- Selenium: `type="date"` não aceita `fillField` em pt-BR; use o
  padrão do `executeJS` da base.

Prova: `EventTest` novo; no navegador, término antes do início mostra
o erro sob o campo e mantém os valores.

### M5. Validação condicional por modalidade

Chance: média. Tempo: 15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/m5-validacao-condicional-modalidade`.

Pedido típico: "Evento online não precisa de local" ou "Evento online
precisa de um link (URL) no campo local".

Arquivos: `EV:64` (`notEmpty('location')`), `EV:33-37` (`MODALITIES`),
`EV:60-82`, `FORM:77-82`, `EVT:86-126` (os testes de campo obrigatório
assumem `location` sempre obrigatório: `requiredFieldsProvider`,
`EVT:101-114`). Se exigir URL nos online, as fixtures online precisam de
link válido: `EnrollmentTest`, `ACC:28-40` e `:144-153`,
`ACT/BaseEventsCest.php` (`seedEvents`, `validEventData`),
`ACT/CreateWithInvalidDataCest.php`, `POP:58,88,108`. O exemplo seguiu
outro caminho: coluna nova `link VARCHAR(255) NULL`, `location` passa a
`NULL`, e mexe também em `SCH`, `EC:14`, `IDX`, `SHOW` e `POP`.

Passos:

1. Testes em `EVT`: online sem local válido; presencial sem local
   inválido; (variante) online com local que não é URL inválido.
2. `validates()`:

```php
if ($this->modality === 'online') {
    Validations::notEmpty('location', $this) && $this->validatesLocationIsUrl();
} else {
    Validations::notEmpty('location', $this);
}
```

   com `filter_var($this->location, FILTER_VALIDATE_URL) === false` no
   método privado e mensagem "deve ser um link (URL) válido!".
3. Rótulo do campo no form pode virar "Local ou link".
4. `requiredFieldsProvider` (`EVT:101-114`) só muda se o fixture padrão
   (`params()`, `in_person`) virar online; com `in_person`, `location`
   continua obrigatório e o provider fica como está.

Armadilhas:

- A coluna `location` é `NOT NULL` (`SCH:30`): se o local pode ficar
  vazio, grave `''`, ou mude para `NULL` no schema.
- `modality` inválida ou vazia cai no `else`; é o esperado.
- Populate: os Eventos online já têm texto de link (`POP:58,88,108`);
  se exigir URL real, troque para `https://...`.

Prova: `EventTest`; no navegador, criar online sem local e presencial
sem local.

### M6. Unicidade (título único na mesma data)

Chance: média-alta (duplicidade cobrada na issue 4.2 do grupo 5;
`uniqueness` está na rubrica). Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/m6-unicidade-titulo-data`.

Pedido típico: "não pode haver dois Eventos com o mesmo título" ou
"com o mesmo título na mesma data".

Arquivos: `VAL:155-205` (`uniqueness`, aceita um campo ou vários:
`:160-162`), `US:42` (uso em `User`), `EV:60-82`,
`tests/Unit/Lib/Validations/UniquenessValidationsTest.php:111-125`
(dois campos), `EVT:302-308`, `ACT/BaseEventsCest.php:84-94` (títulos
`Evento 01..16` já são únicos).

Passos:

1. Teste em `EVT`: dois Eventos iguais; o segundo `save()` falha e
   `errors('title')` é "já existe um registro com esse dado".
2. `validates()`: `Validations::uniqueness(['title', 'starts_on'], $this);`
   (ou só `'title'`). Como `errors()` guarda uma mensagem por campo (a
   última vence), chame dentro de
   `if ($this->errors('title') === null && $this->errors('starts_on') === null)`
   para não trocar "não pode estar no passado!" por "já existe...".
3. Teste de controller: `create` duplicado renderiza o form com o erro.

Armadilhas:

- `uniqueness` ignora o próprio registro ao editar sem mudar os
  campos (`VAL:164-179`): editar continua funcionando.
- A mensagem vem sem `!` e aparece em todos os campos da lista
  (`VAL:197-202`); se o professor pedir outra, mude em `VAL`.
- O populate tem títulos únicos; o `ECT:39-57` cria "Evento 1..7" com a
  mesma data, também únicos.
- Não há índice único no schema para isso; se pedirem, acrescente
  `UNIQUE KEY` em `SCH` e trate a `PDOException` (padrão `EV:166-172`).

Prova: `EventTest`; no navegador, cadastrar o mesmo título duas vezes.

### M7. Horário do Evento

Chance: média-alta. Tempo: 30-40 min.

Exemplo pronto: `fvp/4.2/exemplos/m7-horario-do-evento` (forma B,
`starts_at DATETIME`).

Pedido típico: "o Evento precisa de horário de início (e de fim)". Duas
formas: (A) colunas `starts_time TIME NOT NULL` e `ends_time TIME NULL`
ao lado de `starts_on` (menor, recomendada); (B) trocar `starts_on DATE`
por `starts_at DATETIME` (renomeia a coluna em todos os pontos de M1 e
muda a regra "não no passado" para considerar a hora).

Arquivos: os de M1 (schema, `@property`, `$columns`, `validates()`,
`FIELDS`, `FORM`, `IDX`, `SHOW`, `POP`, fixtures) mais `VAL:99-107`
(`validDate` aceita `$format`), `VAL:116-138` (`notPastDate`, forma B),
`FORM:70-75` (campo `date`), `ACT/BaseEventsCest.php:148-154` (a data é
preenchida por `executeJS`; o campo `time` precisa do mesmo), `IDX:24` e
`SHOW:23` (formatação da data).

Passos (forma A):

1. Testes em `EVT`: `'starts_time' => '14:00'` válido; `'25:00'`,
   `'14h'` e `''` inválidos; `ends_time` antes de `starts_time` inválido
   (compare as strings `H:i`, que ordenam bem).
2. `SCH`: `starts_time TIME NOT NULL, ends_time TIME NULL` depois de
   `starts_on`.
3. `EV`: `@property`, `$columns`, e em `validates()`:

   ```php
   Validations::notEmpty('starts_time', $this) &&
       Validations::validDate('starts_time', $this, 'H:i') &&
       $this->validatesEndsTimeAfterStartsTime();
   ```

4. `EC:14`: `'starts_time'`, `'ends_time'` em `FIELDS`.
5. `FORM`: `<input type="time" ...>` copiando o bloco de `starts_on`;
   `IDX:24`/`SHOW:23`: `date('d/m/Y')` + `substr($event->starts_time, 0, 5)`.
6. `POP:134-143`: `'starts_time' => '19:00'`; fixtures de M1 (`params()`,
   `validParams()`, `seedEvents`, `validEventData`, `fillEventForm` com
   `executeJS` para o `time`, `ACC`, `EnrollmentTest`).

Armadilhas:

- O MySQL devolve `TIME` como `HH:MM:SS`: ao **editar**, `validDate(...,
  'H:i')` recusa `14:00:00`. Normalize antes de validar
  (`$this->starts_time = substr((string) $this->starts_time, 0, 5)` em
  `validates()` ou no construtor) ou aceite os dois formatos.
- `type="time"` no Selenium: `fillField` não digita; use `executeJS`
  como a base faz para `date` (`BaseEventsCest.php:148-154`).
- Forma B: `notPastDate` compara só o dia (`VAL:130` zera a hora);
  para "não no passado" por hora crie `notPastDateTime` com formato
  `'Y-m-d H:i'` e sem `setTime(0, 0)`. O `<input type="datetime-local">`
  envia `2026-11-20T19:00` (com `T`): troque o `T` por espaço antes de
  validar ou use o formato `'Y-m-d\TH:i'`.
- Fuso: o PHP está em `America/Sao_Paulo` (`Dockerfile:10`); o MySQL
  guarda `TIME`/`DATETIME` sem fuso. Não misture com `TIMESTAMP`.
- Formato na view: `date('H:i', strtotime($event->starts_time))`
  funciona para `TIME`, mas `strtotime('')` vira 1970 (R3): proteja.

Prova: `EventTest` e `EventsControllerTest`; no navegador, criar com
hora, editar sem mexer na hora e salvar (é onde o `HH:MM:SS` morde).

### M8. Enum PHP para tipo e modalidade

Chance: média-baixa. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/m8-enum-event-kind`.

Pedido típico: "troque as constantes `KINDS`/`MODALITIES` por um `enum`
do PHP 8.1 com os rótulos" (OO e reutilização, assunto das issues da
4.1).

Arquivos: `EV:24-37` (`KINDS`, `MODALITIES`), `EV:66-70` (`inclusion`
com `array_keys`), `FORM:37-44` e `:46-56` (laços sobre as constantes),
`IDX:23`, `SHOW:14,17`, `EVT:140-168`, `POP:46-130` (valores crus,
continuam válidos), `composer.json:7-14` (PSR-4 `App\` -> `app/`).

Passos:

1. Testes em `EVT`: `EventKind::from('lecture')->label() === 'Palestra'`;
   `EventKind::tryFrom('x') === null`; `Event` com `kind` fora dos
   `values()` inválido (já existe em `EVT:147-153`, só muda a fonte).
2. `app/Models/Enums/EventKind.php`:

   ```php
   namespace App\Models\Enums;

   enum EventKind: string
   {
       case Lecture = 'lecture';
       case Workshop = 'workshop';
       case ShortCourse = 'short_course';
       case AcademicWeek = 'academic_week';

       public function label(): string
       {
           return match ($this) {
               self::Lecture => 'Palestra',
               self::Workshop => 'Oficina',
               self::ShortCourse => 'Minicurso',
               self::AcademicWeek => 'Semana acadêmica',
           };
       }

       /** @return array<int, string> */
       public static function values(): array
       {
           return array_column(self::cases(), 'value');
       }
   }
   ```

3. `EV`: `Validations::inclusion('kind', $this, EventKind::values())`;
   método `kindLabel(): string` com
   `EventKind::tryFrom((string) $this->kind)?->label() ?? (string) $this->kind`.
4. Views: `foreach (EventKind::cases() as $kind)` com `$kind->value` e
   `$kind->label()` no `FORM`; `$event->kindLabel()` em `IDX`/`SHOW`.
   Repita para `EventModality`. Pode manter `Event::KINDS` como atalho
   gerado (`array_combine(EventKind::values(), array_map(...))`) para não
   quebrar o resto de uma vez.

Armadilhas:

- `from()` lança `ValueError` com valor desconhecido (dado velho no
  banco): use `tryFrom()` na exibição.
- Enum não serve como chave de array e não é `string`: compare
  `$event->kind === EventKind::Lecture->value`, não com o case.
- O `fillEventForm` dos Cests seleciona pelo rótulo
  (`BaseEventsCest.php:130-131`): os rótulos têm de continuar iguais.
- Classe nova em `app/`: o PSR-4 resolve sem `dump-autoload`, mas se
  alguém rodou `composer dump-autoload -o` (classmap) é preciso rodar
  `./run composer dump-autoload` de novo.
- `phpcs` PSR-12 aceita `enum`; `phpstan` nível 6 também. `match` sem
  `default` é proposital: o `phpstan` avisa quando falta um case.

Prova: `EventTest`; no navegador, formulário, listagem e `show`
inalterados para o usuário.

### M9. Slug único gerado no backend

Chance: média. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/m9-slug-unico-gerado`.

Pedido típico: "o Evento ganha um identificador legível (`slug`) gerado
a partir do título, único, usado na URL `/events/{slug}`". Mesmo padrão
de P1 (valor gerado e validado no servidor), com texto em vez de número.

Arquivos: `SCH:21-38`, `EV:48-58`, `EV:60-82`, `MODEL:139-187` (`save()`
a sobrescrever, como em P1), `MODEL:335` (`findBy`), `MODEL:347`
(`exists`), `lib/StringUtils.php:5-27` (lugar do `slugify`),
`tests/Unit/Lib/StringUtilsTest.php`, `ROUTES:26-35` (admin continua por
`id`), O2 (`/events/{id}` vira `/events/{slug}`), `core/Router/Route.php:77-100`
(o parâmetro de rota aceita qualquer texto), `SHOW`, `EVT:302-308`.

Passos:

1. Testes: `StringUtilsTest::test_slugify` (`'Semana Acadêmica 2026!'`
   -> `semana-academica-2026`); `EVT`: `save()` gera `slug`, dois títulos
   iguais geram `x` e `x-2`, editar o título não troca o `slug`.
2. `lib/StringUtils.php`:

   ```php
   public static function slugify(string $text): string
   {
       $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
       $slug = strtolower(trim((string) $ascii));
       $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

       return trim($slug, '-');
   }
   ```

3. `SCH`: `slug VARCHAR(255) NOT NULL` + `UNIQUE KEY uq_events_slug (slug)`.
   `EV`: `'slug'` em `$columns`; `save()` sobrescrito gera quando
   `newRecord()` e `slug` vazio: `$base = StringUtils::slugify($title)`,
   e enquanto `self::exists(['slug' => $slug])` acrescenta `-2`, `-3`...
   Em `validates()`: `notEmpty('slug') && uniqueness('slug')` (com a
   guarda de P1 para registro novo).
4. Rota do Participante (O2): `Route::get('/events/{slug}', ...)` e no
   controller `Event::findBy(['slug' => $slug])` com `is_string`.
   Admin continua por `id`.
5. `SHOW`: mostre a URL pública.

Armadilhas:

- `/events/{slug}` e `/events/{id}` têm a mesma forma: o Router casa a
  primeira declarada (`Route.php:77-100` só compara o número de partes).
  Fique com uma.
- `iconv` com `//TRANSLIT` depende do locale no Alpine/Debian; a imagem
  `php:8.3.4-fpm` tem `iconv`, mas teste `'ç'`, `'ã'`. Alternativa sem
  `iconv`: um mapa de acentos (`str_replace`).
- Título só com símbolos vira slug vazio: caia em `'evento'` + id ou
  recuse em `validates()`.
- Trocar o slug ao editar quebra links já divulgados: gere só na
  criação (diga isso ao professor).
- `lib/` fora do `phpstan`: tipe tudo mesmo assim; `preg_replace` pode
  devolver `null`, por isso o `?? ''`.

Prova: `StringUtilsTest`, `EventTest`; no navegador, `/events/<slug>`
abre o detalhe e o admin vê a URL no `show`.

### M10. Campo que não muda depois de haver inscrições

Chance: média. Tempo: 15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/m10-campo-imutavel-com-inscricoes`.

Pedido típico: "depois que alguém se inscreve, a data (ou o tipo, ou a
modalidade) do Evento não pode mais mudar". Regra entre o registro
original e o novo, no model.

Arquivos: `EV:84-99` (`validatesStartsOnNotPast`, que já compara com o
original via `findById`), `EV:104-115` (modelo de regra "só na edição"),
`EV:142-145` (`hasEnrollments`), `FORM:70-75`, `EVT:499-547` (testes com
`createPastEvent`/edição), `EVT:580-632` (regra com Inscrições),
`ECT:191-240`.

Passos:

1. Testes em `EVT`: Evento com `enroll(1)` e `starts_on` alterada ->
   inválido com "não pode mudar depois que há inscrições!"; sem
   Inscrições pode; com Inscrições e mesma data pode.
2. `EV`:

   ```php
   private function validatesFrozenFieldsWithEnrollments(): void
   {
       if ($this->newRecord() || !$this->hasEnrollments()) {
           return;
       }

       $original = self::findById($this->id);

       foreach (['starts_on', 'kind'] as $field) {
           if ($original !== null && $original->$field !== $this->$field) {
               $this->addError(
                   $field,
                   'não pode mudar depois que há inscrições!'
               );
           }
       }
   }
   ```

   chamado no fim de `validates()`.
3. `FORM`: `readonly` nos campos congelados quando
   `$event->hasEnrollments()` (só visual; a regra é a do model).
4. `ECT`: `update` com data nova em Evento com `enroll()` -> form com o
   erro e registro intacto.

Armadilhas:

- `readonly` **envia** o valor; `disabled` **não envia**: com
  `disabled`, `fill()` recebe `null` e o `notEmpty` acusa campo vazio.
  Use `readonly` (e o `select` não tem `readonly`: envie num `hidden`).
- `validatesStartsOnNotPast` (`EV:91`) já carrega o original: carregue
  uma vez e reaproveite para não dobrar consultas.
- Comparação de `starts_on`: as duas vêm como `Y-m-d` (string), ok;
  `capacity` viria `int` do banco e `string` do form: compare com
  `(string)` dos dois lados.
- O professor da 4.2 cobrou exatamente "detecção de alteração" (grupo
  5): compare com o original do banco, não com o que o form diz que era.

Prova: `EventTest`, `EventsControllerTest`; no navegador, Evento 1 do
populate (3 inscritos) não aceita nova data.

### M11. Validar `user_id` no model

Chance: média. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/m11-validar-user-id`.

Pedido típico: "o Evento só pode pertencer a um usuário existente e
administrador; hoje um `user_id` inválido estoura a FK (500)".

Arquivos: `EV:60-82`, `SCH:35-36` (FK que hoje gera `PDOException`),
`US:66-74` (`findById` via `Model`, `isAdmin`), `EVT:41-54` (`params()`
traz `user_id` do admin), `EVT:86-114` (`user_id` não está na lista de
obrigatórios), `ECT:101-112` (o controller já ignora `user_id` do form).

Passos:

1. Testes em `EVT`: `user_id` nulo -> erro; id inexistente -> erro;
   id de Participante -> erro; admin -> válido.
2. `EV::validates()`:

   ```php
   Validations::notEmpty('user_id', $this) && $this->validatesUserIsAdmin();
   ```

   ```php
   private function validatesUserIsAdmin(): void
   {
       $id = filter_var($this->user_id, FILTER_VALIDATE_INT);
       $user = $id === false ? null : User::findById($id);

       if ($user === null || !$user->isAdmin()) {
           $this->addError('user_id', 'deve ser um administrador existente!');
       }
   }
   ```

3. Genérico (bônus de framework): `Validations::existence($attribute,
   $obj, string $modelClass)` em `VAL`, com `$modelClass::findById`.

Armadilhas:

- `user_id` não está no form: o erro não tem campo para aparecer; mostre
  `errors('user_id')` no flash `danger` ou como erro de `base`.
- `test_empty_event_should_have_error_in_every_field` (`EVT:86-96`) e o
  `requiredFieldsProvider` (`EVT:101-114`) não listam `user_id`:
  acrescente, ou o teste novo é o único a cobrir.
- A FK (`SCH:35-36`) continua como última barreira; a validação evita
  o 500 antes.
- Nada de `(int)`: com M15 o `user_id` vem do form, e `(int) '1abc'`
  vira 1. `filter_var(..., FILTER_VALIDATE_INT)` devolve `false`.
- Caso real: `EC:53` usa `currentUser()?->id`; sem login, `user_id`
  fica `null`, `validates()` não reclama (`EV:60-82`) e o `INSERT` falha
  em `SCH:23` (`NOT NULL`), 500. Na rota, os middlewares `auth` e
  `admin` (`ROUTES:18-22`) barram antes; no teste de controller (sem
  middleware) basta não chamar `Auth::login` (`ECT:30`) para ver a
  `PDOException`.

Prova: `EventTest`; populate continua (todos com `$admin->id`).

### M12. Último editor gravado da sessão

Chance: média-baixa. Tempo: 20-25 min.

Exemplo pronto: `fvp/4.2/exemplos/m12-ultimo-editor`.

Pedido típico: "registre quem fez a última alteração do Evento
(`updated_by`) e mostre no detalhe; o valor vem do usuário logado, nunca
do formulário".

Arquivos: `SCH:21-38`, `EV:48-58`, `EV:117-120` (`user()`, modelo de
`BelongsTo`), `EC:77-94` (`update`), `EC:14` (`FIELDS`: **não** entra),
`SHOW:6-33`, `ECT:101-112` (modelo de "ignora campo do form"),
`ECT:191-207`.

Passos:

1. Testes em `ECT`: `update` grava `updated_by` igual ao admin logado e
   ignora `'updated_by' => '999'` vindo do form; `create` deixa `null`.
2. `SCH`: `updated_by INT NULL` + `FOREIGN KEY (updated_by) REFERENCES
   users (id) ON DELETE SET NULL`.
3. `EV`: `'updated_by'` em `$columns`, `@property int|null`,
   `editor(): BelongsTo` (`belongsTo(User::class, 'updated_by')`) e
   `editorName(): string` (`null` -> `''`; `instanceof User`).
4. `EC::update`, antes do `save()`:
   `$event->updated_by = $this->currentUser()?->id;`.
5. `SHOW`: "Última alteração por" quando houver.

Armadilhas:

- `BelongsTo::get()` devolve `Model` e lança erro se a FK for `null`:
  cheque `$this->updated_by !== null` antes de chamar.
- `save()` grava `NULL` na criação (coluna nullable): ok.
- Para ver dois nomes diferentes no navegador é preciso um 2º admin
  (T1/Q1).
- Variante "data da alteração": `updated_at` já existe no schema
  (`SCH:33`), mas não está em `$columns` (R5).

Prova: `EventsControllerTest`; no navegador, editar e ver o nome.

### M13. Montagem do Evento no model ou em um service

Chance: média-baixa. Tempo: 10-20 min.

Exemplo pronto: `fvp/4.2/exemplos/m13-fill-no-model`.

Pedido típico: "tire a lógica de montar o Evento do controller
(`fill`/`FIELDS`) e leve para o model ou para um service" (issues da
4.2: "muita lógica no controller").

Arquivos: `EC:14` (`FIELDS`), `EC:116-130` (`fill`), `EC:49-63` e
`EC:77-94` (chamadas), `EV:198-206` (`setAudienceList`), `ECT:101-138`
(os testes de create inválido e de `user_id` ignorado continuam
valendo), `composer.json:7-14` (`App\` -> `app/`, para um
`App\Services`).

Passos:

1. Teste em `EVT`: `fillFromForm(['title' => 'x', 'user_id' => 9,
   'audience' => ['students']])` define `title`, ignora `user_id` e
   monta `audience`; valores array em campo escalar viram `null`.
2. `EV`:

   ```php
   /** Campos que o formulário pode definir (nunca user_id nem id). */
   public const FORM_FIELDS = [
       'title', 'description', 'kind', 'modality',
       'starts_on', 'location', 'capacity',
   ];

   /** @param array<string, mixed> $params */
   public function fillFromForm(array $params): void
   {
       foreach (self::FORM_FIELDS as $field) {
           $value = $params[$field] ?? null;
           $this->$field = is_scalar($value) ? (string) $value : null;
       }

       $audience = $params['audience'] ?? [];
       $this->setAudienceList(
           is_array($audience) ? array_filter($audience, 'is_scalar') : []
       );
   }
   ```

3. `EC`: `fill()` vira duas linhas: valida a forma do payload
   (`is_array`) e chama `$event->fillFromForm($params)`. Remova
   `FIELDS` do controller.
4. Variante service: `app/Services/EventForm.php` com
   `apply(Event $event, mixed $params): void` e teste unitário próprio.

Armadilhas:

- A checagem `is_array($params)` (forma do payload) fica no controller:
  "validar a forma no limite HTTP" (avaliação 4.1 do grupo 2); a regra
  de quais campos entram fica no model.
- `?? null` aqui é sobre campo **opcional de presença** (o form pode não
  enviar) e o `notEmpty` do model cobra em seguida; não é o `?? ''`
  condenado nas issues. Saiba explicar.
- Linha da constante passa de 80 colunas; o limite do projeto é 130.
- Não exponha `fillFromForm` a `user_id`: `ECT:101-112` tem de continuar
  verde.

Prova: `EventTest` novo e `EventsControllerTest` inalterado.

### M14. Contador de visualizações com `Model::update()`

Chance: média-baixa. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/m14-contador-visualizacoes`.

Pedido típico: "conte quantas vezes cada Evento foi visto e mostre na
listagem". Bom para explicar `update()` (grava sem validar, `MODEL:189-213`)
contra `save()`.

Arquivos: `SCH:21-38`, `EV:48-58` (coluna em `$columns`; padrão `0` no
construtor, `US:28-35`, porque `save()` grava todas as colunas),
`MODEL:189-213` (`update()`: `rowCount() !== 0`, e sincroniza
`$this->$column`), `EC:29-39` (`show`), `IDX:11-26`, `SHOW:6-33`,
`ECT:151-161`, `EVT:325-357`.

Passos:

1. Testes: `EVT`: `registerView()` duas vezes -> `findById` traz `2`;
   Evento novo nasce com `0`. `ECT`: `show` incrementa.
2. `SCH`: `views_count INT UNSIGNED NOT NULL DEFAULT 0`. `EV`:
   `'views_count'` em `$columns`, construtor com
   `if ($this->views_count === null) { $this->views_count = 0; }`.
3. `EV`, com `update()` (o que o título pede):

   ```php
   public function registerView(): void
   {
       if ($this->newRecord()) {
           return;
       }

       $this->update(['views_count' => (int) $this->views_count + 1]);
   }
   ```

   `update()` já atualiza o atributo em memória (`MODEL:206-209`). Ele
   lê, soma e grava: dois acessos ao mesmo tempo perdem um. Variante
   atômica, no SQL:

   ```php
   public function registerView(): void
   {
       if ($this->newRecord()) {
           return;
       }

       $stmt = Database::getDatabaseConn()->prepare(
           'UPDATE events SET views_count = views_count + 1 WHERE id = :id'
       );
       $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);
       $stmt->execute();
       $this->views_count = (int) $this->views_count + 1;
   }
   ```

4. `EC::show`: `$event->registerView();` antes do `render`. `IDX`:
   coluna "Visualizações".

Armadilhas:

- Coluna nova sem padrão no construtor: `save()` manda `NULL` em
  `NOT NULL` -> erro (`MODEL:143-158`).
- `update()` **não valida** (`MODEL:189`): é o que se quer aqui, e é a
  diferença para `save()` que o professor pergunta.
- `update()` devolve `false` quando nada mudou (`rowCount() === 0`,
  `MODEL:212`): não use o retorno como "deu erro".
- Efeito colateral em GET (contar ao exibir): aceitável para contador;
  não faça o mesmo para regras de negócio.
- A view do admin conta a visita do próprio admin; se o pedido for
  "visualizações de Participantes", conte só em O2.

Prova: testes; no navegador, abrir o `show` duas vezes e ver `2` na
listagem.

### M15. Escolher o responsável entre os administradores

Chance: média-baixa. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/m15-responsavel-admin`.

Pedido típico: "ao cadastrar, escolher qual administrador é o
responsável pelo Evento". Inverte a regra atual (`user_id` nunca vem do
form, `ECT:101-112`): agora vem, **validado no model** (M11) e com a
lista restrita a admins.

Arquivos: `EC:14` (`FIELDS`, `user_id` entra), `EC:41-47` (`new`: padrão
= admin logado), `EC:116-130` (`fill`), `EV:60-82` (validação de M11),
`US:19-20`, `FORM:35-44` (select, modelo), `SHOW` (R2 mostra o nome),
`ECT:101-112` (teste a inverter), `POP:12-19` (segundo admin, T1).

Passos:

1. Testes: `ECT`: `create` com `user_id` de outro admin grava esse dono;
   com `user_id` de Participante ou inexistente volta ao form com erro;
   sem `user_id` usa o logado. Inverta `ECT:101-112` (agora o form
   manda).
2. `US`, com `@return array<static>`:

   ```php
   public static function admins(): array
   {
       return self::where(['role' => 'admin']);
   }
   ```

3. `EC`: `user_id` **fora** de `FIELDS`, porque o `fill` grava `null` no
   campo ausente (`EC:123-126`); um bloco próprio troca o responsável só
   quando o form manda. Em `new()`,
   `$event->user_id = $this->currentUser()?->id;`.
4. `EV::validates()`: M11 (`user_id` existe e é admin), mensagem no
   campo.
5. `FORM`: `<select name="event[user_id]">` com `User::admins()`,
   `selected` no atual. `SHOW`: nome do responsável (R2).

Armadilhas:

- Abrir `user_id` ao form sem M11 é a falha que a issue 4.2 do grupo 6
  aponta (recurso pai sem checar); a validação no model é obrigatória.
- Q1/O5 (dono edita) mudam de sentido: quem é "dono", o criador ou o
  responsável? Decida e diga.
- `ECT:101-112` passa a falhar por definição: troque a asserção, não
  apague o teste.
- Populate com um admin só mostra um `select` de uma opção: T1.
- Participante não chega a esse form (grupo `admin`), mas a validação
  cobre quem forjar o POST.

Prova: testes; no navegador, criar Evento para `admin2@` (T1) e ver o
nome no `show`.

### M16. Gancho `beforeValidation` no core (espaços nas pontas)

Chance: baixa. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/m16-before-validation`.

Pedido típico: "tire os espaços das pontas antes de validar". Hoje um
título só com espaços passa: `Validations::notEmpty` só recusa `null` e
`''` (`VAL:9-17`), e o `fill` copia o texto cru (`EC:116-130`).

Arquivos: `MODEL:103-110` (`isValid`), `MODEL:139-141` (`save` chama
`isValid`), `MODEL:189-213` (`update` não valida), `EV:60-82`,
`VAL:9-17`, `EVT`.

Passos:

1. Testes em `EVT`: título `'   '` -> inválido, erro em `title`; título
   `' Oficina '` -> `save()` grava `'Oficina'` (confira com `findById`).
   Um caso para `description` e `location`.
2. `Model`:

   ```php
   public function isValid(): bool
   {
       $this->errors = [];
       $this->beforeValidation();
       $this->validates();

       return empty($this->errors);
   }

   /** Gancho: a subclasse normaliza atributos antes das validações. */
   protected function beforeValidation(): void
   {
   }
   ```

3. `Event`:

   ```php
   protected function beforeValidation(): void
   {
       foreach (['title', 'description', 'location'] as $field) {
           if (is_string($this->$field)) {
               $this->$field = trim($this->$field);
           }
       }
   }
   ```

Armadilhas:

- `update()` não passa por `isValid` (`MODEL:189`): o gancho vale só
  para `save()`. Diga isso se o professor perguntar.
- Só campos de texto livre. Não leve o gancho para o `User` sem pensar
  na senha (espaço na senha é legítimo).
- `trim` não tira espaço não separável (U+00A0, comum em texto colado);
  se o professor testar isso, use `preg_replace('/^\s+|\s+$/u', '', $v)`.
- Alternativa sem core: `trim` no `fill` (`EC:125`). Funciona, mas é
  regra no controller; o gancho deixa a regra no model e serve a
  qualquer model.
- O gancho altera o objeto mesmo quando a validação falha: o form
  volta com o valor já aparado (bom, mas saiba explicar).

Prova: `EVT`; no navegador, título "   " mostra "não pode ser vazio!".

### M17. Campo sim/não por checkbox (certificado)

Chance: média-alta. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/m17-checkbox-certificado`.

Forma do exemplo pronto: O exemplo usa `has_certificate` e normaliza `null` e
`''` para `'0'` no fim de `validates()`, antes do `inclusion(['0', '1'])`, sem
padrão no construtor: desmarcado nunca dá erro, nem num POST sem o campo.

Pedido típico: "marque se o Evento emite certificado" ou "campo sim/não
no formulário". Hoje não há coluna booleana. O erro clássico: checkbox
desmarcado não vai no POST, o `fill` grava `null` (`EC:124`), o
`save()` faz o bind de todas as colunas (`MODEL:153-155`) e o MySQL
recusa: `1048 Column cannot be null` -> 500.

Arquivos: `SCH:31` (coluna nova depois de `capacity`), `EV:48-58`
(`$columns`), `EV:60-82` (`validates`), `US:28-35` (padrão no
construtor, molde), `EC:14` (`FIELDS`), `EC:123-126` (`?? null` e
`(string)`), `VAL:34-44` (`inclusion` com `in_array` estrito,
`VAL:38`), `FORM:58-68` (checkboxes de público-alvo, molde),
`FORM:84-89`, `SHOW:28-32`, `POP:133-149`, `ECT:272-284`
(`validParams`), `ACT/BaseEventsCest.php:122-155` (`fillEventForm`).

Passos:

1. Testes. `EVT`: `new Event($this->params())` sem `certificate` ->
   válido e grava `0`; `'1'` -> `findById` devolve `1` (int); `'2'` ->
   `não é uma opção válida!` (`''` e `null` também, se atribuídos depois
   do construtor; na forma do exemplo viram `0`); Evento lido do banco com
   `0` e salvo de novo -> válido. `ECT`: create com `'1'` ->
   `assertSame(1, $event->certificate)`; update de `1` para `'0'` ->
   `0`; edit de Evento com `1` ->
   `/id="event_certificate"[^>]*checked/`. Cest: desmarcar no editar,
   salvar e ver "Não" no `show`.
2. `SCH:31`, depois de `capacity`:

   ```php
   certificate TINYINT(1) NOT NULL DEFAULT 0,
   ```

3. `Event`: `'certificate'` em `$columns` (`EV:48-58`), padrão no
   construtor e validação:

   ```php
   /** @param array<string, mixed> $params */
   public function __construct($params = [])
   {
       parent::__construct($params);

       if ($this->certificate === null) {
           $this->certificate = 0;
       }
   }

   // em validates(): o banco devolve int, o form devolve string
   Validations::inclusion('certificate', $this, [0, 1, '0', '1']);

   public function hasCertificate(): bool
   {
       return in_array($this->certificate, [1, '1'], true);
   }
   ```

4. `EC:14`: `'certificate'` em `FIELDS`. O `fill` não muda.
5. `FORM`, novo bloco antes de `Vagas` (hidden **antes** do checkbox):

   ```php
   <div class="mb-3 form-check">
       <input type="hidden" name="event[certificate]" value="0">
       <input type="checkbox"
           class="form-check-input<?= $invalid('certificate') ?>"
           id="event_certificate" name="event[certificate]" value="1"
           <?= $event->hasCertificate() ? 'checked' : '' ?>>
       <label class="form-check-label" for="event_certificate">
           Emite certificado
       </label>
       <?= $feedback('certificate') ?>
   </div>
   ```

6. `SHOW`, depois de `SHOW:32`: `<dt>Certificado</dt>` e
   `<dd><?= $event->hasCertificate() ? 'Sim' : 'Não' ?></dd>`.
7. `ECT:272-284`: `'certificate' => '0'` em `validParams`.
   `BaseEventsCest`: chave `certificate` no `fillEventForm` com
   `checkOption`/`uncheckOption('#event_certificate')`.

Armadilhas:

- Sem o hidden, desmarcar no editar nunca grava `0`: a chave some do
  POST, o `fill` põe `null` e o Evento vira inválido ou dá 500.
- Ordem: o PHP fica com o **último** valor de um nome repetido. Hidden
  depois do checkbox = sempre `0`.
- O `DEFAULT 0` do schema não é usado: `save()` grava todas as colunas
  (`MODEL:139-187`). Sem padrão no construtor nem normalização antes
  do `save()`, `POP:134-143` cria
  Eventos com `null` e lança `RuntimeException` (`POP:145-146`); o
  mesmo vale para `ECT:289-300` e `ACC:30-40`.
- `TINYINT` volta do PDO como **int** (`1`, não `'1'`; PHP 8.3 com
  `pdo_mysql`, conferido). `inclusion(['0', '1'])` é estrito
  (`VAL:38`): todo Evento lido e salvo de novo reprova, por exemplo
  `EVT:511-524` (Evento passado editado). Por isso a lista tem int e
  string.
- `ECT:85-112` posta `validParams()` sem `certificate`: sem o passo 7
  o `fill` põe `null` e os testes de create falham.
- `checked` com `$event->certificate == 1` funciona, mas o professor
  cobra comparação estrita: use o método do model.
- Não converta no controller (`(bool)`, `?? '0'`): `true` não está na
  lista e `'abc'` viraria `true`. A regra fica no model.

Prova: `EVT` e `ECT`; no navegador, criar marcado, editar, desmarcar,
salvar e ver "Não" no `show`.

### M18. Janela de datas: antecedência mínima e limite máximo

Chance: média-alta. Tempo: 15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/m18-janela-de-datas`.

Forma do exemplo pronto: O exemplo estende `notPastDate` com `int $minDays =
0, ?int $maxDays = null` no fim (argumentos nomeados no `Event`, constantes
`STARTS_ON_MIN_DAYS` e `STARTS_ON_MAX_DAYS` = 3 e 365) em vez de uma função
nova, e muda `ACT/BaseEventsCest.php:171` para +7 dias.

Pedido típico: "o Evento precisa de 3 dias de antecedência" ou "não
aceite data a mais de 1 ano". Hoje só existe "não passada"
(`VAL:116-138`, chamada em `EV:98`): hoje é aceito e `9999-12-31`
também (`parseDate`, `VAL:140-153`, aceita qualquer ano de 4 dígitos).

Arquivos: `VAL:116-138` (`notPastDate`, molde com `$now`),
`VAL:129-130` (cálculo de "hoje" no fuso padrão), `EV:75-77`,
`EV:88-99` (regra só no create ou quando a data muda), `FORM:72`,
`FORM:16` (`novalidate`),
`tests/Unit/Lib/Validations/NumberAndDateValidationsTest.php:136-154`
(teste com `$now` fixo), `EVT:220-233`, `EVT:538-547`.

Passos:

1. Testes. `NumberAndDateValidationsTest` com `$now` fixo em
   `2026-11-05 10:00`, mínimo 3 e máximo 365: `2026-11-07` recusa,
   `2026-11-08` aceita, `2027-11-05` aceita, `2027-11-06` recusa,
   `9999-12-31` recusa. `EVT`: hoje -> `deve ter pelo menos 3 dias de
   antecedência!`; `+3 days` válido; `+366 days` inválido; ontem
   continua `não pode estar no passado!`; Evento passado editado sem
   mudar a data continua válido (`EVT:511-524`).
2. `Validations`, ao lado de `notPastDate`:

   ```php
   /**
    * A data deve ficar entre hoje + $minDays e hoje + $maxDays
    * (os extremos valem). $now fixa "hoje" nos testes.
    */
   public static function dateWithinDays(
       $attribute,
       $obj,
       int $minDays,
       int $maxDays,
       string $format = 'Y-m-d',
       ?\DateTimeImmutable $now = null
   ) {
       $date = self::parseDate($obj->$attribute, $format);
       if ($date === null) {
           $obj->addError($attribute, 'não é uma data válida!');
           return false;
       }

       $timezone = new \DateTimeZone(date_default_timezone_get());
       $today = ($now ?? new \DateTimeImmutable())
           ->setTimezone($timezone)
           ->setTime(0, 0);

       if ($date < $today->modify("+{$minDays} days")) {
           $obj->addError(
               $attribute,
               "deve ter pelo menos {$minDays} dias de antecedência!"
           );
           return false;
       }

       if ($date > $today->modify("+{$maxDays} days")) {
           $obj->addError(
               $attribute,
               "não pode passar de {$maxDays} dias a partir de hoje!"
           );
           return false;
       }

       return true;
   }
   ```

3. `Event`: constantes e troca de `EV:98`:

   ```php
   public const MIN_DAYS_AHEAD = 3;
   public const MAX_DAYS_AHEAD = 365;

   // EV:98, no lugar da chamada única
   Validations::notPastDate('starts_on', $this) &&
       Validations::dateWithinDays(
           'starts_on',
           $this,
           self::MIN_DAYS_AHEAD,
           self::MAX_DAYS_AHEAD
       );
   ```

4. `FORM:72` (opcional): `min`/`max` calculados a partir das
   constantes. Só ajuda o seletor do navegador.
5. Ajuste os testes que usam datas fora da janela (armadilhas).

Armadilhas:

- `EVT:228-233` (hoje é válido) passa a falhar: troque por `+3 days`
  e acrescente `+2 days` inválido.
- `ACT/BaseEventsCest.php:171` usa `+1 day` em `validEventData`:
  `CreateSuccessfullyCest` quebra com mínimo maior que 1.
- `EVT:541` usa `+5 days` e `ECT:280`/`ACC:150` usam `+10 days`:
  mínimo acima disso quebra esses testes.
- Populate: datas de `+14` (`POP:53`) a `+82` dias (`POP:128`), via
  `POP:140`. Mínimo acima de 14 ou máximo abaixo de 82 lança
  `RuntimeException` (`POP:145-146`).
- `ACT/UpdateSuccessfullyCest.php:19` usa `+90 days`: máximo abaixo
  de 90 quebra o Cest. 365 cobre tudo.
- Chame `notPastDate` antes: assim data passada mantém a mensagem
  `não pode estar no passado!`, conferida em `EVT:225`, `EVT:534`,
  `EVT:556`, `EVT:564`, `ACT/CreateWithInvalidDataCest.php:54` e
  `ACT/UpdateWithInvalidDataCest.php:39`.
- A janela fica dentro de `EV:88-99`: só vale no create ou quando a
  data muda. Fora dali, um Evento daqui a 2 dias não pode mais ter a
  descrição corrigida.
- "1 ano" não é 365 dias em ano bissexto. Se o professor disser
  "1 ano", use `modify('+1 year')` e teste o dia exato.
- `min`/`max` no HTML não barram nada: o form tem `novalidate`
  (`FORM:16`). A regra vale no model.
- O `$now` serve ao teste unitário da `Validations`; no `Event` use
  datas relativas (`date('Y-m-d', strtotime('+3 days'))`).

Prova: `NumberAndDateValidationsTest`, `EVT`, `ECT` e Cests; no
navegador, data de amanhã mostra a mensagem de antecedência e
`9999-12-31` mostra a do limite.

### M19. "Nenhuma alteração" ao salvar sem mudar nada

Chance: média. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/m19-nenhuma-alteracao`.

Pedido típico: "salvei sem mexer em nada e apareceu 'atualizado com
sucesso'". Hoje o `update` chama `save()` direto (`EC:84-88`), que
roda o `UPDATE` com todas as colunas (`MODEL:160-180`) e devolve
`true` mesmo sem mudança; o flash diz "Evento atualizado com
sucesso!" (`EC:87`).

Arquivos: `EC:77-94` (`update`), `EV:88-99` (molde: compara com o
original de `findById`), `FLASH:7-15` (só `success` e `danger`),
`FLASH:25-28`, `app/views/layouts/_flash_message.phtml:2`
(`alert-<?= $type ?>`), `tests/Unit/Lib/FlashMessageTest.php:10-26`,
`ECT:191-207`, `ECT:272-300` (`validParams`, `createEvent`),
`MODEL:189-213`.

Passos:

1. Testes. `FlashMessageTest`: `test_warning` copiando
   `test_danger`. `EVT`: Evento lido do banco sem mudança ->
   `hasChanges()` falso; `capacity` `'20'` contra `20` do banco ->
   falso; público-alvo em outra ordem -> falso; título novo ->
   verdadeiro; Evento novo -> verdadeiro. `ECT`: `createEvent()` e
   `put` com `validParams()` igual -> `Location` do `show`,
   `$_SESSION['flash']['warning']` com o texto e sem chave `success`;
   mudar só o público-alvo -> `success`.
2. `FLASH`, ao lado de `danger`:

   ```php
   public static function warning(string $value): void
   {
       self::message('warning', $value);
   }
   ```

3. `Event`:

   ```php
   /** Verdadeiro se algum campo difere do que está no banco. */
   public function hasChanges(): bool
   {
       $original = $this->newRecord()
           ? null
           : self::findById($this->id);
       if ($original === null) {
           return true;
       }

       foreach (self::$columns as $column) {
           if ($column === 'audience') {
               continue;
           }
           // o banco devolve int; o form, string
           if ((string) $original->$column !== (string) $this->$column) {
               return true;
           }
       }

       $before = $original->audienceList();
       $after = $this->audienceList();
       sort($before);
       sort($after);

       return $before !== $after;
   }
   ```

4. `EC:84`, depois do `fill`:

   ```php
   $this->fill($event, $request);

   if (!$event->hasChanges()) {
       FlashMessage::warning('Nenhuma alteração foi feita.');
       $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
       return;
   }
   ```

   O resto do `update` não muda.

Armadilhas:

- `capacity` volta do PDO como int (`20`) e do form como `'20'`: com
  `!==` sem `(string)` todo save "tem alteração". O `(string)` aqui só
  compara; não grava nada.
- O SET volta do banco na ordem da definição (`SCH:28`);
  `setAudienceList` guarda a ordem recebida (`EV:198-206`).
  `'staff,students'` contra `'students,staff'` é o mesmo público:
  ordene antes de comparar.
- Não use `rowCount` para isso: `save()` sempre devolve `true`
  (`MODEL:181`) e mudar o core para devolver `false` sem mudança faz
  o controller mostrar "Existem dados incorretos!". O
  `Model::update()` já devolve `false` sem mudança (`MODEL:212`), mas
  não valida.
- `return` depois do `redirectTo`, senão o `save()` roda mesmo assim.
- `FlashMessage` guarda um texto por tipo (`FLASH:27`). `warning`
  vira `alert-warning` sem mudar o partial. Usar `success` com outro
  texto também passa, mas fica verde; `danger` fica vermelho e não é
  erro.
- Uma consulta a mais (`findById`, PDO novo) a cada update. Aceitável;
  diga se o professor perguntar.
- `updated_at` já não muda quando nada muda: o MySQL só aplica o
  `ON UPDATE` se algum valor mudar. O problema era só o flash.
- Testes existentes continuam verdes: todo update de `ECT` e dos Cests
  muda ao menos um campo (`ECT:198`, `ECT:216`, `ECT:235`,
  `ACT/UpdateSuccessfullyCest.php:68,84`).
- Textarea: o navegador manda as quebras de linha como `\r\n`; uma
  descrição gravada com `\n` (testes, `update()` direto) sempre parece
  alterada. Compare com `str_replace("\r\n", "\n", ...)` nos dois lados.

Prova: `FlashMessageTest`, `EVT`, `ECT`; no navegador, abrir Editar,
clicar Salvar sem mexer e ver o alerta amarelo no `show`.

### M20. Preço em reais com vírgula, "Gratuito" se 0

Chance: média. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/m20-preco-em-reais`.

Forma do exemplo pronto: O exemplo usa `price DECIMAL(8, 2) NOT NULL` sem
`DEFAULT`, com `CHECK (price >= 0)`, campo obrigatório e `price` em todas as
fixtures (POP, ACC, `EVT::params`, `ECT::validParams`, `BaseEventsCest`); a
validação é `Validations::decimal($attr, $obj, $digits, $decimals)`.

Pedido típico: "o Evento pode ser pago: aceite 10,50 e mostre
R$ 10,50; se for 0, mostre Gratuito". Hoje não há coluna de preço.
O erro comum: `(float) '10,50'` dá `10.0` (perde os centavos sem
aviso), e gravar `'10,50'` direto num `DECIMAL` dá
`1366 Incorrect decimal value` -> 500.

Arquivos: `SCH:31` (coluna nova), `EV:48-58`, `EV:60-82`, `US:28-35`
(padrão no construtor), `VAL:78-94` (`integerMin`, molde de validação
numérica), `EC:14`, `EC:123-126`, `FORM:84-89`, `SHOW:28-32`,
`IDX:15,26`, `ECT:272-284`, catálogo R3 (helper de formatação).

Passos:

1. Testes. `EVT`: `'10,50'` válido e `findById` devolve `'10.50'`;
   `'10'` -> `'10.00'`; `'999999,99'` válido; `'1000000'`, `'1e3'`,
   `'-5'`, `'10,555'`, `'1.234,56'`, `'abc'` -> erro; `''` ->
   `não pode ser vazio!`. `formattedPrice()`: `'0.00'` -> `Gratuito`;
   `'1234.5'` -> `R$ 1.234,50`. `ECT`: create com `'price' => '25,90'`
   -> `show` contém `R$ 25,90`; edit mostra `value="25,90"`.
2. `SCH`, depois de `capacity`:

   ```php
   price DECIMAL(8,2) NOT NULL DEFAULT 0,
   ```

3. `Validations`:

   ```php
   /**
    * Valor em reais: até $digits dígitos inteiros e até 2 decimais,
    * com vírgula ou ponto ("10", "10,5", "1234.56").
    */
   public static function money($attribute, $obj, int $digits = 6)
   {
       $value = $obj->$attribute;
       $pattern = '/^\d{1,' . $digits . '}([.,]\d{1,2})?$/D';

       if (!is_string($value) || preg_match($pattern, $value) !== 1) {
           $obj->addError($attribute, 'deve ser um valor como 10,50!');
           return false;
       }

       return true;
   }
   ```

4. `Event`: `'price'` em `$columns`; no construtor,
   `if ($this->price === null) { $this->price = '0.00'; }`; em
   `validates()` e os helpers:

   ```php
   if (
       Validations::notEmpty('price', $this) &&
       Validations::money('price', $this)
   ) {
       // formato do MySQL: ponto decimal
       $this->price = str_replace(',', '.', $this->price);
   }

   public function formattedPrice(): string
   {
       $price = (float) $this->price;
       if ($price === 0.0) {
           return 'Gratuito';
       }

       return 'R$ ' . number_format($price, 2, ',', '.');
   }

   public function priceForInput(): string
   {
       return str_replace('.', ',', (string) $this->price);
   }
   ```

5. `EC:14`: `'price'` em `FIELDS`. `FORM`, bloco novo:
   `type="text" inputmode="decimal" id="event_price"
   name="event[price]"`, `value` com
   `htmlspecialchars($event->priceForInput())`.
6. `SHOW`: `<dt>Preço</dt>` com
   `htmlspecialchars($event->formattedPrice())`. `IDX`: coluna nova
   (opcional).
7. `ECT:272-284`: `'price' => '0'` em `validParams`.

Armadilhas:

- `FILTER_VALIDATE_FLOAT` e `is_numeric` aceitam `'1e3'` (vira
  `1000.00` no banco). Use a regex.
- Mais de 2 decimais: o MySQL arredonda sem erro (`'10.555'` grava
  `10.56`). A regex barra.
- `'1000000'` estoura o `DECIMAL(8,2)`: `1264 Out of range` -> 500
  (MySQL estrito, conferido). `\d{1,6}` fecha o limite em
  `999999,99`.
- Nada de `(float)` no controller nem no model antes de validar:
  `(float) '10,50'` é `10.0`. O `(float)` do `formattedPrice` só
  formata um valor já validado.
- `DECIMAL` volta do PDO como string (`'10.50'`): compare com
  `assertSame('10.50', ...)`, nunca `assertSame(10.5, ...)`.
- `type="number"` mostra vírgula em navegador pt-BR, mas com
  `novalidate` (`FORM:16`) um valor que o navegador não entende vai
  vazio. Prefira `type="text"` com `inputmode="decimal"`.
- `save()` grava todas as colunas (`MODEL:139-187`): sem o padrão no
  construtor, `POP:134-143`, `ACC:30-40` e `ECT:289-300` gravam
  `null` e falham.
- `ECT:85-112` posta `validParams()`: sem `price` o `fill` põe
  `null` (`EC:124`) e o create reprova.
- A normalização dentro de `validates()` altera o objeto. Diga isso;
  a alternativa limpa é o gancho do M16.
- Sem o modificador `D`, o `$` da regex aceita uma quebra de linha no
  fim: `preg_match` casa `"10\n"`. Teste esse caso.

Prova: `EVT` e `ECT`; no navegador, cadastrar com `10,5`, ver
`R$ 10,50` no `show`, editar para `0` e ver `Gratuito`.

### M21. Vagas ilimitadas (`capacity` NULL)

Chance: média-baixa. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/m21-vagas-ilimitadas`.

Forma do exemplo pronto: O exemplo converte `''` em `null` no início de
`validates()`, mostra "N (vagas ilimitadas)" com if/else nas views e uma dica
`form-text` no form, sem `capacityLabel()`.

Pedido típico: "Evento online não tem limite de vagas". Hoje
`capacity` é obrigatório (`EV:79`, `SCH:31` `NOT NULL`). Tirar só o
`notEmpty` não basta: o `integerMin` (`VAL:81-85`) recusa `''`. O 500
(`1366 Incorrect integer value`) aparece se a guarda tratar `''` como
ilimitado sem convertê-lo para `null`.

Arquivos: `SCH:31` (`capacity INT NOT NULL`), `SCH:34`
(`CHECK (capacity >= 1)`, aceita NULL), `EV:20` (PHPDoc), `EV:79-81`,
`EV:104-115` (`validatesCapacityAgainstEnrollments`, `(int)` em
`EV:112`), `EV:137-140` (`availableSpots`, `(int)` em `EV:139`),
`IDX:26`, `SHOW:30-31`, `FORM:84-89`, `POP:58`, `EVT:86-123`,
`ACT/CreateWithInvalidDataCest.php:23-25`.

Passos:

1. Testes. `EVT`: `capacity` `''` -> válido, `save()` e `findById`
   devolve `null`; `availableSpots()` -> `null`, mesmo com Inscrições;
   `'0'` continua `deve ser maior ou igual a 1!`; de ilimitado para
   `2` com 3 Inscrições -> `não pode ser menor que o total de
   inscrições (3)!`; de `5` para `''` com Inscrições -> válido.
   `ECT`: create com `'capacity' => ''` -> `show` sem `disponíveis`
   e com `sem limite`.
2. `SCH:31`: `capacity INT NULL,`. O `CHECK` de `SCH:34` fica.
3. `Event` (`EV:20` vira `int|string|null`):

   ```php
   public function hasUnlimitedCapacity(): bool
   {
       return $this->capacity === null || $this->capacity === '';
   }

   public function save(): bool
   {
       if ($this->capacity === '') {
           $this->capacity = null; // '' num INT estrito: erro 1366
       }

       return parent::save();
   }

   // EV:79-81
   if (
       !$this->hasUnlimitedCapacity() &&
       Validations::integerMin('capacity', $this, 1)
   ) {
       $this->validatesCapacityAgainstEnrollments();
   }

   /** Vagas restantes; null quando não há limite. */
   public function availableSpots(): ?int
   {
       if ($this->hasUnlimitedCapacity()) {
           return null;
       }

       return max(0, (int) $this->capacity - $this->enrollmentsCount());
   }

   public function capacityLabel(): string
   {
       return $this->hasUnlimitedCapacity()
           ? 'sem limite'
           : (string) $this->capacity;
   }
   ```

4. Views: `IDX:26` e `SHOW:30` trocam `(int) $event->capacity` por
   `htmlspecialchars($event->capacityLabel())`; `SHOW:31` só mostra
   `(N disponíveis)` quando `availableSpots() !== null`. `FORM:85`:
   rótulo "Vagas (em branco = sem limite)".
5. `POP:58`: o Evento online pode ganhar `null` para aparecer na
   demonstração.
6. Variante "só online pode ser ilimitado": no `validates`, se
   `modality !== 'online'` mantenha o `notEmpty` (catálogo M5).

Armadilhas:

- `(int) null` é `0`: sem trocar `EV:139`, `IDX:26` e `SHOW:30` a tela
  mostra `1/0` e `(0 disponíveis)`, e um badge de lotado (N6) acende.
- `availableSpots()` passa a devolver `?int`: em N6 use
  `=== 0`, nunca `!$event->availableSpots()` (`null` também é falso).
- Sem a guarda em `EV:79-81`, `validatesCapacityAgainstEnrollments`
  faz `(int) null < $enrolled` (`EV:112`) e recusa todo Evento
  ilimitado com Inscrições.
- Testes que mudam: `EVT:93` (Evento vazio esperava erro em
  `capacity`), `EVT:111` (caso `capacity` do provider de obrigatórios)
  e `ACT/CreateWithInvalidDataCest.php:23-25` (form vazio esperava
  `não pode ser vazio!` em `capacity`). Tire `capacity` dessas listas.
- Campo vazio virar "sem limite" é uma regra: deixe escrito no rótulo.
  Se o professor achar implícito demais, use um checkbox "Sem limite"
  com hidden (M17).
- O `CHECK (capacity >= 1)` aceita NULL (no SQL, NULL não reprova
  `CHECK`): não precisa mexer em `SCH:34`.

Prova: `EVT`, `ECT` e Cests; no navegador, cadastrar com Vagas em
branco e ver `sem limite` na listagem e no `show`.

### M22. No máximo N Eventos por dia

Chance: média-baixa. Tempo: 20-25 min.

Exemplo pronto: `fvp/4.2/exemplos/m22-limite-por-dia`.

Pedido típico: "no máximo 3 Eventos no mesmo dia". Hoje não há limite:
qualquer quantidade de Eventos na mesma data passa (`EV:75-77` só
olha formato e passado).

Arquivos: `EV:60-82` (`validates`), `EV:75-77`, `EV:88-99` (molde:
regra só no create ou quando a data muda), `MODEL:300-330` (`where`,
só igualdade), `ECT:39-57`, `ECT:289-300` (`createEvent` ignora o
retorno de `save()`), `EVT:49-53` (`params` com `+30 days`),
`EVT:390-429` (paginação), N11 (`count`).

Passos:

1. Testes. `EVT`: três Eventos no mesmo dia salvam; o quarto ->
   `já tem 3 eventos neste dia, escolha outra data!` em `starts_on`;
   outro dia -> válido;
   editar o título de um dos três -> válido (não conta a si mesmo);
   mover um Evento de outro dia para o dia cheio -> inválido. `ECT`:
   create no dia cheio -> form com o erro e `Event::all()` com 3.
2. `Event`:

   ```php
   private const MAX_EVENTS_PER_DAY = 3;

   private function validatesEventsPerDay(): void
   {
       $others = array_filter(
           self::where(['starts_on' => $this->starts_on]),
           fn(Event $other) => $other->id !== $this->id
       );

       if (count($others) >= self::MAX_EVENTS_PER_DAY) {
           $max = self::MAX_EVENTS_PER_DAY;
           $this->addError(
               'starts_on',
               "já tem {$max} eventos neste dia, escolha outra data!"
           );
       }
   }
   ```

3. `EV:75-77` vira:

   ```php
   if (
       Validations::notEmpty('starts_on', $this) &&
       Validations::validDate('starts_on', $this)
   ) {
       $this->validatesStartsOnNotPast();

       if ($this->errors('starts_on') === null) {
           $this->validatesEventsPerDay();
       }
   }
   ```

4. Ajuste os testes que criam vários Eventos na mesma data
   (armadilhas): data variando no laço, por exemplo
   `'starts_on' => date('Y-m-d', strtotime('+' . (10 + $i) . ' days'))`.

Armadilhas:

- Sem excluir o próprio `id`, editar a descrição de um dos três
  Eventos do dia reprova (ele conta a si mesmo).
- `ECT:41-43` cria 7 Eventos com a mesma data (`ECT:280`). O
  `createEvent` ignora o `false` do `save()` (`ECT:297`): só 3 entram
  e o teste falha mais adiante ("Evento 5" não aparece), longe da
  causa.
- `EVT:392-394` (12 Eventos) e `EVT:417-419` (7) usam a data de
  `params()`: o `createEvent` do `EVT` faz `assertTrue($event->save())`
  (`EVT:62`) e falha no quarto. Com N = 2 também quebram `EVT:368-370`
  e `EVT:454-456` (três Eventos cada).
- `addError` guarda um texto por campo (`MODEL:131-134`): rode a regra
  só se `starts_on` não tiver erro, senão ela apaga
  `não pode estar no passado!`.
- Se o limite baixar com Eventos já gravados, um dia com 4 deixa todos
  ineditáveis. Para evitar, rode a regra só no create ou quando a data
  muda, como em `EV:90-96`.
- Populate e Cests não quebram: as 16 datas de `POP:49-130` e de
  `ACT/BaseEventsCest.php:70` são todas diferentes.
- `count(where(...))` traz as linhas inteiras; com N11 vira
  `SELECT COUNT(*)`. A consulta fica no model, nunca no controller.
- Dois cadastros simultâneos podem passar do limite (sem trava). Só
  comente se perguntarem.

Prova: `EVT` e `ECT`; no navegador, cadastrar o quarto Evento na mesma
data e ver o erro no campo Data.

### M23. Data digitada como dd/mm/aaaa

Chance: baixa. Tempo: 20-25 min.

Exemplo pronto: `fvp/4.2/exemplos/m23-data-dd-mm-aaaa`.

Forma do exemplo pronto: O exemplo marca a data vinda do form
(`setStartsOnInput()`): só ela é validada em `d/m/Y` e convertida no início de
`validates()`; `Y-m-d` vindo do form é recusado, e o que vem do código ou do
banco segue `Y-m-d`. `starts_on` sai de `EC::FIELDS` (`EC:14`, `EC:116-130`).
Mudam `ECT:80`, `ACC:150`, `ACT/BaseEventsCest.php:148-153` e
`UpdateSuccessfullyCest:37`, e entra um Cest em `CreateWithInvalidDataCest`;
`EVT:212` não muda.

Pedido típico: "quero digitar a data como dd/mm/aaaa". Hoje o campo é
`type="date"` (`FORM:72`): o navegador em pt-BR já **mostra**
dd/mm/aaaa, mas sempre **envia** `Y-m-d`. Pergunte se isso basta. Se
o pedido for campo de texto, `05/03/2027` chega como veio e reprova
em `validDate` (`EV:76`, formato `Y-m-d` em `VAL:99`); gravado direto,
o MySQL recusa (`1292 Incorrect date value`) -> 500.

Arquivos: `FORM:70-75`, `EV:75-77`, `EV:88-99` (`EV:93` compara o
original `Y-m-d` com o novo valor), `VAL:99-107` e `VAL:140-153`
(`validDate`/`parseDate` com `$format`), `MODEL:153-155` (bind),
`IDX:24` e `SHOW:23` (exibem com `date('d/m/Y', strtotime(...))`),
`POP:140` (`Y-m-d`), `EVT:210-218`, `ECT:80`,
`ACT/BaseEventsCest.php:148-153`,
`ACT/UpdateSuccessfullyCest.php:37`.

Passos:

1. Testes. `EVT`: `'05/03/2031'` -> válido e `findById` devolve
   `'2031-03-05'`; `'2031-03-05'` continua válido; `'31/02/2031'`,
   `'5/3/2031'`, `'05/03/31'` -> `não é uma data válida!`; Evento
   passado editado sem mudar a data, com ela vinda como dd/mm/aaaa ->
   válido. `ECT`: edit mostra `value="05/03/2031"`; create com
   `'starts_on' => '05/03/2031'` grava `2031-03-05`.
2. `Event`:

   ```php
   /**
    * Aceita dd/mm/aaaa e guarda Y-m-d (formato do DATE). Valor que
    * não casa fica como veio, para a validação recusar.
    */
   private function normalizeStartsOn(): void
   {
       if (!is_string($this->starts_on)) {
           return;
       }

       $date = \DateTimeImmutable::createFromFormat(
           '!d/m/Y',
           $this->starts_on
       );
       if ($date !== false && $date->format('d/m/Y') === $this->starts_on) {
           $this->starts_on = $date->format('Y-m-d');
       }
   }

   /** Data para o campo do form: dd/mm/aaaa quando já é Y-m-d. */
   public function startsOnForInput(): string
   {
       $value = (string) $this->starts_on;
       $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

       return $date !== false && $date->format('Y-m-d') === $value
           ? $date->format('d/m/Y')
           : $value;
   }
   ```

3. `validates()`: `$this->normalizeStartsOn();` na primeira linha,
   antes de `EV:75`.
4. `FORM:72-73`: `type="text" placeholder="dd/mm/aaaa"
   inputmode="numeric"` e `value` com
   `htmlspecialchars($event->startsOnForInput())`.
5. Ajuste os testes que mudam (armadilhas).

Armadilhas:

- Converta **antes** da validação. Se a conversão vier depois,
  `EV:93` compara `'2026-10-01'` com `'01/10/2026'`, acha que a data
  mudou e um Evento passado não pode mais ter a descrição corrigida
  (`não pode estar no passado!`).
- Continue aceitando `Y-m-d`: o populate monta `Y-m-d` (`POP:140`) e o
  Cest preenche o campo com valor ISO por JS
  (`ACT/BaseEventsCest.php:148-153`).
- `strtotime('05/03/2027')` lê no formato americano e dá
  `2027-05-03`. Nunca passe dd/mm/aaaa para `strtotime`; `IDX:24` e
  `SHOW:23` funcionam porque recebem o valor do banco.
- O retorno de formato (`format(...) === valor`) recusa `31/02` e
  `5/3/2031`. Se o professor quiser aceitar sem zero à esquerda, use
  `'!j/n/Y'` e compare com `format('j/n/Y')`.
- Testes que mudam: `EVT:212` tem `'30/12/2030'` na lista de datas
  inválidas (agora é válida; troque por `'31/02/2030'`); `ECT:80`
  confere `type="date"`; `ACT/UpdateSuccessfullyCest.php:37` espera
  o campo em `Y-m-d`.
- A conversão dentro de `validates()` altera o objeto. Funciona porque
  `save()` valida antes do bind (`MODEL:141`); o lugar mais limpo é o
  gancho do M16.
- Com `type="text"` o seletor de calendário some. Máscara, se pedirem,
  em `public/assets/js/`, nunca na view.

Prova: `EVT` e `ECT`; no navegador, digitar `05/03/2027`, salvar, ver
`05/03/2027` no `show` e no campo ao editar.

### N1. Filtro por tipo com `where`, mantido na paginação

Chance: alta (`where` é a linha nova da rubrica; manter o filtro na
paginação expõe os limites do framework). Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/n1-filtro-por-tipo-paginacao`.

Pedido típico: "na listagem, filtre por tipo: `/admin/events?kind=workshop`".
A rubrica pede `where`.

Arquivos: `EC:16-27` (`index`), `IDX:1-3` e `IDX:40`, `MODEL:284-294`
(`paginate` não repassa condições), `PAG:18-26` (construtor já aceita
`conditions`), `PAG:93-97` (`renderPagesNavigation`), `PAG:147-158`
(`buildConditions`, só igualdade), `PAGES:6,16,22` (links só com
`page`), `HASMANY:44-55` (modelo de `paginate` com `conditions:`),
`EVT:366-376` (teste de `where`), `ECT:39-57`.

Passos (forma 1, segura; forma 2, completa):

1. `index()`:

   ```php
   $kind = $request->getParam('kind');
   $kind = is_string($kind) && array_key_exists($kind, Event::KINDS)
       ? $kind
       : null;
   ```

2. Forma 1 (sem paginação quando há filtro): com `$kind`,
   `$events = Event::where(['kind' => $kind]); $paginator = null;`;
   sem, o caminho atual. Na `IDX:40`, só chame
   `renderPagesNavigation()` se `$paginator !== null`. `$kind` no
   `compact`.
3. Forma 2 (com paginação, 15 min a mais):
   - `PAG:18-26`: `private array $queryParams = []` no construtor e
     getter `queryParams(): array` com `@return array<string, mixed>`.
   - `MODEL:284-294`: parâmetros `array $conditions = []` e
     `array $queryParams = []` repassados (modelo `HASMANY:44-55`), com
     `@param array<string, mixed>` em cada um.
   - `EC:18-22`: `conditions: $kind !== null ? ['kind' => $kind] : []`
     e `queryParams` igual.
   - `PAGES:6,16,22`: `array_merge(['page' => X], $paginator->queryParams())`;
     `route()` põe o que não está na URI como query string.
4. `IDX`: `<form method="get" action="<?= route('admin.events.index') ?>">`
   com `<select name="kind">` sobre `Event::KINDS` e `selected`.

Armadilhas:

- Não use `$kind` dentro de `PAGES`: o partial é incluído em
  `Paginator::renderPagesNavigation()` (`PAG:93-97`), onde só existem
  `$paginator` e `$this`. Variável indefinida vira warning, e o
  `ErrorsHandler` transforma em 500.
- `?kind[]=x` chega como array: `array_key_exists` com array lança
  `TypeError`; por isso `is_string` antes.
- `array` sem PHPDoc em `app/`, `core/`: `phpstan` nível 6,
  `missingType.iterableValue`.
- Sem `queryParams`, a página 2 perde o filtro.
- `$paginator` nulo na `IDX:40` dá 500 (forma 1).
- `where` só faz igualdade (`MODEL:309-311`): `LIKE` é N2.

Prova: teste de model (forma 1: `EVT:366-376` já cobre `where`; forma 2:
teste de `paginate` com `conditions:` e `queryParams:`) e de controller
(cópia de `ECT:39-57` com `params: ['kind' => 'lecture']`, só Palestras na
resposta; na forma 2, `/admin/events/page/2?kind=lecture` nos links);
no navegador, filtrar e ir à página 2. O `BaseEventsCest` distribui os
tipos em rodízio (`:79,87`), útil para um Cest.

### N2. Busca por título (`LIKE`)

Chance: média. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/n2-busca-por-titulo`.

Pedido típico: "campo de busca por título na listagem".

Arquivos: `EV` (método novo depois de `hasEnrollments()`, `EV:142-145`),
`EC:16-27`, `IDX:1-3`, `EVT:366-376`, `ECT:39-57`, `MODEL:300-333`
(`where`, modelo de SQL com `prepare`).

Passos:

1. Teste de model: dois Eventos, um termo que casa só um.
2. `EV`:

```php
/** @return array<static> */
public static function searchByTitle(string $term): array
{
    $columns = implode(', ', static::$columns);
    $stmt = Database::getDatabaseConn()->prepare(
        "SELECT id, {$columns} FROM events WHERE title LIKE :term"
    );
    $stmt->bindValue(':term', "%{$term}%");
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(fn($row) => new static($row), $rows);
}
```

   Imports: `Core\Database\Database`, `PDO`.
3. `EC::index`: `$q = $request->getParam('q');` e
   `$q = is_string($q) ? trim($q) : '';`.
   Se `$q !== ''`, `$events = Event::searchByTitle($q); $paginator = null;`
   (como N1 forma 1); senão o caminho atual.
4. `$q` **sempre** no `compact`. `IDX`: `<form method="get">` com
   `<input name="q" value="<?= htmlspecialchars($q) ?>">`.

Armadilhas:

- `$q` fora do `compact`: variável indefinida na view, 500.
- `array` sem `@return array<static>`: `phpstan` nível 6.
- Concatenar `$term` no SQL: injeção; sempre `prepare` + `bindValue`.
- Ecoar `$q` sem `htmlspecialchars`: XSS.
- `%` e `_` digitados viram curinga; aceitável, saiba dizer.
- `Model::where` não serve (só igualdade). SQL no controller: recusado.

Prova: teste unitário e de controller com `params: ['q' => ...]`; no
navegador, busca vazia mostra a listagem normal.

### N3. Ordenar por data no `Paginator`

Chance: média-alta. Tempo: 15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/n3-ordenar-por-data`.

Pedido típico: "ordene os Eventos pela data (mais próximos primeiro)".
Hoje a listagem segue o `id`; o `Paginator` não tem `ORDER BY`.

Arquivos: `PAG:18-26` (construtor) e `PAG:124-128` (SQL sem `ORDER BY`),
`MODEL:284-294`, `EC:18-22`, `ECT:39-57`, `EVT:390-413`, e os Cests que
assumem a ordem de `id`: `ACT/CreateSuccessfullyCest.php`,
`ACT/ListEventsCest.php:19-33`, `ACT/PaginateEventsCest.php`,
`ACT/BaseEventsCest.php:73-76`.

Passos:

1. Teste em `ECT`: Eventos com datas fora de ordem; `strpos` do título
   mais próximo antes do outro na resposta.
2. `PAG`: parâmetro `private ?string $orderBy = null` no construtor;
   em `loadRegisters()`:
   `$order = $this->orderBy !== null ? " ORDER BY {$this->orderBy}" : '';`
   inserido antes de `LIMIT :limit OFFSET :offset` (`PAG:127`).
3. `MODEL::paginate`: `?string $orderBy = null` repassado.
4. `EC:18-22`: `orderBy: 'starts_on, id'`.
5. Rode os Cests: `CreateSuccessfullyCest` cadastra para amanhã
   (`validEventData`, `BaseEventsCest.php:171`) e espera encontrá-lo na
   última página; com a ordenação ele vai para a página 1.

Armadilhas:

- `usort` no controller ordena só a página de 5: errado, o professor
  percebe na página 2. Faça no SQL.
- Desempate por `id`: sem ele, datas iguais trocam de página entre
  requisições; `ECT:39-57` cria tudo com a mesma data e depende da
  ordem de `id`.
- Nunca interpole `?sort=` do usuário em `ORDER BY`: lista fechada
  (`['starts_on', 'title']`) e `in_array(..., true)`.
- `Event::all()` (`MODEL:261-282`) também não ordena; o pedido pode ser
  sobre ele: método `Event::allOrderedByDate()` com SQL no model.
- Sem `ORDER BY` o MySQL não garante ordem nenhuma: hoje sai por `id`
  porque o InnoDB lê pela chave primária. Se o professor apontar o
  defeito sem pedir outra ordem, `ORDER BY id` já é a correção mínima
  e não quebra `ECT:39-57` nem os Cests.

Prova: teste de controller novo. No navegador, o populate sozinho não
mostra nada (datas crescem com o `id`): cadastre um Evento para
amanhã e veja-o subir para a página 1.

### N4. Mudar `per_page` ou deixar o usuário escolher

Chance: média-alta (micro-ajuste; a FVP real pediu feature nova, mas é
rápido e serve de aquecimento). Tempo: 5-10 min (mais 10 com a escolha
pelo usuário).

Exemplo pronto: `fvp/4.2/exemplos/n4-per-page`.

Pedido típico: "mostre 10 por página", "3 por página", "deixe o
usuário escolher".

Arquivos: `EC:12` (`PER_PAGE = 5`), `EC:18-22`, `ECT:39-57` (assume 5),
`PAG:113-114` (divisão por `per_page`), `ACT/BaseEventsCest.php:23`
(`PER_PAGE = 5`), `ACT/ListEventsCest.php:17,43`,
`ACT/PaginateEventsCest.php` (textos "Mostrando 1 - 5 de 16", número de
itens da paginação), `ACT/CreateSuccessfullyCest.php:32-35` (abre
`/admin/events/page/4` e espera "Mostrando 16 - 17 de 17").

Passos:

1. `EC:12`: troque o valor.
2. `ECT:39-57` cria 7 Eventos e espera "Evento 6" só na página 2: com
   10 falha. Ajuste (ex.: 12 Eventos) e rode.
3. `BaseEventsCest::PER_PAGE` e reescreva o `PaginateEventsCest`: com
   10 por página e 16 Eventos há só 2 páginas (ele clica na 3 e na 4).
4. Variante `?per_page=`:
   `$perPage = (int) $request->getParam('per_page', self::PER_PAGE);`
   e `in_array($perPage, [5, 10, 20], true) ? $perPage : self::PER_PAGE;`
   repassado aos links (mecanismo `queryParams` de N1 forma 2).

Armadilhas:

- `per_page` 0 dá divisão por zero em `PAG:114`: nunca o valor cru.
- Com 10 ou mais Eventos nos testes, `'Evento 1'` casa com
  `'Evento 10'`: use `sprintf('Evento %02d', $i)` como o
  `BaseEventsCest.php:63-66`.
- `EVT:390-413` usa 5 explícito; não depende de `PER_PAGE`.

Prova: `EventsControllerTest`; no navegador, 16 Eventos em 2 páginas e
`Mostrando 1 - 10 de 16`.

### N5. Só Eventos futuros ou passados

Chance: média. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/n5-eventos-futuros-passados`.

Pedido típico: "mostre só os Eventos que ainda vão acontecer" ou
"uma aba de Eventos passados".

Arquivos: `EV` (métodos novos, modelo N2), `MODEL:300-333` (`where` só
faz igualdade), `EC:16-27`, `IDX:1-3`, `EVT:499-509` (`createPastEvent`,
grava data passada direto no banco com `update`), `ECT:39-57`.

Passos:

1. Teste de model: um Evento futuro e um passado (criado com
   `createPastEvent`, `EVT:499-509`); `Event::upcoming()` traz só o
   futuro.
2. `EV`: `upcoming()` e `past()` com SQL no model:
   `WHERE starts_on >= :today ORDER BY starts_on, id` com
   `bindValue(':today', date('Y-m-d'))` (e `<` para passados),
   `@return array<static>`. Não use `CURDATE()`: ver a 1ª armadilha.
3. `EC::index`: `$scope = $request->getParam('scope');` validado em
   `['upcoming', 'past']`; com escopo, `$events = Event::upcoming()` e
   `$paginator = null`; sem, o caminho atual. `$scope` no `compact`.
4. `IDX`: dois links (`?scope=upcoming`, `?scope=past`) e "Todos".

Armadilhas:

- `CURDATE()` do MySQL usa o fuso do contêiner `db`; o PHP usa
  `America/Sao_Paulo` (`Dockerfile:10`). Para evitar divergência à
  noite, passe a data do PHP: `bindValue(':today', date('Y-m-d'))`.
- A validação não deixa criar Evento passado (`EV:75-77`): nos testes
  use `update(['starts_on' => ...])`, que não valida (`MODEL:189`).
- `$paginator` nulo na `IDX:40`: proteja (N1 forma 1).

Prova: teste de model; no navegador, o populate só tem futuros, então
crie um passado pelo `db:console` ou pelo teste.

### N6. Dado calculado na tela (badge "Lotado", total)

Chance: média. Tempo: 10-15 min.

Exemplo pronto: `fvp/4.2/exemplos/n6-badge-lotado-contador`.

Pedido típico: "mostre 'Lotado' quando não houver vagas" ou "quantos
Eventos existem no painel administrativo".

Arquivos: `IDX:26` (`inscritos/capacidade`), `EV:125-140`
(`enrollmentsCount`, `availableSpots`), `SHOW:28-32`,
`app/Controllers/Admin/DashboardController.php:9-13`,
`app/views/admin/dashboard/index.phtml:1-5`, `ECT:59-68`, `EVT:483-497`,
`ACT/ListEventsCest.php:36-38` (conferem `3/3`, `0/22`, `1/23`).

Passos:

1. Teste em `ECT`: copie `:59-68` e confira `Lotado` para um Evento
   com `capacity` igual às Inscrições (`enroll`, `ECT:302-313`).
2. `IDX:26`: mantenha `x/y` e acrescente

   ```php
   <?php if ($event->availableSpots() === 0) : ?>
       <span class="badge text-bg-danger">Lotado</span>
   <?php endif; ?>
   ```
3. Contador: `DashboardController::index` com
   `$total = count(Event::all());` (importar `App\Models\Event`) e
   `compact('title', 'total')`; imprima na view.

Armadilhas:

- Mudar o formato `1/10`: `ECT:67` e o `ListEventsCest` conferem a
  string.
- `enrollmentsCount()` faz uma consulta por linha (N+1); aceitável.
- `$total` fora do `compact`: 500.
- O Evento lotado do populate é o primeiro (3/3), na página 1.

Prova: teste de controller; o primeiro Evento mostra "Lotado".

### N7. Painel administrativo com resumo

Chance: média. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/n7-painel-resumo`.

Pedido típico: "no painel administrativo mostre quantos Eventos há por
tipo, os próximos três e o total de Inscrições" (agregação no model).

Arquivos: `app/Controllers/Admin/DashboardController.php:9-13`,
`app/views/admin/dashboard/index.phtml:1-5`, `EV` (métodos novos; N5
`upcoming()` serve de base), `EN`, `MODEL:300-333` (`where`, modelo de
SQL com `prepare`), `EV:25-30` (`KINDS`, rótulos), N6 (contador
simples, se já existir), `tests/Integration/Controllers/Admin/` (teste
novo de controller; N6 tem um `DashboardControllerTest` como modelo).

Passos:

1. Testes em `EVT`: `Event::countByKind()` com 2 Palestras e 1 Oficina
   devolve `['lecture' => 2, 'workshop' => 1, ...]` com `0` nos demais.
2. `EV`:

   ```php
   /** @return array<string, int> */
   public static function countByKind(): array
   {
       $stmt = Database::getDatabaseConn()->prepare(
           'SELECT kind, COUNT(*) AS total FROM events GROUP BY kind'
       );
       $stmt->execute();

       $totals = array_fill_keys(array_keys(self::KINDS), 0);
       foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
           $totals[$row['kind']] = (int) $row['total'];
       }

       return $totals;
   }
   ```

   `nextEvents(int $limit = 3): array` com `WHERE starts_on >= :today
   ORDER BY starts_on, id LIMIT :limit` (`bindValue(':limit', $limit,
   PDO::PARAM_INT)`), `@return array<static>`.
3. `DashboardController::index`: `$byKind = Event::countByKind();`,
   `$next = Event::nextEvents();`, `$enrollments = count(Enrollment::all())`
   (ou `Enrollment::count()`, N11); tudo no `compact`.
4. View: tabela tipo/quantidade com `Event::KINDS[$kind]`, lista dos
   próximos com link para o `show`, cartão de Inscrições.

Armadilhas:

- `LIMIT :limit` precisa de `PDO::PARAM_INT`, senão vira `'3'` com
  aspas e o MySQL recusa.
- Tipos sem Evento não voltam do `GROUP BY`: preencha com `0` a partir
  de `KINDS` (acima) para a tabela não "sumir" linhas.
- `fetchColumn`/`COUNT` devolvem string: `(int)`.
- Variável fora do `compact`: 500. `$title` idem.
- `AdminDashboardCest` (`tests/Acceptance/admin/AdminDashboardCest.php`)
  roda sem dados: a view precisa funcionar com zero Eventos.

Prova: testes; no navegador o populate mostra Palestra 5, Oficina 4,
Minicurso 4, Semana acadêmica 3 (`POP:49-130`) e 14 Inscrições. O
exemplo chama `Event::upcoming(int $limit)` (mesmo nome de N5) em vez de
`nextEvents()` e cria `Enrollment::total()` com `COUNT(*)`; nomes
equivalentes.

### N8. Ordenar clicando no cabeçalho da listagem

Chance: média. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/n8-ordenar-pelo-cabecalho`.

Pedido típico: "clicar em Título ou Data ordena a listagem (crescente e
decrescente), e a ordem se mantém ao paginar". Junta N3 (`ORDER BY` no
`Paginator`) com N1 forma 2 (`queryParams`).

Arquivos: `PAG:18-26` e `:124-128` (N3: `orderBy`), `MODEL:284-294`,
`EV` (lista fechada e cláusula), `EC:16-27`, `IDX:11-16` (cabeçalhos),
`PAGES:6,16,22` (links com `queryParams`, N1 forma 2), `ECT:39-57`,
`ACT/ListEventsCest.php:19-33` (o comentário supõe a ordem de `id`),
`ACT/CreateSuccessfullyCest.php`.

Passos:

1. Testes em `ECT`: `params: ['sort' => 'title', 'dir' => 'desc']`
   ordena; `sort` fora da lista cai na ordem padrão; os links de página
   trazem `?sort=title&dir=desc`.
2. `EV` (validação e SQL no model):

   ```php
   public const SORTS = ['starts_on', 'title'];

   /** @return array{0: string, 1: string} coluna e direção seguras */
   public static function normalizeSort(mixed $sort, mixed $dir): array
   {
       $sort = is_string($sort) && in_array($sort, self::SORTS, true)
           ? $sort
           : 'starts_on';

       return [$sort, $dir === 'desc' ? 'desc' : 'asc'];
   }
   ```

   e `orderClause(string $sort, string $dir): string` devolvendo
   `"{$sort} {$dir}, id"`. `EC::index`: `[$sort, $dir] =
   Event::normalizeSort(...)` com os dois `getParam`;
   `orderBy: Event::orderClause($sort, $dir)` e
   `queryParams: ['sort' => $sort, 'dir' => $dir]`.
3. `IDX:11-16`: cabeçalhos viram links
   `route('admin.events.index', ['sort' => 'title', 'dir' => $next])`
   com `$next = $dir === 'asc' ? 'desc' : 'asc'` e uma seta no ativo.
4. `PAGES` com `array_merge(['page' => X], $paginator->queryParams())`
   (N1 forma 2).

Armadilhas:

- `ORDER BY` não aceita `bindValue` para nome de coluna: a única defesa
  é a lista fechada (`in_array(..., true)`). Nunca interpole o valor cru.
- `?sort[]=x` vem como array: `is_string` antes.
- Desempate por `id`, senão títulos iguais trocam de página.
- A ordem padrão muda de `id` para `starts_on`: `ListEventsCest:19-33`
  continua passando (no seed a ordem de `id` é a de data), só o
  comentário fica errado; `CreateSuccessfullyCest` assume `id` (N3).
- Ordenação de texto segue a collation do MySQL (`utf8mb4_0900_ai_ci`,
  sem distinguir acentos nem maiúsculas); diga isso se perguntarem.

Prova: testes; no navegador, clicar em Título, ir à página 2 e ver a
ordem mantida.

### N9. Filtro por período

Chance: média. Tempo: 30-40 min.

Exemplo pronto: `fvp/4.2/exemplos/n9-filtro-por-periodo` (forma 2, paginada).

Pedido típico: "filtrar os Eventos entre duas datas (de/até); data
inválida mostra erro, não é ignorada em silêncio".

Arquivos: `EV` (método novo com `BETWEEN`; N5 `upcoming()` é o molde),
`EC:16-27`, `IDX:1-3` (form), `VAL:140-153` (`parseDate`, privado: copie
a ideia), `PAG:147-169` (só igualdade: a forma paginada exige estender
o `Paginator`, como P11 fez com `IS NULL`), `ECT:39-57`, `EVT:366-376`.

Passos (forma 1, sem paginação quando filtrado):

1. Testes em `EVT`: três Eventos em datas distintas; `betweenDates(a, b)`
   devolve só o do meio. `ECT`: `from` inválido (`'2026-13-40'`) -> form
   com mensagem e listagem normal; válido -> só os do intervalo.
2. `EV`:

   ```php
   /** @return array<static> */
   public static function betweenDates(string $from, string $to): array
   {
       $columns = implode(', ', static::$columns);
       $stmt = Database::getDatabaseConn()->prepare(
           "SELECT id, {$columns} FROM events "
           . 'WHERE starts_on BETWEEN :from AND :to ORDER BY starts_on, id'
       );
       $stmt->bindValue(':from', $from);
       $stmt->bindValue(':to', $to);
       $stmt->execute();

       $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

       return array_map(fn($row) => new static($row), $rows);
   }
   ```

3. `EV::periodError(mixed $from, mixed $to): ?string` (estático):
   `null` se os dois vierem vazios ou válidos e em ordem; senão a
   mensagem. Valida com `is_string` e
   `DateTimeImmutable::createFromFormat('!Y-m-d', $v)` + `format() === $v`
   (como `VAL:140-153`). `EC::index` só lê `from`/`to` e chama
   `periodError`: com erro, mensagem e listagem padrão; ambos válidos ->
   `Event::betweenDates(...)`, `$paginator = null`. `from`, `to` e
   `error` sempre no `compact`.
4. `IDX`: dois `<input type="date">` com os valores mantidos, botão
   "Filtrar", "Limpar"; mensagem de erro em vermelho; guarda do
   `$paginator` nulo (N1 forma 1).
5. Forma 2 (paginada): `Paginator` com condições `>=`/`<=` (estenda
   `buildConditions`/`bindConditions` em `lib/`, nunca em `core/`), e
   `queryParams` com `from`/`to`.

Armadilhas:

- `from > to`: troque ou acuse; não devolva lista vazia sem aviso.
- Só um dos dois preenchido: decida (hoje em diante / até) e teste.
- `BETWEEN` com `DATE` inclui as duas pontas; com `DATETIME` (M7 forma
  B) o `to` precisa de `23:59:59`.
- Validação no model (`periodError`), não no controller: é o que a
  rubrica cobra, mesmo sendo a entrada de um filtro.
- `$paginator` nulo na `IDX:40`: 500 se esquecer a guarda.

Prova: testes; no navegador, filtrar o populate por duas semanas e
depois digitar uma data inválida.

### N10. Página e id inválidos sem conversão silenciosa

Chance: média-alta. Tempo: 15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/n10-pagina-id-invalido`.

Pedido típico: "`/admin/events/page/abc` e `/admin/events/page/999` não
podem mostrar lixo; `/admin/events/abc/edit` não pode virar `id = 0`
por mágica" (issue 4.2 do grupo 5: conversão silenciosa com `(int)`).

Arquivos: `EC:19` (`max(1, (int) $request->getParam('page', 1))`),
`EC:132-135` (`findEvent` com `(int)`), `PAG:81-86` (`entriesInfo` com
página fora do intervalo imprime "Mostrando 4996 - 4995 de 16"),
`PAG:51-54` (`totalOfPages`), `PAG:104-115` (`loadTotals`),
`ECT:39-57`, `ACT/PaginateEventsCest.php`, `ACC:85-97` (`/page/2`).

Passos:

1. Testes em `ECT`: `page => 'abc'` -> `Location: /admin/events` e flash
   `danger`; `page => '999'` -> idem (ou redireciona para a última
   página; escolha e teste); `page => '2'` continua ok. `findEvent` com
   `id => 'abc'` -> "Evento não encontrado!" sem passar por `(int)`.
2. `EC`: método privado

   ```php
   private function pageParam(Request $request): ?int
   {
       $page = $request->getParam('page', '1');

       $valid = is_string($page) && ctype_digit($page) && (int) $page >= 1;

       return $valid ? (int) $page : null;
   }
   ```

   e em `index()`: `null` -> `FlashMessage::danger('Página inválida!')`,
   `redirectTo(route('admin.events.index'))`, `return`.
3. `PAG`:

   ```php
   public function isOutOfRange(): bool
   {
       return $this->page > max(1, $this->totalOfPages);
   }
   ```

   Em `index()`, depois de montar o paginador,
   `if ($paginator->isOutOfRange())` -> mesma saída do passo 2.
4. `findEvent`:

   ```php
   $id = $request->getParam('id');
   if (!is_string($id) || !ctype_digit($id)) {
       return null;
   }

   return Event::findById((int) $id);
   ```

Armadilhas:

- O parâmetro de rota chega como **string** (`Route.php:91-98`), mas o
  padrão `getParam('page', 1)` é `int`: trate os dois (`(string)` antes
  do `ctype_digit`, ou padrão `'1'`).
- `ctype_digit('')` é `false` e `ctype_digit('007')` é `true` (página 7):
  aceitável; se quiser recusar zeros à esquerda, compare
  `(string) (int) $page === $page`.
- `totalOfPages` é `0` sem Eventos: página 1 tem de continuar válida
  (`max(1, ...)`).
- Divisão em `PAG:114` só depende de `per_page` (N4), não da página.
- `(int) 'abc'` hoje vira `0` e cai em "não encontrado" por sorte;
  `(int) '12abc'` vira `12` e abre outro Evento: é o caso que a
  validação explícita fecha.
- Na tela, página além da última nem chega ao `entriesInfo` de
  `PAG:81-86`: `IDX:5-6` mostra "Nenhum evento cadastrado." (falso, há
  16) e a navegação (`IDX:40`) fica no `else`, sem link de volta. É o
  que o professor vê em `/admin/events/page/99`.

Prova: `EventsControllerTest`, `PaginateEventsCest`; no navegador,
`/admin/events/page/abc` e `/admin/events/page/99`.

### N11. `Model::count()` no framework

Chance: média. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/n11-model-count`.

Pedido típico: "a listagem faz uma consulta por linha para contar
inscritos (`count(Enrollment::where(...))`); crie `count()` no
ActiveRecord e use" (bônus "Funcionalidades Extras no Framework").

Arquivos: `MODEL:300-333` (`where`: a montagem de condições a
reaproveitar), `MODEL:261-282` (`all`), `EV:125-132`
(`enrollmentsCount`), `core/Database/ActiveRecord/BelongsToMany.php:59-82`
(`count()` da relação, modelo de `SELECT COUNT`), `EVT:465-481`,
`tests/Unit/Models/Events/EnrollmentTest.php`.

Passos:

1. Testes: `Enrollment::count() === 0` no banco vazio;
   `Enrollment::count(['event_id' => $event->id]) === 2` depois de
   `enroll($event, 2)`; `Event::count()`; `enrollmentsCount()` continua
   devolvendo o mesmo.
2. `MODEL`, ao lado de `where`:

   ```php
   /** @param array<string, mixed> $conditions */
   public static function count(array $conditions = []): int
   {
       $sql = 'SELECT COUNT(*) FROM ' . static::$table;

       if ($conditions !== []) {
           $sql .= ' WHERE ' . implode(' AND ', array_map(
               fn(string $column) => "{$column} = :{$column}",
               array_keys($conditions)
           ));
       }

       $stmt = Database::getDatabaseConn()->prepare($sql);
       foreach ($conditions as $column => $value) {
           $stmt->bindValue($column, $value);
       }
       $stmt->execute();

       return (int) $stmt->fetchColumn();
   }
   ```

3. `EV:131`: `return Enrollment::count(['event_id' => $this->id]);`.
   N6/N7: `Enrollment::count()` no painel.

Armadilhas:

- `fetchColumn()` devolve string: `(int)`.
- Mudança em `core/`: mínima, com teste, e sem alterar assinaturas
  existentes; o `phpstan` cobre `core` (PHPDoc do array).
- Nome `count` não colide com nada em `Model`; se preferir,
  `countWhere`.
- O N+1 na listagem continua (uma consulta por linha), só mais barata;
  acabar com ele exige um `JOIN` com `GROUP BY` (N7) ou um `LEFT JOIN`
  no `Paginator`. Saiba dizer.
- Não troque `enrollmentsCount()` por `participants()->count()`
  (`BelongsToMany:59-82`) sem motivo: os testes já cobrem o atual.
- Cada consulta abre um PDO novo (`core/Database/Database.php:18`): a
  listagem de 5 linhas abre 5 conexões só para contar (`IDX:26`). O
  `show` conta duas vezes: `SHOW:30` e `SHOW:31` via `availableSpots()`
  (`EV:139`). Guarde o total numa variável antes de imprimir.

Prova: testes; a listagem continua igual no navegador.

### N12. Paginação com Primeira/Última e janela de números

Chance: média. Tempo: 10-20 min.

Exemplo pronto: `fvp/4.2/exemplos/n12-paginacao-primeira-ultima`.

Pedido típico: "botões Primeira e Última e, no máximo, três números em
volta da página atual" (o `Paginator` é item 2.6 da rubrica).

Arquivos: `PAG:56-79` (`previousPage`, `nextPage`, `hasPreviousPage`,
`hasNextPage`, `isPage`), `PAG:51-54` (`totalOfPages`), `PAGES:14-18`
(laço `for` que imprime todas as páginas), `ACT/PaginateEventsCest.php`
(conta os itens da paginação: 6 com 4 páginas), `ECT:39-57`, S5
(`PaginatorTest`), T1 (30 Eventos para a janela aparecer).

Passos:

1. Testes: `PaginatorTest` (S5): com 7 páginas na página 4,
   `pagesWindow()` devolve `[3, 4, 5]`; na página 1, `[1, 2]`;
   `lastPage() === 7`. `ECT`: a página 2 de 21 Eventos (5 por página,
   5 páginas) mostra "Primeira", "Última" e não mostra o link da 5.
2. `PAG`:

   ```php
   public function firstPage(): int
   {
       return 1;
   }

   public function lastPage(): int
   {
       return max(1, $this->totalOfPages);
   }

   /** @return array<int, int> */
   public function pagesWindow(int $radius = 1): array
   {
       $from = max(1, $this->page - $radius);
       $to = min($this->lastPage(), $this->page + $radius);

       return range($from, $to);
   }
   ```

3. `PAGES`: "Primeira" (desabilitada em `isPage(1)`), Anterior, o laço
   sobre `pagesWindow()` (com "…" quando `$from > 1` / `$to < lastPage`),
   Próxima, "Última". Links com
   `route($paginator->getRouteName(), ['page' => N])`
   (mais `queryParams()` se N1 forma 2 existir).
4. Atualize `PaginateEventsCest` (número de itens) e `ListEventsCest`
   se contarem elementos da paginação.

Armadilhas:

- `totalOfPages` é `0` sem registros: `lastPage()` com `max(1, ...)`
  evita `range(1, 0)` (que devolve `[1, 0]`!).
- O partial só enxerga `$paginator` (`PAG:93-97`): toda informação vem
  de métodos dele.
- `PaginateEventsCest:18` conta 6 itens (Anterior, 4 páginas, Próxima):
  com Primeira/Última e janela muda; reescreva o cenário.
- `lib/` fora do `phpstan`: anote `@return array<int, int>` mesmo assim.

Prova: testes; no navegador com T1 (30 Eventos, 6 páginas), página 3
mostra "Primeira Anterior 2 3 4 Próxima Última".

### N13. Filtro que o `where` não expressa: público-alvo ou "só com vagas"

Chance: média-baixa. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/n13-filtro-find-in-set-vagas`.

Pedido típico: "filtre os Eventos para Estudantes" (`audience` é um
`SET`: igualdade não serve) ou "mostre só os que ainda têm vaga"
(depende de uma contagem).

Arquivos: `SCH:28` (`SET`), `EV:40-45` (`AUDIENCES`), `EV:180-211`
(`audienceList`, `hasAudience`), `VAL:52-73` (`inclusionOfAll`),
`MODEL:300-333` (`where`, só igualdade), `PAG:147-169` (idem), `EC:16-27`,
`IDX:1-3`, N1 (validação do parâmetro e `$paginator` nulo), `EVT:366-376`.

Passos (forma 1, sem paginação quando filtrado):

1. Testes em `EVT`: `forAudience('students')` traz quem tem `students`
   no SET (inclusive `students,professors`) e não quem só tem
   `professors`; `withSpots()` exclui o lotado (`enroll` até a capacidade).
2. `EV`:

   ```php
   /** @return array<static> */
   public static function forAudience(string $audience): array
   {
       $columns = implode(', ', static::$columns);
       $stmt = Database::getDatabaseConn()->prepare(
           "SELECT id, {$columns} FROM events "
           . 'WHERE FIND_IN_SET(:audience, audience) > 0 ORDER BY starts_on, id'
       );
       $stmt->bindValue(':audience', $audience);
       $stmt->execute();

       $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

       return array_map(fn($row) => new static($row), $rows);
   }
   ```

   `withSpots()`:

   ```sql
   WHERE capacity > (SELECT COUNT(*) FROM event_user eu
                     WHERE eu.event_id = events.id)
   ```

3. `EC::index`: `audience` validado com `array_key_exists($v, Event::AUDIENCES)`
   (`is_string` antes); `spots=1` booleano; com filtro, `$paginator = null`.
4. `IDX`: `<select name="audience">` sobre `AUDIENCES` e checkbox "só
   com vagas"; guarda do `$paginator` nulo.

Armadilhas:

- `SET` guarda `students,professors` como string: `LIKE '%students%'`
  casaria `students` dentro de outro valor; `FIND_IN_SET` compara o
  token inteiro.
- Subconsulta por linha é aceitável aqui; a alternativa é `LEFT JOIN` +
  `GROUP BY` + `HAVING`.
- `where` e `Paginator` não servem (igualdade); para paginar, estenda o
  `Paginator` com condição crua em `lib/` (P11 mostra o caminho) e nunca
  concatene o valor do usuário.
- Valor do filtro sempre por `bindValue`.

Prova: testes; no populate, "Servidores" traz 6 Eventos e "só com
vagas" tira o Evento 1.

### N14. Query Builder mínimo no core

Chance: baixa (mais de 40 min). Tempo: 40-60 min.

Exemplo pronto: `fvp/4.2/exemplos/n14-query-builder`.

Pedido típico: "em vez de SQL à mão no model, faça
`Event::query()->where(...)->orderBy(...)->paginate(...)`" (aula de
Builder). Junta N1 (filtro), N3 (ordem) e N13 (condição que não é
igualdade) numa API só.

Arquivos: `MODEL:284-294` (`paginate` sem condições), `MODEL:300-330`
(`where` só com `=`; com `[]` gera `WHERE` vazio e erro de SQL),
`PAG:18-29` (construtor recebe `conditions`), `PAG:104-158`
(`loadTotals`, `loadRegisters`, `buildConditions`: só `=` e `AND`, sem
`ORDER BY`), `EC:16-27`.

Passos:

1. Testes do builder sem banco: `toSql()` de
   `where('kind', 'lecture')->where('starts_on', '>=', '2026-11-05')
   ->orderBy('starts_on')` gera
   `... WHERE kind = :p0 AND starts_on >= :p1 ORDER BY starts_on ASC`;
   operador fora da lista e coluna fora de `$columns` lançam
   `InvalidArgumentException`. Com banco: `get()` devolve na ordem;
   `paginate` conta só os filtrados.
2. `core/Database/ActiveRecord/QueryBuilder.php`: guarda classe, tabela,
   colunas, a lista de condições `[coluna, operador, valor]` e a lista
   de ordens; métodos `where(string $column, mixed $opOrValue,
   mixed $value = null): static`, `orderBy(string $column,
   string $dir = 'ASC'): static`, `get(): array`, `count(): int` (opcional),
   `paginate(int $page, int $perPage, string $route): Paginator`.
3. `Model::query(): QueryBuilder` com
   `new QueryBuilder(static::class, static::$table, static::$columns)`.
4. `Paginator`: parâmetros opcionais `whereSql`, `bindings` e `orderBy`
   no construtor (`PAG:18-26`), usados em `loadTotals`/`loadRegisters`
   quando vierem. O caminho atual (`conditions`) fica igual e os testes
   de paginação seguem verdes.
5. `EC::index`: `Event::query()->orderBy('starts_on')->orderBy('id')
   ->paginate(...)`.

Armadilhas:

- Placeholders numerados (`:p0`, `:p1`). Com `:starts_on` nas duas
  pontas (de/até), o segundo `bindValue` sobrescreve o primeiro e a
  faixa some sem erro (`>= :s AND <= :s` com 2000 e 2100 conta 0).
- Coluna e direção não aceitam bind: lista permitida
  (`in_array($column, ['id', ...static::$columns], true)`, `ASC`/`DESC`),
  senão é injeção de SQL.
- Operadores em lista fechada (`=`, `!=`, `<`, `<=`, `>`, `>=`, `LIKE`).
- `LIMIT`/`OFFSET` com `PDO::PARAM_INT` (`PAG:133-134`).
- Core com PHPDoc dos arrays
  (`@var list<array{0: string, 1: string, 2: mixed}>`), phpstan nível 6.
- O tempo da FVP não comporta tudo: proponha o mínimo (`where` +
  `orderBy` + `get`) e diga o que ficou de fora.

Prova: testes do builder; listagem ordenada e filtrada pelo builder.

### N15. Dois paginadores na mesma tela

Chance: baixa. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/n15-dois-paginadores`.

Pedido típico: "na listagem, uma tabela de próximos e outra de passados,
cada uma com a sua paginação".

Arquivos: `PAG:99-102` (`getRouteName`), `PAGES:6,16,22` (sempre
`['page' => N]`), `PAG:147-158` (só igualdade: "passado" exige N5),
`ROUTES:27-28` (`/admin/events/page/{page}`), `core/Router/Router.php:79-103`
(o que não está na URI vira query string), `EC:16-27`, `IDX`.

Passos:

1. Testes `ECT`: com 7 futuros e 7 passados, `index` com `page=2` e
   `past_page=1` mostra a página 2 dos futuros e a 1 dos passados; os
   links da tabela de passados levam o `page` atual junto, e vice-versa.
2. Consultas: cada lista precisa de `starts_on >= :today` ou `<`, que o
   `Paginator` não faz (`PAG:147-158`). Use o `Paginator` estendido do
   N14 ou dois métodos no model com `LIMIT/OFFSET` e `COUNT(*)` próprios
   (molde N13). O exemplo pronto põe `comparisons` (operador fixo do
   model) e `orderBy` no `Paginator` e cria `Event::paginateUpcoming` e
   `paginatePast`.
3. `Paginator`: parâmetros `string $pageParam = 'page'` e
   `array $otherParams = []` no construtor e um método (mesmo nome do
   O6):

   ```php
   /** @return array<string, mixed> */
   public function routeParams(int $page): array
   {
       return [...$this->otherParams, $this->pageParam => $page];
   }
   ```

   `PAGES` monta cada link com `route($paginator->getRouteName(),
   $paginator->routeParams(N))` dentro de `htmlspecialchars`: com dois
   parâmetros na query string, o `&` sai cru.
4. `EC::index`: lê `page` e `past_page` (validados como N10), monta os
   dois paginadores, cada um com o número de página do outro em
   `otherParams`.
5. `IDX`: duas tabelas, cada uma com o seu `renderPagesNavigation()`.

Armadilhas:

- A rota `admin.events.paginate` tem `{page}` na URI: `route()` põe
  `page` no caminho e o resto na query string. O segundo paginador
  precisa de outro nome (`past_page`), senão um pisa no outro.
- `entriesInfo()` e "Nenhum evento" por tabela.
- Página fora do intervalo em uma das listas (N10).
- `PAGES` serve a qualquer paginador: com `pageParam = 'page'` como
  padrão, a listagem atual e os testes de paginação ficam iguais.
- Com `WHERE starts_on ...`, o MySQL lê pelo índice de `starts_on`
  (`SCH:37`) e a ordem por `id` deixa de valer: ponha
  `ORDER BY starts_on, id` em cada lista.

Prova: `ECT`; no navegador, avançar uma tabela não mexe na outra.

### O1. Rota RESTful nova com página (lotados)

Chance: média-alta. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/o1-rota-eventos-lotados`.

Pedido típico: "uma página que lista os Eventos lotados"
(`GET /admin/events/full`, `admin.events.full`). Só existem `get`,
`post`, `put` e `delete` em `core/Router/Route.php:111-141` (não há
`patch`).

Arquivos: `ROUTES:26-35` (rota fixa `new` em `:29`, `{id}` em `:31`),
`EC` (ação nova), `app/views/admin/events/full.phtml` (novo), `IDX:1-3`
(link), `EV:125-145` (`enrollmentsCount`, `availableSpots`,
`hasEnrollments`), `ACC:58-78` (`newClient`, `loggedClient`) e
`ACC:162-173` (admin acessa e vê o conteúdo), `ACC:85-97`
(`eventRoutes`).

Passos:

1. Teste de controller: `get(action: 'full', ...)` com um Evento lotado
   (`enroll`, `ECT:302-313`, `capacity => '1'`) e um não.
2. `ROUTES`, **antes** de `/admin/events/{id}` (`:31`), junto de `new`
   (`:29`):

   ```php
   Route::get('/admin/events/full', [AdminEventsController::class, 'full'])
       ->name('admin.events.full');
   ```
3. `EC::full(): void`:

   ```php
   $events = array_filter(
       Event::all(),
       fn(Event $e) => $e->availableSpots() === 0
   );
   $title = 'Eventos lotados';
   $this->render('admin/events/full', compact('events', 'title'));
   ```

4. `full.phtml`: tabela (copie `IDX:8-38` sem o paginador e sem o
   botão Remover). Link em `IDX:1-3`.
5. `ACC`: `'admin.events.full' => ['GET', '/admin/events/full']` em
   `eventRoutes()` e um teste com admin logado (cópia de `:162-173`)
   esperando 200 e "Eventos lotados". É o cenário S2: o exemplo
   `s2-teste-acesso-rota-nova` empilha esse teste sobre o `o1-...`.

Armadilhas:

- O `CTC` chama a ação direto (`CTC:70-84`), sem Router: o teste de
  controller **não pega** rota declarada depois de `{id}`. Só um teste
  por HTTP pega (se a rota estiver depois, vem 302 de "Evento não
  encontrado!").
- Sem sessão a resposta é 302 nos dois casos: o teste HTTP tem que
  logar o admin (`loggedClient`).
- `$title` fora do `compact`: o layout quebra (`LAYOUT:26`).
- Nome fora do padrão `admin.events.*`.
- Para `POST .../duplicate` (P6) o form precisa de `method="post"`;
  PUT/DELETE exigem `_method` (`core/Http/Request.php:18`).

Prova: teste de controller + HTTP; no navegador a página lista só o
primeiro Evento do populate (3/3).

### O2. Listagem e detalhe de Eventos para o Participante

Chance: média-alta. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/o2-eventos-para-participante`.

Pedido típico: "o Participante precisa ver os Eventos" (`/events` e
`/events/{id}`, só leitura). Variante: pública, sem login. É a base de
P2 (inscrição).

Arquivos: `ROUTES:18-20` (grupo `auth`, hoje só o dashboard; as rotas
novas entram aqui, fora de `admin`), `ROUTES:4` (o admin já é importado
com alias; `use App\Controllers\EventsController;` fica livre),
`EC:16-39` (modelo de `index` e `show`), `IDX`, `SHOW`, `NAV:10-12`
(link para todos os logados), `MODEL:284-294` (`paginate` com nome de
rota), `ECT:17-57` (modelo de teste),
`tests/Integration/Access/DashboardAccessTest.php`
(modelo de acesso sem admin), `app/views/dashboard/index.phtml`.

Passos:

1. Testes: `tests/Integration/Controllers/EventsControllerTest.php`
   (namespace `Tests\Integration\Controllers`, não colide com o admin),
   `setUp` logando um **Participante**; `index` lista títulos; `show`
   mostra descrição e não mostra "Editar"/"Remover".
2. `ROUTES`, dentro de `auth`, antes do grupo `admin`:

```php
Route::get('/events', [EventsController::class, 'index'])->name('events.index');
Route::get('/events/page/{page}', [EventsController::class, 'index'])
    ->name('events.paginate');
Route::get('/events/{id}', [EventsController::class, 'show'])
    ->name('events.show');
```

3. `app/Controllers/EventsController.php` (namespace `App\Controllers`):
   `index` igual a `EC:16-27` com `route: 'events.paginate'` e
   `render('events/index', ...)`; `show` igual a `EC:29-39` com
   "não encontrado" redirecionando para `events.index`.
4. `app/views/events/index.phtml` e `show.phtml`: copie `IDX` e `SHOW`
   sem "Novo", "Editar", "Remover"; links para `events.show`.
5. `NAV:10-12`: `<li><a href="<?= route('events.index') ?>">Eventos</a></li>`
   para todo logado (o link admin de `NAV:17-19` continua). O exemplo
   põe o link no `else` do `isAdmin()` (`NAV:13-20`): o admin fica só
   com `/admin/events` e acessa `/events` pela URL; as duas formas valem.
6. Acesso: teste HTTP (cópia de `DashboardAccessTest`): sem sessão 302
   `/login`; Participante 200; admin 200.

Armadilhas:

- Rota de Participante dentro do grupo `admin` por engano
  (`ROUTES:22-36`): Participante cai em `/dashboard` com "Acesso
  restrito".
- Dois controllers com o mesmo nome curto: no `routes.php` importe
  `App\Controllers\EventsController` sem alias e mantenha
  `AdminEventsController` (`ROUTES:4`).
- `paginate` sem `route:` usa `events.paginate` por padrão
  (`PAG:99-102`); declare a rota com esse nome.
- Versão pública (fora de `auth`): `currentUser()` é `null`; as views
  copiadas não podem chamar `$this->currentUser()->name`.
- Esquecer `$title` no `compact`: 500.

Prova: testes; no navegador, logado como `user@example.com`, abrir
`/events`, paginar e abrir um Evento.

### O3. Inscritos no `show` (`participants()`)

Chance: média. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/o3-inscritos-no-show`.

Pedido típico: "no detalhe do Evento, liste quem está inscrito (nome e
e-mail)".

Arquivos: `EV:117-120` (`user()`, modelo de relação), `MODEL:365`
(`BelongsToMany(string $related, string $pivot_table,
string $from_foreign_key, string $to_foreign_key)`,
com B maiúsculo), `core/Database/ActiveRecord/BelongsToMany.php:22-57`
(`get()`) e `:59-82` (`count()`), `EN:20-28`, `SHOW:28-32`, `EVT:67-76`
(`enroll`), `ECT:151-161` e `ECT:302-313`.

Passos:

1. Teste em `EVT`: `createEvent()`, `enroll($event, 2)`,
   `assertCount(2, $event->participants()->get())`.
2. `EV`:

```php
public function participants(): BelongsToMany
{
    return $this->BelongsToMany(
        User::class,
        'event_user',
        'event_id',
        'user_id'
    );
}
```

   Import `Core\Database\ActiveRecord\BelongsToMany`.
3. `SHOW`: depois de Vagas, `<ul>` com
   `<?php foreach ($event->participants()->get() as $participant) : ?>`
   e `htmlspecialchars($participant->name)` / `->email`.
4. Teste em `ECT`: `show` de um Evento com `enroll()` contém
   "Participante" (nome criado em `ECT:305`).

Armadilhas:

- `get()` devolve `array<Model>`: `$participant->name` funciona pelo
  `__get`, mas o `phpstan` não conhece `name` em `Model`; na view não
  importa (views fora do `phpstan`), em código `app/` use
  `/** @var User $participant */` ou `instanceof User`.
- O `__get` mágico (`MODEL:55-68`) também permite `$event->participants`
  (sem parênteses) devolvendo o array; use a forma explícita.
- Alternativa sem `BelongsToMany`:
  `Enrollment::where(['event_id' => $this->id])`
  e `->user()->get()` por linha (N+1).
- `count()` da relação (`BelongsToMany:59-82`) pode substituir
  `enrollmentsCount()`; não troque sem pedido (testes conferem).

Prova: `EventTest` e `EventsControllerTest`; o primeiro Evento do
populate mostra 3 inscritos.

### O4. Resposta JSON da listagem

Chance: baixa. Tempo: 20 min.

Exemplo pronto: `fvp/4.2/exemplos/o4-resposta-json`.

Pedido típico: "a listagem deve responder JSON quando o cliente pede
`Accept: application/json`" (preparo para o Ajax da 4.4).

Arquivos: `CTRL:44-55` (`renderJson`: exige a view
`app/views/<view>.json.php` que preenche `$json`),
`core/Http/Request.php:52-55` (`acceptJson()`: compara `HTTP_ACCEPT`
com `application/json` exato), `EC:16-27`, `ECT:39-57`.

Passos:

1. Teste em `ECT`: `$_SERVER['HTTP_ACCEPT'] = 'application/json';`
   antes do `get('index', ...)`; `json_decode($response, true)` tem a
   chave `events` com os títulos. Limpe `$_SERVER['HTTP_ACCEPT']` no
   `tearDown`.
2. `EC::index`, depois de montar `$events`:

```php
if ($request->acceptJson()) {
    $this->renderJson('admin/events/index', compact('events'));
    return;
}
```

3. `app/views/admin/events/index.json.php`:

```php
<?php
$json['events'] = array_map(fn(\App\Models\Event $event) => [
    'id' => $event->id,
    'title' => $event->title,
    'starts_on' => $event->starts_on,
    'available_spots' => $event->availableSpots(),
], $events);
```

Armadilhas:

- `acceptJson()` exige igualdade exata: `Accept: application/json, */*`
  não casa. Teste com `curl -H 'Accept: application/json'`.
- `renderJson` não passa pelo layout e tem o header `chartset` com
  erro de grafia (`CTRL:51`); não é seu problema, mas saiba.
- Rota com extensão (`/admin/events.json`) não é o padrão do
  framework; use o header.
- `$json` precisa existir na view (`CTRL:49` inicializa `[]`).

Prova: teste de controller;
`curl -H 'Accept: application/json' -b <cookie> http://localhost/admin/events`.

### O5. Admin vê e lista só os próprios Eventos

Chance: média-alta. Tempo: 15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/o5-admin-lista-so-proprios`.

Pedido típico: "cada Administrador só enxerga e cadastra os Eventos
dele" (Q1 fecha editar/remover; aqui a listagem e a criação). Usa
`HasMany::paginate`, que já repassa a condição `user_id`.

Arquivos: `EC:16-27` (`index`), `EC:49-63` (`create`), `US:52-55`
(`events(): HasMany`), `HASMANY:27-32` (`new()`, já põe o `user_id`) e
`:44-55` (`paginate` com `conditions`), `ECT:17-57`, `POP:12-19` (um
admin só; T1 traz o segundo), `ACT/BaseEventsCest.php:84-94` (todos os
Eventos são do mesmo admin: os Cests continuam valendo).

Passos:

1. Teste em `ECT`: dois admins, dois Eventos de cada; logado como o
   primeiro, `index` mostra só os dele (e `Mostrando 1 - 2 de 2`).
2. `EC::index`:

   ```php
   $admin = $this->currentUser();
   if ($admin === null) {
       $this->redirectTo(route('users.login'));
       return;
   }

   $paginator = $admin->events()->paginate(
       page: max(1, (int) $request->getParam('page', 1)),
       per_page: self::PER_PAGE,
       route: 'admin.events.paginate'
   );
   ```

3. `EC::create`: `$event = $admin->events()->new();` no lugar de
   `new Event()` + `user_id` manual (`EC:51-53`).
4. Q1 para `findEvent` (edit/update/destroy/show restritos).
5. T1: segundo admin no populate para ver a diferença.

Armadilhas:

- `currentUser()` é `?User`: o `phpstan` exige tratar `null` mesmo com o
  middleware garantindo sessão; o `return` após `redirectTo` evita o 500.
- `HasMany::paginate` não recebe `queryParams`/`orderBy` (N1/N3): se
  esses cenários já existirem, repasse também lá (`HASMANY:44-55`).
- O painel (N6/N7) passa a ser "meus" ou "todos"? Decida e nomeie.
- `test_index_lists_five_events_per_page` (`ECT:39-57`) cria tudo com
  `$this->admin`: continua verde.

Prova: `EventsControllerTest`; no navegador, logar como `admin2@` (T1)
e ver só os Eventos dele.

### O6. Inscritos em página própria, paginada

Chance: média-baixa. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/o6-inscritos-pagina-propria`.

Pedido típico: "a lista de inscritos sai do `show` e vai para uma página
própria, paginada" (`GET /admin/events/{id}/enrollments`).

Arquivos: `ROUTES:26-35`, `EV:117-120` (relação; falta
`enrollments(): HasMany`), `HASMANY:44-55` (`paginate` com condição),
`PAG:99-102` (`getRouteName`), `PAGES:6,16,22` (links só com `page`),
`core/Router/Router.php:59-72` (`route()` substitui `{id}` **e** `{page}`
se os dois vierem nos params), N1 forma 2 (`queryParams`), `SHOW:28-36`,
O3 (`participants()`), `ACC:85-97`.

Passos:

1. Testes: `ECT` (ou `Admin\EnrollmentsControllerTest`): 7 inscritos,
   `per_page` 5, página 1 com 5 nomes, link para
   `/admin/events/{id}/enrollments/page/2`.
2. `ROUTES` (admin): `GET /admin/events/{id}/enrollments` ->
   `admin.events.enrollments.index` e
   `GET /admin/events/{id}/enrollments/page/{page}` ->
   `admin.events.enrollments.paginate`.
3. `EV`: `enrollments(): HasMany` com
   `$this->hasMany(Enrollment::class, 'event_id')`.
4. `Admin\EnrollmentsController::index`: `findEvent` e

   ```php
   $paginator = $event->enrollments()->paginate(
       page: $page,
       per_page: 5,
       route: 'admin.events.enrollments.paginate'
   );
   ```

   view com nome/e-mail via `$enrollment->user()->get()`.
5. Links de página: o partial só manda `page`; `{id}` ficaria sem valor.
   Com N1 forma 2, passe `queryParams: ['id' => $event->id]`: o
   `route()` usa o `id` para preencher `{id}` e não o repete na query.
   Sem N1, troque `PAGES` por uma cópia que receba o `id`.

Armadilhas:

- `route('x.paginate', ['page' => 2])` com `{id}` na URI deixa `{id}`
  literal no link (`Router.php:63-65`): é o erro mais comum aqui.
- Rota `.../enrollments/page/{page}` tem 6 partes, `.../{id}/edit` tem 4:
  sem conflito (`Route.php:77-100` compara o tamanho).
- `HasMany::paginate` exige `HasMany` em `Event`; `participants()` (O3)
  é `BelongsToMany`, que não pagina.
- Nomes de parâmetro só com `[a-z_]` (`Route.php:90`): `{event_id}` sim,
  `{eventId}` não (vira texto literal e 404).

Prova: teste de controller e HTTP (`ACC`: admin 200, Participante
redirecionado); no navegador, Evento 9 do populate (4 inscritos) com
`per_page` 2.

### O7. `Route::patch` no core

Chance: média-baixa. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/o7-route-patch`.

Pedido típico: "o framework só tem `get/post/put/delete`; acrescente
`patch` e use numa alteração parcial (ex.: mudar só o status)".

Arquivos: `core/Router/Route.php:111-141` (os quatro métodos estáticos,
copiar), `core/Http/Request.php:18` (`_method` aceita qualquer texto),
`core/Router/Route.php:72-75` (`isSameMethod`),
`tests/Unit/Core/Router/RouteTest.php:78-104` (teste do `put`, copiar),
`ROUTES:33-35`, P5 (ação de status), `ACC:104-110`
(`methodOverrideRoutes`).

Passos:

1. Teste em `RouteTest`: cópia de
   `test_should_add_route_to_the_router_method_put` com `PATCH`.
2. `Route.php`, depois de `delete()`:

   ```php
   /**
    * @param string $uri
    * @param mixed[] $action
    * @return Route
    */
   public static function patch(string $uri, $action): Route
   {
       return Router::getInstance()
           ->addRoute(new Route('PATCH', $uri, $action[0], $action[1]));
   }
   ```

   No arquivo, deixe numa linha só, como os vizinhos (`Route.php:111-144`).

3. Uso:

   ```php
   Route::patch(
       '/admin/events/{id}/status',
       [AdminEventsController::class, 'updateStatus']
   )->name('admin.events.status');
   ```

   com form `post` + `<input type="hidden" name="_method" value="PATCH">`.
4. `ACC::methodOverrideRoutes()`: `['PATCH', '/admin/events/1/status']`.

Armadilhas:

- `PATCH` por HTML só existe via `_method` (`Request.php:18`), como
  PUT/DELETE; o navegador nunca manda PATCH nativo em form.
- Mudança em `core/`: mínima, com teste no `tests/Unit/Core`, e PHPDoc
  no padrão dos vizinhos (`Route.php:106-110`).
- A ação parcial continua validando pelo model (`save()` roda
  `validates()` inteiro); se quiser pular, `update()` (`MODEL:189`) e
  diga por quê.

Prova: `RouteTest`; a ação funciona no navegador com o `_method`.

### O8. Status HTTP corretos

Chance: baixa. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/o8-status-http`.

Pedido típico: "Evento inexistente deve responder 404 (não 302) e form
inválido 422" (A8 da 4.1 aplicado ao CRUD).

Arquivos: `EC:137-141` (`eventNotFound`: flash + redirect), `EC:58-62`
e `:89-93` (render do form inválido), `core/Errors/ErrorsHandler.php:21-47`
(exceção HTTP -> `header('HTTP/1.1 404 ...')` com stack trace),
`core/Exceptions/HTTPException.php`, `ECT:163-168` e `ACC:175-183`
(esperam 302), `CTC:92-108`.

Passos:

1. Testes por HTTP (`ACC`): `GET /admin/events/999999` com admin -> 404
   e corpo com "Evento não encontrado"; `POST /admin/events` inválido ->
   422. No `ECT`, `http_response_code()` também funciona em CLI (devolve
   o código definido): zere com `http_response_code(200)` no `setUp`.
2. `eventNotFound()`: `http_response_code(404);` +
   `render('admin/events/not_found', ['title' => 'Evento não
   encontrado'])` (view nova) em vez de flash + redirect. Não use
   `errors/404.phtml`: é a página sem layout do O11.
3. `create`/`update` inválidos: `http_response_code(422);` antes do
   `render`.
4. Ajuste `ECT:163-168` (não há mais `Location`) e `ACC:175-183`.

Armadilhas:

- Chame `http_response_code()` antes do `render` (boa prática). Aqui
  funcionaria depois, porque o `ErrorsHandler` segura a saída com
  `ob_start()` (`core/Errors/ErrorsHandler.php:16`).
- O status fica no processo do PHPUnit: zere no `setUp`, senão um teste
  herda o 404 do anterior. O teste por HTTP continua o mais fiel.
- Lançar `HTTPException` (`Router.php:122` faz isso para rota
  inexistente) dá 404 com **stack trace** na tela (`ErrorsHandler:32-45`):
  não use para "Evento não encontrado"; é feio e expõe caminhos (O11).
- Flash + redirect é o padrão da base e do professor nas demos; 404/422
  é melhoria opcional, diga o custo (testes a mudar).

Prova: `curl -i` com cookie de admin em `/admin/events/999999`.

### O9. `_method` só via POST

Chance: média (falha real, 15 min). Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/o9-method-so-via-post`.

Pedido típico: "GET nunca pode alterar dados". Hoje
`GET /admin/events/16?_method=DELETE` cai no `destroy`: o `Request` lê
`_method` de `$_REQUEST` (`Request.php:18`), que inclui a query string.
Um `<img src="...">` numa página visitada por um admin logado remove um
Evento.

Arquivos: `core/Http/Request.php:16-22` (construtor),
`core/Router/Route.php:72-75` (`isSameMethod`),
`tests/Unit/Core/Http/RequestTest.php:13-25` (`setUp`/`tearDown` com
`$_SERVER`/`$_REQUEST`), `ACC:104-137` (`methodOverrideRoutes`, POST +
`_method`, continua verde), `IDX:30-33`, `FORM:17-19`.

Passos:

1. Testes em `RequestTest`: `REQUEST_METHOD=GET` +
   `$_REQUEST['_method'] = 'DELETE'` -> `getMethod() === 'GET'`; `POST` +
   `$_POST['_method'] = 'DELETE'` -> `DELETE`; `POST` + `_method=GET` ->
   `POST`. Teste HTTP (`ACC`): `GET /admin/events/1?_method=DELETE` com
   admin -> 200 do `show`, Evento continua existindo.
2. `Request.php:18`:

   ```php
   $method = $_SERVER['REQUEST_METHOD'];
   $override = $_POST['_method'] ?? null;
   $allowed = ['PUT', 'PATCH', 'DELETE'];
   if ($method === 'POST' && in_array($override, $allowed, true)) {
       $method = $override;
   }
   $this->method = $method;
   ```

3. Nada muda nos forms (`FORM:17-19`, `IDX:30-33` já usam POST).

Armadilhas:

- `$_REQUEST` mistura GET, POST e cookie: nunca decida método por ele.
- Lista fechada de verbos: `_method=GET` ou `=HEAD` não pode virar
  método.
- Mudança em `core/` com teste unitário; `RequestTest` limpa
  `$_REQUEST`/`$_SERVER` no `tearDown` (`:20-25`): limpe `$_POST` também.
- `Route::isSameMethod` (`Route.php:72-75`) compara strings: `delete`
  minúsculo não casa; mantenha maiúsculas.
- Não resolve CSRF por completo (um form POST em outro site ainda
  funciona): isso é Q5.

Prova: `RequestTest`; no navegador logado, abrir
`/admin/events/16?_method=DELETE` e ver o `show`, não a remoção.

### O10. `Route::resource` ou `Route::prefix` no core

Chance: média-baixa. Tempo: 25-40 min.

Exemplo pronto: `fvp/4.2/exemplos/o10-route-resource-prefix`.

Pedido típico: "um atalho que registre as rotas RESTful de uma vez" ou
"um prefixo `/admin` para o grupo, sem repetir em cada rota" (item de
framework da rubrica).

Arquivos: `core/Router/Route.php:106-149` (`get/post/put/delete`,
`middleware`), `core/Router/RouteWrapperMiddleware.php:15-25` (`group`
conta as rotas antes e depois do callback), `core/Router/Router.php:33-52`
(`reset`, `addRoute`, `getRouteSize`, `getRoute`), `ROUTES:25-35`,
`tests/Unit/Core/Router/RouteTest.php`, `RouterTest.php:19-24`
(`tearDown` zera o singleton por reflexão), `CTC:17-18` (todo teste de
controller carrega `config/routes.php`), `ACC` (rotas por nome).

Passos (`resource`):

1. Teste em `RouteTest`: depois de
   `Route::resource('/admin/events', MockController::class, 'admin.events')`,
   `getRouteSize() === 8`, nomes na ordem `index, paginate, new, create,
   show, edit, update, destroy`, e
   `getRoutePathByName('admin.events.edit', ['id' => 3])` devolve
   `/admin/events/3/edit`. Confira que `new` vem antes de `show`.
2. `Route.php`:

   ```php
   /** @var list<array{0: string, 1: string, 2: string, 3: string}> */
   private const RESOURCE = [
       ['get', '', 'index', 'index'],
       ['get', '/page/{page}', 'index', 'paginate'],
       ['get', '/new', 'new', 'new'],
       ['post', '', 'create', 'create'],
       ['get', '/{id}', 'show', 'show'],
       ['get', '/{id}/edit', 'edit', 'edit'],
       ['put', '/{id}', 'update', 'update'],
       ['delete', '/{id}', 'destroy', 'destroy'],
   ];

   public static function resource(
       string $uri,
       string $ctrl,
       string $name
   ): void {
       foreach (self::RESOURCE as [$verb, $suffix, $action, $route]) {
           self::$verb($uri . $suffix, [$ctrl, $action])
               ->name("{$name}.{$route}");
       }
   }
   ```

3. `ROUTES:25-35` vira
   `Route::resource('/admin/events', AdminEventsController::class,
   'admin.events');` dentro do grupo `admin` (o `group` conta as rotas
   antes e depois, então o middleware chega a todas).
4. Rode a suíte inteira: `ACC` e `ECT` provam que nenhum nome nem
   ordem mudou.

Variante `prefix`: `Route::prefix('/admin')->group(fn () => ...)`. Uma
classe `RoutePrefix` guarda os prefixos numa pilha estática;
`get/post/put/delete` concatenam a pilha na URI; `group` empilha, chama
o callback e desempilha num `finally`. Teste: rota registrada dentro do
grupo tem URI `/admin/events`; registrada fora, não.

Armadilhas:

- A ordem é o ponto: o Router usa a primeira rota que casa
  (`Router.php:109-121`); `new` e `page/{page}` antes de `{id}`.
- Recurso sem alguma ação (sem `destroy`): parâmetro `only`/`except`,
  ou registre essa rota à mão.
- Testes novos: `Router::reset()` no `setUp` e no `tearDown`; o
  singleton vaza entre testes.
- Nome de parâmetro só com `[a-z,_]` (`Route.php:90`).
- `Route::patch` não existe (O7); recurso com PATCH exige criá-lo antes.
- Pilha de prefixo sem `finally`: uma exceção no callback deixa o
  prefixo grudado nas rotas seguintes.
- PHPDoc da constante e dos parâmetros (phpstan nível 6).
- Prefixo com barra final: a rota `'/'` dentro de `/admin` vira
  `/admin/`, que não casa com `/admin`. Tire a barra final do prefixo e
  trate `'/'` e o prefixo vazio.

Prova: `RouteTest`; suíte inteira verde sem mudar nenhum teste.

### O11. Página 404 amigável, sem stack trace fora de development

Chance: baixa. Tempo: 20-25 min.

Exemplo pronto: `fvp/4.2/exemplos/o11-404-amigavel`.

Pedido típico: "não mostre stack trace ao usuário" ou "URL inexistente
deve mostrar uma página de não encontrado". Hoje toda exceção imprime
classe, arquivo, linha e trace (`core/Errors/ErrorsHandler.php:32-45`),
e a mensagem `URI ... not found.` vai para o `<h1>` sem escape.

Arquivos: `core/Errors/ErrorsHandler.php:14-19` (buffer e handlers),
`:21-47` (exceção), `:49-86` (warning ou notice -> 500 com
`debug_print_backtrace`), `config/bootstrap.php:11-12`
(`ErrorsHandler::init()` roda **antes** de `EnvLoader::init()`),
`core/Env/EnvLoader.php:9-16` (grava só em `$_ENV`, sem `putenv`),
`.env.example:1` (`APP_ENV=development`), `docker-compose.yml:66` (só o
`php_test` define `APP_ENV=testing`), `core/Router/Router.php:122`
(`HTTPException` 404), `app/views/errors/` (nova).

Passos:

1. Extraia a decisão para um método testável:
   `ErrorsHandler::viewFor(\Throwable $e, string $env): ?string` devolve
   `'errors/404'` para `HTTPException` 404, `'errors/500'` para o resto
   e `null` em `development` (mantém o trace). Testes em
   `tests/Unit/Core/Errors/ErrorsHandlerTest.php`: 404 e 500 em
   `production`, qualquer um em `development` -> `null`.
2. No `exceptionHandler`, logo depois do `ob_end_clean()` (`:24`) e
   **antes** do `header()` da base (`:27`, que põe a URI digitada na
   linha de status), mande o próprio status e a página:

   ```php
   $env = $_ENV['APP_ENV'] ?? 'production';
   $view = self::viewFor($e, $env);
   if ($view !== null) {
       $code = str_ends_with($view, '404') ? 404 : 500;
       header("HTTP/1.1 {$code} " . ($code === 404
           ? 'Not Found'
           : 'Internal Server Error'));
       require Constants::rootPath()->join("app/views/{$view}.phtml");
       return;
   }
   ```

3. `app/views/errors/404.phtml` e `500.phtml`: HTML completo e simples,
   sem layout e sem `$this`, com link para `/`.
4. No `errorHandler`, o mesmo para warnings e notices fora de
   development.
5. No navegador: `APP_ENV=production` no `.env` local, abra
   `/qualquer-coisa` e veja a página 404; volte para `development`.

Armadilhas:

- `getenv('APP_ENV')` devolve `false` no contêiner `php` (o `.env` só
  vai para `$_ENV`): leia `$_ENV`.
- Erro antes do `EnvLoader` (`.env` ausente): `$_ENV` vazio; trate como
  produção, o padrão seguro.
- Não chame `render()` do `Controller`: precisa de `$title`, layout e
  sessão, e se a view falhar o handler entra em laço. Inclua HTML
  simples.
- `ob_end_clean()` (`:24`) descarta o que já saiu; não imprima nada
  antes do `header`.
- A mensagem da exceção traz a URI digitada, e o `header()` da base a
  repete na linha de status: fora de development, não use a mensagem
  nem no cabeçalho nem na página (ou escape com `htmlspecialchars`).
- O contêiner de teste roda em `testing`: decida se `testing` mostra o
  trace (ajuda a depurar o `ACC`) e diga.
- "Evento não encontrado" do `EC` é redirect com flash (`EC:137-141`),
  não 404; status certos são o O8.

Prova: testes do `viewFor`; com `APP_ENV=production`, `/nao-existe`
mostra a página 404 com status 404 (aba Rede do navegador).

### P1. Código de check-in aleatório (1000-9999) por Evento

Chance: alta. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/p1-codigo-checkin-aleatorio`.

Pedido típico: "cada Evento recebe, ao ser criado, um código numérico
aleatório de 4 dígitos (1000 a 9999), único, gerado pelo backend, que
aparece no detalhe e na listagem". É a transposição direta do pedido
real da FVP 4.1 (P12) para o CRUD: campo gerado no servidor, não
editável pelo form, validado no model.

Arquivos: `SCH:21-38`, `EV:10-21`, `EV:48-58`, `EV:60-82`, `MODEL:139-187`
(`save()`, que `Event` vai sobrescrever), `MODEL:347` (`exists()`),
`VAL:78-94` (`integerMin`) e `VAL:155-205` (`uniqueness`), `EC:14`
(`FIELDS`: o código **não** entra), `EC:49-63`, `IDX:11-26`, `SHOW:6-33`,
`EVT:302-308`, `ECT:85-112` (`:101-112` é o modelo de "ignora campo
enviado pelo form").

Passos:

1. Testes em `EVT`: depois de `createEvent()`, `checkin_code` entre
   1000 e 9999; salvar de novo não troca o código; dois Eventos não
   compartilham código (`Event::exists(['checkin_code' => ...])` só
   acha um). Em `ECT`: `create` com `'checkin_code' => '1'` no form
   gera outro valor (cópia de `:101-112`).
2. `SCH`: `checkin_code SMALLINT UNSIGNED NOT NULL,` e
   `UNIQUE KEY uq_events_checkin_code (checkin_code)`.
3. `EV`: `@property int|string $checkin_code`, `'checkin_code'` em
   `$columns`, e:

```php
public function save(): bool
{
    $code = $this->checkin_code;

    if ($this->newRecord() && ($code === null || $code === '')) {
        $this->checkin_code = self::newCheckinCode();
    }

    return parent::save();
}

public static function newCheckinCode(): int
{
    do {
        $code = random_int(self::CHECKIN_CODE_MIN, self::CHECKIN_CODE_MAX);
    } while (self::exists(['checkin_code' => $code]));

    return $code;
}
```

   com `public const CHECKIN_CODE_MIN = 1000;` e `MAX = 9999`. Em
   `validates()`:

   ```php
   Validations::integerMin('checkin_code', $this, self::CHECKIN_CODE_MIN) &&
       $this->validatesCheckinCodeMax() &&
       Validations::uniqueness('checkin_code', $this);
   ```

   (método privado: `(int) $this->checkin_code > self::CHECKIN_CODE_MAX`
   -> `addError('checkin_code', 'deve ser menor ou igual a 9999!')`;
   ou crie `integerMax` em `VAL`, cenário M2).
4. `EC:14`: **não** acrescente em `FIELDS`. `IDX` e `SHOW`: mostre
   `<?= (int) $event->checkin_code ?>` (coluna "Código").
5. `./run db:populate`: os 16 Eventos ganham código sozinhos (o
   `save()` gera).

Armadilhas:

- Gerar no controller: "regra de negócio no model"; o professor
  recusa. Gerar na view: nunca.
- `rand()`/`mt_rand()` servem, mas `random_int()` é o correto para
  valor imprevisível; diga isso se perguntarem.
- Sem o laço de unicidade há colisão (9000 valores); com `UNIQUE KEY`
  no schema a colisão vira `PDOException` no `INSERT`. O `uniqueness`
  em `validates()` cobre quem tenta forçar um código repetido via
  `update`.
- Como o código é gerado em `save()`, `isValid()` chamado antes de
  `save()` em um registro novo acusa `checkin_code` vazio: o teste
  `test_empty_event_should_have_error_in_every_field` (`EVT:86-99`)
  pode passar a listar `checkin_code`; ajuste a expectativa. Se
  preferir, gere no construtor (padrão `US:28-35`) e não em `save()`.
- `exists()` (`MODEL:347-350`) usa `where`: aceita uma ou mais
  condições de igualdade.
- Código **não** vai ao form nem ao `FIELDS`; se o professor quiser
  "regenerar", é uma ação `PUT /admin/events/{id}/checkin_code` (O1).
- `ECT:101-112` e os `validParams()` não precisam mudar.

Prova: `EventTest`, `EventsControllerTest`; no navegador, criar um
Evento e ver o código no `show` e na listagem; `db:console` com
`SELECT id, checkin_code FROM events` mostra 16 valores distintos.

### P2. Inscrição do Participante (`POST /events/{id}/enrollments`)

Chance: média-alta (RF05 e a feature natural seguinte; não "alta" porque
o NxN é o tema da 4.4, que o professor pode reservar). Tempo: 30-40 min.

Exemplo pronto: `fvp/4.2/exemplos/p2-inscricao-participante`.

Pedido típico: "o Participante logado se inscreve em um Evento, se
houver vaga e se ainda não estiver inscrito". O model `Enrollment` e a
tabela `event_user` já existem (`EN`, `SCH:43-53`); falta a rota, o
controller, a validação e a tela. Pressupõe O2 (tela do Participante)
ou um botão no `show` do admin para demonstrar.

Arquivos: `EN:15-29` (sem `validates()`), `SCH:43-53` (`UNIQUE
(event_id, user_id)` em `:48`), `EV:125-140` (`availableSpots`),
`US:52-55` (`events()`, modelo de `HasMany`), `ROUTES:18-20`,
`CTRL:20-27` (`currentUser()`), `EC:96-111` (modelo de ação que
redireciona com flash), `MODEL:347` (`exists`),
`tests/Unit/Models/Events/EnrollmentTest.php:70-77` (hoje espera
`PDOException` na duplicata), `ECT:17-31`, `ACC:67-78`.

Passos:

1. Testes: `EnrollmentTest`: inscrição válida; duplicata agora
   `save()` falso com `errors('base')` (troque o `expectException` de
   `:70-77`); Evento lotado (`capacity => 1` + uma Inscrição) recusa.
   `EnrollmentsControllerTest` (novo, logando um Participante):
   `create` grava e redireciona para `events.show`; duplicata não
   grava e flash `danger`.
2. `EN::validates()`:

```php
public function validates(): void
{
    Validations::notEmpty('event_id', $this);
    Validations::notEmpty('user_id', $this);

    if (!$this->newRecord()) {
        return;
    }

    $same = ['event_id' => $this->event_id, 'user_id' => $this->user_id];

    if (Enrollment::exists($same)) {
        $this->addError('base', 'você já está inscrito neste evento!');
        return;
    }

    $event = Event::findById((int) $this->event_id);
    if ($event !== null && $event->availableSpots() === 0) {
        $this->addError('base', 'não há vagas neste evento!');
    }
}
```

   Import `Lib\Validations`. `exists()` com duas condições usa
   `findBy` (`MODEL:335`), que aceita várias.
3. `ROUTES`, dentro de `auth`:

   ```php
   Route::post(
       '/events/{id}/enrollments',
       [EnrollmentsController::class, 'create']
   )->name('events.enrollments.create');
   ```
4. `app/Controllers/EnrollmentsController.php`:

```php
public function create(Request $request): void
{
    $event = Event::findById((int) $request->getParam('id'));
    if ($event === null) {
        FlashMessage::danger('Evento não encontrado!');
        $this->redirectTo(route('events.index'));
        return;
    }

    $enrollment = new Enrollment([
        'event_id' => $event->id,
        'user_id' => $this->currentUser()?->id,
    ]);

    if ($enrollment->save()) {
        FlashMessage::success('Inscrição realizada com sucesso!');
    } else {
        $error = $enrollment->errors('base');
        FlashMessage::danger($error ?? 'Não foi possível se inscrever!');
    }

    $this->redirectTo(route('events.show', ['id' => $event->id]));
}
```

5. View `events/show.phtml` (O2): form `method="post"` para
   `route('events.enrollments.create', ['id' => $event->id])` com o
   botão "Inscrever-se"; mostre "Você já está inscrito" quando
   `Enrollment::exists([...])` (ou `User::isEnrolledIn(Event)` no
   model) e "Lotado" quando `availableSpots() === 0`.
6. `US`:

   ```php
   public function enrollments(): HasMany
   {
       return $this->hasMany(Enrollment::class, 'user_id');
   }
   ```

   e `isEnrolledIn(Event $event): bool`.
7. Acesso: teste HTTP `POST /events/1/enrollments` sem sessão -> 302
   `/login`.

Armadilhas:

- `user_id` vindo do form: nunca; sempre `currentUser()->id`
  (`ECT:101-112` mostra o padrão para Evento).
- Concorrência: duas inscrições simultâneas podem passar na contagem;
  o `UNIQUE` do schema barra a duplicata com `PDOException`, trate como
  em `EV:166-172`. Vaga a mais por corrida é aceitável aqui, saiba dizer.
- `EnrollmentTest:70-77` espera exceção: com a validação ele passa a
  falhar; atualize.
- `enrollmentsCount()` e `safeDestroy()` (`EV:125-173`) já consideram
  as Inscrições: Evento com inscritos continua não removível.
- Rota com verbo na URI (`/events/{id}/enroll`) funciona, mas a
  rubrica cobra nomes RESTful: recurso aninhado `enrollments` com
  `create`/`destroy` (P3).
- `redirectTo` sem `return` depois (no "não encontrado") segue
  executando no teste (o `CTC` só ecoa o `Location`).
- Admin também pode se inscrever? Decida com o professor; a
  validação não impede.

Prova: testes; no navegador, `user@example.com` abre `/events/<id>` de
um Evento sem inscrição dele (ex.: o 3), inscreve-se, vê a mensagem;
tentar de novo dá "já está inscrito". Para ver "não há vagas" no Evento 1
(3/3) entre com `fernanda@example.com`: `user@`, `mariana@` e `carlos@`
já estão inscritos nele (`POP:156-157`) e receberiam "já está inscrito".

### P3. Cancelar inscrição (`DELETE /events/{id}/enrollments`)

Chance: média. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/p3-cancelar-inscricao`.

Pedido típico: "o Participante pode cancelar a própria inscrição".
Depende de P2.

Arquivos: os de P2, `MODEL:215-231` (`destroy()`), `MODEL:335`
(`findBy`), `IDX:30-33` (form com `_method=DELETE`, modelo),
`core/Http/Request.php:18`.

Passos:

1. Teste de controller: inscrito cancela, `Enrollment::all()` vazio,
   flash `success`; não inscrito recebe `danger`.
2. `ROUTES` (grupo `auth`):

   ```php
   Route::delete(
       '/events/{id}/enrollments',
       [EnrollmentsController::class, 'destroy']
   )->name('events.enrollments.destroy');
   ```
3. `EnrollmentsController::destroy`: `Enrollment::findBy([...])` com
   `event_id` da rota e `user_id` do logado;
   `null` -> `danger('Você não está inscrito neste evento!')`; senão
   `->destroy()` e `success('Inscrição cancelada!')`; redireciona para
   `events.show`.
4. View: form `method="post"` +
   `<input type="hidden" name="_method" value="DELETE">`
   (como `IDX:30-33`) no lugar do botão "Inscrever-se" quando inscrito.

Armadilhas:

- Só a própria inscrição: a busca inclui `user_id` do logado; nunca
  `enrollment_id` do form.
- Sem o `_method=DELETE` o POST cai na rota `POST /events/{id}/enrollments`
  de P2 (`create`), não em `destroy` (`Request.php:18`); sem P2 seria 404.
- Após P9 (lista de espera), cancelar deve promover o primeiro da
  fila.

Prova: testes; no navegador, inscrever e cancelar.

### P4. Confirmar presença pelo código do Evento

Chance: média-baixa (empilha P1 e P2; sozinho passa dos 40 min).
Tempo: 30-40 min.

Exemplo pronto: `fvp/4.2/exemplos/p4-confirmar-presenca-codigo`.

Pedido típico: "o Participante inscrito confirma presença digitando o
código do Evento (o número de 4 dígitos); o backend valida e registra
a confirmação". Reúne P1 (código gerado) e P2 (inscrição). É o padrão
"número gerado pelo backend + validação do número" da FVP real da 4.1.

Arquivos: `EN:17-18` (`$columns`), `SCH:43-53`, `EV` (P1), P2,
`MODEL:189-213` (`update()`, grava sem validar), `core/Http/Request.php:57-60`
(`getParam`), `tests/Unit/Models/Events/EnrollmentTest.php`.

Passos:

1. Testes: `EnrollmentTest`: `confirm('1234')` com o código certo grava
   `confirmed_at`; errado devolve `false` e não grava; chamar duas vezes
   mantém a primeira data. Controller: não inscrito recebe `danger`;
   código em formato errado (`'12a'`, array) recebe `danger` sem 500.
2. `SCH:43-53`: `confirmed_at TIMESTAMP NULL,` em `event_user`;
   `EN:18`: `'confirmed_at'` em `$columns`;
   `@property string|null $confirmed_at`.
3. `EN`:

```php
public function confirm(string $code): bool
{
    $event = Event::findById((int) $this->event_id);

    if ($event === null || $this->confirmed_at !== null) {
        return false;
    }

    if (!ctype_digit($code) || (int) $code !== (int) $event->checkin_code) {
        $this->addError('base', 'código inválido!');
        return false;
    }

    return $this->update(['confirmed_at' => date('Y-m-d H:i:s')]);
}

public function isConfirmed(): bool
{
    return $this->confirmed_at !== null;
}
```

4. `ROUTES` (`auth`):

   ```php
   Route::post(
       '/events/{id}/enrollments/confirmation',
       [EnrollmentsController::class, 'confirm']
   )->name('events.enrollments.confirm');
   ```

   O exemplo usa `POST /events/{id}/checkin` (`events.checkin`, ação
   `checkin`), mais curto; as duas formas valem.
5. `EnrollmentsController::confirm`: acha a inscrição do logado
   (`findBy`, como P3); `$code = $request->getParam('code');` e
   `$code = is_string($code) ? trim($code) : '';`;
   `confirm($code)` -> flash `success('Presença confirmada!')` ou
   `danger($enrollment->errors('base') ?? 'Não foi possível confirmar!')`;
   redireciona para `events.show`.
6. View do Participante: form com
   `<input name="code" inputmode="numeric" maxlength="4">`
   visível só para inscrito não confirmado; "Presença confirmada em
   dd/mm" quando confirmado.
7. Admin: no `SHOW`, "confirmados/inscritos"
   (`Enrollment::where(['event_id' => $id])`
   filtrando `isConfirmed()`).

Armadilhas:

- Comparar `'1234' === 1234` (string com int) é sempre falso; converta
  os dois para `int` depois de `ctype_digit`.
- Não revele o código no erro nem na página do Participante; o código
  o admin fornece no local.
- `update()` não valida (`MODEL:189`): bom aqui (não repete a checagem
  de vagas), mas lembre de dizer que é proposital.
- Limite de tentativas: se o professor pedir, guarde
  `$_SESSION['checkin_attempts'][$event->id]` (padrão do cenário B9 da
  4.1).
- `getParam('code')` pode vir array: `is_string` antes do `trim`.
- `TIMESTAMP NULL` precisa estar em `$columns`, senão `save()` grava
  `NULL` por omissão: ok, mas `confirmed_at` nunca persistiria via
  `save()` sem a coluna na lista.

Prova: testes; no navegador, inscrever-se, digitar código errado
(mensagem), digitar o certo (lido no `show` do admin) e ver a
confirmação.

### P5. Status do Evento: cancelar e encerrar

Chance: alta. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/p5-status-cancelar-encerrar`.

Pedido típico: "o administrador pode cancelar um Evento; cancelado
aparece com destaque, não aceita inscrição nem edição". Variante:
"encerrar" (`finished`) manualmente ou por data.

Arquivos: `SCH:21-38`, `EV:25-45` (constantes com rótulo, modelo),
`EV:48-58`, `EV:60-82` (`inclusion`, `EV:66-67`), `US:28-35`
(construtor com valor padrão, modelo), `ROUTES:26-35`, `EC:96-111`
(ação curta com flash, modelo), `IDX:22-33`, `SHOW:6-36`, `ECT:242-251`,
`ACC:85-97`.

Passos:

1. Testes: `EVT`: Evento novo nasce `scheduled`; `cancel()` muda para
   `canceled` e persiste; `status` fora da lista é inválido
   (`inclusion`). `ECT`: `put('cancel', ...)` com `id` -> `Location:
   /admin/events/{id}` e status cancelado; cancelar de novo dá `danger`.
2. `SCH`: `status ENUM('scheduled', 'canceled', 'finished')`
   `NOT NULL DEFAULT 'scheduled',`.
3. `EV`: `'status'` em `$columns`, construtor (cópia de `US:28-35`) com
   `if ($this->status === null) { $this->status = 'scheduled'; }`, e:

   ```php
   public const STATUSES = [
       'scheduled' => 'Agendado',
       'canceled' => 'Cancelado',
       'finished' => 'Encerrado',
   ];
   ```

   Em `validates()`:

   ```php
   Validations::notEmpty('status', $this) &&
       Validations::inclusion('status', $this, array_keys(self::STATUSES));
   ```

   e:

```php
public function cancel(): bool
{
    if ($this->status !== 'scheduled') {
        $this->addError('base', 'só um evento agendado pode ser cancelado!');
        return false;
    }

    $this->status = 'canceled';

    return $this->save();
}

public function isCanceled(): bool
{
    return $this->status === 'canceled';
}
```

4. `ROUTES` (grupo admin, depois de `update`):

   ```php
   Route::put(
       '/admin/events/{id}/cancel',
       [AdminEventsController::class, 'cancel']
   )->name('admin.events.cancel');
   ```
5. `EC::cancel(Request $request)`: `findEvent` (`EC:132-135`), não
   achou -> `eventNotFound()`; `$event->cancel()` ->
   `success('Evento cancelado!')` ou `danger($event->errors('base') ?? ...)`;
   `redirectTo(route('admin.events.show', ...))`.
6. `IDX`: badge `<span class="badge text-bg-secondary">` com
   `<?= Event::STATUSES[$event->status] ?>` dentro,
   ao lado do título; botão "Cancelar" como form `post` + `_method=PUT`
   (não confundir com o link "Cancelar" do form, `FORM:92`: renomeie
   para "Cancelar Evento"). `SHOW`: linha "Situação".
7. Bloqueios: `EN::validates()` (P2) recusa inscrição em cancelado
   (`addError('base', 'evento cancelado!')`); edição/remoção em Q2. O
   exemplo já bloqueia `edit`/`update` de cancelado aqui e traz também
   `finish()` com `PUT /admin/events/{id}/finish` (`admin.events.finish`).
8. `ACC:85-97`: `'admin.events.cancel' => ['PUT', '/admin/events/1/cancel']`.
9. `./run db:populate`; `POP` não precisa mudar (construtor dá o
   padrão).

Armadilhas:

- `DEFAULT` do schema não vale no `save()` (insere todas as colunas,
  `MODEL:143-158`): por isso o padrão no construtor.
- `status` **não** entra em `FIELDS` (`EC:14`): só a ação muda.
- `cancel()` chama `save()`, que roda `validates()` inteiro: um Evento
  com capacidade abaixo das inscrições ou data inválida não cancela.
  Se o professor quiser cancelar mesmo assim, use
  `update(['status' => 'canceled'])` (`MODEL:189`) e explique.
- Rota PUT com `{id}` e sufixo: ordem não importa (`:31` só casa
  `GET`), mas mantenha junto das outras.
- `finished` por data: `isFinished(): bool` comparando `starts_on <
  date('Y-m-d')` sem gravar; ou tarefa `./run` que atualiza (T do 4.1,
  G3).
- Textos dos testes: `ECT` compara `Location` exato.

Prova: testes; no navegador, cancelar um Evento sem inscrições, ver o
badge, tentar inscrever-se (P2) e receber a recusa.

### P6. Duplicar Evento

Chance: média-alta. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/p6-duplicar-evento`.

Pedido típico: "um botão 'Duplicar' cria uma cópia do Evento (título
com '(cópia)', data daqui a 7 dias, sem inscrições) e abre a edição".

Arquivos: `EV:48-58` (`$columns`), `MODEL:20-44` (`$attributes` é
**privado** em `Model`: copie via `$this->$column`), `MODEL:139-187`,
`EC:49-63` (`create`, modelo de redirect), `ROUTES:26-35`, `IDX:28-33`,
`ECT:85-99`, `ACC:85-97`, `EVT`. Os testes de model do exemplo estão em
`s3-teste-unitario-model`, empilhado sobre o `p6-...`; a rota POST entra
em `eventRoutes()` (`ACC:85-97`), não em `methodOverrideRoutes()`.

Passos:

1. Testes: `EVT`: `duplicate()` devolve Evento novo (`newRecord()`),
   mesmo `kind`/`audience`/`user_id`, título com "(cópia)", data +7
   dias, `enrollmentsCount() === 0` após salvar. `ECT`:
   `post('duplicate', ...)` com `id` -> `Event::all()` tem 2 e `Location:
   /admin/events/{novo}/edit`.
2. `EV`:

```php
public function duplicate(): Event
{
    $copy = new Event();

    foreach (static::$columns as $column) {
        $copy->$column = $this->$column;
    }

    $copy->title = "{$this->title} (cópia)";
    $copy->starts_on = date('Y-m-d', strtotime('+7 days'));

    return $copy;
}
```

   Com P1, zere `checkin_code` (`$copy->checkin_code = null;`) para
   gerar outro; com P5, `status = 'scheduled'`.
3. `ROUTES` (admin):

   ```php
   Route::post(
       '/admin/events/{id}/duplicate',
       [AdminEventsController::class, 'duplicate']
   )->name('admin.events.duplicate');
   ```
4. `EC::duplicate(Request $request)`: `findEvent`;
   `$copy = $event->duplicate();`
   `if ($copy->save())` -> `success('Evento duplicado! Revise os dados.')`
   e `redirectTo(route('admin.events.edit', ['id' => $copy->id]))`;
   senão `danger` e volta ao `show`.
5. `IDX:28-33`: form `method="post"` com botão "Duplicar".
6. `ACC`: a rota em `eventRoutes()`.

Armadilhas:

- `clone $this` copia `id` e o objeto continua "existente"
  (`newRecord()` falso): `save()` faria `UPDATE` no original. Construa
  um `new Event()`.
- Título com 255 caracteres + " (cópia)" estoura o `VARCHAR(255)`:
  `mb_substr($this->title, 0, 247)`.
- Data: se copiar `starts_on` de um Evento passado, a validação
  (`EV:75-77`) recusa o novo; por isso `+7 days`.
- `user_id`: o original ou o admin logado? Mantenha o original salvo
  pedido contrário; com Q1 use o logado.
- Redirecionar para `edit` sem flash deixa o usuário sem saber que
  foi criado.

Prova: testes; no navegador, duplicar, ver a edição aberta com
"(cópia)" e a listagem com 17 Eventos.

### P7. "Meus eventos" do Participante

Chance: média-alta. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/p7-meus-eventos`.

Pedido típico: "uma página com os Eventos em que o Participante logado
está inscrito". Depende de P2 (ou do populate, que já tem Inscrições).

Arquivos: `US:52-55` (`events()`, modelo de `HasMany`), `HASMANY:19-22`
(`get()`), `EN:20-23` (`event()`), `ROUTES:18-20`, `POP:156-177`
(Inscrições do populate: `user@example.com` está nos Eventos 1, 2, 4 e
9), `tests/Integration/Access/DashboardAccessTest.php`, `ECT:17-31`.

Passos:

1. Testes: controller logando `user@` com duas Inscrições e uma de
   outro Participante: a resposta tem os dois títulos e não o terceiro.
   Acesso: sem sessão -> `/login`.
2. `US`: `enrollments(): HasMany` com
   `$this->hasMany(Enrollment::class, 'user_id')` (código em P2).
3. `ROUTES` (`auth`):

   ```php
   Route::get('/enrollments', [EnrollmentsController::class, 'index'])
       ->name('enrollments.index');
   ```
4. `EnrollmentsController::index`:
   `$enrollments = $this->currentUser()?->enrollments()->get() ?? [];`
   `$title = 'Meus eventos';` `render('enrollments/index', ...)`.
5. `app/views/enrollments/index.phtml`: tabela com
   `$enrollment->event()->get()` (título, data, local), link para
   `events.show` (O2) e botão cancelar (P3). `NAV`: link "Meus eventos".

Armadilhas:

- `get()` devolve `array<Model>`; para o `phpstan` em `app/`, declare
  `/** @var array<int, Enrollment> $enrollments */`.
- N+1 (`event()->get()` por linha): aceitável, saiba dizer; a
  alternativa é `BelongsToMany` em `User` para `events` via
  `event_user` (`MODEL:365`), mas o nome `events()` já é usado para os
  Eventos criados (`US:52-55`): chame de `enrolledEvents()`.
- Tabela vazia: mensagem "Você não está inscrito em nenhum evento".
- `$title` no `compact`.

Prova: testes; no navegador, `user@example.com` vê 4 Eventos.

### P8. Exportar a listagem em CSV

Chance: média. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/p8-exportar-csv`.

Pedido típico: "um botão que baixa os Eventos em CSV" (`GET
/admin/events/export`).

Arquivos: `ROUTES:26-35` (antes de `{id}`, `:31`), `EC`, `CTRL:32-39`
(`render`, que **não** será usado), `CTC:70-84` (captura a saída com
`ob_start`), `IDX:1-3`, `ACC:85-97`, `ECT:39-57` (modelo do teste).

Passos:

1. Teste em `ECT`: `get('export', ...)` com dois Eventos: a resposta
   começa com a linha de cabeçalho e contém os títulos.
2. `ROUTES`, antes de `/admin/events/{id}`:

   ```php
   Route::get(
       '/admin/events/export',
       [AdminEventsController::class, 'export']
   )->name('admin.events.export');
   ```
3. `EC::export(): void`:

```php
public function export(): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="events.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'id', 'title', 'kind', 'starts_on',
        'location', 'capacity', 'enrollments',
    ]);

    foreach (Event::all() as $event) {
        fputcsv($out, [
            $event->id, $event->title, $event->kind, $event->starts_on,
            $event->location, $event->capacity, $event->enrollmentsCount(),
        ]);
    }

    fclose($out);
}
```

4. Botão "Exportar CSV" em `IDX:1-3`. `ACC`: a rota em `eventRoutes()`.

Armadilhas:

- Não chame `render()` (layout HTML dentro do CSV) nem `exit` (mata o
  teste; o `CTC` precisa que a ação retorne).
- Rota depois de `{id}`: `export` vira um `id` e cai em "Evento não
  encontrado" (o teste de controller não pega; o HTTP sim).
- Excel em pt-BR espera `;`: `fputcsv($out, $row, ';')` se o
  professor abrir no Excel; diga a diferença.
- BOM UTF-8 (`"\xEF\xBB\xBF"`) ajuda o Excel com acentos; opcional.
- `header()` em teste CLI não falha, mas se já houve saída dá
  "headers already sent" no navegador: a ação não pode ecoar antes.
- Versão JSON: O4.

Prova: teste; no navegador, clicar e abrir o arquivo.

### P9. Lista de espera quando lotado

Chance: média-baixa. Tempo: 35-40 min.

Exemplo pronto: `fvp/4.2/exemplos/p9-lista-de-espera`.

Pedido típico: "se o Evento estiver lotado, a inscrição entra numa
lista de espera; quando alguém cancela, o primeiro da fila é
promovido". Depende de P2 e P3.

Arquivos: `SCH:43-53`, `EN:17-18`, `EN::validates()` (P2), `EV:125-140`
(`enrollmentsCount` deve contar só confirmados), `EV:60-82`
(`validatesCapacityAgainstEnrollments`, `EV:104-115`), `POP:156-177`,
`MODEL:300-333`.

Passos:

1. Testes: Evento com 1 vaga: 1ª inscrição `confirmed`, 2ª `waiting`;
   cancelar a 1ª promove a 2ª; `enrollmentsCount()` ignora `waiting`.
2. `SCH`: `status ENUM('confirmed', 'waiting') NOT NULL DEFAULT 'confirmed'`
   em `event_user`; `EN`: `'status'` em `$columns`, construtor com
   padrão (`US:28-35`), `STATUSES`.
3. `EN::validates()` (P2): em vez de recusar sem vaga, defina
   `$this->status = 'waiting'` quando `availableSpots() === 0`.
4. `EV:131`:
   `Enrollment::where(['event_id' => $this->id, 'status' => 'confirmed'])`.
5. `EV::promoteFirstWaiting(): void`: SQL no model,

   ```sql
   SELECT id FROM event_user
   WHERE event_id = :id AND status = 'waiting'
   ORDER BY created_at, id LIMIT 1
   ```

   depois `Enrollment::findById(...)->update(['status' => 'confirmed'])`.
   Chamado por `EnrollmentsController::destroy` (P3) após remover uma
   inscrição `confirmed`; ou, como no exemplo, dentro de
   `Enrollment::destroy()` sobrescrito (promove quando a removida era
   `confirmed`), o que dispensa P3 na pilha.
6. Views: "Você está na lista de espera (posição N)".

Armadilhas:

- `where` só faz igualdade e sem `ORDER BY`: a promoção precisa de SQL
  próprio no model.
- `created_at` tem precisão de segundo: desempate por `id`.
- `safeDestroy()` (`EV:151-173`) conta todas as Inscrições: um Evento
  só com fila continua não removível; decida se é o desejado.
- Populate: as 14 Inscrições ganham `confirmed` pelo construtor; o
  Evento 1 (3/3) fica lotado como antes.

Prova: testes; no navegador, inscrever um 4º Participante no Evento 1
e ver "lista de espera".

### P10. Comentários do Evento (recurso filho 1xN)

Chance: média. Tempo: 35-40 min.

Exemplo pronto: `fvp/4.2/exemplos/p10-comentarios-evento`.

Pedido típico: "o Participante pode deixar um comentário no Evento;
o detalhe lista os comentários". É um CRUD mínimo aninhado (prévia da
4.3: relação 1xN) e o cenário mais longo do catálogo.

Arquivos: `SCH:40-53` (tabela filha com FKs, modelo), `EN:1-29` (model
filho com `BelongsTo`, modelo), `US:52-55` (`HasMany`), `EV:142-173`
(`safeDestroy`: regra para filhos), `ROUTES:18-20`, P2 (controller
aninhado), `tests/Unit/Models/Events/EnrollmentTest.php` (modelo de
teste de model filho), `ACC`.

Passos:

1. Testes: `CommentTest` (válido; `body` vazio inválido; 501
   caracteres inválido; `event()->get()`); controller `create` grava e
   redireciona para `events.show`; acesso sem sessão.
2. `SCH`:

```sql
CREATE TABLE comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    event_id   INT NOT NULL,
    user_id    INT NOT NULL,
    body       TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_event FOREIGN KEY (event_id)
        REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE RESTRICT
);
```

   e `DROP TABLE IF EXISTS comments;` no topo (`SCH:3-5`).
3. `app/Models/Comment.php`: `$table = 'comments'`,
   `$columns = ['event_id', 'user_id', 'body']`, `event()` e `user()`
   (`BelongsTo`), `validates()` com `notEmpty('body')` e `maxLength`
   (M2). `EV`: `comments(): HasMany` com
   `$this->hasMany(Comment::class, 'event_id')`.
4. `ROUTES` (`auth`):

   ```php
   Route::post('/events/{id}/comments', [CommentsController::class, 'create'])
       ->name('events.comments.create');
   ```

5. `CommentsController::create`: acha o Evento; `new Comment([...])` com
   `event_id` do Evento, `user_id` do logado e `body`
   com `$body = $request->getParam('comment')['body'] ?? null` validado
   como escalar (`is_array($params)`, `is_string`); `save()` -> flash e
   redirect para `events.show`; inválido -> `danger` com
   `errors('body')`.
6. View do Participante (O2): lista `$event->comments()->get()` (nome
   via `->user()->get()->name`, `nl2br(htmlspecialchars($comment->body))`)
   e form `<textarea name="comment[body]">`. Sem O2, o exemplo cria uma
   tela mínima própria (`GET /events/{id}`, `events.show`,
   `app/views/events/show.phtml`): se depois aplicar O2, fique com uma
   só rota/view desse nome.
7. Remoção do pai: `ON DELETE CASCADE` apaga comentários; documente em
   `safeDestroy()` ou bloqueie como Inscrições (regra explícita, pedida
   pelo professor ao grupo 6).

Armadilhas:

- Sem `DROP TABLE IF EXISTS comments` antes de `events`, o `migrate()`
  falha por FK na 2ª execução.
- `created_at` não está em `$columns`: o banco preenche; para exibir
  a data, acrescente a coluna em `$columns` **só se** aceitar que
  `save()` grave `NULL`... não: deixe fora e leia com SQL próprio, ou
  inclua e preencha `date('Y-m-d H:i:s')` no construtor.
- Escape do `body` na view: XSS.
- `comment[body]` pode vir array: valide antes do `new Comment`.

Prova: testes; no navegador, comentar e ver na lista.

### P11. Soft delete (arquivar Evento)

Chance: baixa. Tempo: 30-40 min.

Exemplo pronto: `fvp/4.2/exemplos/p11-soft-delete-arquivar`.

Pedido típico: "em vez de remover, arquive o Evento; arquivados não
aparecem na listagem e podem ser restaurados".

Arquivos: `SCH:21-38`, `EV:48-58`, `EV:151-173` (`safeDestroy`),
`MODEL:261-282` (`all()` sem filtro), `PAG:147-169` (`buildConditions` e
`bindConditions`, só igualdade: `IS NULL` não dá), `EC:96-111`,
`ROUTES:26-35` (rota `archived` antes de `{id}`, `restore`), `IDX`,
`ECT:242-251`, `EVT:634-680`, `ACC:85-110`, `ACT/DeleteEventCest.php`.
Terceira opção (a do exemplo): `deleted_at DATETIME NULL` e o
`Paginator` aprende `coluna IS NULL` quando a condição vale `null`
(`buildConditions` e `bindConditions` juntos), ficando em `lib/`, não em
`core/`.

Passos:

1. Testes: `archive()` marca e `Event::active()` não traz; `restore()`
   volta; `destroy` do controller arquiva em vez de apagar.
2. `SCH`: `archived TINYINT(1) NOT NULL DEFAULT 0` (um flag de
   igualdade, para o `Paginator` filtrar com `conditions: ['archived' => 0]`,
   N1 forma 2) ou `archived_at TIMESTAMP NULL` (precisa de SQL
   próprio no model).
3. `EV`: `'archived'` em `$columns`, padrão `0` no construtor,
   `archive(): bool` (`update(['archived' => 1])`), `restore()`,
   `isArchived()`, `public static function active(): array` (SQL
   `WHERE archived = 0`, `@return array<static>`) e `archived()`.
4. `EC::destroy`: `$event->archive()` em vez de `safeDestroy()`;
   `index` usa `conditions: ['archived' => 0]` (N1 forma 2) e uma
   rota `GET /admin/events/archived` (O1) com `PUT .../restore`.

Armadilhas:

- `Event::all()` e `findById` do core continuam vendo arquivados;
  cada uso em `app/` precisa decidir. Não altere o core sem pedido.
- `DeleteEventCest` e `ECT:242-261` esperam remoção real: adapte.
- Evento arquivado com Inscrições: as Inscrições continuam; defina a
  regra.

Prova: testes; no navegador, arquivar e restaurar.

### P12. Código aleatório no login (a FVP real da 4.1)

Chance: média-baixa (já foi pedido na 4.1; o padrão pode voltar em

Exemplo pronto: `fvp/4.2/exemplos/p12-codigo-aleatorio-login`.
outro lugar, ver P1 e P4). Tempo: 30-40 min.

Pedido real da FVP da 4.1 (29/10): "depois de validar e-mail e senha,
o backend gera um número aleatório de 1000 a 9999 e o devolve; o
usuário informa esse número e só então o login é concluído; o foco é a
validação do login pelo código". Não estava no catálogo da 4.1; a
lição é que o professor pede uma feature pequena com **valor gerado no
servidor e validado no servidor**.

Arquivos: `app/Controllers/AuthenticationsController.php:19-32`
(`authenticate`: `:24` valida a senha, `:25` chama `Auth::login`, `:27`
redireciona), `lib/Authentication/Auth.php:9-13` (`login`), `ROUTES:13-15`,
`app/views/authentications/new.phtml`, `US:19-20` (`$columns`),
`SCH:7-17`,
`tests/Integration/Controllers/AuthenticationsControllerTest.php:32-54`
(`test_authenticate_with_valid_credentials_should_login` espera
`Auth::check()` verdadeiro e `Location: /dashboard`),
`tests/Acceptance/authentications/LoginCest.php:31-38` e
`tests/Support/Helper/LoginHelper.php:11-19` (`login()` clica em
"Entrar" e os Cests esperam `/dashboard`), `ACC:67-78` (`loggedClient`
espera 302 para `/dashboard` logo após o POST).

Passos (versão com o código no model, a mais defensável):

1. Testes em `AuthenticationsControllerTest`: senha certa ->
   `Auth::check()` **falso**, `Location: /login/code`, e
   `User::findByEmail(...)->login_code` entre 1000 e 9999; `POST
   /login/code` com o código certo -> `Auth::check()` verdadeiro e
   `Location: /dashboard`; errado -> falso, `Location: /login/code`;
   sem código pendente -> `Location: /login`.
2. `SCH:7-17`: `login_code SMALLINT UNSIGNED NULL,`
   `login_code_expires_at TIMESTAMP NULL`;
   `US:20`: as duas colunas em `$columns` e `@property`.
3. `US`:

```php
public function generateLoginCode(): int
{
    $code = random_int(1000, 9999);
    $this->update([
        'login_code' => $code,
        'login_code_expires_at' => date('Y-m-d H:i:s', time() + 300),
    ]);

    return $code;
}

public function verifyLoginCode(string $code): bool
{
    if ($this->login_code === null || $this->login_code_expires_at === null) {
        return false;
    }

    $expired = strtotime($this->login_code_expires_at) < time();

    if ($expired || !ctype_digit($code)) {
        return false;
    }

    if ((int) $code !== (int) $this->login_code) {
        return false;
    }

    $this->update(['login_code' => null, 'login_code_expires_at' => null]);

    return true;
}
```

4. `AuthenticationsController::authenticate` (`:24-27`): no lugar de
   `Auth::login($user)`, `$code = $user->generateLoginCode();`
   `$_SESSION['pending_user_id'] = $user->id;` e
   `FlashMessage::success("Seu código de acesso é {$code}")` (é assim
   que "o backend devolve o número" sem e-mail),
   `redirectTo(route('users.code'))`.
5. Rotas públicas (fora de `auth`), ao lado de `:13-15`:

   ```php
   Route::get('/login/code', [AuthenticationsController::class, 'code'])
       ->name('users.code');
   Route::post('/login/code', [AuthenticationsController::class, 'verifyCode'])
       ->name('users.verify_code');
   ```
6. `code()`: sem `pending_user_id` -> `redirectTo(route('users.login'))`;
   senão `render('authentications/code', ['title' => 'Código de acesso'])`
   com `<input name="code" inputmode="numeric" maxlength="4">`.
7. `verifyCode(Request $request)`:

   ```php
   $user = User::findById((int) ($_SESSION['pending_user_id'] ?? 0));
   $code = $request->getParam('code');
   $code = is_string($code) ? trim($code) : '';
   ```

   `$user` nulo -> `redirectTo(route('users.login'))`; código certo ->
   `unset($_SESSION['pending_user_id'])`, `Auth::login($user)`,
   `success('Login realizado com sucesso!')`,
   `redirectTo(route('dashboard.index'))`;
   errado -> `danger('Código inválido!')`, `redirectTo(route('users.code'))`.
8. Testes existentes: `AuthenticationsControllerTest:32-42` passa a
   esperar `/login/code`; `LoginHelper::login()` precisa ler o código
   da página (`$page->grabTextFrom('.alert-success')` + regex `/\d{4}/`)
   e preencher o form; `ACC:67-78` (`loggedClient`) faz o mesmo em dois
   POSTs. Sem isso todos os Cests e testes de acesso falham.

Armadilhas:

- Fazer `Auth::login` antes do código anula a feature: o login só
  acontece em `verifyCode`.
- Gerar o código na view ou em JavaScript: não é "backend devolve".
- Guardar só na sessão (`$_SESSION['login_code']`) funciona e é mais
  rápido (sem schema), mas "campo gerado e validado no model" é o que
  o professor cobra; cite as duas e justifique.
- Comparação `'1234' == 1234` passa, `===` não: converta.
- Expiração e limite de tentativas são extras; faça só se pedirem.
- `update()` não valida (`MODEL:189`): bom aqui (não exige
  `password_confirmation`).
- O código **não** deve aparecer para quem não acertou a senha.
- Os Cests de login e todos os testes de acesso dependem do fluxo
  antigo: avise que vai ajustar `LoginHelper` e `loggedClient` e faça
  isso logo após a funcionalidade.

Prova: testes; no navegador, logar, ler o código no alerta, errar uma
vez e acertar.

### P13. Admin inscreve e remove um Participante

Chance: média-alta. Tempo: 30-40 min.

Exemplo pronto: `fvp/4.2/exemplos/p13-admin-inscreve-remove-participante`.

Pedido típico: "no detalhe do Evento, o administrador escolhe um usuário
e o inscreve; pode também remover uma inscrição". Aqui o `user_id` vem
do form e por isso precisa ser **validado no model** (existe, é
Participante, há vaga, não duplica).

Arquivos: `ROUTES:26-35` (grupo admin), `EN:15-29` (`validates()` de
P2, ou criar), `US:71-74` (`isAdmin`), `SHOW:28-36`, O3
(`participants()` para listar), `EV:137-140` (`availableSpots`),
`MODEL:335` (`findBy` com duas condições), `core/Router/Route.php:90`
(nomes de parâmetro só `[a-z_]`), `ACC:85-110`, `ECT:17-31`.

Passos:

1. Testes: `Admin\EnrollmentsControllerTest`: `create` com `user_id`
   de Participante grava; com admin, inexistente, duplicado ou lotado
   não grava e flash `danger` com a mensagem do model; `destroy` remove
   só a inscrição daquele Evento; `destroy` com `id` de outro Evento não
   remove. `ACC`: as duas rotas em `eventRoutes()`/`methodOverrideRoutes()`.
2. `ROUTES` (admin):

   ```php
   Route::post(
       '/admin/events/{id}/enrollments',
       [AdminEnrollmentsController::class, 'create']
   )->name('admin.events.enrollments.create');
   Route::delete(
       '/admin/events/{event_id}/enrollments/{id}',
       [AdminEnrollmentsController::class, 'destroy']
   )->name('admin.events.enrollments.destroy');
   ```

3. `EN::validates()` (P2 + validação do usuário):

   ```php
   private function validatesUserIsParticipant(): void
   {
       $id = filter_var($this->user_id, FILTER_VALIDATE_INT);
       $user = $id === false ? null : User::findById($id);

       if ($user === null || $user->isAdmin()) {
           $this->addError('user_id', 'deve ser um participante existente!');
       }
   }
   ```

4. `Admin\EnrollmentsController::create`: `findEvent`;
   `$userId = $request->getParam('user_id')` com `is_string` +
   `ctype_digit`;
   `new Enrollment(['event_id' => $event->id, 'user_id' => (int) $userId])`;
   `save()` -> flash; redirect para `admin.events.show`. `destroy`:
   `Enrollment::findBy(['id' => (int) $id, 'event_id' => (int) $eventId])`
   (os dois da rota, ambos `ctype_digit`); `null` -> `danger`.
5. `SHOW`: `<select name="user_id">` com
   `User::where(['role' => 'participant'])` (ou `User::participants()`),
   botão "Inscrever"; na lista de inscritos (O3), form `DELETE` por linha.

Armadilhas:

- `user_id` do form é a exceção à regra "nunca do form": por isso toda a
  validação fica no model e o teste cobre admin/inexistente.
- Nome de parâmetro `{eventId}` não é reconhecido (`Route.php:90`
  aceita só `[a-z,_]`): vira texto literal e a rota nunca casa, sem
  erro. Use `{event_id}`.
- `findBy` com `id` **e** `event_id`: sem o `event_id`, um admin remove
  inscrição de outro Evento trocando o `id` na URL.
- `select` com centenas de usuários: aceitável aqui; cite a busca (N2)
  como evolução.
- Com P2 na pilha, a mesma `validates()` serve aos dois fluxos
  (autoinscrição e admin): não duplique regra no controller.

Prova: testes; no navegador, inscrever `fernanda@` no Evento 2 e
remover.

### P14. Comprovante de inscrição com número gerado

Chance: média. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/p14-comprovante-inscricao`.

Pedido típico: "cada inscrição recebe um número de comprovante de 4
dígitos (1000-9999), único dentro do Evento, mostrado ao Participante e
ao admin". É P1 no nível da Inscrição.

Arquivos: `SCH:43-53` (`event_user`), `EN:17-18` (`$columns`), P1
(`save()` sobrescrito e laço de unicidade, `EV`), `MODEL:347`
(`exists`), P2 (tela do Participante), P7 ("Meus eventos"), O3 (lista
de inscritos), `tests/Unit/Models/Events/EnrollmentTest.php`.

Passos:

1. Testes em `EnrollmentTest`: após `save()`, `ticket_code` entre 1000 e
   9999; dois inscritos no mesmo Evento têm códigos diferentes; o mesmo
   código pode repetir em Eventos diferentes; `save()` de novo não troca.
2. `SCH`: `ticket_code SMALLINT UNSIGNED NOT NULL` e
   `UNIQUE KEY uq_event_user_ticket (event_id, ticket_code)`.
3. `EN`: `'ticket_code'` em `$columns`; `save()` sobrescrito gera quando
   `newRecord()` e vazio:


   ```php
   do {
       $code = random_int(1000, 9999);
       $taken = ['event_id' => $this->event_id, 'ticket_code' => $code];
   } while (self::exists($taken));
   ```

   `validates()`: faixa (P1) com a guarda para registro novo.
4. Views: P2/P7 mostram "Comprovante nº 1234"; O3 mostra na lista.
5. Populate: nada (o `save()` gera).

Armadilhas:

- Unicidade é **por Evento** (`event_id` + código), 9000 valores por
  Evento: o laço raramente repete; com `UNIQUE` composto o banco fecha a
  corrida.
- `exists()` com duas condições usa `where` (`MODEL:347-350`): ok.
- Mostrar o código só a quem é dono da inscrição ou admin.
- `BaseEventsCest`/`EnrollmentTest` criam Inscrições com `new
  Enrollment([...])->save()`: ganham código sozinhas.

Prova: testes; no navegador, inscrever-se (P2) e ver o número.

### P15. Inscrição via JSON

Chance: média-baixa. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/p15-inscricao-json`.

Pedido típico: "o `POST /events/{id}/enrollments` deve responder JSON
quando o cliente pedir `Accept: application/json`" (prévia do Ajax da
4.4, sobre P2).

Arquivos: P2 (`EnrollmentsController::create`), `CTRL:44-55`
(`renderJson`, view `.json.php`), `core/Http/Request.php:52-55`
(`acceptJson`), O4 (mesmo mecanismo na listagem),
`tests/Integration/Controllers/EnrollmentsControllerTest.php` (de P2),
`core/Http/Request.php:41-44` (`getHeaders`).

Passos:

1. Teste: `$_SERVER['HTTP_ACCEPT'] = 'application/json'`; `post('create', ...)`
   devolve JSON com `ok`, `message` e `available_spots`; sem o header,
   continua `Location:`.
2. `create`, depois do `save()`:

   ```php
   if ($request->acceptJson()) {
       http_response_code($saved ? 201 : 422);
       $ok = $saved;
       $message = $saved
           ? 'Inscrição realizada!'
           : ($enrollment->errors('base') ?? 'Inválido');
       $this->renderJson(
           'enrollments/create',
           compact('ok', 'message', 'event')
       );
       return;
   }
   ```

3. `app/views/enrollments/create.json.php`:

   ```php
   $json = [
       'ok' => $ok,
       'message' => $message,
       'available_spots' => $event->availableSpots(),
   ];
   ```

   Com `fetch()` atualizando a tela, acrescente `errors`,
   `enrollments_count` e `capacity`.
4. Sem flash no caminho JSON (ninguém lê a sessão).
5. Evento inexistente com `acceptJson()`: 404 em JSON, não flash +
   redirect.

Armadilhas:

- `acceptJson()` exige igualdade exata do header (`Request.php:54`);
  `fetch()` do navegador manda `application/json` se você setar.
- `http_response_code()` funciona também em CLI: zere no `setUp` (O8).
  O teste por HTTP é o mais fiel para o status.
- Pedido JSON sem sessão continua 302 para `/login` (middleware `auth`).
- `renderJson` tem `chartset` no header (`CTRL:51`); não é seu.
- Não misture: ou redireciona, ou responde JSON. `return` após o
  `renderJson`.

Prova: teste e `curl -X POST -H 'Accept: application/json' -b <cookie>`
em `localhost/events/3/enrollments` (no Evento 2, `user@example.com` já
está inscrito pelo populate e recebe 422).

### P16. Remover só com código de confirmação gerado no backend

Chance: média-alta. Tempo: 30-40 min.

Exemplo pronto: `fvp/4.2/exemplos/p16-remover-com-codigo-confirmacao`.

Pedido típico: "para remover um Evento o sistema mostra um código de 4
dígitos gerado na hora; o admin digita o código e só então a remoção
acontece". É a FVP real da 4.1 (P12) aplicada à remoção.

Arquivos: `ROUTES:26-35` (rota `GET /admin/events/{id}/delete`: 4 partes,
não conflita com `{id}`), `EC:96-111` (`destroy`), `IDX:30-33` (form
de remoção vira link), `EC:137-141`, `lib/` (classe nova para gerar e
conferir, testável sem controller), `ECT:242-270` (destroy),
`ACT/DeleteEventCest.php:50-54`, `ACC:85-97`, R4 (confirm em JS, a
alternativa fraca).

Passos:

1. Testes: `tests/Unit/Lib/ConfirmationCodeTest.php` (gera 1000-9999,
   confere, apaga depois de usar, rejeita `'12a'`); `ECT`: `delete`
   renderiza a tela com o código na sessão; `destroy` sem código ou com
   código errado não remove e volta para a tela; com o código remove.
2. `lib/ConfirmationCode.php`:

   ```php
   final class ConfirmationCode
   {
       public static function issue(string $key): int
       {
           $code = random_int(1000, 9999);
           $_SESSION['confirmation'][$key] = $code;

           return $code;
       }

       public static function check(string $key, mixed $given): bool
       {
           $expected = $_SESSION['confirmation'][$key] ?? null;
           $ok = is_int($expected)
               && is_string($given)
               && ctype_digit($given)
               && (int) $given === $expected;
           unset($_SESSION['confirmation'][$key]);

           return $ok;
       }
   }
   ```

3. `ROUTES` (admin):

   ```php
   Route::get(
       '/admin/events/{id}/delete',
       [AdminEventsController::class, 'delete']
   )->name('admin.events.delete');
   ```

4. `EC::delete`: `findEvent`;
   `$code = ConfirmationCode::issue("event-{$event->id}")`;
   `render('admin/events/delete', compact('event', 'code', 'title'))`
   com a tela mostrando o código e um form `DELETE` com
   `<input name="confirmation_code">`.
5. `EC::destroy`, antes de `safeDestroy()`:

   ```php
   $given = $request->getParam('confirmation_code');
   if (!ConfirmationCode::check("event-{$event->id}", $given)) {
       FlashMessage::danger('Código de confirmação inválido!');
       $this->redirectTo(route('admin.events.delete', ['id' => $event->id]));
       return;
   }
   ```

6. `IDX:30-33`: o form vira um link para `admin.events.delete`.
7. `DeleteEventCest`: clicar Remover,
   `grabTextFrom('#confirmation_code_value')`, `fillField`, confirmar.
   `ACC`: a rota `GET` em `eventRoutes()`.

Armadilhas:

- Código na sessão **por Evento** (`event-{id}`): sem a chave, confirmar
  o Evento 2 removeria o 3.
- Apague o código depois de conferir (uso único) e ao errar (gera outro
  na próxima tela): evita força bruta trivial.
- `destroy` antigo (form direto da listagem) deixa de funcionar: os
  testes de `ECT:242-270` precisam passar pela sessão
  (`$_SESSION['confirmation'][...] = 1234` no teste, ou chamar `issue`).
- Mostrar o código na própria tela é o que o professor pediu na 4.1
  ("o backend devolve"); não é segurança contra o usuário, é prova de
  que ele leu. Diga isso.
- `lib/` está fora do `phpstan`: tipe tudo; teste unitário cobre.
- Com P12 na base, reaproveite a mesma classe para o login.

Prova: testes; no navegador, Remover -> tela com código -> errar ->
acertar -> listagem sem o Evento.

### P17. Remover Evento com inscrições, em cascata e com confirmação

Chance: média. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/p17-remover-em-cascata`.

Pedido típico: "hoje Evento com inscritos não pode ser removido; permita,
apagando as inscrições, mas só depois de confirmar". Fica melhor em cima
de P16 (a confirmação); sem P16, use um parâmetro `force` vindo de um
segundo botão.

Arquivos: `EV:151-173` (`safeDestroy` bloqueia), `SCH:49-50` (FK
`ON DELETE RESTRICT`), `MODEL:215-231` (`destroy`),
`core/Database/Database.php:10-22` (`getDatabaseConn` abre um PDO novo
a cada chamada), `EC:96-111`,
`EVT:643-680` e `ECT:253-270` (hoje provam o bloqueio),
`ACT/DeleteEventCest.php:30-48`.

Passos:

1. Testes: `EVT`: `forceDestroy()` apaga Evento e Inscrições; falha no
   meio desfaz tudo (difícil de provocar: se não testar, diga). `ECT`:
   `destroy` sem confirmação em Evento com inscritos continua
   bloqueando; com `force`/código, remove e o flash diz quantas
   inscrições saíram (`enrollmentsCount()` antes de remover).
2. `EV`:

   ```php
   public function forceDestroy(): bool
   {
       $pdo = Database::getDatabaseConn();
       $pdo->beginTransaction();

       try {
           $deletes = ['event_user' => 'event_id', 'events' => 'id'];
           foreach ($deletes as $t => $c) {
               $stmt = $pdo->prepare("DELETE FROM {$t} WHERE {$c} = :id");
               $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);
               $stmt->execute();
           }
           if ($stmt->rowCount() !== 1) {
               throw new \RuntimeException('evento não encontrado');
           }
           $pdo->commit();

           return true;
       } catch (\Throwable $e) {
           $pdo->rollBack();
           $this->addError(
               'base',
               'não foi possível remover o evento e suas inscrições!'
           );

           return false;
       }
   }
   ```

3. `EC::destroy`: `$force = $request->getParam('force') === '1';`
   (ou a confirmação de P16) -> `forceDestroy()`; senão `safeDestroy()`.
4. Tela de P16 (ou a listagem) avisa "Este Evento tem N inscrições; elas
   serão removidas".
5. Alternativa de schema: `ON DELETE CASCADE` em `SCH:49-50` e
   `destroy()` direto; então `EVT:675-680` (FK bloqueia) inverte.

Armadilhas:

- Transação: `getDatabaseConn()` cria um PDO novo a cada chamada
  (`Database.php:10-22`). `Model::destroy()` e `Enrollment::destroy()`
  rodam em outra conexão, **fora** da transação: os `DELETE` precisam
  usar o mesmo `$pdo` do `beginTransaction`, como no passo 2.
  `migrate()` nos testes recria o schema com `foreign_key_checks` ok.
- Não troque o padrão (`safeDestroy`) sem confirmação explícita: a
  rubrica e as issues cobram regra clara para excluir pai com filhos.
- Os testes atuais de bloqueio (`EVT:643-680`, `ECT:253-270`,
  `DeleteEventCest:30-48`) continuam válidos para o caminho **sem**
  `force`; acrescente os do caminho com.
- Flash: um por tipo (`FLASH:27`): junte a contagem na mesma mensagem.

Prova: testes; no navegador, Evento 1 (3 inscritos) com confirmação.

### P18. Evento privado com código de convite

Chance: baixa. Tempo: 30-40 min.

Exemplo pronto: `fvp/4.2/exemplos/p18-evento-privado-convite`.

Pedido típico: "um Evento pode ser privado: só se inscreve quem informar
o código de convite, gerado pelo sistema e visível ao admin". Combina P1
(código gerado) e P2 (inscrição).

Arquivos: `SCH:21-38`, `EV:48-58`, P1 (`save()` gera código), P2
(`EN::validates()` e `EnrollmentsController::create`), `FORM`
(checkbox "privado"), `SHOW` (admin vê o código), tela do Participante
(O2/P2) com o campo.

Passos:

1. Testes: Evento privado gera `invite_code` 1000-9999 e público fica
   `null`; inscrição em privado sem código ou com código errado falha
   com `errors('base')`; com o código certo grava; público ignora o campo.
2. `SCH`: `is_private TINYINT(1) NOT NULL DEFAULT 0` e
   `invite_code SMALLINT UNSIGNED NULL`.
3. `EV`: `'is_private'`, `'invite_code'` em `$columns` (padrão `0` no
   construtor, `US:28-35`); `save()` normaliza `is_private` para 1/0 e
   gera `invite_code` quando privado e vazio (P1); `isPrivate()` e
   `matchesInviteCode(?string)` com `ctype_digit` + `(int)`.
4. `EN`: propriedade **não persistida** `private ?string $inviteCode`
   com `withInviteCode(?string $code): static`; a checagem vai no método
   que só roda na criação (`validatesAvailableSpots()`, junto da vaga),
   senão salvar de novo uma Inscrição existente pede o código:

   ```php
   if ($event->isPrivate() && !$event->matchesInviteCode($this->inviteCode)) {
       $this->addError('base', 'código de convite inválido!');
   }
   ```
5. `EnrollmentsController::create` (P2): lê `invite_code` com
   `is_string` e passa por `withInviteCode()`.
6. `FORM`: checkbox `event[is_private]` (em `FIELDS`); `SHOW` do admin
   mostra o código; tela do Participante mostra o campo quando privado.

Armadilhas:

- Checkbox desmarcado **não é enviado**: `fill()` recebe `null`;
  normalize para `'0'` no model (`$this->is_private = $value ? 1 : 0`).
- A coluna é `is_private`, não `private`: funcionaria (`private` só é
  reservada como modificador), mas confunde na leitura.
- O código do convite não vai em `FIELDS`: gerado no model.
- Propriedade extra no model (`$inviteCode`) precisa existir como
  propriedade declarada: `Model::__set` (`MODEL:74-87`) lança exceção para
  nome fora de `$columns`/propriedades.
- Listagem pública (O2) deve ocultar o código.

Prova: testes; no navegador, criar privado, ver o código no `show` do
admin e inscrever-se como Participante com e sem o código.

### P19. Remoção em lote

Chance: baixa. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/p19-remocao-em-lote`.

Pedido típico: "checkbox em cada linha e um botão Remover selecionados".

Arquivos: `ROUTES:26-35` (rota de coleção `DELETE /admin/events`),
`IDX:8-38` (tabela; `IDX:30-33` já tem um `<form>` por linha),
`EV:151-173` (`safeDestroy` por Evento), `FLASH:25-28` (um texto por
tipo), `EC:96-111`, `ECT:242-265`, `ACC`.

Passos:

1. Testes `ECT`: `ids = [a, b]` sem inscrições -> os dois somem, flash
   "2 eventos removidos!"; `[a, c]` com `c` inscrito -> `a` some, `c`
   fica, flash de sucesso e de erro juntos; `ids` vazio, não array,
   `['x']` ou id inexistente -> nada removido e flash de erro. `ACC`: a
   rota exige admin.
2. `ROUTES`: `Route::delete('/admin/events', [AdminEventsController::class,
   'destroyMany'])->name('admin.events.destroy_many');`. Não colide com
   `DELETE /admin/events/{id}`: o número de partes é outro.
3. `EV`: `public static function destroyMany(array $ids): array` com
   `@param list<int> $ids` e `@return array{removed: list<string>,
   blocked: list<string>, missing: list<int>}`: para cada id, `findById`
   (inexistente vai para `missing`) e `safeDestroy()`, que separa
   removidos de bloqueados (com inscrições), pelos títulos.
4. `EC::destroyMany`: valida `ids` (`is_array`, cada item `is_string` e
   `ctype_digit`, e só então `(int)`; vazio ->
   `danger('Selecione ao menos um evento!')`,
   redirect e `return`), chama o model e monta **um** texto por tipo.
5. `IDX`: um `<form id="bulk-delete">` fora da tabela (POST com
   `_method=DELETE`) e checkboxes `name="ids[]" form="bulk-delete"`.

Armadilhas:

- Form dentro de form é HTML inválido, e cada linha já tem um
  (`IDX:30-33`): use o atributo `form="bulk-delete"` nos checkboxes.
- `FlashMessage` guarda um texto por tipo (`FLASH:27`): no laço, a
  segunda mensagem apaga a primeira. Junte antes.
- `(int)` só depois de `is_string` + `ctype_digit` (N10): `'3abc'` não
  pode virar 3.
- "Tudo ou nada" é outro pedido: transação na **mesma** conexão (P17;
  `getDatabaseConn` abre uma conexão nova a cada chamada). O padrão
  aqui é remover o que pode e avisar o resto; diga qual escolheu.
- `ids[]` repetidos: `array_unique`.

Prova: testes; no navegador, marcar dois e remover.

### P20. Exportar Evento em iCal (`.ics`)

Chance: baixa. Tempo: 20-25 min.

Exemplo pronto: `fvp/4.2/exemplos/p20-exportar-ical`.

Pedido típico: "botão Adicionar ao calendário no detalhe do Evento".

Arquivos: `ROUTES:31-32` (rota `GET /admin/events/{id}/ics` junto do
`show`), `EC:29-39` (`show` como molde), `CTRL:32-55` (`render` e
`renderJson`; o `.ics` não usa layout), `SHOW:35-36` (botões),
`CTC:70-84` (o teste captura a saída com `ob_start`), `EV`, `EVT`,
`ACC` (rota em `eventRoutes()`; teste HTTP de `Content-Type` e
`Content-Disposition`), `POP:52` (descrição com mais de 75 bytes).

Passos:

1. Testes: `EVT` para `Event::toIcs(): string` (começa com
   `BEGIN:VCALENDAR`, tem `DTSTART;VALUE=DATE:` com a data do Evento
   (data relativa, `+30 days`; data fixa envelhece), `DTEND` do dia
   seguinte, `SUMMARY` escapado, termina com `END:VCALENDAR`, linhas
   separadas por `\r\n`); `ECT`: `ics` devolve o texto; id inexistente
   redireciona como o `show`.
2. `EV::toIcs()`: monta as linhas e devolve
   `implode("\r\n", $lines) . "\r\n"`. `UID` `event-{id}@eventos.local`,
   `DTSTAMP` em UTC (`gmdate('Ymd\THis\Z')`), texto escapado:

   ```php
   private static function icsText(string $value): string
   {
       return str_replace(
           ['\\', ';', ',', "\r\n", "\n", "\r"],
           ['\\\\', '\;', '\,', '\n', '\n', '\n'],
           $value
       );
   }
   ```

   E cada linha dobrada em 75 bytes (RFC 5545; a descrição do Evento 1
   do populate já passa disso):

   ```php
   private static function fold(string $line): string
   {
       $parts = [];
       while (strlen($line) > 75) {
           $chunk = mb_strcut($line, 0, 75, 'UTF-8');
           $parts[] = $chunk;
           $line = ' ' . substr($line, strlen($chunk));
       }
       $parts[] = $line;

       return implode("\r\n", $parts);
   }
   ```

   Alternativa: uma classe em `lib/` (`escape`, `fold`, `build`) e
   `Event::toICalendar()` só monta os campos.

3. `EC::ics(Request $request)`: `findEvent` com early return;
   `header('Content-Type: text/calendar; charset=utf-8')`,
   `header('Content-Disposition: attachment; filename="evento-'
   . $event->id . '.ics"')` e `echo $event->toIcs();`.
4. `SHOW`: link "Adicionar ao calendário".

Armadilhas:

- `DTEND` com `VALUE=DATE` é exclusivo: Evento de um dia termina no dia
  seguinte.
- Sem `render`: o layout HTML entraria no arquivo.
- `header()` não é capturado no teste de controller (CLI): teste o
  corpo; o tipo de conteúdo fica para o teste HTTP ou o navegador.
- Dobra por bytes com `mb_strcut`: `substr` partiria um caractere UTF-8
  ao meio; a continuação começa com um espaço.
- Para o Participante (O2), a rota vai no grupo dele, não no `admin`.

Prova: testes; no navegador o arquivo baixa e abre no calendário.

### P21. Imagem de capa do Evento (upload)

Chance: baixa (conteúdo da 4.3). Tempo: 35-40 min.

Exemplo pronto: `fvp/4.2/exemplos/p21-banner-upload`.

Forma do exemplo pronto: O exemplo valida a capa em `validates()` (erro no
campo, barra o `save()`), grava o arquivo no `save()` sobrescrito, depois do
`INSERT`, com nome aleatório (`bin2hex(random_bytes(16))`) numa pasta única
`uploads/events/`, e lê `$_FILES` num método privado do controller; `url()`
devolve `null` sem capa. As armadilhas do id e da pasta viram "salve a capa
depois do `save()`" e "apague o arquivo".

Pedido típico: "o Evento pode ter uma imagem de capa".

Arquivos: `SCH` (coluna `cover_name VARCHAR(255) NULL`), `EV:48-58`
(`$columns`), `FORM:16` (form sem `enctype`), `EC:49-94`,
`core/Http/Request.php` (não expõe `$_FILES`), `php/uploads.ini`
(40 MB), `public/assets/uploads/` (já existe, com `.gitkeep`), molde
`ProfileAvatar` do projeto de exemplo da disciplina (problem-track:
valida extensão e tamanho, move para
`public/assets/uploads/{tabela}/{id}/` e grava o nome com `update()`),
`SHOW`.

Passos:

1. Testes do serviço (`tests/Unit/Services/EventCoverTest.php`) com
   arquivo temporário; `move_uploaded_file` isolado num método
   `protected` que o teste sobrescreve. Casos: extensão inválida,
   tamanho acima do limite, sem arquivo (`UPLOAD_ERR_NO_FILE`) mantém a
   capa atual.
2. `SCH`, `EV::$columns` e T1 (populate sem capa).
3. `app/Services/EventCover.php`: validações
   `['extension' => ['jpg', 'png'], 'size' => 2 * 1024 * 1024]`, nome
   gerado `cover.<ext>`, `path()` com imagem padrão.
4. `Request::getFile(string $key): ?array` (core, com teste), ou um
   método privado do controller que leia `$_FILES`, como o molde.
5. `FORM:16`: `enctype="multipart/form-data"`;
   `<input type="file" name="event_cover">` fora de `event[]`.
6. `EC::create`/`update`: depois do `save()` com sucesso (a pasta usa o
   `id`), `$event->cover()->update($file)`; erro de capa vira flash de
   aviso, o Evento continua salvo.
7. `SHOW`: `<img src="<?= htmlspecialchars($event->cover()->path()) ?>">`.

Armadilhas:

- O molde confia na extensão do nome: confira também o tipo real
  (`getimagesize` ou `finfo_file`).
- `error !== UPLOAD_ERR_OK` primeiro; sem arquivo no editar, mantenha a
  capa.
- O `id` só existe depois do `INSERT`: no create, salve o Evento e
  depois a capa.
- O nome gravado é gerado, nunca o original do usuário; `update()` não
  valida (`MODEL:189`).
- Remover o Evento deve apagar a pasta (`safeDestroy`).
- `post_max_size` estourado esvazia `$_POST` inteiro: o form volta
  vazio, sem erro claro.
- Arquivos enviados no `.gitignore` (a base já ignora o conteúdo de
  `uploads`, mantendo a pasta).

Prova: testes; no navegador, enviar uma imagem e vê-la no `show`.

### P22. Adiar o Evento uma semana com um botão

Chance: média-baixa. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/p22-adiar-uma-semana`.

Pedido típico: "um botão 'Adiar uma semana' no detalhe do Evento".
Variante: "adiar N dias". Hoje não há ação para isso: só o `update`
inteiro (`EC:77-94`) muda `starts_on`, e a regra de data no passado
(`EV:88-99`) só é cobrada quando a data muda.

Arquivos: `EV:75-77` (`starts_on` validado), `EV:88-99`
(`validatesStartsOnNotPast`: compara com o original por `findById`,
`:91-93`), `EV:104-115` (capacidade x Inscrições, roda em todo `save()`),
`EV:155-158` (guarda de `newRecord()` no `safeDestroy`, modelo),
`MODEL:143-159` (`save()` faz `INSERT` se `newRecord()`),
`MODEL:189-213` (`update()` não valida), `ROUTES:33-35`, `EC:96-111`
(ação curta com flash, modelo), `EC:132-141` (`findEvent`,
`eventNotFound`), `SHOW:35-36`, `EVT:499-509` (`createPastEvent`),
`EVT:538-547` (Evento passado aceita data futura), `ECT:242-251`,
`ACC:81-110`, `core/Router/Route.php:84-86`.

Passos:

1. Testes. `EVT`: Evento em +30 dias, `postpone()` -> `true` e
   `findById` mostra +37 dias; com 2 Inscrições (`enroll`, `EVT:67-74`)
   a capacidade e `enrollmentsCount()` não mudam; `createPastEvent()`
   (-10 dias) -> `false`, erro `'não pode estar no passado!'` em
   `starts_on` e banco intacto; `postpone(0)` -> `false`;
   `(new Event($this->params()))->postpone()` -> `false` e
   `Event::all()` vazio. `ECT`: `put('postpone', ...)` com `id` ->
   `Location: /admin/events/{id}` e flash com a data nova; `id` 9999 ->
   `Location: /admin/events` e `'Evento não encontrado!'`; Evento
   passado (`$event->update(['starts_on' => ...])` antes) -> `danger` e
   data igual. `ACC`: a rota nas duas listas (passo 6).

   ```php
   public function test_postpone_should_reject_a_still_past_date(): void
   {
       $event = $this->createPastEvent();
       $original = $event->starts_on;

       $this->assertFalse($event->postpone());
       $this->assertEquals(
           'não pode estar no passado!',
           $event->errors('starts_on')
       );
       $this->assertEquals(
           $original,
           Event::findById($event->id)->starts_on
       );
   }
   ```

2. `EV` (importe `DateTimeImmutable` junto de `EV:5-8`):

   ```php
   public const POSTPONE_DAYS = 7;

   /**
    * Adia o Evento e grava com save(): a data nova passa pelas
    * validações de sempre (não pode ficar no passado).
    * A capacidade não muda.
    */
   public function postpone(int $days = self::POSTPONE_DAYS): bool
   {
       $this->errors = []; // como em safeDestroy (EV:153)
       if ($this->newRecord()) {
           $this->addError('base', 'o evento não existe!');
           return false;
       }

       if ($days < 1) {
           $this->addError('base', 'adie pelo menos 1 dia!');
           return false;
       }

       $original = $this->starts_on;
       $date = DateTimeImmutable::createFromFormat('!Y-m-d', $original);
       if ($date === false) {
           $this->addError('starts_on', 'não é uma data válida!');
           return false;
       }

       $this->starts_on = $date->modify("+{$days} days")
           ->format('Y-m-d');
       if ($this->save()) {
           return true;
       }

       $this->starts_on = $original;

       return false;
   }
   ```

3. `ROUTES`, depois de `update` (`:33`):

   ```php
   Route::put(
       '/admin/events/{id}/postpone',
       [AdminEventsController::class, 'postpone']
   )->name('admin.events.postpone');
   ```

4. `EC`:

   ```php
   public function postpone(Request $request): void
   {
       $event = $this->findEvent($request);
       if ($event === null) {
           $this->eventNotFound();
           return;
       }

       if ($event->postpone()) {
           $date = date('d/m/Y', strtotime($event->starts_on));
           FlashMessage::success("Evento adiado para {$date}!");
       } else {
           $reason = $event->errors('starts_on')
               ?? $event->errors('base') ?? 'dados inválidos!';
           FlashMessage::danger("Não foi possível adiar: {$reason}");
       }

       $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
   }
   ```

5. `SHOW:35`, ao lado do Editar:

   ```php
   <form class="d-inline" method="post"
       action="<?= route('admin.events.postpone', ['id' => $event->id]) ?>">
       <input type="hidden" name="_method" value="PUT">
       <button type="submit" class="btn btn-outline-warning">
           Adiar uma semana
       </button>
   </form>
   ```

6. `ACC`: `'admin.events.postpone' => ['PUT', '/admin/events/1/postpone']`
   em `eventRoutes()` (`:85-97`) e em `methodOverrideRoutes()`
   (`:104-110`); o comentário "As 8 rotas" (`:81`) passa a 9. No teste
   do Participante (`ACC:139-160`), tente adiar e confira que a data não
   mudou.
7. Cest: o Evento 16 adiado pelo botão; o Evento 01 (lotado, 3/3)
   continua com a mesma ocupação depois de adiado.

Armadilhas:

- Num Evento novo, `save()` faria `INSERT` (`MODEL:143-159`): adiar
  criaria um Evento. Por isso a guarda de `newRecord()`, como em
  `EV:155-158`.
- `update(['starts_on' => ...])` não valida (`MODEL:189`) e gravaria
  data no passado. Use `save()`.
- Some os dias a partir de `starts_on`, não de hoje:
  `strtotime('+7 days')` (como em P6) dá "hoje + 7".
- Evento passado: -10 dias + 7 = -3, recusado; -3 dias + 7 = +4,
  aceito (coerente com `EVT:538-547`). Se o professor quiser "passado
  não adia", ponha no início de `postpone()` um teste de
  `starts_on < date('Y-m-d')` com erro em `base`, e um teste para o caso
  de -3 dias.
- `save()` roda `validates()` inteiro: um Evento com capacidade abaixo
  das Inscrições (gravada por `update()`) não adia. Mesma armadilha
  de P5.
- Se o `save()` falhar, devolva a data original ao objeto; senão quem
  usar o objeto depois vê uma data que não foi gravada.
- Nunca por link (`<a href=".../postpone">`): GET não altera dados (O9).
  Form POST + `_method=PUT`; com O7, `Route::patch` e `_method=PATCH`.
- Rota com `{id}` e sufixo não disputa com `/admin/events/{id}`: o
  `Route` compara o número de segmentos (`Route.php:84-86`). Só uma rota
  GET fixa sem `{id}` precisa vir antes de `ROUTES:31`.
- `ECT` não passa pelo `Router` (`CTC:70-84`): rota ou verbo errado só
  aparece no `ACC`.
- Prazo digitado ("adiar N dias"): passe o valor cru para o model e
  valide lá com `filter_var($days, FILTER_VALIDATE_INT,`
  `['options' => ['min_range' => 1, 'max_range' => 365]])`; nunca
  `(int)` no controller (texto vira 0).
- Com P5, Evento cancelado não adia (`isCanceled()` no início).

Prova: `EVT`, `ECT`, `ACC`; no navegador, "Adiar uma semana" no Evento
16 mostra o flash com a data nova e o detalhe 7 dias depois.

### Q1. Só o dono edita e remove

Chance: média-alta. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/q1-so-dono-edita-remove`.

Pedido típico: "só o Administrador que criou o Evento pode editar ou
remover" (cenário D8 da 4.1 aplicado a Eventos). Variante: "o `show`
continua aberto a todo admin".

Arquivos: `EC:132-135` (`findEvent`), `EC:137-141` (`eventNotFound`),
`EC:65-111` (edit, update, destroy), `US:52-55` (`events(): HasMany`),
`HASMANY:34-42` (`findById(int): ?Model`), `IDX:28-33` (botões),
`app/Middleware/Admin.php:12-21`, `ECT:17-31` (login do admin no
teste), `ECT:163-168`, `ACC:67-78`, `POP:12-19` (um só admin).

Passos:

1. Teste em `ECT`: crie um segundo admin (cópia de `:22-30`),
   `Auth::login($outro)`, e peça `edit`, `update` e `destroy` de um
   Evento do primeiro: Evento intacto e `Location: /admin/events`
   (`assertSame`, como `:167`).
2. `findEvent` (`EC:132-135`):

```php
private function findEvent(Request $request): ?Event
{
    $id = (int) $request->getParam('id');
    $event = $this->currentUser()?->events()->findById($id);

    return $event instanceof Event ? $event : null;
}
```

   Evento alheio vira `null` e cai em "Evento não encontrado!".
   Mensagem própria: compare `(int) $event->user_id` com
   `(int) $this->currentUser()?->id` e use
   `FlashMessage::danger('Você não pode alterar este Evento!')`.
3. O `show` (`EC:31`) também usa `findEvent`: para manter o `show`
   amplo, troque `EC:31` por `Event::findById((int) $request->getParam('id'))`
   e deixe `findEvent` para edit/update/destroy. Pergunte ao professor.
4. `IDX:28-33`:
   `<?php if ((int) $event->user_id === (int) $this->currentUser()?->id) : ?>`
   em volta de Editar e Remover.

Armadilhas:

- Sem `instanceof`, `phpstan` nível 6 acusa: `findById` devolve
  `?Model` e `findEvent` declara `?Event`.
- O populate tem um admin só (`POP:12-19`): para ver no navegador
  crie outro (cenário G1 da 4.1) ou promova `user@example.com` no
  banco (`UPDATE users SET role = 'admin' WHERE email = 'user@example.com'`).
- Comparar `user_id`: do banco vem `int` (mysqlnd, PHP 8.1+), do form
  vem string; normalize um lado com `(string)` antes do `===`.
- Esconder o botão não protege: teste a URL direta.
- Não confiar em `user_id` do form (`ECT:101-112` já cobre).

Prova: o teste com dois admins; `ACC:112-128` (sem sessão e
Participante) continua passando.

### Q2. Bloquear edição e remoção de Evento passado ou cancelado

Chance: média. Tempo: 15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/q2-bloquear-passado-cancelado`.

Pedido típico: "Evento que já aconteceu (ou cancelado, P5) não pode
ser editado nem removido".

Arquivos: `EV:84-99` (hoje a regra permite editar Evento passado sem
mudar a data), `EC:65-111`, `EC:32-35` (padrão de *early return*),
`IDX:28-33`, `SHOW:35`, `EVT:499-547` (testes que **garantem** a edição
de Evento passado:
`test_past_event_can_be_edited_when_starts_on_does_not_change`
em `:511`), `ECT:191-207`.

Passos:

1. Testes: `EVT`: `isEditable()` falso para passado (use
   `createPastEvent`, `EVT:499-509`) e para cancelado; `ECT`: `update`
   de Evento passado não altera e redireciona com `danger`.
2. `EV`:

```php
public function isEditable(): bool
{
    return !$this->isCanceled() && $this->starts_on >= date('Y-m-d');
}
```

   (`isCanceled()` de P5; sem P5, só a data).
3. `EC` edit/update/destroy, logo após o `findEvent`:

   ```php
   if (!$event->isEditable()) {
       FlashMessage::danger('Evento encerrado não pode ser alterado!');
       $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
       return;
   }
   ```
4. `IDX:28-33` e `SHOW:35`: botões só se `isEditable()`.

Armadilhas:

- Contradiz a decisão atual (`EV:84-87`: Evento passado pode ter
  descrição corrigida). Com a regra no controller e nas views (passo 3)
  os testes `EVT:511-547` continuam verdes, porque `save()` segue
  aceitando; só se a regra for para `validates()`/`save()` eles quebram
  e precisam ser removidos ou invertidos com o professor ciente.
- Texto do flash: com P5, "Encerrado" é o rótulo de `finished`, que
  `isEditable()` não bloqueia; prefira "Evento passado ou cancelado não
  pode ser alterado!".
- Comparação de datas `Y-m-d` como string funciona só com o formato
  garantido pelo banco (`DATE`).
- Regra no controller em vez do model: coloque a decisão em
  `isEditable()` e deixe o controller só perguntar.
- O populate não tem Evento passado: crie via `db:console`
  (`UPDATE events SET starts_on = '2020-01-01' WHERE id = 16`).

Prova: testes; no navegador, o Evento 16 "passado" perde os botões e a
URL direta `/admin/events/16/edit` redireciona.

### Q3. Ação só para Participante

Chance: média-baixa. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/q3-acao-so-participante`.

Pedido típico: "Administrador não pode se inscrever em Evento; só
Participante" (sobre P2). Duas formas: middleware `participant` nas
rotas de inscrição, ou regra no model (`EN::validates()` recusa admin,
a mesma regra que P13 já valida para o `user_id`).

Arquivos: `app/Middleware/Admin.php:10-28` (modelo; inverter a
condição), `config/App.php:7-10` (aliases), `ROUTES:18-20` (grupo
`auth`), `core/Router/RouteWrapperMiddleware.php:15-25` (ordem: o
middleware do grupo é anexado **depois** do callback, então o grupo de
dentro roda antes do de fora; A17 da 4.1), P2 (`EnrollmentsController`),
`ACC:58-78` (`loggedClient`),
`tests/Unit/Core/Router/RouteWrapperMiddlewareTest.php`.

Passos:

1. Testes: `ACC`: admin logado faz `POST /events/1/enrollments` ->
   302 para `/admin` (ou `/events/1` com flash) e nada gravado;
   Participante -> 302 para o `show` e inscrição gravada. Unitário do
   middleware (F2 da 4.1): `Auth::login($admin)` + `handle()` redireciona.
2. `app/Middleware/Participant.php`:

   ```php
   class Participant implements Middleware
   {
       public function handle(Request $request): void
       {
           $user = Auth::user();

           if ($user !== null && $user->isAdmin()) {
               FlashMessage::danger('Ação só para participantes');
               $this->redirectTo(route('admin.dashboard.index'));
           }
       }
   }
   ```

3. `config/App.php`: `'participant' => \App\Middleware\Participant::class`.
4. `ROUTES`, dentro de `auth`:
   `Route::middleware('participant')->group(function () { /* P2, P3, P4 */ });`.
5. Forma model: em `EN::validates()`, `User::findById($this->user_id)`
   admin -> `addError('base', 'administradores não se inscrevem!')`.
   P13 continua funcionando: a regra recusa o usuário **inscrito**, não
   quem faz a ação, e o admin inscreve Participantes.

Armadilhas:

- Com usuário nulo o middleware **não** redireciona (quem faz isso é
  `Authenticate`): o grupo interno roda primeiro
  (`RouteWrapperMiddleware:15-25`), então `Participant` vê sessão vazia
  antes do `auth`; copie o padrão
  `$user !== null && ...` de `Admin.php:17`.
- `redirectTo` duplicado em três middlewares: é a crítica A1 da 4.1
  ("centralizar"); se já houver uma base comum, use-a.
- Esconder o botão para admin na view não basta: teste a URL direta.
- Forma middleware e forma model não são excludentes; a do model
  protege qualquer caminho futuro (P13, P15).

Prova: testes; no navegador, admin abre `/events/2` e tenta inscrever-se.

### Q4. Perfil Organizador com CRUD só dos próprios Eventos

Chance: média. Tempo: 35-40 min.

Exemplo pronto: `fvp/4.2/exemplos/q4-perfil-organizador` (variante:
área própria `/organizer/events`, onde o admin não entra).

Pedido típico: "crie o perfil `organizer`: ele cadastra e gerencia só os
Eventos dele; o admin continua vendo tudo" (A7 da 4.1 aplicado ao CRUD;
junta Q1/O5 com um papel novo).

Arquivos: `SCH:12` (ENUM `role`), `US:28-35` (padrão `participant`),
`US:71-74` (`isAdmin`), `app/Middleware/Admin.php`, `config/App.php:7-10`,
`ROUTES:22-36` (grupo `admin` cobre dashboard **e** Eventos), `EC:132-135`
(Q1), `EC:16-27` (O5), `NAV:13-20`, `POP:12-19`, `ACC:23-56,85-97`
(usuários e provider), `tests/Support/Helper/LoginHelper.php`,
B10 da 4.1 (`User::ROLES` com `inclusion`, se já aplicado).

Passos:

1. Testes: `ACC`: organizer acessa `/admin/events` (200) e `/admin`
   (302 para `/dashboard`); Participante segue barrado; `ECT`: organizer
   logado vê só os seus (O5) e não edita Evento do admin (Q1); admin vê
   tudo. `UserTest`: `isOrganizer()`.
2. `SCH:12`: `ENUM('admin', 'organizer', 'participant')`; `US`:
   `isOrganizer()` e `manageEvents(): bool` (`isAdmin() || isOrganizer()`);
   `ROLES` se houver `inclusion`.
3. Middleware `Staff` (ou alias `events_manager`) copiando `Admin.php` com
   `!$user->manageEvents()`; alias em `config/App.php`.
4. `ROUTES`: tire as rotas de Eventos do grupo `admin` e ponha num grupo
   `Route::middleware('staff')->group(...)` dentro de `auth`; `/admin`
   (dashboard) continua só `admin`.
5. `EC::index`/`create`: admin -> `Event::paginate`/`new Event`;
   organizer -> `events()->paginate`/`events()->new` (O5). `findEvent`:
   admin acha qualquer; organizer só os dele (Q1).
6. `NAV:13-20`: link "Eventos" para `manageEvents()`; "Administração"
   só admin. `POP`: um organizer (`organizer@example.com`) com 2 Eventos.
7. `ACC`: terceiro usuário e provider com os três perfis.

Armadilhas:

- ENUM novo exige `./run db:populate`; `inclusion` (B10) precisa do valor
  na lista, senão o populate falha ao salvar o organizer.
- Deixar as rotas de Eventos no grupo `admin` e "liberar" o organizer
  dentro de `Admin.php` mistura responsabilidades; crie o middleware.
- `A9`/`A18` da 4.1 (redirect pós-login por perfil): o organizer cai em
  `/dashboard`; se o pedido incluir "cair na listagem", ajuste
  `AuthenticationsController::authenticate`.
- Os Cests logam `admin@` (`BaseEventsCest:46`) e continuam válidos; um
  Cest de organizer precisa de login próprio (`LoginHelper::login`).
- `isAdmin()` espalhado em views (`NAV:13`) é o custo de não ter
  centralizado o perfil (H2 da 4.1).

Prova: testes; no navegador, logar como organizer e ver só os seus
Eventos, sem link "Administração".

### Q5. Token CSRF nos formulários de Evento

Chance: média-baixa. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/q5-token-csrf`.

Pedido típico: "proteja criar, editar e remover contra CSRF" (variante
de A15 da 4.1, agora no CRUD). Faz par com O9.

Arquivos: `config/App.php:7-10` (aliases), `app/Middleware/Admin.php`
(modelo de middleware), `core/Router/RouteWrapperMiddleware.php:15-25`
(o grupo **interno** roda primeiro), `core/Http/Request.php:24-27,57-60`,
`FORM:16`, `IDX:30-33`, `app/views/authentications/new.phtml` (se cobrir
o login), `CTC:70-84` (teste de controller **não** roda middleware),
`ACC:58-78` (`loggedClient` faz POST em `/login`),
`ACT/BaseEventsCest.php:122-158` (form preenchido pelo navegador: já leva
o token).

Passos:

1. Testes HTTP (`ACC`): `POST /admin/events` sem `_token` -> 302 para a
   origem com flash (ou 403) e nada gravado; com o token lido da página
   `new` (regex no `<input name="_token">`) -> grava. Unitário de
   `Lib\Csrf` (gera, confere, rejeita diferente/ausente).
2. `lib/Csrf.php`:

   ```php
   final class Csrf
   {
       public static function token(): string
       {
           $_SESSION['_token'] ??= bin2hex(random_bytes(32));

           return $_SESSION['_token'];
       }

       public static function check(mixed $given): bool
       {
           return is_string($given) && isset($_SESSION['_token'])
               && hash_equals($_SESSION['_token'], $given);
       }
   }
   ```

3. `app/Middleware/VerifyCsrf.php`: em `POST/PUT/PATCH/DELETE`
   (`$request->getMethod()`), se `!Csrf::check($_POST['_token'] ?? null)`
   -> flash `danger('Sessão expirada, tente de novo')` + redirect para
   `/` (padrão de `Admin.php:23-27`). Alias `'csrf'`.
4. `ROUTES`: envolva os grupos `auth`/`admin` com
   `Route::middleware('csrf')->group(...)` **por fora** (assim roda depois
   do `auth`).
5. Forms: `<input type="hidden" name="_token" value="<?= Csrf::token() ?>">`
   em `FORM:16` e `IDX:30-33` (e no login, se incluído).

Armadilhas:

- Ordem: o middleware do grupo de fora é anexado por último
  (`RouteWrapperMiddleware:21-23`) e roda por último; se o `csrf` for o
  grupo interno, roda antes do `auth` (funciona, mas um POST anônimo
  recebe "sessão expirada" em vez de "faça login"). Com o A17 da 4.1
  aplicado (o grupo de fora roda primeiro), inverta: `csrf` como grupo
  mais interno, senão o teste de `ACC:130-137` (POST sem sessão) recebe
  a recusa do `csrf` em vez do redirect do `auth`.
- `ECT` não passa pelo Router (`CTC:70-84`): não vê o middleware; a
  prova é por HTTP e por Cest.
- `loggedClient` (`ACC:67-78`) posta `/login`: se o login exigir token,
  o helper precisa buscar a página antes (A15 da 4.1 descreve).
- Token por sessão (não por requisição): abas múltiplas continuam
  funcionando.
- Leia o token de `$_POST`. `getParam` lê `$_REQUEST`, que inclui a
  query string (`Request.php:20,57-60`). Combine com O9.

Prova: testes; `curl -X POST -b <cookie> localhost/admin/events` sem
token é recusado.

### Q6. Revisar escape e testar XSS

Chance: média-baixa. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/q6-escape-xss`.

Pedido típico: "ponha `<script>alert(1)</script>` no título e mostre
que nada executa" ou "revise o escape de todas as telas". Hoje os
campos do Evento já saem escapados (`IDX:22,25`, `SHOW:8,11,20,26`,
`FORM:24,31,73,80,87`), mas o resto não: `NAV:23` imprime o e-mail cru,
os dois painéis imprimem o nome cru, o partial de flash imprime tipo e
texto crus, e o título da página é escapado no controller (`EC:37`) em
vez de no layout (`LAYOUT:26`).

Arquivos: `NAV:23`, `app/views/admin/dashboard/index.phtml:1`,
`app/views/dashboard/index.phtml:1`,
`app/views/layouts/_flash_message.phtml:2-3` (e `:6`, short tag
`<? endforeach; ?>`), `LAYOUT:26`, `EC:37` (o único `$title` escapado;
os outros controllers passam texto fixo), `FORM:12-14` (`$feedback` já
escapa o erro), `US:37-47` (nome e e-mail sem validação de formato),
`ECT:17-31` (`setUp` com admin logado), `ECT:151-161`, `ECT:171-189`,
`tests/Integration/Controllers/HomeControllerTest.php` (molde de teste
de controller fora do admin),
`tests/Acceptance/admin/AdminDashboardCest.php:55`.

Passos:

1. Testes em `ECT`:
   - título `<b>x</b>` em `index`, `show` e `edit`: a resposta contém
     `&lt;b&gt;x&lt;/b&gt;` e não contém `<b>x</b>` nem `&amp;lt;`
     (escape duplo). Hoje passa: é a rede para o passo 3;
   - `$this->admin->update(['email' => '<i>a</i>@example.com'])` antes
     do `get('index')`: espera `&lt;i&gt;a&lt;/i&gt;@example.com` (hoje
     falha, `NAV:23`); `Auth::user()` relê o usuário do banco;
   - `FlashMessage::danger('<b>x</b>')` antes do `get('index')`: espera
     o texto escapado (hoje falha);
   - painéis: teste novo com `ControllerTestCase` para
     `App\Controllers\DashboardController` e
     `App\Controllers\Admin\DashboardController`, com um usuário de nome
     `<b>Ana</b>` logado por `Auth::login` (hoje falha).

   ```php
   public function test_event_title_is_escaped_once(): void
   {
       $event = $this->createEvent(['title' => '<b>x</b>']);
       $pages = [
           'index' => [],
           'show' => ['id' => $event->id],
           'edit' => ['id' => $event->id],
       ];

       foreach ($pages as $action => $params) {
           $html = $this->get(
               action: $action,
               controllerName: self::CONTROLLER,
               params: $params
           );
           $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
           $this->assertStringNotContainsString('<b>x</b>', $html);
           $this->assertStringNotContainsString('&amp;lt;', $html);
       }
   }
   ```

2. Views. `NAV:23`:

   ```php
   <?= htmlspecialchars($this->currentUser()->email) ?>
   ```

   Nos dois painéis, `htmlspecialchars($this->currentUser()->name)`. No
   partial de flash:

   ```php
   <div class="alert alert-<?= htmlspecialchars($type) ?> alert-dismissible"
       role="alert">
       <div><?= htmlspecialchars($msg) ?></div>
   ```

   e `:6` vira `<?php endforeach; ?>`.
3. Título: `EC:37` vira `$title = $event->title;` e `LAYOUT:26` vira
   `<?= htmlspecialchars($title) ?>`. Escape na saída, uma vez.

Armadilhas:

- Escape duplo: mudar `LAYOUT:26` sem tirar o `htmlspecialchars` de
  `EC:37` mostra `&lt;b&gt;` literal no `<h1>` do show. A asserção com
  `&amp;lt;` pega.
- Escape na saída, nunca na entrada: `htmlspecialchars` no `fill`
  (`EC:116-130`) ou no model grava `&lt;` no banco, aumenta o tamanho
  (`VARCHAR(255)`) e o form de edição mostra `&amp;lt;`.
- Depois do passo 2, flash com HTML de propósito (negrito) vira texto.
  Hoje nenhum flash tem HTML (`EC:56-139`, `app/Middleware/*.php`).
- PHP 8.1+ já usa `ENT_QUOTES | ENT_SUBSTITUTE` por padrão: aspas
  simples em atributo também saem escapadas; não precisa passar flags.
- Rótulos de `Event::KINDS`/`MODALITIES` (`IDX:23`, `SHOW:14,17`,
  `FORM:40,52`) são constantes do código e não precisam de escape; o
  valor cru do banco, no `??`, já é escapado.
- `nl2br(htmlspecialchars(...))` nessa ordem (`SHOW:11`); invertido, o
  `<br>` vira texto.
- A short tag do flash (`:6`) só funciona porque a imagem não tem
  `php.ini` e o padrão do PHP é `short_open_tag=On`; com
  `php.ini-production` a view quebra. Troque por `<?php`.
- `AdminDashboardCest.php:55` confere `Olá, Admin!`: nome sem caractere
  especial não muda.

Prova: `ECT` e os testes dos painéis; no navegador, Evento com título
`<script>alert(1)</script>` aparece literal na listagem, no detalhe
(`<h1>` e corpo) e no form.

### R1. Flash: texto, título do Evento, tipo `warning`

Chance: média (micro-ajuste de texto; costuma vir dentro de outro
pedido). Tempo: 10-15 min.

Exemplo pronto: `fvp/4.2/exemplos/r1-flash-titulo-warning`.

Pedido típico: "troque o texto do flash ao remover", "mostre o título
do Evento na mensagem" ou "um flash de aviso (`warning`)".

Arquivos: `EC:56, 59, 87, 90, 105, 107, 139` (textos), `FLASH:7-15`
(tipos) e `FLASH:25-28` (um valor por tipo),
`app/views/layouts/_flash_message.phtml:3`
(imprime `$msg` sem escape), `ECT:98, 137, 168, 206, 223, 250, 262-265`
(asserções de texto), `tests/Unit/Lib/FlashMessageTest.php`,
`ACT/DeleteEventCest.php:23,41`, `ACT/CreateSuccessfullyCest.php:28` e
`ACT/UpdateSuccessfullyCest.php:45,71,87` (textos exatos dos flashes).

Passos:

1. Ajuste as asserções de `ECT` e veja falhar.
2. Textos em `EC`. Para o título na mensagem passe o título **cru**:
   `FlashMessage::success("Evento \"{$event->title}\" removido!")`.
3. Escape em um lugar só, o partial: `_flash_message.phtml:3` passa a
   `<div><?= htmlspecialchars($msg) ?></div>`.
4. Tipo novo: `warning(string $value): void` chamando
   `self::message('warning', $value)`
   em `FLASH`; o partial já monta `alert-warning`.
5. Teste unitário copiando `test_danger`.

Armadilhas:

- O partial imprime sem escape: título com `<script>` vira XSS.
  Escape no partial **ou** no controller, nunca nos dois (`&amp;amp;`
  na tela).
- Um flash por tipo (`FLASH:27`): a segunda mensagem do mesmo tipo na
  mesma requisição apaga a primeira.
- Na remoção com Inscrições a mensagem `danger` vem do model
  (`EC:107` -> `EV:162`); mudar o texto é no model.
- Create e update redirecionam para o `show` (`EC:57,88`): o flash de
  sucesso aparece lá.
- O flash da remoção bloqueada começa com minúscula ("não é possível
  remover...", `EV:162` -> `EC:107`): é texto de `errors('base')`, no
  formato das mensagens de campo (`EV:156` e `EV:170` idem). Corrija no
  model; o texto exato está em `EVT:650`, `ECT:263` e
  `ACT/DeleteEventCest.php:41`.
- Três textos não terminam com `!`: `VAL:199` (unicidade, usada só em
  `US:42`), `app/Middleware/Admin.php:18` e
  `app/Middleware/Authenticate.php:15`. Padronizar não quebra teste: os
  Cests usam `see()` por trecho
  (`tests/Acceptance/admin/AdminDashboardCest.php:37,46` e
  `tests/Acceptance/dashboard/DashboardCest.php:28`).
- `short_open_tag` só vem ligado porque a imagem não tem `php.ini`; o
  `php.ini-production` (base do PHP instalado por pacote) o desliga. Aí
  `_flash_message.phtml:6` sai como texto, o `foreach` da linha 1 fica
  aberto e toda página com layout dá parse error. Trocar por
  `<?php endforeach; ?>` é uma linha e não muda nenhum teste.

Prova: `EventsControllerTest`, `FlashMessageTest`; no navegador, criar
um Evento com título `A & "B" <b>x</b>` e removê-lo: o título aparece
literal, escapado uma vez.

### R2. Nome do criador no `show` e na listagem

Chance: média-alta. Tempo: 10-15 min.

Exemplo pronto: `fvp/4.2/exemplos/r2-nome-do-criador`.

Pedido típico: "mostre quem cadastrou o Evento" (usa a relação
`BelongsTo`, que a rubrica da 4.3 cobra).

Arquivos: `EV:117-120` (`user(): BelongsTo`),
`core/Database/ActiveRecord/BelongsTo.php:14` (`get(): Model`),
`SHOW:6-33`, `IDX:11-26`, `ECT:151-161` (`show`), `EVT:443-449`.

Passos:

1. Teste em `ECT`: `show` contém `Admin` (nome do admin do `setUp`,
   `ECT:23`).
2. `SHOW`: par `<dt>Criado por</dt>` e
   `<dd><?= htmlspecialchars($event->user()->get()->name) ?></dd>`.
3. `IDX`: coluna "Criado por" (opcional; é uma consulta por linha).

Armadilhas:

- `get()` devolve `Model`, não `User`: na view tudo bem; em `app/`
  use `instanceof User` para o `phpstan` ou crie `EV::creatorName(): string`
  no model (melhor: regra num só lugar).
- `__get` mágico permite `$event->user->name` (`MODEL:55-68`); prefira
  a forma explícita.
- `user_id` sem usuário (impossível pela FK `SCH:35-36`) daria erro em
  `get()`; não precisa tratar.

Prova: teste; no navegador, "Administrador" aparece no `show`.

### R3. Formatação na view com helper num só lugar

Chance: média. Tempo: 10-15 min.

Exemplo pronto: `fvp/4.2/exemplos/r3-helper-formatacao`.

Pedido típico: "data por extenso", "descrição truncada em 80
caracteres na listagem", "badge colorido por tipo" ou "ordem das
colunas".

Arquivos: `IDX:22-26`, `SHOW:6-33`, `IDX:24` e `SHOW:23`
(`date('d/m/Y', strtotime(...))` repetido), `core/helpers.php:19-22`
(`route()`, modelo de helper global), `EV:122-145` (métodos de
apresentação no model), `ECT:59-68`, `ACT/ListEventsCest.php:28`
(confere `d/m/Y`).

Passos:

1. Teste em `ECT` com a string esperada.
2. Métodos no model: `formattedStartsOn(): string` com
   `date('d/m/Y', strtotime($this->starts_on))` (ou
   `DateTimeImmutable::createFromFormat('!Y-m-d', ...)`, que devolve
   `false` em vez de 01/01/1970 quando a data está vazia) e
   `excerpt(int $max = 80): string` com
   `mb_strimwidth($this->description, 0, $max, '...')`.
   Use nas duas views.
3. Badge: `public const KIND_BADGES = ['lecture' => 'primary', ...]`
   em `EV` ao lado de `KINDS`; na view
   `<span class="badge text-bg-<?= Event::KIND_BADGES[$event->kind]`
   `?? 'secondary' ?>">`.

Armadilhas:

- Lógica de apresentação duplicada em duas views: o professor cobrou
  "rótulos num só lugar" (4.1, C8). Model ou helper, uma vez.
- Data por extenso em pt-BR: `strftime` está deprecado; use
  `IntlDateFormatter` (extensão `intl` **não** está na imagem; confira
  com `php -m`) ou um array de meses em pt-BR no model.
- `mb_strimwidth` com HTML já escapado quebra entidades: trunque antes
  do `htmlspecialchars`.
- `ListEventsCest:28` confere `d/m/Y`: se mudar o formato, mude lá.

Prova: teste; navegador.

### R4. Confirmação antes de remover, JS em arquivo próprio

Chance: média. Tempo: 10-15 min.

Exemplo pronto: `fvp/4.2/exemplos/r4-confirmacao-js-arquivo`.

Pedido típico: "peça confirmação antes de remover". O professor exige
JS e CSS em arquivos próprios, nunca na view (issues dos grupos 5 e 6).

Arquivos: `IDX:30-33` (form de remoção), `LAYOUT:9`
(`/assets/js/application.js` já carregado), `public/assets/js/application.js`,
`ACT/DeleteEventCest.php:50-54` (clica em "Remover" sem `confirm()`).

Passos:

1. `IDX:30-33`: `data-confirm="Remover o evento?"` no `<form>`.
2. `public/assets/js/events.js` (novo) ou um bloco em
   `application.js`:

```js
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("form[data-confirm]").forEach(function (form) {
    form.addEventListener("submit", function (event) {
      if (!window.confirm(form.dataset.confirm)) {
        event.preventDefault();
      }
    });
  });
});
```

3. `LAYOUT:9`: `<script src="/assets/js/events.js"></script>` se for
   arquivo novo.

Armadilhas:

- `onsubmit="return confirm(...)"` inline na view: é exatamente o que o
  professor penaliza.
- O `DeleteEventCest` passa a precisar de `$page->acceptPopup()` depois
  do clique (Selenium); sem isso o Cest trava no diálogo.
- `application.js` só age se existir `image_preview_input`
  (`:2-6`, retorna cedo): coloque o novo código **antes** do `return`
  ou em arquivo separado.
- Cache do navegador: `Ctrl+F5` ao testar.
- Confirmar não resolve o caso mais comum: `IDX:30-33` mostra Remover
  também nos Eventos com Inscrições (1, 2, 3, 4, 6 e 9 do populate); o
  usuário confirma e recebe o flash `danger` de `EV:162`. Esconder o
  botão com `hasEnrollments()` (`EV:142-145`) é R12 e quebra
  `ACT/DeleteEventCest.php:30-48`, que clica nesse botão.
- O `show` não tem Remover (`SHOW:35-36`). Se pedirem lá (R6), o form
  leva o mesmo `data-confirm`; o JS por seletor cobre as duas telas.

Prova: Cest ajustado; navegador.

### R5. Mostrar `created_at` e `updated_at`

Chance: média. Tempo: 15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/r5-created-updated-at`.

Pedido típico: "mostre quando o Evento foi cadastrado e alterado pela
última vez". As colunas existem (`SCH:32-33`) mas não estão em
`$columns`, então o ActiveRecord nem as lê.

Arquivos: `SCH:32-33`, `EV:48-58`, `MODEL:233-256` (`findById` só
seleciona `$columns`), `MODEL:139-187` (`save()` grava **todas** as
colunas de `$columns`: pôr `created_at` lá faz o `INSERT` mandar `NULL`),
`SHOW:6-33`, `IDX:11-26`, `US:76-79` (`registerLogin` grava hora do PHP),
`Dockerfile:10` (PHP em `America/Sao_Paulo`; o MySQL do compose fica em
UTC), `ECT:151-161`, `EVT:302-308`.

Passos:

1. Testes: `EVT`: após `save()`, `timestamps()` devolve `created_at` e
   `updated_at` não nulos e iguais; após `update(...)`, `updated_at`
   muda (ou, sem esperar 1 s, só confira que não é nulo). `ECT`: `show`
   contém "Criado em".
2. Forma 1 (SQL no model, sem mexer em `$columns`):

   ```php
   /** @return array{created_at: ?string, updated_at: ?string} */
   public function timestamps(): array
   {
       if ($this->newRecord()) {
           return ['created_at' => null, 'updated_at' => null];
       }

       $stmt = Database::getDatabaseConn()->prepare(
           'SELECT created_at, updated_at FROM events WHERE id = :id'
       );
       $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);
       $stmt->execute();

       $row = $stmt->fetch(PDO::FETCH_ASSOC);

       return $row ?: ['created_at' => null, 'updated_at' => null];
   }
   ```

3. Forma 2 (bônus de framework): `protected static array $readOnly = []`
   em `Model`, incluído no `SELECT` de `findById`/`all`/`where`/`Paginator`
   mas excluído do `INSERT`/`UPDATE`; `Event::$readOnly = ['created_at',
   'updated_at']`. Mais trabalho, elimina a consulta extra.
   Forma 3 (a do exemplo pronto, sem consulta extra nem mudança no core):
   as duas colunas entram em `$columns` e `Event` sobrescreve `save()`
   (`created_at = date('Y-m-d H:i:s')` só se `newRecord()`, `updated_at`
   sempre; se `parent::save()` falhar, restaura os dois valores) e
   `update(array $data)` (acrescenta `updated_at` se não vier). Nada vai
   `NULL`, e a hora é a do PHP em tudo, como `last_login_at`. No
   `EVT`, prove que a edição muda `updated_at` e mantém `created_at`.
4. `SHOW`: "Criado em dd/mm/aaaa hh:mm" e "Atualizado em ...";
   formate com `DateTimeImmutable` (R3).

Armadilhas:

- Pôr `created_at` em `$columns` sem preencher: `save()` envia `NULL`
  e o MySQL 8 (`explicit_defaults_for_timestamp` ligado) recusa
  `NOT NULL`; preencher no construtor com `date()` funciona, mas então
  `updated_at` para de atualizar sozinho (o `UPDATE` reenvia o valor
  antigo e o `ON UPDATE` só age quando a coluna não é escrita). A
  Forma 3 resolve isso preenchendo `updated_at` em `save()`/`update()`.
- Fuso: `TIMESTAMP` é convertido pelo fuso da sessão do MySQL (UTC no
  contêiner) e o PHP está em São Paulo: a hora exibida pode vir 3 h
  "adiantada" em relação a `last_login_at` (gravado por `date()` do PHP,
  `US:78`). Converta na exibição
  (`new DateTimeImmutable($value, new DateTimeZone('UTC'))->setTimezone(...)`)
  ou fixe `SET time_zone` na conexão. Diga qual escolheu.
- `fetch()` devolve `false` sem linha: por isso o `?:`.
- Teste de "updated_at muda" depende de 1 s de diferença: evite
  `sleep(1)`; confira só não nulo ou use `update()` com data fixa.

Prova: testes; no navegador o `show` mostra as duas datas.

### R6. Partials reutilizados e botão Remover no `show`

Chance: média-baixa. Tempo: 15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/r6-partials-remover-show`.

Pedido típico: "a tabela de Eventos aparece em três telas (listagem,
lotados, Participante): extraia um partial; e ponha Remover também no
detalhe". Reutilização de view, como `_form.phtml` já faz para new/edit.

Arquivos: `FORM:1-15` (partial com docblock `@var`, o modelo),
`app/views/admin/events/new.phtml:1-4` (como incluir: `require __DIR__
. '/_form.phtml'`), `IDX:8-38` (tabela), O1 `full.phtml`, O2
`events/index.phtml`, `IDX:28-33` (ações), `SHOW:35-36`,
`CTRL:32-39` (`render` faz `extract`: o partial vê as mesmas variáveis),
`ACT/ListEventsCest.php`, `ACT/DeleteEventCest.php:50-54`
(`clickRemove`), `ACT/BaseEventsCest.php:178` (`rowOf`).

Passos:

1. Testes existentes continuam (mesma saída HTML); novo em `ECT`: `show`
   contém o form `DELETE` do Evento.
2. `app/views/admin/events/_table.phtml`: a `<table>` de `IDX:8-38`
   com `$events` e um flag `$showActions` (docblock `@var bool`);
   `_actions.phtml` com Ver/Editar/Remover de `IDX:28-33`.
3. `IDX`: `$showActions = true; require __DIR__ . '/_table.phtml';`;
   `full.phtml` idem sem Remover; `events/index.phtml` (O2) com
   `$showActions = false`.
4. `SHOW:35-36`: `require __DIR__ . '/_actions.phtml'` (ou só o form
   `DELETE`), e Voltar.

Armadilhas:

- Variável não definida antes do `require` -> warning -> 500: defina
  todos os flags em cada tela que inclui o partial.
- `$this` dentro do partial é o controller (mesmo escopo do `render`):
  `$this->currentUser()` funciona, como em `NAV:5`.
- O partial de paginação (`PAGES`) **não** vê essas variáveis
  (`PAG:93-97`); não misture os dois.
- `DeleteEventCest` clica "Remover" dentro de `rowOf()`: um segundo
  Remover no `show` não atrapalha; um Cest do `show` precisa de
  `acceptPopup()` se R4 estiver aplicado.
- Caminho do `require`: relativo ao arquivo (`__DIR__`), não à raiz.
- Na base só a listagem tem a tabela (`IDX:8-38`): as outras duas
  telas dependem de O1 (`full.phtml`) e O2 (`events/index.phtml`). Sem
  eles, o pedido fica em listagem + `show`.

Prova: testes; três telas iguais no navegador.

### R7. Voltar à mesma página da listagem depois de remover ou editar

Chance: média-baixa. Tempo: 10-15 min.

Exemplo pronto: `fvp/4.2/exemplos/r7-voltar-mesma-pagina`.

Pedido típico: "removi um Evento da página 3 e o sistema me jogou na
página 1" ou "o Cancelar da edição devia voltar ao detalhe".

Arquivos: `EC:110` (`destroy` -> `admin.events.index`), `IDX:30-33`
(form de remoção), `CTRL:63-67` (`redirectBack()`, usa `HTTP_REFERER`),
`FORM:92` (Cancelar -> index), `app/views/admin/events/edit.phtml`,
`ECT:242-251` e `:261` (`Location` exato), `ACT/DeleteEventCest.php`,
N10 (página fora do intervalo).

Passos:

1. Testes em `ECT`: `destroy` com `params: ['page' => '2']` ->
   `Location: /admin/events/page/2`; sem `page` -> `/admin/events`;
   `page` inválido -> `/admin/events`.
2. `IDX:30-33`: no form de remoção,
   `<input type="hidden" name="page" value="<?= $paginator->getPage() ?>">`.
3. `EC::destroy`: `$page = $request->getParam('page')`; se `is_string` +
   `ctype_digit` + `> 1` ->
   `route('admin.events.paginate', ['page' => (int) $page])`, senão
   `route('admin.events.index')`.
4. Cancelar da edição: em `edit.phtml` passe
   `$cancelUrl = route('admin.events.show', ['id' => $event->id])` e em
   `new.phtml` `$cancelUrl = route('admin.events.index')`; `FORM:92` usa
   `$cancelUrl`.
5. Alternativa: `redirectBack()` (`CTRL:63-67`), que cai em `/` quando
   não há `Referer` (testes, `curl`): menos previsível; prefira o
   `page` explícito.

Armadilhas:

- Removeu o último item da página 3: a página 3 deixa de existir;
  com N10 isso vira flash "Página inválida"; sem N10, "Nenhum evento"
  sem paginação. Trate (volte à anterior) ou documente.
- `ECT:249,261` comparam `Location` exato: atualize os testes que
  passarem `page`.
- `$cancelUrl` indefinido em quem inclui `_form.phtml`: 500.
- `HTTP_REFERER` pode ser omitido pelo navegador (política de
  `Referrer-Policy`); não dependa dele para fluxo principal.

Prova: testes; no navegador, remover um Evento da página 2 e ficar nela.

### R8. Lista de todos os erros no topo do formulário

Chance: média. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/r8-erros-no-topo-do-form`.

Pedido típico: "quando o cadastro falhar, mostre no topo quais campos
estão errados" (item 1 da demonstração da rubrica). O core não devolve
todos os erros: `errors()` sem índice retorna `null` (`MODEL:122-129`).

Arquivos: `MODEL:122-134` (`errors`, `addError`; a propriedade
`$errors` é acessível na subclasse), `EC:59,90` (flash genérico),
`FORM:11-16` (helpers e abertura do form),
`app/views/layouts/_flash_message.phtml:3` (sem escape), `EV` (rótulos num
só lugar, C8 da 4.1), `ECT:114-138`.

Passos:

1. Teste em `ECT`: create inválido com título e local vazios -> a resposta
   tem `<ul class="form-errors">` com "Título" e "Local".
2. `MODEL`, ao lado de `errors()`:

   ```php
   /** @return array<string, string> */
   public function allErrors(): array
   {
       return $this->errors;
   }
   ```

3. `EV`:

   ```php
   public const LABELS = ['title' => 'Título', 'description' => 'Descrição'];

   public static function label(string $field): string
   {
       return self::LABELS[$field] ?? $field;
   }
   ```

4. `FORM`, antes do `<form>`:

   ```php
   <?php if ($event->hasErrors()) : ?>
       <ul class="form-errors alert alert-danger">
           <?php foreach ($event->allErrors() as $field => $message) : ?>
               <li><?= htmlspecialchars(
                   Event::label($field) . ' ' . $message
               ) ?></li>
           <?php endforeach; ?>
       </ul>
   <?php endif; ?>
   ```

5. O flash `danger` de `EC:59,90` pode sair (duplica) ou ficar como
   resumo; decida.

Armadilhas:

- `errors('base')` só nasce em `safeDestroy` (`EV:151-173`), e o
  `destroy` redireciona com flash: hoje não chega ao form. Só importa se
  um fluxo novo re-renderizar o form com erro `base`; então trate o
  rótulo (`LABELS['base'] = ''`).
- Mensagens começam em minúscula e terminam em `!` ("não pode ser
  vazio!"): a concatenação "Título não pode ser vazio!" fica certa.
- Rótulos duplicados entre `FORM` (labels) e a lista: tire de um só
  lugar (`LABELS`), e use também nos `<label>` do form.
- Escape no partial; `hasErrors()` existe (`MODEL:117-120`).
- Mudança em `core/`: um método de leitura, com PHPDoc de array.

Prova: teste; no navegador, enviar o form vazio e ver a lista.

### R9. Lista de presença imprimível com layout próprio

Chance: média. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/r9-lista-de-presenca-print`.

Pedido típico: "uma página para imprimir a lista de presença: inscritos
com coluna para assinatura, sem navbar". Único cenário que usa
`Controller::$layout` (`CTRL:11`), hoje só com o layout padrão.

Arquivos: `CTRL:11` (`protected string $layout = 'application'`) e
`CTRL:32-39` (`render` monta `layouts/{$this->layout}.phtml`),
`LAYOUT:1-35` (modelo), `ROUTES:26-35` (rota
`GET /admin/events/{id}/attendance`, 4 partes), O3 (`participants()`),
`EN:20-28`, `public/assets/css/application.css` (`@media print`),
`ECT:151-161`, `ACC:85-97`.

Passos:

1. Testes: `ECT`: `attendance` lista os inscritos e **não** contém a
   navbar (a classe `navbar` de `NAV`; o nome do site pode estar no
   `<title>`); `ACC`: rota em `eventRoutes()`
   + admin 200.
2. `app/views/layouts/print.phtml`: `<!DOCTYPE html>`, `<head>` com o CSS,
   `<main>` com `<h1><?= $title ?></h1>` e `require $view`; sem navbar
   nem flash.
3. `EC::attendance(Request $request)`: `findEvent`;
   `$this->layout = 'print';` antes do `render(...)` da view
   `admin/events/attendance` com `event`, `participants` e `title`,
   sendo `$participants = $event->participants()->get()` (O3).
4. View: tabela Nome, E-mail, Assinatura (célula vazia); botão
   "Imprimir" com `window.print()` em arquivo JS (R4) ou só `Ctrl+P`.
5. CSS: `@media print { .no-print { display: none; } }` em
   `application.css` (29 linhas hoje).

Armadilhas:

- `$layout` é propriedade de instância: mude só na ação que precisa;
  as outras continuam com `application`.
- O layout novo precisa imprimir `$title` e incluir `$view` exatamente
  como `LAYOUT:24-30`, senão a view não aparece.
- Sem navbar não há link de volta: ponha "Voltar" na própria view.
- JS inline `onclick="window.print()"` é o que o professor penaliza
  (R4): arquivo em `public/assets/js`.
- C4 da 4.1 (layout do login) é o mesmo mecanismo.

Prova: testes; no navegador, `/admin/events/1/attendance` com os 3
inscritos do populate e `Ctrl+P`.

### R10. "Salvar e cadastrar outro"

Chance: média-baixa. Tempo: 10-15 min.

Exemplo pronto: `fvp/4.2/exemplos/r10-salvar-e-cadastrar-outro`.

Pedido típico: "um segundo botão que salva e já abre um formulário em
branco".

Arquivos: `FORM:91` (botão Salvar), `EC:49-63` (`create`: redirect para
o `show`), `ECT:85-99` (`Location` do create), `ACT/CreateSuccessfullyCest.php`.

Passos:

1. Testes em `ECT`: `create` com `'commit' => 'save_and_new'` ->
   `Location: /admin/events/new` e Evento gravado; sem `commit` -> `show`.
2. `FORM:91`: segundo botão

   ```html
   <button type="submit" name="commit" value="save_and_new"
           class="btn btn-outline-primary">Salvar e cadastrar outro</button>
   ```

   (`name` fora de `event[]`, para não entrar no `fill`).
3. `EC::create`, após o `save()` com sucesso:

   ```php
   $target = $request->getParam('commit') === 'save_and_new'
       ? route('admin.events.new')
       : route('admin.events.show', ['id' => $event->id]);
   $this->redirectTo($target);
   ```

4. Cest: clicar no botão novo e `see('Novo Evento', 'h1')` +
   `see('Evento cadastrado com sucesso!')`.

Armadilhas:

- `getParam('commit')` pode vir array: a comparação estrita com string
  já protege.
- O botão não pode ficar dentro de `event[...]`, senão `fill()` tenta
  atribuir `commit` ao model (`MODEL:86`: `Property commit not found`).
  Com `FIELDS` fechado (`EC:14`) ele é ignorado, mas não dependa disso.
- Flash de sucesso aparece na tela de novo cadastro: ok, é o esperado.
- `update` não ganha o botão (não faz sentido editar e "cadastrar
  outro").

Prova: teste; navegador.

### R11. Evento anterior e próximo no detalhe

Chance: média-baixa. Tempo: 20-25 min.

Exemplo pronto: `fvp/4.2/exemplos/r11-anterior-proximo-no-show`.

Pedido típico: "no detalhe, links para o Evento anterior e o próximo
pela data".

Arquivos: `EV` (dois métodos com SQL; desempate por `id`), `SHOW:35-36`,
O2 (`events/show.phtml`), `EVT:366-376` (modelo de teste de consulta),
`ECT:151-161`, N3 (mesma noção de ordem).

Passos:

1. Testes em `EVT`: três Eventos em datas crescentes: o do meio tem
   `previous()` e `next()` certos; o primeiro tem `previous() === null`;
   dois na mesma data desempatam por `id`.
2. `EV`:

   ```php
   public function next(): ?static
   {
       return $this->neighbour('>', 'ASC');
   }

   public function previous(): ?static
   {
       return $this->neighbour('<', 'DESC');
   }

   private function neighbour(string $op, string $dir): ?static
   {
       $columns = implode(', ', static::$columns);
       $sql = "SELECT id, {$columns} FROM events "
           . "WHERE (starts_on {$op} :date "
           . "OR (starts_on = :same_date AND id {$op} :id)) "
           . "ORDER BY starts_on {$dir}, id {$dir} LIMIT 1";
       $stmt = Database::getDatabaseConn()->prepare($sql);
       $stmt->bindValue(':date', $this->starts_on);
       $stmt->bindValue(':same_date', $this->starts_on);
       $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);
       $stmt->execute();
       $row = $stmt->fetch(PDO::FETCH_ASSOC);

       return $row ? new static($row) : null;
   }
   ```

3. `SHOW:35-36`: links "« Anterior" / "Próximo »" quando não nulos.

Armadilhas:

- `$op` e `$dir` são texto fixo do model (`'<'`/`'>'`, `'ASC'`/`'DESC'`),
  nunca entrada do usuário: diga isso ao interpolar.
- Sem desempate por `id`, `starts_on >` pula os outros Eventos do mesmo
  dia; com `>=`, a consulta devolve o próprio Evento ou alterna entre
  dois.
- Placeholder repetido (`:date` duas vezes) só funciona com prepares
  emulados (o padrão do `pdo_mysql`); nomes distintos não dependem disso.
- Nos extremos os métodos devolvem `null`: a view precisa testar.
- `PDO::PARAM_INT` no `id`; `starts_on` como string `Y-m-d`.

Prova: testes; no navegador, navegar do Evento 1 ao 16 pelos links.

### R12. Tela reflete as regras do model

Chance: média. Tempo: 10-20 min.

Exemplo pronto: `fvp/4.2/exemplos/r12-tela-reflete-regras`.

Pedido típico: "se o Evento tem inscritos, nem mostre o Remover" ou "o
campo Vagas deveria dizer o mínimo aceito". Hoje a listagem mostra
Remover em toda linha (`IDX:30-33`) e o clique só falha depois, no
`safeDestroy` (`EV:160-164`); `hasEnrollments()` (`EV:142-145`) não é
usado em nenhuma view. O campo Vagas tem `min="1"` fixo (`FORM:87`),
mesmo com 3 inscritos.

Arquivos: `EV:104-115` (capacidade >= Inscrições), `EV:125-145`
(`enrollmentsCount`, `hasEnrollments`), `EV:151-173` (`safeDestroy`: a
regra continua aqui), `IDX:26-34`, `FORM:84-89` (`novalidate` em
`FORM:16`), `SHOW:35-36`, `ECT:253-266` (servidor recusa remover com
Inscrição), `ECT:302-313` (`enroll`), `EVT:67-74`,
`ACT/DeleteEventCest.php:30-48`, `ACT/ListEventsCest.php:30-32`,
`ACT/UpdateWithInvalidDataCest.php:69-77`,
`core/Database/Database.php:10-21`.

Passos:

1. Testes. `EVT`: `isRemovable()` falso para Evento novo, verdadeiro
   para salvo sem Inscrições, falso depois de `enroll($event, 1)`;
   `minCapacity()` é 1 sem Inscrições e 3 com 3. `ECT`: dois Eventos,
   um com `enroll`; no `index`, `action="/admin/events/{$free->id}"`
   aparece e o do outro não (o link Ver usa `href`, não `action`);
   `edit` de Evento com 2 Inscrições contém `min="2"`; `new` contém
   `min="1"`. `ECT:253-266` continua: o `DELETE` direto segue barrado.
2. `EV`, depois de `hasEnrollments()`:

   ```php
   /** Mesma regra de safeDestroy(): salvo e sem Inscrições. */
   public function isRemovable(): bool
   {
       return !$this->newRecord() && !$this->hasEnrollments();
   }

   /** Menor capacidade aceita na edição (EV:104-115). */
   public function minCapacity(): int
   {
       return max(1, $this->enrollmentsCount());
   }
   ```

3. `IDX:30-33`:

   ```php
   <?php if ($event->isRemovable()) : ?>
       <form class="d-inline" method="post"
           action="<?= route('admin.events.destroy', ['id' => $event->id]) ?>">
           <input type="hidden" name="_method" value="DELETE">
           <button type="submit" class="btn btn-sm btn-outline-danger">
               Remover
           </button>
       </form>
   <?php else : ?>
       <span class="small text-body-secondary">Com inscrições</span>
   <?php endif; ?>
   ```

4. `FORM:84-89`:

   ```php
   <?php $minCapacity = $event->minCapacity(); ?>
   <input type="number" class="form-control<?= $invalid('capacity') ?>"
       id="event_capacity" name="event[capacity]"
       min="<?= $minCapacity ?>"
       value="<?= htmlspecialchars((string) $event->capacity) ?>">
   <?= $feedback('capacity') ?>
   <?php if ($event->hasEnrollments()) : ?>
       <div class="form-text">
           Mínimo: <?= $minCapacity ?> (total de inscrições).
       </div>
   <?php endif; ?>
   ```

5. Cests. `DeleteEventCest.php:38-41`, no lugar do clique:

   ```php
   $page->dontSeeElement(
       $this->rowOf('Evento 01') . "//button[contains(., 'Remover')]"
   );
   $page->see('Com inscrições', $this->rowOf('Evento 01'));
   ```

   `ListEventsCest.php:32`:

   ```php
   $this->assertContains(
       $event->hasEnrollments() ? 'Com inscrições' : 'Remover',
       $row
   );
   ```

Armadilhas:

- A regra continua em `safeDestroy()` (`EV:151-173`): esconder o botão
  é só visual. Um `DELETE` forjado ainda chega ao servidor, e
  `ECT:253-266` prova que ele recusa.
- `DeleteEventCest.php:38` clica Remover no Evento 01 (3 Inscrições):
  sem o botão, o clique falha. A recusa do servidor fica no `ECT`.
- `ListEventsCest.php:32` confere `Remover` em toda linha da página 1;
  os Eventos 01 e 03 têm Inscrições (`BaseEventsCest.php:99-105`).
  Alternativa: botão `disabled` com o texto Remover mantém esse Cest
  verde, mas `DeleteEventCest.php:38-41` quebra igual (o clique não
  envia nada).
- `novalidate` (`FORM:16`): o `min` só ajuda as setas do campo e o
  leitor de tela; quem barra é o model (`EV:104-115`).
  `UpdateWithInvalidDataCest.php:71-76` digita 2 com 3 inscritos e
  espera o erro do servidor: não tire o `novalidate`.
- N+1: `IDX:26` já conta Inscrições por linha; `isRemovable()` faz mais
  uma contagem por linha, cada uma com PDO novo
  (`core/Database/Database.php:10-21`). Com 5 linhas é aceitável; diga
  isso e cite N11.
- Decisão no model (`isRemovable()`), não `enrollmentsCount() === 0` na
  view: é a mesma regra do `safeDestroy`, num lugar só.
- Com R6 (Remover no show), use o mesmo `isRemovable()` em
  `SHOW:35-36`.

Prova: testes; no navegador, a página 1 não mostra Remover nos Eventos
1 a 4 do populate, e o Editar do Evento 1 mostra "Mínimo: 3".

### R13. Textos de vagas com concordância

Chance: média-baixa. Tempo: 5-10 min.

Exemplo pronto: `fvp/4.2/exemplos/r13-concordancia-vagas`.

Pedido típico: "'1 disponíveis' está errado" ou "a coluna Vagas mostra
'1/10': são vagas ou inscritos?". Hoje `SHOW:30-31` imprime
`1/4 (3 disponíveis)`; com 1 vaga sai `(1 disponíveis)` e com 0,
`(0 disponíveis)`. `IDX:15` chama a coluna de "Vagas", mas `IDX:26`
mostra inscritos/capacidade.

Arquivos: `EV:125-132` (`enrollmentsCount`), `EV:134-140`
(`availableSpots`, com `max(0, ...)`), `SHOW:28-32`, `IDX:15,26`,
`ECT:59-68` (`'1/10'` em `:67`), `ECT:151-161` (`'1/4'` em `:159`),
`ACT/ListEventsCest.php:35-38` (`3/3`, `0/22`, `1/23`), `EVT:67-74`
(`enroll`), `EVT:483-491`, `MODEL:189` (`update()` não valida).

Passos:

1. Testes. `EVT`: capacidade 3 sem Inscrições ->
   `'3 vagas disponíveis'`; com 2 -> `'1 vaga disponível'`; com 3 ->
   `'Lotado'`. Borda: capacidade abaixo das Inscrições gravada por
   `update(['capacity' => 1])` (não valida) também dá `'Lotado'`, nunca
   número negativo. `ECT`: copie `:151-161` com capacidade 2 e um
   `enroll`; espere `'1 vaga disponível'` e nenhum `'disponíveis'`.
2. `EV`, depois de `availableSpots()`:

   ```php
   /** Vagas restantes em texto, com concordância. */
   public function availableSpotsLabel(): string
   {
       $spots = $this->availableSpots();

       return match ($spots) {
           0 => 'Lotado',
           1 => '1 vaga disponível',
           default => "{$spots} vagas disponíveis",
       };
   }
   ```

3. `SHOW:31`: `(<?= $event->availableSpotsLabel() ?>)`.
4. `IDX:15` e `SHOW:28`: `Inscritos/Vagas`. O corpo `1/10` fica igual.

Armadilhas:

- Mudar o formato `x/y` (para "1 de 10", por exemplo) quebra `ECT:67`,
  `ECT:159` e `ListEventsCest.php:36-38`. Troque só o cabeçalho, ou
  atualize os três na mesma mudança.
- Texto montado no model (ou helper, R3), uma vez. `if` de plural
  repetido em duas views é o que o professor cobra.
- `match` compara com `===`: `availableSpots()` devolve `int`
  (`EV:137-140`), então `0` e `1` casam. O `capacity` string do PDO já
  passou por `(int)` em `EV:139`.
- `availableSpotsLabel()` troca a chamada de `availableSpots()` em
  `SHOW:31`: o número de consultas do show não muda.
- Com N6 (badge "Lotado" na listagem), use o mesmo texto ou o mesmo
  `availableSpots() === 0`.
- A remoção barrada também tem plural fixo: "com inscrições (1)"
  (`EV:162`). Mudar esse texto quebra `ECT:263` e
  `DeleteEventCest.php:41`.

Prova: testes; no navegador, o Evento 1 (3/3) mostra "Lotado" no
detalhe; o Evento 3 com capacidade 2 mostra "1 vaga disponível".

### R14. Título da aba com o nome do Evento

Chance: baixa-média. Tempo: 10-15 min.

Exemplo pronto: `fvp/4.2/exemplos/r14-titulo-da-pagina`.

Pedido típico: "a aba do navegador deveria mostrar o nome do Evento".
Hoje toda página tem `<title>Eventos do Câmpus</title>` fixo
(`LAYOUT:6`); o `$title` que cada action passa (`EC:25,37,45,73`) só vai
para o `<h1>` (`LAYOUT:26`). O layout começa em `<html>`, sem
`<!DOCTYPE html>` (`LAYOUT:1`): o navegador renderiza em modo quirks.

Arquivos: `LAYOUT:1-6`, `LAYOUT:24-28`, `EC:37` (`htmlspecialchars` no
controller), `EC:73,91` ("Editar Evento"), `EC:74,92` (`compact`),
`CTRL:32-38` (`render` faz `extract`: o layout vê `$title`),
`app/Controllers/HomeController.php:11` e os outros controllers
(`$title` fixo), `ECT:48,74,129,181,220` e
`tests/Integration/Controllers/HomeControllerTest.php:14` (regex do
`<h1>`), `ACT/ListEventsCest.php:55-57`,
`ACT/CreateSuccessfullyCest.php:29`, `ACT/UpdateSuccessfullyCest.php:46`
(título do Evento no `h1`).

Passos:

1. Testes em `ECT`: `show` de `'Palestra Final'` contém
   `<title>Palestra Final | Eventos do Câmpus</title>`; `index` contém
   `<title>Eventos | Eventos do Câmpus</title>`. Borda: título
   `A & <b>B</b>` sai como `A &amp; &lt;b&gt;B&lt;/b&gt;` no `<title>` e
   no `<h1>`, sem `&amp;amp;`. Cest: depois do clique em Ver
   (`ListEventsCest.php:55-57`), `$page->seeInTitle('Evento 01');`.
2. `LAYOUT`:

   ```php
   <!DOCTYPE html>
   <html lang="pt-br">
   ...
   <title><?= htmlspecialchars($title) ?> | Eventos do Câmpus</title>
   ```

   e `LAYOUT:26`: `<?= htmlspecialchars($title) ?>`.
3. `EC:37`: `$title = $event->title;` (cru; o layout escapa).
4. Opcional, nome também na aba do editar sem mudar o `<h1>`:
   `$pageTitle = "Editar: {$event->title}";` no `compact` de `EC:74` e
   `EC:92`, e no layout `htmlspecialchars($pageTitle ?? $title)`.

Armadilhas:

- Escape duplo: com o layout escapando e `EC:37` ainda escapando,
  `A & B` aparece na aba como `A &amp; B`. Escape uma vez, no layout.
- Os testes do `<h1>` (`ECT:48,74,129,181,220`) usam `\s*` em volta do
  texto: mantenha o `<h1>` de `LAYOUT:25-27` e troque só o `echo`.
  Títulos sem caractere especial saem iguais.
- O `<title>` muda em todas as páginas ("Home Page | Eventos do
  Câmpus"). Nenhum teste confere `<title>` hoje.
- `$title` faltando num `render` agora quebra em dois lugares do layout
  (500).
- `<!DOCTYPE html>` tira o modo quirks, que o Bootstrap 5 não suporta.
  Rode os Cests: espaçamentos podem mudar um pouco
  (`BaseEventsCest.php:44-45` usa janela 1280x2400).
- `$pageTitle` só em edit e update: sem o `??` no layout, as outras
  páginas dão warning -> 500. Depois de um update inválido,
  `$event->title` é o texto digitado (pode estar vazio).
- Q6 mexe nas mesmas linhas (`EC:37`, `LAYOUT:26`): faça uma vez só.

Prova: `ECT` e o Cest; no navegador, o detalhe do Evento 1 mostra o
nome na aba e a listagem mostra "Eventos | Eventos do Câmpus".

### S1. Teste de aceitação da modificação

Chance: alta. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/s1-teste-aceitacao-filtro`.

Pedido típico: depois de uma modificação (N1, N3, O1, P2...), "escreva
o teste de aceitação dela". Os sete cenários do CRUD já têm Cest; o
que falta é sempre o da coisa nova.

Arquivos (modelos): `ACT/BaseEventsCest.php` (base: 16 Eventos "Evento
01" a "Evento 16", `_before` com login do admin `:33-47`, `fillEventForm`
`:122-158`, `validEventData` `:163-175`, `rowOf` `:178-181`),
`ACT/ListEventsCest.php:10-44`, `ACT/DeleteEventCest.php:11-28` (curto),
`ACT/CreateSuccessfullyCest.php` (formulário completo),
`tests/Acceptance/BaseAcceptanceCest.php`, `tests/acceptance.suite.yml`,
`tests/Support/Helper/LoginHelper.php:11-27`.

Passos:

1. `tests/Acceptance/admin/events/<Nome>Cest.php`, namespace
   `Tests\Acceptance\admin\events`, `extends BaseEventsCest`. Sem
   `_before`: a base cria os dados e loga.
2. Um método por cenário: `$page->wantTo(...)`, `amOnPage`, `click`,
   `see`, `seeCurrentUrlEquals`. Filtro (N1):
   `amOnPage('/admin/events?kind=workshop')`, `see('Evento 02', 'table')`,
   `dontSee('Evento 01', 'table')`.
3. Formulário:
   `fillEventForm($page, $this->validEventData(['title' => '...']))`
   e `click('Salvar')`. A data é definida por `executeJS`
   (`:148-154`); não digite no `date`.
4. Cenário de Participante (O2, P2): a base loga o admin; para logar
   outro, crie uma base própria copiando `_before` com
   `$page->login('participante1@example.com', self::PASSWORD)`.
5. `./run test:browser tests/Acceptance/admin/events/<Nome>Cest.php`.

Armadilhas:

- A base define os dados: títulos `Evento %02d` (`:63-66`), tipos em
  rodízio (`:79-80,87-88`), `PER_PAGE = 5`, `TOTAL_EVENTS = 16`
  (`:22-23`). Se o pedido muda paginação ou ordem, base e Cests
  acompanham (N3, N4).
- `LoginHelper::logout()` clica em `fulano@example.com` fixo
  (`LoginHelper.php:25`): não use com o admin (cenário F3 da 4.1).
- A base redimensiona para 1280x2400 (`:45`): o Selenium não clica em
  elemento fora da tela; copie o padrão.
- Radio: `checkOption('#event_modality_online')`; select:
  `selectOption('#event_kind', 'Oficina')` (rótulo ou valor).
- Selenium de pé (`./run up -d`); o teste demora.
- Texto conferido é o texto exato do flash (`EC:56,87,105`).

Prova: o teste passa; quebre a funcionalidade e ele deve falhar.

### S2. Teste de acesso por HTTP da rota nova

Chance: alta. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/s2-teste-acesso-rota-nova`.

Pedido típico: "prove que quem não é admin não acessa a rota nova" ou
"que sem login vai para `/login`".

Arquivos: `ACC:85-97` (`eventRoutes()`, data provider das 8 rotas),
`ACC:104-110` (`methodOverrideRoutes`), `ACC:113-137` (sem sessão ->
`/login`; Participante -> `/dashboard`; `_method`), `ACC:139-160`
(Participante não cria, altera nem remove), `ACC:162-183` (admin 200),
`ACC:58-78` (`newClient` em `http://web:8080`, `loggedClient`),
`tests/Integration/Access/DashboardAccessTest.php` (rota `auth` sem
admin).

Passos:

1. Rota admin: uma linha em `eventRoutes()`:
   `'admin.events.full' => ['GET', '/admin/events/full'],`. Os dois
   testes com data provider cobrem sem sessão e Participante.
2. Admin deve ver 200: copie `:162-173` com `loggedClient($this->admin)`
   e `get('/admin/events/full')`.
3. Rota `auth` (O2, P2, P7): classe nova em `tests/Integration/Access/`
   copiando `DashboardAccessTest`: sem sessão 302 `/login`;
   Participante 200 (ou 302 para o destino esperado após POST).
4. Rota pública: espere 200 sem sessão.

Armadilhas:

- Esses testes passam pelo Router de verdade (HTTP ao contêiner
  `web`): pegam rota mal posicionada, diferente do `CTC`.
- `loggedClient` exige 302 para `/dashboard` após o login
  (`ACC:74-75`): se o login mudar (P12, ou A4 da 4.1), ajuste.
- Rotas PUT/DELETE: também em `methodOverrideRoutes` (`:104-110`).
- O schema é recriado a cada teste (`TestCase.php:10-14`): o `id` do
  Evento do `setUp` é 1; `eventRoutes()` usa `/1` fixo.
- Em `./run test tests/Integration` o contêiner `web` precisa estar de
  pé (`./run up -d`).

Prova: `./run test tests/Integration/Access`.

### S3. Teste unitário do método novo do model

Chance: alta. Tempo: 10-15 min.

Exemplo pronto: `fvp/4.2/exemplos/s3-teste-unitario-model`.

Pedido típico: "escreva o teste do método que você criou"
(`searchByTitle`, `upcoming`, `cancel`, `duplicate`, `participants`).

Arquivos: `EVT:16-76` (helpers: `createUser`, `params`, `createEvent`,
`enroll`), `EVT:128-138` (`errorsOf`), `EVT:366-376` (`where`),
`EVT:465-497` (contagens), `EVT:499-509` (`createPastEvent`),
`tests/Unit/Models/Events/EnrollmentTest.php:17-78`, `tests/TestCase.php`.

Passos:

1. Novo método em `EVT`: `test_<metodo>_should_<efeito>`.
2. Monte dados com `createEvent([...])` (já faz `assertTrue(save())`),
   `enroll($event, N)`; para passado, `createPastEvent()`.
3. Uma asserção principal por teste; para mensagens use
   `errorsOf($event)` ou `errors('campo')`.
4. `./run test tests/Unit/Models/Events/EventTest.php --filter <nome>`
   (o `run test` repassa argumentos ao PHPUnit).

Armadilhas:

- Teste que só chama `isValid()` não prova gravação (M3): use `save()`
  e `findById` quando o schema mudou.
- Datas: `+N days` relativo, nunca fixo.
- Cada teste recria o schema (`TestCase.php:10-14`): não há dados do
  populate nos testes.
- Nome do teste em inglês, `snake_case` descritivo (padrão do
  arquivo).

Prova: `./run test tests/Unit`.

### S4. Testes de borda pedidos pelo professor

Chance: média-alta. Tempo: 15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/s4-testes-de-borda`.

Pedido típico (issue 4.2 do grupo 5): "testes para quantidades
negativas, valores não numéricos, duplicidade, edição não autorizada,
atualização sem alteração".

Arquivos: `EVT:185-208` (`capacity` inteiro e >= 1), `EVT:86-126`
(campos obrigatórios), `ECT:114-138` (create inválido mantém valores),
`ECT:209-224` (update inválido mantém registro), `ECT:101-112` (ignora
`user_id` do form), `EVT:580-632` (capacidade x inscrições).

Passos:

1. Negativo e não numérico: `createEvent` não serve (assert dentro);
   use `new Event($this->params(['capacity' => -1]))` + `assertFalse(isValid())`
   + `assertSame('deve ser maior ou igual a 1!', $event->errors('capacity'))`.
   Pelo curto-circuito `notEmpty && integerMin` (`EV:79`), `'abc'`,
   `'1.5'`, `'1e1'` e `'010'` dão "deve ser um número inteiro!" e `''`
   ou `null` dão "não pode ser vazio!".
2. Duplicidade (M6, P2): dois `save()` iguais, o segundo falso. Só vale
   com M6 aplicado; na base o duplicado é aceito (teste documenta isso).
3. Não autorizado (Q1): segundo admin, `update`/`destroy` sem efeito. Só
   vale com Q1; na base todo admin edita tudo, então teste o
   Participante (`ACC:139-160`) e o `user_id` forjado (`ECT:101-112`).
4. Update sem alteração: `update` com os mesmos dados -> `Location`
   do `show` e registro igual (`assertSame` dos campos).
5. Parâmetro em formato errado: `params: ['event' => 'texto']`
   (string em vez de array) -> form renderizado, sem 500 (`EC:118-121`
   já trata).

Armadilhas:

- `integerMin` aceita `'10'`, `10`, `' 10 '` (espaços nas pontas) e
  `'+5'`; rejeita `'1.5'`, `'1e1'` e `'010'` (zero à esquerda). É o
  comportamento de `filter_var` com `FILTER_VALIDATE_INT` (`VAL:81`).
- `notEmpty` não faz `trim` (`VAL:9-17`): título só com espaços passa.
  Se pedirem, normalize no model (`trim`) antes de validar, não no
  controller.
- `capacity` acima de 2147483647 (`INT`) passa no `integerMin` e
  estoura no `INSERT` (`PDOException`, 500): `integerMax` (M2) resolve.
- Mensagens exatas com `!`.
- Teste de borda que depende da ordem dos erros: `errors()` guarda uma
  mensagem por campo (`MODEL:131-134`), a última vence.
- O `createEvent` do `ECT` (`ECT:289-300`) ignora o retorno de `save()`
  (`ECT:297`). Se a modificação deixar `validParams()` (`ECT:272-284`)
  inválido, o Evento não é gravado, o `id` fica `null` e o teste falha
  longe da causa. Ponha `$this->assertTrue($event->save())`, como o
  helper do `EVT` (`EVT:59-62`).
- Id inexistente só tem teste no `show` (`ECT:163-169`; por HTTP,
  `ACC:175-183`). `edit`, `update` e `destroy` (`EC:67-71`, `EC:79-83`,
  `EC:98-102`) não têm; remover o mesmo Evento duas vezes (a segunda dá
  "Evento não encontrado!") também não.
- `test_update_with_valid_data_changes_event` (`ECT:191-207`) só confere
  `title`, `capacity` e `user_id`: campo esquecido no `fill`
  (`EC:116-130`) passa despercebido. Confira os demais campos.

Prova: `./run test tests/Unit` e `tests/Integration`.

### S5. Testes que faltam: `PaginatorTest` e Cests do `show`

Chance: média-baixa. Tempo: 15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/s5-paginator-test-cests`.

Pedido típico: "o `Paginator` é item da rubrica e não tem teste próprio;
escreva" ou "não há Cest do detalhe nem do 'não encontrado'".

Arquivos: `PAG:18-29` (construtor exige `class`, `page`, `per_page`,
`table`, `attributes`), `PAG:56-86` (`previousPage`, `hasNextPage`,
`entriesInfo`), `PAG:93-102` (`renderPagesNavigation` chama `route()`
com `getRouteName()`), `EVT:390-441` (testes de `paginate` via model, o
que já existe), `tests/TestCase.php` (recria o schema),
`ACT/ListEventsCest.php:46-61` (já abre o `show` por link),
`ACT/BaseEventsCest.php`, `core/Router/Router.php:59-72` (`route()`
lança exceção se o nome não existir).

Passos:

1. `tests/Unit/Lib/PaginatorTest.php` (`extends Tests\TestCase`, precisa
   de banco): crie 7 Eventos (helper como `EVT:59-65`) e

   ```php
   $paginator = new Paginator(
       Event::class, 2, 5, 'events', Event::columns(),
       [], 'admin.events.paginate'
   );
   ```

   `totalOfRegisters() === 7`, `totalOfPages() === 2`, `registers()`
   com 2, `entriesInfo() === 'Mostrando 6 - 7 de 7'`, `hasPreviousPage()`
   verdadeiro, `hasNextPage()` falso; página 3: `registers()` vazio e
   `entriesInfo()` incoerente ("Mostrando 11 - 10 de 7", defeito que N10
   trata); `conditions: ['kind' => 'lecture']` filtra; banco vazio:
   `totalOfPages() === 0`.
2. `tests/Acceptance/admin/events/ShowEventCest.php` (`extends
   BaseEventsCest`): `amOnPage("/admin/events/{$this->events[1]->id}")`,
   `see(título, 'h1')`, `see(descrição)`, `seeLink('Editar')`,
   `seeLink('Voltar')`; e `amOnPage('/admin/events/999999')` ->
   `seeCurrentUrlEquals('/admin/events')` + `see('Evento não encontrado!')`.
3. `./run test tests/Unit/Lib/PaginatorTest.php` e `./run test:browser ...`.

Armadilhas:

- `renderPagesNavigation()` chama `route($this->getRouteName(), ...)`:
  sem rota com esse nome (`events.paginate` por padrão, `PAG:101`) o
  `Router` lança exceção (`Router.php:71`). Passe `route:` ou não renderize
  no teste unitário.
- O `Paginator` fala com o banco no construtor (`PAG:27-28`): o teste
  precisa de `Tests\TestCase` (migra o schema), não de PHPUnit puro.
- `lib/` está fora do `phpstan`, mas `tests/` não: anote arrays.
- Cest do `show`: a base loga o admin (`BaseEventsCest:46`); o Evento 1
  tem 3 inscritos (`3/3`), bom para conferir vagas.
- Não duplique `EVT:390-441`: o `PaginatorTest` testa a classe
  diretamente (construtor, páginas vazias, condições), não o `paginate`
  do model.

Prova: os testes passam; quebre `entriesInfo()` e veja falhar.

### T1. Populate: campo novo, mais Eventos, segundo admin

Chance: alta (sempre junto de M1, M3, P1, P5). Tempo: 5-10 min.

Exemplo pronto: `fvp/4.2/exemplos/t1-populate-campo-eventos-admin`.

Pedido típico: "atualize o populate", "crie 30 Eventos para ver a
paginação", "um segundo Administrador".

Arquivos: `POP:10` (`migrate()`), `POP:12-19` (admin), `POP:21-42`
(Participantes), `POP:49-130` (16 Eventos posicionais), `POP:133-149`
(laço do `new Event`, `:145-147` aborta se inválido), `POP:156-177`
(Inscrições por índice), `./run` (`db:populate`).

Passos:

1. Campo novo: a chave no `new Event` (`:134-143`), valor igual para
   todos (`'workload_hours' => 4`). Para variar, acrescente uma 9ª
   posição em **todas** as linhas de `:49-130` e no `foreach` (`:133`).
2. Mais Eventos: `for ($i = 17; $i <= 30; $i++)` gerando
   `sprintf('Evento %02d', $i)` com `+ (80 + $i) days`.
3. Segundo admin: cópia de `:12-19` com `admin2@example.com`; para Q1,
   alguns Eventos com `'user_id' => $admin2->id`.
4. `./run db:populate` e confira as mensagens (`:29,44,151,179`).

Armadilhas:

- `db:populate` apaga tudo (`migrate()` faz `DROP`): não há "adicionar
  só os novos".
- Evento inválido aborta com `RuntimeException` (`:145-147`): leia a
  mensagem, geralmente campo novo faltando.
- As Inscrições usam índices do array de Eventos (`:156-163`): não
  reordene as linhas.
- Data: `+14` em diante (`:47-48`); nunca passado.
- Não cite a senha em commit público além do que já está (`123456` é
  dado de demonstração).
- O populate não tem caso de borda para mostrar: nenhum Evento passado
  (`:47-48`) e nenhum com 1 vaga restante (o único cheio é o Evento 1,
  `:53` e `:157`). Para Q2 ou N5, salve com data futura e depois
  `update(['starts_on' => ...])`, que não valida (`MODEL:189-213`);
  para R13, um Evento com `capacity` igual a inscritos + 1.

Prova: `./run db:populate` sem erro; listagem com o número esperado.

### T2. `phpcs`, `phpstan` e CI verdes

Chance: alta (a CI é item da rubrica da PR). Tempo: 5 min.

Exemplo pronto: `fvp/4.2/exemplos/t2-phpcs-phpstan-verdes`.

Pedido típico: implícito em todo pedido; o professor abre o CI.

Arquivos: `phpcs.xml` (PSR-12, `lineLimit` 130), `phpstan.neon`
(nível 6, `paths`: `app`, `core`, `tests`; `lib/` fora),
`.github/workflows/ci.yml` (Unit, Integration, Acceptance), `./run`
(`phpcs`, `phpcbf`, `phpstan`).

Checklist:

1. `./run phpcbf` e depois `./run phpcs` (zero erros).
2. `./run phpstan`: `array` sem PHPDoc em `app/`/`core/` ->
   `@param array<string, mixed> $x` / `@return array<static>`;
   `?Model` onde se espera `?Event` -> `instanceof`; propriedade
   mágica desconhecida -> `@property` no model.
3. `./run test tests/Unit` e `tests/Integration`; `test:browser` só
   se mexeu em view ou fluxo.
4. Commit em inglês `type: Title`; branch com o nome pedido.

Armadilhas:

- `lib/` não passa pelo `phpstan`: erro de tipo lá só aparece em
  teste.
- `// @phpstan-ignore-line` genérico ou `ignoreErrors` amplo no
  `phpstan.neon`: penalizado (issue do grupo 6).
- Linha > 130 colunas: `phpcs` falha; quebre.
- Código comentado e `use` sem uso: `phpcs` não pega tudo; revise.

### T3. README e wiki da feature nova

Chance: média-baixa. Tempo: 10 min.

Exemplo pronto: `fvp/4.2/exemplos/t3-wiki-readme-feature`.

Pedido típico: "documente a rota nova no README/wiki do projeto" (a
rubrica cobra wiki com descrição da entrega e link da PR; o professor
costuma pedir que a mudança apareça na documentação).

Arquivos: `README.md` do projeto (seções de funcionalidades/rotas e de
como testar), `wiki/` do repositório (cópia das páginas: `Home.md`,
`Descrição-do-Projeto.md`, `_Sidebar.md`), a página da entrega 4.2 na
wiki oficial (`<repo>.wiki`), `.github/pull_request_template.md`.

Passos:

1. README: linha na lista de rotas (`GET /admin/events/full`,
   `admin.events.full`, quem acessa) e comando de teste
   (`./run test tests/Integration/...`).
2. Wiki da entrega: seção "Alterações da FVP" com o pedido, a solução
   em 3 linhas e o link do commit/PR.
3. Commit `docs: Document the full events page` separado do `feat:`.

Armadilhas:

- Documentação em pt-BR; identificadores e rotas como estão no código.
- Não invente endpoints que não existem; copie os nomes de
  `config/routes.php`.
- Wiki é outro repositório (`.wiki.git`): o commit no projeto não a
  atualiza; a cópia em `wiki/` dentro do repo é só espelho.
- Não cite credenciais além das do populate (`123456`, já públicas).

Prova: `README.md` renderizado no GitHub com a rota; página da wiki
atualizada.

### T4. Itens por página e capacidade máxima no `.env`

Chance: média-baixa. Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/t4-env-per-page`.

Pedido típico: "o número de itens por página não pode ficar fixo no
código" ou "a capacidade máxima vem da configuração".

Arquivos: `core/Env/EnvLoader.php:9-16` (lê `.env` com
`parse_ini_file` e grava em `$_ENV`, preferindo `getenv()` quando a
variável já existe no processo), `config/bootstrap.php:12`,
`.env.example` (o CI copia para `.env`:
`.github/workflows/ci.yml:19,54,81`), `EC:12,20` (`PER_PAGE`),
`phpunit.xml:6-13` (`<env>` dos testes PHPUnit),
`docker-compose.yml:65-71` (ambiente do `php_test`), `PAG:114` (divide
por `per_page`), `ECT:39-57`.

Passos:

1. Testes `ECT`: sem a chave, `index` lista 5; com
   `$_ENV['EVENTS_PER_PAGE'] = '10'` (e `unset` no `tearDown`), lista
   10; com `'0'`, `'abc'` ou `'500'`, volta a 5. `EVT`: com
   `MAX_CAPACITY = '100'`, capacidade 101 é inválida.
2. `.env.example` (e o `.env` local): `EVENTS_PER_PAGE=5` e
   `MAX_CAPACITY=1000`.
3. Leitura num só lugar, `lib/Config.php`:

   ```php
   public static function int(
       string $key,
       int $default,
       int $min,
       int $max
   ): int {
       $value = $_ENV[$key] ?? null;
       if (!is_string($value) || !ctype_digit($value)) {
           return $default;
       }
       $number = (int) $value;

       return ($number >= $min && $number <= $max) ? $number : $default;
   }
   ```

4. `EC::index`: `per_page: Config::int('EVENTS_PER_PAGE', 5, 1, 100)`.
   `EV::validates()`: `integerMax` (M2) com
   `Config::int('MAX_CAPACITY', 1000, 1, 100000)`.

Armadilhas:

- Valor do `.env` é string (`""` para chave vazia); `"0"` faz
  `PAG:114` dividir por zero. Valide e use o padrão.
- Os testes PHPUnit não rodam o `EnvLoader`, então não leem o `.env`:
  `$_ENV` tem o `phpunit.xml` e o ambiente do `php_test`, e sem a chave
  vale o padrão. O teste HTTP passa pelo
  `bootstrap` no `php_test` e lê o `.env` do projeto (no CI, a cópia do
  `.env.example`).
- `getenv()` não enxerga o que veio do `.env` (`EnvLoader` não faz
  `putenv`): leia `$_ENV`.
- Nunca commite o `.env`, só o `.env.example`; quem já tem `.env`
  precisa acrescentar a chave.
- O `(int)` vem depois do `ctype_digit`: não é conversão silenciosa.
- `lib/` fica fora do phpstan, mas tipe tudo mesmo assim.

Prova: testes; trocar `EVENTS_PER_PAGE=3` no `.env` e ver 3 por página
no navegador, sem reiniciar o contêiner (o `.env` é lido a cada
requisição).

## 9. Armadilhas gerais do CRUD de Eventos

- `Model::save()` grava **todas** as colunas de `$columns`
  (`MODEL:139-187`): coluna sem valor vai como `NULL`, o `DEFAULT` do
  schema não é usado. Padrões ficam no construtor (`US:28-35`).
- `Model::update([...])` não valida (`MODEL:189`); `save()` valida.
- `Model::where` só faz igualdade (`MODEL:309-311`); `all()` e o
  `Paginator` não ordenam; `paginate` não repassa condições
  (`MODEL:284-294`); `HasMany::paginate` repassa (`HASMANY:44-55`).
- `Model::$attributes` é privado (`MODEL:23`): no `Event` acesse as
  colunas por `$this->$column`.
- `route('x', ['page' => 2, 'q' => 'a'])` põe o que não está na URI
  como query string (`core/Router/Router.php:59-72`).
- O partial de paginação só enxerga `$paginator` e `$this`
  (`PAG:93-97`). Variável fora do `compact` na view: warning -> 500
  (`core/Errors/ErrorsHandler.php`).
- `PUT`/`DELETE` vêm do `_method` lido de `$_REQUEST`
  (`core/Http/Request.php:18`): até `GET ...?_method=DELETE` vira
  `DELETE` (O9).
- Parâmetro de rota só com `{[a-z,_]+}` (`core/Router/Route.php:90`):
  `{eventId}` ou `{event2}` viram texto literal e a rota dá 404 sem
  erro. Use `{event_id}` em rotas aninhadas (P13, O7, O10).
- `Database::getDatabaseConn()` abre um PDO novo a cada chamada
  (`core/Database/Database.php:10-22`): transação só vale para os
  comandos feitos no mesmo `$pdo` (P17, P19).
- Rota fixa (`new`, `full`, `export`) sempre antes de `{id}`
  (`ROUTES:29-31`); o teste de controller não pega a ordem errada, o
  teste HTTP pega.
- `render` sem `$title`: 500 (`LAYOUT:26`). `render` renderiza;
  `redirectTo` redireciona; depois de `redirectTo` ponha `return`.
- `db:populate` refaz o schema inteiro e apaga os dados (`POP:10`);
  teste passando não prova que o banco de desenvolvimento está
  atualizado.
- Os testes conferem strings e o `Location` exato (`ECT:97,167,205,249,261`).
- Create e update redirecionam para `/admin/events/{id}`; destroy e
  "não encontrado" para `/admin/events`.
- Logout ainda é `GET /logout` (`ROUTES:15`, `NAV:26`): o cenário A2
  da 4.1 (DELETE) continua em aberto e pode ser pedido junto.
- `phpcs`: 130 colunas, PSR-12; `phpstan` nível 6 em `app`, `core`,
  `tests`. O `phpcs.xml` só varre `.php`: as views `.phtml` ficam de
  fora (algumas da base já passam de 130); quebre à mão.
- Parâmetro de query ou de form pode vir array: `is_string`/`is_array`
  antes de usar; nunca `(int)` silencioso em valor que o model vai
  validar.
- SQL só no model; regra de negócio no model; controller com *early
  return*; nomes `index/new/create/show/edit/update/destroy`.
- CSS e JS em `public/assets/`, nunca na view.

## 10. Fontes

- Rubrica da 4.2 (`4.2 - CRUD`, itens avaliados 2026-2) e
  `docs/grading-path.md` §4.2.
- Issues "Avaliação da Entrega 4.2" de 03/11 (grupos 3, 5 e 6) e
  "Avaliação da Entrega 4.1" dos nove grupos.
- Pedido real da FVP da 4.1 (29/10): login por código aleatório
  1000-9999 gerado e validado no backend.
- Preparo interno da FVP de 29/10 (M1-M12 originais, convertidos para
  M1-M3, N1-N6, O1, Q1, R1, S1 deste catálogo).
- Código do repositório público em `f31006b` (`main`), arquivos
  listados na tabela de siglas.
- Levantamento dos repositórios dos outros grupos (05/11): nenhum
  vestígio da FVP nos repositórios; o guia de preparo do grupo 9
  esperava campo novo, perfil novo, rota nova, página com controller e
  view, validação ou middleware, seeds e testes.
