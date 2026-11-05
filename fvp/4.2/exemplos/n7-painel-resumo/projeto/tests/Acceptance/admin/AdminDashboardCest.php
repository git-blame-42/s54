<?php

namespace Tests\Acceptance\admin;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class AdminDashboardCest extends BaseAcceptanceCest
{
    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);
        $admin = new User([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin'
        ]);
        $admin->save();

        $user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $user->save();
    }

    public function accessAdminWithoutLogin(AcceptanceTester $page): void
    {
        $page->amOnPage('/admin');

        $page->seeCurrentUrlEquals('/login');
        $page->see('Você deve estar logado para acessar essa página');
    }

    public function accessAdminAsCommonUser(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');
        $page->amOnPage('/admin');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Acesso restrito a administradores');
    }

    public function accessAdminAsAdmin(AcceptanceTester $page): void
    {
        $page->login('admin@example.com', '123456');
        $page->amOnPage('/admin');

        $page->see('Painel Administrativo', '//h1');
        $page->see('Olá, Admin!');
        $page->see('0', '#total_events');
        $page->see('0', '#total_enrollments');
        $page->see('Nenhum evento agendado.');
    }

    public function adminDashboardShowsSummary(AcceptanceTester $page): void
    {
        $admin = User::findByEmail('admin@example.com');
        $participant = User::findByEmail('fulano@example.com');
        $days = ['Oficina Distante' => 40, 'Palestra Amanhã' => 1, 'Palestra Semana' => 7, 'Minicurso Mês' => 30];
        $kinds = ['Oficina Distante' => 'workshop', 'Palestra Amanhã' => 'lecture', 'Palestra Semana' => 'lecture',
            'Minicurso Mês' => 'short_course'];
        foreach ($days as $title => $daysAhead) {
            $event = new Event([
                'user_id' => $admin->id,
                'title' => $title,
                'description' => 'Descrição.',
                'kind' => $kinds[$title],
                'modality' => 'online',
                'audience' => 'students',
                'starts_on' => date('Y-m-d', strtotime("+{$daysAhead} days")),
                'location' => 'Online',
                'capacity' => 10,
            ]);
            $event->save();
            if ($title === 'Palestra Amanhã') {
                (new Enrollment(['event_id' => $event->id, 'user_id' => $participant->id]))->save();
            }
        }

        $page->wantTo('Ver o resumo do painel: totais, Eventos por tipo e próximos 3');
        $page->login('admin@example.com', '123456');
        $page->amOnPage('/admin');

        $page->see('4', '#total_events');
        $page->see('1', '#total_enrollments');
        $page->see('Palestra', '#events_by_kind tr:nth-child(1)');
        $page->see('2', '#events_by_kind tr:nth-child(1)');
        $page->see('0', '#events_by_kind tr:nth-child(4)');
        $page->see('Palestra Amanhã', '#upcoming_events tr:nth-child(1)');
        $page->see('Palestra Semana', '#upcoming_events tr:nth-child(2)');
        $page->see('Minicurso Mês', '#upcoming_events tr:nth-child(3)');
        $page->dontSee('Oficina Distante', '#upcoming_events');

        $page->click('Palestra Amanhã');
        $page->see('Palestra Amanhã', 'h1');
    }
}
