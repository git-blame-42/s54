<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Enrollment;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * O Administrador inscreve e remove Participantes pelo show do Evento.
 */
class ManageEnrollmentsCest extends BaseEventsCest
{
    public function enrollAndRemoveParticipant(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Inscrever e remover um Participante pelo show do Evento');
        $page->amOnPage('/admin/events');
        $page->click('Ver', $this->rowOf('Evento 02'));

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Nenhuma inscrição.');
        $page->dontSeeElement('#enrollments');

        $page->selectOption('#enrollment_user_id', 'Participante 2 (participante2@example.com)');
        $page->click('Inscrever');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Participante inscrito com sucesso!');
        $page->see('participante2@example.com', '#enrollments');
        $page->see('1/' . $event->capacity);
        $page->dontSeeElement('#enrollment_user_id option[value="' . $this->participants[2]->id . '"]');
        Assert::assertSame(1, $event->enrollmentsCount());

        $page->click('Remover', "//table[@id='enrollments']//tr[contains(., 'participante2@example.com')]");

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Inscrição removida com sucesso!');
        $page->see('Nenhuma inscrição.');
        $page->seeElement('#enrollment_user_id option[value="' . $this->participants[2]->id . '"]');
        Assert::assertCount(0, Enrollment::where(['event_id' => $event->id]));
    }

    public function enrollInFullEventIsRejected(AcceptanceTester $page): void
    {
        $event = $this->events[1];
        $this->createUser('Participante 4', 'participante4@example.com', 'participant');

        $page->wantTo('Inscrição recusada em Evento lotado');
        $page->amOnPage("/admin/events/{$event->id}");

        $page->see('3/3');
        $page->selectOption('#enrollment_user_id', 'Participante 4 (participante4@example.com)');
        $page->click('Inscrever');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('não há vagas neste evento!');
        $page->dontSee('participante4@example.com', '#enrollments');
        Assert::assertSame(3, $event->enrollmentsCount());
    }

    public function adminIsNotOfferedAndFullListSaysSo(AcceptanceTester $page): void
    {
        $event = $this->events[1];

        $page->wantTo('Select só com Participantes não inscritos');
        $page->amOnPage("/admin/events/{$event->id}");

        for ($i = 1; $i <= 3; $i++) {
            $page->see("participante{$i}@example.com", '#enrollments');
        }
        $page->dontSeeElement('#enrollment_user_id');
        $page->see('Todos os Participantes já estão inscritos.');
    }
}
