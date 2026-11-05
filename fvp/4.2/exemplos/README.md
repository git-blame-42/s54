# Exemplos de FVP 4.2: uma solução pronta por cenário

Cada subdiretório é um cenário do catálogo `fvp/4.2/catalogo.md` (CRUD de
Eventos), resolvido de verdade sobre o código da Entrega 4.2 (`main` do
repositório público, commit `f31006b`), com testes, PHPCS, PHPStan e PSR-4
verdes. Mesmo formato dos exemplos da 4.1 (`fvp/4.1/exemplos/`): leia o
README de lá para as receitas gerais de cherry-pick, patch e bundle.

## O que há em cada cenário

```
<id>-<slug>/
  README.md        pedido, o que mudou, por quê, como verificar,
                   como fazer cherry-pick, conflitos prováveis
  solucao.patch    git format-patch dos commits do cenário (git am)
  solucao.bundle   bundle completo: main (f31006b) + branch fvp/<id>-<slug>
  projeto/         árvore do público com a solução aplicada (sem .git,
                   sem vendor; `fvp/recriar.sh fvp/4.2/exemplos/<cenario>`
                   devolve o .git a partir do bundle)
```

O `main` dos bundles e dos clones `projeto/` é o `f31006b` reescrito
(aparece como `5dedab5`): mesmo código, autor neutro e sem dados pessoais
no `README.md` e na wiki. Por isso o `git log` do bundle não mostra
`f31006b`; cherry-pick e patch aplicam igual sobre o `f31006b` do público.

Pré-requisito para aplicar: o repositório alvo descende de `f31006b`.
Cenários empilhados (P2 sobre O2; P3, P7 e P9 sobre P2; P4 sobre P2 e P1;
Q2 sobre P5; S1 sobre N1; S2 sobre O1; S3 sobre P6) trazem a base junto
no bundle; o README de cada um diz se pega só o topo ou o intervalo.

## Como foram verificados

Cada cenário rodou, antes do commit: `./run phpcs`, `./run phpstan`,
`./run test tests/Unit`, `./run test tests/Integration` e
`./run test:browser` (suíte inteira, Selenium), mais `./run db:populate`
quando mexe em schema ou populate. Base: Unit 159, Integration 45,
Acceptance 23. A contagem de cada cenário está no README dele.

## Índice

- M1. Campo novo em `events` com validação (`workload_hours`)
  `m1-campo-novo-workload-hours/`
  - feat: Add workload hours to events
- M10. Campo que não muda depois de haver inscrições
  `m10-campo-imutavel-com-inscricoes/`
  - feat: Lock event start date and kind once it has enrollments
- M11. Validar `user_id` no model (existe e é admin)
  `m11-validar-user-id/`
  - feat: Validate that the event creator exists and is an admin
- M2. Validação nova em `lib/Validations.php` (`maxLength`)
  `m2-validacao-maxlength/`
  - feat: Add maxLength validation and limit event title to 100
- M3. Valor novo em ENUM (tipo Seminário)
  `m3-enum-novo-seminar/`
  - feat: Add seminar to event kinds
- M4. Segunda data (`ends_on`) não anterior a `starts_on`
  `m4-segunda-data-ends-on/`
  - feat: Add end date to events
- M5. Validação condicional por modalidade
  `m5-validacao-condicional-modalidade/`
  - feat: Require location only for in-person events and link for
- M6. Unicidade (título único na mesma data)
  `m6-unicidade-titulo-data/`
  - feat: Require unique event title on the same date
- M9. Slug único gerado no backend
  `m9-slug-unico-gerado/`
  - feat: Generate a unique slug for each event
- N1. Filtro por tipo com `where`, mantido na paginação
  `n1-filtro-por-tipo-paginacao/`
  - feat: Filter events by kind keeping the filter in pagination
- N10. Página e id inválidos sem `(int)` silencioso
  `n10-pagina-id-invalido/`
  - fix: Validate page and id params without silent int casts
- N11. `Model::count()` no framework
  `n11-model-count/`
  - feat: Add Model::count() and use it for enrollments and
- N2. Busca por título (`LIKE`)
  `n2-busca-por-titulo/`
  - feat: Search events by title
- N3. Ordenar a listagem por data no Paginator
  `n3-ordenar-por-data/`
  - feat: Order events listing by date in the Paginator
- N4. Mudar `per_page` ou deixar o usuário escolher
  `n4-per-page/`
  - feat: Show 10 events per page and allow choosing per_page
- N5. Só Eventos futuros ou passados
  `n5-eventos-futuros-passados/`
  - feat: Add upcoming and past events tabs
- N6. Dado calculado na tela (badge "Lotado", total no painel)
  `n6-badge-lotado-contador/`
  - feat: Show full badge and admin dashboard totals
- N7. Painel administrativo com resumo
  `n7-painel-resumo/`
  - feat: Show event summary on the admin dashboard
- N8. Ordenar clicando no cabeçalho
  `n8-ordenar-pelo-cabecalho/`
  - feat: Sort events listing by clicking the table header
- N9. Filtro por período (de/até) validado, mantido na paginação
  `n9-filtro-por-periodo/`
  - feat: Filter events by period keeping it in pagination
- O1. Rota RESTful nova com página (lotados)
  `o1-rota-eventos-lotados/`
  - feat: List full events in admin area
