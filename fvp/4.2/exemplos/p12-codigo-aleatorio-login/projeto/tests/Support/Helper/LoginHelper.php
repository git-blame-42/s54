<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Module;

class LoginHelper extends Module
{
    public function login(string $username, string $password): void
    {
        /** @var \Tests\Support\AcceptanceTester $page */
        $page = $this->getModule('WebDriver');
        $this->submitCredentials($username, $password);

        // Com a senha certa vem a tela do código; o código chega no flash (simulação do envio).
        if ($page->grabFromCurrentUrl() === '/login/code') {
            $page->fillField('code', $this->grabLoginCode());
            $page->click('Confirmar');
        }
    }

    /**
     * Só o primeiro passo do login (e-mail e senha), sem informar o código.
     */
    public function submitCredentials(string $username, string $password): void
    {
        /** @var \Tests\Support\AcceptanceTester $page */
        $page = $this->getModule('WebDriver');
        $page->amOnPage('/login');
        $page->fillField('user[email]', $username);
        $page->fillField('user[password]', $password);
        $page->click('Entrar');
    }

    /**
     * Lê o código de 4 dígitos do alerta "Seu código de acesso é 1234".
     */
    public function grabLoginCode(): string
    {
        /** @var \Tests\Support\AcceptanceTester $page */
        $page = $this->getModule('WebDriver');
        $text = $page->grabTextFrom('.alert-success');
        \PHPUnit\Framework\Assert::assertMatchesRegularExpression('/\b\d{4}\b/', $text);
        preg_match('/\b(\d{4})\b/', $text, $matches);

        return $matches[1];
    }

    public function logout(): void
    {
        /** @var \Tests\Support\AcceptanceTester $page */
        $page = $this->getModule('WebDriver');
        $page->click('fulano@example.com');
        $page->click('Sair');
    }
}
