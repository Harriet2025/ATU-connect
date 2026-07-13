<?php
namespace Core;

use PDO;

// =========================================================
// BASE MODEL — shared PDO query helpers for all models
// =========================================================
abstract class BaseModel
{
    protected static string $table;
    protected static string $primaryKey = 'id';

    protected static function db(): PDO
    {
        return Database::connect();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM ' . static::$table . ' WHERE ' . static::$primaryKey . ' = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function all(string $orderBy = null): array
    {
        $sql = 'SELECT * FROM ' . static::$table;
        if ($orderBy) {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        return self::db()->query($sql)->fetchAll();
    }

    public static function where(array $conditions, string $orderBy = null): array
    {
        $clauses = [];
        foreach (array_keys($conditions) as $col) {
            $clauses[] = "$col = :$col";
        }
        $sql = 'SELECT * FROM ' . static::$table . ' WHERE ' . implode(' AND ', $clauses);
        if ($orderBy) {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        $stmt = self::db()->prepare($sql);
        $stmt->execute($conditions);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = 'INSERT INTO ' . static::$table . " ($columns) VALUES ($placeholders)";
        $stmt = self::db()->prepare($sql);
        $stmt->execute($data);
        return (int) self::db()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $assignments = implode(', ', array_map(fn($col) => "$col = :$col", array_keys($data)));
        $sql = 'UPDATE ' . static::$table . " SET $assignments WHERE " . static::$primaryKey . ' = :__id';
        $data['__id'] = $id;
        $stmt = self::db()->prepare($sql);
        return $stmt->execute($data);
    }

    public static function delete(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM ' . static::$table . ' WHERE ' . static::$primaryKey . ' = :id');
        return $stmt->execute(['id' => $id]);
    }
}
