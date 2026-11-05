<?php

namespace Tests\Integration\Controllers;

use Lib\FlashMessage;

class HomeControllerTest extends ControllerTestCase
{
    public function test_render_home_page(): void
    {
        $response = $this->get(
            action: 'index',
            controllerName: 'App\Controllers\HomeController'
        );

        $this->assertMatchesRegularExpression('/<h1>\s*Home Page\s*<\/h1>/', $response);
        $this->assertMatchesRegularExpression('/TSI3D Framework Template/', $response);
    }

    public function test_index_should_escape_flash_message(): void
    {
        FlashMessage::danger('<b>Oi</b>');

        $response = $this->get(
            action: 'index',
            controllerName: 'App\Controllers\HomeController'
        );

        $this->assertStringContainsString('alert-danger', $response);
        $this->assertStringContainsString('&lt;b&gt;Oi&lt;/b&gt;', $response);
        $this->assertStringNotContainsString('<b>Oi</b>', $response);
    }

    public function test_index_should_show_success_flash_message_with_icon(): void
    {
        FlashMessage::success('Tudo certo');

        $response = $this->get(
            action: 'index',
            controllerName: 'App\Controllers\HomeController'
        );

        $this->assertStringContainsString('alert-success', $response);
        $this->assertStringContainsString('bi-check-circle-fill', $response);
        $this->assertStringContainsString('Tudo certo', $response);
    }
}
