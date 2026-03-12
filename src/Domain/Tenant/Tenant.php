<?php

declare(strict_types=1);

namespace CurserPos\Domain\Tenant;

final class Tenant
{
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $status,
        public readonly string $planId,
        public readonly array $settings,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt
    ) {
    }

    public function schemaName(): string
    {
        return 'tenant_' . str_replace('-', '_', strtolower($this->id));
    }
}
