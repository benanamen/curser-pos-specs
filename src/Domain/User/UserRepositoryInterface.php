<?php

declare(strict_types=1);

namespace CurserPos\Domain\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findById(string $id): ?User;

    public function getPasswordHash(string $userId): string;

    /**
     * @return array{tenant_id: string, tenant_slug: string, tenant_name: string}|null
     */
    public function getDefaultTenantForUser(string $userId): ?array;

    public function create(string $email, string $passwordHash): string;

    public function updatePassword(string $userId, string $passwordHash): void;
}
