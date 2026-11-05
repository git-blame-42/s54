<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class TimestampsCest extends BaseEventsCest
{
    public function showDisplaysCreatedAndUpdatedAt(AcceptanceTester $page): void
    {
        $event = $this->events[2];
        $event->update(['created_at' => '2026-02-03 14:07:00', 'updated_at' => '2026-02-03 14:07:00']);

        $page->wantTo('Detalhes e listagem do Evento com "Criado em" e "Atualizado em"');
        $page->amOnPage("/admin/events/{$event->id}");
        $page->see('Criado em');
        $page->see('03/02/2026 14:07', '//dt[normalize-space(.)="Criado em"]/following-sibling::dd[1]');
        $page->see('03/02/2026 14:07', '//dt[normalize-space(.)="Atualizado em"]/following-sibling::dd[1]');

        // Na listagem, as mesmas datas na linha do Evento.
        $page->amOnPage('/admin/events');
        $page->see('Criado em', 'table thead');
        $page->see('Atualizado em', 'table thead');
        $page->see('03/02/2026 14:07', $this->rowOf('Evento 02'));
        $page->amOnPage("/admin/events/{$event->id}");

        // Edita pelo formulário: "Atualizado em" muda, "Criado em" fica.
        $page->click('Editar');
        $this->fillEventForm($page, ['location' => 'Auditório Novo']);
        $page->click('Salvar');

        $page->see('Evento atualizado com sucesso!');
        $page->see('03/02/2026 14:07', '//dt[normalize-space(.)="Criado em"]/following-sibling::dd[1]');
        $page->dontSee('03/02/2026 14:07', '//dt[normalize-space(.)="Atualizado em"]/following-sibling::dd[1]');

        $stored = Event::findById((int) $event->id);
        Assert::assertNotNull($stored);
        Assert::assertSame('2026-02-03 14:07:00', $stored->created_at);
        $page->see($stored->formattedUpdatedAt(), '//dt[normalize-space(.)="Atualizado em"]/following-sibling::dd[1]');
    }
}
