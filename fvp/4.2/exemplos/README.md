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
Cenários empilhados trazem a base junto no bundle; o README de cada um
diz se pega só o topo ou o intervalo.

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
- M2. Validação nova em `lib/Validations.php` (`maxLength`)
  `m2-validacao-maxlength/`
  - feat: Add maxLength validation and limit event title to 100
- M5. Validação condicional por modalidade
  `m5-validacao-condicional-modalidade/`
  - feat: Require location only for in-person events and link for
- M6. Unicidade (título único na mesma data)
  `m6-unicidade-titulo-data/`
  - feat: Require unique event title on the same date
- N1. Filtro por tipo com `where`, mantido na paginação
  `n1-filtro-por-tipo-paginacao/`
  - feat: Filter events by kind keeping the filter in pagination
- N3. Ordenar a listagem por data no Paginator
  `n3-ordenar-por-data/`
  - feat: Order events listing by date in the Paginator
- N4. Mudar `per_page` ou deixar o usuário escolher
  `n4-per-page/`
  - feat: Show 10 events per page and allow choosing per_page
- N6. Dado calculado na tela (badge "Lotado", total no painel)
  `n6-badge-lotado-contador/`
  - feat: Show full badge and admin dashboard totals
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
- P1. Código de check-in aleatório (1000–9999) por Evento
  `p1-codigo-checkin-aleatorio/`
  - feat: Generate a random check-in code for each Event
- P10. Comentários do Evento (recurso filho 1xN)
  `p10-comentarios-evento/`
  - feat: Add comments to events
- P2. Inscrição do Participante (`POST /events/{id}/enrollments`)
  `p2-inscricao-participante/`
  - feat: Add events listing and details for participants
  - feat: Allow participants to enroll in events
- P3. Cancelar inscrição (`DELETE /events/{id}/enrollments`)
  `p3-cancelar-inscricao/`
  - feat: Add events listing and details for participants
  - feat: Allow participants to enroll in events
  - feat: Allow participants to cancel their enrollment
- P5. Status do Evento: cancelar e encerrar
  `p5-status-cancelar-encerrar/`
  - feat: Add Event status with cancel and finish actions
- P7. "Meus eventos" do Participante
  `p7-meus-eventos/`
  - feat: Add events listing and details for participants
  - feat: Allow participants to enroll in events
  - feat: Add my events page for participants
- P8. Exportar a listagem em CSV
  `p8-exportar-csv/`
  - feat: Export events listing as CSV
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
- S1. Teste de aceitação da modificação
  `s1-teste-aceitacao-filtro/`
  - feat: Filter events by kind keeping the filter in
  - test: Add acceptance test for the events kind filter
- S2. Teste de acesso por HTTP da rota nova
  `s2-teste-acesso-rota-nova/`
  - feat: List full events in admin area
  - test: Cover full events route access over HTTP
- S4. Testes de borda pedidos pelo professor
  `s4-testes-de-borda/`
  - test: Add edge case tests for events
