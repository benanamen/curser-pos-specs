<?php

declare(strict_types=1);

namespace CurserPos\Domain\Consignor;

final class Consignor
{
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $address,
        public readonly float $defaultCommissionPct,
        public readonly ?\DateTimeImmutable $agreementSignedAt,
        public readonly string $status,
        public readonly ?string $notes,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt
    ) {
    }
}
