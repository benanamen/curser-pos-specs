<?php

declare(strict_types=1);

namespace CurserPos\Domain\Register;

use PDO;

final class RegisterRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findById(string $id): ?Register
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, location_id, register_id, assigned_user_id, status, opening_cash, closing_cash, opened_at, closed_at, created_at, updated_at FROM registers WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    /**
     * @return list<Register>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, location_id, register_id, assigned_user_id, status, opening_cash, closing_cash, opened_at, closed_at, created_at, updated_at FROM registers ORDER BY register_id'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn (array $r) => $this->hydrate($r), $rows);
    }

    public function create(string $locationId, string $registerId): string
    {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO registers (id, location_id, register_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $locationId, $registerId, Register::STATUS_CLOSED, $now, $now]);
        return $id;
    }

    public function open(string $id, string $userId, float $openingCash = 0): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE registers SET status = ?, assigned_user_id = ?, opening_cash = ?, opened_at = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([Register::STATUS_OPEN, $userId, $openingCash, $now, $now, $id]);
    }

    public function close(string $id, float $closingCash): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE registers SET status = ?, closing_cash = ?, closed_at = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([Register::STATUS_CLOSED, $closingCash, $now, $now, $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Register
    {
        $openedAt = isset($row['opened_at']) && $row['opened_at'] !== null ? new \DateTimeImmutable((string) $row['opened_at']) : null;
        $closedAt = isset($row['closed_at']) && $row['closed_at'] !== null ? new \DateTimeImmutable((string) $row['closed_at']) : null;
        return new Register(
            (string) $row['id'],
            (string) $row['location_id'],
            (string) $row['register_id'],
            isset($row['assigned_user_id']) && $row['assigned_user_id'] !== null ? (string) $row['assigned_user_id'] : null,
            (string) $row['status'],
            (float) ($row['opening_cash'] ?? 0),
            isset($row['closing_cash']) && $row['closing_cash'] !== null ? (float) $row['closing_cash'] : null,
            $openedAt,
            $closedAt,
            new \DateTimeImmutable((string) $row['created_at']),
            new \DateTimeImmutable((string) $row['updated_at'])
        );
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
