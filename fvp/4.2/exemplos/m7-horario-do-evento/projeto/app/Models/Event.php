<?php

namespace App\Models;

use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\Model;
use Lib\Validations;
use PDOException;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $description
 * @property string $kind
 * @property string $modality
 * @property string|null $audience valores separados por vírgula (tipo SET do MySQL); use audienceList()
 * @property string $starts_at data e hora no formato Y-m-d H:i (o __set normaliza)
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
        'starts_at',
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

        Validations::notEmpty('starts_at', $this) &&
            Validations::validDate('starts_at', $this, 'Y-m-d H:i') &&
            $this->validatesStartsAtNotPast();

        if (Validations::notEmpty('capacity', $this) && Validations::integerMin('capacity', $this, 1)) {
            $this->validatesCapacityAgainstEnrollments();
        }
    }

    /**
     * Normaliza `starts_at` para Y-m-d H:i. O `<input type="datetime-local">` envia "2030-05-10T14:30"
     * e o MySQL devolve a coluna DATETIME como "2030-05-10 14:30:00"; os dois viram "2030-05-10 14:30".
     * Valor fora desses formatos fica como veio, para a validação rejeitar.
     */
    public function __set(string $property, mixed $value): void
    {
        if ($property === 'starts_at' && is_string($value)) {
            $value = preg_replace('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(:\d{2})?$/', '$1 $2', $value);
        }

        parent::__set($property, $value);
    }

    /**
     * Data e hora não podem estar no passado ao criar (comparação por minuto: um horário de hoje que já
     * passou é rejeitado). Na edição, só é cobrada quando `starts_at` muda: assim um Evento que já
     * aconteceu ainda pode ter a descrição, o local etc. corrigidos.
     */
    private function validatesStartsAtNotPast(): void
    {
        if (!$this->newRecord()) {
            $original = self::findById($this->id);

            if ($original !== null && $original->starts_at === $this->starts_at) {
                return;
            }
        }

        // Os dois lados estão em Y-m-d H:i (já passou pelo validDate), então a comparação de strings é cronológica.
        if ($this->starts_at < date('Y-m-d H:i')) {
            $this->addError('starts_at', 'não pode estar no passado!');
        }
    }

    /**
     * Valor para o `<input type="datetime-local">` (Y-m-d\TH:i). Mantém o texto digitado se for inválido.
     */
    public function startsAtInputValue(): string
    {
        return (string) preg_replace('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})$/', '$1T$2', (string) $this->starts_at);
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
     * retorna false e registra o motivo em errors('base').
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
