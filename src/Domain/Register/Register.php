<?php

declare(strict_types=1);

namespace CurserPos\Domain\Register;

final class Register
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public function __construct(
        public readonly string $id,
        public readonly string $locationId,
        public readonly string $registerId,
        public readonly ?string $assignedUserId,
        public readonly string $status,
        public readonly float $openingCash,
        public readonly ?float $closingCash,
        public readonly ?\DateTimeImmutable $openedAt,
        public readonly ?\DateTimeImmutable $closedAt,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt
    ) {
    }
}
