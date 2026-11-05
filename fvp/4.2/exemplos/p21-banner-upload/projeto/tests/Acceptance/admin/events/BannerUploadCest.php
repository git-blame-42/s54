<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use Core\Constants\Constants;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Imagem de capa (banner) do Evento por upload. Os arquivos de envio ficam em tests/Support/Data/:
 * banner.png é um PNG de verdade; banner-falso.png tem extensão .png, mas é texto.
 */
class BannerUploadCest extends BaseEventsCest
{
    public function _after(AcceptanceTester $page): void
    {
        // Apaga os arquivos que o upload gravou em public/assets/uploads/events/ antes de derrubar o banco.
        foreach (Event::all() as $event) {
            $event->banner()->delete();
        }

        $dir = (string) Constants::rootPath()->join('public/assets/uploads/events');
        if (is_dir($dir) && count((array) scandir($dir)) === 2) {
            rmdir($dir);
        }

        parent::_after($page);
    }

    public function createEventWithBanner(AcceptanceTester $page): void
    {
        $data = $this->validEventData(['title' => 'Evento com Capa']);

        $page->wantTo('Cadastrar um Evento com imagem de capa');
        $page->amOnPage('/admin/events/new');
        $this->fillEventForm($page, $data);
        $page->attachFile('#event_banner', 'banner.png');
        $page->click('Salvar');

        $page->see('Evento cadastrado com sucesso!');
        $event = Event::findBy(['title' => 'Evento com Capa']);
        Assert::assertNotNull($event);

        // Nome gerado pelo servidor, nunca o do arquivo enviado.
        $name = (string) $event->banner_name;
        Assert::assertMatchesRegularExpression('/^[0-9a-f]{32}\.png$/', $name);
        Assert::assertFileExists((string) Constants::rootPath()->join("public/assets/uploads/events/{$name}"));

        $page->seeElement('img.event-banner', ['src' => "/assets/uploads/events/{$name}"]);
        $page->waitForJS("return document.querySelector('img.event-banner').complete;", 5);
        $width = $page->executeJS("return document.querySelector('img.event-banner').naturalWidth;");
        Assert::assertSame(240, $width);
    }

    public function replaceBannerOnEdit(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Trocar a imagem de capa na edição sem perder os outros dados');
        $page->amOnPage("/admin/events/{$event->id}/edit");
        $page->dontSeeElement('img.event-banner-current');
        $page->attachFile('#event_banner', 'banner.png');
        $page->click('Salvar');
        $page->see('Evento atualizado com sucesso!');

        $first = (string) Event::findById((int) $event->id)?->banner_name;
        Assert::assertNotSame('', $first);

        $page->amOnPage("/admin/events/{$event->id}/edit");
        $page->seeElement('img.event-banner-current', ['src' => "/assets/uploads/events/{$first}"]);
        $page->attachFile('#event_banner', 'banner.png');
        $page->click('Salvar');
        $page->see('Evento atualizado com sucesso!');

        $stored = Event::findById((int) $event->id);
        Assert::assertNotNull($stored);
        Assert::assertNotSame($first, $stored->banner_name);
        Assert::assertSame('Evento 02', $stored->title);
        Assert::assertFileDoesNotExist((string) Constants::rootPath()->join("public/assets/uploads/events/{$first}"));
    }

    public function rejectFileThatIsNotAnImage(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Recusar arquivo de texto com extensão .png');
        $page->amOnPage("/admin/events/{$event->id}/edit");
        $this->fillEventForm($page, ['title' => 'Título que não deve ser salvo']);
        $page->attachFile('#event_banner', 'banner-falso.png');
        $page->click('Salvar');

        $page->see('Existem dados incorretos! Por favor, verifique!');
        $page->see('deve ser uma imagem JPG ou PNG!', '.invalid-feedback');
        $page->seeElement('#event_banner.is-invalid');

        $stored = Event::findById((int) $event->id);
        Assert::assertNotNull($stored);
        Assert::assertNull($stored->banner_name);
        Assert::assertSame('Evento 02', $stored->title);
    }
}
