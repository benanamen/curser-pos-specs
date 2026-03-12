<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Service\BillingService;
use PerfectApp\Routing\Route;

final class PlatformBillingController
{
    public function __construct(
        private readonly BillingService $billingService
    ) {
    }

    #[Route('/api/v1/platform/tenants/([0-9a-fA-F-]{36})/billing', ['GET'])]
    public function getBilling(string $id): void
    {
        try {
            $result = $this->billingService->getBilling($id);
            $this->json(200, $result);
        } catch (\InvalidArgumentException $e) {
            $this->json(404, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/api/v1/platform/tenants/([0-9a-fA-F-]{36})/subscription', ['POST'])]
    public function createSubscription(string $id): void
    {
        $input = $this->getJsonInput();
        $planId = isset($input['plan_id']) && is_string($input['plan_id']) ? trim($input['plan_id']) : '';
        $customerEmail = isset($input['customer_email']) && is_string($input['customer_email']) ? trim($input['customer_email']) : '';
        $paymentMethodId = isset($input['payment_method_id']) && is_string($input['payment_method_id']) ? trim($input['payment_method_id']) : null;
        if ($paymentMethodId !== null && $paymentMethodId === '') {
            $paymentMethodId = null;
        }

        if ($planId === '' || $customerEmail === '') {
            $this->json(400, ['error' => 'plan_id and customer_email are required']);
            return;
        }

        try {
            $result = $this->billingService->createSubscription($id, $planId, $customerEmail, $paymentMethodId);
            $this->json(201, $result);
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            $this->json(502, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/api/v1/platform/tenants/([0-9a-fA-F-]{36})/subscription/cancel', ['POST'])]
    public function cancelSubscription(string $id): void
    {
        $input = $this->getJsonInput();
        $atPeriodEnd = !isset($input['immediate']) || $input['immediate'] !== true;

        try {
            $this->billingService->cancelSubscription($id, $atPeriodEnd);
            $this->json(200, ['message' => $atPeriodEnd ? 'Subscription will cancel at period end' : 'Subscription cancelled']);
        } catch (\InvalidArgumentException $e) {
            $this->json(404, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/api/v1/platform/tenants/([0-9a-fA-F-]{36})/invoices', ['GET'])]
    public function listInvoices(string $id): void
    {
        try {
            $invoices = $this->billingService->listInvoices($id);
            $this->json(200, ['invoices' => $invoices]);
        } catch (\InvalidArgumentException $e) {
            $this->json(404, ['error' => $e->getMessage()]);
        }
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
