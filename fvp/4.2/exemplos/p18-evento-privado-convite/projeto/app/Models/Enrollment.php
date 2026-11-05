<?php

namespace App\Models;

use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\Model;
use Lib\Validations;

/**
 * Inscrição de um Participante em um Evento (tabela pivô event_user).
 *
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 */
class Enrollment extends Model
{
    protected static string $table = 'event_user';
    protected static array $columns = ['event_id', 'user_id'];

    /** Código de convite digitado pelo Participante (não é coluna; só vale para Evento privado). */
    private ?string $inviteCode = null;

    public function withInviteCode(?string $code): static
    {
        $this->inviteCode = $code;

        return $this;
    }

    public function validates(): void
    {
        if (!Validations::notEmpty('event_id', $this) || !Validations::notEmpty('user_id', $this)) {
            return;
        }

        if (!Validations::uniqueness(['event_id', 'user_id'], $this)) {
            $this->addError('base', 'você já está inscrito neste evento!');
            return;
        }

        $this->validatesAvailableSpots();
    }

    /**
     * Só ao criar: o Evento precisa existir, aceitar o código de convite (se privado) e ter vaga.
     */
    private function validatesAvailableSpots(): void
    {
        if (!$this->newRecord()) {
            return;
        }

        $event = Event::findById((int) $this->event_id);

        if ($event === null) {
            $this->addError('event_id', 'evento não encontrado!');
        } elseif ($event->isPrivate() && !$event->matchesInviteCode($this->inviteCode)) {
            $this->addError('base', 'código de convite inválido!');
        } elseif ($event->availableSpots() <= 0) {
            $this->addError('base', 'não há vagas neste evento!');
        }
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
