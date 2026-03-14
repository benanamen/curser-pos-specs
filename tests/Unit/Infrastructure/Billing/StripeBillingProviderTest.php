<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Infrastructure\Billing;

use CurserPos\Domain\Billing\TenantBillingRepository;
use CurserPos\Infrastructure\Billing\StripeBillingProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class StripeBillingProviderTest extends TestCase
{
    public function testCreateSubscriptionThrowsWhenPlanNotConfigured(): void
    {
        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $provider = new StripeBillingProvider('sk_test_xxx', [], $billingRepo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No Stripe Price ID configured for plan');
        $provider->createSubscription('t1', 'plan-unknown', 'owner@example.com');
    }

    public function testCreateSubscriptionThrowsWhenPlanPriceIdEmpty(): void
    {
        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $provider = new StripeBillingProvider('sk_test_xxx', ['plan-basic' => ''], $billingRepo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No Stripe Price ID configured for plan');
        $provider->createSubscription('t1', 'plan-basic', 'owner@example.com');
    }

    public function testCancelSubscriptionNoopWhenNoBilling(): void
    {
        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $billingRepo->method('findByTenantId')->with('t1')->willReturn(null);

        $provider = new StripeBillingProvider('sk_test_xxx', ['plan-basic' => 'price_xxx'], $billingRepo);
        $provider->cancelSubscription('t1');
    }

    public function testCancelSubscriptionNoopWhenNoExternalSubscriptionId(): void
    {
        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $billingRepo->method('findByTenantId')->with('t1')->willReturn([
            'external_subscription_id' => '',
            'external_customer_id' => null,
        ] + array_fill_keys(['id', 'tenant_id', 'provider', 'plan_id', 'status', 'current_period_start', 'current_period_end', 'cancel_at_period_end'], null));

        $provider = new StripeBillingProvider('sk_test_xxx', [], $billingRepo);
        $provider->cancelSubscription('t1');
    }

    public function testGetSubscriptionReturnsNullWhenNoBilling(): void
    {
        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $billingRepo->method('findByTenantId')->with('t1')->willReturn(null);

        $provider = new StripeBillingProvider('sk_test_xxx', [], $billingRepo);
        $this->assertNull($provider->getSubscription('t1'));
    }

    public function testGetSubscriptionReturnsNullWhenNoExternalSubscriptionId(): void
    {
        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $billingRepo->method('findByTenantId')->with('t1')->willReturn([
            'external_subscription_id' => null,
            'external_customer_id' => null,
            'plan_id' => null,
            'status' => null,
            'current_period_start' => null,
            'current_period_end' => null,
            'cancel_at_period_end' => false,
        ] + array_fill_keys(['id', 'tenant_id', 'provider'], ''));

        $provider = new StripeBillingProvider('sk_test_xxx', [], $billingRepo);
        $this->assertNull($provider->getSubscription('t1'));
    }

    public function testGetSubscriptionReturnsFallbackWhenRequestThrows(): void
    {
        $billing = [
            'id' => 'b1',
            'tenant_id' => 't1',
            'provider' => 'stripe',
            'external_customer_id' => 'cus_xxx',
            'external_subscription_id' => 'sub_xxx',
            'plan_id' => 'plan-basic',
            'status' => 'active',
            'current_period_start' => '2025-01-01',
            'current_period_end' => '2025-02-01',
            'cancel_at_period_end' => false,
        ];
        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $billingRepo->method('findByTenantId')->with('t1')->willReturn($billing);

        $provider = new StripeBillingProvider('sk_test_xxx', [], $billingRepo);
        $result = $provider->getSubscription('t1');
        $this->assertIsArray($result);
        $this->assertSame('cus_xxx', $result['external_customer_id']);
        $this->assertSame('sub_xxx', $result['external_subscription_id']);
        $this->assertSame('plan-basic', $result['plan_id']);
    }
}
