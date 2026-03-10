<?php

declare(strict_types=1);

namespace CurserPos\Domain\Consignor;

final class ConsignorBalance
{
    public function __construct(
        public readonly string $consignorId,
        public readonly float $balance,
        public readonly float $pendingSales,
        public readonly float $paidOut,
        public readonly \DateTimeImmutable $updatedAt
    ) {
    }
}
