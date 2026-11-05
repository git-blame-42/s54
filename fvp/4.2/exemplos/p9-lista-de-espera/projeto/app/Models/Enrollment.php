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
 * @property string $status 'confirmed' (ocupa vaga) ou 'waiting' (lista de espera)
 */
class Enrollment extends Model
{
    /** Valores do ENUM `status`, com o rótulo exibido nas telas. */
    public const STATUSES = [
        'confirmed' => 'Confirmada',
        'waiting' => 'Lista de espera',
    ];

    protected static string $table = 'event_user';
    protected static array $columns = ['event_id', 'user_id', 'status'];

    /**
     * @param array<string, mixed> $params
     */
    public function __construct($params = [])
    {
        parent::__construct($params);

        // save() grava todas as colunas: o DEFAULT do schema não vale, então o padrão fica aqui.
        if ($this->status === null) {
            $this->status = 'confirmed';
        }
    }

    public function validates(): void
    {
        if (!Validations::notEmpty('event_id', $this) || !Validations::notEmpty('user_id', $this)) {
            return;
        }

        Validations::inclusion('status', $this, array_keys(self::STATUSES));

        if (!Validations::uniqueness(['event_id', 'user_id'], $this)) {
            $this->addError('base', 'você já está inscrito neste evento!');
            return;
        }

        $this->decideStatusBySpots();
    }

    /**
     * Só ao criar: o Evento precisa existir; sem vaga (availableSpots() === 0), a Inscrição
     * entra na lista de espera em vez de ser recusada.
     */
    private function decideStatusBySpots(): void
    {
        if (!$this->newRecord()) {
            return;
        }

        $event = Event::findById($this->event_id);

        if ($event === null) {
            $this->addError('event_id', 'evento não encontrado!');
        } elseif ($event->availableSpots() === 0) {
            $this->status = 'waiting';
        }
    }

    public function isWaiting(): bool
    {
        return $this->status === 'waiting';
    }

    /**
     * Posição na lista de espera do Evento (1 = próximo a ser promovido); 0 se não está na espera.
     */
    public function waitingPosition(): int
    {
        if (!$this->isWaiting()) {
            return 0;
        }

        $event = Event::findById($this->event_id);
        if ($event === null) {
            return 0;
        }

        foreach ($event->waitingList() as $index => $enrollment) {
            if ($enrollment->id === $this->id) {
                return $index + 1;
            }
        }

        return 0;
    }

    /**
     * Remove a Inscrição; se ela ocupava vaga (confirmed), promove o primeiro da lista de espera.
     */
    public function destroy(): bool
    {
        $wasConfirmed = $this->status === 'confirmed';

        if (!parent::destroy()) {
            return false;
        }

        if ($wasConfirmed) {
            Event::findById($this->event_id)?->promoteFirstWaiting();
        }

        return true;
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
