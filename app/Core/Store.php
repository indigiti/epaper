<?php
namespace Digiti\Core;

final class Store
{
    public static function all(string $sql, array $params = []): array
    {
        $st = Database::pdo()->prepare($sql); $st->execute($params);
        return array_map([self::class,'decodeRow'], $st->fetchAll());
    }
    public static function one(string $sql, array $params = []): ?array
    {
        $st = Database::pdo()->prepare($sql); $st->execute($params); $row = $st->fetch();
        return $row ? self::decodeRow($row) : null;
    }
    public static function value(string $sql, array $params = []): mixed
    {
        $st = Database::pdo()->prepare($sql); $st->execute($params); return $st->fetchColumn();
    }
    public static function exec(string $sql, array $params = []): int
    {
        $st = Database::pdo()->prepare($sql); $st->execute($params); return $st->rowCount();
    }
    public static function tx(callable $fn): mixed
    {
        $pdo = Database::pdo(); $pdo->beginTransaction();
        try { $result = $fn($pdo); $pdo->commit(); return $result; }
        catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    public static function decodeRow(array $row): array
    {
        foreach ($row as $k=>$v) {
            if ($v !== null && str_ends_with($k, '_guid') && is_string($v) && strlen($v) === 16) $row[$k] = Uuid::fromBinary($v);
        }
        return $row;
    }
}
