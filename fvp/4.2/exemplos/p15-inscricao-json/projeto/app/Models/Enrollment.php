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
     * Mensagens de erro da última validação, sem as chaves (para respostas JSON). Quando há erro em
     * `base`, só ele: a duplicata também marca event_id e user_id com o texto genérico de
     * `Validations::uniqueness`, que não diz nada ao usuário.
     *
     * @return array<int, string>
     */
    public function errorMessages(): array
    {
        if (isset($this->errors['base'])) {
            return [$this->errors['base']];
        }

        return array_values(array_unique($this->errors));
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
