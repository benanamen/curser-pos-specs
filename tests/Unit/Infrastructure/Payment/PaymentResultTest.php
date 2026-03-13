<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Infrastructure\Payment;

use CurserPos\Infrastructure\Payment\PaymentResult;
use PHPUnit\Framework\TestCase;

final class PaymentResultTest extends TestCase
{
    public function testSuccessFactory(): void
    {
        $result = PaymentResult::success('ch_xyz');
        $this->assertTrue($result->success);
        $this->assertSame('ch_xyz', $result->reference);
        $this->assertNull($result->errorMessage);
    }

    public function testFailureFactory(): void
    {
        $result = PaymentResult::failure('Card declined');
        $this->assertFalse($result->success);
        $this->assertNull($result->reference);
        $this->assertSame('Card declined', $result->errorMessage);
    }

    public function testConstructorWithAllArgs(): void
    {
        $result = new PaymentResult(true, 'ref_1', null);
        $this->assertTrue($result->success);
        $this->assertSame('ref_1', $result->reference);
        $this->assertNull($result->errorMessage);
    }
}
