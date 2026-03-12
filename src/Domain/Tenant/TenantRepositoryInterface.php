<?php

declare(strict_types=1);

namespace CurserPos\Domain\Tenant;

interface TenantRepositoryInterface
{
    public function findBySlug(string $slug): ?Tenant;

    public function findById(string $id): ?Tenant;

    public function slugExists(string $slug): bool;

    /**
     * @return list<Tenant>
     */
    public function list(): array;

    public function update(string $id, string $name, string $status, string $planId): void;
}
