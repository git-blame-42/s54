<?php

namespace Tests\Acceptance\events;

use App\Models\Comment;
use Tests\Acceptance\admin\events\BaseEventsCest;
use Tests\Support\AcceptanceTester;

/**
 * P10: Participante comenta na tela do Evento (/events/{id}); o Administrador vê o comentário
 * no show da Área Administrativa e também pode comentar.
 */
class CommentsCest extends BaseEventsCest
{
    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);

        // A base loga como Administrador; aqui o cenário começa como Participante.
        $page->amOnPage('/logout');
        $page->login('participante1@example.com', self::PASSWORD);
    }

    public function participantCommentsOnEvent(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Comentar em um Evento como Participante');
        $page->amOnPage("/events/{$event->id}");
        $page->see('Evento 02', 'h1');
        $page->see('Comentários (0)');
        $page->see('Nenhum comentário ainda.');

        $page->fillField('#comment_body', 'Haverá certificado? <b>sim</b>');
        $page->click('Comentar');

        $page->seeCurrentUrlEquals("/events/{$event->id}");
        $page->see('Comentário publicado!', '.alert-success');
        $page->see('Comentários (1)');
        $page->see('Participante 1', '.comment');
        $page->see('Haverá certificado? <b>sim</b>', '.comment-body');
        $page->dontSeeElement('.comment-body b');
    }

    public function emptyCommentIsRejected(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Enviar comentário vazio e ver o erro');
        $page->amOnPage("/events/{$event->id}");
        $page->click('Comentar');

        $page->seeCurrentUrlEquals("/events/{$event->id}");
        $page->see('Comentário não pode ser vazio!', '.alert-danger');
        $page->see('Nenhum comentário ainda.');
        \PHPUnit\Framework\Assert::assertCount(0, Comment::all());
    }

    public function adminSeesAndRepliesInAdminShow(AcceptanceTester $page): void
    {
        $event = $this->events[2];
        $page->amOnPage("/events/{$event->id}");
        $page->fillField('#comment_body', 'Pergunta do Participante');
        $page->click('Comentar');

        $page->wantTo('Ver e responder o comentário pelo show da Área Administrativa');
        $page->amOnPage('/logout');
        $page->login(self::ADMIN_EMAIL, self::PASSWORD);
        $page->amOnPage("/admin/events/{$event->id}");
        $page->see('Pergunta do Participante', '.comment-body');

        $page->fillField('#comment_body', 'Resposta do Administrador');
        $page->click('Comentar');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Comentários (2)');
        $page->see('Resposta do Administrador', '.comment-body');
    }
}
