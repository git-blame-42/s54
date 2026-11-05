# P22. Adiar o Evento uma semana com um botão

Pedido, como o professor faria na hora: "Ponha um botão 'Adiar uma
semana' no detalhe do Evento. Ele soma 7 dias à data, sem passar pelo
formulário de edição, e avisa a data nova."

Chance: média-baixa. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P22.

Branch no clone: `fvp/p22-adiar-uma-semana`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                            |
|-----------|-----------------------------------------------------|
| `5089dbe` | `feat: Add button to postpone an event by one week` |

## O que mudou

- `app/Models/Event.php`:
  - `use DateTimeImmutable;` e a constante `POSTPONE_DAYS = 7`;
  - `postpone(int $days = self::POSTPONE_DAYS): bool`. Recusa Evento
    novo (erro em `base`, "o evento não existe!") e `$days < 1`
    ("adie pelo menos 1 dia!"). Soma os dias a partir de `starts_on`
    com `createFromFormat('!Y-m-d', ...)->modify("+N days")` e grava
    com `save()`. Se não gravar, devolve a data original ao objeto e
    retorna `false` com o motivo em `errors()`.
- `config/routes.php`: `Route::put('/admin/events/{id}/postpone', ...)`
  com o nome `admin.events.postpone`, logo depois do `update`.
- `app/Controllers/Admin/EventsController.php`: ação `postpone`.
  `findEvent`/`eventNotFound` como nas outras; flash `success`
  "Evento adiado para dd/mm/aaaa!" ou `danger` "Não foi possível
  adiar: <motivo>"; redireciona para o show.
- `app/views/admin/events/show.phtml`: form `POST` com
  `_method=PUT` e o botão "Adiar uma semana" ao lado do Editar.
- Testes:
  - `EventTest` (7): +30 dias vira +37; outro prazo (`postpone(3)`
    em 30/12/2099 dá 02/01/2100); capacidade e Inscrições mantidas;
    Evento em -10 dias continua no passado (erro em `starts_on`,
    objeto e banco com a data antiga); Evento em -3 dias vira +4
    (aceito); `postpone(0)` e `postpone(-7)` recusados; Evento novo
    não é inserido.
  - `EventsControllerTest` (4): adiar redireciona ao show com a flash
    da data nova; id 9999 vai para a listagem com "Evento não
    encontrado!"; Evento passado fica com a data e recebe `danger`;
    o show tem o form `PUT` do botão.
  - `EventsAccessTest`: a rota em `eventRoutes()` (sem sessão vai
    para `/login`; Participante vai para `/dashboard`) e em
    `methodOverrideRoutes()`; o comentário passa de 8 para 9 rotas; o
    Participante tenta adiar e a data não muda.
  - `PostponeEventCest` (novo, 2 cenários): Evento 16 adiado pelo
    botão (flash, data nova no detalhe, banco); Evento 01 lotado
    continua com 3/3 depois de adiar.

## Por que assim

- A regra mora no model e o controller fica fino: acha o Evento,
  chama `postpone()`, escolhe a flash e redireciona, como o `destroy`
  faz com `safeDestroy()`.
- `save()` e não `update(['starts_on' => ...])`: `update()` não
  valida e gravaria data no passado. Com `save()`, a data nova passa
  pela regra "não pode estar no passado" e pela de capacidade.
- A guarda de `newRecord()`: num Evento novo, `save()` faz `INSERT`,
  e adiar criaria um Evento. É a mesma guarda do `safeDestroy()`.
- Soma a partir de `starts_on`, não de hoje: `strtotime('+7 days')`
  daria "hoje + 7".
- `DateTimeImmutable::createFromFormat('!Y-m-d')`: não inventa data
  para valor vazio ou mal formado (`strtotime('')` vira 1970), e o
  `!` zera a hora.
- Devolver a data original quando o `save()` falha: senão quem usa o
  objeto depois vê uma data que não foi gravada.
- `PUT` por form com `_method`, nunca por link: `GET` não altera
  dados. Um `GET /admin/events/16/postpone` dá 404 (não há rota).
- `$days` com padrão 7 já deixa pronta a variante "adiar N dias".

## Como verificar

