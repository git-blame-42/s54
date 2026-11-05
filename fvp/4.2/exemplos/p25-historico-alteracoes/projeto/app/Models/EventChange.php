<?php

namespace App\Models;

use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\Model;
use Core\Database\Database;
use Lib\Validations;
use PDO;

/**
 * Uma linha do histórico de alterações de um Evento: qual campo mudou, de que valor
 * para qual, quem editou e quando. Gravada por Event::saveWithHistory().
 *
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 * @property string $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string $created_at
 */
class EventChange extends Model
{
    /** Rótulo de cada campo do Evento na tela do histórico. */
    public const FIELD_LABELS = [
        'title' => 'Título',
        'description' => 'Descrição',
        'kind' => 'Tipo',
        'modality' => 'Modalidade',
        'audience' => 'Público-alvo',
        'starts_on' => 'Data',
        'location' => 'Local',
        'capacity' => 'Vagas',
    ];

    protected static string $table = 'event_changes';
    protected static array $columns = ['event_id', 'user_id', 'field', 'old_value', 'new_value', 'created_at'];

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

        Validations::notEmpty('field', $this) &&
            Validations::inclusion('field', $this, Event::columns());
    }

    /**
     * Histórico de um Evento, do mais recente para o mais antigo. Várias linhas da mesma
     * edição têm o mesmo created_at (resolução de segundos); o id desempata.
     *
     * @return array<static>
     */
    public static function forEvent(int $eventId): array
    {
        $table = static::$table;
        $attributes = implode(', ', static::$columns);

        $sql = <<<SQL
            SELECT id, {$attributes} FROM {$table}
            WHERE event_id = :event_id
            ORDER BY created_at DESC, id DESC;
        SQL;

        $stmt = Database::getDatabaseConn()->prepare($sql);
        $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
        $stmt->execute();

        $changes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $changes[] = new static($row);
        }

        return $changes;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function userName(): string
    {
        $user = $this->user()->get();

        return $user instanceof User ? $user->name : '';
    }

    public function fieldLabel(): string
    {
        return self::FIELD_LABELS[$this->field] ?? $this->field;
    }

    public function oldValueLabel(): string
    {
        return $this->valueLabel($this->old_value);
    }

    public function newValueLabel(): string
    {
        return $this->valueLabel($this->new_value);
    }

    /**
     * Valor como o usuário vê no show do Evento: rótulo do ENUM/SET e data em d/m/Y.
     * Valor desconhecido (ex.: opção removida do ENUM depois) aparece como foi gravado.
     */
    private function valueLabel(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($this->field) {
            'kind' => Event::KINDS[$value] ?? $value,
            'modality' => Event::MODALITIES[$value] ?? $value,
            'audience' => implode(', ', array_map(
                fn(string $item) => Event::AUDIENCES[$item] ?? $item,
                explode(',', $value)
            )),
            'starts_on' => self::dateLabel($value),
            default => $value,
        };
    }

    private static function dateLabel(string $value): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? $value : $date->format('d/m/Y');
    }
}
