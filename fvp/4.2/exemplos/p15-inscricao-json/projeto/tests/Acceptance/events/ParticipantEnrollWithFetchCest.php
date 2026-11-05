<?php

namespace Tests\Acceptance\events;

use App\Models\Enrollment;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * O botão "Inscrever-se" usa fetch() pedindo JSON (public/assets/js/enrollments.js) e atualiza a
 * tela sem recarregar. O marcador em `window` some se a página recarregar.
 */
class ParticipantEnrollWithFetchCest extends BaseParticipantEventsCest
{
    public function enrollWithoutReloadingThePage(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Inscrição via fetch() atualiza o contador sem recarregar a página');
        $page->amOnPage("/events/{$event->id}");
        $page->see('0/22', '#event_spots');
        $page->executeJS('window.semRecarregar = true;');

        $page->click('Inscrever-se');

        $page->waitForText('Inscrição realizada com sucesso!', 5, '#enrollment_feedback');
        $page->see('1/22', '#event_spots');
        $page->see('(21 disponíveis)', '#event_available');
        $page->see('Você está inscrito neste evento.');
        $page->dontSee('Inscrever-se');
        $page->dontSeeElement('.alert-dismissible');
        Assert::assertTrue($page->executeJS('return window.semRecarregar === true;'));
        Assert::assertTrue($this->participants[1]->isEnrolledIn($event));
    }

    public function fullEventShowsErrorWithoutReloading(AcceptanceTester $page): void
    {
        $event = $this->events[1];
        $user = $this->loginAsNewParticipant($page, 'novo@example.com');

        $page->wantTo('Inscrição via fetch() em Evento lotado mostra o erro sem recarregar');
        $page->amOnPage("/events/{$event->id}");
        $page->executeJS('window.semRecarregar = true;');

        $page->click('Inscrever-se');

        $page->waitForText('não há vagas neste evento!', 5, '#enrollment_feedback');
        $page->see('3/3', '#event_spots');
        $page->see('Inscrever-se');
        Assert::assertTrue($page->executeJS('return window.semRecarregar === true;'));
        Assert::assertFalse($user->isEnrolledIn($event));
        Assert::assertCount(3, Enrollment::where(['event_id' => $event->id]));
    }
}