```
./run up -d
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium
./run test:browser
# confere o PSR-4 (há um Cest novo)
./run composer dump-autoload -o --strict-psr
```

Resultado obtido: Unit 166 testes, Integration 52, Acceptance 25 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros; PSR-4 estrito ok. Schema e
populate não mudaram.

No navegador (admin@example.com / 123456): abra o Evento 16, clique em
"Adiar uma semana". A flash mostra "Evento adiado para dd/mm/aaaa!" e
a data do detalhe passa 7 dias para a frente. Na VM deste exemplo:
26/01/2027 virou 02/02/2027.

## Relação com outros cenários

- P5 (`p5-status-cancelar-encerrar`): outra ação curta no show com
  form `PUT` e flash. Se aplicar os dois, Evento cancelado não deve
  adiar: ponha `if ($this->isCanceled())` com erro em `base` no
  início de `postpone()`. Os dois botões ficam lado a lado no show.
- O7 (`o7-route-patch`): com `Route::patch`, a rota vira
  `Route::patch('/admin/events/{id}/postpone', ...)` e o form manda
  `_method=PATCH`; troque também o verbo no `EventsAccessTest`.
- P6 (duplicar Evento): lá a data parte de hoje (`strtotime`); aqui
  parte da data do próprio Evento.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p22-adiar-uma-semana`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/p22-adiar-uma-semana`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p22 \
    s50/fvp/4.2/exemplos/p22-adiar-uma-semana/projeto
git fetch fvp-p22 fvp/p22-adiar-uma-semana
git cherry-pick 5089dbe
git remote remove fvp-p22
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p22-adiar-uma-semana/solucao.bundle \
    fvp/p22-adiar-uma-semana:fvp-p22
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 5089dbe
# opcional, limpa a ref
git branch -D fvp-p22
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/p22-adiar-uma-semana/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p22-adiar-uma-semana/solucao.patch
```

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(Cest novo), os testes e o `./run phpcs`.

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

- `config/routes.php`: P5, O6, P13 e outros acrescentam rotas no mesmo
  bloco. Fique com todas; rota fixa sem `{id}` vem antes de
  `/admin/events/{id}`.
- `EventsController.php`: ações novas de outros cenários no mesmo
  ponto (antes do `destroy`); mantenha as duas.
- `show.phtml`: P5, R4 e R6 mexem nos botões do fim da página. Fique
  com todos os botões.
- `EventsAccessTest.php`: `eventRoutes()` e `methodOverrideRoutes()`
  ganham linhas de outros cenários; mantenha todas e corrija o número
  de rotas no comentário.
- `EventTest.php` e `EventsControllerTest.php`: testes novos no fim
  da classe; fique com os dois blocos.

## Armadilhas

- Sem a guarda de `newRecord()`, `postpone()` num `new Event(...)`
  válido faz `INSERT` e cria um Evento.
- `update(['starts_on' => ...])` grava sem validar: data no passado
  passa. Use `save()`.
- `strtotime('+7 days')` soma a partir de hoje, não da data do Evento.
- Evento passado: -10 dias + 7 = -3, recusado; -3 dias + 7 = +4,
  aceito (a regra de data só é cobrada quando a data muda, e aqui ela
  muda). Se o professor quiser "Evento passado não adia", teste
  `starts_on < date('Y-m-d')` no início de `postpone()` com erro em
  `base` e escreva um teste para o caso de -3 dias.
- `save()` roda `validates()` inteiro: um Evento com dado inválido
  gravado por `update()` (capacidade abaixo das Inscrições, por
  exemplo) não adia, e a flash mostra o erro de `starts_on` ou de
  `base` ou "dados inválidos!".
- Sem devolver a data original, o objeto fica com a data que não foi
  gravada.
- Link `<a href=".../postpone">` não funciona: a rota é `PUT`, e `GET`
  não deve alterar dados.
- O `EventsControllerTest` chama o método direto, sem o `Router`:
  rota ou verbo errado só aparece no `EventsAccessTest` e no Cest.
- Prazo digitado ("adiar N dias"): mande o valor cru ao model e valide
  lá com `filter_var($days, FILTER_VALIDATE_INT, ['options' =>
  ['min_range' => 1, 'max_range' => 365]])`. Nunca `(int)` no
  controller: texto vira 0.
