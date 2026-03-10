<?php

declare(strict_types=1);

namespace CurserPos\Domain\Tenant;

interface TenantRepositoryInterface
{
    public function findBySlug(string $slug): ?Tenant;

    public function findById(string $id): ?Tenant;

    public function slugExists(string $slug): bool;
}