- O2. Listagem e detalhe de Eventos para o Participante
  `o2-eventos-para-participante/`
  - feat: Add events listing and details for participants
- O3. Inscritos no `show` (`participants()`)
  `o3-inscritos-no-show/`
  - feat: Show event participants in admin area
- O4. Resposta JSON da listagem
  `o4-resposta-json/`
  - feat: Return JSON for admin events listing
- O5. Admin vê e lista só os próprios Eventos
  `o5-admin-lista-so-proprios/`
  - feat: List and manage only the logged admin events
- P1. Código de check-in aleatório (1000–9999) por Evento
  `p1-codigo-checkin-aleatorio/`
  - feat: Generate a random check-in code for each Event
- P10. Comentários do Evento (recurso filho 1xN)
  `p10-comentarios-evento/`
  - feat: Add comments to events
- P11. Soft delete (arquivar Evento)
  `p11-soft-delete-arquivar/`
  - feat: Support null conditions as IS NULL in Paginator
  - feat: Archive events instead of deleting them
- P12. Código aleatório no login (a FVP real da 4.1)
  `p12-codigo-aleatorio-login/`
  - feat: Require a random access code after the password on
- P16. Remover Evento só com código de confirmação gerado no backend
  `p16-remover-com-codigo-confirmacao/`
  - feat: Require a confirmation code to remove an Event
- P17. Remover Evento com inscrições em cascata, com confirmação
  `p17-remover-em-cascata/`
  - feat: Require a confirmation code to remove an Event
  - feat: Remove an Event with its enrollments when forced
- P2. Inscrição do Participante (`POST /events/{id}/enrollments`)
  `p2-inscricao-participante/`
  - feat: Add events listing and details for participants
  - feat: Allow participants to enroll in events
- P3. Cancelar inscrição (`DELETE /events/{id}/enrollments`)
  `p3-cancelar-inscricao/`
  - feat: Add events listing and details for participants
  - feat: Allow participants to enroll in events
  - feat: Allow participants to cancel their enrollment
- P4. Confirmar presença pelo código do Evento
  `p4-confirmar-presenca-codigo/`
  - feat: Add events listing and details for participants
  - feat: Allow participants to enroll in events
  - feat: Generate a random check-in code for each Event
  - feat: Confirm event presence with the check-in code
- P5. Status do Evento: cancelar e encerrar
  `p5-status-cancelar-encerrar/`
  - feat: Add Event status with cancel and finish actions
- P6. Duplicar Evento
  `p6-duplicar-evento/`
  - feat: Duplicate an Event from the listing
- P7. "Meus eventos" do Participante
  `p7-meus-eventos/`
  - feat: Add events listing and details for participants
  - feat: Allow participants to enroll in events
  - feat: Add my events page for participants
- P8. Exportar a listagem em CSV
  `p8-exportar-csv/`
  - feat: Export events listing as CSV
- P9. Lista de espera quando lotado
  `p9-lista-de-espera/`
  - feat: Add events listing and details for participants
  - feat: Allow participants to enroll in events
  - feat: Add waiting list for full events
- Q1. Só o dono edita e remove
  `q1-so-dono-edita-remove/`
  - feat: Restrict event edit and removal to its owner
- Q2. Bloquear edição e remoção de Evento passado ou cancelado
  `q2-bloquear-passado-cancelado/`
  - feat: Add Event status with cancel and finish actions
  - feat: Block editing and removing past or canceled Events
- R1. Flash: texto, título do Evento, tipo `warning`
  `r1-flash-titulo-warning/`
  - feat: Show event title in flash messages and add warning type
- R2. Nome do criador no show/listagem (BelongsTo)
  `r2-nome-do-criador/`
  - feat: Show event creator name in listing and show
- R3. Formatação na view com helper num só lugar
  `r3-helper-formatacao/`
  - refactor: Move event formatting to model helpers
- R4. Confirmação antes de remover, JS em arquivo próprio
  `r4-confirmacao-js-arquivo/`
  - feat: Confirm before removing an event
- R5. Mostrar `created_at`/`updated_at` no show e na listagem
  `r5-created-updated-at/`
  - feat: Show event created_at and updated_at
- R8. Lista de todos os erros no topo do form
  `r8-erros-no-topo-do-form/`
  - feat: List all validation errors on top of the event form
- S1. Teste de aceitação da modificação
  `s1-teste-aceitacao-filtro/`
  - feat: Filter events by kind keeping the filter in
  - test: Add acceptance test for the events kind filter
- S2. Teste de acesso por HTTP da rota nova
  `s2-teste-acesso-rota-nova/`
  - feat: List full events in admin area
  - test: Cover full events route access over HTTP
- S3. Teste unitário do método novo do model
  `s3-teste-unitario-model/`
  - feat: Duplicate an Event from the listing
  - test: Cover Event::duplicate() with unit tests
- S4. Testes de borda pedidos pelo professor
  `s4-testes-de-borda/`
  - test: Add edge case tests for events
- T1. Populate: campo novo, mais Eventos, segundo admin
  `t1-populate-campo-eventos-admin/`
  - chore: Add second admin and more events to the populate
- T2. `phpcs`, `phpstan` e CI verdes
  `t2-phpcs-phpstan-verdes/`
  - (sem código; só README)
