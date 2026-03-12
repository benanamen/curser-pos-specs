<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Billing\TenantBillingRepository;
use PerfectApp\Routing\Route;

final class StripeWebhookController
{
    public function __construct(
        private readonly TenantBillingRepository $billingRepository
    ) {
    }

    #[Route('/api/v1/webhooks/stripe', ['POST'])]
    public function handle(): void
    {
        $secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
        if ($secret === '') {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Webhook secret not configured']);
            return;
        }

        $payload = file_get_contents('php://input');
        if ($payload === false) {
            $payload = '';
        }

        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        if ($sigHeader === '') {
            $this->respond(400, 'Missing Stripe-Signature');
            return;
        }

        if (!$this->verifySignature($payload, $sigHeader, $secret)) {
            $this->respond(400, 'Invalid signature');
            return;
        }

        $data = json_decode($payload, true);
        if (!is_array($data) || !isset($data['type'], $data['data']['object'])) {
            $this->respond(200);
            return;
        }

        $type = $data['type'];
        $object = $data['data']['object'];

        if ($type === 'customer.subscription.updated' || $type === 'customer.subscription.deleted') {
            $this->syncSubscription($object);
        }

        $this->respond(200);
    }

    /**
     * @param array<string, mixed> $subscription Stripe subscription object
     */
    private function syncSubscription(array $subscription): void
    {
        $subId = $subscription['id'] ?? '';
        if ($subId === '') {
            return;
        }

        $tenantId = $this->billingRepository->getTenantIdByExternalSubscriptionId($subId);
        if ($tenantId === null) {
            return;
        }

        $status = $this->mapStripeStatus($subscription['status'] ?? '');
        $start = isset($subscription['current_period_start']) ? (int) $subscription['current_period_start'] : null;
        $end = isset($subscription['current_period_end']) ? (int) $subscription['current_period_end'] : null;
        $cancelAtPeriodEnd = ($subscription['cancel_at_period_end'] ?? false) === true;

        $startStr = $start !== null ? date('c', $start) : null;
        $endStr = $end !== null ? date('c', $end) : null;

        $this->billingRepository->updateByExternalSubscriptionId($subId, $status, $startStr, $endStr, $cancelAtPeriodEnd);
    }

    private function mapStripeStatus(string $stripeStatus): string
    {
        return match (strtolower($stripeStatus)) {
            'active', 'trialing' => 'active',
            'past_due', 'unpaid' => 'past_due',
            'canceled', 'cancelled', 'incomplete_expired' => 'cancelled',
            'incomplete', 'paused' => 'past_due',
            default => 'active',
        };
    }

    private function verifySignature(string $payload, string $sigHeader, string $secret): bool
    {
        $parts = [];
        foreach (explode(',', $sigHeader) as $part) {
            $part = trim($part);
            if (str_contains($part, '=')) {
                [$k, $v] = explode('=', $part, 2);
                $parts[trim($k)] = trim($v);
            }
        }
        $timestamp = $parts['t'] ?? '';
        $v1 = $parts['v1'] ?? '';
        if ($timestamp === '' || $v1 === '') {
            return false;
        }
        $signed = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signed, $secret);
        return hash_equals($expected, $v1);
    }

    private function respond(int $code, ?string $message = null): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        if ($message !== null) {
            echo json_encode(['error' => $message]);
        } else {
            echo json_encode(['received' => true]);
        }
    }
}
