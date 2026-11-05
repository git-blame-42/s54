# Passo a passo (sbs) dos cenários FVP 4.2

Um guia por cenário de chance **alta** ou **média-alta** do
`catalogo.md` (mais Q3, Q4 e Q5, pedidos à parte), no formato "um arquivo
por vez": para cada arquivo tocado,
onde mexer, o que digitar e o motivo (o que aquilo ensina sobre o
framework). Mesma solução dos `exemplos/<id>-*/` (patch, bundle,
`projeto/`), só que em ordem de execução e com o porquê.

`<id>-sbs.md` é a fonte; `<id>-sbs.html` é gerado com `pandoc`
(`fvp/4.2/sbs/gerar-html.sh`).

Ordem sugerida de leitura: `m1-sbs.md` primeiro (é o mais básico e o
molde dos outros).

## Chance alta

| ID | Guia | Cenário |
|---|---|---|
| M1 | `m1-sbs.md` | Campo novo em `events` com validação |
| M2 | `m2-sbs.md` | Validação nova em `lib/Validations.php` |
| N1 | `n1-sbs.md` | Filtro por tipo com `where` na paginação |
| P1 | `p1-sbs.md` | Código de check-in aleatório (1000-9999) |
| P5 | `p5-sbs.md` | Status do Evento: cancelar e encerrar |
| P23 | `p23-sbs.md` | Código do registro exigido para remover |
| S1 | `s1-sbs.md` | Teste de aceitação da modificação |
| S2 | `s2-sbs.md` | Teste de acesso por HTTP da rota nova |
| S3 | `s3-sbs.md` | Teste unitário do método novo do model |
| T1 | `t1-sbs.md` | Populate: campo novo, mais Eventos, segundo admin |
| T2 | `t2-sbs.md` | `phpcs`, `phpstan` e CI verdes |

## Chance média-alta

| ID | Guia | Cenário |
|---|---|---|
| M4 | `m4-sbs.md` | Data de término não anterior ao início |
| M6 | `m6-sbs.md` | Unicidade (título único na mesma data) |
| M7 | `m7-sbs.md` | Horário do Evento (`TIME` ou `DATETIME`) |
| M17 | `m17-sbs.md` | Campo sim/não por checkbox (certificado) |
| M18 | `m18-sbs.md` | Janela de datas: antecedência e limite |
| N3 | `n3-sbs.md` | Ordenar por data no `Paginator` |
| N4 | `n4-sbs.md` | Mudar `per_page` ou deixar o usuário escolher |
| N10 | `n10-sbs.md` | Página e id inválidos, sem `(int)` cego |
| N16 | `n16-sbs.md` | Busca, filtro e ordenação juntos na paginação |
| O1 | `o1-sbs.md` | Rota RESTful nova com página (lotados) |
| O2 | `o2-sbs.md` | Eventos para o Participante (lista e detalhe) |
| O5 | `o5-sbs.md` | Admin vê e lista só os próprios Eventos |
| P2 | `p2-sbs.md` | Inscrição do Participante em um Evento |
| P6 | `p6-sbs.md` | Duplicar Evento |
| P7 | `p7-sbs.md` | "Meus eventos" do Participante |
| P13 | `p13-sbs.md` | Admin inscreve e remove um Participante |
| P16 | `p16-sbs.md` | Remover só com código de confirmação |
| Q1 | `q1-sbs.md` | Só o dono edita e remove |
| R2 | `r2-sbs.md` | Nome do criador no `show` e na listagem |
| S4 | `s4-sbs.md` | Testes de borda pedidos pelo professor |

## Pedidos à parte (autorização)

| ID | Guia | Cenário | Chance |
|---|---|---|---|
| Q3 | `q3-sbs.md` | Ação só para Participante | média-baixa |
| Q4 | `q4-sbs.md` | Perfil Organizador com CRUD dos próprios | média |
| Q5 | `q5-sbs.md` | Token CSRF nos formulários de Evento | média-baixa |
