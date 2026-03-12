<?php

declare(strict_types=1);

namespace CurserPos\Domain\Sale;

use PDO;

class SaleRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findById(string $id): ?Sale
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, register_id, location_id, user_id, sale_number, subtotal, discount_amount, tax_amount, total, status, created_at, updated_at FROM sales WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    public function generateSaleNumber(): string
    {
        $prefix = 'S' . date('Ymd');
        $stmt = $this->pdo->prepare('SELECT sale_number FROM sales WHERE sale_number LIKE ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$prefix . '%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return $prefix . '0001';
        }
        $num = (int) substr((string) $row['sale_number'], -4);
        return $prefix . str_pad((string) ($num + 1), 4, '0', STR_PAD_LEFT);
    }

    public function create(?string $registerId, ?string $locationId, string $userId, float $subtotal, float $discountAmount, float $taxAmount, float $total): string
    {
        $id = $this->generateUuid();
        $saleNumber = $this->generateSaleNumber();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO sales (id, register_id, location_id, user_id, sale_number, subtotal, discount_amount, tax_amount, total, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $registerId ?: null, $locationId ?: null, $userId, $saleNumber, $subtotal, $discountAmount, $taxAmount, $total, Sale::STATUS_COMPLETED, $now, $now]);
        return $id;
    }

    public function addSaleItem(string $saleId, ?string $itemId, ?string $consignorId, int $quantity, float $unitPrice, float $discountAmount, float $taxAmount, float $storeShare, float $consignorShare): string
    {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO sale_items (id, sale_id, item_id, consignor_id, quantity, unit_price, discount_amount, tax_amount, store_share, consignor_share, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $saleId, $itemId, $consignorId, $quantity, $unitPrice, $discountAmount, $taxAmount, $storeShare, $consignorShare, $now]);
        return $id;
    }

    public function voidSale(string $id): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE sales SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([Sale::STATUS_VOIDED, $now, $id]);
    }

    public function markRefunded(string $id): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE sales SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([Sale::STATUS_REFUNDED, $now, $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $dateFrom = null, ?string $dateTo = null, ?string $registerId = null, int $limit = 100): array
    {
        $sql = 'SELECT id, register_id, location_id, user_id, sale_number, subtotal, discount_amount, tax_amount, total, status, created_at FROM sales WHERE 1=1';
        $params = [];
        if ($dateFrom !== null) {
            $sql .= ' AND created_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $sql .= ' AND created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        if ($registerId !== null) {
            $sql .= ' AND register_id = ?';
            $params[] = $registerId;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSaleItems(string $saleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sale_id, item_id, consignor_id, quantity, unit_price, store_share, consignor_share FROM sale_items WHERE sale_id = ?'
        );
        $stmt->execute([$saleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSalesByConsignor(string $consignorId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.sale_number, s.total, s.status, s.created_at, COALESCE(SUM(si.consignor_share), 0) AS consignor_share
             FROM sales s
             JOIN sale_items si ON si.sale_id = s.id AND si.consignor_id = ?
             WHERE s.status = ?
             GROUP BY s.id, s.sale_number, s.total, s.status, s.created_at
             ORDER BY s.created_at DESC LIMIT ?'
        );
        $stmt->execute([$consignorId, Sale::STATUS_COMPLETED, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Sale
    {
        return new Sale(
            (string) $row['id'],
            isset($row['register_id']) && $row['register_id'] !== null ? (string) $row['register_id'] : null,
            isset($row['location_id']) && $row['location_id'] !== null ? (string) $row['location_id'] : null,
            (string) $row['user_id'],
            (string) $row['sale_number'],
            (float) $row['subtotal'],
            (float) $row['discount_amount'],
            (float) $row['tax_amount'],
            (float) $row['total'],
            (string) $row['status'],
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
