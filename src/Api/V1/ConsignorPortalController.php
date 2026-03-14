<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Item\Item;
use CurserPos\Domain\Item\ItemRepository;
use CurserPos\Domain\Payout\PayoutRepository;
use CurserPos\Domain\Sale\SaleRepository;
use CurserPos\Http\RequestContextHolder;
use CurserPos\Service\ConsignorService;
use CurserPos\Service\InventoryService;
use PDO;
use PerfectApp\Routing\Route;

final class ConsignorPortalController
{
    public function __construct(
        private readonly ConsignorService $consignorService,
        private readonly SaleRepository $saleRepository,
        private readonly PayoutRepository $payoutRepository,
        private readonly ItemRepository $itemRepository,
        private readonly InventoryService $inventoryService,
        private readonly PDO $pdo
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/portal/me', ['GET'])]
    public function me(string $slug): void
    {
        $context = RequestContextHolder::get();
        if ($context === null || $context->consignor === null) {
            $this->json(401, ['error' => 'Invalid or missing portal token']);
            return;
        }

        if ($context->tenant === null) {
            $this->json(404, ['error' => 'Tenant not found']);
            return;
        }

        if (!$this->tenantHasConsignorPortal($context->tenant->id)) {
            $this->json(403, ['error' => 'Consignor portal is not available on your plan']);
            return;
        }

        $consignor = $context->consignor;
        $balance = $this->consignorService->getBalance($consignor->id);
        $sales = $this->saleRepository->getSalesByConsignor($consignor->id, 50);
        $payouts = $this->payoutRepository->listByConsignor($consignor->id, 50);

        $this->json(200, [
            'consignor' => [
                'id' => $consignor->id,
                'slug' => $consignor->slug,
                'name' => $consignor->name,
            ],
            'balance' => $balance->balance,
            'pending_sales' => $balance->pendingSales,
            'paid_out' => $balance->paidOut,
            'recent_sales' => $sales,
            'recent_payouts' => $payouts,
        ]);
    }

    private function tenantHasConsignorPortal(string $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.features FROM tenants t JOIN plans p ON p.id = t.plan_id WHERE t.id = ?'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !isset($row['features'])) {
            return false;
        }
        $features = is_string($row['features']) ? json_decode($row['features'], true) : $row['features'];
        return isset($features['consignor_portal']) && $features['consignor_portal'] === true;
    }

