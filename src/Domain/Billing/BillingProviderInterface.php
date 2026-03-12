<?php

declare(strict_types=1);

namespace CurserPos\Domain\Billing;

/**
 * Provider-agnostic billing (Stripe, Paddle, Chargebee, etc.).
 */
interface BillingProviderInterface
{
    /**
     * Create or attach a subscription for the tenant.
     *
     * @param string|null $existingCustomerId If tenant already has a billing customer, pass it to reuse.
     * @return array{external_customer_id: string, external_subscription_id: string, status: string, current_period_start: string, current_period_end: string}
     */
    public function createSubscription(
        string $tenantId,
        string $planId,
        string $customerEmail,
        ?string $paymentMethodId = null,
        ?string $existingCustomerId = null
    ): array;

    /**
     * Cancel subscription (at period end or immediately).
     */
    public function cancelSubscription(string $tenantId, bool $atPeriodEnd = true): void;

    /**
     * Get current subscription for tenant, or null if none.
     *
     * @return array{external_customer_id: string, external_subscription_id: string, plan_id: string, status: string, current_period_start: ?string, current_period_end: ?string, cancel_at_period_end: bool}|null
     */
    public function getSubscription(string $tenantId): ?array;

    /**
     * List invoices for tenant (optional; provider-specific shape).
     *
     * @return list<array{id: string, amount_cents: int, status: string, created: string, pdf_url?: string}>
     */
    public function listInvoices(string $tenantId): array;
}
