<?php

namespace Core\Database\ActiveRecord;

use Core\Database\Database;
use InvalidArgumentException;
use Lib\Paginator;
use PDO;

/**
 * Query Builder mínimo: Model::query()->where(...)->orderBy(...)->get() ou ->paginate(...).
 *
 * Valores sempre por bindValue (placeholders :p0, :p1, ...). Coluna, operador e direção não
 * aceitam bind, por isso só passam se estiverem nas listas fechadas abaixo.
 *
 * @template T of Model
 */
class QueryBuilder
{
    private const OPERATORS = ['=', '!=', '<', '<=', '>', '>=', 'LIKE'];
    private const DIRECTIONS = ['ASC', 'DESC'];

    /** @var list<array{0: string, 1: string, 2: string|int|float|bool}> coluna, operador, valor */
    private array $wheres = [];

    /** @var list<array{0: string, 1: string}> coluna, direção */
    private array $orders = [];

    /**
     * @param class-string<T> $class
     * @param array<int, string> $columns colunas do model (sem o id)
     */
    public function __construct(
        private string $class,
        private string $table,
        private array $columns
    ) {
    }

    /**
     * where('kind', 'lecture') é igualdade; where('starts_on', '>=', $data) usa o operador dado.
     *
     * @return static
     */
    public function where(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operatorOrValue;
            $operator = '=';
        } else {
            $operator = is_string($operatorOrValue) ? strtoupper($operatorOrValue) : '';
        }

        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Operador inválido: {$operator}");
        }

        if (!is_scalar($value)) {
            throw new InvalidArgumentException("Valor inválido para a coluna {$column}");
        }

        $this->wheres[] = [$this->checkColumn($column), $operator, $value];

        return $this;
    }

    /**
     * @return static
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction = strtoupper($direction);

        if (!in_array($direction, self::DIRECTIONS, true)) {
            throw new InvalidArgumentException("Direção inválida: {$direction}");
        }

        $this->orders[] = [$this->checkColumn($column), $direction];

        return $this;
    }

    /**
     * Condições sem a palavra WHERE: "kind = :p0 AND starts_on >= :p1" ('' se não houver).
     */
    public function whereSql(): string
    {
        $conditions = [];
        foreach ($this->wheres as $index => [$column, $operator]) {
            $conditions[] = "{$column} {$operator} :p{$index}";
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Ordem sem a palavra ORDER BY: "starts_on ASC, id ASC" ('' se não houver).
     */
    public function orderBySql(): string
    {
        $orders = array_map(fn(array $order) => "{$order[0]} {$order[1]}", $this->orders);

        return implode(', ', $orders);
    }

    /**
     * @return array<string, string|int|float|bool> placeholder => valor
     */
    public function bindings(): array
    {
        $bindings = [];
        foreach ($this->wheres as $index => $where) {
            $bindings[":p{$index}"] = $where[2];
        }

        return $bindings;
    }

    public function toSql(): string
    {
        $attributes = implode(', ', $this->columns);
        $sql = "SELECT id, {$attributes} FROM {$this->table}";

        if ($this->wheres !== []) {
            $sql .= ' WHERE ' . $this->whereSql();
        }

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . $this->orderBySql();
        }

        return $sql;
    }

    /**
     * @return array<T>
     */
    public function get(): array
    {
        $stmt = Database::getDatabaseConn()->prepare($this->toSql());

        foreach ($this->bindings() as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value);
        }

        $stmt->execute();

        $models = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $models[] = new $this->class($row);
        }

        return $models;
    }

    public function paginate(int $page = 1, int $perPage = 10, ?string $route = null): Paginator
    {
        $orderBy = $this->orderBySql();

        return new Paginator(
            class: $this->class,
            page: $page,
            per_page: $perPage,
            table: $this->table,
            attributes: $this->columns,
            route: $route,
            whereSql: $this->whereSql(),
            bindings: $this->bindings(),
            orderBy: $orderBy !== '' ? $orderBy : null
        );
    }

    private function checkColumn(string $column): string
    {
        if (!in_array($column, ['id', ...$this->columns], true)) {
            throw new InvalidArgumentException("Coluna inválida: {$column}");
        }

        return $column;
    }
}
