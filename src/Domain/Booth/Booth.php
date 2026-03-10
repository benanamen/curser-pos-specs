<?php

declare(strict_types=1);

namespace CurserPos\Domain\Booth;

final class Booth
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $locationId,
        public readonly float $monthlyRent,
        public readonly string $status,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt
    ) {
    }
}
