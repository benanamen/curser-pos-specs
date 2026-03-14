<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Infrastructure\Payment;

use CurserPos\Infrastructure\Payment\StripePaymentProcessor;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class StripePaymentProcessorTest extends TestCase
{
    public function testChargeWithTestKeyReturnsTestReference(): void
    {
        $processor = new StripePaymentProcessor('test');
        $ref = $processor->charge(1000, 'pm_123', 'Test');
        $this->assertStringStartsWith('ch_test_', $ref);
        $this->assertSame(24, strlen($ref));
    }

    public function testChargeWithEmptyKeyReturnsTestReference(): void
    {
        $processor = new StripePaymentProcessor('');
        $ref = $processor->charge(500, 'pm_abc', null);
        $this->assertStringStartsWith('ch_test_', $ref);
    }

    public function testChargeWithRealKeyThrows(): void
    {
        $processor = new StripePaymentProcessor('sk_live_xxx');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe SDK not installed');
        $processor->charge(1000, 'pm_123');
    }

    public function testRefundWithTestChargeReturnsTestRefundId(): void
    {
        $processor = new StripePaymentProcessor('test');
        $ref = $processor->refund('ch_test_abc123', null);
        $this->assertStringStartsWith('re_test_', $ref);
    }

    public function testRefundWithNonTestChargeThrows(): void
    {
        $processor = new StripePaymentProcessor('test');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe SDK not installed');
        $processor->refund('ch_live_xyz', null);
    }
}
