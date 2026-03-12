<?php

declare(strict_types=1);

namespace CurserPos\Domain\Sale;

use PDO;

class HeldSaleRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function create(string $userId, array $cartData): string
    {
        $id = $this->generateUuid();
        $json = json_encode($cartData, JSON_THROW_ON_ERROR);
        $stmt = $this->pdo->prepare('INSERT INTO held_sales (id, cart_data, user_id, created_at) VALUES (?, ?::jsonb, ?, ?)');
        $stmt->execute([$id, $json, $userId, (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
        return $id;
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, cart_data, user_id, created_at FROM held_sales WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row['cart_data'] = is_string($row['cart_data']) ? json_decode($row['cart_data'], true) : $row['cart_data'];
        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByUser(string $userId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare('SELECT id, cart_data, user_id, created_at FROM held_sales WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
        $stmt->execute([$userId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['cart_data'] = is_string($row['cart_data']) ? json_decode($row['cart_data'], true) : $row['cart_data'];
        }
        return $rows;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM held_sales WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
