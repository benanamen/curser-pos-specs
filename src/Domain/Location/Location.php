<?php

declare(strict_types=1);

namespace CurserPos\Domain\Location;

final class Location
{
    /**
     * @param array<int, array{rate: float, name: string, type?: string}> $taxRates
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $address,
        public readonly array $taxRates,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt
    ) {
    }
}
