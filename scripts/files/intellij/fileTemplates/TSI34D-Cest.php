<?php

namespace ${NAMESPACE};

use Tests\Support\AcceptanceTester;

class ${NAME} extends ${classe_base}
{
    public function ${cenario}(AcceptanceTester ${DS}page): void
    {
        ${DS}page->wantTo('${descricao}');
        ${DS}page->amOnPage('${url}');
        ${DS}page->see('${titulo}', 'h1');
    }
}
