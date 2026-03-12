<?php

declare(strict_types=1);

namespace CurserPos\Domain\Sale;

use PDO;

class GiftCardRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, code, balance, status FROM gift_cards WHERE code = ? AND status = ?');
        $stmt->execute([trim($code), 'active']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, code, balance, status FROM gift_cards WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function create(string $code, float $initialBalance): string
    {
        $id = $this->generateUuid();
        $stmt = $this->pdo->prepare('INSERT INTO gift_cards (id, code, balance, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt->execute([$id, trim($code), $initialBalance, 'active', $now, $now]);
        return $id;
    }

    public function deduct(string $id, float $amount): void
    {
        $stmt = $this->pdo->prepare('UPDATE gift_cards SET balance = balance - ?, updated_at = ? WHERE id = ? AND balance >= ?');
        $stmt->execute([$amount, (new \DateTimeImmutable())->format('Y-m-d H:i:s'), $id, $amount]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Insufficient gift card balance or not found');
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
