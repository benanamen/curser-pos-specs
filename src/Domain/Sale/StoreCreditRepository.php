<?php

declare(strict_types=1);

namespace CurserPos\Domain\Sale;

use PDO;

class StoreCreditRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, consignor_id, balance, status FROM store_credits WHERE id = ? AND status = ?');
        $stmt->execute([$id, 'active']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getByConsignorId(string $consignorId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, consignor_id, balance, status FROM store_credits WHERE consignor_id = ? AND status = ?');
        $stmt->execute([$consignorId, 'active']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(?string $consignorId, float $initialBalance): string
    {
        $id = $this->generateUuid();
        $stmt = $this->pdo->prepare('INSERT INTO store_credits (id, consignor_id, balance, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt->execute([$id, $consignorId, $initialBalance, 'active', $now, $now]);
        return $id;
    }

    public function deduct(string $id, float $amount): void
    {
        $stmt = $this->pdo->prepare('UPDATE store_credits SET balance = balance - ?, updated_at = ? WHERE id = ? AND balance >= ?');
        $stmt->execute([$amount, (new \DateTimeImmutable())->format('Y-m-d H:i:s'), $id, $amount]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Insufficient store credit balance or not found');
        }
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
