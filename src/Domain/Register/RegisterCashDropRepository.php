<?php

declare(strict_types=1);

namespace CurserPos\Domain\Register;

use PDO;

final class RegisterCashDropRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function record(string $registerId, float $amount): string
    {
        $id = $this->generateUuid();
        $stmt = $this->pdo->prepare('INSERT INTO register_cash_drops (id, register_id, amount, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$id, $registerId, $amount, (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
        return $id;
    }

    public function totalByRegisterSince(string $registerId, string $since): float
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM register_cash_drops WHERE register_id = ? AND created_at >= ?');
        $stmt->execute([$registerId, $since]);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row !== false ? (float) $row[0] : 0.0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByRegister(string $registerId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT id, register_id, amount, created_at FROM register_cash_drops WHERE register_id = ? ORDER BY created_at DESC LIMIT ?');
        $stmt->execute([$registerId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
