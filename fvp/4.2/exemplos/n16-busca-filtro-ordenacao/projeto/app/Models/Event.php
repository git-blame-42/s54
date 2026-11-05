<?php

namespace App\Models;

use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\Model;
use Lib\Paginator;
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
 *
 * @phpstan-type ListingFilters array{q: string, kind: ?string, sort: string, dir: string}
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

    /** Ordens da listagem (`?sort=`), com o rótulo do formulário; 'id' (cadastro) é o padrão. */
    public const SORTS = [
        'id' => 'Cadastro',
        'title' => 'Título',
        'starts_on' => 'Data',
    ];

    /** Direções da listagem (`?dir=`), com o rótulo do formulário; 'asc' é o padrão. */
    public const DIRS = [
        'asc' => 'Crescente',
        'desc' => 'Decrescente',
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
     * Lê os parâmetros da listagem (q, kind, sort, dir) como chegaram da requisição.
     *
     * Ausente ou vazio: sem busca, todos os tipos, ordem de cadastro, crescente. Inválido: não some
     * calado; ganha uma mensagem na chave do parâmetro e o valor padrão no lugar.
     *
     * @param array<mixed> $params
     * @return array{0: ListingFilters, 1: array<string, string>}
     */
    public static function listingFilters(array $params): array
    {
        $errors = [];

        $q = $params['q'] ?? null;
        $q = is_string($q) ? trim($q) : $q;
        $qValid = $q === null || (is_string($q) && mb_strlen($q) <= 255);
        if (!$qValid) {
            $errors['q'] = 'Busca inválida (até 255 caracteres).';
        }

        $kind = self::choice($params['kind'] ?? null, self::KINDS);
        $sort = self::choice($params['sort'] ?? null, self::SORTS);
        $dir = self::choice($params['dir'] ?? null, self::DIRS);
        $checks = [
            'kind' => [$kind, 'Tipo inválido.'],
            'sort' => [$sort, 'Ordenação inválida.'],
            'dir' => [$dir, 'Direção inválida.'],
        ];
        foreach ($checks as $param => [$value, $message]) {
            if ($value === false) {
                $errors[$param] = $message;
            }
        }

        $filters = [
            'q' => $qValid && is_string($q) ? $q : '',
            'kind' => is_string($kind) ? $kind : null,
            'sort' => is_string($sort) ? $sort : 'id',
            'dir' => is_string($dir) ? $dir : 'asc',
        ];

        return [$filters, $errors];
    }

    /**
     * Uma página da listagem com busca por título, filtro por tipo e ordenação, tudo junto.
     *
     * O WHERE e o ORDER BY são montados aqui; o Paginator usa o mesmo WHERE (e os mesmos valores)
     * no COUNT e na consulta da página, que recebe o ORDER BY e o LIMIT/OFFSET.
     *
     * @param ListingFilters $filters valores lidos por listingFilters()
     */
    public static function listingPage(array $filters, int $page, int $perPage, string $route): Paginator
    {
        $where = '';
        $binds = [];
        if ($filters['q'] !== '') {
            // '!' escapa os curingas do LIKE: "100%" procura o texto "100%", não "100" seguido de qualquer coisa.
            $term = strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']);
            $where = "title LIKE :q ESCAPE '!'";
            $binds['q'] = "%{$term}%";
        }

        // Nome de coluna não aceita bind: só entra o que está na lista. O id desempata valores iguais.
        $column = array_key_exists($filters['sort'], self::SORTS) ? $filters['sort'] : 'id';
        $dir = $filters['dir'] === 'desc' ? 'DESC' : 'ASC';
        $order = $column === 'id' ? "id {$dir}" : "{$column} {$dir}, id ASC";
        $kind = $filters['kind'];

        return new Paginator(
            class: static::class,
            page: $page,
            per_page: $perPage,
            table: static::$table,
            attributes: static::$columns,
            conditions: $kind !== null ? ['kind' => $kind] : [],
            route: $route,
            where: $where,
            binds: $binds,
            orderBy: $order,
            queryParams: self::listingQuery($filters),
        );
    }

    /**
     * Query string dos links de página: só o que difere do padrão.
     *
     * @param ListingFilters $filters
     * @return array<string, string>
     */
    public static function listingQuery(array $filters): array
    {
        return array_filter([
            'q' => $filters['q'],
            'kind' => $filters['kind'] ?? '',
            'sort' => $filters['sort'] === 'id' ? '' : $filters['sort'],
            'dir' => $filters['dir'] === 'asc' ? '' : $filters['dir'],
        ], fn(string $value) => $value !== '');
    }

    /**
     * Valor de uma lista fechada: null se ausente ou vazio, false se inválido.
     *
     * @param array<string, string> $allowed
     */
    private static function choice(mixed $value, array $allowed): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) && array_key_exists($value, $allowed) ? $value : false;
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
