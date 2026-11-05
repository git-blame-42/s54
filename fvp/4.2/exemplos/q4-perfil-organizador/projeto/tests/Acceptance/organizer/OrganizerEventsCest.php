<?php

namespace Tests\Acceptance\organizer;

use App\Models\Event;
use App\Models\User;
use PHPUnit\Framework\Assert;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class OrganizerEventsCest extends BaseAcceptanceCest
{
    private User $organizer;

    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);

        $admin = $this->createUser('Administrador', 'admin@example.com', 'admin');
        $this->organizer = $this->createUser('Organizadora', 'organizador@example.com', 'organizer');

        $this->createEvent($this->organizer, 'Oficina da Organizadora');
        $this->createEvent($admin, 'Palestra do Administrador');

        $page->resizeWindow(1280, 2400);
    }

    public function organizerLoginListsOnlyOwnEvents(AcceptanceTester $page): void
    {
        $page->wantTo('Login do Organizador e listagem só dos Eventos dele');
        $page->login('organizador@example.com', '123456');

        $page->seeCurrentUrlEquals('/organizer/events');
        $page->see('Login realizado com sucesso!');
        $page->seeLink('Meus Eventos', '/organizer/events');
        $page->dontSeeLink('Administração');
        $page->see('Oficina da Organizadora', 'table');
        $page->dontSee('Palestra do Administrador');
        $page->see('Mostrando 1 - 1 de 1');
    }

    public function organizerCreatesEvent(AcceptanceTester $page): void
    {
        $page->wantTo('Organizador cadastra um Evento e ele fica como dono');
        $page->login('organizador@example.com', '123456');

        $page->click('Novo Evento');
        $page->seeCurrentUrlEquals('/organizer/events/new');
        $page->fillField('#event_title', 'Minicurso da Organizadora');
        $page->fillField('#event_description', 'Criado pela Organizadora.');
        $page->selectOption('#event_kind', Event::KINDS['short_course']);
        $page->checkOption('#event_modality_online');
        $page->checkOption('#event_audience_students');
        $page->executeJS(
            "document.getElementById('event_starts_on').value = arguments[0];",
            [date('Y-m-d', strtotime('+5 days'))]
        );
        $page->fillField('#event_location', 'Google Meet');
        $page->fillField('#event_capacity', '30');
        $page->click('Salvar');

        $event = Event::findBy(['title' => 'Minicurso da Organizadora']);
        Assert::assertNotNull($event);
        Assert::assertEquals($this->organizer->id, $event->user_id);
        $page->seeCurrentUrlEquals("/organizer/events/{$event->id}");
        $page->see('Evento cadastrado com sucesso!');

        $page->click('Voltar');
        $page->seeCurrentUrlEquals('/organizer/events');
        $page->see('Mostrando 1 - 2 de 2');
    }

    public function organizerCannotOpenAdminArea(AcceptanceTester $page): void
    {
        $page->wantTo('Organizador não entra na Área Administrativa');
        $page->login('organizador@example.com', '123456');
        $page->amOnPage('/admin/events');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Acesso restrito a administradores');
    }

    private function createUser(string $name, string $email, string $role): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }

    private function createEvent(User $owner, string $title): void
    {
        $event = new Event([
            'user_id' => $owner->id,
            'title' => $title,
            'description' => "Descrição de {$title}.",
            'kind' => 'workshop',
            'modality' => 'in_person',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+20 days')),
            'location' => 'Sala 1',
            'capacity' => 20,
        ]);
        Assert::assertTrue($event->save());
    }
}
