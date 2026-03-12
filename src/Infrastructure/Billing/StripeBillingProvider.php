<?php

declare(strict_types=1);

namespace CurserPos\Infrastructure\Billing;

use CurserPos\Domain\Billing\BillingProviderInterface;
use CurserPos\Domain\Billing\TenantBillingRepository;

/**
 * Stripe billing using REST API (no SDK). Uses form-urlencoded and Bearer auth.
 */
final class StripeBillingProvider implements BillingProviderInterface
{
    private const API_BASE = 'https://api.stripe.com/v1';

    /**
     * @param array<string, string> $planIdToPriceId Map plan UUID => Stripe Price ID (e.g. price_xxx)
     */
    public function __construct(
        private readonly string $secretKey,
        private readonly array $planIdToPriceId,
        private readonly TenantBillingRepository $billingRepository
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createSubscription(
        string $tenantId,
        string $planId,
        string $customerEmail,
        ?string $paymentMethodId = null,
        ?string $existingCustomerId = null
    ): array {
        $priceId = $this->planIdToPriceId[$planId] ?? null;
        if ($priceId === null || $priceId === '') {
            throw new \InvalidArgumentException('No Stripe Price ID configured for plan ' . $planId);
        }

        $customerId = $existingCustomerId;
        if ($customerId === null || $customerId === '') {
            $customerId = $this->createCustomer($customerEmail);
        }

        $params = [
            'customer' => $customerId,
            'items[0][price]' => $priceId,
        ];
        if ($paymentMethodId !== null && $paymentMethodId !== '') {
            $params['default_payment_method'] = $paymentMethodId;
        }

        $subscription = $this->request('POST', self::API_BASE . '/subscriptions', $params);
        $status = $this->mapStripeStatusToBilling($subscription['status'] ?? 'incomplete');
        $start = isset($subscription['current_period_start']) ? (int) $subscription['current_period_start'] : null;
        $end = isset($subscription['current_period_end']) ? (int) $subscription['current_period_end'] : null;

        return [
            'external_customer_id' => $customerId,
            'external_subscription_id' => (string) ($subscription['id'] ?? ''),
            'status' => $status,
            'current_period_start' => $start !== null ? date('c', $start) : '',
            'current_period_end' => $end !== null ? date('c', $end) : '',
        ];
    }

    /**
     * @inheritDoc
     */
    public function cancelSubscription(string $tenantId, bool $atPeriodEnd = true): void
    {
        $billing = $this->billingRepository->findByTenantId($tenantId);
        if ($billing === null || ($billing['external_subscription_id'] ?? '') === '') {
            return;
        }
        $subId = $billing['external_subscription_id'];
        if ($atPeriodEnd) {
            $this->request('POST', self::API_BASE . '/subscriptions/' . $subId, ['cancel_at_period_end' => 'true']);
        } else {
            $this->request('DELETE', self::API_BASE . '/subscriptions/' . $subId, []);
        }
    }

    /**
     * @inheritDoc
     */
    public function getSubscription(string $tenantId): ?array
    {
        $billing = $this->billingRepository->findByTenantId($tenantId);
        if ($billing === null || ($billing['external_subscription_id'] ?? '') === '') {
            return null;
        }
        $subId = $billing['external_subscription_id'];
        try {
            $data = $this->request('GET', self::API_BASE . '/subscriptions/' . $subId . '?expand=latest_invoice', []);
        } catch (\Throwable) {
            return [
                'external_customer_id' => $billing['external_customer_id'] ?? '',
                'external_subscription_id' => $subId,
                'plan_id' => $billing['plan_id'] ?? '',
                'status' => $billing['status'] ?? 'unknown',
                'current_period_start' => $billing['current_period_start'] ?? null,
                'current_period_end' => $billing['current_period_end'] ?? null,
                'cancel_at_period_end' => $billing['cancel_at_period_end'] ?? false,
            ];
        }
        $status = $this->mapStripeStatusToBilling($data['status'] ?? '');
        $start = isset($data['current_period_start']) ? (int) $data['current_period_start'] : null;
        $end = isset($data['current_period_end']) ? (int) $data['current_period_end'] : null;
        return [
            'external_customer_id' => (string) ($data['customer'] ?? $billing['external_customer_id'] ?? ''),
            'external_subscription_id' => $subId,
            'plan_id' => $billing['plan_id'] ?? '',
            'status' => $status,
            'current_period_start' => $start !== null ? date('c', $start) : null,
            'current_period_end' => $end !== null ? date('c', $end) : null,
            'cancel_at_period_end' => ($data['cancel_at_period_end'] ?? false) === true,
        ];
    }

    /**
     * @inheritDoc
     */
    public function listInvoices(string $tenantId): array
    {
        $billing = $this->billingRepository->findByTenantId($tenantId);
        if ($billing === null || ($billing['external_customer_id'] ?? '') === '') {
            return [];
        }
        $customerId = $billing['external_customer_id'];
        $data = $this->request('GET', self::API_BASE . '/invoices?customer=' . urlencode($customerId) . '&limit=20', []);
        $list = $data['data'] ?? [];
        $out = [];
        foreach ($list as $inv) {
            $out[] = [
                'id' => (string) ($inv['id'] ?? ''),
                'amount_cents' => (int) ($inv['amount_due'] ?? 0),
                'status' => (string) ($inv['status'] ?? ''),
                'created' => isset($inv['created']) ? date('c', (int) $inv['created']) : '',
                'pdf_url' => $inv['invoice_pdf'] ?? null,
            ];
        }
        return $out;
    }

    private function createCustomer(string $email): string
    {
        $data = $this->request('POST', self::API_BASE . '/customers', ['email' => $email]);
        $id = $data['id'] ?? '';
        if ($id === '') {
            throw new \RuntimeException('Stripe customer creation did not return id');
        }
        return $id;
    }

    /**
     * @param array<string, string> $params
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $params): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('cURL init failed');
        }

        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
        ];

        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($method === 'DELETE') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            }
            if ($params !== []) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            }
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('Stripe API request failed');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $decoded = ['error' => ['message' => $body]];
        }

        if ($code >= 400 && isset($decoded['error']['message'])) {
            throw new \RuntimeException('Stripe: ' . $decoded['error']['message']);
        }
        if ($code >= 400) {
            throw new \RuntimeException('Stripe API error: HTTP ' . $code);
        }

        return $decoded;
    }

    private function mapStripeStatusToBilling(string $stripeStatus): string
    {
        return match (strtolower($stripeStatus)) {
            'active' => 'active',
            'trialing' => 'active',
            'past_due', 'unpaid' => 'past_due',
            'canceled', 'cancelled', 'incomplete_expired' => 'cancelled',
            'incomplete', 'paused' => 'past_due',
            default => 'active',
        };
    }
}
