<?php
declare(strict_types=1);

namespace App\Infra\Persistence\MariaDB;

/**
 * Query Builder
 * Provides fluent interface for building SQL queries safely
 */
class QueryBuilder
{
    private Database $database;
    private string $type;
    private string $table;
    private array $columns = [];
    private array $values = [];
    private array $wheres = [];
    private array $joins = [];
    private array $orderBy = [];
    private array $groupBy = [];
    private ?string $having = null;
    private ?int $limit = null;
    private ?int $offset = null;
    private array $parameters = [];
    
    public function __construct(Database $database, string $type, string $table)
    {
        $this->database = $database;
        $this->type = $type;
        $this->table = $table;
    }
    
    public function select(array|string $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : [$columns];
        return $this;
    }
    
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $placeholder = ':where_' . count($this->wheres);
        $this->wheres[] = "{$column} {$operator} {$placeholder}";
        $this->parameters[$placeholder] = $value;
        
        return $this;
    }
    
    public function whereIn(string $column, array $values): self
    {
        $placeholders = [];
        foreach ($values as $i => $value) {
            $placeholder = ':where_in_' . $i;
            $placeholders[] = $placeholder;
            $this->parameters[$placeholder] = $value;
        }
        
        $this->wheres[] = "{$column} IN (" . implode(', ', $placeholders) . ")";
        return $this;
    }
    
    public function whereNull(string $column): self
    {
        $this->wheres[] = "{$column} IS NULL";
        return $this;
    }
    
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = "{$column} IS NOT NULL";
        return $this;
    }
    
    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = "INNER JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }
    
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = "LEFT JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }
    
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = "{$column} {$direction}";
        return $this;
    }
    
    public function groupBy(array|string $columns): self
    {
        $this->groupBy = is_array($columns) ? $columns : [$columns];
        return $this;
    }
    
    public function having(string $condition): self
    {
        $this->having = $condition;
        return $this;
    }
    
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }
    
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }
    
    public function values(array $data): self
    {
        $this->values = $data;
        return $this;
    }
    
    public function set(array $data): self
    {
        return $this->values($data);
    }
    
    public function toSQL(): string
    {
        switch ($this->type) {
            case 'SELECT':
                return $this->buildSelect();
            case 'INSERT':
                return $this->buildInsert();
            case 'UPDATE':
                return $this->buildUpdate();
            case 'DELETE':
                return $this->buildDelete();
            default:
                throw new \InvalidArgumentException("Unsupported query type: {$this->type}");
        }
    }
    
    public function execute(): \PDOStatement
    {
        return $this->database->execute($this->toSQL(), $this->parameters);
    }
    
    public function get(): array
    {
        $stmt = $this->execute();
        return $stmt->fetchAll();
    }
    
    public function first(): ?array
    {
        $this->limit(1);
        $stmt = $this->execute();
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function count(): int
    {
        $originalColumns = $this->columns;
        $this->columns = ['COUNT(*) as count'];
        
        $stmt = $this->execute();
        $result = $stmt->fetch();
        
        $this->columns = $originalColumns;
        return (int) ($result['count'] ?? 0);
    }
    
    private function buildSelect(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns);
        $sql .= " FROM {$this->table}";
        
        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        
        if ($this->having !== null) {
            $sql .= ' HAVING ' . $this->having;
        }
        
        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }
        
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        
        return $sql;
    }
    
    private function buildInsert(): string
    {
        if (empty($this->values)) {
            throw new \InvalidArgumentException('No values provided for INSERT');
        }
        
        $columns = array_keys($this->values);
        $placeholders = [];
        
        foreach ($this->values as $column => $value) {
            $placeholder = ':insert_' . $column;
            $placeholders[] = $placeholder;
            $this->parameters[$placeholder] = $value;
        }
        
        $sql = "INSERT INTO {$this->table}";
        $sql .= ' (' . implode(', ', $columns) . ')';
        $sql .= ' VALUES (' . implode(', ', $placeholders) . ')';
        
        return $sql;
    }
    
    private function buildUpdate(): string
    {
        if (empty($this->values)) {
            throw new \InvalidArgumentException('No values provided for UPDATE');
        }
        
        $sets = [];
        foreach ($this->values as $column => $value) {
            $placeholder = ':update_' . $column;
            $sets[] = "{$column} = {$placeholder}";
            $this->parameters[$placeholder] = $value;
        }
        
        $sql = "UPDATE {$this->table}";
        $sql .= ' SET ' . implode(', ', $sets);
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        
        return $sql;
    }
    
    private function buildDelete(): string
    {
        $sql = "DELETE FROM {$this->table}";
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        
        return $sql;
    }
}
