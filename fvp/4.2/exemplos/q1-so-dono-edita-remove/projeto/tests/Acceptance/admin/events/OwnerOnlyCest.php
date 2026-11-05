<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Q1: só o Administrador que criou o Evento edita e remove. Os Eventos da base são do
 * admin@example.com; aqui o login é trocado para um segundo Administrador.
 */
class OwnerOnlyCest extends BaseEventsCest
{
    private const OTHER_EMAIL = 'outro.admin@example.com';

    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);

        $this->createUser('Outro Administrador', self::OTHER_EMAIL, 'admin');
        $page->amOnPage('/logout');
        $page->login(self::OTHER_EMAIL, self::PASSWORD);
    }

    public function otherAdminSeesEventsWithoutEditAndRemove(AcceptanceTester $page): void
    {
        $page->wantTo('Listar Eventos de outro Administrador sem os botões Editar e Remover');
        $page->amOnPage('/admin/events');

        $page->see('Evento 02', $this->rowOf('Evento 02'));
        $page->see('Ver', $this->rowOf('Evento 02'));
        $page->dontSee('Editar', $this->rowOf('Evento 02'));
        $page->dontSee('Remover', $this->rowOf('Evento 02'));

        $page->click('Ver', $this->rowOf('Evento 02'));
        $page->seeCurrentUrlEquals("/admin/events/{$this->events[2]->id}");
        $page->see('Evento 02', 'h1');
        $page->dontSee('Editar', 'main');
    }

    public function otherAdminCannotOpenEditByUrl(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Abrir a edição de Evento alheio pela URL e ser barrado');
        $page->amOnPage("/admin/events/{$event->id}/edit");

        $page->seeCurrentUrlEquals('/admin/events');
        $page->see('Evento não encontrado!');
        $stored = Event::findById((int) $event->id);
        Assert::assertNotNull($stored);
        Assert::assertSame('Evento 02', $stored->title);
    }

    public function ownEventShowsEditAndRemove(AcceptanceTester $page): void
    {
        $page->wantTo('Ver Editar e Remover no Evento criado pelo próprio Administrador');
        $page->amOnPage('/admin/events/new');
        $this->fillEventForm($page, $this->validEventData(['title' => 'Evento do Outro']));
        $page->click('Salvar');
        $page->see('Evento cadastrado com sucesso!');

        $page->amOnPage('/admin/events/page/4');
        $page->see('Editar', $this->rowOf('Evento do Outro'));
        $page->see('Remover', $this->rowOf('Evento do Outro'));
    }
}
