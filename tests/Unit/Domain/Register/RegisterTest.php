<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Register;

use CurserPos\Domain\Register\Register;
use PHPUnit\Framework\TestCase;

final class RegisterTest extends TestCase
{
    public function testConstruction(): void
    {
        $now = new \DateTimeImmutable();
        $reg = new Register(
            'reg-1',
            'loc-1',
            'R1',
            'user-1',
            Register::STATUS_OPEN,
            100.0,
            null,
            $now,
            null,
            $now,
            $now
        );
        $this->assertSame('reg-1', $reg->id);
        $this->assertSame('loc-1', $reg->locationId);
        $this->assertSame('R1', $reg->registerId);
        $this->assertSame('user-1', $reg->assignedUserId);
        $this->assertSame(Register::STATUS_OPEN, $reg->status);
        $this->assertSame(100.0, $reg->openingCash);
        $this->assertNull($reg->closingCash);
        $this->assertSame($now, $reg->openedAt);
        $this->assertNull($reg->closedAt);
    }

    public function testStatusConstants(): void
    {
        $this->assertSame('open', Register::STATUS_OPEN);
        $this->assertSame('closed', Register::STATUS_CLOSED);
    }
}
