<?php

declare(strict_types=1);

namespace CurserPos\Domain\Booth;

use PDO;

final class RentDeductionRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function getLastDeductionDate(string $consignorId): ?\DateTimeImmutable
    {
        $stmt = $this->pdo->prepare(
            'SELECT period_end FROM rent_deductions WHERE consignor_id = ? ORDER BY period_end DESC LIMIT 1'
        );
        $stmt->execute([$consignorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false && isset($row['period_end']) && $row['period_end'] !== '' ? new \DateTimeImmutable((string) $row['period_end']) : null;
    }

    public function record(string $consignorId, float $amount, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd, ?string $payoutId = null): string
    {
        $id = $this->generateUuid();
        $stmt = $this->pdo->prepare(
            'INSERT INTO rent_deductions (id, consignor_id, amount, period_start, period_end, payout_id, created_at) VALUES (?, ?, ?, ?::date, ?::date, ?, ?)'
        );
        $stmt->execute([
            $id,
            $consignorId,
            $amount,
            $periodStart->format('Y-m-d'),
            $periodEnd->format('Y-m-d'),
            $payoutId,
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByConsignor(string $consignorId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, consignor_id, amount, period_start, period_end, payout_id, created_at FROM rent_deductions WHERE consignor_id = ? ORDER BY period_end DESC LIMIT ?'
        );
        $stmt->execute([$consignorId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sum rent collected in date range (for reporting).
     */
    public function sumCollected(?string $dateFrom = null, ?string $dateTo = null): float
    {
        $sql = 'SELECT COALESCE(SUM(amount), 0) FROM rent_deductions WHERE 1=1';
        $params = [];
        if ($dateFrom !== null) {
            $sql .= ' AND period_end >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $sql .= ' AND period_start <= ?';
            $params[] = $dateTo;
        }
        $stmt = $params === [] ? $this->pdo->query($sql) : $this->pdo->prepare($sql);
        if ($params !== []) {
            $stmt->execute($params);
        }
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row !== false ? (float) $row[0] : 0.0;
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
