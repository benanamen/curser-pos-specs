<?php

declare(strict_types=1);

namespace CurserPos\Domain\Billing;

use PDO;

final class TenantBillingRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * @return array{id: string, tenant_id: string, provider: string, external_customer_id: ?string, external_subscription_id: ?string, plan_id: string, status: string, current_period_start: ?string, current_period_end: ?string, cancel_at_period_end: bool}|null
     */
    public function findByTenantId(string $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, provider, external_customer_id, external_subscription_id, plan_id, status, current_period_start, current_period_end, cancel_at_period_end FROM tenant_billing WHERE tenant_id = ?'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return $this->rowToArray($row);
    }

    public function upsert(
        string $tenantId,
        string $provider,
        ?string $externalCustomerId,
        ?string $externalSubscriptionId,
        string $planId,
        string $status,
        ?string $currentPeriodStart = null,
        ?string $currentPeriodEnd = null,
        bool $cancelAtPeriodEnd = false
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_billing (tenant_id, provider, external_customer_id, external_subscription_id, plan_id, status, current_period_start, current_period_end, cancel_at_period_end, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?::timestamptz, ?::timestamptz, ?, CURRENT_TIMESTAMP)
             ON CONFLICT (tenant_id) DO UPDATE SET
               provider = EXCLUDED.provider,
               external_customer_id = COALESCE(EXCLUDED.external_customer_id, tenant_billing.external_customer_id),
               external_subscription_id = COALESCE(EXCLUDED.external_subscription_id, tenant_billing.external_subscription_id),
               plan_id = EXCLUDED.plan_id,
               status = EXCLUDED.status,
               current_period_start = COALESCE(EXCLUDED.current_period_start, tenant_billing.current_period_start),
               current_period_end = COALESCE(EXCLUDED.current_period_end, tenant_billing.current_period_end),
               cancel_at_period_end = EXCLUDED.cancel_at_period_end,
               updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            $tenantId,
            $provider,
            $externalCustomerId,
            $externalSubscriptionId,
            $planId,
            $status,
            $currentPeriodStart,
            $currentPeriodEnd,
            $cancelAtPeriodEnd ? 't' : 'f',
        ]);
    }

    public function updateByExternalSubscriptionId(
        string $externalSubscriptionId,
        string $status,
        ?string $currentPeriodStart = null,
        ?string $currentPeriodEnd = null,
        bool $cancelAtPeriodEnd = false
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE tenant_billing SET status = ?, current_period_start = COALESCE(?::timestamptz, current_period_start), current_period_end = COALESCE(?::timestamptz, current_period_end), cancel_at_period_end = ?, updated_at = CURRENT_TIMESTAMP WHERE external_subscription_id = ?'
        );
        $stmt->execute([
            $status,
            $currentPeriodStart,
            $currentPeriodEnd,
            $cancelAtPeriodEnd ? 't' : 'f',
            $externalSubscriptionId,
        ]);
    }

    public function getTenantIdByExternalSubscriptionId(string $externalSubscriptionId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT tenant_id FROM tenant_billing WHERE external_subscription_id = ?');
        $stmt->execute([$externalSubscriptionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? (string) $row['tenant_id'] : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: string, tenant_id: string, provider: string, external_customer_id: ?string, external_subscription_id: ?string, plan_id: string, status: string, current_period_start: ?string, current_period_end: ?string, cancel_at_period_end: bool}
     */
    private function rowToArray(array $row): array
    {
        $start = $row['current_period_start'] ?? null;
        $end = $row['current_period_end'] ?? null;
        if ($start instanceof \DateTimeInterface) {
            $start = $start->format('c');
        }
        if ($end instanceof \DateTimeInterface) {
            $end = $end->format('c');
        }
        $cancel = $row['cancel_at_period_end'] ?? false;
        if (is_string($cancel)) {
            $cancel = $cancel === 't' || $cancel === '1' || strtolower($cancel) === 'true';
        }
        return [
            'id' => (string) $row['id'],
            'tenant_id' => (string) $row['tenant_id'],
            'provider' => (string) $row['provider'],
            'external_customer_id' => isset($row['external_customer_id']) && $row['external_customer_id'] !== '' ? (string) $row['external_customer_id'] : null,
            'external_subscription_id' => isset($row['external_subscription_id']) && $row['external_subscription_id'] !== '' ? (string) $row['external_subscription_id'] : null,
            'plan_id' => (string) $row['plan_id'],
            'status' => (string) $row['status'],
            'current_period_start' => $start !== null && $start !== '' ? (string) $start : null,
            'current_period_end' => $end !== null && $end !== '' ? (string) $end : null,
            'cancel_at_period_end' => $cancel,
        ];
    }
}
