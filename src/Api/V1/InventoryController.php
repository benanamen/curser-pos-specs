<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Item\ItemRepository;
use CurserPos\Http\RequestContextHolder;
use CurserPos\Service\InventoryService;
use PerfectApp\Routing\Route;

final class InventoryController
{
    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly InventoryService $inventoryService
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/items', ['GET'])]
    public function list(string $slug): void
    {
        $q = $_GET['q'] ?? null;
        $status = $_GET['status'] ?? null;
        $consignorId = $_GET['consignor_id'] ?? null;
        $categoryId = $_GET['category_id'] ?? null;
        $limit = (int) ($_GET['limit'] ?? 50);

        $items = $this->itemRepository->search($q, $status ?: null, $consignorId ?: null, $categoryId ?: null, $limit);
        $this->json(200, array_map(fn ($i) => $this->itemToArray($i), $items));
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/items/([0-9a-fA-F-]{36})', ['GET'])]
    public function show(string $slug, string $id): void
    {
        $item = $this->itemRepository->findById($id);
        if ($item === null) {
            $this->json(404, ['error' => 'Item not found']);
            return;
        }
        $this->json(200, $this->itemToArray($item));
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/items/lookup/barcode', ['GET'])]
    public function lookupBarcode(string $slug): void
    {
        $barcode = $_GET['barcode'] ?? '';
        if ($barcode === '') {
            $this->json(400, ['error' => 'barcode parameter required']);
            return;
        }
        $item = $this->itemRepository->findByBarcode($barcode);
        if ($item === null) {
            $this->json(404, ['error' => 'Item not found']);
            return;
        }
        $this->json(200, $this->itemToArray($item));
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/items/lookup/sku', ['GET'])]
    public function lookupSku(string $slug): void
    {
        $sku = $_GET['sku'] ?? '';
        if ($sku === '') {
            $this->json(400, ['error' => 'sku parameter required']);
            return;
        }
        $item = $this->itemRepository->findBySku($sku);
        if ($item === null) {
            $this->json(404, ['error' => 'Item not found']);
            return;
        }
        $this->json(200, $this->itemToArray($item));
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/items', ['POST'])]
    public function create(string $slug): void
    {
        $input = $this->getJsonInput();
        $sku = $input['sku'] ?? $this->inventoryService->generateSku();
        $barcode = isset($input['barcode']) && $input['barcode'] !== '' ? (string) $input['barcode'] : null;
        $consignorId = isset($input['consignor_id']) && $input['consignor_id'] !== '' ? (string) $input['consignor_id'] : null;
        $categoryId = isset($input['category_id']) && $input['category_id'] !== '' ? (string) $input['category_id'] : null;
        $locationId = isset($input['location_id']) && $input['location_id'] !== '' ? (string) $input['location_id'] : null;
        $description = isset($input['description']) && $input['description'] !== '' ? (string) $input['description'] : null;
        $size = isset($input['size']) && $input['size'] !== '' ? (string) $input['size'] : null;
        $condition = isset($input['condition']) && $input['condition'] !== '' ? (string) $input['condition'] : null;
        $price = (float) ($input['price'] ?? 0);
        $storeSharePct = (float) ($input['store_share_pct'] ?? 50);
        $consignorSharePct = (float) ($input['consignor_share_pct'] ?? 50);
        $intakeDate = isset($input['intake_date']) ? new \DateTimeImmutable((string) $input['intake_date']) : new \DateTimeImmutable();
        $expiryDate = isset($input['expiry_date']) && $input['expiry_date'] !== '' ? new \DateTimeImmutable((string) $input['expiry_date']) : null;

        if ($price <= 0) {
            $this->json(400, ['error' => 'Price must be greater than 0']);
            return;
        }

        $tenantId = RequestContextHolder::get()?->tenant?->id;

        try {
            $item = $this->inventoryService->createItem($sku, $barcode, $consignorId, $categoryId, $locationId, $description, $size, $condition, $price, $storeSharePct, $consignorSharePct, $intakeDate, $expiryDate, $tenantId);
            $this->json(201, $this->itemToArray($item));
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/items/([0-9a-fA-F-]{36})', ['PUT', 'PATCH'])]
    public function update(string $slug, string $id): void
    {
        $item = $this->itemRepository->findById($id);
        if ($item === null) {
            $this->json(404, ['error' => 'Item not found']);
            return;
        }
        $input = $this->getJsonInput();
        $sku = $input['sku'] ?? $item->sku;
        $barcode = array_key_exists('barcode', $input) ? ($input['barcode'] !== '' ? (string) $input['barcode'] : null) : $item->barcode;
        $consignorId = array_key_exists('consignor_id', $input) ? ($input['consignor_id'] !== '' ? (string) $input['consignor_id'] : null) : $item->consignorId;
        $categoryId = array_key_exists('category_id', $input) ? ($input['category_id'] !== '' ? (string) $input['category_id'] : null) : $item->categoryId;
        $locationId = array_key_exists('location_id', $input) ? ($input['location_id'] !== '' ? (string) $input['location_id'] : null) : $item->locationId;
        $description = array_key_exists('description', $input) ? ($input['description'] !== '' ? (string) $input['description'] : null) : $item->description;
        $size = array_key_exists('size', $input) ? ($input['size'] !== '' ? (string) $input['size'] : null) : $item->size;
        $condition = array_key_exists('condition', $input) ? ($input['condition'] !== '' ? (string) $input['condition'] : null) : $item->condition;
        $price = array_key_exists('price', $input) ? (float) $input['price'] : $item->price;
        $storeSharePct = array_key_exists('store_share_pct', $input) ? (float) $input['store_share_pct'] : $item->storeSharePct;
        $consignorSharePct = array_key_exists('consignor_share_pct', $input) ? (float) $input['consignor_share_pct'] : $item->consignorSharePct;
        $expiryDate = array_key_exists('expiry_date', $input) ? ($input['expiry_date'] !== '' ? new \DateTimeImmutable((string) $input['expiry_date']) : null) : $item->expiryDate;

        if ($this->itemRepository->skuExists($sku, $id)) {
            $this->json(400, ['error' => 'SKU already exists']);
            return;
        }

        $this->itemRepository->update($id, $sku, $barcode, $consignorId, $categoryId, $locationId, $description, $size, $condition, $price, $storeSharePct, $consignorSharePct, $expiryDate);
        $updated = $this->itemRepository->findById($id);
        $this->json(200, $updated !== null ? $this->itemToArray($updated) : []);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/items/([0-9a-fA-F-]{36})/status', ['PUT', 'PATCH'])]
    public function updateStatus(string $slug, string $id): void
    {
        $item = $this->itemRepository->findById($id);
        if ($item === null) {
            $this->json(404, ['error' => 'Item not found']);
            return;
        }
        $input = $this->getJsonInput();
        $status = $input['status'] ?? '';
        if ($status === '') {
            $this->json(400, ['error' => 'status is required']);
            return;
        }
        try {
            $this->inventoryService->updateItemStatus($id, $status);
            $updated = $this->itemRepository->findById($id);
            $this->json(200, $updated !== null ? $this->itemToArray($updated) : []);
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/items/([0-9a-fA-F-]{36})/label', ['GET'])]
    public function label(string $slug, string $id): void
    {
        $item = $this->itemRepository->findById($id);
        if ($item === null) {
            $this->json(404, ['error' => 'Item not found']);
            return;
        }
        $this->json(200, [
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'description' => $item->description,
            'price' => $item->price,
            'size' => $item->size,
        ]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/items/import', ['POST'])]
    public function bulkImport(string $slug): void
    {
        $tenantId = RequestContextHolder::get()?->tenant?->id;
        $body = file_get_contents('php://input');
        if ($body === false || trim($body) === '') {
            $this->json(400, ['error' => 'CSV body required']);
            return;
        }
        try {
            $result = $this->inventoryService->bulkImportFromCsv($body, $tenantId);
            $this->json(200, $result);
        } catch (\Throwable $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/items/bulk-status', ['PUT', 'PATCH', 'POST'])]
    public function bulkStatus(string $slug): void
    {
        $input = $this->getJsonInput();
        $itemIds = $input['item_ids'] ?? [];
        $status = $input['status'] ?? '';
        if (!is_array($itemIds) || $status === '') {
            $this->json(400, ['error' => 'item_ids (array) and status are required']);
            return;
        }
        $itemIds = array_values(array_filter(array_map('strval', $itemIds)));
        if ($itemIds === []) {
            $this->json(400, ['error' => 'At least one item_id required']);
            return;
        }
        try {
            $result = $this->inventoryService->bulkUpdateStatus($itemIds, $status);
            $this->json(200, $result);
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/items/bulk-price', ['PUT', 'PATCH', 'POST'])]
    public function bulkPrice(string $slug): void
    {
        $input = $this->getJsonInput();
        $updates = $input['updates'] ?? [];
        if (!is_array($updates)) {
            $this->json(400, ['error' => 'updates must be an array of { id, price }']);
            return;
        }
        $list = [];
        foreach ($updates as $u) {
            $id = isset($u['id']) && $u['id'] !== '' ? (string) $u['id'] : null;
            $price = isset($u['price']) ? (float) $u['price'] : null;
            if ($id !== null && $price !== null && $price >= 0) {
                $list[] = ['id' => $id, 'price' => $price];
            }
        }
        if ($list === []) {
            $this->json(400, ['error' => 'At least one valid { id, price } required']);
            return;
        }
        $result = $this->inventoryService->bulkUpdatePrices($list);
        $this->json(200, $result);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemToArray(object $item): array
    {
        return [
            'id' => $item->id,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'consignor_id' => $item->consignorId,
            'category_id' => $item->categoryId,
            'location_id' => $item->locationId,
            'description' => $item->description,
            'size' => $item->size,
            'condition' => $item->condition,
            'price' => $item->price,
            'store_share_pct' => $item->storeSharePct,
            'consignor_share_pct' => $item->consignorSharePct,
            'status' => $item->status,
            'intake_date' => $item->intakeDate->format('Y-m-d'),
            'expiry_date' => $item->expiryDate?->format('Y-m-d'),
            'created_at' => $item->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $item->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonInput(): array
    {
        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        if ($data !== []) {
            echo json_encode($data);
        }
    }
}
