<?php

declare(strict_types=1);

namespace CurserPos\Service;

use CurserPos\Domain\Item\Item;
use CurserPos\Domain\Item\ItemRepository;
use PDO;

final class InventoryService
{
    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly PDO $pdo
    ) {
    }

    public function createItem(
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
        ?\DateTimeImmutable $expiryDate = null,
        ?string $tenantId = null,
        int $quantity = 1
    ): Item {
        $this->enforceItemLimit($tenantId);
        if ($this->itemRepository->skuExists($sku)) {
            throw new \InvalidArgumentException("SKU '{$sku}' already exists");
        }
        $id = $this->itemRepository->create($sku, $barcode, $consignorId, $categoryId, $locationId, $description, $size, $condition, $price, $storeSharePct, $consignorSharePct, $intakeDate, $expiryDate, $quantity);
        $item = $this->itemRepository->findById($id);
        if ($item === null) {
            throw new \RuntimeException('Failed to create item');
        }
        return $item;
    }

    public function generateSku(): string
    {
        $prefix = 'IT';
        $items = $this->itemRepository->search(null, null, null, null, 1);
        $last = $items[0] ?? null;
        if ($last === null) {
            return $prefix . date('Ymd') . '0001';
        }
        if (!preg_match('/\d{4}$/', $last->sku, $m)) {
            return $prefix . date('Ymd') . '0001';
        }
        $num = (int) $m[0];
        return $prefix . date('Ymd') . str_pad((string) ($num + 1), 4, '0', STR_PAD_LEFT);
    }

    public function updateItemStatus(string $id, string $status): void
    {
        $valid = [Item::STATUS_AVAILABLE, Item::STATUS_SOLD, Item::STATUS_RETURNED, Item::STATUS_EXPIRED, Item::STATUS_PICKED_UP, Item::STATUS_DONATED, Item::STATUS_WRITTEN_OFF];
        if (!in_array($status, $valid, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }
        $this->itemRepository->updateStatus($id, $status);
    }

    /**
     * @param list<array{id: string, price: float}> $updates
     * @return array{updated: int}
     */
    public function bulkUpdatePrices(array $updates): array
    {
        if ($updates === []) {
            return ['updated' => 0];
        }
        $this->itemRepository->bulkUpdatePrices($updates);
        return ['updated' => count($updates)];
    }

    /**
     * @param list<string> $itemIds
     * @return array{updated: int, errors: list<string>}
     */
    public function bulkUpdateStatus(array $itemIds, string $status): array
    {
        $valid = [Item::STATUS_AVAILABLE, Item::STATUS_SOLD, Item::STATUS_RETURNED, Item::STATUS_EXPIRED, Item::STATUS_PICKED_UP, Item::STATUS_DONATED, Item::STATUS_WRITTEN_OFF];
        if (!in_array($status, $valid, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }
        $this->itemRepository->updateStatusForIds($itemIds, $status);
        return ['updated' => count($itemIds), 'errors' => []];
    }

    /**
     * Import items from CSV. First row = headers. Columns: sku, barcode, consignor_id, category_id, description, size, condition, price, store_share_pct, consignor_share_pct
     *
     * @return array{created: list<array<string, mixed>>, errors: list<array{row: int, message: string}>}
     */
    public function bulkImportFromCsv(string $csv, ?string $tenantId = null): array
    {
        $lines = array_filter(explode("\n", $csv), fn (string $l) => trim($l) !== '');
        if ($lines === []) {
            return ['created' => [], 'errors' => [['row' => 0, 'message' => 'Empty or invalid CSV']]];
        }
        $header = str_getcsv(array_shift($lines));
        $header = array_map('trim', $header);
        $created = [];
        $errors = [];
        $rowNum = 2;
        foreach ($lines as $line) {
            $row = str_getcsv($line);
            $assoc = array_combine($header, array_pad($row, count($header), null));
            if ($assoc === false) {
                $errors[] = ['row' => $rowNum, 'message' => 'Column count mismatch'];
                $rowNum++;
                continue;
            }
            $sku = isset($assoc['sku']) && trim((string) $assoc['sku']) !== '' ? trim((string) $assoc['sku']) : $this->generateSku();
            $barcode = isset($assoc['barcode']) && trim((string) $assoc['barcode']) !== '' ? trim((string) $assoc['barcode']) : null;
            $consignorId = isset($assoc['consignor_id']) && trim((string) $assoc['consignor_id']) !== '' ? trim((string) $assoc['consignor_id']) : null;
            $categoryId = isset($assoc['category_id']) && trim((string) $assoc['category_id']) !== '' ? trim((string) $assoc['category_id']) : null;
            $locationId = isset($assoc['location_id']) && trim((string) $assoc['location_id']) !== '' ? trim((string) $assoc['location_id']) : null;
            $description = isset($assoc['description']) && trim((string) $assoc['description']) !== '' ? trim((string) $assoc['description']) : null;
            $size = isset($assoc['size']) && trim((string) $assoc['size']) !== '' ? trim((string) $assoc['size']) : null;
            $condition = isset($assoc['condition']) && trim((string) $assoc['condition']) !== '' ? trim((string) $assoc['condition']) : null;
            $price = (float) ($assoc['price'] ?? 0);
            $storeSharePct = (float) ($assoc['store_share_pct'] ?? 50);
            $consignorSharePct = (float) ($assoc['consignor_share_pct'] ?? 50);

            if ($price <= 0) {
                $errors[] = ['row' => $rowNum, 'message' => 'Price must be greater than 0'];
                $rowNum++;
                continue;
            }
            try {
                $item = $this->createItem($sku, $barcode, $consignorId, $categoryId, $locationId, $description, $size, $condition, $price, $storeSharePct, $consignorSharePct, new \DateTimeImmutable(), null, $tenantId);
                $created[] = ['id' => $item->id, 'sku' => $item->sku];
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            }
            $rowNum++;
        }
        return ['created' => $created, 'errors' => $errors];
    }

    private function enforceItemLimit(?string $tenantId): void
    {
        if ($tenantId === null) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT p.item_limit FROM public.tenants t JOIN public.plans p ON p.id = t.plan_id WHERE t.id = ?');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $limit = $row !== false ? (int) $row['item_limit'] : 0;
        if ($limit <= 0) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM items WHERE status = ?');
        $stmt->execute([Item::STATUS_AVAILABLE]);
        $total = (int) $stmt->fetchColumn();
        if ($total >= $limit) {
            throw new \InvalidArgumentException("Item limit reached for this plan ({$limit} items). Upgrade to add more.");
        }
    }
}
