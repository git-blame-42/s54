<?php

namespace Lib;

use Core\Constants\Constants;
use Core\Database\Database;
use InvalidArgumentException;
use PDO;
use PDOStatement;

class Paginator
{
    private int $totalOfRegisters = 0;
    private int $totalOfPages = 0;
    private int $totalOfRegistersOfPage = 0;
    private int $offset = 0;
    private array $registers = [];

    public function __construct(
        private string $class,
        private int $page,
        private int $per_page,
        private string $table,
        private array $attributes,
        private array $conditions = [],
        private ?string $route = null,
        private ?string $order_by = null
    ) {
        $this->validateOrderBy();
        $this->loadTotals();
        $this->loadRegisters();
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->per_page;
    }

    public function totalOfRegistersOfPage(): int
    {
        return $this->totalOfRegistersOfPage;
    }

    public function totalOfRegisters(): int
    {
        return $this->totalOfRegisters;
    }

    public function totalOfPages(): int
    {
        return $this->totalOfPages;
    }

    public function previousPage(): int
    {
        return $this->page - 1;
    }

    public function nextPage(): int
    {
        return $this->page + 1;
    }

    public function hasPreviousPage(): bool
    {
        return $this->previousPage() >= 1;
    }

    public function hasNextPage(): bool
    {
        return $this->nextPage() <= $this->totalOfPages();
    }

    public function isPage(int $page): bool
    {
        return $this->page === $page;
    }

    public function entriesInfo(): string
    {
        $totalVisualizedBegin = $this->offset + 1;
        $totalVisualizedEnd = $totalVisualizedBegin + $this->totalOfRegistersOfPage - 1;
        return "Mostrando {$totalVisualizedBegin} - {$totalVisualizedEnd} de {$this->totalOfRegisters}";
    }

    public function registers(): array
    {
        return $this->registers;
    }

    public function renderPagesNavigation()
    {
        $paginator = $this;
        require Constants::rootPath()->join('app/views/paginator/_pages.phtml');
    }

    public function getRouteName(): string
    {
        return $this->route ?? "$this->table.paginate";
    }

    private function loadTotals(): void
    {
        $pdo = Database::getDatabaseConn();
        $sql = "SELECT COUNT(*) FROM {$this->table}" . $this->buildConditions();

        $stmt = $pdo->prepare($sql);
        $this->bindConditions($stmt);
        $stmt->execute();

        $this->totalOfRegisters = $stmt->fetchColumn();
        $this->totalOfPages = ceil($this->totalOfRegisters / $this->per_page);
    }

    private function loadRegisters(): void
    {
        $this->registers = [];
        $this->offset = $this->per_page * ($this->page - 1);

        $attributes = implode(', ', $this->attributes);

        $sql = <<<SQL
            SELECT id, {$attributes} FROM {$this->table}
            {$this->buildConditions()}
            {$this->buildOrderBy()}
            LIMIT :limit OFFSET :offset
        SQL;

        $pdo = Database::getDatabaseConn();
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue('limit', $this->per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $this->offset, PDO::PARAM_INT);

        $this->bindConditions($stmt);

        $stmt->execute();
        $resp = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->totalOfRegistersOfPage = $stmt->rowCount();

        foreach ($resp as $row) {
            $this->registers[] = new $this->class($row);
        }
    }

    private function buildConditions(): string
    {
        if (empty($this->conditions)) {
            return '';
        }

        $sqlConditions = array_map(function ($column) {
            return "{$column} = :{$column}";
        }, array_keys($this->conditions));

        return ' WHERE ' . implode(' AND ', $sqlConditions);
    }

    /**
     * A coluna entra direto no SQL (não dá para usar bind em ORDER BY);
     * por isso só aceitamos `id` e as colunas declaradas no model.
     */
    private function validateOrderBy(): void
    {
        if ($this->order_by === null) {
            return;
        }

        if (!in_array($this->order_by, ['id', ...$this->attributes], true)) {
            throw new InvalidArgumentException("Coluna de ordenação inválida: {$this->order_by}");
        }
    }

    /**
     * O `id` desempata nomes iguais, para que a mesma linha não apareça em duas páginas.
     */
    private function buildOrderBy(): string
    {
        if ($this->order_by === null) {
            return '';
        }

        return "ORDER BY {$this->order_by}, id";
    }

    private function bindConditions(PDOStatement $stmt): void
    {
        if (empty($this->conditions)) {
            return;
        }

        foreach ($this->conditions as $column => $value) {
            $stmt->bindValue($column, $value);
        }
    }
}
