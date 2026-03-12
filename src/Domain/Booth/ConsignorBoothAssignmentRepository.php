<?php

declare(strict_types=1);

namespace CurserPos\Domain\Booth;

use PDO;

class ConsignorBoothAssignmentRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function getActiveByConsignorId(string $consignorId): ?ConsignorBoothAssignment
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, consignor_id, booth_id, started_at, ended_at, monthly_rent, created_at, updated_at
             FROM consignor_booth_assignments WHERE consignor_id = ? AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1'
        );
        $stmt->execute([$consignorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    /**
     * @return list<ConsignorBoothAssignment>
     */
    public function getByConsignorId(string $consignorId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, consignor_id, booth_id, started_at, ended_at, monthly_rent, created_at, updated_at
             FROM consignor_booth_assignments WHERE consignor_id = ? ORDER BY started_at DESC'
        );
        $stmt->execute([$consignorId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn (array $r) => $this->hydrate($r), $rows);
    }

    /**
     * @return list<ConsignorBoothAssignment>
     */
    public function getActiveByBoothId(string $boothId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, consignor_id, booth_id, started_at, ended_at, monthly_rent, created_at, updated_at
             FROM consignor_booth_assignments WHERE booth_id = ? AND ended_at IS NULL ORDER BY started_at DESC'
        );
        $stmt->execute([$boothId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn (array $r) => $this->hydrate($r), $rows);
    }

    public function assign(string $consignorId, string $boothId, float $monthlyRent, \DateTimeImmutable $startedAt): string
    {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $started = $startedAt->format('Y-m-d');
        $stmt = $this->pdo->prepare(
            'INSERT INTO consignor_booth_assignments (id, consignor_id, booth_id, started_at, monthly_rent, created_at, updated_at) VALUES (?, ?, ?, ?::date, ?, ?, ?)'
        );
        $stmt->execute([$id, $consignorId, $boothId, $started, $monthlyRent, $now, $now]);
        return $id;
    }

    public function endAssignment(string $consignorId, \DateTimeImmutable $endedAt): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $ended = $endedAt->format('Y-m-d');
        $stmt = $this->pdo->prepare(
            'UPDATE consignor_booth_assignments SET ended_at = ?::date, updated_at = ? WHERE consignor_id = ? AND ended_at IS NULL'
        );
        $stmt->execute([$ended, $now, $consignorId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ConsignorBoothAssignment
    {
        return new ConsignorBoothAssignment(
            (string) $row['id'],
            (string) $row['consignor_id'],
            (string) $row['booth_id'],
            new \DateTimeImmutable((string) $row['started_at']),
            isset($row['ended_at']) && $row['ended_at'] !== null && $row['ended_at'] !== '' ? new \DateTimeImmutable((string) $row['ended_at']) : null,
            (float) $row['monthly_rent'],
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
