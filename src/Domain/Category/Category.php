<?php

declare(strict_types=1);

namespace CurserPos\Domain\Category;

final class Category
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $parentId,
        public readonly string $name,
        public readonly int $sortOrder,
        public readonly bool $taxExempt,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt
    ) {
    }
}
