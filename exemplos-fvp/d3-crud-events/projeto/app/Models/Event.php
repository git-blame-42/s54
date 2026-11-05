<?php

namespace App\Models;

use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\Model;
use Lib\Validations;

/**
 * Evento cadastrado por um usuário (relação 1xN com users).
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $starts_at
 * @property int $user_id
 * @property User $user
 */
class Event extends Model
{
    protected static string $table = 'events';
    protected static array $columns = ['title', 'starts_at', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function validates(): void
    {
        Validations::notEmpty('title', $this);
        Validations::notEmpty('starts_at', $this);
    }

    /**
     * Data de início no formato pt-BR (dd/mm/aaaa hh:mm) para exibição.
     */
    public function startsAtFormatted(): string
    {
        $timestamp = $this->startsAtTimestamp();

        return $timestamp === null ? '' : date('d/m/Y H:i', $timestamp);
    }

    /**
     * Data de início no formato aceito pelo input datetime-local (aaaa-mm-ddThh:mm).
     */
    public function startsAtForInput(): string
    {
        $timestamp = $this->startsAtTimestamp();

        return $timestamp === null ? '' : date('Y-m-d\TH:i', $timestamp);
    }

    private function startsAtTimestamp(): ?int
    {
        if ($this->starts_at === null || $this->starts_at === '') {
            return null;
        }

        $timestamp = strtotime($this->starts_at);

        return $timestamp === false ? null : $timestamp;
    }
}
