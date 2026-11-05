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
 * @property int|string|null $receipt_code número do comprovante (1000-9999), gerado no save(), único no Evento
 */
class Enrollment extends Model
{
    protected static string $table = 'event_user';
    protected static array $columns = ['event_id', 'user_id', 'receipt_code'];

    public const RECEIPT_CODE_MIN = 1000;
    public const RECEIPT_CODE_MAX = 9999;

    /**
     * Gera o número do comprovante na criação (nunca vem do formulário). Salvar de novo não troca o número.
     */
    public function save(): bool
    {
        $code = $this->receipt_code;

        if ($this->newRecord() && ($code === null || $code === '')) {
            $this->receipt_code = self::newReceiptCode((int) $this->event_id);
        }

        return parent::save();
    }

    /**
     * Sorteia um número de 1000 a 9999 que ainda não existe nas Inscrições do Evento (repete até achar).
     */
    public static function newReceiptCode(int $eventId): int
    {
        do {
            $code = random_int(self::RECEIPT_CODE_MIN, self::RECEIPT_CODE_MAX);
        } while (self::exists(['event_id' => $eventId, 'receipt_code' => $code]));

        return $code;
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
        $this->validatesReceiptCode();
    }

    /**
     * Número do comprovante: presente, na faixa 1000-9999 e único no Evento (cobre quem tenta forçar um repetido).
     */
    private function validatesReceiptCode(): void
    {
        if (!Validations::notEmpty('receipt_code', $this)) {
            return;
        }

        $code = filter_var($this->receipt_code, FILTER_VALIDATE_INT);
        if ($code === false || $code < self::RECEIPT_CODE_MIN || $code > self::RECEIPT_CODE_MAX) {
            $this->addError('receipt_code', 'deve estar entre 1000 e 9999!');
            return;
        }

        if (!Validations::uniqueness(['event_id', 'receipt_code'], $this)) {
            $this->addError('receipt_code', 'já existe neste evento!');
        }
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
