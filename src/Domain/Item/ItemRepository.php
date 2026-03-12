<?php

declare(strict_types=1);

namespace CurserPos\Domain\Item;

use PDO;

class ItemRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findById(string $id): ?Item
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sku, barcode, consignor_id, category_id, location_id, description, size, condition,
                    price, store_share_pct, consignor_share_pct, status, intake_date, expiry_date, created_at, updated_at
             FROM items WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    public function findBySku(string $sku): ?Item
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sku, barcode, consignor_id, category_id, location_id, description, size, condition,
                    price, store_share_pct, consignor_share_pct, status, intake_date, expiry_date, created_at, updated_at
             FROM items WHERE sku = ?'
        );
        $stmt->execute([$sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    public function findByBarcode(string $barcode): ?Item
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sku, barcode, consignor_id, category_id, location_id, description, size, condition,
                    price, store_share_pct, consignor_share_pct, status, intake_date, expiry_date, created_at, updated_at
             FROM items WHERE barcode = ? AND status = ?'
        );
        $stmt->execute([$barcode, Item::STATUS_AVAILABLE]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->hydrate($row) : null;
    }

    /**
     * @return list<Item>
     */
    public function search(?string $q = null, ?string $status = null, ?string $consignorId = null, ?string $categoryId = null, int $limit = 50): array
    {
        $sql = 'SELECT id, sku, barcode, consignor_id, category_id, location_id, description, size, condition,
                       price, store_share_pct, consignor_share_pct, status, intake_date, expiry_date, created_at, updated_at
                FROM items WHERE 1=1';
        $params = [];

        if ($q !== null && $q !== '') {
            $sql .= ' AND (sku ILIKE ? OR description ILIKE ? OR barcode ILIKE ?)';
            $p = '%' . $q . '%';
            $params[] = $p;
            $params[] = $p;
            $params[] = $p;
        }
        if ($status !== null && $status !== '') {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        if ($consignorId !== null && $consignorId !== '') {
            $sql .= ' AND consignor_id = ?';
            $params[] = $consignorId;
        }
        if ($categoryId !== null && $categoryId !== '') {
            $sql .= ' AND category_id = ?';
            $params[] = $categoryId;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT ' . (int) $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn (array $r) => $this->hydrate($r), $rows);
    }

    public function skuExists(string $sku, ?string $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM items WHERE sku = ? AND id != ?');
            $stmt->execute([$sku, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM items WHERE sku = ?');
            $stmt->execute([$sku]);
        }
        return $stmt->fetch() !== false;
    }

    public function create(
        string $sku,
        ?string $barcode,
        ?string $consignorId,
        ?string $categoryId,
        ?string $locationId,
        ?string $description,
        ?string $size,
        ?string $condition,
        float $price,
        float $storeSharePct,
        float $consignorSharePct,
        \DateTimeImmutable $intakeDate,
        ?\DateTimeImmutable $expiryDate = null
    ): string {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $intake = $intakeDate->format('Y-m-d');
        $expiry = $expiryDate?->format('Y-m-d');

        $stmt = $this->pdo->prepare(
            'INSERT INTO items (id, sku, barcode, consignor_id, category_id, location_id, description, size, condition,
                               price, store_share_pct, consignor_share_pct, status, intake_date, expiry_date, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::date, ?::date, ?, ?)'
        );
        $stmt->execute([
            $id, $sku, $barcode, $consignorId, $categoryId, $locationId, $description, $size, $condition,
            $price, $storeSharePct, $consignorSharePct, Item::STATUS_AVAILABLE, $intake, $expiry, $now, $now
        ]);
        return $id;
    }

    public function update(
        string $id,
        string $sku,
        ?string $barcode,
        ?string $consignorId,
        ?string $categoryId,
        ?string $locationId,
        ?string $description,
        ?string $size,
        ?string $condition,
        float $price,
        float $storeSharePct,
        float $consignorSharePct,
        ?\DateTimeImmutable $expiryDate
    ): void {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $expiry = $expiryDate?->format('Y-m-d');

        $stmt = $this->pdo->prepare(
            'UPDATE items SET sku = ?, barcode = ?, consignor_id = ?, category_id = ?, location_id = ?, description = ?,
                             size = ?, condition = ?, price = ?, store_share_pct = ?, consignor_share_pct = ?,
                             expiry_date = ?::date, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([$sku, $barcode, $consignorId, $categoryId, $locationId, $description, $size, $condition, $price, $storeSharePct, $consignorSharePct, $expiry, $now, $id]);
    }

    public function updatePrice(string $id, float $price): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE items SET price = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$price, $now, $id]);
    }

    public function updateStatus(string $id, string $status): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE items SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$status, $now, $id]);
    }

    /**
     * @param list<string> $ids
     */
    public function updateStatusForIds(array $ids, string $status): void
    {
        if ($ids === []) {
            return;
        }
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$status, $now], $ids);
        $stmt = $this->pdo->prepare("UPDATE items SET status = ?, updated_at = ? WHERE id IN ($placeholders)");
        $stmt->execute($params);
    }

    /**
     * @param list<array{id: string, price: float}> $updates
     */
    public function bulkUpdatePrices(array $updates): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        foreach ($updates as $u) {
            $stmt = $this->pdo->prepare('UPDATE items SET price = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$u['price'], $now, $u['id']]);
        }
    }

    public function countActiveByConsignor(string $consignorId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM items WHERE consignor_id = ? AND status = ?');
        $stmt->execute([$consignorId, Item::STATUS_AVAILABLE]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Item
    {
        $expiry = isset($row['expiry_date']) && $row['expiry_date'] !== null
            ? new \DateTimeImmutable((string) $row['expiry_date'])
            : null;

        return new Item(
            (string) $row['id'],
            (string) $row['sku'],
            isset($row['barcode']) && $row['barcode'] !== '' ? (string) $row['barcode'] : null,
            isset($row['consignor_id']) && $row['consignor_id'] !== null ? (string) $row['consignor_id'] : null,
            isset($row['category_id']) && $row['category_id'] !== null ? (string) $row['category_id'] : null,
            isset($row['location_id']) && $row['location_id'] !== null ? (string) $row['location_id'] : null,
            isset($row['description']) && $row['description'] !== '' ? (string) $row['description'] : null,
            isset($row['size']) && $row['size'] !== '' ? (string) $row['size'] : null,
            isset($row['condition']) && $row['condition'] !== '' ? (string) $row['condition'] : null,
            (float) $row['price'],
            (float) $row['store_share_pct'],
            (float) $row['consignor_share_pct'],
            (string) $row['status'],
            new \DateTimeImmutable((string) $row['intake_date']),
            $expiry,
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
