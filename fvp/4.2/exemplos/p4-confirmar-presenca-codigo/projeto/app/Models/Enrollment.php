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
 * @property string|null $confirmed_at data e hora da confirmação de presença (NULL = não confirmada)
 */
class Enrollment extends Model
{
    protected static string $table = 'event_user';
    protected static array $columns = ['event_id', 'user_id', 'confirmed_at'];

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
     * Só ao criar: o Evento precisa existir e ter vaga (availableSpots() > 0).
     */
    private function validatesAvailableSpots(): void
    {
        if (!$this->newRecord()) {
            return;
        }

        $event = Event::findById((int) $this->event_id);

        if ($event === null) {
            $this->addError('event_id', 'evento não encontrado!');
        } elseif ($event->availableSpots() <= 0) {
            $this->addError('base', 'não há vagas neste evento!');
        }
    }

    /**
     * Confirma a presença com o código de check-in do Evento. Grava confirmed_at só se o código
     * estiver certo e a presença ainda não tiver sido confirmada; senão retorna false e registra
     * o motivo em errors('base'). A mensagem de código errado é genérica (não revela o código).
     */
    public function confirm(string $code): bool
    {
        $this->errors = [];

        if ($this->isConfirmed()) {
            $this->addError('base', 'presença já confirmada!');
            return false;
        }

        $event = Event::findById((int) $this->event_id);

        if ($event === null || !ctype_digit($code) || (int) $code !== (int) $event->checkin_code) {
            $this->addError('base', 'código inválido!');
            return false;
        }

        // update() não passa por validates(): proposital, a inscrição já foi validada ao ser criada.
        return $this->update(['confirmed_at' => date('Y-m-d H:i:s')]);
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null && $this->confirmed_at !== '';
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
