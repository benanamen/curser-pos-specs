<?php

declare(strict_types=1);

namespace CurserPos\Domain\Payout;

use PDO;

final class PayoutRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * @param array<string, mixed>|null $methodMetadata ACH/bank metadata (e.g. last4, bank_name, batch_id)
     */
    public function create(string $consignorId, float $amount, string $method, ?string $reference = null, ?array $methodMetadata = null): string
    {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $metaJson = $methodMetadata !== null ? json_encode($methodMetadata, JSON_THROW_ON_ERROR) : null;
        $stmt = $this->pdo->prepare(
            'INSERT INTO payouts (id, consignor_id, amount, method, status, reference, method_metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, ?::jsonb, ?)'
        );
        $stmt->execute([$id, $consignorId, $amount, $method, 'pending', $reference, $metaJson, $now]);
        return $id;
    }

    public function markProcessed(string $id): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE payouts SET status = ?, processed_at = ? WHERE id = ?');
        $stmt->execute(['processed', $now, $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByConsignor(string $consignorId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, consignor_id, amount, method, status, reference, method_metadata, created_at, processed_at FROM payouts WHERE consignor_id = ? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->execute([$consignorId, $limit]);
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
