<?php

namespace Tests\Acceptance\authentications;

use App\Models\User;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

/**
 * Login por código (FVP B11) no navegador: pede o código pelo e-mail, lê o código do
 * flash na tela (não há envio de e-mail no projeto) e entra com ele; depois, o caso
 * do código errado. Último passo da solução; a partir daqui: ./run phpcs,
 * ./run phpstan, ./run test e ./run test:browser (e ./run db:reset, pois o schema mudou).
 */
class LoginByCodeCest extends BaseAcceptanceCest
{
    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);
        $user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $user->save();
    }

    public function loginWithAValidCode(AcceptanceTester $page): void
    {
        $this->requestCode($page);

        $page->seeCurrentUrlEquals('/login/code/verify');
        // O código só existe em texto puro na tela: é de lá que o teste o lê.
        $text = (string) $page->grabTextFrom('.alert-success');
        $code = preg_match('/\d{4}/', $text, $match) === 1 ? $match[0] : '';
        $page->fillField('login_code[code]', $code);
        $page->click('Entrar');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Login realizado com sucesso!');
        $page->see('fulano@example.com');
    }

    public function loginWithAWrongCode(AcceptanceTester $page): void
    {
        $this->requestCode($page);

        // 0000 está fora de 1000..9999: nunca coincide com o código sorteado.
        $page->fillField('login_code[code]', '0000');
        $page->click('Entrar');

        $page->seeCurrentUrlEquals('/login/code/verify');
        $page->see('Código inválido!');
    }

    private function requestCode(AcceptanceTester $page): void
    {
        $page->amOnPage('/login/code');
        $page->fillField('login_code[email]', 'fulano@example.com');
        $page->click('Receber código');
    }
}
