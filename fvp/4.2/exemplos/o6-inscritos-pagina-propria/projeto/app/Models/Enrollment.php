<?php

namespace App\Models;

use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\Model;

/**
 * Inscrição de um Participante em um Evento (tabela pivô event_user).
 *
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 * @property string|null $created_at data e hora da inscrição (Y-m-d H:i:s)
 */
class Enrollment extends Model
{
    protected static string $table = 'event_user';
    protected static array $columns = ['event_id', 'user_id', 'created_at'];

    /**
     * `Model::save()` grava todas as colunas de `$columns`: sem valor, `created_at` iria como NULL
     * explícito e o DEFAULT CURRENT_TIMESTAMP do schema não valeria. Por isso a data é definida aqui.
     */
    public function save(): bool
    {
        if ($this->newRecord() && $this->created_at === null) {
            $this->created_at = date('Y-m-d H:i:s');
        }

        return parent::save();
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
