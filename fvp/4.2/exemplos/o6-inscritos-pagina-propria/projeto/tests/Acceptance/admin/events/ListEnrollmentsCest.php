<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Enrollment;
use Tests\Support\AcceptanceTester;

/**
 * Inscritos do Evento em página própria (5 por página). Além dos dados da base, o Evento 02 (sem
 * Inscrições na base) recebe 7 Inscrições: os 3 Participantes da base e mais 4 criados aqui.
 */
class ListEnrollmentsCest extends BaseEventsCest
{
    private const ENROLLED = 7;

    public function _before(AcceptanceTester $page): void
    {
        // O Codeception reaproveita a instância do Cest entre cenários: sem limpar, os Participantes 4 a 7
        // do cenário anterior (já apagados do banco) seriam inscritos pelo seedEvents da base.
        $this->participants = [];
        parent::_before($page);

        for ($i = 4; $i <= self::ENROLLED; $i++) {
            $this->participants[$i] = $this->createUser("Participante {$i}", "participante{$i}@example.com", 'participant');
        }

        foreach ($this->participants as $participant) {
            (new Enrollment(['event_id' => $this->events[2]->id, 'user_id' => $participant->id]))->save();
        }
    }

    public function openFromShowAndPaginate(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Ver os inscritos de um Evento a partir do detalhe e paginar');
        $page->amOnPage("/admin/events/{$event->id}");
        $page->click('Inscritos (7)');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}/enrollments");
        $page->see('Inscritos: Evento 02', 'h1');
        $page->see('7 inscrito(s) de 22 vagas.');
        $page->see('Inscrito em', 'table thead');
        $page->seeNumberOfElements('table tbody tr', 5);
        $page->see('participante1@example.com', 'table');
        $page->see('Participante 5', 'table');
        $page->dontSee('Participante 6', 'table');
        $page->see(date('d/m/Y'), 'table');
        $page->see('Mostrando 1 - 5 de 7');

        $page->click('Próxima', '.pagination');
        $page->seeCurrentUrlEquals("/admin/events/{$event->id}/enrollments/page/2");
        $page->seeNumberOfElements('table tbody tr', 2);
        $page->see('Participante 6', 'table');
        $page->see('participante7@example.com', 'table');
        $page->dontSee('Participante 1', 'table');
        $page->see('Mostrando 6 - 7 de 7');

        $page->click('1', '.pagination');
        $page->seeCurrentUrlEquals("/admin/events/{$event->id}/enrollments/page/1");
        $page->see('Participante 1', 'table');

        $page->click('Voltar ao evento');
        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
    }

    public function eventWithoutEnrollments(AcceptanceTester $page): void
    {
        $event = $this->events[4];

        $page->wantTo('Ver a página de inscritos de um Evento sem Inscrições');
        $page->amOnPage("/admin/events/{$event->id}");
        $page->click('Inscritos (0)');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}/enrollments");
        $page->see('Nenhum inscrito neste evento.');
        $page->dontSeeElement('table');
        $page->dontSeeElement('.pagination');
    }
}
