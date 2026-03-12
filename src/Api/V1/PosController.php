<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Sale\SaleRepository;
use CurserPos\Http\RequestContextHolder;
use CurserPos\Service\PosService;
use PerfectApp\Routing\Route;

final class PosController
{
    public function __construct(
        private readonly PosService $posService,
        private readonly SaleRepository $saleRepository,
        private readonly \CurserPos\Service\AuditService $auditService
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/pos/checkout', ['POST'])]
    public function checkout(string $slug): void
    {
        $user = $this->getCurrentUserId();
        if ($user === null) {
            $this->json(401, ['error' => 'Authentication required']);
            return;
        }
        $input = $this->getJsonInput();
        $heldId = isset($input['held_id']) && $input['held_id'] !== '' ? (string) $input['held_id'] : null;
        $registerId = isset($input['register_id']) && $input['register_id'] !== '' ? (string) $input['register_id'] : null;
        $locationId = isset($input['location_id']) && $input['location_id'] !== '' ? (string) $input['location_id'] : null;

        if ($heldId !== null) {
            try {
                $result = $this->posService->checkoutFromHold($heldId, $user, $registerId, $locationId);
                $this->json(201, $result);
            } catch (\InvalidArgumentException $e) {
                $this->json(400, ['error' => $e->getMessage()]);
            }
            return;
        }

        $cart = $input['cart'] ?? [];
        $payments = $input['payments'] ?? [];
        $discountAmount = (float) ($input['discount_amount'] ?? 0);
        $taxAmount = (float) ($input['tax_amount'] ?? 0);
        $taxExempt = ($input['tax_exempt'] ?? false) === true;

        if (!is_array($cart) || !is_array($payments)) {
            $this->json(400, ['error' => 'cart and payments must be arrays']);
            return;
        }

        try {
            $result = $this->posService->checkout($user, $registerId, $locationId, $cart, $payments, $discountAmount, $taxAmount, $taxExempt);
            $this->json(201, $result);
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/pos/hold', ['POST'])]
    public function hold(string $slug): void
    {
        $user = $this->getCurrentUserId();
        if ($user === null) {
            $this->json(401, ['error' => 'Authentication required']);
            return;
        }
        $input = $this->getJsonInput();
        $cart = $input['cart'] ?? [];
        $payments = $input['payments'] ?? [];
        if (!is_array($cart) || !is_array($payments)) {
            $this->json(400, ['error' => 'cart and payments must be arrays']);
            return;
        }
        try {
            $heldId = $this->posService->holdCart($user, $cart, $payments);
            $this->json(201, ['held_id' => $heldId]);
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/pos/held', ['GET'])]
    public function listHeld(string $slug): void
    {
        $user = $this->getCurrentUserId();
        if ($user === null) {
            $this->json(401, ['error' => 'Authentication required']);
            return;
        }
        $limit = (int) ($_GET['limit'] ?? 200);
        if ($limit < 1) {
            $limit = 200;
        }
        $list = $this->posService->listHeld($user, $limit);
        $this->json(200, ['held_sales' => $list]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/pos/held/([0-9a-fA-F-]{36})', ['GET'])]
    public function getHeld(string $slug, string $id): void
    {
        $user = $this->getCurrentUserId();
        if ($user === null) {
            $this->json(401, ['error' => 'Authentication required']);
            return;
        }
        $held = $this->posService->getHeld($id);
        if ($held === null) {
            $this->json(404, ['error' => 'Held sale not found']);
            return;
        }
        $this->json(200, $held);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/pos/held/([0-9a-fA-F-]{36})/release', ['POST'])]
    public function releaseHeld(string $slug, string $id): void
    {
        $user = $this->getCurrentUserId();
        if ($user === null) {
            $this->json(401, ['error' => 'Authentication required']);
            return;
        }
        try {
            $this->posService->releaseHold($id, $user);
            $this->json(200, ['message' => 'Held sale released', 'held_id' => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/pos/sales', ['GET'])]
    public function listSales(string $slug): void
    {
        $user = $this->getCurrentUserId();
        if ($user === null) {
            $this->json(401, ['error' => 'Authentication required']);
            return;
        }
        $dateFrom = isset($_GET['from']) ? (string) $_GET['from'] : null;
        $dateTo = isset($_GET['to']) ? (string) $_GET['to'] : null;
        $registerId = isset($_GET['register_id']) && $_GET['register_id'] !== '' ? (string) $_GET['register_id'] : null;
        $limit = (int) ($_GET['limit'] ?? 100);
        $list = $this->saleRepository->list($dateFrom, $dateTo, $registerId, $limit);
        $this->json(200, ['sales' => $list]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/pos/sales/([0-9a-fA-F-]{36})/refund', ['POST'])]
    public function refund(string $slug, string $id): void
    {
        $context = RequestContextHolder::get();
        $user = $this->getCurrentUserId();
        if ($user === null) {
            $this->json(401, ['error' => 'Authentication required']);
            return;
        }
        try {
            $result = $this->posService->refund($id, $user);
            $this->auditService->log(
                $context?->tenant?->id,
                $user,
                $context?->supportUserId,
                'sale.refund',
                'sale',
                $id,
                ['refund_amount' => $result['refund_amount'] ?? null],
                $context?->clientIp
            );
            $this->json(200, $result);
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/pos/sales/([0-9a-fA-F-]{36})/receipt/email', ['POST'])]
    public function emailReceipt(string $slug, string $id): void
    {
        $sale = $this->saleRepository->findById($id);
        if ($sale === null) {
            $this->json(404, ['error' => 'Sale not found']);
            return;
        }
        $input = $this->getJsonInput();
        $email = isset($input['email']) && $input['email'] !== '' ? (string) $input['email'] : null;
        if ($email === null) {
            $this->json(400, ['error' => 'email is required']);
            return;
        }
        // Stub: no email sent in API-only MVP; UI or job queue would send
        $this->json(200, ['message' => 'Receipt email queued', 'sale_id' => $id, 'email' => $email]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/pos/sales/([0-9a-fA-F-]{36})', ['GET'])]
    public function receipt(string $slug, string $id): void
    {
        $sale = $this->saleRepository->findById($id);
        if ($sale === null) {
            $this->json(404, ['error' => 'Sale not found']);
            return;
        }
        $this->json(200, [
            'id' => $sale->id,
            'sale_number' => $sale->saleNumber,
            'subtotal' => $sale->subtotal,
            'discount_amount' => $sale->discountAmount,
            'tax_amount' => $sale->taxAmount,
            'total' => $sale->total,
            'status' => $sale->status,
            'created_at' => $sale->createdAt->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/pos/sales/([0-9a-fA-F-]{36})/void', ['POST'])]
    public function void(string $slug, string $id): void
    {
        $context = RequestContextHolder::get();
        $user = $this->getCurrentUserId();
        if ($user === null) {
            $this->json(401, ['error' => 'Authentication required']);
            return;
        }
        try {
            $this->posService->voidSale($id, $user);
            $this->auditService->log(
                $context?->tenant?->id,
                $user,
                $context?->supportUserId,
                'sale.void',
                'sale',
                $id,
                [],
                $context?->clientIp
            );
            $this->json(200, ['message' => 'Sale voided']);
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/pos/tax', ['GET'])]
    public function tax(string $slug): void
    {
        $context = RequestContextHolder::get();
        if ($context?->tenant === null) {
            $this->json(404, ['error' => 'Tenant not found']);
            return;
        }
        $subtotal = isset($_GET['subtotal']) ? (float) $_GET['subtotal'] : 0.0;
        $locationId = isset($_GET['location_id']) && $_GET['location_id'] !== '' ? (string) $_GET['location_id'] : null;
        $taxConfig = $context->tenant->settings['tax_config'] ?? [];
        $rate = 0.0;
        if (is_array($taxConfig)) {
            if ($locationId !== null && isset($taxConfig['locations']) && is_array($taxConfig['locations']) && isset($taxConfig['locations'][$locationId])) {
                $rate = (float) $taxConfig['locations'][$locationId];
            } elseif (isset($taxConfig['rate'])) {
                $rate = (float) $taxConfig['rate'];
            }
        }
        $taxAmount = round($subtotal * $rate, 2);
        $this->json(200, ['tax_amount' => $taxAmount, 'rate' => $rate]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/pos/charge-card', ['POST'])]
    public function chargeCard(string $slug): void
    {
        $user = $this->getCurrentUserId();
        if ($user === null) {
            $this->json(401, ['error' => 'Authentication required']);
            return;
        }
        $input = $this->getJsonInput();
        $amount = (float) ($input['amount'] ?? 0);
        $paymentMethodId = isset($input['payment_method_id']) && $input['payment_method_id'] !== '' ? (string) $input['payment_method_id'] : '';
        if ($amount <= 0 || $paymentMethodId === '') {
            $this->json(400, ['error' => 'amount and payment_method_id are required']);
            return;
        }
        $registerId = isset($input['register_id']) && $input['register_id'] !== '' ? (string) $input['register_id'] : null;
        $locationId = isset($input['location_id']) && $input['location_id'] !== '' ? (string) $input['location_id'] : null;
        $description = isset($input['description']) && $input['description'] !== '' ? (string) $input['description'] : 'Keyed sale';
        try {
            $result = $this->posService->chargeCardKeyed($user, $registerId, $locationId, $amount, $paymentMethodId, $description);
            $this->json(201, $result);
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            $this->json(502, ['error' => $e->getMessage()]);
        }
    }

    private function getCurrentUserId(): ?string
    {
        $context = RequestContextHolder::get();
        $user = $context?->user;
        return $user?->id;
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
        echo json_encode($data);
    }
}
