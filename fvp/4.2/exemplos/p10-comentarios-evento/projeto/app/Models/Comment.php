<?php

namespace App\Models;

use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\Model;
use Lib\Validations;

/**
 * Comentário de um usuário em um Evento (filho 1xN de events).
 *
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 * @property string $body
 * @property string $created_at
 */
class Comment extends Model
{
    public const BODY_MAX_LENGTH = 500;

    protected static string $table = 'comments';
    protected static array $columns = ['event_id', 'user_id', 'body', 'created_at'];

    /**
     * `save()` grava todas as colunas de $columns (o DEFAULT do banco não é usado),
     * então created_at é preenchido aqui quando não vem do banco.
     *
     * @param array<string, mixed> $params
     */
    public function __construct($params = [])
    {
        parent::__construct($params);

        if ($this->created_at === null) {
            $this->created_at = date('Y-m-d H:i:s');
        }
    }

    public function validates(): void
    {
        Validations::notEmpty('event_id', $this);
        Validations::notEmpty('user_id', $this);

        Validations::notEmpty('body', $this) &&
            Validations::maxLength('body', $this, self::BODY_MAX_LENGTH);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function authorName(): string
    {
        $user = $this->user()->get();

        return $user instanceof User ? $user->name : '';
    }
}
