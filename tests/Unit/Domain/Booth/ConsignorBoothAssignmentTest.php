<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Booth;

use CurserPos\Domain\Booth\ConsignorBoothAssignment;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class ConsignorBoothAssignmentTest extends TestCase
{
    public function testIsActiveReturnsTrueWhenEndedAtNull(): void
    {
        $start = new \DateTimeImmutable('2025-01-01');
        $assignment = new ConsignorBoothAssignment(
            'a1',
            'cons-1',
            'booth-1',
            $start,
            null,
            100.0,
            $start,
            $start
        );
        $this->assertTrue($assignment->isActive());
    }

    public function testIsActiveReturnsFalseWhenEndedAtSet(): void
    {
        $start = new \DateTimeImmutable('2025-01-01');
        $end = new \DateTimeImmutable('2025-06-01');
        $assignment = new ConsignorBoothAssignment(
            'a1',
            'cons-1',
            'booth-1',
            $start,
            $end,
            100.0,
            $start,
            $start
        );
        $this->assertFalse($assignment->isActive());
    }
}
