<?php

declare(strict_types=1);

namespace CurserPos\Domain\User;

final class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $status,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt
    ) {
    }
}
