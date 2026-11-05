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
 * @property string $starts_on
 * @property string $location
 * @property int|string $capacity
 * @property int|string $price DECIMAL(8,2): o banco devolve "10.50"; o formulário pode mandar "10,50"
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
        'price',
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

        if (Validations::notEmpty('price', $this) && Validations::decimal('price', $this, 6, 2)) {
            // "10,50" vira "10.50": em DECIMAL o MySQL só aceita ponto ("10,50" daria erro no INSERT).
            $this->price = str_replace(',', '.', (string) $this->price);
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
     * Preço para exibir: "Gratuito" quando é 0; senão "R$ 1.234,50" (milhar com ponto, centavos com vírgula).
     */
    public function formattedPrice(): string
    {
        $price = str_replace(',', '.', (string) $this->price);

        if (!is_numeric($price)) {
            return '';
        }

        if ((float) $price === 0.0) {
            return 'Gratuito';
        }

        return 'R$ ' . number_format((float) $price, 2, ',', '.');
    }

    /**
     * Valor do campo no formulário: "10.50" (banco ou já validado) aparece como "10,50".
     * Vazio ou inválido (inclusive acima do limite, como "1000000") volta como foi digitado.
     */
    public function priceForInput(): string
    {
        $price = (string) $this->price;

        if (preg_match('/^\d{1,6}(\.\d{1,2})?$/D', $price) !== 1) {
            return $price;
        }

        return number_format((float) $price, 2, ',', '');
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
