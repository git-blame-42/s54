<?php

namespace Tests\Acceptance\events;

use App\Models\Enrollment;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Comprovante de inscrição: número 1000-9999 gerado no save() da Inscrição.
 */
class EnrollmentReceiptCest extends BaseParticipantEventsCest
{
    public function participantSeesReceiptAfterEnrolling(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Ver o comprovante de inscrição depois de se inscrever');
        $page->amOnPage("/events/{$event->id}");
        $page->dontSee('Comprovante de inscrição');

        $page->click('Inscrever-se');

        $enrollment = $this->participants[1]->enrollmentIn($event);
        Assert::assertNotNull($enrollment);
        $page->see('Inscrição realizada com sucesso!');
        $page->see("Comprovante de inscrição: Nº {$enrollment->receipt_code}", '.receipt');
    }

    public function adminSeesReceiptCodesOfEnrollments(AcceptanceTester $page): void
    {
        $event = $this->events[1];

        $page->wantTo('Administrador vê os comprovantes na lista de inscritos');
        $page->amOnPage('/logout');
        $page->login(self::ADMIN_EMAIL, self::PASSWORD);
        $page->amOnPage("/admin/events/{$event->id}");

        $page->see('Inscritos', 'h2');
        $page->seeNumberOfElements('table.enrollments tbody tr', 3);
        foreach (Enrollment::where(['event_id' => $event->id]) as $enrollment) {
            $page->see("Nº {$enrollment->receipt_code}", 'table.enrollments');
        }
        $page->see($this->participants[1]->email, 'table.enrollments');
    }
}
