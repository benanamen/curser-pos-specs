<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Tenant\TenantRepositoryInterface;
use CurserPos\Http\RequestContextHolder;
use PerfectApp\Routing\Route;
use PDO;

final class StoreConfigController
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly PDO $pdo,
        private readonly \CurserPos\Service\AuditService $auditService
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/store/config', ['GET'])]
    public function get(string $slug): void
    {
        $tenant = $this->getTenantFromContext();
        if ($tenant === null) {
            $this->json(404, ['error' => 'Tenant not found']);
            return;
        }
        $settings = $tenant->settings;
        $taxConfig = $settings['tax_config'] ?? [];
        $taxRate = is_array($taxConfig) && isset($taxConfig['rate']) ? (float) $taxConfig['rate'] : 0.0;
        $this->json(200, [
            'store_name' => $tenant->name,
            'store_slug' => $tenant->slug,
            'store_address' => $settings['store_address'] ?? null,
            'store_phone' => $settings['store_phone'] ?? null,
            'store_email' => $settings['store_email'] ?? null,
            'default_commission_pct' => $settings['default_commission_pct'] ?? 50.0,
            'tax_config' => $taxConfig,
            'tax_rate' => $taxRate,
            'consignment_terms' => $settings['consignment_terms'] ?? null,
            'expiration_days' => $settings['expiration_days'] ?? 90,
        ]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/store/public', ['GET'])]
    public function publicInfo(string $slug): void
    {
        $tenant = $this->getTenantFromContext();
        if ($tenant === null) {
            $this->json(404, ['error' => 'Tenant not found']);
            return;
        }
        $settings = $tenant->settings;
        $this->json(200, [
            'store_name' => $tenant->name,
            'store_slug' => $tenant->slug,
            'store_address' => $settings['store_address'] ?? null,
            'store_phone' => $settings['store_phone'] ?? null,
            'store_email' => $settings['store_email'] ?? null,
        ]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/store/config', ['PUT', 'PATCH'])]
    public function update(string $slug): void
    {
        $tenant = $this->getTenantFromContext();
        if ($tenant === null) {
            $this->json(404, ['error' => 'Tenant not found']);
            return;
        }
        $input = $this->getJsonInput();
        $settings = $tenant->settings;

        if (array_key_exists('store_address', $input)) {
            $settings['store_address'] = $input['store_address'] !== '' && $input['store_address'] !== null ? (string) $input['store_address'] : null;
        }
        if (array_key_exists('store_phone', $input)) {
            $settings['store_phone'] = $input['store_phone'] !== '' && $input['store_phone'] !== null ? (string) $input['store_phone'] : null;
        }
        if (array_key_exists('store_email', $input)) {
            $settings['store_email'] = $input['store_email'] !== '' && $input['store_email'] !== null ? (string) $input['store_email'] : null;
        }
        if (array_key_exists('default_commission_pct', $input)) {
            $settings['default_commission_pct'] = (float) $input['default_commission_pct'];
        }
        if (array_key_exists('tax_config', $input) && is_array($input['tax_config'])) {
            $settings['tax_config'] = $input['tax_config'];
        }
        if (array_key_exists('tax_rate', $input)) {
            $taxConfig = $settings['tax_config'] ?? [];
            if (!is_array($taxConfig)) {
                $taxConfig = [];
            }
            $taxConfig['rate'] = (float) $input['tax_rate'];
            $settings['tax_config'] = $taxConfig;
        }
        if (array_key_exists('consignment_terms', $input)) {
            $settings['consignment_terms'] = $input['consignment_terms'] !== '' ? (string) $input['consignment_terms'] : null;
        }
        if (array_key_exists('expiration_days', $input)) {
            $settings['expiration_days'] = (int) $input['expiration_days'];
        }

        $stmt = $this->pdo->prepare('UPDATE public.tenants SET settings = ?::jsonb, updated_at = ? WHERE id = ?');
        $stmt->execute([json_encode($settings), (new \DateTimeImmutable())->format('Y-m-d H:i:s'), $tenant->id]);

        $context = RequestContextHolder::get();
        $this->auditService->log(
            $tenant->id,
            $context?->user?->id,
            $context?->supportUserId,
            'store.config_update',
            'tenant',
            $tenant->id,
            ['settings_keys' => array_keys($settings)],
            $context?->clientIp
        );

        $taxConfig = $settings['tax_config'] ?? [];
        $taxRate = is_array($taxConfig) && isset($taxConfig['rate']) ? (float) $taxConfig['rate'] : 0.0;
        $this->json(200, [
            'store_name' => $tenant->name,
            'store_slug' => $tenant->slug,
            'store_address' => $settings['store_address'] ?? null,
            'store_phone' => $settings['store_phone'] ?? null,
            'store_email' => $settings['store_email'] ?? null,
            'default_commission_pct' => $settings['default_commission_pct'] ?? 50.0,
            'tax_config' => $taxConfig,
            'tax_rate' => $taxRate,
            'consignment_terms' => $settings['consignment_terms'] ?? null,
            'expiration_days' => $settings['expiration_days'] ?? 90,
        ]);
    }

    private function getTenantFromContext(): ?object
    {
        $context = RequestContextHolder::get();
        return $context?->tenant;
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
