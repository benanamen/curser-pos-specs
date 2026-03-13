<?php

declare(strict_types=1);

namespace CurserPos\Domain\Plan;

use PDO;

class PlanRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * @return list<array{id: string, name: string, tier: string, item_limit: int, features: array<string, mixed>}>
     */
    public function list(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, tier, item_limit, features FROM plans ORDER BY item_limit ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $features = is_string($row['features'] ?? '{}')
                ? json_decode($row['features'], true)
                : ($row['features'] ?? []);
            $result[] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'tier' => (string) $row['tier'],
                'item_limit' => (int) $row['item_limit'],
                'features' => is_array($features) ? $features : [],
            ];
        }
        return $result;
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, tier, item_limit, features FROM plans WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $features = is_string($row['features'] ?? '{}')
            ? json_decode($row['features'], true)
            : ($row['features'] ?? []);
        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'tier' => (string) $row['tier'],
            'item_limit' => (int) $row['item_limit'],
            'features' => is_array($features) ? $features : [],
        ];
    }
}
