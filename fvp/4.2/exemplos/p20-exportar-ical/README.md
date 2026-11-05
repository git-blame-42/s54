# P20. Exportar Evento em iCal (.ics), botão "Adicionar ao calendário"

Pedido, como o professor faria na hora: "No detalhe do Evento, coloque
um botão 'Adicionar ao calendário' que baixa o Evento num arquivo .ics,
que o Google Agenda e o Outlook importam."

Chance: baixa. Tempo estimado: 20-25 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P20. Variante do P8 (exportar CSV).

Branch no clone: `fvp/p20-exportar-ical`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                       |
|-----------|------------------------------------------------|
| `5b8bd8c` | `feat: Export an Event as an iCalendar file`   |

## O que mudou

- `lib/ICalendar.php` (novo, estático):
  - `escape(string)`: `\` → `\\`, `;` → `\;`, `,` → `\,` e quebras
    de linha (`\r\n`, `\n`, `\r`) → `\n` literal;
  - `fold(string)`: linha com mais de 75 octetos continua na seguinte,
    que começa com um espaço; corta com `mb_strcut`, sem partir um
    caractere UTF-8 ao meio;
  - `build(array $lines)`: aplica `fold` e junta com CRLF (`\r\n`),
    inclusive depois da última linha.
- `app/Models/Event.php`: `toICalendar(): string` monta `VCALENDAR` +
  `VEVENT` de dia inteiro: `UID:event-{id}@eventos.utfpr`, `DTSTAMP`
  (UTC), `DTSTART;VALUE=DATE:` = `starts_on`, `DTEND;VALUE=DATE:` = dia
  seguinte, `SUMMARY`/`DESCRIPTION`/`LOCATION` com `escape`.
- `config/routes.php`: `GET /admin/events/{id}/ics` →
  `admin.events.ics`, junto do `edit`.
- `app/Controllers/Admin/EventsController.php`: ação `ics`:
  `findEvent` (não achou → `eventNotFound()`), `header('Content-Type:
  text/calendar; charset=utf-8')`, `header('Content-Disposition:
  attachment; filename="evento-{id}.ics"')` e `echo
  $event->toICalendar()`. Sem `render()` e sem `exit`.
- `show.phtml`: link "Adicionar ao calendário" (ícone
  `bi-calendar-plus`) para a rota nova.

## Por que assim

- Resposta sem layout, como o P8: `render()` sempre põe o layout HTML
  em volta; aqui a ação faz `header` + `echo`, igual ao `renderJson`.
- Sem `exit`: o teste de controller (`ControllerTestCase`, com
  `ob_start`) captura a saída e precisa que a ação retorne. Em produção
  o script termina sozinho. O `header()` no CLI do PHPUnit não faz
  nada, por isso os headers são conferidos no teste de acesso (HTTP
  real), e o corpo no de controller.
- Formato num lugar só (`Lib\ICalendar`), testável sem banco; o model
  só diz quais campos vão em quais linhas; o controller só responde.
- CRLF é obrigatório no iCalendar (RFC 5545); com `\n` puro alguns
  clientes recusam o arquivo. Pelo mesmo motivo, as linhas longas são
  dobradas em 75 octetos (a descrição do populate passa disso).
- Escape: o local do populate tem vírgula ("Bloco D, Auditório
  Central"); sem `\,` alguns clientes cortam o texto ali.
- Dia inteiro: o Evento só tem data (`starts_on`), sem hora. No
  formato, `DTEND` de dia inteiro é exclusivo, por isso é o dia
  seguinte.
- `UID` fixo por Evento: importar de novo atualiza em vez de duplicar.
- Rota `/{id}/ics` tem um segmento a mais que `/{id}`, então não
  disputa com o `show` (diferente do `/export` do P8, que precisa vir
  antes de `{id}`).

## Como verificar

```
./run phpcs
./run phpstan
./run composer dump-autoload -o --strict-psr
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 168, Integration 51, Acceptance 24 (base: 159 /
45 / 23). PHPCS e PHPStan sem erros; PSR-4 estrito ok. Schema não
mudou.

Testes novos:

- `tests/Unit/Lib/ICalendarTest.php` (7): escape de `\ ; ,`; quebras
  de linha → `\n`; texto simples intacto; linha curta sem dobra; linha
  longa dobrada em 75 octetos com espaço na continuação (e remontável);
  sem partir "ç"; `build` com CRLF também no fim.
- `EventTest`: `test_to_icalendar_should_describe_an_all_day_event`
  (UID, DTSTART/DTEND, escapes, DTSTAMP, fim), e
  `test_to_icalendar_should_use_only_crlf_line_endings` (nenhum `\n`
  sem `\r`; nenhuma linha acima de 75 octetos).
- `EventsControllerTest`: link no `show`; `ics` devolve o calendário
  sem `<html`; id inexistente redireciona.
- `EventsAccessTest`: `admin.events.ics` em `eventRoutes()` (sem
  sessão → `/login`, Participante → `/dashboard`);
  `test_admin_should_download_the_event_as_icalendar` (200,
  `Content-Type: text/calendar`, `Content-Disposition`, corpo).
- `ExportICalendarCest` (novo): o botão aparece no `show` e aponta
  para `/admin/events/{id}/ics`.

Na tela (dentro da rede do compose):

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -D - -b /tmp/c \
    http://localhost/admin/events/1/ics
```

Saída obtida (corpo, populate; cada linha termina em CRLF):

```
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//UTFPR TSI34D//Eventos//PT-BR
CALSCALE:GREGORIAN
METHOD:PUBLISH
BEGIN:VEVENT
UID:event-1@eventos.utfpr
DTSTAMP:20261105T201132Z
DTSTART;VALUE=DATE:20261119
DTEND;VALUE=DATE:20261120
SUMMARY:Semana Acadêmica de Informática 2026
DESCRIPTION:Semana com palestras\, minicursos e maratona de programação p
 romovida pelo curso de Sistemas para Internet.
LOCATION:Bloco D\, Auditório Central
END:VEVENT
END:VCALENDAR
```

## Relação com outros cenários

- P8 (`p8-exportar-csv`): mesma receita de resposta sem layout
  (`header` + saída, sem `render` nem `exit`) e de teste (corpo no
  controller, headers no acesso). Lá a listagem inteira em CSV; aqui
  um Evento em iCalendar.
- O4 (resposta JSON): outra resposta sem layout, via `renderJson`.
- S2 (teste de acesso da rota nova): rota em `eventRoutes()` + admin
  com 200.
- O2 (área do Participante): se pedirem o botão também para o
  Participante, crie `GET /events/{id}/ics` no grupo `auth` reusando
  `toICalendar()`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p20-exportar-ical`.

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público. `<caminho>` abaixo é `s50/fvp/4.2/exemplos/p20-exportar-ical`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p20 s50/fvp/4.2/exemplos/p20-exportar-ical/projeto
git fetch fvp-p20 fvp/p20-exportar-ical
git cherry-pick 5b8bd8c
git remote remove fvp-p20
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/fvp/4.2/exemplos/p20-exportar-ical/solucao.bundle \
    fvp/p20-exportar-ical:fvp-p20
git cherry-pick 5b8bd8c
# opcional, limpa a ref
git branch -D fvp-p20
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/p20-exportar-ical/solucao.patch
# só o diff, sem commit
git apply --3way s50/fvp/4.2/exemplos/p20-exportar-ical/solucao.patch
```

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(classe nova em `lib/`) e os testes.

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

- `config/routes.php`: outras rotas `/{id}/...` (P5, P6, P16) no mesmo
  ponto. Fique com todas.
- `show.phtml`: a linha de botões do fim também muda em P5, Q2, P16.
  Junte os botões.
- `EventsController`, `EventTest`, `EventsControllerTest`,
  `EventsAccessTest::eventRoutes`: métodos e testes novos no mesmo
  ponto; fique com os dois.

## Armadilhas

- `render()`: o .ics sai dentro do layout HTML.
- `exit` no fim da ação: mata o PHPUnit no teste de controller.
- `implode("\n", ...)`: o arquivo abre em alguns clientes e falha em
  outros. Use CRLF, também depois da última linha.
- Dobrar com `substr` em 75 caracteres: o limite é em octetos, e
  `substr` pode partir "ã" ao meio (UTF-8 inválido). `mb_strcut` corta
  em octetos sem partir caractere.
- Esquecer o espaço no início das linhas de continuação: o cliente lê
  a sobra como uma propriedade nova e inválida.
- Não escapar vírgula e ponto e vírgula, ou escapar `:` (não precisa).
- `DTEND` igual a `DTSTART` num evento de dia inteiro: alguns clientes
  mostram duração zero. O fim é exclusivo: dia seguinte.
- `DTSTAMP` com `date()` em vez de `gmdate()` e o `Z` no fim: o `Z`
  diz UTC; com a hora local o carimbo fica errado.
- Qualquer `echo` antes do `header()` dá "headers already sent" no
  navegador (no teste CLI não aparece).
