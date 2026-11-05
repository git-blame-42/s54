<?php

namespace Lib;

use Core\Constants\Constants;
use Core\Database\Database;
use PDO;
use PDOStatement;

class Paginator
{
    private int $totalOfRegisters = 0;
    private int $totalOfPages = 0;
    private int $totalOfRegistersOfPage = 0;
    private int $offset = 0;
    private array $registers = [];

    /**
     * @param array<string, mixed> $conditions filtros por igualdade (WHERE coluna = valor)
     * @param string $where condição SQL extra, com placeholders nomeados, somada às de $conditions com AND
     * @param array<string, mixed> $binds valores dos placeholders de $where
     * @param string|null $orderBy cláusula do ORDER BY; quem chama monta a partir de uma lista fechada
     * @param array<string, string> $queryParams parâmetros mantidos na query string dos links de página
     */
    public function __construct(
        private string $class,
        private int $page,
        private int $per_page,
        private string $table,
        private array $attributes,
        private array $conditions = [],
        private ?string $route = null,
        private string $where = '',
        private array $binds = [],
        private ?string $orderBy = null,
        private array $queryParams = []
    ) {
        $this->loadTotals();
        // Página além da última (ex.: depois de filtrar) vira a última; sem registros, a 1.
        $this->page = max(1, min($this->page, $this->totalOfPages));
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

    /**
     * Parâmetros que os links de página (`_pages.phtml`) mantêm na query string.
     *
     * @return array<string, string>
     */
    public function queryParams(): array
    {
        return $this->queryParams;
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
        $order = $this->orderBy !== null ? "ORDER BY {$this->orderBy}" : '';

        $sql = <<<SQL
            SELECT id, {$attributes} FROM {$this->table}
            {$this->buildConditions()}
            {$order}
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

    /**
     * O mesmo WHERE serve ao COUNT (loadTotals) e à página (loadRegisters).
     */
    private function buildConditions(): string
    {
        $parts = array_map(
            fn($column) => "{$column} = :{$column}",
            array_keys($this->conditions)
        );
        if ($this->where !== '') {
            $parts[] = "({$this->where})";
        }

        return $parts === [] ? '' : ' WHERE ' . implode(' AND ', $parts);
    }

    private function bindConditions(PDOStatement $stmt): void
    {
        foreach ($this->conditions + $this->binds as $name => $value) {
            $stmt->bindValue($name, $value);
        }
    }
}
