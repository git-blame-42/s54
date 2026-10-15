<?php

namespace Tests\Acceptance\events;

use App\Models\Event;
use App\Models\User;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class EventCest extends BaseAcceptanceCest
{
    private User $user;

    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);
        $this->user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $this->user->save();
    }

    public function accessEventsWithoutLogin(AcceptanceTester $page): void
    {
        $page->amOnPage('/events');

        $page->seeCurrentUrlEquals('/login');
        $page->see('Você deve estar logado para acessar essa página');
    }

    public function createEventWithInvalidData(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');
        $page->amOnPage('/events/new');
        $page->see('Novo Evento', '//h1');

        $page->click('Criar evento');

        $page->see('Existem dados incorretos! Verifique o formulário.');
        $page->see('não pode ser vazio!');
    }

    public function createEventWithValidData(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');
        $page->amOnPage('/events/new');

        $page->fillField('event[title]', 'Semana Acadêmica');
        $this->fillStartsAt($page, '2026-10-20T19:30');
        $page->click('Criar evento');

        $page->seeCurrentUrlEquals('/events');
        $page->see('Evento criado com sucesso!');
        $page->see('Semana Acadêmica', '//table');
        $page->see('20/10/2026 19:30', '//table');
    }

    public function listOnlyMyEvents(AcceptanceTester $page): void
    {
        $this->createEvent($this->user, 'Palestra de Abertura', '2026-10-21 19:00:00');
        $other = new User([
            'name' => 'Beltrano',
            'email' => 'beltrano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $other->save();
        $this->createEvent($other, 'Evento de outro', '2026-10-22 19:00:00');

        $page->login('fulano@example.com', '123456');
        $page->click('Eventos');

        $page->seeCurrentUrlEquals('/events');
        $page->see('Meus Eventos', '//h1');
        $page->see('Palestra de Abertura', '//table');
        $page->dontSee('Evento de outro');
    }

    public function showEvent(AcceptanceTester $page): void
    {
        $event = $this->createEvent($this->user, 'Workshop de PHP', '2026-10-22 14:00:00');

        $page->login('fulano@example.com', '123456');
        $page->amOnPage("/events/{$event->id}");

        $page->see("Evento #{$event->id}", '//h1');
        $page->see('Workshop de PHP');
        $page->see('Início: 22/10/2026 14:00');
    }

    public function updateEvent(AcceptanceTester $page): void
    {
        $event = $this->createEvent($this->user, 'Workshop de PHP', '2026-10-22 14:00:00');

        $page->login('fulano@example.com', '123456');
        $page->amOnPage("/events/{$event->id}/edit");
        $page->seeInField('event[title]', 'Workshop de PHP');

        $page->fillField('event[title]', 'Workshop de PHP 8');
        $this->fillStartsAt($page, '2026-10-23T09:00');
        $page->click('Salvar alterações');

        $page->seeCurrentUrlEquals('/events');
        $page->see('Evento atualizado com sucesso!');
        $page->see('Workshop de PHP 8', '//table');
        $page->see('23/10/2026 09:00', '//table');
    }

    public function updateEventWithInvalidData(AcceptanceTester $page): void
    {
        $event = $this->createEvent($this->user, 'Workshop de PHP', '2026-10-22 14:00:00');

        $page->login('fulano@example.com', '123456');
        $page->amOnPage("/events/{$event->id}/edit");

        $page->fillField('event[title]', '');
        $page->click('Salvar alterações');

        $page->see('Existem dados incorretos! Verifique o formulário.');
        $page->see('não pode ser vazio!');
    }

    public function destroyEvent(AcceptanceTester $page): void
    {
        $this->createEvent($this->user, 'Workshop de PHP', '2026-10-22 14:00:00');

        $page->login('fulano@example.com', '123456');
        $page->amOnPage('/events');
        $page->click('Excluir');

        $page->seeCurrentUrlEquals('/events');
        $page->see('Evento removido com sucesso!');
        $page->dontSee('Workshop de PHP');
        $page->see('Nenhum evento cadastrado.');
    }

    private function createEvent(User $user, string $title, string $startsAt): Event
    {
        $event = new Event(['title' => $title, 'starts_at' => $startsAt, 'user_id' => $user->id]);
        $event->save();

        return $event;
    }

    /**
     * O Chrome não aceita texto digitado num input datetime-local pelo fillField;
     * o valor é atribuído direto no DOM, no formato que o navegador enviaria.
     */
    private function fillStartsAt(AcceptanceTester $page, string $value): void
    {
        $page->executeJS("document.getElementById('event_starts_at').value = '{$value}';");
    }
}
