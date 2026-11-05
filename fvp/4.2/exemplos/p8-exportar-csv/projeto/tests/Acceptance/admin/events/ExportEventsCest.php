<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

class ExportEventsCest extends BaseEventsCest
{
    public function listingHasLinkToCsvExport(AcceptanceTester $page): void
    {
        $page->wantTo('Exportar a listagem em CSV (link na listagem)');
        $page->amOnPage('/admin/events');

        // O clique dispara um download, que o Selenium não mostra; o conteúdo é conferido nos testes de Integration.
        $page->seeLink('Exportar CSV', '/admin/events/export');
    }
}