    private function tenantHasVendorPortal(string $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.features FROM tenants t JOIN plans p ON p.id = t.plan_id WHERE t.id = ?'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !isset($row['features'])) {
            return false;
        }
        $features = is_string($row['features']) ? json_decode($row['features'], true) : $row['features'];
        return isset($features['vendor_portal']) && $features['vendor_portal'] === true;
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/portal/items', ['GET'])]
    public function listItems(string $slug): void
    {
        $context = RequestContextHolder::get();
        if ($context === null || $context->consignor === null || $context->tenant === null) {
            $this->json(401, ['error' => 'Invalid or missing portal token']);
            return;
        }
        if (!$this->tenantHasVendorPortal($context->tenant->id)) {
            $this->json(403, ['error' => 'Vendor portal is not available on your plan']);
            return;
        }
        $items = $this->itemRepository->search(null, null, $context->consignor->id, null, 100);
        $this->json(200, ['items' => array_map(fn (Item $i) => $this->itemToArray($i), $items)]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/portal/items', ['POST'])]
    public function createItem(string $slug): void
    {
        $context = RequestContextHolder::get();
        if ($context === null || $context->consignor === null || $context->tenant === null) {
            $this->json(401, ['error' => 'Invalid or missing portal token']);
            return;
        }
        if (!$this->tenantHasVendorPortal($context->tenant->id)) {
            $this->json(403, ['error' => 'Vendor portal is not available on your plan']);
            return;
        }
        $input = $this->getJsonInput();
        $sku = isset($input['sku']) && $input['sku'] !== '' ? (string) $input['sku'] : $this->inventoryService->generateSku();
        $barcode = isset($input['barcode']) && $input['barcode'] !== '' ? (string) $input['barcode'] : null;
        $categoryId = isset($input['category_id']) && $input['category_id'] !== '' ? (string) $input['category_id'] : null;
        $description = isset($input['description']) && $input['description'] !== '' ? (string) $input['description'] : null;
        $size = isset($input['size']) && $input['size'] !== '' ? (string) $input['size'] : null;
        $condition = isset($input['condition']) && $input['condition'] !== '' ? (string) $input['condition'] : null;
        $price = (float) ($input['price'] ?? 0);
        $storeSharePct = (float) ($input['store_share_pct'] ?? 50);
        $consignorSharePct = (float) ($input['consignor_share_pct'] ?? 50);
        $intakeDate = isset($input['intake_date']) ? new \DateTimeImmutable((string) $input['intake_date']) : new \DateTimeImmutable();
        $expiryDate = isset($input['expiry_date']) && $input['expiry_date'] !== '' ? new \DateTimeImmutable((string) $input['expiry_date']) : null;
        $quantity = isset($input['quantity']) ? max(1, (int) $input['quantity']) : 1;

        if ($price <= 0) {
            $this->json(400, ['error' => 'Price must be greater than 0']);
            return;
        }

        try {
            $item = $this->inventoryService->createItem($sku, $barcode, $context->consignor->id, $categoryId, null, $description, $size, $condition, $price, $storeSharePct, $consignorSharePct, $intakeDate, $expiryDate, $context->tenant->id, $quantity);
            $this->json(201, $this->itemToArray($item));
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/portal/items/([0-9a-fA-F-]{36})', ['GET'])]
    public function getItem(string $slug, string $id): void
    {
        $context = RequestContextHolder::get();
        if ($context === null || $context->consignor === null || $context->tenant === null) {
            $this->json(401, ['error' => 'Invalid or missing portal token']);
            return;
        }
        if (!$this->tenantHasVendorPortal($context->tenant->id)) {
            $this->json(403, ['error' => 'Vendor portal is not available on your plan']);
            return;
        }
        $item = $this->itemRepository->findById($id);
        if ($item === null || $item->consignorId !== $context->consignor->id) {
            $this->json(404, ['error' => 'Item not found']);
            return;
        }
        $this->json(200, $this->itemToArray($item));
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/portal/items/([0-9a-fA-F-]{36})', ['PUT', 'PATCH'])]
    public function updateItem(string $slug, string $id): void
    {
        $context = RequestContextHolder::get();
        if ($context === null || $context->consignor === null || $context->tenant === null) {
            $this->json(401, ['error' => 'Invalid or missing portal token']);
            return;
        }
        if (!$this->tenantHasVendorPortal($context->tenant->id)) {
            $this->json(403, ['error' => 'Vendor portal is not available on your plan']);
            return;
        }
        $item = $this->itemRepository->findById($id);
        if ($item === null || $item->consignorId !== $context->consignor->id) {
            $this->json(404, ['error' => 'Item not found']);
            return;
        }
        $input = $this->getJsonInput();
        $description = array_key_exists('description', $input) ? ($input['description'] !== '' ? (string) $input['description'] : null) : $item->description;
        $price = array_key_exists('price', $input) ? (float) $input['price'] : $item->price;
        $size = array_key_exists('size', $input) ? ($input['size'] !== '' ? (string) $input['size'] : null) : $item->size;
        $condition = array_key_exists('condition', $input) ? ($input['condition'] !== '' ? (string) $input['condition'] : null) : $item->condition;

        if ($price <= 0) {
            $this->json(400, ['error' => 'Price must be greater than 0']);
            return;
        }

        $this->itemRepository->update($id, $item->sku, $item->barcode, $item->consignorId, $item->categoryId, $item->locationId, $description, $size, $condition, $price, $item->storeSharePct, $item->consignorSharePct, $item->expiryDate, null);
        $updated = $this->itemRepository->findById($id);
        $this->json(200, $updated !== null ? $this->itemToArray($updated) : []);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/portal/items/([0-9a-fA-F-]{36})/price', ['PATCH'])]
    public function updateItemPrice(string $slug, string $id): void
    {
        $context = RequestContextHolder::get();
        if ($context === null || $context->consignor === null || $context->tenant === null) {
            $this->json(401, ['error' => 'Invalid or missing portal token']);
            return;
        }
        if (!$this->tenantHasVendorPortal($context->tenant->id)) {
            $this->json(403, ['error' => 'Vendor portal is not available on your plan']);
            return;
        }
        $item = $this->itemRepository->findById($id);
        if ($item === null || $item->consignorId !== $context->consignor->id) {
            $this->json(404, ['error' => 'Item not found']);
            return;
        }
        $input = $this->getJsonInput();
        $price = (float) ($input['price'] ?? 0);
        if ($price <= 0) {
            $this->json(400, ['error' => 'Price must be greater than 0']);
            return;
        }
        $this->itemRepository->updatePrice($id, $price);
        $updated = $this->itemRepository->findById($id);
        $this->json(200, $updated !== null ? $this->itemToArray($updated) : []);
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
     * @return array<string, mixed>
     */
    private function itemToArray(Item $item): array
    {
        return [
            'id' => $item->id,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'consignor_id' => $item->consignorId,
            'category_id' => $item->categoryId,
            'description' => $item->description,
            'size' => $item->size,
            'condition' => $item->condition,
            'price' => $item->price,
            'status' => $item->status,
            'intake_date' => $item->intakeDate->format('Y-m-d'),
            'expiry_date' => $item->expiryDate?->format('Y-m-d'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
