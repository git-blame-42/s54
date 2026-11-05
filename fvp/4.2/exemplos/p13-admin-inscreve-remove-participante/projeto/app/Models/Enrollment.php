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

    /**
     * Regras da Inscrição: o usuário existe e é Participante, o Evento existe e tem vaga
     * e o Participante ainda não está inscrito nele. O user_id pode vir de um formulário, então
     * é conferido aqui, e não no controller.
     */
    public function validates(): void
    {
        if (!Validations::notEmpty('event_id', $this) || !Validations::notEmpty('user_id', $this)) {
            return;
        }

        if (!$this->newRecord()) {
            return;
        }

        if (!$this->validatesParticipant()) {
            return;
        }

        $event = $this->validatesEvent();
        if ($event === null) {
            return;
        }

        if (self::exists(['event_id' => $event->id, 'user_id' => $this->user_id])) {
            $this->addError('base', 'o participante já está inscrito neste evento!');
            return;
        }

        if ($event->availableSpots() <= 0) {
            $this->addError('base', 'não há vagas neste evento!');
        }
    }

    /**
     * O user_id precisa ser um número inteiro de um usuário existente com perfil Participante.
     */
    private function validatesParticipant(): bool
    {
        $id = filter_var((string) $this->user_id, FILTER_VALIDATE_INT);
        $user = $id === false ? null : User::findById($id);

        if ($user === null) {
            $this->addError('base', 'o usuário informado não existe!');
            return false;
        }

        if ($user->role !== 'participant') {
            $this->addError('base', 'somente Participantes podem ser inscritos!');
            return false;
        }

        return true;
    }

    private function validatesEvent(): ?Event
    {
        $id = filter_var((string) $this->event_id, FILTER_VALIDATE_INT);
        $event = $id === false ? null : Event::findById($id);

        if ($event === null) {
            $this->addError('base', 'o evento informado não existe!');
        }

        return $event;
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
