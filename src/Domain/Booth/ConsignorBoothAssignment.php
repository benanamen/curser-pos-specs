<?php

declare(strict_types=1);

namespace CurserPos\Domain\Booth;

final class ConsignorBoothAssignment
{
    public function __construct(
        public readonly string $id,
        public readonly string $consignorId,
        public readonly string $boothId,
        public readonly \DateTimeImmutable $startedAt,
        public readonly ?\DateTimeImmutable $endedAt,
        public readonly float $monthlyRent,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt
    ) {
    }

    public function isActive(): bool
    {
        return $this->endedAt === null;
    }
}
