<?php

declare(strict_types=1);

namespace CurserPos\Domain\Item;

final class Item
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_SOLD = 'sold';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_PICKED_UP = 'picked_up';
    public const STATUS_DONATED = 'donated';
    public const STATUS_WRITTEN_OFF = 'written_off';

    public function __construct(
        public readonly string $id,
        public readonly string $sku,
        public readonly ?string $barcode,
        public readonly ?string $consignorId,
        public readonly ?string $categoryId,
        public readonly ?string $locationId,
        public readonly ?string $description,
        public readonly ?string $size,
        public readonly ?string $condition,
        public readonly float $price,
        public readonly float $storeSharePct,
        public readonly float $consignorSharePct,
        public readonly string $status,
        public readonly \DateTimeImmutable $intakeDate,
        public readonly ?\DateTimeImmutable $expiryDate,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt
    ) {
    }
}
