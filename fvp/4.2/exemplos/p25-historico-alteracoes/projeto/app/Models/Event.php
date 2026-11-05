<?php

namespace App\Models;

use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\Model;
use Lib\Validations;
use PDOException;
use RuntimeException;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $description
 * @property string $kind
 * @property string $modality
 * @property string|null $audience valores separados por vírgula (tipo SET do MySQL); use audienceList()
 * @property string $starts_on
 * @property string $location
 * @property int|string $capacity
 */
class Event extends Model
{
    /** Valores do ENUM `kind`, com o rótulo exibido nos formulários. */
    public const KINDS = [
        'lecture' => 'Palestra',
        'workshop' => 'Oficina',
        'short_course' => 'Minicurso',
        'academic_week' => 'Semana acadêmica',
    ];

    /** Valores do ENUM `modality`, com o rótulo exibido nos formulários. */
    public const MODALITIES = [
        'in_person' => 'Presencial',
        'online' => 'Online',
        'hybrid' => 'Híbrido',
    ];

    /** Valores do SET `audience`, com o rótulo exibido nos formulários. */
    public const AUDIENCES = [
        'students' => 'Estudantes',
        'professors' => 'Professores',
        'staff' => 'Servidores',
        'community' => 'Comunidade externa',
    ];

    protected static string $table = 'events';
    protected static array $columns = [
        'user_id',
        'title',
        'description',
        'kind',
        'modality',
        'audience',
        'starts_on',
        'location',
        'capacity',
    ];

    public function validates(): void
    {
        Validations::notEmpty('title', $this);
        Validations::notEmpty('description', $this);
        Validations::notEmpty('location', $this);

        Validations::notEmpty('kind', $this) &&
            Validations::inclusion('kind', $this, array_keys(self::KINDS));

        Validations::notEmpty('modality', $this) &&
            Validations::inclusion('modality', $this, array_keys(self::MODALITIES));

        Validations::notEmpty('audience', $this) &&
            Validations::inclusionOfAll('audience', $this, array_keys(self::AUDIENCES));

        Validations::notEmpty('starts_on', $this) &&
            Validations::validDate('starts_on', $this) &&
            $this->validatesStartsOnNotPast();

        if (Validations::notEmpty('capacity', $this) && Validations::integerMin('capacity', $this, 1)) {
            $this->validatesCapacityAgainstEnrollments();
        }
    }

    /**
     * A data não pode estar no passado ao criar. Na edição, só é cobrada quando `starts_on` muda:
     * assim um Evento que já aconteceu ainda pode ter a descrição, o local etc. corrigidos.
     */
    private function validatesStartsOnNotPast(): void
    {
        if (!$this->newRecord()) {
            $original = self::findById($this->id);

            if ($original !== null && $original->starts_on === $this->starts_on) {
                return;
            }
        }

        Validations::notPastDate('starts_on', $this);
    }

    /**
     * P02: na edição, a capacidade não pode ficar abaixo do total de Inscrições.
     */
    private function validatesCapacityAgainstEnrollments(): void
    {
        if ($this->newRecord()) {
            return;
        }

        $enrolled = $this->enrollmentsCount();

        if ((int) $this->capacity < $enrolled) {
            $this->addError('capacity', "não pode ser menor que o total de inscrições ({$enrolled})!");
        }
    }

    /**
     * Salva o Evento e registra no histórico uma linha por campo que mudou, com $userId
     * como autor. Sem autor não salva (o histórico sempre diz quem editou). Se nada
     * mudou, salva sem registrar nada. Retorna false se a validação falhar.
     */
    public function saveWithHistory(?int $userId): bool
    {
        if ($userId === null) {
            $this->addError('base', 'não é possível salvar sem saber quem editou!');
            return false;
        }

        // Antes do save(): depois dele o banco já tem os valores novos.
        $changes = $this->changedFields();

        if (!$this->save()) {
            return false;
        }

        foreach ($changes as $field => [$oldValue, $newValue]) {
            $change = new EventChange([
                'event_id' => $this->id,
                'user_id' => $userId,
                'field' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]);

            if (!$change->save()) {
                throw new RuntimeException("Não foi possível registrar a alteração de {$field}.");
            }
        }

        return true;
    }

