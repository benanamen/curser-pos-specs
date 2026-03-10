<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Audit;

use CurserPos\Domain\Audit\ActivityLogRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class ActivityLogRepositoryTest extends TestCase
{
    public function testLogInsertsRow(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->anything());

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ActivityLogRepository($pdo);
        $repo->log('t1', 'u1', null, 'auth.login', null, null, [], null);
    }

    public function testListByTenantReturnsRows(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturnOnConsecutiveCalls(
            [
                'id' => '1',
                'tenant_id' => 't1',
                'user_id' => 'u1',
                'support_user_id' => null,
                'action' => 'auth.login',
                'entity_type' => null,
                'entity_id' => null,
                'payload' => '{}',
                'ip' => null,
                'created_at' => '2025-01-01 00:00:00',
            ],
            false
        );

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ActivityLogRepository($pdo);
        $rows = $repo->listByTenant('t1');
        $this->assertCount(1, $rows);
        $this->assertSame('auth.login', $rows[0]['action']);
    }
}
