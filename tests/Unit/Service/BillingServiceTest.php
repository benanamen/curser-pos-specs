<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Service;

use CurserPos\Domain\Billing\BillingProviderInterface;
use CurserPos\Domain\Billing\TenantBillingRepository;
use CurserPos\Domain\Plan\PlanRepository;
use CurserPos\Domain\Tenant\Tenant;
use CurserPos\Domain\Tenant\TenantRepositoryInterface;
use CurserPos\Service\BillingService;
use PHPUnit\Framework\TestCase;

final class BillingServiceTest extends TestCase
{
    public function testCreateSubscriptionThrowsWhenTenantNotFound(): void
    {
        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $provider = $this->createMock(BillingProviderInterface::class);
        $tenantRepo = $this->createMock(TenantRepositoryInterface::class);
        $tenantRepo->method('findById')->with('t1')->willReturn(null);
        $planRepo = $this->createMock(PlanRepository::class);

        $service = new BillingService($billingRepo, $provider, $tenantRepo, $planRepo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant not found');
        $service->createSubscription('t1', 'plan-basic', 'owner@example.com');
    }

    public function testCreateSubscriptionThrowsWhenPlanNotFound(): void
    {
        $tenant = new Tenant(
            't1',
            'mystore',
            'My Store',
            'active',
            'plan-basic',
            [],
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $provider = $this->createMock(BillingProviderInterface::class);
        $tenantRepo = $this->createMock(TenantRepositoryInterface::class);
        $tenantRepo->method('findById')->with('t1')->willReturn($tenant);
        $planRepo = $this->createMock(PlanRepository::class);
        $planRepo->method('findById')->with('plan-basic')->willReturn(null);

        $service = new BillingService($billingRepo, $provider, $tenantRepo, $planRepo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan not found');
        $service->createSubscription('t1', 'plan-basic', 'owner@example.com');
    }

    public function testCreateSubscriptionUsesExistingCustomerAndPersists(): void
    {
        $tenant = new Tenant(
            't1',
            'mystore',
            'My Store',
            'active',
            'plan-basic',
            [],
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        $existingBilling = [
            'tenant_id' => 't1',
            'provider' => 'stripe',
            'external_customer_id' => 'cus_123',
            'external_subscription_id' => 'sub_old',
            'plan_id' => 'plan-basic',
            'status' => 'active',
            'current_period_start' => null,
            'current_period_end' => null,
            'cancel_at_period_end' => false,
        ];

        $plan = [
            'id' => 'plan-basic',
            'name' => 'Basic',
            'tier' => 'lite',
            'item_limit' => 1000,
            'features' => [],
        ];

        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $billingRepo->method('findByTenantId')->with('t1')->willReturn($existingBilling);
        $billingRepo
            ->expects($this->once())
            ->method('upsert')
            ->with(
                't1',
                'stripe',
                'cus_789',
                'sub_123',
                'plan-basic',
                'active',
                null,
                null,
                false
            );

        $provider = $this->createMock(BillingProviderInterface::class);
        $provider
            ->expects($this->once())
            ->method('createSubscription')
            ->with('t1', 'plan-basic', 'owner@example.com', 'pm_123', 'cus_123')
            ->willReturn([
                'external_customer_id' => 'cus_789',
                'external_subscription_id' => 'sub_123',
                'status' => 'active',
                'current_period_start' => '',
                'current_period_end' => '',
            ]);

        $tenantRepo = $this->createMock(TenantRepositoryInterface::class);
        $tenantRepo->method('findById')->with('t1')->willReturn($tenant);
        $tenantRepo
            ->expects($this->once())
            ->method('update')
            ->with('t1', 'My Store', 'active', 'plan-basic');

        $planRepo = $this->createMock(PlanRepository::class);
        $planRepo->method('findById')->with('plan-basic')->willReturn($plan);

        $service = new BillingService($billingRepo, $provider, $tenantRepo, $planRepo);
        $result = $service->createSubscription('t1', 'plan-basic', 'owner@example.com', 'pm_123');

        $this->assertSame('plan-basic', $result['subscription']['plan_id']);
        $this->assertSame('active', $result['subscription']['status']);
        $this->assertSame('', $result['subscription']['current_period_start']);
        $this->assertSame('', $result['subscription']['current_period_end']);
        $this->assertSame($plan, $result['plan']);
    }

    public function testCancelSubscriptionNoopWhenNoBilling(): void
    {
        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $billingRepo->method('findByTenantId')->with('t1')->willReturn(null);
        $billingRepo->expects($this->never())->method('upsert');

        $provider = $this->createMock(BillingProviderInterface::class);
        $provider->expects($this->never())->method('cancelSubscription');

        $tenantRepo = $this->createMock(TenantRepositoryInterface::class);
        $planRepo = $this->createMock(PlanRepository::class);

        $service = new BillingService($billingRepo, $provider, $tenantRepo, $planRepo);
        $service->cancelSubscription('t1');
    }

    public function testCancelSubscriptionAtPeriodEndUpdatesCancelFlag(): void
    {
        $billing = [
            'tenant_id' => 't1',
            'provider' => 'stripe',
            'external_customer_id' => 'cus_123',
            'external_subscription_id' => 'sub_123',
            'plan_id' => 'plan-basic',
            'status' => 'active',
            'current_period_start' => '2025-01-01T00:00:00Z',
            'current_period_end' => '2025-02-01T00:00:00Z',
            'cancel_at_period_end' => false,
        ];

        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $billingRepo->method('findByTenantId')->with('t1')->willReturn($billing);
        $billingRepo
            ->expects($this->once())
            ->method('upsert')
            ->with(
                't1',
                'stripe',
                'cus_123',
                'sub_123',
                'plan-basic',
                'active',
                '2025-01-01T00:00:00Z',
                '2025-02-01T00:00:00Z',
                true
            );

        $provider = $this->createMock(BillingProviderInterface::class);
        $provider
            ->expects($this->once())
            ->method('cancelSubscription')
            ->with('t1', true);

        $tenantRepo = $this->createMock(TenantRepositoryInterface::class);
        $planRepo = $this->createMock(PlanRepository::class);

        $service = new BillingService($billingRepo, $provider, $tenantRepo, $planRepo);
        $service->cancelSubscription('t1', true);
    }

    public function testCancelSubscriptionImmediatelyMarksCancelled(): void
    {
        $billing = [
            'tenant_id' => 't1',
            'provider' => 'stripe',
            'external_customer_id' => 'cus_123',
            'external_subscription_id' => 'sub_123',
            'plan_id' => 'plan-basic',
            'status' => 'active',
            'current_period_start' => '2025-01-01T00:00:00Z',
            'current_period_end' => '2025-02-01T00:00:00Z',
            'cancel_at_period_end' => false,
        ];

        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $billingRepo->method('findByTenantId')->with('t1')->willReturn($billing);
        $billingRepo
            ->expects($this->once())
            ->method('upsert')
            ->with(
                't1',
                'stripe',
                'cus_123',
                'sub_123',
                'plan-basic',
                'cancelled',
                '2025-01-01T00:00:00Z',
                '2025-02-01T00:00:00Z',
                false
            );

        $provider = $this->createMock(BillingProviderInterface::class);
        $provider
            ->expects($this->once())
            ->method('cancelSubscription')
            ->with('t1', false);

        $tenantRepo = $this->createMock(TenantRepositoryInterface::class);
        $planRepo = $this->createMock(PlanRepository::class);

        $service = new BillingService($billingRepo, $provider, $tenantRepo, $planRepo);
        $service->cancelSubscription('t1', false);
    }

    public function testGetBillingThrowsWhenTenantNotFound(): void
    {
        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $provider = $this->createMock(BillingProviderInterface::class);
        $tenantRepo = $this->createMock(TenantRepositoryInterface::class);
        $tenantRepo->method('findById')->with('t1')->willReturn(null);
        $planRepo = $this->createMock(PlanRepository::class);

        $service = new BillingService($billingRepo, $provider, $tenantRepo, $planRepo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant not found');
        $service->getBilling('t1');
    }

    public function testGetBillingReturnsPlanOnlyWhenNoSubscription(): void
    {
        $tenant = new Tenant(
            't1',
            'mystore',
            'My Store',
            'active',
            'plan-basic',
            [],
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        $plan = [
            'id' => 'plan-basic',
            'name' => 'Basic',
            'tier' => 'lite',
            'item_limit' => 1000,
            'features' => [],
        ];

        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $billingRepo->method('findByTenantId')->with('t1')->willReturn(null);

        $provider = $this->createMock(BillingProviderInterface::class);

        $tenantRepo = $this->createMock(TenantRepositoryInterface::class);
        $tenantRepo->method('findById')->with('t1')->willReturn($tenant);

        $planRepo = $this->createMock(PlanRepository::class);
        $planRepo->method('findById')->with('plan-basic')->willReturn($plan);

        $service = new BillingService($billingRepo, $provider, $tenantRepo, $planRepo);
        $result = $service->getBilling('t1');

        $this->assertNull($result['subscription']);
        $this->assertSame($plan, $result['plan']);
    }

    public function testGetBillingReturnsSubscriptionAndPlan(): void
    {
        $tenant = new Tenant(
            't1',
            'mystore',
            'My Store',
            'active',
            'plan-basic',
            [],
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        $billing = [
            'tenant_id' => 't1',
            'provider' => 'stripe',
            'external_customer_id' => 'cus_123',
            'external_subscription_id' => 'sub_123',
            'plan_id' => 'plan-basic',
            'status' => 'active',
            'current_period_start' => '2025-01-01T00:00:00Z',
            'current_period_end' => '2025-02-01T00:00:00Z',
            'cancel_at_period_end' => true,
        ];

        $plan = [
            'id' => 'plan-basic',
            'name' => 'Basic',
            'tier' => 'lite',
            'item_limit' => 1000,
            'features' => [],
        ];

        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $billingRepo->method('findByTenantId')->with('t1')->willReturn($billing);

        $provider = $this->createMock(BillingProviderInterface::class);

        $tenantRepo = $this->createMock(TenantRepositoryInterface::class);
        $tenantRepo->method('findById')->with('t1')->willReturn($tenant);

        $planRepo = $this->createMock(PlanRepository::class);
        $planRepo->method('findById')->with('plan-basic')->willReturn($plan);

        $service = new BillingService($billingRepo, $provider, $tenantRepo, $planRepo);
        $result = $service->getBilling('t1');

        $this->assertSame('plan-basic', $result['subscription']['plan_id']);
        $this->assertSame('active', $result['subscription']['status']);
        $this->assertSame('2025-01-01T00:00:00Z', $result['subscription']['current_period_start']);
        $this->assertSame('2025-02-01T00:00:00Z', $result['subscription']['current_period_end']);
        $this->assertTrue($result['subscription']['cancel_at_period_end']);
        $this->assertSame($plan, $result['plan']);
    }

    public function testListInvoicesDelegatesToProvider(): void
    {
        $billingRepo = $this->createMock(TenantBillingRepository::class);
        $provider = $this->createMock(BillingProviderInterface::class);
        $provider
            ->expects($this->once())
            ->method('listInvoices')
            ->with('t1')
            ->willReturn([
                ['id' => 'in_1', 'amount_cents' => 1000, 'status' => 'paid', 'created' => '2025-01-01T00:00:00Z'],
            ]);

        $tenantRepo = $this->createMock(TenantRepositoryInterface::class);
        $planRepo = $this->createMock(PlanRepository::class);

        $service = new BillingService($billingRepo, $provider, $tenantRepo, $planRepo);
        $result = $service->listInvoices('t1');

        $this->assertCount(1, $result);
        $this->assertSame('in_1', $result[0]['id']);
    }
}