    /**
     * Campos cujo valor difere do que está gravado no banco, no formato
     * [campo => [valor antigo, valor novo]]. O original é recarregado do banco UMA vez
     * (não se usa o que veio no request nem o que estava em memória). user_id fica de
     * fora: não é editado pelo formulário. Registro novo não tem alterações.
     *
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public function changedFields(): array
    {
        if ($this->newRecord()) {
            return [];
        }

        $original = self::findById($this->id);
        if ($original === null) {
            return [];
        }

        $changes = [];
        foreach (array_diff(static::$columns, ['user_id']) as $column) {
            $old = $original->$column;
            $new = $this->$column;

            if (self::comparableValue($column, $old) !== self::comparableValue($column, $new)) {
                $changes[$column] = [self::storableValue($old), self::storableValue($new)];
            }
        }

        return $changes;
    }

    /**
     * Histórico de alterações deste Evento, do mais recente para o mais antigo.
     *
     * @return array<EventChange>
     */
    public function history(): array
    {
        if ($this->newRecord()) {
            return [];
        }

        return EventChange::forEvent($this->id);
    }

    /**
     * Valor em forma comparável. O banco devolve capacity como int (20) e o formulário
     * como string ('20'); o SET volta na ordem da definição da coluna, não na ordem
     * enviada; o navegador envia as quebras de linha do textarea como \r\n.
     */
    private static function comparableValue(string $column, mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        if ($column === 'audience') {
            $values = explode(',', $value);
            sort($values);

            return implode(',', $values);
        }

        return str_replace("\r\n", "\n", $value);
    }

    private static function storableValue(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Total de Inscrições (linhas em event_user) deste Evento.
     */
    public function enrollmentsCount(): int
    {
        if ($this->newRecord()) {
            return 0;
        }

        return count(Enrollment::where(['event_id' => $this->id]));
    }

    /**
     * Vagas restantes (capacity - Inscrições), nunca negativo. Calculado, não armazenado.
     */
    public function availableSpots(): int
    {
        return max(0, (int) $this->capacity - $this->enrollmentsCount());
    }

    public function hasEnrollments(): bool
    {
        return $this->enrollmentsCount() > 0;
    }

    /**
     * Remove o Evento sem lançar exceção (P03): se houver Inscrições, não remove,
     * retorna false e registra o motivo em errors('base'). O histórico de alterações não
     * bloqueia: o banco o apaga junto (ON DELETE CASCADE em event_changes.event_id).
     */
    public function safeDestroy(): bool
    {
        $this->errors = [];

        if ($this->newRecord()) {
            $this->addError('base', 'o evento não existe e não pode ser removido!');
            return false;
        }

        $enrolled = $this->enrollmentsCount();
        if ($enrolled > 0) {
            $this->addError('base', "não é possível remover um evento com inscrições ({$enrolled})!");
            return false;
        }

        try {
            return $this->destroy();
        } catch (PDOException $e) {
            // FK RESTRICT: alguém se inscreveu entre a contagem e o DELETE.
            $this->addError('base', 'não foi possível remover o evento, pois ele possui inscrições!');
            return false;
        }
    }

    /**
     * Público-alvo como array (o banco guarda "students,professors").
     *
     * @return array<int, string>
     */
    public function audienceList(): array
    {
        $audience = $this->audience;

        if ($audience === null || $audience === '') {
            return [];
        }

        return explode(',', $audience);
    }

    /**
     * Define o público-alvo a partir de um array (ex.: checkboxes `audience[]`).
     * Remove duplicados e valores vazios; valores fora de AUDIENCES são mantidos
     * para que a validação os rejeite.
     *
     * @param array<int, mixed> $values
     */
    public function setAudienceList(array $values): void
    {
        $values = array_values(array_unique(array_filter(
            array_map('strval', $values),
            fn(string $value) => $value !== ''
        )));

        $this->audience = implode(',', $values);
    }

    public function hasAudience(string $value): bool
    {
        return in_array($value, $this->audienceList(), true);
    }
}
