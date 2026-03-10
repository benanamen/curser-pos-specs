<?php

declare(strict_types=1);

namespace CurserPos\Domain\Sale;

final class Sale
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VOIDED = 'voided';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_HELD = 'held';

    public function __construct(
        public readonly string $id,
        public readonly ?string $registerId,
        public readonly ?string $locationId,
        public readonly string $userId,
        public readonly string $saleNumber,
        public readonly float $subtotal,
        public readonly float $discountAmount,
        public readonly float $taxAmount,
        public readonly float $total,
        public readonly string $status,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt
    ) {
    }
}
