<?php

declare(strict_types=1);

namespace CurserPos\Domain\Sale;

use PDO;

class PaymentRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * @return list<array{id: string, method: string, amount: float, reference: string|null}>
     */
    public function getBySaleId(string $saleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, method, amount, reference FROM payments WHERE sale_id = ? AND amount > 0 ORDER BY created_at'
        );
        $stmt->execute([$saleId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (string) $row['id'],
                'method' => (string) $row['method'],
                'amount' => (float) $row['amount'],
                'reference' => isset($row['reference']) && $row['reference'] !== '' && $row['reference'] !== null ? (string) $row['reference'] : null,
            ];
        }
        return $out;
    }

    public function addPayment(string $saleId, string $method, float $amount, ?string $reference = null, ?string $refundOfId = null): string
    {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO payments (id, sale_id, method, amount, reference, status, refund_of_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $saleId, $method, $amount, $reference, 'completed', $refundOfId, $now]);
        return $id;
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
