<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Billing;

use CurserPos\Domain\Billing\TenantBillingRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

#[AllowMockObjectsWithoutExpectations]
final class TenantBillingRepositoryTest extends TestCase
{
    public function testFindByTenantIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['t1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantBillingRepository($pdo);
        $this->assertNull($repo->findByTenantId('t1'));
    }

    public function testFindByTenantIdReturnsArrayWhenFound(): void
    {
        $row = [
            'id' => 'b1',
            'tenant_id' => 't1',
            'provider' => 'stripe',
            'external_customer_id' => 'cus_123',
            'external_subscription_id' => 'sub_123',
            'plan_id' => 'plan-basic',
            'status' => 'active',
            'current_period_start' => '2025-01-01T00:00:00+00:00',
            'current_period_end' => '2025-02-01T00:00:00+00:00',
            'cancel_at_period_end' => 'f',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['t1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantBillingRepository($pdo);
        $result = $repo->findByTenantId('t1');
        $this->assertIsArray($result);
        $this->assertSame('b1', $result['id']);
        $this->assertSame('t1', $result['tenant_id']);
        $this->assertSame('cus_123', $result['external_customer_id']);
        $this->assertSame('sub_123', $result['external_subscription_id']);
        $this->assertFalse($result['cancel_at_period_end']);
    }

    public function testFindByTenantIdConvertsDateTimeInterfaceInRow(): void
    {
        $row = [
            'id' => 'b1',
            'tenant_id' => 't1',
            'provider' => 'stripe',
            'external_customer_id' => null,
            'external_subscription_id' => '',
            'plan_id' => 'plan-basic',
            'status' => 'active',
            'current_period_start' => new \DateTimeImmutable('2025-01-01 00:00:00'),
            'current_period_end' => new \DateTimeImmutable('2025-02-01 00:00:00'),
            'cancel_at_period_end' => true,
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['t1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantBillingRepository($pdo);
        $result = $repo->findByTenantId('t1');
        $this->assertStringContainsString('2025-01-01', $result['current_period_start'] ?? '');
        $this->assertStringContainsString('2025-02-01', $result['current_period_end'] ?? '');
        $this->assertTrue($result['cancel_at_period_end']);
        $this->assertNull($result['external_customer_id']);
        $this->assertNull($result['external_subscription_id']);
    }

    public function testFindByTenantIdParsesCancelAtPeriodEndString(): void
    {
        $row = [
            'id' => 'b1',
            'tenant_id' => 't1',
            'provider' => 'stripe',
            'external_customer_id' => null,
            'external_subscription_id' => null,
            'plan_id' => 'plan-basic',
            'status' => 'active',
            'current_period_start' => null,
            'current_period_end' => null,
            'cancel_at_period_end' => 't',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['t1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantBillingRepository($pdo);
        $result = $repo->findByTenantId('t1');
        $this->assertTrue($result['cancel_at_period_end']);
    }

    public function testFindByTenantIdParsesCancelTrueAndOne(): void
    {
        $row = [
            'id' => 'b1',
            'tenant_id' => 't1',
            'provider' => 'stripe',
            'external_customer_id' => null,
            'external_subscription_id' => null,
            'plan_id' => 'plan-basic',
            'status' => 'active',
            'current_period_start' => null,
            'current_period_end' => null,
            'cancel_at_period_end' => '1',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['t1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantBillingRepository($pdo);
        $result = $repo->findByTenantId('t1');
        $this->assertTrue($result['cancel_at_period_end']);
    }

    public function testUpsertExecutesWithPositionalParams(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->equalTo([
            't1',
            'stripe',
            'cus_123',
            'sub_123',
            'plan-basic',
            'active',
            '2025-01-01',
            '2025-02-01',
            'f',
        ]));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantBillingRepository($pdo);
        $repo->upsert('t1', 'stripe', 'cus_123', 'sub_123', 'plan-basic', 'active', '2025-01-01', '2025-02-01', false);
    }

    public function testUpsertPassesTForCancelAtPeriodEndTrue(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[8] === 't';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantBillingRepository($pdo);
        $repo->upsert('t1', 'stripe', null, null, 'plan-basic', 'cancelled', null, null, true);
    }

    public function testUpdateByExternalSubscriptionIdExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->equalTo([
            'cancelled',
            '2025-01-01',
            '2025-02-01',
            't',
            'sub_xyz',
        ]));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantBillingRepository($pdo);
        $repo->updateByExternalSubscriptionId('sub_xyz', 'cancelled', '2025-01-01', '2025-02-01', true);
    }

    public function testGetTenantIdByExternalSubscriptionIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['sub_bad']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantBillingRepository($pdo);
        $this->assertNull($repo->getTenantIdByExternalSubscriptionId('sub_bad'));
    }

    public function testGetTenantIdByExternalSubscriptionIdReturnsTenantIdWhenFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['sub_123']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(['tenant_id' => 't1']);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantBillingRepository($pdo);
        $this->assertSame('t1', $repo->getTenantIdByExternalSubscriptionId('sub_123'));
    }

    public function testFindByTenantIdWithEmptyStartEndReturnsNullForPeriods(): void
    {
        $row = [
            'id' => 'b1',
            'tenant_id' => 't1',
            'provider' => 'stripe',
            'external_customer_id' => null,
            'external_subscription_id' => null,
            'plan_id' => 'plan-basic',
            'status' => 'active',
            'current_period_start' => '',
            'current_period_end' => '',
            'cancel_at_period_end' => 'true',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['t1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantBillingRepository($pdo);
        $result = $repo->findByTenantId('t1');
        $this->assertNull($result['current_period_start']);
        $this->assertNull($result['current_period_end']);
        $this->assertTrue($result['cancel_at_period_end']);
    }
}
