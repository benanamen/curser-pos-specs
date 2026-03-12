<?php

declare(strict_types=1);

namespace CurserPos\Service;

use CurserPos\Domain\Billing\BillingProviderInterface;
use CurserPos\Domain\Billing\TenantBillingRepository;
use CurserPos\Domain\Plan\PlanRepository;
use CurserPos\Domain\Tenant\TenantRepositoryInterface;

final class BillingService
{
    public function __construct(
        private readonly TenantBillingRepository $billingRepository,
        private readonly BillingProviderInterface $billingProvider,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly PlanRepository $planRepository
    ) {
    }

    /**
     * Create or update subscription for tenant. Uses existing Stripe customer if present.
     *
     * @return array{subscription: array<string, mixed>, plan: array<string, mixed>|null}
     */
    public function createSubscription(
        string $tenantId,
        string $planId,
        string $customerEmail,
        ?string $paymentMethodId = null
    ): array {
        $tenant = $this->tenantRepository->findById($tenantId);
        if ($tenant === null) {
            throw new \InvalidArgumentException('Tenant not found');
        }
        $plan = $this->planRepository->findById($planId);
        if ($plan === null) {
            throw new \InvalidArgumentException('Plan not found');
        }

        $existing = $this->billingRepository->findByTenantId($tenantId);
        $existingCustomerId = $existing !== null ? $existing['external_customer_id'] : null;

        $result = $this->billingProvider->createSubscription(
            $tenantId,
            $planId,
            $customerEmail,
            $paymentMethodId,
            $existingCustomerId
        );

        $this->billingRepository->upsert(
            $tenantId,
            'stripe',
            $result['external_customer_id'],
            $result['external_subscription_id'],
            $planId,
            $result['status'],
            $result['current_period_start'] !== '' ? $result['current_period_start'] : null,
            $result['current_period_end'] !== '' ? $result['current_period_end'] : null,
            false
        );

        $this->tenantRepository->update($tenantId, $tenant->name, $tenant->status, $planId);

        return [
            'subscription' => [
                'plan_id' => $planId,
                'status' => $result['status'],
                'current_period_start' => $result['current_period_start'],
                'current_period_end' => $result['current_period_end'],
            ],
            'plan' => $plan,
        ];
    }

    /**
     * Cancel subscription (at period end or immediately). Updates tenant_billing and optionally tenant plan.
     */
    public function cancelSubscription(string $tenantId, bool $atPeriodEnd = true): void
    {
        $billing = $this->billingRepository->findByTenantId($tenantId);
        if ($billing === null) {
            return;
        }

        $this->billingProvider->cancelSubscription($tenantId, $atPeriodEnd);

        if ($atPeriodEnd) {
            $this->billingRepository->upsert(
                $tenantId,
                $billing['provider'],
                $billing['external_customer_id'],
                $billing['external_subscription_id'],
                $billing['plan_id'],
                $billing['status'],
                $billing['current_period_start'],
                $billing['current_period_end'],
                true
            );
        } else {
            $this->billingRepository->upsert(
                $tenantId,
                $billing['provider'],
                $billing['external_customer_id'],
                $billing['external_subscription_id'],
                $billing['plan_id'],
                'cancelled',
                $billing['current_period_start'],
                $billing['current_period_end'],
                false
            );
        }
    }

    /**
     * Get billing summary for tenant (from tenant_billing + plan). No live call to provider.
     *
     * @return array{subscription: array<string, mixed>|null, plan: array<string, mixed>|null}
     */
    public function getBilling(string $tenantId): array
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if ($tenant === null) {
            throw new \InvalidArgumentException('Tenant not found');
        }

        $billing = $this->billingRepository->findByTenantId($tenantId);
        $plan = $this->planRepository->findById($tenant->planId);

        if ($billing === null) {
            return [
                'subscription' => null,
                'plan' => $plan,
            ];
        }

        return [
            'subscription' => [
                'plan_id' => $billing['plan_id'],
                'status' => $billing['status'],
                'current_period_start' => $billing['current_period_start'],
                'current_period_end' => $billing['current_period_end'],
                'cancel_at_period_end' => $billing['cancel_at_period_end'],
            ],
            'plan' => $plan,
        ];
    }

    /**
     * List invoices from provider (e.g. Stripe).
     *
     * @return list<array{id: string, amount_cents: int, status: string, created: string, pdf_url?: string}>
     */
    public function listInvoices(string $tenantId): array
    {
        return $this->billingProvider->listInvoices($tenantId);
    }
}
